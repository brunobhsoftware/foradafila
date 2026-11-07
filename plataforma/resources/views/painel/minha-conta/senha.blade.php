@extends('layouts.painel')
@section('titulo', 'Trocar senha')

@section('conteudo')
<div class="cabecalho">
    <div>
        <div class="trilha"><a href="{{ route('organizacao.edit') }}">Organização</a> › Trocar senha</div>
        <h1>Trocar senha</h1>
        <p>Por segurança, a troca é confirmada com um código enviado ao seu e-mail.</p>
    </div>
</div>
<div class="cartao">
    @include('painel.minha-conta._passo-codigo', ['rota' => 'conta.senha.codigo'])
    <form method="post" action="{{ route('conta.senha.salvar') }}">
        @csrf @method('PUT')
        <section class="secao-form">
            <h2>2. Digitar o código e a nova senha</h2>
            <div class="form-grade">
                <x-campo nome="codigo" rotulo="Código recebido por e-mail" obrigatorio classe="col-4" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" />
                <x-campo nome="senha" rotulo="Nova senha" tipo="password" obrigatorio classe="col-4" autocomplete="new-password" ajuda="Mínimo de 8 caracteres, com letras e números." />
                <x-campo nome="senha_confirmation" rotulo="Confirme a nova senha" tipo="password" obrigatorio classe="col-4" autocomplete="new-password" />
            </div>
        </section>
        <div class="acoes-form">
            <a href="{{ route('organizacao.edit') }}" class="botao botao--contorno">Cancelar</a>
            <button class="botao botao--primario">Trocar senha</button>
        </div>
    </form>
</div>
@endsection
