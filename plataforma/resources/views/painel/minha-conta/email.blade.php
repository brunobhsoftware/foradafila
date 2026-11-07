@extends('layouts.painel')
@section('titulo', 'Trocar e-mail de acesso')

@section('conteudo')
<div class="cabecalho">
    <div>
        <div class="trilha"><a href="{{ route('organizacao.edit') }}">Organização</a> › Trocar e-mail</div>
        <h1>Trocar e-mail de acesso</h1>
        <p>Primeiro confirmamos o e-mail atual com um código. Só depois o novo e-mail é gravado.</p>
    </div>
</div>
<div class="cartao">
    @include('painel.minha-conta._passo-codigo', ['rota' => 'conta.email.codigo'])
    <form method="post" action="{{ route('conta.email.salvar') }}">
        @csrf @method('PUT')
        <section class="secao-form">
            <h2>2. Validar e informar o novo e-mail</h2>
            <div class="form-grade">
                <x-campo nome="codigo" rotulo="Código recebido no e-mail atual" obrigatorio classe="col-4" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" />
                <x-campo nome="email" rotulo="Novo e-mail" tipo="email" obrigatorio classe="col-8" autocomplete="email" ajuda="Será o e-mail de login. Avisaremos o e-mail antigo e o novo sobre a troca." />
            </div>
        </section>
        <div class="acoes-form">
            <a href="{{ route('organizacao.edit') }}" class="botao botao--contorno">Cancelar</a>
            <button class="botao botao--primario">Trocar e-mail</button>
        </div>
    </form>
</div>
@endsection
