@extends('layouts.admin')
@section('titulo', 'Pagamento de teste')

@section('conteudo')
<div class="cabecalho"><div><h1>Pagamento de teste criado</h1>
    <p>{{ moeda($valor) }} · {{ $teste ? 'Credenciais de teste' : 'Produção (cobrança real)' }} · referência {{ $referencia }}</p></div></div>

<section class="cartao link-teste" style="padding:20px">
    <div class="link-teste__qr" data-qr="{{ $link }}" aria-label="QR Code do pagamento"></div>
    <div class="link-teste__texto">
        <h2 style="margin-top:0">Abra fora da conta da BH Software</h2>
        <ol class="mp__passos">
            <li>Copie o link e abra numa <b>janela anônima</b> (Ctrl+Shift+N no Chrome/Edge, Ctrl+Shift+P no Firefox) ou em outro navegador — ou leia o QR Code com o celular.</li>
            @if ($teste)
                <li>Na tela do Mercado Pago, <b>não entre com a conta da BH Software</b>. Pague como visitante com um cartão de teste (titular <code>APRO</code>) ou entre com a conta <b>compradora de teste</b>.</li>
            @else
                <li>Pague com uma conta diferente da BH Software (o Mercado Pago não deixa pagar para si mesmo).</li>
            @endif
            <li>Depois de pagar, o Mercado Pago volta para a tela de retorno com o ID do pagamento e do pedido.</li>
        </ol>
        <div class="copiar"><input id="link-teste" value="{{ $link }}" readonly><button type="button" class="botao botao--escuro botao--pequeno" data-copiar="link-teste">Copiar link</button></div>
        <p style="margin-top:14px">
            <a href="{{ $link }}" target="_blank" rel="noopener" class="botao botao--contorno botao--pequeno">Abrir nesta janela mesmo ↗</a>
            <a href="{{ route('admin.mercadopago') }}" class="botao botao--link">Voltar</a>
        </p>
    </div>
</section>
<script src="{{ asset('js/vendor/qrcode-generator.js') }}"></script>
<script>
document.querySelectorAll('[data-qr]').forEach((alvo) => {
    const qr = qrcode(0, 'M'); qr.addData(alvo.dataset.qr); qr.make();
    alvo.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
});
</script>
@endsection
