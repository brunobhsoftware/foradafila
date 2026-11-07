@extends('layouts.publico')
@section('titulo', 'Ingresso '.$ingresso->numeroExibicao())

@php
  $evento = $ingresso->evento; $org = $ingresso->pedido->organizadorPublico;
  $valido = $ingresso->status === 'valido';
@endphp

@section('conteudo')
<section class="pub-secao">
  <div class="container">
    <div class="nao-imprimir" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;max-width:760px;margin-inline:auto">
      <a href="{{ route('comprador.ingressos') }}">‹ Meus ingressos</a>
      @if ($valido)<button type="button" class="botao botao--escuro botao--pequeno" onclick="window.print()">Imprimir / salvar em PDF</button>@endif
    </div>

    <article class="pub-ingresso {{ $valido ? '' : 'pub-ingresso--invalido' }}">
      <div class="pub-ingresso__dados">
        <small>{{ $org->nomeExibicao() }} apresenta</small>
        <h1>{{ $evento->titulo }}</h1>
        <dl>
          <dt>Participante</dt><dd>{{ $ingresso->participante?->nome }}</dd>
          <dt>Ingresso</dt><dd>{{ $ingresso->tipo->nome }} — {{ $ingresso->lote->nome }} ({{ \App\Models\Lote::MODALIDADES[$ingresso->lote->modalidade] }})</dd>
          <dt>Data</dt><dd>{{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }}@if ($evento->abertura_portoes) · portões {{ data_local($evento->abertura_portoes, 'H:i') }}@endif</dd>
          <dt>Local</dt><dd>{{ $evento->local ? $evento->local->nome.' · '.$evento->local->cidade.'/'.$evento->local->uf : \App\Models\Evento::FORMATOS[$evento->formato] }}</dd>
          <dt>Pedido</dt><dd>{{ $ingresso->pedido->codigo }} · {{ (float) $ingresso->valor_pago > 0 ? moeda($ingresso->valor_pago) : 'Gratuito' }}</dd>
          <dt>Situação</dt><dd>{{ \App\Models\Ingresso::STATUS[$ingresso->status] }}</dd>
        </dl>
        <p style="margin:18px 0 0"><small>Organizador responsável pelo evento: {{ $org->razao_social }} · atendimento {{ $org->email_contato }}.
          Emitido pela plataforma fora da fila.</small></p>
      </div>
      <div class="pub-ingresso__qr">
        <div class="qr" id="qr" data-conteudo="{{ $valido ? $ingresso->conteudoQr() : '' }}" role="img" aria-label="QR Code do ingresso {{ $ingresso->numeroExibicao() }}"></div>
        <b>{{ $ingresso->numeroExibicao() }}</b>
        <small>{{ $valido ? 'Apresente este QR Code na entrada. Cada ingresso vale para uma entrada.' : 'Este ingresso não é mais válido.' }}</small>
      </div>
    </article>

    <div class="nao-imprimir" style="max-width:760px;margin:20px auto 0">
      <p><small>Esta é a segunda via do mesmo ingresso: o QR Code é sempre o mesmo e não é gerado de novo.
        Não compartilhe o QR Code — quem apresentar primeiro na entrada usa o ingresso.</small></p>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/qrcode-generator.js') }}"></script>
<script>
  (function () {
    const alvo = document.getElementById('qr');
    const conteudo = alvo && alvo.dataset.conteudo;
    if (!conteudo || typeof qrcode === 'undefined') return;
    const qr = qrcode(0, 'M');
    qr.addData(conteudo);
    qr.make();
    alvo.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  })();
</script>
@endpush
