@extends('layouts.publico')
@section('titulo', 'Complete seu cadastro')

@section('conteudo')
<section class="pub-secao">
  <div class="container" style="max-width:640px">
    <h1>Complete seu cadastro</h1>
    <p>E-mail confirmado: <b>{{ $email }}</b>.</p>
    <form method="post" action="{{ route('comprador.completar') }}" class="pub-cartao form-grade">
      @csrf
      <x-campo nome="nome" rotulo="Nome completo" obrigatorio classe="col-12" autocomplete="name" autofocus />
      <x-campo nome="cpf" rotulo="CPF" obrigatorio classe="col-6" data-mascara="cpf" inputmode="numeric" autocomplete="off" />
      <x-campo nome="data_nascimento" rotulo="Data de nascimento" obrigatorio tipo="date" classe="col-6" autocomplete="bday" />
      <x-campo nome="telefone" rotulo="Celular" obrigatorio tipo="tel" classe="col-6" data-mascara="telefone" autocomplete="tel" />
      <div class="col-6"></div>
      <x-campo nome="senha" rotulo="Senha" obrigatorio tipo="password" classe="col-6" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
      <x-campo nome="senha_confirmation" rotulo="Repita a senha" obrigatorio tipo="password" classe="col-6" autocomplete="new-password" />
      <div class="col-12"><button class="botao botao--amarelo">Criar minha conta</button></div>
    </form>
  </div>
</section>
@endsection
