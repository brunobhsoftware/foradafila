@extends('layouts.painel')
@section('titulo', 'Início')

@section('conteudo')
<div class="cabecalho"><div><h1>Olá, {{ auth()->user()->primeiroNome() }}!</h1><p>Seu perfil: {{ $tenant->vinculo()->perfil->nome }}</p></div></div>
<div class="cartao"><div class="vazio">
    <h3>Use o menu ao lado</h3>
    <p>Seu perfil dá acesso só às funções que aparecem no menu. Fale com o responsável pela organização se precisar de mais acesso.</p>
</div></div>
@endsection
