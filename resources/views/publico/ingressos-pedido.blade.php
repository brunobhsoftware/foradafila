@extends('layouts.publico')
@section('titulo', 'Seus ingressos — '.$evento->titulo)

@php $validos = $ingressos->whereIn('status', ['valido', 'utilizado']); $org = $evento->organizador; @endphp

@section('conteudo')
<section class="pub-secao">
  <div class="container">
    <div class="nao-imprimir" style="max-width:760px;margin:0 auto 20px">
      <h1 style="font-size:1.7rem;margin:0 0 6px">{{ ! empty($unico) ? 'Seu ingresso' : 'Seus ingressos' }}</h1>
      @if (! empty($unico))
      <p style="margin:0">{{ $evento->titulo }}. Apresente o QR Code na entrada (no celular ou impresso).</p>
      @else
      <p style="margin:0">Pedido <b>{{ $pedido->codigo }}</b> · {{ $evento->titulo }}. Os ingressos também foram enviados para
        <b>{{ \App\Http\Controllers\Publico\InscricaoController::mascararEmail($comprador?->email) }}</b>, em PDF.</p>
      @endif
      @if ($validos->isNotEmpty() && empty($unico))
        <p style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 0">
          <a href="{{ route('pedido.pdf', $pedido->uuid) }}" class="botao botao--amarelo">Baixar ingressos em PDF</a>
          <button type="button" class="botao botao--contorno" onclick="window.print()">Imprimir</button>
          @if (auth('comprador')->id() === $pedido->comprador_id)
            <a href="{{ route('comprador.ingressos') }}" class="botao botao--escuro">Acessar meus ingressos</a>
          @else
            <form method="post" action="{{ route('pedido.acessar', $pedido->uuid) }}" style="display:inline">@csrf
              <button class="botao botao--escuro" title="Enviamos um código para o seu e-mail">Acessar meus ingressos</button></form>
          @endif
        </p>
      @endif
    </div>

    @forelse ($ingressos as $ingresso)
      @php $valido = $ingresso->status === 'valido'; @endphp
      <article class="pub-ingresso {{ $valido ? '' : 'pub-ingresso--invalido' }}" style="margin-bottom:18px">
        <div class="pub-ingresso__dados">
          <small>{{ $org->nomeExibicao() }} apresenta</small>
          <h2 style="margin:4px 0 10px">{{ $evento->titulo }}</h2>
          <dl>
            <dt>Participante</dt><dd>{{ $ingresso->participante?->nome }}</dd>
            <dt>Ingresso</dt><dd>{{ $ingresso->tipo?->nome }} — {{ $ingresso->lote?->nome }}</dd>
            <dt>Data</dt><dd>{{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }}@if ($evento->abertura_portoes) · portões {{ data_local($evento->abertura_portoes, 'H:i') }}@endif</dd>
            <dt>Local</dt><dd>{{ $evento->local ? $evento->local->nome.' · '.$evento->local->cidade.'/'.$evento->local->uf : \App\Models\Evento::FORMATOS[$evento->formato] }}</dd>
            <dt>Situação</dt><dd>{{ \App\Models\Ingresso::STATUS[$ingresso->status] }}</dd>
          </dl>
        </div>
        <div class="pub-ingresso__qr">
          <div class="qr" data-qr="{{ $valido ? $ingresso->conteudoQr() : '' }}" role="img" aria-label="QR Code do ingresso {{ $ingresso->numeroExibicao() }}"></div>
          <b>{{ $ingresso->numeroExibicao() }}</b>
          <small>{{ $valido ? 'Apresente este QR Code na entrada.' : 'Este ingresso não é mais válido.' }}</small>
        </div>
      </article>
    @empty
      <p>Nenhum ingresso neste pedido.</p>
    @endforelse

    <div class="nao-imprimir" style="max-width:760px;margin:10px auto 0">
      <p><small>Não compartilhe o QR Code: quem apresentar primeiro na entrada usa o ingresso.
        @if ($comprador && ! $comprador->senha_hash) Quer guardar seus ingressos numa conta? Use o link “Crie a sua senha” do e-mail (opcional).@endif</small></p>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/qrcode-generator.js') }}"></script>
<script>
  document.querySelectorAll('[data-qr]').forEach((alvo) => {
    if (!alvo.dataset.qr || typeof qrcode === 'undefined') return;
    const qr = qrcode(0, 'M'); qr.addData(alvo.dataset.qr); qr.make();
    alvo.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  });
</script>
@endpush
