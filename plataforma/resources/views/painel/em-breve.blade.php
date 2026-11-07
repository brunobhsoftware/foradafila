@extends('layouts.painel')
@section('titulo', $info['titulo'])

@section('conteudo')
<div class="cabecalho"><div><h1>{{ $info['titulo'] }}</h1><p>Módulo da próxima fase de desenvolvimento.</p></div></div>
<div class="cartao">
    <div class="vazio">
        <h3>Em construção</h3>
        <p>{{ $info['texto'] }}</p>
        <p><small>A estrutura já existe no banco de dados ({{ $info['tabelas'] }}).</small></p>
        <a href="{{ route('painel') }}" class="botao botao--contorno">Voltar ao painel</a>
    </div>
</div>
@endsection
