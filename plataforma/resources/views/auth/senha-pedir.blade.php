@extends('layouts.publico')
@section('titulo', 'Esqueci a senha')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Esqueci a senha</h1>
    <p>Informe o e-mail da sua conta ({{ $info['nome'] }}). Enviaremos um link para criar uma nova senha.</p>
    <form method="post" action="{{ route('senha.pedir', $area) }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px"><x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio autocomplete="email" autofocus /></div>
      <button class="botao botao--amarelo botao--largo">Enviar link</button>
    </form>
    <p style="margin-top:16px"><a href="{{ route($info['login']) }}">‹ Voltar para entrar</a></p>
  </div>
</section>
@endsection
