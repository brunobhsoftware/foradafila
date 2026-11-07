@extends('layouts.publico')
@section('titulo', $org->nomeExibicao())

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <div class="pub-trilha"><a href="{{ route('vitrine') }}">Eventos</a> › Organizador</div>
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
  </div>
</section>
@endsection
