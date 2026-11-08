<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use App\Models\Evento;
use App\Models\Ingresso;
use App\Models\Lote;
use App\Models\Participante;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Termo;
use App\Models\TermoAceite;
use App\Rules\DocumentoValido;
use App\Support\Auditoria;
use App\Support\Documento;
use App\Support\Notificacoes;
use App\Support\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inscrição em lotes GRATUITOS: emite ingressos de verdade, sem pagamento.
 * Lotes pagos dependem da integração com o meio de pagamento (fase seguinte)
 * e são recusados aqui.
 *
 * Estoque (regras, seção 16): baixa por UPDATE condicional na mesma transação
 * que cria o pedido e os ingressos; lotes travados em ordem crescente de id;
 * chave de idempotência contra pedido duplicado.
 */
class InscricaoController extends Controller
{
    private const TERMOS = ['compra_comprador', 'privacidade'];

    public function create(Request $request, string $slug, Tenant $tenant)
    {
        [$evento, $selecao] = $this->carregar($request, $slug, $tenant);
        $termos = Termo::vigente()->whereIn('tipo', self::TERMOS)->get()->keyBy('tipo');

        // Se a pessoa preferir entrar na conta antes, volta para esta mesma seleção depois do login.
        if (! Auth::guard('comprador')->check()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('publico.inscricao', [
            'evento' => $evento, 'selecao' => $selecao, 'termos' => $termos,
            'comprador' => Auth::guard('comprador')->user(), 'chave' => (string) Str::uuid(),
            'cupom' => $request->session()->get('cupom_divulgacao'),
        ]);
    }

    public function store(Request $request, string $slug, Tenant $tenant)
    {
        [$evento, $selecao] = $this->carregar($request, $slug, $tenant);
        $total = $selecao->sum('quantidade');
        $comprador = Auth::guard('comprador')->user();

        $regras = [
            'chave' => ['required', 'uuid'],
            'participantes' => ['required', 'array', 'size:'.$total],
            'participantes.*.nome' => ['required', 'string', 'max:150'],
            'participantes.*.cpf' => $evento->exige_cpf_participante ? ['required', new DocumentoValido('PF')] : ['nullable', new DocumentoValido('PF')],
            'aceites' => ['required', 'array'],
            'aceites.*' => ['accepted'],
            'cupom' => ['nullable', 'string', 'max:30'],
        ];
        if (! $comprador) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
            $regras += CompradorAuthController::regrasConta();
        }
        $dados = $request->validate($regras, [
            'email.unique' => 'Já existe uma conta com este e-mail. Entre em “Meus ingressos” para continuar.',
            'participantes.*.nome.required' => 'Informe o nome de cada participante.',
            'participantes.*.cpf.required' => 'Este evento exige o CPF de cada participante.',
        ], ['participantes.*.cpf' => 'CPF do participante']);

        $termos = Termo::vigente()->whereIn('tipo', self::TERMOS)->get();
        if ($termos->count() < count(self::TERMOS) || $termos->contains(fn ($t) => ! isset($dados['aceites'][$t->tipo]))) {
            throw ValidationException::withMessages(['aceites' => 'É preciso aceitar os Termos de compra e a Política de privacidade.']);
        }

        // Pedido já criado com esta chave (clique duplo, voltar e reenviar): leva ao mesmo pedido.
        if ($existente = Pedido::withoutGlobalScope('organizador')->where('chave_idempotencia', $dados['chave'])->first()) {
            return redirect()->route('comprador.ingressos')->with('ok', 'Seu pedido '.$existente->codigo.' já está confirmado.');
        }

        $cupom = $this->cupomValido($evento, $dados['cupom'] ?? null);

        $participantes = collect($dados['participantes'])->values()->map(fn ($p) => [
            'nome' => trim($p['nome']), 'cpf' => Documento::digitos($p['cpf'] ?? '') ?: null,
        ]);
        $this->conferirLimitePorCpf($evento, $participantes);

        if ($selecao->contains(fn ($s) => (float) $s['lote']->preco > 0)) {
            return $this->reservarPago($request, $evento, $selecao, $participantes, $termos, $dados, $comprador, $cupom);
        }

        try {
            $pedido = DB::transaction(function () use ($evento, $selecao, $total, $participantes, $termos, $dados, $request, $comprador, $cupom) {
                if (! $comprador) {
                    $comprador = Comprador::create([
                        'nome' => $dados['nome'], 'email' => $dados['email'],
                        'telefone' => preg_replace('/\D/', '', (string) ($dados['telefone'] ?? '')) ?: null,
                        'senha_hash' => Hash::make($dados['senha']),
                    ]);
                }

                // Baixa do estoque: um UPDATE condicional por lote, sempre em ordem crescente de id.
                foreach ($selecao->sortBy(fn ($s) => $s['lote']->id) as $s) {
                    $ok = DB::update("UPDATE lote SET vendido = vendido + ?, status = 'ativo'
                        WHERE id = ? AND evento_id = ? AND preco = 0 AND visibilidade = 'publico'
                          AND status IN ('ativo', 'agendado') AND UTC_TIMESTAMP() BETWEEN venda_inicio AND venda_fim
                          AND quantidade - reservado - vendido >= ?",
                        [$s['quantidade'], $s['lote']->id, $evento->id, $s['quantidade']]);
                    if ($ok === 0) {
                        throw ValidationException::withMessages(['q' => "Os ingressos de “{$s['lote']->nome}” esgotaram ou deixaram de estar disponíveis. Escolha de novo."]);
                    }
                }

                // Números sequenciais do evento, reservados na mesma transação.
                DB::update('UPDATE evento SET ultimo_numero_ingresso = ultimo_numero_ingresso + ? WHERE id = ?', [$total, $evento->id]);
                $ultimo = (int) DB::table('evento')->where('id', $evento->id)->value('ultimo_numero_ingresso');
                $numero = $ultimo - $total;

                $pedido = Pedido::create([
                    'codigo' => Pedido::novoCodigo(), 'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id,
                    'comprador_id' => $comprador->id, 'cupom_id' => $cupom?->id, 'origem' => 'site', 'chave_idempotencia' => $dados['chave'],
                    'status' => 'pago', 'valor_bruto' => 0, 'valor_desconto' => 0, 'valor_total' => 0,
                    'metodo_pagamento' => 'gratuito', 'ip' => $request->ip(), 'expira_em' => now(), 'pago_em' => now(),
                ]);

                $i = 0;
                foreach ($selecao as $s) {
                    $item = PedidoItem::create([
                        'pedido_id' => $pedido->id, 'lote_id' => $s['lote']->id, 'quantidade' => $s['quantidade'],
                        'preco_unitario' => 0, 'desconto_unitario' => 0, 'subtotal' => 0,
                    ]);
                    for ($n = 0; $n < $s['quantidade']; $n++, $i++) {
                        $ingresso = Ingresso::create([
                            'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id, 'pedido_id' => $pedido->id,
                            'pedido_item_id' => $item->id, 'lote_id' => $s['lote']->id, 'ingresso_tipo_id' => $s['lote']->ingresso_tipo_id,
                            'numero' => ++$numero, 'codigo_qr' => Ingresso::novoCodigoQr(), 'valor_pago' => 0, 'status' => 'valido',
                        ]);
                        $p = $participantes[$i];
                        Participante::create([
                            'ingresso_id' => $ingresso->id, 'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id,
                            'nome' => $p['nome'], 'cpf' => $p['cpf'],
                            'email' => $i === 0 ? $comprador->email : null,
                            'mesmo_que_comprador' => $i === 0 && mb_strtolower($p['nome']) === mb_strtolower($comprador->nome),
                        ]);
                        $eleMesmo = $i === 0 && mb_strtolower($p['nome']) === mb_strtolower($comprador->nome);
                        \App\Support\CadastroPessoa::registrar($p['cpf'], $p['nome'], $eleMesmo ? $comprador->email : null, $eleMesmo ? $comprador->telefone : null, 'inscricao');
                    }
                }

                foreach ($termos as $termo) {
                    TermoAceite::create([
                        'termo_id' => $termo->id, 'comprador_id' => $comprador->id,
                        'ip' => $request->ip() ?? '0.0.0.0', 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                    ]);
                }

                if ($cupom) {
                    // Inscrição gratuita: o código conta (divulgação) sem dar desconto. Cupom com limite não passa do máximo.
                    $ok = DB::update('UPDATE cupom SET usos = usos + 1 WHERE id = ? AND (max_usos IS NULL OR usos < max_usos)', [$cupom->id]);
                    if (! $ok) {
                        throw ValidationException::withMessages(['cupom' => 'Este código atingiu o limite de usos.']);
                    }
                    DB::table('cupom_uso')->insert(['cupom_id' => $cupom->id, 'pedido_id' => $pedido->id,
                        'cpf' => $participantes[0]['cpf'] ?? null, 'valor_desconto' => 0, 'status' => 'confirmado']);
                }

                Auditoria::registrar('pedido.gratuito.emitir', $pedido);

                return $pedido->setRelation('comprador_modelo', $comprador);
            });
        } catch (QueryException $e) {
            // Corrida na chave de idempotência: o outro envio já criou o pedido.
            if (str_contains($e->getMessage(), 'uq_pedido_idempotencia')) {
                return redirect()->route('comprador.ingressos');
            }
            throw $e;
        }

        $compradorFinal = $pedido->getRelation('comprador_modelo');
        if (! Auth::guard('comprador')->check()) {
            Auth::guard('comprador')->login($compradorFinal);
            $request->session()->regenerate();
        }

        // E-mail com a confirmação e a lista de ingressos (conta fique@, depois da resposta).
        $pedido->load(['ingressos.participante', 'ingressos.tipo', 'ingressos.lote']);
        Notificacoes::ingressosConfirmados($pedido, $compradorFinal, $evento->loadMissing('local'), $evento->organizador, $pedido->ingressos);
        Notificacoes::novaCompraParaEquipe($pedido, $compradorFinal, $evento, $evento->organizador, $pedido->ingressos->count());

        return redirect()->route('comprador.ingressos')
            ->with('ok', "Pronto! Pedido {$pedido->codigo} confirmado. Seus ingressos estão abaixo.");
    }

    /**
     * Compra paga: reserva o estoque, cria o pedido "aguardando pagamento" e manda o comprador ao Mercado Pago.
     * Os ingressos só são emitidos quando o pagamento é aprovado (Checkout::aprovar).
     */
    private function reservarPago(Request $request, Evento $evento, Collection $selecao, Collection $participantes, $termos, array $dados, ?Comprador $comprador, $cupom)
    {
        $conta = \App\Support\Checkout::contaDoEvento($evento);
        $org = $evento->organizador;
        $subtotal = $selecao->sum(fn ($s) => (float) $s['lote']->preco * $s['quantidade']);
        try {
            $pedido = DB::transaction(function () use ($request, $evento, $selecao, $participantes, $termos, $dados, $comprador, $cupom, $conta, $org, $subtotal) {
                if (! $comprador) {
                    $comprador = Comprador::create([
                        'nome' => $dados['nome'], 'email' => $dados['email'],
                        'telefone' => preg_replace('/\D/', '', (string) ($dados['telefone'] ?? '')) ?: null,
                        'senha_hash' => Hash::make($dados['senha']),
                    ]);
                }
                foreach ($selecao->sortBy(fn ($s) => $s['lote']->id) as $s) {
                    $ok = DB::update("UPDATE lote SET reservado = reservado + ?
                        WHERE id = ? AND evento_id = ? AND visibilidade = 'publico'
                          AND status IN ('ativo', 'agendado') AND UTC_TIMESTAMP() BETWEEN venda_inicio AND venda_fim
                          AND quantidade - reservado - vendido >= ?",
                        [$s['quantidade'], $s['lote']->id, $evento->id, $s['quantidade']]);
                    if ($ok === 0) {
                        throw ValidationException::withMessages(['q' => "Os ingressos de “{$s['lote']->nome}” esgotaram ou deixaram de estar disponíveis. Escolha de novo."]);
                    }
                }
                $bruto = 0; $desconto = 0; $taxaTotal = 0; $linhas = [];
                foreach ($selecao as $s) {
                    $preco = (float) $s['lote']->preco;
                    $desc = $preco > 0 && $cupom ? (float) ($cupom->desconto($preco, $s['lote']->id, $subtotal) ?? 0) : 0;
                    $tx = $preco > 0 ? \App\Support\TaxaServico::paraPreco($evento, $preco - $desc, $org) : ['taxa' => 0, 'repassada' => false];
                    $taxaUnit = (float) $tx['taxa'];
                    $bruto += $preco * $s['quantidade']; $desconto += $desc * $s['quantidade'];
                    $taxaTotal += ($tx['repassada'] ? $taxaUnit : 0) * $s['quantidade'];
                    $linhas[] = [$s, $preco, $desc, $taxaUnit, $tx['repassada']];
                }
                $pedido = new Pedido();
                $pedido->forceFill([
                    'codigo' => Pedido::novoCodigo(), 'organizador_id' => $evento->organizador_id, 'evento_id' => $evento->id,
                    'comprador_id' => $comprador->id, 'cupom_id' => $cupom?->id, 'origem' => 'site', 'chave_idempotencia' => $dados['chave'],
                    'status' => 'aguardando_pagamento', 'valor_bruto' => round($bruto, 2), 'valor_desconto' => round($desconto, 2),
                    'valor_taxa_servico' => round($taxaTotal, 2), 'valor_total' => round($bruto - $desconto + $taxaTotal, 2),
                    'conta_pagamento_id' => $conta->id, 'ip' => $request->ip(), 'expira_em' => now()->addMinutes(\App\Support\Checkout::RESERVA_MIN),
                ] + (\App\Support\Esquema::temV013() ? ['dados_participantes' => json_encode($participantes->values()->all())] : []))->save();
                if (! \App\Support\Esquema::temV013()) {
                    \Illuminate\Support\Facades\Cache::put('pedido_participantes_'.$pedido->id, json_encode($participantes->values()->all()), now()->addDays(3));
                }
                foreach ($linhas as [$s, $preco, $desc, $taxaUnit, $repassada]) {
                    // taxa_unitaria = taxa da plataforma por ingresso (sempre). subtotal = (preço − desconto) × quantidade (regra
                    // ck_item_subtotal); a taxa repassada ao comprador fica em pedido.valor_taxa_servico.
                    $item = new PedidoItem();
                    $item->forceFill(['pedido_id' => $pedido->id, 'lote_id' => $s['lote']->id, 'quantidade' => $s['quantidade'],
                        'preco_unitario' => $preco, 'desconto_unitario' => $desc, 'taxa_unitaria' => $taxaUnit,
                        'subtotal' => round(($preco - $desc) * $s['quantidade'], 2)])->save();
                }
                foreach ($termos as $termo) {
                    TermoAceite::create(['termo_id' => $termo->id, 'comprador_id' => $comprador->id,
                        'ip' => $request->ip() ?? '0.0.0.0', 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255)]);
                }
                if ($cupom) {
                    $ok = DB::update('UPDATE cupom SET usos = usos + 1 WHERE id = ? AND (max_usos IS NULL OR usos < max_usos)', [$cupom->id]);
                    if (! $ok) {
                        throw ValidationException::withMessages(['cupom' => 'Este código atingiu o limite de usos.']);
                    }
                    DB::table('cupom_uso')->insert(['cupom_id' => $cupom->id, 'pedido_id' => $pedido->id,
                        'cpf' => $participantes[0]['cpf'] ?? null, 'valor_desconto' => round($desconto, 2), 'status' => 'reservado']);
                }
                Auditoria::registrar('pedido.reservar', $pedido);

                return $pedido->setRelation('comprador_modelo', $comprador);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'uq_pedido_idempotencia')) {
                return redirect()->route('comprador.ingressos');
            }
            throw $e;
        }

        $compradorFinal = $pedido->getRelation('comprador_modelo');
        if (! Auth::guard('comprador')->check()) {
            Auth::guard('comprador')->login($compradorFinal);
            $request->session()->regenerate();
        }
        $cpf = $participantes[0]['cpf'] ?? null;
        try {
            $link = \App\Support\Checkout::criarOrder($pedido, $evento, $compradorFinal, $conta, $cpf);
        } catch (\Throwable $e) {
            report($e);
            \App\Support\Checkout::liberar($pedido->fresh(), 'cancelado');

            return redirect()->$evento->linkPublico()
                ->withErrors(['q' => 'Não foi possível abrir o pagamento agora. Tente de novo em alguns minutos.']);
        }

        return redirect()->away($link);
    }

    /**
     * Evento + lotes escolhidos (q[uuid_do_lote] = quantidade), já conferidos:
     * só lotes gratuitos à venda agora, dentro dos limites por pedido.
     *
     * @return array{0: Evento, 1: Collection}
     */
    private function carregar(Request $request, string $slug, Tenant $tenant): array
    {
        $evento = EventoPublicoController::buscar($slug);
        $tenant->definir($evento->organizador);
        \App\Support\Checkout::liberarVencidas($evento->id);
        $evento->load('lotes.tipo');

        $pedidos = collect((array) $request->input('q', []))->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0);
        $selecao = $pedidos->map(function ($qtd, $uuid) use ($evento) {
            $lote = $evento->lotes->firstWhere('uuid', $uuid);
            if (! $lote || ! EventoPublicoController::loteAVenda($evento, $lote)
                || ((float) $lote->preco > 0 && ! \App\Support\Checkout::contaDoEvento($evento))) {
                throw ValidationException::withMessages(['q' => 'Um dos ingressos escolhidos não está mais disponível. Escolha de novo.']);
            }
            if ($qtd < $lote->min_por_pedido || $qtd > $lote->max_por_pedido) {
                throw ValidationException::withMessages(['q' => "Para “{$lote->nome}”, escolha entre {$lote->min_por_pedido} e {$lote->max_por_pedido} ingressos."]);
            }

            return ['lote' => $lote, 'quantidade' => $qtd];
        })->values();

        if ($selecao->isEmpty()) {
            throw ValidationException::withMessages(['q' => 'Escolha a quantidade de ingressos.'])->redirectTo(route('evento.publico', $slug));
        }
        if ($selecao->sum('quantidade') > $evento->max_ingressos_pedido) {
            throw ValidationException::withMessages(['q' => "Máximo de {$evento->max_ingressos_pedido} ingressos por pedido."])->redirectTo(route('evento.publico', $slug));
        }

        return [$evento, $selecao];
    }

    /** max_ingressos_cpf: soma os ingressos válidos já emitidos para cada CPF neste evento. */
    /** Código de divulgação/cupom informado na inscrição (ou vindo do link). Inválido = erro no formulário. */
    private function cupomValido(Evento $evento, ?string $codigo): ?\App\Models\Cupom
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo === '') {
            return null;
        }
        $cupom = \App\Models\Cupom::withoutGlobalScope('organizador')->where('organizador_id', $evento->organizador_id)->where('codigo', $codigo)
            ->where(fn ($q) => $q->whereNull('evento_id')->orWhere('evento_id', $evento->id))->first();
        if (! $cupom || $cupom->situacao() !== 'ativo') {
            throw ValidationException::withMessages(['cupom' => 'Código não encontrado, desativado ou fora da validade. Confira ou deixe em branco.']);
        }

        return $cupom;
    }

    private function conferirLimitePorCpf(Evento $evento, Collection $participantes): void
    {
        if (! $evento->max_ingressos_cpf) {
            return;
        }
        foreach ($participantes->pluck('cpf')->filter()->countBy() as $cpf => $novos) {
            $ja = DB::table('participante as p')->join('ingresso as i', 'i.id', '=', 'p.ingresso_id')
                ->where('p.evento_id', $evento->id)->where('p.cpf', $cpf)->whereIn('i.status', ['valido', 'utilizado'])->count();
            if ($ja + $novos > $evento->max_ingressos_cpf) {
                throw ValidationException::withMessages(['participantes' => 'O CPF '.Documento::formatar($cpf)." passa do limite de {$evento->max_ingressos_cpf} ingressos por CPF neste evento."]);
            }
        }
    }
}
