@extends('layouts.publico')
@section('titulo', 'Meus dados')

@section('conteudo')
@include('comprador._abas')
<section class="pub-secao">
  <div class="container" style="max-width:640px">
    <form method="post" action="{{ route('comprador.dados') }}" class="pub-cartao form-grade">
      @csrf @method('PUT')
      <div class="campo col-6"><label>E-mail</label><input value="{{ $comprador->email }}" disabled>
        <small>É o seu login e para onde vão os ingressos.</small></div>
      @if ($comprador->cpf)
        <div class="campo col-6"><label>CPF</label><input value="{{ \App\Support\Documento::formatar($comprador->cpf) }}" disabled></div>
      @else
        <x-campo nome="cpf" rotulo="CPF (opcional)" classe="col-6" data-mascara="cpf" inputmode="numeric" ajuda="Com o CPF, seus dados aparecem sozinhos na próxima compra." />
      @endif
      <x-campo nome="nome" rotulo="Nome completo" obrigatorio classe="col-12" :valor="$comprador->nome" autocomplete="name" />
      <x-campo nome="telefone" rotulo="Celular (opcional)" tipo="tel" classe="col-6" :valor="$comprador->telefone" data-mascara="telefone" autocomplete="tel" />
      <div class="col-12" style="margin-top:6px"><b>{{ $comprador->senha_hash ? 'Trocar a senha' : 'Criar uma senha' }}</b> <small>(opcional; em branco mantém)</small></div>
      <x-campo nome="senha" rotulo="Nova senha" tipo="password" classe="col-6" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
      <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" classe="col-6" autocomplete="new-password" />
      <div class="col-12"><button class="botao botao--amarelo">Salvar</button></div>
    </form>
  </div>
</section>
@endsection
