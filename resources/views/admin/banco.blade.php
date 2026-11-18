@extends('layouts.admin')
@section('titulo', 'Atualizações do banco')

@section('conteudo')
<div class="cabecalho"><div><h1>Atualizações do banco</h1>
    <p>Aplique aqui os scripts novos do banco de dados, sem precisar do phpMyAdmin. Os scripts podem rodar mais de uma vez sem problema.</p></div></div>

@error('banco')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

<section class="cartao">
    @if (empty($scripts))
        <div class="vazio"><h3>Nenhuma atualização disponível</h3></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Versão</th><th>O que faz</th><th>Situação</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($scripts as $s)
            <tr><td><b>{{ $s['versao'] }}</b><br><small>{{ $s['arquivo'] }}</small></td><td>{{ $s['descricao'] }}</td>
                <td>@if ($s['aplicado_em'])<span class="selo selo--verde">Aplicado</span><br><small>{{ data_local($s['aplicado_em']) }}</small>@else<span class="selo selo--amarelo">Pendente</span>@endif</td>
                <td class="num"><form method="post" action="{{ route('admin.banco.aplicar') }}" data-confirmar="Aplicar {{ $s['versao'] }} no banco de produção?">@csrf
                    <input type="hidden" name="arquivo" value="{{ $s['arquivo'] }}">
                    <button class="botao {{ $s['aplicado_em'] ? 'botao--contorno' : 'botao--primario' }} botao--pequeno">{{ $s['aplicado_em'] ? 'Aplicar de novo' : 'Aplicar' }}</button></form></td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
<section class="cartao" style="margin-top:18px;padding:20px 24px">
    <h2 style="margin-top:0">Padronizar nomes de pessoas</h2>
    <p class="suave">Corrige os nomes já gravados de usuários da equipe, compradores, participantes dos ingressos, cadastro único, convidados, listas de e-mail
        , representantes legais e nomes das empresas (razão social e nome fantasia, com ME/EPP/EIRELI/S/A em maiúsculas): primeira letra de cada palavra maiúscula e o resto minúsculo, com “de, da, do, das, dos, e” minúsculos
        (ex.: “BRUNO DE MAGALHÃES” → “Bruno de Magalhães”). Só altera o que estiver diferente; pode rodar de novo.</p>
    <form method="post" action="{{ route('admin.banco.nomes') }}" data-confirmar="Padronizar os nomes de pessoas em todo o banco de produção?">@csrf
        <button class="botao botao--primario botao--pequeno">Padronizar nomes</button></form>
</section>
<p class="suave" style="margin-top:12px"><small>Scripts com gatilhos (triggers) continuam indo pelo phpMyAdmin. Cada aplicação fica registrada na auditoria.</small></p>
@endsection
