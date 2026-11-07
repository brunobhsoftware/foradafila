<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\ContaPagamento;
use App\Models\Evento;
use App\Models\EventoCategoria;
use App\Models\Local;
use App\Support\Auditoria;
use App\Support\ImagemEvento;
use App\Support\RegraDoBanco;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EventoController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('evento.ver');

        $eventos = Evento::visiveis()->with('local')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('busca'), fn ($q) => $q->where('titulo', 'like', '%'.$request->input('busca').'%'))
            ->orderByRaw("FIELD(status, 'publicado', 'pausado', 'rascunho', 'encerrado', 'cancelado')")
            ->orderBy('data_inicio')
            ->paginate(15)->withQueryString();

        $indicadores = DB::table('vw_dashboard_evento')->whereIn('evento_id', $eventos->pluck('id'))->get()->keyBy('evento_id');

        return view('painel.eventos.index', compact('eventos', 'indicadores'));
    }

    public function create()
    {
        Gate::authorize('evento.criar');

        return view('painel.eventos.form', $this->dadosFormulario(new Evento([
            'formato' => 'presencial', 'classificacao' => 'livre', 'visibilidade' => 'publico',
            'max_ingressos_pedido' => 10, 'exige_cpf_participante' => true, 'nominal' => true,
            'permite_transferencia' => true, 'transferencia_ate_h' => 24, 'permite_reentrada' => false,
        ])));
    }

    public function store(Request $request)
    {
        Gate::authorize('evento.criar');
        [$dadosEvento, $dadosLocal] = $this->validar($request);

        $evento = RegraDoBanco::executar(fn () => DB::transaction(function () use ($dadosEvento, $dadosLocal, $request) {
            $local = $dadosLocal ? Local::create($dadosLocal) : null;
            $evento = Evento::create($dadosEvento + [
                'local_id' => $local?->id,
                'slug' => Evento::slugDisponivel($dadosEvento['titulo']),
                'criado_por' => $request->user()->id,
            ]);
            // O nome do arquivo usa o id e o uuid do evento, então a imagem é gravada depois da criação.
            if ($request->hasFile('imagem')) {
                $evento->forceFill(['imagem_capa_url' => ImagemEvento::salvar($evento, $request->file('imagem'))])->save();
            }
            Auditoria::registrar('evento.criar', $evento);
            self::registrarDeclaracao($evento);

            return $evento;
        }), 'titulo');

        return redirect()->route('eventos.show', $evento)
            ->with('ok', 'Evento criado como rascunho. Agora cadastre os tipos de ingresso e os lotes.');
    }

    public function show(Evento $evento)
    {
        Gate::authorize('evento.ver');
        $this->garantirAcesso($evento);

        $evento->load(['local', 'categoria', 'tipos.lotes', 'organizador']);
        $indicadores = $evento->indicadores();
        $checklist = $evento->status === 'rascunho' ? $evento->checklistPublicacao() : [];

        return view('painel.eventos.show', compact('evento', 'indicadores', 'checklist'));
    }

    public function edit(Evento $evento)
    {
        Gate::authorize('evento.criar');
        $this->garantirAcesso($evento);
        abort_if(in_array($evento->status, ['encerrado', 'cancelado']), 403, 'Evento encerrado ou cancelado não pode ser editado.');

        return view('painel.eventos.form', $this->dadosFormulario($evento->load('local')));
    }

    public function update(Request $request, Evento $evento)
    {
        Gate::authorize('evento.criar');
        $this->garantirAcesso($evento);
        abort_if(in_array($evento->status, ['encerrado', 'cancelado']), 403);
        [$dadosEvento, $dadosLocal] = $this->validar($request, $evento);

        RegraDoBanco::executar(fn () => DB::transaction(function () use ($evento, $dadosEvento, $dadosLocal, $request) {
            $antes = $evento->getAttributes();
            if ($request->hasFile('imagem')) {
                $dadosEvento['imagem_capa_url'] = ImagemEvento::salvar($evento, $request->file('imagem'));
            } elseif ($request->boolean('remover_imagem')) {
                ImagemEvento::remover($evento);
                $dadosEvento['imagem_capa_url'] = null;
            }
            if ($dadosLocal) {
                $local = $evento->local ?? new Local;
                $local->fill($dadosLocal)->save();
                $dadosEvento['local_id'] = $local->id;
            } else {
                $dadosEvento['local_id'] = null;
            }
            $evento->update($dadosEvento);
            Auditoria::registrar('evento.editar', $evento, $antes);
            self::registrarDeclaracao($evento);
        }), 'titulo');

        return redirect()->route('eventos.show', $evento)->with('ok', 'Evento atualizado.');
    }

    /** Publicar, pausar, retomar, encerrar ou cancelar. As regras finais são dos triggers do banco. */
    public function status(Request $request, Evento $evento)
    {
        $this->garantirAcesso($evento);
        $novo = $request->input('status');
        $permitidas = Evento::TRANSICOES[$evento->status] ?? [];
        abort_unless(isset($permitidas[$novo]), 422, 'Mudança de status não permitida.');
        Gate::authorize($permitidas[$novo]);

        $dados = ['status' => $novo];
        if ($novo === 'cancelado') {
            $dados['cancelamento_motivo'] = $request->validate([
                'cancelamento_motivo' => ['required', 'string', 'min:5', 'max:500'],
            ])['cancelamento_motivo'];
        }

        RegraDoBanco::executar(function () use ($evento, $dados) {
            $antes = $evento->getAttributes();
            $evento->forceFill($dados)->save();
            Auditoria::registrar('evento.status.'.$dados['status'], $evento, $antes, $evento->fresh()->getAttributes());
        }, 'status');

        $mensagens = [
            'publicado' => 'Evento publicado! As vendas seguem a agenda de cada lote.',
            'pausado' => 'Vendas pausadas.',
            'encerrado' => 'Evento encerrado.',
            'cancelado' => 'Evento cancelado.',
        ];

        return back()->with('ok', $mensagens[$novo] ?? 'Status atualizado.');
    }

    /** Texto da declaração que o produtor marca ao criar ou editar o evento. */
    public const DECLARACAO_CANCELAMENTO = 'Declaro que, em caso de cancelamento do evento, é responsabilidade do produtor (organizador) '
        .'devolver todos os valores pagos aos clientes, sob as penas das leis de defesa do consumidor.';

    /** Prova da declaração: quem marcou, quando, de qual IP e o texto exato (tabela auditoria). */
    private static function registrarDeclaracao(Evento $evento): void
    {
        \App\Models\Auditoria::create([
            'organizador_id' => $evento->organizador_id, 'usuario_id' => auth()->id(),
            'acao' => 'evento.declaracao_cancelamento', 'tabela' => 'evento', 'registro_id' => $evento->id,
            'depois' => ['texto' => self::DECLARACAO_CANCELAMENTO, 'user_agent' => mb_substr((string) request()->userAgent(), 0, 255)],
            'ip' => request()->ip(),
        ]);
    }

    private function garantirAcesso(Evento $evento): void
    {
        abort_unless(Evento::visiveis()->whereKey($evento->id)->exists(), 404);
    }

    private function dadosFormulario(Evento $evento): array
    {
        return [
            'evento' => $evento,
            'categorias' => EventoCategoria::where('ativo', 1)->orderBy('nome')->pluck('nome', 'id'),
            'contas' => ContaPagamento::whereIn('status', ['ativa', 'homologada', 'em_homologacao', 'conectada'])->pluck('apelido', 'id'),
        ];
    }

    /** @return array{0: array, 1: ?array} dados do evento e do local */
    private function validar(Request $request, ?Evento $evento = null): array
    {
        $presencial = $request->input('formato') !== 'online';

        $v = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'categoria_id' => ['nullable', Rule::exists('evento_categoria', 'id')],
            'resumo' => ['nullable', 'string', 'max:300'],
            'descricao' => ['nullable', 'string', 'max:20000'],
            'formato' => ['required', Rule::in(array_keys(Evento::FORMATOS))],
            'link_online' => ['nullable', 'url', 'max:255', Rule::requiredIf($request->input('formato') !== 'presencial')],
            'classificacao' => ['required', Rule::in(array_keys(Evento::CLASSIFICACOES))],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'abertura_portoes' => ['nullable', 'date', 'before_or_equal:data_inicio'],
            'capacidade' => [\App\Support\Esquema::capacidadeOpcional() ? 'nullable' : 'required', 'integer', 'min:1', 'max:10000000'],
            // Declaração do produtor sobre cancelamento (pedido do Bruno, 07/11/2026), registrada na auditoria.
            'aceite_cancelamento' => ['accepted'],
            'visibilidade' => ['required', Rule::in(array_keys(Evento::VISIBILIDADES))],
            'conta_pagamento_id' => ['nullable', Rule::exists('conta_pagamento', 'id')->where('organizador_id', app(\App\Support\Tenant::class)->id())],
            'max_ingressos_pedido' => ['required', 'integer', 'min:1', 'max:100'],
            'max_ingressos_cpf' => ['nullable', 'integer', 'min:1', 'max:100'],
            'transferencia_ate_h' => ['required', 'integer', 'min:0', 'max:720'],
            'politica_cancelamento' => ['nullable', 'string', 'max:5000'],
            'imagem' => ImagemEvento::regras(),
            'local.nome' => [Rule::requiredIf($presencial), 'nullable', 'string', 'max:150'],
            'local.cep' => ['nullable', 'string', 'max:9'],
            'local.logradouro' => ['nullable', 'string', 'max:150'],
            'local.numero' => ['nullable', 'string', 'max:20'],
            'local.complemento' => ['nullable', 'string', 'max:60'],
            'local.bairro' => ['nullable', 'string', 'max:100'],
            'local.cidade' => [Rule::requiredIf($presencial), 'nullable', 'string', 'max:100'],
            'local.uf' => [Rule::requiredIf($presencial), 'nullable', 'string', 'size:2'],
        ], [
            'imagem.max' => 'A imagem pode ter no máximo 2 MB.',
            'imagem.mimes' => 'Envie a imagem em JPEG, GIF ou PNG.',
            'imagem.mimetypes' => 'Envie a imagem em JPEG, GIF ou PNG.',
            'imagem.image' => 'O arquivo enviado não é uma imagem válida.',
            'imagem.uploaded' => 'A imagem pode ter no máximo 2 MB.',
            'aceite_cancelamento.accepted' => 'Para salvar o evento, marque a declaração sobre a política de cancelamento.',
        ], [
            'imagem' => 'imagem de divulgação',
            'local.nome' => 'nome do local', 'local.cidade' => 'cidade', 'local.uf' => 'UF',
            'data_inicio' => 'início', 'data_fim' => 'término', 'abertura_portoes' => 'abertura dos portões',
        ]);

        $dadosEvento = collect($v)->except(['local', 'imagem', 'aceite_cancelamento'])->all();
        $dadosEvento['capacidade'] = $dadosEvento['capacidade'] ?? null;
        foreach (['data_inicio', 'data_fim', 'abertura_portoes'] as $campo) {
            $dadosEvento[$campo] = utc_de_local($dadosEvento[$campo] ?? null);
        }
        foreach (['exige_cpf_participante', 'nominal', 'permite_transferencia', 'permite_reentrada'] as $campo) {
            $dadosEvento[$campo] = $request->boolean($campo);
        }

        $dadosLocal = null;
        if ($presencial) {
            $dadosLocal = $v['local'];
            $dadosLocal['cep'] = preg_replace('/\D/', '', (string) ($dadosLocal['cep'] ?? '')) ?: null;
            $dadosLocal['uf'] = strtoupper($dadosLocal['uf']);
        }

        return [$dadosEvento, $dadosLocal];
    }
}
