@extends('layouts.publico')
@section('titulo', 'Meus ingressos — entrar')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Meus ingressos</h1>
    <p>Digite o seu e-mail para entrar ou criar a sua conta.</p>
    <form method="post" action="{{ route('comprador.identificar') }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px">
        <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio autocomplete="email" autofocus classe="col-12" />
      </div>
      <button class="botao botao--amarelo botao--largo">Continuar</button>
    </form>
    <p style="margin-top:16px"><small>É produtor? Use a <a href="{{ route('entrar') }}">Área do produtor</a>.</small></p>
  </div>
</section>
@endsection
