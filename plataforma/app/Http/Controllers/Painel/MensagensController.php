<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Support\Auditoria;
use App\Support\Email;
use App\Support\ModeloMensagem;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Divulgação → Mensagens: modelos de e-mail editáveis, comunicado para os participantes e histórico de envios. */
class MensagensController extends Controller
{
    /** Tipos editáveis hoje e as variáveis de cada um. */
    public const TIPOS = [
        'confirmacao_compra' => ['nome' => 'Confirmação da compra / inscrição', 'quando' => 'Enviado assim que o pedido é confirmado (e no reenvio).',
            'variaveis' => ['comprador_nome', 'pedido_codigo', 'evento_titulo', 'evento_data', 'local_nome', 'link_meus_ingressos', 'organizador_razao_social', 'organizador_documento', 'organizador_email_contato']],
        'lembrete_evento' => ['nome' => 'Lembrete do evento', 'quando' => 'Enviado antes do evento (entra em funcionamento com o agendador automático).',
            'variaveis' => ['participante_nome', 'evento_titulo', 'evento_data', 'local_nome', 'ingresso_numero']],
    ];

    public const LIMITE_COMUNICADO = 300;

    public function index(Request $request)
    {
        Gate::authorize('mensagem.gerenciar');
        $org = app(Tenant::class)->id();
        $eventos = Evento::visiveis()->orderByDesc('data_inicio')->get(['id', 'uuid', 'titulo', 'data_inicio']);
        $modelos = DB::table('modelo_mensagem')->whereIn('tipo', array_keys(self::TIPOS))->where('canal', 'email')->where('ativo', 1)
            ->where(fn ($q) => $q->whereNull('organizador_id')->orWhere('organizador_id', $org))->get();
        $historico = DB::table('mensagem_envio')->where('organizador_id', $org)->orderByDesc('id')->limit(40)
            ->get(['tipo', 'destinatario', 'assunto', 'status', 'erro', 'criado_em', 'enviado_em']);

        $tipo = array_key_exists((string) $request->query('tipo'), self::TIPOS) ? $request->query('tipo') : 'confirmacao_compra';
        $eventoSel = $request->filled('evento') ? $eventos->firstWhere('uuid', $request->query('evento')) : null;
        $doTipo = $modelos->where('tipo', $tipo);
        $proprio = $doTipo->first(fn ($m) => $m->organizador_id == $org && $m->evento_id == ($eventoSel?->id));
        $herdado = $eventoSel ? ($doTipo->first(fn ($m) => $m->organizador_id == $org && $m->evento_id === null) ?? $doTipo->firstWhere('organizador_id', null))
            : $doTipo->firstWhere('organizador_id', null);
        $personalizados = $modelos->where('organizador_id', $org)->map(fn ($m) => [$m->tipo, $m->evento_id]);

        return view('painel.mensagens.index', compact('eventos', 'historico', 'tipo', 'eventoSel', 'proprio', 'herdado', 'personalizados'));
    }

    public function salvarModelo(Request $request)
    {
        Gate::authorize('mensagem.gerenciar');
        $org = app(Tenant::class)->id();
        $eventos = Evento::visiveis()->pluck('id', 'uuid');
        $d = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'evento' => ['nullable', Rule::in($eventos->keys()->all())],
            'assunto' => ['required', 'string', 'max:150'],
            'corpo_texto' => ['required', 'string', 'max:5000'],
            'antecedencia_h' => ['nullable', 'integer', 'min:1', 'max:720'],
        ], [], ['corpo_texto' => 'texto da mensagem', 'antecedencia_h' => 'antecedência']);
        $eventoId = ($d['evento'] ?? null) ? $eventos[$d['evento']] : null;
        DB::table('modelo_mensagem')->updateOrInsert(
            ['organizador_id' => $org, 'evento_id' => $eventoId, 'tipo' => $d['tipo'], 'canal' => 'email'],
            ['assunto' => $d['assunto'], 'corpo_texto' => $d['corpo_texto'], 'antecedencia_h' => $d['tipo'] === 'lembrete_evento' ? ($d['antecedencia_h'] ?? 24) : null, 'ativo' => 1]);
        Auditoria::registrar('mensagem.modelo_salvar', app(Tenant::class)->organizador(), null, ['tipo' => $d['tipo'], 'evento_id' => $eventoId]);

        return redirect()->route('mensagens.index', ['tipo' => $d['tipo'], 'evento' => $d['evento'] ?? null])->with('ok', 'Modelo salvo. As próximas mensagens já usam este texto.');
    }

    public function restaurarModelo(Request $request)
    {
        Gate::authorize('mensagem.gerenciar');
        $org = app(Tenant::class)->id();
        $eventos = Evento::visiveis()->pluck('id', 'uuid');
        $d = $request->validate(['tipo' => ['required', Rule::in(array_keys(self::TIPOS))], 'evento' => ['nullable', Rule::in($eventos->keys()->all())]]);
        $eventoId = ($d['evento'] ?? null) ? $eventos[$d['evento']] : null;
        DB::table('modelo_mensagem')->where('organizador_id', $org)->where('tipo', $d['tipo'])->where('canal', 'email')
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId), fn ($q) => $q->whereNull('evento_id'))
            ->update(['ativo' => 0]); // desativa (os e-mails já enviados continuam ligados ao modelo)
        Auditoria::registrar('mensagem.modelo_restaurar', app(Tenant::class)->organizador(), null, ['tipo' => $d['tipo'], 'evento_id' => $eventoId]);

        return redirect()->route('mensagens.index', ['tipo' => $d['tipo'], 'evento' => $d['evento'] ?? null])
            ->with('ok', $eventoId ? 'Texto próprio do evento removido: vale o texto da organização (ou o padrão).' : 'Texto padrão restaurado.');
    }

    /** Comunicado avulso para quem tem ingresso válido no evento (comprador ou participantes). */
    public function comunicado(Request $request)
    {
        Gate::authorize('mensagem.gerenciar');
        $eventos = Evento::visiveis()->pluck('id', 'uuid');
        $d = $request->validate([
            'evento' => ['required', Rule::in($eventos->keys()->all())],
            'destino' => ['required', 'in:compradores,participantes'],
            'assunto' => ['required', 'string', 'max:150'],
            'texto' => ['required', 'string', 'max:5000'],
        ], [], ['texto' => 'mensagem']);
        $evento = Evento::find($eventos[$d['evento']]);
        $org = app(Tenant::class)->organizador();

        $base = Ingresso::where('evento_id', $evento->id)->whereIn('status', ['valido', 'utilizado']);
        $emails = $d['destino'] === 'compradores'
            ? DB::table('pedido as p')->join('comprador as c', 'c.id', '=', 'p.comprador_id')->whereIn('p.id', (clone $base)->select('pedido_id'))->distinct()->pluck('c.email')
            : DB::table('participante')->whereIn('ingresso_id', (clone $base)->select('id'))->whereNotNull('email')->distinct()->pluck('email');
        $emails = $emails->map(fn ($e) => mb_strtolower(trim($e)))->filter()->unique()->values();
        if ($emails->isEmpty()) {
            return back()->withInput()->withErrors(['comunicado' => 'Ninguém com e-mail para receber neste evento.']);
        }
        if ($emails->count() > self::LIMITE_COMUNICADO) {
            return back()->withInput()->withErrors(['comunicado' => 'Este envio teria '.$emails->count().' destinatários. Por enquanto o limite é '.self::LIMITE_COMUNICADO.' por envio (limite das caixas de e-mail). Fale com o suporte.']);
        }

        $html = Email::layout($d['assunto'], '<p>'.nl2br(e($d['texto'])).'</p><p style="font-size:13px;color:#667085">Você recebe esta mensagem porque tem ingresso para '.e($evento->titulo).'.</p>',
            null, null, '<b>'.e($org->razao_social).'</b> · '.e($org->documentoFormatado()).($org->email_contato ? ' · '.e($org->email_contato) : ''));
        foreach ($emails as $email) {
            Email::operacao('comunicado', $email, \App\Support\Notificacoes::assuntoEvento($evento->titulo, $d['assunto']), $html, $d['texto'], ['organizador_id' => $org->id, 'prioridade' => 5]);
        }
        Auditoria::registrar('mensagem.comunicado', $evento, null, ['destino' => $d['destino'], 'quantidade' => $emails->count(), 'assunto' => $d['assunto']]);

        return redirect()->route('mensagens.index')->with('ok', "Comunicado enviado para {$emails->count()} e-mail(s).");
    }

    /** Pré-visualização (JSON) com dados de exemplo. */
    public function previa(Request $request)
    {
        Gate::authorize('mensagem.gerenciar');
        $d = $request->validate(['assunto' => ['nullable', 'string', 'max:150'], 'corpo_texto' => ['nullable', 'string', 'max:5000']]);
        $org = app(Tenant::class)->organizador();
        $exemplo = ['comprador_nome' => 'Maria Souza', 'participante_nome' => 'Maria Souza', 'pedido_codigo' => 'FFAB12CD', 'evento_titulo' => 'Seu evento',
            'evento_data' => now()->addDays(7)->format('d/m/Y \à\s 20:00'), 'local_nome' => 'Local do evento', 'ingresso_numero' => 'FF-0001-000001',
            'link_meus_ingressos' => route('comprador.ingressos'), 'organizador_razao_social' => $org->razao_social,
            'organizador_documento' => $org->documentoFormatado(), 'organizador_email_contato' => $org->email_contato];
        $trocar = fn ($t) => preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => $exemplo[$m[1]] ?? $m[0], (string) $t);

        return response()->json(['assunto' => $trocar($d['assunto'] ?? ''), 'texto' => $trocar($d['corpo_texto'] ?? '')]);
    }
}
