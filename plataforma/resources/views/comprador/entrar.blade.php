@extends('layouts.publico')
@section('titulo', 'Meus ingressos — entrar')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Meus ingressos</h1>
    <p>Entre com e-mail e senha, ou receba um código no e-mail (abaixo).</p>
    <form method="post" action="{{ route('comprador.entrar') }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px">
        <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio autocomplete="email" autofocus />
        <x-campo nome="senha" rotulo="Senha" tipo="password" obrigatorio autocomplete="current-password" />
      </div>
      <button class="botao botao--amarelo botao--largo">Entrar</button>
    </form>
    <form method="post" action="{{ route('comprador.codigo.enviar') }}" class="pub-cartao" style="margin-top:16px">
      @csrf
      <b>Sem senha? Entre com um código no e-mail</b>
      <p style="margin:4px 0 10px"><small>Use o e-mail da compra: enviamos um código de 6 dígitos.</small></p>
      <div class="form-grade" style="margin-bottom:12px"><x-campo nome="email" rotulo="E-mail" tipo="email" classe="col-12" autocomplete="email" /></div>
      <button class="botao botao--escuro botao--largo">Receber código</button>
    </form>
    <p style="margin-top:16px">Ainda não tem conta? <a href="{{ route('comprador.criar') }}">Criar conta</a>. Você também pode criar a conta ao garantir um ingresso.</p>
    <p><a href="{{ route('senha.pedir', 'comprador') }}">Esqueci a senha</a></p>
    <p><small>É produtor? Use a <a href="{{ route('entrar') }}">Área do produtor</a>.</small></p>
  </div>
</section>
@endsection
