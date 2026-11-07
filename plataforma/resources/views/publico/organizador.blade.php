@extends('layouts.publico')
@section('titulo', $org->nomeExibicao())

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <div class="pub-trilha"><a href="{{ route('vitrine') }}">Eventos</a> › Organizador</div>
    @if ($org->logo_url)<img src="{{ $org->logo_url }}" alt="Logo de {{ $org->nomeExibicao() }}" width="96" height="96" style="width:96px;height:96px;object-fit:contain;background:#fff;border-radius:16px;margin-bottom:16px">@endif
    <h1>{{ $org->nomeExibicao() }}</h1>
    @if ($org->descricao)<p>{{ $org->descricao }}</p>@endif
    <p style="font-size:.95rem">{{ $org->cidade ? $org->cidade.'/'.$org->uf.' · ' : '' }}Atendimento: <a href="mailto:{{ $org->email_contato }}" style="color:var(--amarelo)">{{ $org->email_contato }}</a>
      @if ($org->instagram) · <a href="https://instagram.com/{{ $org->instagram }}" rel="noopener" target="_blank" style="color:var(--amarelo)">{{ '@'.$org->instagram }}</a>@endif</p>
  </div>
</section>
<section class="pub-secao">
  <div class="container">
    <h2>Próximos eventos</h2>
    @if ($eventos->isEmpty())
      <div class="pub-vazio"><p>Nenhum evento publicado no momento.</p></div>
    @else
      <div class="pub-eventos">
        @foreach ($eventos as $evento) @include('publico._cartao-evento', ['evento' => $evento]) @endforeach
      </div>
    @endif

    @if ($passados->isNotEmpty())
      <h2 style="margin-top:48px">Eventos passados</h2>
      <div class="pub-eventos pub-eventos--passados">
        @foreach ($passados as $evento) @include('publico._cartao-evento', ['evento' => $evento]) @endforeach
      </div>
    @endif
  </div>
</section>
@endsection
