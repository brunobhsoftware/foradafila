@extends('layouts.app-checkin')
@section('titulo', 'Ler QR Code')

@section('corpo')
<div class="ck-leitor" data-leitor data-url="{{ route('checkin.registrar', $evento) }}" data-aberta="{{ $janela['aberta'] ? 1 : 0 }}">
  <video playsinline muted autoplay></video>
  <canvas hidden></canvas>
  <div class="ck-leitor__topo">
    <a href="{{ route('app.evento', $evento) }}" aria-label="Voltar">‹</a>
    <div><b>{{ $evento->titulo }}</b><small data-contagem-topo>{{ $numeros['entraram'] }} de {{ $numeros['total'] }} entraram</small></div>
  </div>
  @if ($janela['aberta'])
    <div class="ck-mira" aria-hidden="true"></div>
  @else
    <div style="position:relative;z-index:2;margin:auto;padding:24px;text-align:center;max-width:340px">
      <div style="font-size:3rem">⏱</div>
      <h2 style="margin:8px 0">Check-in fechado</h2>
      <p>{{ $janela['motivo'] }}</p>
      <p style="opacity:.8"><small>O check-in só funciona no dia do evento, da abertura dos portões até o término.</small></p>
    </div>
  @endif
  <div class="ck-leitor__base">
    <p class="ck-contagem" data-dica>{{ $janela['aberta'] ? 'Aponte a câmera para o QR Code do ingresso' : '' }}</p>
    @if ($janela['aberta'])
      <form data-manual><input name="codigo" placeholder="Ou digite o número do ingresso" inputmode="text" autocomplete="off"><button>OK</button></form>
    @endif
  </div>
</div>
<div class="ck-resultado" data-resultado role="status" aria-live="assertive">
  <div class="icone" data-r-icone></div><h2 data-r-titulo></h2><p data-r-nome></p><p data-r-info></p><small>Toque para ler o próximo</small>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/jsQR.js') }}"></script>
<script>
(function () {
  const raiz = document.querySelector('[data-leitor]');
  if (raiz.dataset.aberta !== '1') return;
  const video = raiz.querySelector('video'), canvas = raiz.querySelector('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
  const res = document.querySelector('[data-resultado]'), dica = raiz.querySelector('[data-dica]');
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  let ocupado = false, ultimo = '', ultimoEm = 0, detector = null, audio = null;
  if ('BarcodeDetector' in window) { try { detector = new BarcodeDetector({ formats: ['qr_code'] }); } catch (e) {} }

  const bip = (ok) => {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      const o = audio.createOscillator(), g = audio.createGain();
      o.frequency.value = ok ? 880 : 220; o.type = ok ? 'sine' : 'square'; g.gain.value = .15;
      o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + (ok ? .15 : .45));
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [120, 80, 120]);
  };
  const mostrar = (j) => {
    const aceito = j.aceito, ja = j.resultado === 'ja_utilizado';
    res.className = 'ck-resultado mostrar ' + (aceito ? 'aceito' : ja ? 'alerta' : 'negado');
    res.querySelector('[data-r-icone]').textContent = aceito ? '✓' : ja ? '!' : '✕';
    res.querySelector('[data-r-titulo]').textContent = j.mensagem || (aceito ? 'Entrada liberada' : 'Não liberado');
    res.querySelector('[data-r-nome]').textContent = j.ingresso ? (j.ingresso.nome || '') : '';
    res.querySelector('[data-r-info]').textContent = j.ingresso ? [j.ingresso.tipo, j.ingresso.lote, j.ingresso.numero].filter(Boolean).join(' · ')
      + (ja && j.ingresso.primeiro_checkin ? ' · entrou em ' + j.ingresso.primeiro_checkin : '') : '';
    if (j.numeros) document.querySelector('[data-contagem-topo]').textContent = j.numeros.entraram + ' de ' + j.numeros.total + ' entraram';
    bip(aceito);
    clearTimeout(mostrar.t); mostrar.t = setTimeout(fechar, aceito ? 1800 : 3500);
  };
  const fechar = () => { res.className = 'ck-resultado'; ocupado = false; };
  res.addEventListener('click', fechar);

  const enviar = async (codigo) => {
    if (ocupado) return;
    const agora = Date.now();
    if (codigo === ultimo && agora - ultimoEm < 4000) return; // o mesmo QR parado na frente da câmera
    ultimo = codigo; ultimoEm = agora; ocupado = true;
    try {
      const r = await fetch(raiz.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ codigo, sentido: 'entrada' }) });
      if (r.status === 419 || r.status === 401) { location.reload(); return; }
      const j = r.status === 409 ? { aceito: false, resultado: 'ja_utilizado', mensagem: 'Acabou de ser lido em outro portão' } : await r.json();
      mostrar(j);
    } catch (e) { mostrar({ aceito: false, mensagem: 'Sem conexão. Tente de novo.' }); }
  };

  raiz.querySelector('[data-manual]').addEventListener('submit', (e) => {
    e.preventDefault(); const i = e.target.codigo; if (i.value.trim()) { ultimo = ''; enviar(i.value.trim()); i.value = ''; i.blur(); }
  });

  const ler = async () => {
    if (!ocupado && video.readyState >= 2) {
      try {
        let texto = null;
        if (detector) {
          const r = await detector.detect(video); if (r.length) texto = r[0].rawValue;
        } else {
          const w = video.videoWidth, h = video.videoHeight, lado = Math.min(w, h), s = Math.min(1, 640 / lado);
          canvas.width = Math.round(lado * s); canvas.height = Math.round(lado * s);
          ctx.drawImage(video, (w - lado) / 2, (h - lado) / 2, lado, lado, 0, 0, canvas.width, canvas.height);
          const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const r = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' }); if (r) texto = r.data;
        }
        if (texto) enviar(texto.trim());
      } catch (e) {}
    }
    setTimeout(() => requestAnimationFrame(ler), 120);
  };

  navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false })
    .then((s) => { video.srcObject = s; video.play(); ler(); })
    .catch(() => { dica.textContent = 'Não foi possível abrir a câmera. Libere a câmera para este site (ou app) ou digite o número do ingresso.'; });
})();
</script>
@endpush
