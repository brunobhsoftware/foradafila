@extends('layouts.publico')
@section('titulo', 'Meus dados')

@section('conteudo')
@include('comprador._abas')
<section class="pub-secao">
  <div class="container" style="max-width:640px">
    @unless ($verificado)
      <div class="pub-cartao" style="margin-bottom:16px;border-left:4px solid var(--laranja)">
        <b>Para editar os seus dados, confirme o seu e-mail</b>
        <p style="margin:6px 0 12px"><small>Por segurança, enviamos um código de 6 dígitos para <b>{{ $emailMascarado }}</b>. Quem entrou com e-mail e senha já pode editar.</small></p>
        <form method="post" action="{{ route('comprador.dados.codigo') }}">@csrf<button class="botao botao--amarelo">Enviar código para o meu e-mail</button></form>
      </div>
    @endunless
    <form method="post" action="{{ route('comprador.dados') }}" class="pub-cartao form-grade">
      <fieldset @disabled(! $verificado) style="display:contents;border:0;padding:0;margin:0">
      @csrf @method('PUT')
      <div class="campo col-6"><label>E-mail</label><input value="{{ $comprador->email }}" disabled>
        <small>É o seu login e para onde vão os ingressos.</small></div>
      @if ($comprador->cpf)
        <div class="campo col-6"><label>CPF</label><input value="{{ \App\Support\Documento::formatar($comprador->cpf) }}" disabled></div>
      @else
        <x-campo nome="cpf" rotulo="CPF (opcional)" classe="col-6" data-mascara="cpf" inputmode="numeric" :valor="''" autocomplete="off" ajuda="Em branco até você informar. Com o CPF, seus dados aparecem sozinhos na próxima compra." />
      @endif
      <x-campo nome="nome" rotulo="Nome completo" obrigatorio classe="col-12" :valor="$comprador->nome" autocomplete="name" />
      <x-campo nome="telefone" rotulo="Celular (opcional)" tipo="tel" classe="col-6" :valor="$comprador->telefone" data-mascara="telefone" autocomplete="tel" />
      <x-campo nome="data_nascimento" rotulo="Data de nascimento" tipo="date" classe="col-6" :valor="$comprador->data_nascimento?->format('Y-m-d')" autocomplete="bday" ajuda="Para saber a idade (eventos com classificação etária)." />
      <div class="col-12" style="margin-top:6px"><b>{{ $comprador->senha_hash ? 'Trocar a senha' : 'Criar uma senha' }}</b> <small>(opcional; em branco mantém)</small></div>
      <x-campo nome="senha" rotulo="Nova senha" tipo="password" classe="col-6" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
      <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" classe="col-6" autocomplete="new-password" />
      @if ($verificado)<div class="col-12"><button class="botao botao--amarelo">Salvar</button></div>@endif
      </fieldset>
    </form>
  </div>
</section>
@endsection
