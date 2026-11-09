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
    /** Lembra na sessão os pedidos feitos neste navegador: quem acabou de comprar vê os ingressos sem login. */
    public static function lembrar(Pedido $pedido): void
    {
        session()->put('pedidos_compra', array_values(array_unique(array_merge((array) session('pedidos_compra', []), [$pedido->uuid]))));
    }

    /** Link dos ingressos que abre sem login (assinado, 90 dias) — vai no e-mail. */
    public static function linkIngressos(Pedido $pedido): string
    {
        return url(\Illuminate\Support\Facades\URL::temporarySignedRoute('pedido.ingressos', now()->addDays(90), ['pedido' => $pedido->uuid], absolute: false));
    }

    private static function podeVer(\Illuminate\Http\Request $request, Pedido $p): bool
    {
        if (in_array($p->uuid, (array) session('pedidos_compra', []), true) || Auth::guard('comprador')->id() === $p->comprador_id) {
            return true;
        }
        if ($request->hasValidSignature(absolute: false)) {
            self::lembrar($p); // o link do e-mail também libera o PDF nesta sessão

            return true;
        }

        return false;
    }

    /** "Seus ingressos": logo depois da compra (ou pelo link do e-mail), sem pedir login. */
    public function ingressos(\Illuminate\Http\Request $request, string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        if (! self::podeVer($request, $p)) {
            return redirect()->route('comprador.entrar')->with('ok', 'Entre para ver os seus ingressos ou use o link que enviamos por e-mail.');
        }
        if ($p->status === 'aguardando_pagamento') {
            return redirect()->route('pedido.retorno', ['pedido' => $p->uuid]);
        }
        [$evento, $ingressos] = self::dadosIngressos($p);

        return view('publico.ingressos-pedido', ['pedido' => $p, 'evento' => $evento, 'ingressos' => $ingressos,
            'comprador' => \App\Models\Comprador::find($p->comprador_id)]);
    }

    public function pdf(\Illuminate\Http\Request $request, string $pedido)
    {
        $p = Pedido::withoutGlobalScopes()->where('uuid', $pedido)->firstOrFail();
        abort_unless(self::podeVer($request, $p), 403);
        [$evento, $ingressos] = self::dadosIngressos($p);
        $validos = $ingressos->whereIn('status', ['valido', 'utilizado'])->values();
        abort_if($validos->isEmpty(), 404);

        return response(\App\Support\IngressoPdf::gerar($p, $evento, $validos), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="ingressos-'.$p->codigo.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function dadosIngressos(Pedido $p): array
    {
        $evento = \App\Models\Evento::withoutGlobalScopes()->with(['organizador'])->find($p->evento_id);
        app(\App\Support\Tenant::class)->definir($evento->organizador);
        $evento->load('local');
        $ingressos = \App\Models\Ingresso::where('pedido_id', $p->id)->with(['participante', 'tipo', 'lote'])->orderBy('numero')->get();

        return [$evento, $ingressos];
    }

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
        if ($situacao === 'aprovado' && $p->status === 'pago' && ($logado || in_array($p->uuid, (array) session('pedidos_compra', []), true))) {
            return redirect()->route('pedido.ingressos', $p->uuid)->with('ok', "Pagamento aprovado! Pedido {$p->codigo} confirmado. Os ingressos também foram enviados para o seu e-mail, em PDF.");
        }
        $pg = DB::table('pagamento')->where('pedido_id', $p->id)->orderByDesc('id')->first();
        $evento = \App\Models\Evento::withoutGlobalScopes()->find($p->evento_id);

        return view('publico.pagamento', ['pedido' => $p, 'situacao' => $p->status === 'pago' ? 'aprovado' : $situacao,
            'link' => $p->status === 'aguardando_pagamento' ? $pg?->link_pagamento : null, 'evento' => $evento, 'logado' => $logado,
            'semEstoque' => $p->status === 'cancelado' && str_contains((string) $pg?->motivo_recusa, 'sem estoque')]);
    }
}
