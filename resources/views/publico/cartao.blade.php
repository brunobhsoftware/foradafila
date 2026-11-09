@extends('layouts.publico')
@section('titulo', 'Pague com cartão — pedido '.$pedido->codigo)

@section('conteudo')
<section class="pub-secao">
  <div class="container" style="max-width:600px">
    <div class="pub-cartao">
      <small>Pedido {{ $pedido->codigo }} · {{ $evento?->titulo }}</small>
      <h1 style="font-size:1.6rem;margin:6px 0">Pague com cartão de crédito</h1>
      <p style="margin:0 0 6px">Valor: <b style="font-size:1.25rem">{{ moeda($pedido->valor_total) }}</b>
        <small>· reservado até {{ data_local($pedido->expira_em, 'H:i') }}</small></p>
      @if ($teste)
        <p style="margin:0 0 10px;padding:10px 12px;background:#FFF4D6;border:1px solid var(--amarelo);border-radius:8px"><small><b>Ambiente de teste:</b> use um cartão de teste do Mercado Pago (titular APRO). Nada é cobrado.</small></p>
      @endif
      @if ($publicKey)
        <p style="margin:0 0 10px"><small>Os dados do cartão vão protegidos direto para o Mercado Pago; o Fora da Fila não guarda o número do cartão.</small></p>
        <div id="brick-cartao" data-pk="{{ $publicKey }}" data-valor="{{ number_format((float) $pedido->valor_total, 2, '.', '') }}"
          data-email="{{ $comprador?->email }}" data-url="{{ route('pedido.cartao.pagar', $pedido->uuid) }}"></div>
        <p class="erro" id="cartao-erro" hidden style="color:#B42318;font-weight:600;margin-top:10px"></p>
      @else
        <p class="erro">O pagamento com cartão ainda não está disponível para este evento. Volte e escolha Pix.</p>
      @endif
      <p style="margin:14px 0 0"><a href="{{ $evento->linkPublico() }}" style="color:var(--laranja)">‹ Voltar ao evento</a></p>
    </div>
  </div>
</section>
@endsection

@push('scripts')
@if ($publicKey)
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
  (function () {
    const box = document.getElementById('brick-cartao');
    const erro = document.getElementById('cartao-erro');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const mp = new MercadoPago(box.dataset.pk, { locale: 'pt-BR' });
    mp.bricks().create('cardPayment', 'brick-cartao', {
      initialization: { amount: Number(box.dataset.valor), payer: { email: box.dataset.email } },
      customization: { paymentMethods: { maxInstallments: 12 }, visual: { style: { theme: 'default' } } },
      callbacks: {
        onReady: () => {},
        onError: (e) => { console.error(e); },
        onSubmit: (dados) => new Promise((ok, falha) => {
          erro.hidden = true;
          fetch(box.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(dados) })
            .then((r) => r.json())
            .then((j) => {
              if (j.redirect) { ok(); location.href = j.redirect; return; }
              erro.textContent = j.erro || (j.message || 'Não foi possível processar o cartão.'); erro.hidden = false; falha();
            })
            .catch(() => { erro.textContent = 'Falha de conexão. Tente de novo.'; erro.hidden = false; falha(); });
        }),
      },
    });
  })();
</script>
@endif
@endpush
