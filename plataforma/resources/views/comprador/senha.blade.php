@extends('layouts.publico')
@section('titulo', 'Alterar senha')

@section('conteudo')
@include('comprador._abas')
<section class="pub-secao">
  <div class="container" style="max-width:520px">
    @unless ($verificado)
      <div class="pub-cartao" style="border-left:4px solid var(--laranja)">
        <b>Para {{ $criar ? 'criar' : 'alterar' }} a senha, confirme o seu e-mail</b>
        <p style="margin:6px 0 12px"><small>Enviamos um código de 6 dígitos para <b>{{ $emailMascarado }}</b>.</small></p>
        <form method="post" action="{{ route('comprador.dados.codigo') }}">@csrf<button class="botao botao--amarelo">Enviar código para o meu e-mail</button></form>
      </div>
    @else
      <form method="post" action="{{ route('comprador.senha') }}" class="pub-cartao form-grade">
        @csrf @method('PUT')
        <x-campo nome="senha" rotulo="Nova senha" tipo="password" obrigatorio classe="col-12" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
        <x-campo nome="senha_confirmation" rotulo="Repita a nova senha" tipo="password" obrigatorio classe="col-12" autocomplete="new-password" />
        <div class="col-12" style="display:flex;gap:10px;flex-wrap:wrap"><button class="botao botao--amarelo">{{ $criar ? 'Criar senha' : 'Salvar nova senha' }}</button>
          <a href="{{ route('comprador.dados') }}" class="botao botao--contorno">Voltar</a></div>
      </form>
    @endunless
  </div>
</section>
@endsection
