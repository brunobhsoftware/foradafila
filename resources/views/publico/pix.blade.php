@extends('layouts.publico')
@section('titulo', 'Pague com Pix — pedido '.$pedido->codigo)

@section('conteudo')
<section class="pub-secao">
  <div class="container" style="max-width:560px">
    <div class="pub-cartao" style="text-align:center" data-pix data-situacao="{{ route('pedido.situacao', $pedido->uuid) }}" data-retorno="{{ route('pedido.retorno', ['pedido' => $pedido->uuid, 'resultado' => 'conferir']) }}">
      <small>Pedido {{ $pedido->codigo }} · {{ $evento?->titulo }}</small>
      <h1 style="font-size:1.6rem;margin:6px 0">Pague com Pix</h1>
      <p style="margin:0 0 14px">Valor: <b style="font-size:1.25rem">{{ moeda($pedido->valor_total) }}</b></p>
      <div id="qr-pix" data-conteudo="{{ $pix['qr'] }}" role="img" aria-label="QR Code do Pix" style="width:240px;max-width:100%;margin:0 auto 14px"></div>
      <p style="margin:0 0 6px"><small>Abra o app do seu banco, escolha <b>Pix → Ler QR Code</b>, ou copie o código abaixo (Pix copia e cola).</small></p>
      <textarea readonly rows="3" style="width:100%;font-size:.8rem;word-break:break-all" id="pix-codigo">{{ $pix['qr'] }}</textarea>
      <button type="button" class="botao botao--amarelo botao--largo" style="margin-top:8px" data-copiar-pix>Copiar código do Pix</button>
      <p style="margin:14px 0 0" data-pix-status><small>Aguardando o pagamento… esta página confirma sozinha assim que o Pix cair.
        O código vale até <b>{{ data_local($pedido->expira_em, 'H:i') }}</b>.</small></p>
      <p style="margin:10px 0 0"><a href="{{ route('pedido.retorno', ['pedido' => $pedido->uuid, 'resultado' => 'conferir']) }}" class="botao botao--contorno botao--pequeno">Já paguei, conferir</a></p>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/qrcode-generator.js') }}"></script>
<script>
  (function () {
    const alvo = document.getElementById('qr-pix');
    if (alvo && typeof qrcode !== 'undefined') {
      const qr = qrcode(0, 'M'); qr.addData(alvo.dataset.conteudo); qr.make();
      alvo.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    }
    const bt = document.querySelector('[data-copiar-pix]');
    bt.addEventListener('click', async () => {
      const txt = document.getElementById('pix-codigo');
      try { await navigator.clipboard.writeText(txt.value); } catch (e) { txt.select(); document.execCommand('copy'); }
      bt.textContent = 'Código copiado! Cole no app do seu banco';
    });
    // Confere a cada 5 s; aprovado ou encerrado → página de resultado.
    const box = document.querySelector('[data-pix]');
    const conferir = async () => {
      try {
        const r = await fetch(box.dataset.situacao, { headers: { Accept: 'application/json' } });
        const j = await r.json();
        if (j.situacao === 'aprovado' || j.status !== 'aguardando_pagamento') { location.href = box.dataset.retorno; return; }
      } catch (e) {}
      setTimeout(conferir, 5000);
    };
    setTimeout(conferir, 5000);
  })();
</script>
@endpush
