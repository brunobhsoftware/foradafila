@extends('layouts.publico')
@section('titulo', 'Eventos')

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>Eventos<span class="ponto">.</span></h1>
    <p>Eventos publicados pelos organizadores no Fora da Fila.</p>
  </div>
</section>

<section class="pub-secao">
  <div class="container">
    @if ($eventos->isEmpty())
      <div class="pub-vazio">
        <h2>Nenhum evento publicado no momento</h2>
        <p>Volte em breve. É produtor? <a href="{{ route('cadastro') }}">Publique o seu evento</a>.</p>
      </div>
    @else
      <div class="pub-eventos">
        @foreach ($eventos as $evento)
          @include('publico._cartao-evento', ['evento' => $evento])
        @endforeach
      </div>
      {{ $eventos->links() }}
    @endif
  </div>
</section>
@endsection
