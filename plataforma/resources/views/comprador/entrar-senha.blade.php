@extends('layouts.publico')
@section('titulo', 'Meus ingressos — senha')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Meus ingressos</h1>
    <p>Entrando como <b>{{ $email }}</b>. <a href="{{ route('comprador.entrar') }}">Trocar</a></p>
    <form method="post" action="{{ route('comprador.entrar') }}" class="pub-cartao">
      @csrf
      <input type="hidden" name="email" value="{{ $email }}">
      @error('email')<p class="erro" style="color:var(--vermelho, #B42318);margin:0 0 10px">{{ $message }}</p>@enderror
      <div class="form-grade" style="margin-bottom:18px">
        <x-campo nome="senha" rotulo="Senha" tipo="password" obrigatorio autocomplete="current-password" autofocus classe="col-12" />
      </div>
      <button class="botao botao--amarelo botao--largo">Entrar</button>
    </form>
    <form method="post" action="{{ route('comprador.codigo.enviar') }}" style="margin-top:12px">
      @csrf <input type="hidden" name="email" value="{{ $email }}">
      <button class="botao botao--contorno botao--largo">Receber um código no e-mail</button>
    </form>
    <p style="margin-top:12px"><a href="{{ route('senha.pedir', 'comprador') }}">Esqueci a senha</a></p>
  </div>
</section>
@endsection
