<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Support\Checkout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Volta do Mercado Pago: confere a order na API (não confia no parâmetro da URL) e mostra a situação do pedido. */
class PagamentoController extends Controller
{
    /** Pix na nossa página: QR Code + copia e cola; a página consulta /situacao até o pagamento cair. */
    public function pix(string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        if ($p->status !== 'aguardando_pagamento') {
            return redirect()->route('pedido.retorno', ['pedido' => $p->uuid]);
        }
        $pg = DB::table('pagamento')->where('pedido_id', $p->id)->orderByDesc('id')->first();
        $pix = json_decode((string) $pg?->resposta_gateway, true)['pix'] ?? null;
        abort_unless($pix && $pix['qr'], 404);
        $evento = \App\Models\Evento::withoutGlobalScopes()->find($p->evento_id);

        return view('publico.pix', ['pedido' => $p, 'pix' => $pix, 'evento' => $evento]);
    }

    /** Cartão na nossa página (Card Payment Brick do Mercado Pago). */
    public function cartao(string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        if ($p->status !== 'aguardando_pagamento') {
            return redirect()->route('pedido.retorno', ['pedido' => $p->uuid]);
        }
        $evento = \App\Models\Evento::withoutGlobalScopes()->with('organizador')->find($p->evento_id);
        $conta = Checkout::contaDoEvento($evento);
        $pk = $conta ? Checkout::publicKey($conta) : null;
        $comprador = \App\Models\Comprador::find($p->comprador_id);

        return view('publico.cartao', ['pedido' => $p, 'evento' => $evento, 'publicKey' => $pk, 'comprador' => $comprador,
            'teste' => Checkout::modoTeste($p->organizador_id)]);
    }

    public function pagarCartao(\Illuminate\Http\Request $request, string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        if ($p->status !== 'aguardando_pagamento') {
            return response()->json(['redirect' => route('pedido.retorno', ['pedido' => $p->uuid])]);
        }
        $f = $request->validate([
            'token' => ['required', 'string', 'max:255'], 'payment_method_id' => ['required', 'string', 'max:40'],
            'payment_type_id' => ['nullable', 'string', 'max:40'], 'installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'issuer_id' => ['nullable'], 'payer' => ['nullable', 'array'],
        ]);
        $evento = \App\Models\Evento::withoutGlobalScopes()->with('organizador')->find($p->evento_id);
        app(\App\Support\Tenant::class)->definir($evento->organizador); // tipos/lotes do pedido têm escopo por organizador
        $conta = Checkout::contaDoEvento($evento);
        if (! $conta) {
            return response()->json(['erro' => 'O pagamento com cartão não está disponível agora. Tente pelo Pix.'], 422);
        }
        try {
            $r = Checkout::pagarCartao($p, $evento, \App\Models\Comprador::findOrFail($p->comprador_id), $conta, $f + ['payer' => $request->input('payer', [])]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['erro' => 'Não foi possível processar o cartão agora. Confira os dados ou pague com Pix.'], 422);
        }
        if ($r === 'aprovado' || $r === 'pendente') {
            return response()->json(['redirect' => route('pedido.retorno', ['pedido' => $p->uuid, 'resultado' => 'conferir'])]);
        }

        return response()->json(['erro' => $r], 422);
    }

    public function situacao(string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        $s = Checkout::sincronizar($p);
        $p->refresh();

        return response()->json(['situacao' => $p->status === 'pago' ? 'aprovado' : $s, 'status' => $p->status]);
    }

    public function retorno(string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        $situacao = Checkout::sincronizar($p);
        $p->refresh();
        $logado = Auth::guard('comprador')->id() === $p->comprador_id;
        if ($situacao === 'aprovado' && $p->status === 'pago' && $logado) {
            return redirect()->route('comprador.ingressos')->with('ok', "Pagamento aprovado! Pedido {$p->codigo} confirmado. Seus ingressos estão abaixo e também foram enviados por e-mail.");
        }
        $pg = DB::table('pagamento')->where('pedido_id', $p->id)->orderByDesc('id')->first();
        $evento = \App\Models\Evento::withoutGlobalScopes()->find($p->evento_id);

        return view('publico.pagamento', ['pedido' => $p, 'situacao' => $p->status === 'pago' ? 'aprovado' : $situacao,
            'link' => $p->status === 'aguardando_pagamento' ? $pg?->link_pagamento : null, 'evento' => $evento, 'logado' => $logado,
            'semEstoque' => $p->status === 'cancelado' && str_contains((string) $pg?->motivo_recusa, 'sem estoque')]);
    }
}
