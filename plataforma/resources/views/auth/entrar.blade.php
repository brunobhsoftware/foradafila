@extends('layouts.acesso')
@section('titulo', 'Entrar')
@section('chamada', 'Bem-vindo de volta.')

@section('conteudo')
<h2>Entrar na área do produtor</h2>
<p class="subtitulo">Ainda não tem conta? <a href="{{ route('cadastro') }}">Criar conta grátis</a></p>

<form method="post" action="{{ route('entrar') }}">
    @csrf
    <div class="form-grade" style="margin-bottom:20px">
        <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio autocomplete="email" autofocus />
        <x-campo nome="senha" rotulo="Senha" tipo="password" obrigatorio autocomplete="current-password" />
    </div>
    <button type="submit" class="botao botao--primario botao--largo">Entrar</button>
    <p class="acesso__rodape">Esqueceu a senha? Por enquanto, fale com o suporte do Fora da Fila.</p>
</form>
@endsection
