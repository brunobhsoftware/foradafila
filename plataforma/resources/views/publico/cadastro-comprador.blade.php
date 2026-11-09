@extends('layouts.publico')
@section('titulo', 'Meu cadastro')

@section('conteudo')
<section class="pub-secao">
  <div class="container" style="max-width:640px">
    <div class="pub-cartao">
      @if ($volta)<div class="pub-trilha" style="margin-bottom:4px"><a href="{{ $volta }}" style="color:var(--laranja)">‹ Voltar à compra</a></div>@endif
      <h1 style="font-size:1.7rem;margin-bottom:6px">Meu cadastro</h1>

      @unless ($enviado)
        <p>Para proteger os seus dados, enviamos um código de 6 dígitos para o e-mail do cadastro (<b>{{ $emailMascarado }}</b>).</p>
        <form method="post" action="{{ route('compra.cadastro.codigo') }}">
          @csrf <input type="hidden" name="cpf" value="{{ $cpf }}"> <input type="hidden" name="volta" value="{{ $volta }}">
          <button class="botao botao--amarelo">Enviar código para o meu e-mail</button>
        </form>
      @else
        <form method="post" action="{{ route('compra.cadastro.salvar') }}" class="form-grade">
          @csrf
          <div class="campo col-6"><label for="codigo">Código enviado para {{ $emailMascarado }} <span class="obrigatorio">*</span></label>
            <input id="codigo" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus>
            @error('codigo')<small class="erro">{{ $message }}</small>@enderror</div>
          <div class="campo col-6"><label>CPF</label><input value="{{ \App\Support\Documento::formatar($cpf) }}" disabled></div>
          <x-campo nome="nome" rotulo="Nome completo" obrigatorio classe="col-12" :valor="$comprador->nome" autocomplete="name" />
          <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio classe="col-6" :valor="$comprador->email" autocomplete="email" />
          <x-campo nome="telefone" rotulo="Celular (opcional)" tipo="tel" classe="col-6" :valor="$comprador->telefone" data-mascara="telefone" autocomplete="tel" />
          <div class="col-12"><small>Senha (opcional): crie se quiser entrar em “Meus ingressos” com e-mail e senha.</small></div>
          <x-campo nome="senha" rotulo="Senha" tipo="password" classe="col-6" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
          <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" classe="col-6" autocomplete="new-password" />
          <div class="col-12"><button class="botao botao--amarelo">Salvar cadastro{{ $volta ? ' e voltar à compra' : '' }}</button></div>
        </form>
        <form method="post" action="{{ route('compra.cadastro.codigo') }}" style="margin-top:10px">
          @csrf <input type="hidden" name="cpf" value="{{ $cpf }}"> <input type="hidden" name="volta" value="{{ $volta }}">
          <button class="botao botao--contorno botao--pequeno">Enviar outro código</button>
        </form>
      @endunless
    </div>
  </div>
</section>
@endsection
