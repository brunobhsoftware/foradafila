@extends('layouts.publico')
@section('titulo', 'Meus ingressos — entrar')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Meus ingressos</h1>
    <p>Entre com o e-mail e a senha que você criou ao garantir o ingresso.</p>
    <form method="post" action="{{ route('comprador.entrar') }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px">
        <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio autocomplete="email" autofocus />
        <x-campo nome="senha" rotulo="Senha" tipo="password" obrigatorio autocomplete="current-password" />
      </div>
      <button class="botao botao--amarelo botao--largo">Entrar</button>
    </form>
    <p style="margin-top:16px">Ainda não tem conta? <a href="{{ route('comprador.criar') }}">Criar conta</a>. Você também pode criar a conta ao garantir um ingresso.</p>
    <p><a href="{{ route('senha.pedir', 'comprador') }}">Esqueci a senha</a></p>
    <p><small>É produtor? Use a <a href="{{ route('entrar') }}">Área do produtor</a>.</small></p>
  </div>
</section>
@endsection
