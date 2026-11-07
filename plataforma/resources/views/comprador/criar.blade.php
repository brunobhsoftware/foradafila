@extends('layouts.publico')
@section('titulo', 'Criar conta')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Criar conta</h1>
    <p>Para acessar os seus ingressos em qualquer lugar. Já tem conta? <a href="{{ route('comprador.entrar') }}">Entrar</a></p>
    <form method="post" action="{{ route('comprador.criar') }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px">
        @include('comprador._campos-conta')
      </div>
      <button class="botao botao--amarelo botao--largo">Criar conta</button>
    </form>
  </div>
</section>
@endsection
