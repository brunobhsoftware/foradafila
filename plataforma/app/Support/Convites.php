<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Organizador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Convite por e-mail (Divulgação → Convite por e-mail, V018). O e-mail é montado do evento (imagem, data, local, descrição e botão
 * para a página); só o assunto muda. Os destinatários ficam em campanha_envio e saem aos poucos pelo cron (convites:enviar),
 * respeitando o limite por hora de cada conta SMTP da empresa. Todo convite leva "Não quero mais receber" (email_descadastro).
 */
class Convites
{
    /** No máximo por conta em cada rodada do cron (1 por minuto), para a rodada não passar do tempo da hospedagem. */
    public const POR_RODADA = 40;

    /** Limite padrão da plataforma (a Administração ajusta por empresa, `App\Support\Marketing`). */
    public const LIMITE_DIA = Marketing::LIMITE_PADRAO;

    public static function linkDescadastro(int $orgId, string $email): string
    {
        // assinatura só do caminho: o cron (linha de comando) e o site podem ver hosts diferentes
        return url(URL::signedRoute('descadastro', ['org' => $orgId, 'email' => base64_encode(mb_strtolower($email))], null, false));
    }

    /** @return array{0: string, 1: string} html e texto */
    public static function corpo(Evento $evento, Organizador $org, ?string $nome, string $linkDescadastro): array
    {
        $local = $evento->local ? trim($evento->local->nome.' · '.$evento->local->cidade.'/'.$evento->local->uf, ' ·/') : Evento::FORMATOS[$evento->formato] ?? '';
        $quando = data_local($evento->data_inicio, 'd/m/Y \à\s H:i');
        $link = $evento->linkPublico();
        $img = $evento->imagem_capa_url ? url($evento->imagem_capa_url) : null;
        $descricao = $evento->resumo ?: $evento->descricao;
        $blocos = ($img ? '<p style="margin:0 0 16px"><a href="'.e($link).'"><img src="'.e($img).'" alt="'.e($evento->titulo).'" width="544" style="width:100%;max-width:544px;height:auto;border-radius:10px;display:block"></a></p>' : '')
            .($nome ? '<p>Olá, '.e(strtok($nome, ' ')).'!</p>' : '')
            .'<p><b>'.e($org->nomeExibicao()).'</b> convida você para <b>'.e($evento->titulo).'</b>.</p>'
            .'<p style="margin:0">📅 '.e($quando).'<br>📍 '.e($local).'</p>'
            .($descricao ? '<p style="white-space:pre-line">'.e(mb_strimwidth($descricao, 0, 1200, '…')).'</p>' : '');
        $rodape = 'Você recebeu este convite de '.e($org->nomeExibicao()).' pelo Fora da Fila. '
            .'<a href="'.e($linkDescadastro).'" style="color:#667085">Não quero mais receber e-mails desta empresa</a>.';
        $html = Email::layout($evento->titulo, $blocos, 'Garantir meu ingresso', $link, $rodape);
        $texto = "{$org->nomeExibicao()} convida você para {$evento->titulo}.\n$quando — $local\n\n".($descricao ? mb_strimwidth($descricao, 0, 1200, '…')."\n\n" : '')
            ."Ingressos: $link\n\nNão quero mais receber: $linkDescadastro";

        return [$html, $texto];
    }

    /** Uma rodada do cron: manda os pendentes das campanhas em andamento. Devolve quantos e-mails saíram. */
    public static function rodada(): int
    {
        if (! Esquema::temV018()) {
            return 0;
        }
        cache(['convites_ultima_rodada' => now()], now()->addDay());
        // agendadas cuja hora chegou passam a enviar
        DB::table('campanha')->where('status', 'agendada')->where('agendada_para', '<=', now())->update(['status' => 'enviando', 'iniciada_em' => now()]);
        $total = 0;
        $orgs = DB::table('campanha')->where('status', 'enviando')->whereNotNull('uuid')->distinct()->pluck('organizador_id');
        foreach ($orgs as $orgId) {
            $total += self::rodadaDaEmpresa((int) $orgId);
        }

        return $total;
    }

    /** Início do dia de hoje no horário de Brasília, em UTC (o limite diário vira à meia-noite local). */
    public static function inicioDoDia(): \Illuminate\Support\Carbon
    {
        return now('America/Sao_Paulo')->startOfDay()->utc();
    }

    /** Quantos e-mails a empresa ainda pode mandar hoje e a cota de cada conta ativa (LIMITE_DIA ÷ nº de contas). */
    public static function capacidade(int $orgId): array
    {
        $contas = DB::table('smtp_conta')->where('organizador_id', $orgId)->where('ativo', 1)->orderBy('id')->get();
        $hoje = DB::table('campanha_envio')->where('organizador_id', $orgId)->where('enviado_em', '>=', self::inicioDoDia())->count();
        // recurso liberado e limite diário vêm da Administração (por empresa); desligado = não envia nada
        $ativo = Marketing::ativo($orgId);
        $limite = Marketing::limiteDia($orgId);

        return ['ativo' => $ativo, 'limite' => $limite, 'contas' => $contas,
            'cota' => $ativo && $contas->count() ? intdiv($limite, $contas->count()) : 0,
            'hoje' => $hoje, 'restante' => $ativo ? max(0, $limite - $hoje) : 0];
    }

    private static function rodadaDaEmpresa(int $orgId): int
    {
        $cap = self::capacidade($orgId);
        if (! $cap['ativo'] || ! $cap['contas']->count() || $cap['restante'] <= 0) {
            return 0;
        }
        $org = Organizador::find($orgId);
        app(Tenant::class)->definir($org);
        $restante = $cap['restante'];
        $total = 0;
        $eventos = [];
        $campanhas = [];
        foreach ($cap['contas'] as $conta) {
            $dia = DB::table('campanha_envio')->where('smtp_conta_id', $conta->id)->where('enviado_em', '>=', self::inicioDoDia())->count();
            $hora = DB::table('campanha_envio')->where('smtp_conta_id', $conta->id)->where('enviado_em', '>=', now()->subHour())->count();
            $vagas = min(self::POR_RODADA, $conta->limite_hora - $hora, $cap['cota'] - $dia, $restante);
            if ($vagas <= 0) {
                continue;
            }
            // pendentes das campanhas que usam "todas as contas" ou esta conta, na ordem em que foram criadas
            $pendentes = DB::table('campanha_envio as e')->join('campanha as c', 'c.id', '=', 'e.campanha_id')
                ->where('c.organizador_id', $orgId)->where('c.status', 'enviando')->where('e.status', 'pendente')
                ->where(fn ($q) => $q->whereNull('c.smtp_conta_id')->orWhere('c.smtp_conta_id', $conta->id))
                ->orderBy('c.id')->orderBy('e.id')->limit($vagas)->select('e.*', 'c.assunto', 'c.evento_id')->get();
            foreach ($pendentes as $p) {
                // marca antes de mandar: se a rodada cair no meio, o e-mail não sai duas vezes
                if (! DB::table('campanha_envio')->where('id', $p->id)->where('status', 'pendente')->update(['status' => 'enviado', 'smtp_conta_id' => $conta->id, 'enviado_em' => now()])) {
                    continue;
                }
                $campanhas[$p->campanha_id] = true;
                $restante--;
                $evento = $eventos[$p->evento_id] ??= Evento::with('local')->find($p->evento_id);
                if (! $evento || DB::table('email_descadastro')->where('organizador_id', $orgId)->where('email', $p->email)->exists()) {
                    DB::table('campanha_envio')->where('id', $p->id)->update(['status' => 'pulado', 'enviado_em' => null]);
                    $restante++;
                    continue;
                }
                $desc = self::linkDescadastro($orgId, $p->email);
                [$html, $texto] = self::corpo($evento, $org, $p->nome, $desc);
                $erro = SmtpEmpresa::enviar($conta, $p->email, $p->assunto, $html, $texto,
                    ['List-Unsubscribe' => '<'.$desc.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
                if ($erro) {
                    DB::table('campanha_envio')->where('id', $p->id)->update(['status' => 'erro', 'erro' => $erro]);
                    DB::table('smtp_conta')->where('id', $conta->id)->update(['ultimo_erro' => $erro]);
                } else {
                    $total++;
                }
            }
        }
        foreach (array_keys($campanhas) as $id) {
            self::atualizarContadores($id);
        }

        return $total;
    }

    public static function atualizarContadores(int $campanhaId): void
    {
        $n = DB::table('campanha_envio')->where('campanha_id', $campanhaId)->selectRaw("SUM(status='enviado') e, SUM(status='erro') x, SUM(status='pendente') p")->first();
        $dados = ['enviados' => (int) $n->e, 'erros' => (int) $n->x];
        if ((int) $n->p === 0) {
            $dados += ['status' => 'concluida', 'concluida_em' => now()];
        }
        DB::table('campanha')->where('id', $campanhaId)->where('status', 'enviando')->update($dados);
    }
}
