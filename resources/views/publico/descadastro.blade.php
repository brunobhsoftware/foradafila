@extends('layouts.publico')
@section('titulo', 'Não quero mais receber')

@section('conteudo')
<section class="pub-secao"><div class="container" style="max-width:560px">
  <h1 style="font-size:1.6rem">Convites por e-mail</h1>
  @if ($feito || session('ok'))
    <p><b>{{ $email }}</b> não vai mais receber convites de <b>{{ $empresa->nomeExibicao() }}</b>.</p>
    <p><small>Os e-mails dos ingressos que você comprar continuam chegando normalmente.</small></p>
  @else
    <p>Deseja parar de receber convites de eventos de <b>{{ $empresa->nomeExibicao() }}</b> no e-mail <b>{{ $email }}</b>?</p>
    <form method="post" action="{{ request()->fullUrl() }}">@csrf
      <button class="botao botao--primario">Não quero mais receber</button>
    </form>
  @endif
</div></section>
@endsection
