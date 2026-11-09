@extends('layouts.publico')
@section('titulo', 'Alterar meu e-mail')

@section('conteudo')
@include('comprador._abas')
<section class="pub-secao">
  <div class="container" style="max-width:520px">
    @unless ($verificado)
      <div class="pub-cartao" style="border-left:4px solid var(--laranja)">
        <b>Para alterar o e-mail, confirme primeiro o e-mail atual</b>
        <p style="margin:6px 0 12px"><small>Enviamos um código de 6 dígitos para <b>{{ $emailMascarado }}</b>.</small></p>
        <form method="post" action="{{ route('comprador.dados.codigo') }}">@csrf<button class="botao botao--amarelo">Enviar código para o meu e-mail</button></form>
      </div>
    @else
      <form method="post" action="{{ route('comprador.email.codigo') }}" class="pub-cartao form-grade">
        @csrf
        <div class="col-12"><h2 style="font-size:1.2rem;margin:0">Alterar meu e-mail</h2>
          <small>E-mail atual: {{ $emailMascarado }}. Vamos mandar um código para o novo e-mail para confirmar que ele é seu.</small></div>
        <x-campo nome="email" rotulo="Novo e-mail" tipo="email" obrigatorio classe="col-12" :valor="$novo" autocomplete="email" />
        <div class="col-12"><button class="botao {{ $novo ? 'botao--contorno' : 'botao--amarelo' }}">{{ $novo ? 'Enviar outro código' : 'Enviar código para o novo e-mail' }}</button></div>
      </form>
      @if ($novo)
        <form method="post" action="{{ route('comprador.email') }}" class="pub-cartao form-grade" style="margin-top:14px">
          @csrf @method('PUT')
          <div class="campo col-12"><label for="codigo">Código enviado para {{ $novo }} <span class="obrigatorio">*</span></label>
            <input id="codigo" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus style="font-size:1.3rem;letter-spacing:.3em">
            @error('codigo')<small class="erro">{{ $message }}</small>@enderror</div>
          <div class="col-12" style="display:flex;gap:10px;flex-wrap:wrap"><button class="botao botao--amarelo">Confirmar novo e-mail</button>
            <a href="{{ route('comprador.dados') }}" class="botao botao--contorno">Voltar</a></div>
        </form>
      @endif
    @endunless
  </div>
</section>
@endsection
