@extends('layouts.publico')
@section('titulo', 'Acessar meus ingressos')

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-form-caixa">
    <h1>{{ $novo ? 'Criar a sua conta' : 'Acessar meus ingressos' }}</h1>
    <p>Digite o código de 6 dígitos que enviamos para <b>{{ $emailMascarado }}</b>.</p>
    <form method="post" action="{{ route('comprador.codigo') }}" class="pub-cartao">
      @csrf
      <div class="form-grade" style="margin-bottom:18px">
        <div class="campo col-12"><label for="codigo">Código <span class="obrigatorio">*</span></label>
          <input id="codigo" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus style="font-size:1.4rem;letter-spacing:.3em">
          @error('codigo')<small class="erro">{{ $message }}</small>@enderror</div>
        @if ($semSenha)
          <div class="col-12"><b>Aproveite e cadastre a sua senha</b> <small>(opcional)</small><br>
            <small>Com a senha você entra direto em “Meus ingressos”, sem precisar de código.</small></div>
          <x-campo nome="senha" rotulo="Senha" tipo="password" classe="col-6" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
          <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" classe="col-6" autocomplete="new-password" />
        @endif
      </div>
      <button class="botao botao--amarelo botao--largo">{{ $novo ? 'Confirmar e-mail' : 'Entrar' }}</button>
    </form>
    <p style="margin-top:14px"><small>Não recebeu? Confira o spam ou <a href="{{ route('comprador.entrar') }}">peça outro código</a> (um a cada minuto).</small></p>
  </div>
</section>
@endsection
