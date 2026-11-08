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
