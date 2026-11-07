@extends('layouts.acesso')
@section('titulo', 'Convite para a equipe')
@section('chamada', 'Você foi convidado para uma equipe.')

@section('conteudo')
<h2>Entrar na equipe de {{ $organizador->nomeExibicao() }}</h2>
<p class="subtitulo">Perfil de acesso: <b>{{ $vinculo->perfil->nome }}</b> · convite para {{ $vinculo->usuario->email }}</p>

<form method="post" action="{{ route('convite', $token) }}">
    @csrf
    <div class="form-grade" style="margin-bottom:20px">
        @if ($vinculo->usuario->senha_hash)
            <p class="col-12">Você já tem conta no Fora da Fila. Confirme sua senha para aceitar.</p>
            <x-campo nome="senha" rotulo="Sua senha" tipo="password" obrigatorio autocomplete="current-password" />
        @else
            <x-campo nome="nome" rotulo="Seu nome completo" :valor="$vinculo->usuario->nome" obrigatorio />
            <x-campo nome="telefone" rotulo="Celular" tipo="tel" data-mascara="telefone" />
            <x-campo nome="senha" rotulo="Crie uma senha" tipo="password" obrigatorio autocomplete="new-password" classe="col-6" ajuda="Mínimo de 8 caracteres, com letras e números." />
            <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" obrigatorio autocomplete="new-password" classe="col-6" />
        @endif
    </div>
    <button type="submit" class="botao botao--primario botao--largo">Aceitar convite</button>
    <p class="acesso__rodape">Convite válido até {{ data_local($vinculo->convite_expira_em) }}.</p>
</form>
@endsection
