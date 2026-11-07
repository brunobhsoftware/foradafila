@extends('layouts.publico')
@section('titulo', 'Nova senha')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>Criar nova senha</h1>
    <p>Conta: {{ $info['nome'] }}. Depois de salvar, este link deixa de funcionar.</p>
    <form method="post" action="{{ request()->fullUrl() }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px">
        <x-campo nome="senha" rotulo="Nova senha" tipo="password" obrigatorio autocomplete="new-password" classe="col-6" ajuda="Mínimo de 8 caracteres, com letras e números." />
        <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" obrigatorio autocomplete="new-password" classe="col-6" />
      </div>
      <button class="botao botao--amarelo botao--largo">Salvar nova senha</button>
    </form>
  </div>
</section>
@endsection
