<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Organizador;
use App\Support\Auditoria;
use App\Support\Convites;
use App\Support\Marketing;
use App\Support\Esquema;
use App\Support\SmtpEmpresa;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Divulgação → Convite por e-mail: escolhe evento, lista e conta SMTP, escreve o assunto e o cron envia aos poucos. */
class ConvitesController extends Controller
{
    public function index(Tenant $tenant)
    {
        $this->pode();

        return view('painel.convites.index', [
            'campanhas' => DB::table('campanha as c')->where('c.organizador_id', $tenant->id())->whereNotNull('c.uuid')
                ->leftJoin('evento as e', 'e.id', '=', 'c.evento_id')->leftJoin('lista_email as l', 'l.id', '=', 'c.lista_id')
                ->select('c.*', 'e.titulo as evento_titulo', 'l.nome as lista_nome')->orderByDesc('c.id')->limit(50)->get(),
            'eventos' => Evento::visiveis()->where('status', 'publicado')->where('data_fim', '>', now())->orderBy('data_inicio')->get(),
            'listas' => DB::table('lista_email as l')->where('l.organizador_id', $tenant->id())
                ->select('l.*', DB::raw('(SELECT COUNT(*) FROM lista_email_contato c WHERE c.lista_id = l.id) as total'))->orderBy('l.nome')->get(),
            'contas' => DB::table('smtp_conta')->where('organizador_id', $tenant->id())->where('ativo', 1)->orderBy('id')->get(),
            'marketing' => Marketing::ativo($tenant->id()),
            'limiteDia' => Marketing::limiteDia($tenant->id()),
        ]);
    }

    /** Pré-visualização do e-mail (HTML puro, abre num iframe). */
    public function previa(Request $request, Tenant $tenant)
    {
        $this->pode();
        $evento = Evento::visiveis()->where('uuid', $request->query('evento'))->with('local')->firstOrFail();
        [$html] = Convites::corpo($evento, Organizador::findOrFail($tenant->id()), 'Maria Souza', '#');

        return response($html);
    }

    public function teste(Request $request, Tenant $tenant)
    {
        $this->pode();
        [$evento, $conta, $d] = $this->dados($request, $tenant, false);
        [$html, $texto] = Convites::corpo($evento, Organizador::findOrFail($tenant->id()), auth()->user()->nome, Convites::linkDescadastro($tenant->id(), auth()->user()->email));
        $erro = SmtpEmpresa::enviar($conta, auth()->user()->email, '[Teste] '.$d['assunto'], $html, $texto);

        return back()->withInput()->with($erro ? 'erro' : 'ok', $erro ? 'O teste falhou: '.$erro : 'Teste enviado para '.auth()->user()->email.'.');
    }

    public function criar(Request $request, Tenant $tenant)
    {
        $this->pode();
        abort_unless(Marketing::ativo($tenant->id()), 403, 'O e-mail marketing não está liberado para a sua empresa.');
        [$evento, $conta, $d, $lista] = $this->dados($request, $tenant, true);
        $org = $tenant->id();
        $agendar = ($d['quando'] ?? 'agora') === 'agendar' ? \Illuminate\Support\Carbon::parse(utc_de_local($d['agendar_para']), 'UTC') : null;
        if ($agendar && $agendar->lte(now())) {
            return back()->withInput()->withErrors(['agendar_para' => 'Escolha uma data e hora no futuro.']);
        }
        if ($agendar && $agendar->gte($evento->data_fim)) {
            return back()->withInput()->withErrors(['agendar_para' => 'O agendamento tem de ser antes do fim do evento.']);
        }
        $id = DB::transaction(function () use ($evento, $conta, $d, $lista, $org, $agendar) {
            $id = DB::table('campanha')->insertGetId([
                'uuid' => (string) Str::uuid(), 'organizador_id' => $org, 'evento_id' => $evento->id, 'lista_id' => $lista->id,
                'smtp_conta_id' => $conta->todas ? null : $conta->id, 'nome' => mb_substr('Convite — '.$evento->titulo, 0, 100), 'canal' => 'email',
                'assunto' => $d['assunto'], 'corpo_texto' => '(montado do evento)', 'status' => $agendar ? 'agendada' : 'enviando',
                'agendada_para' => $agendar, 'iniciada_em' => $agendar ? null : now(),
                'criado_por' => auth()->id(),
            ]);
            // foto da lista neste momento, sem descadastrados (cada convite manda uma vez por e-mail; outro convite manda de novo)
            DB::statement('INSERT IGNORE INTO campanha_envio (campanha_id, organizador_id, email, nome)
                SELECT ?, ?, c.email, c.nome FROM lista_email_contato c
                WHERE c.lista_id = ?
                  AND NOT EXISTS (SELECT 1 FROM email_descadastro d WHERE d.organizador_id = ? AND d.email = c.email)',
                [$id, $org, $lista->id, $org]);
            $total = DB::table('campanha_envio')->where('campanha_id', $id)->count();
            DB::table('campanha')->where('id', $id)->update(['total_destinatarios' => $total] + ($total ? [] : ['status' => 'concluida', 'concluida_em' => now()]));

            return $id;
        });
        $c = DB::table('campanha')->find($id);
        Auditoria::registrar('campanha.criar', $evento, null, ['campanha' => $c->uuid, 'lista' => $lista->nome, 'total' => $c->total_destinatarios, 'assunto' => $d['assunto']]);
        $limiteDia = Marketing::limiteDia($tenant->id());
        $dias = (int) ceil($c->total_destinatarios / $limiteDia);

        if ($c->total_destinatarios && $agendar) {
            return redirect()->route('convites.agendamentos')->with('ok', 'Convite agendado para '.data_local($agendar, 'd/m/Y \\à\\s H:i')." ({$c->total_destinatarios} e-mail(s)).");
        }

        return redirect()->route('convites.show', $c->uuid)->with('ok', $c->total_destinatarios
            ? "Convite na fila para {$c->total_destinatarios} e-mail(s). A empresa envia até {$limiteDia} por dia".($dias > 1 ? " (cerca de $dias dias, somando os outros convites na fila)." : '.')
            : 'Ninguém para enviar: a lista está vazia ou todos se descadastraram.');
    }

    /** Fila: agendados, enviando e pausados, com a previsão de término e a explicação de como o envio funciona. */
    public function agendamentos(Tenant $tenant)
    {
        $this->pode();
        $fila = DB::table('campanha as c')->where('c.organizador_id', $tenant->id())->whereNotNull('c.uuid')
            ->whereIn('c.status', ['agendada', 'enviando', 'rascunho'])
            ->leftJoin('evento as e', 'e.id', '=', 'c.evento_id')->leftJoin('smtp_conta as s', 's.id', '=', 'c.smtp_conta_id')
            ->select('c.*', 'e.titulo as evento_titulo', 's.nome as conta_nome', 's.limite_hora',
                DB::raw("(SELECT COUNT(*) FROM campanha_envio x WHERE x.campanha_id = c.id AND x.status = 'pendente') as pendentes"))
            ->orderByRaw("FIELD(c.status, 'enviando', 'agendada', 'rascunho')")->orderBy('c.agendada_para')->get();

        return view('painel.convites.agendamentos', ['fila' => $fila, 'ultimaRodada' => cache('convites_ultima_rodada'), 'cap' => Convites::capacidade($tenant->id())]);
    }

    public function show(Tenant $tenant, string $uuid)
    {
        $this->pode();
        $c = $this->campanha($tenant, $uuid);
        Convites::atualizarContadores($c->id);
        $c = $this->campanha($tenant, $uuid);

        return view('painel.convites.show', [
            'c' => $c,
            'evento' => Evento::find($c->evento_id),
            'conta' => DB::table('smtp_conta')->find($c->smtp_conta_id),
            'lista' => DB::table('lista_email')->find($c->lista_id),
            'pendentes' => DB::table('campanha_envio')->where('campanha_id', $c->id)->where('status', 'pendente')->count(),
            'pulados' => DB::table('campanha_envio')->where('campanha_id', $c->id)->where('status', 'pulado')->count(),
            'ultimosErros' => DB::table('campanha_envio')->where('campanha_id', $c->id)->where('status', 'erro')->orderByDesc('id')->limit(10)->get(),
            'limiteDia' => Marketing::limiteDia($tenant->id()),
        ]);
    }

    public function situacao(Tenant $tenant, string $uuid, string $acao)
    {
        $this->pode();
        $c = $this->campanha($tenant, $uuid);
        $para = ['pausar' => ['enviando', 'rascunho'], 'retomar' => ['rascunho', 'enviando'], 'cancelar' => [null, 'cancelada']][$acao] ?? abort(404);
        $q = DB::table('campanha')->where('id', $c->id);
        $q = $para[0] ? $q->where('status', $para[0]) : $q->whereIn('status', ['enviando', 'rascunho', 'agendada']);
        $q->update(['status' => $para[1]]);
        if ($acao === 'cancelar') {
            DB::table('campanha_envio')->where('campanha_id', $c->id)->where('status', 'pendente')->update(['status' => 'pulado']);
        }
        Auditoria::registrar('campanha.'.$acao, Organizador::findOrFail($tenant->id()), null, ['campanha' => $c->uuid]);

        return back()->with('ok', ['pausar' => 'Envio pausado.', 'retomar' => 'Envio retomado.', 'cancelar' => 'Envio cancelado; os que faltavam não serão enviados.'][$acao]);
    }

    private function dados(Request $request, Tenant $tenant, bool $comLista): array
    {
        $d = $request->validate([
            'evento' => ['required', 'string'], 'smtp' => ['nullable', 'string'], 'assunto' => ['required', 'string', 'max:150'],
            'quando' => ['nullable', 'in:agora,agendar'], 'agendar_para' => ['nullable', 'required_if:quando,agendar', 'date'],
            'lista' => [$comLista ? 'required' : 'nullable', 'string'],
        ], ['smtp.required' => 'Cadastre uma conta de e-mail em Organização → Opções de marketing.'], ['smtp' => 'conta de envio']);
        $evento = Evento::visiveis()->where('uuid', $d['evento'])->with('local')->firstOrFail();
        // vazio = todas as contas ativas (divisão automática); para o teste usa a primeira
        $conta = DB::table('smtp_conta')->where('organizador_id', $tenant->id())->where('ativo', 1)
            ->when(! empty($d['smtp']), fn ($q) => $q->where('uuid', $d['smtp']))->orderBy('id')->first();
        if (! $conta) {
            throw \Illuminate\Validation\ValidationException::withMessages(['smtp' => 'Cadastre uma conta de e-mail ativa em Marketing → Contas de e-mail.']);
        }
        $conta->todas = empty($d['smtp']);
        $lista = $comLista ? (DB::table('lista_email')->where('organizador_id', $tenant->id())->where('uuid', $d['lista'])->first() ?? abort(422)) : null;

        return [$evento, $conta, $d, $lista];
    }

    private function campanha(Tenant $tenant, string $uuid): object
    {
        return DB::table('campanha')->where('organizador_id', $tenant->id())->where('uuid', $uuid)->first() ?? abort(404);
    }

    private function pode(): void
    {
        Gate::authorize('campanha.gerenciar');
        abort_unless(Esquema::temV018(), 404);
    }
}
