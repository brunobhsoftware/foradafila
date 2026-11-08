<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\ContaPagamento;
use App\Models\Evento;
use App\Models\EventoCategoria;
use App\Models\Local;
use App\Support\Auditoria;
use App\Support\ImagemEvento;
use App\Support\PoliticaCancelamento;
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

        $ids = $eventos->pluck('id');
        $indicadores = DB::table('vw_dashboard_evento')->whereIn('evento_id', $ids)->get()->keyBy('evento_id');
        // Valores por evento: vendido (o que os compradores pagaram), descontos, taxa da plataforma, devoluções e líquido.
        $vendas = DB::table('pedido')->whereIn('evento_id', $ids)->whereIn('status', ['pago', 'reembolso_parcial', 'reembolsado'])
            ->groupBy('evento_id')->selectRaw('evento_id, SUM(valor_total) AS vendido, SUM(valor_desconto) AS descontos, COUNT(*) AS pedidos')
            ->get()->keyBy('evento_id');
        // Taxa só dos ingressos que continuam válidos (a de um ingresso devolvido volta ao comprador junto com o valor).
        $taxas = DB::table('ingresso as i')->join('pedido_item as pi', 'pi.id', '=', 'i.pedido_item_id')->whereIn('i.evento_id', $ids)
            ->whereIn('i.status', ['valido', 'utilizado'])->groupBy('i.evento_id')
            ->selectRaw('i.evento_id, SUM(pi.taxa_unitaria) AS taxa')->pluck('taxa', 'i.evento_id');
        $devolvido = DB::table('reembolso as r')->join('pedido as p', 'p.id', '=', 'r.pedido_id')->whereIn('p.evento_id', $ids)
            ->whereIn('r.status', ['aprovado', 'concluido'])->groupBy('p.evento_id')->selectRaw('p.evento_id, SUM(r.valor) AS v')->pluck('v', 'p.evento_id');
        $valores = $ids->mapWithKeys(function ($id) use ($vendas, $taxas, $devolvido) {
            $vendido = (float) ($vendas[$id]->vendido ?? 0);
            $taxa = (float) ($taxas[$id] ?? 0);
            $dev = (float) ($devolvido[$id] ?? 0);

            return [$id => (object) ['vendido' => $vendido, 'descontos' => (float) ($vendas[$id]->descontos ?? 0), 'taxa' => $taxa,
                'devolvido' => $dev, 'liquido' => round($vendido - $taxa - $dev, 2), 'pedidos' => (int) ($vendas[$id]->pedidos ?? 0)]];
        });

        return view('painel.eventos.index', compact('eventos', 'indicadores', 'valores'));
    }

    public function create()
    {
        Gate::authorize('evento.criar');

        return view('painel.eventos.form', $this->dadosFormulario(new Evento([
            'formato' => 'presencial', 'classificacao' => 'livre', 'visibilidade' => 'publico',
            'max_ingressos_pedido' => 10, 'exige_cpf_participante' => false, 'nominal' => true,
            'permite_transferencia' => true, 'transferencia_ate_h' => 24, 'permite_reentrada' => false,
            // Já vem com o modelo sugerido, com os dados da organização (editável).
            'politica_cancelamento' => PoliticaCancelamento::modelo(app(\App\Support\Tenant::class)->organizador()),
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
            self::ajustarFimDasVendas($evento, $antes);
            self::registrarDeclaracao($evento);
        }), 'titulo');

        return redirect()->route('eventos.show', $evento)->with('ok', 'Evento atualizado.');
    }

    /**
     * Mudou a data do evento: o fim das vendas dos lotes acompanha o novo término (decisão do Bruno, 08/11/2026).
     * Muda os lotes que terminavam junto com o evento (no início ou no fim antigos) e os que passariam do novo término.
     */
    private static function ajustarFimDasVendas(Evento $evento, array $antes): void
    {
        $fimNovo = $evento->data_fim->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        $iniAntigo = $antes['data_inicio'] ?? null;
        $fimAntigo = $antes['data_fim'] ?? null;
        // Nunca há venda depois do fim do evento: todo lote que passa do término é ajustado, mesmo sem mudar a data
        // (corrige lotes antigos). Se a data mudou, os que terminavam junto com o evento acompanham o novo término.
        $mudou = $fimNovo !== $fimAntigo || $evento->data_inicio->copy()->setTimezone('UTC')->format('Y-m-d H:i:s') !== $iniAntigo;
        $lotes = $evento->lotes()
            ->where(fn ($q) => $q->where('venda_fim', '>', $fimNovo)
                ->when($mudou, fn ($q) => $q->orWhere(fn ($q) => $q->whereNotIn('status', ['encerrado'])->whereIn('venda_fim', array_filter([$iniAntigo, $fimAntigo])))))->get();
        foreach ($lotes as $lote) {
            $antesLote = $lote->getAttributes();
            $lote->update(['venda_fim' => $fimNovo]);
            Auditoria::registrar('lote.fim_vendas_ajustado', $lote, $antesLote);
        }
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

        if ($novo === 'publicado' && ($rec = \App\Support\Recebimento::contaAtual($evento->organizador_id)) && $rec->status === 'ativa') {
            $dados['conta_pagamento_id'] = $rec->id; // conta usada nas vendas (plataforma ou aplicação própria)
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
            'modeloPolitica' => PoliticaCancelamento::modelo(app(\App\Support\Tenant::class)->organizador()),
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
        // Formulário de inscrição (V015): os campos pedidos vêm dele; "exigir CPF" passa a ser "o formulário pede CPF obrigatório".
        if (\App\Support\Formularios::ativo()) {
            $org = app(\App\Support\Tenant::class)->id();
            $fid = $request->integer('formulario_id') ?: null;
            $dadosEvento['formulario_id'] = $fid && DB::table('formulario')->where('organizador_id', $org)->whereNull('evento_id')->where('id', $fid)->exists() ? $fid : null;
            $form = $dadosEvento['formulario_id'] ? DB::table('formulario')->find($dadosEvento['formulario_id']) : \App\Support\Formularios::padrao($org);
            $dadosEvento['exige_cpf_participante'] = \App\Support\Formularios::exigeCpf(\App\Support\Formularios::campos($form));
            $dadosEvento['nominal'] = true;
        }
        // Quem paga a taxa da plataforma (V010): "" = padrão da organização, "0" = embutida, "1" = repassada ao comprador.
        if (\App\Support\Esquema::temV010()) {
            $t = (string) $request->input('taxa_comprador', '');
            $dadosEvento['taxa_comprador'] = in_array($t, ['0', '1'], true) ? (int) $t : null;
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
