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
        $total = 0;
        $campanhas = DB::table('campanha')->where('status', 'enviando')->whereNotNull('smtp_conta_id')->orderBy('id')->get();
        foreach ($campanhas as $c) {
            $conta = DB::table('smtp_conta')->where('id', $c->smtp_conta_id)->where('organizador_id', $c->organizador_id)->first();
            if (! $conta || ! $conta->ativo) {
                continue;
            }
            $naHora = DB::table('campanha_envio')->where('smtp_conta_id', $conta->id)->where('enviado_em', '>=', now()->subHour())->count();
            $vagas = min(self::POR_RODADA, $conta->limite_hora - $naHora);
            if ($vagas <= 0) {
                continue;
            }
            $org = Organizador::find($c->organizador_id);
            app(Tenant::class)->definir($org);
            $evento = Evento::with('local')->find($c->evento_id);
            $pendentes = DB::table('campanha_envio')->where('campanha_id', $c->id)->where('status', 'pendente')->orderBy('id')->limit($vagas)->get();
            foreach ($pendentes as $p) {
                // marca antes de mandar: se a rodada cair no meio, o e-mail não sai duas vezes
                if (! DB::table('campanha_envio')->where('id', $p->id)->where('status', 'pendente')->update(['status' => 'enviado', 'smtp_conta_id' => $conta->id, 'enviado_em' => now()])) {
                    continue;
                }
                if (! $evento || DB::table('email_descadastro')->where('organizador_id', $c->organizador_id)->where('email', $p->email)->exists()) {
                    DB::table('campanha_envio')->where('id', $p->id)->update(['status' => 'pulado', 'enviado_em' => null]);
                    continue;
                }
                $desc = self::linkDescadastro($c->organizador_id, $p->email);
                [$html, $texto] = self::corpo($evento, $org, $p->nome, $desc);
                $erro = SmtpEmpresa::enviar($conta, $p->email, $c->assunto, $html, $texto,
                    ['List-Unsubscribe' => '<'.$desc.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
                if ($erro) {
                    DB::table('campanha_envio')->where('id', $p->id)->update(['status' => 'erro', 'erro' => $erro]);
                    DB::table('smtp_conta')->where('id', $conta->id)->update(['ultimo_erro' => $erro]);
                } else {
                    $total++;
                }
            }
            self::atualizarContadores($c->id);
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
