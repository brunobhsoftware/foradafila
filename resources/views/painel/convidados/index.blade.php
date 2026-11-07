@extends('layouts.painel')
@section('titulo', 'Convidados')

@section('conteudo')
<div class="cabecalho"><div><h1>Convidados</h1><p>Listas de convidados por evento (VIP, imprensa, aniversariantes...). Os ingressos são emitidos sem cobrança e enviados por e-mail.</p></div></div>

@if ($eventos->isEmpty())
    <div class="cartao vazio"><h3>Nenhum evento ainda</h3></div>
@else
<form method="get" action="{{ route('convidados.index') }}" class="filtros cartao" style="padding:14px 16px">
    <div class="campo" style="flex:1 1 320px"><label>Evento</label><select name="evento" onchange="this.form.submit()">
        @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($evento && $evento->id === $e->id)>{{ $e->titulo }} — {{ data_local($e->data_inicio, 'd/m/Y') }}</option>@endforeach</select></div>
</form>

<section class="cartao" style="margin-top:14px">
    @if ($listas->isEmpty())
        <div class="vazio"><h3>Nenhuma lista neste evento</h3><p>Crie a primeira abaixo.</p></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Lista</th><th>Lote dos ingressos</th><th>Convidados</th><th>Emitidos</th><th>Acompanhantes</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($listas as $l)
            <tr><td><a href="{{ route('convidados.lista', [$evento, $l->id]) }}"><b>{{ $l->nome }}</b></a></td><td>{{ $l->lote_nome }}</td>
                <td>{{ $l->total }}{{ $l->limite_convidados ? ' / '.$l->limite_convidados : '' }}</td><td>{{ $l->emitidos }}</td><td>até {{ $l->max_acompanhantes }}</td>
                <td class="num"><a href="{{ route('convidados.lista', [$evento, $l->id]) }}" class="botao botao--contorno botao--pequeno">Abrir</a></td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Nova lista</h2>
    @if ($lotes->isEmpty())
        <p class="suave">Este evento ainda não tem lotes. Crie um lote (de preferência "Para ser adicionado manualmente") em Meus eventos.</p>
    @else
        @include('painel.convidados._lista-form', ['l' => null])
    @endif
</section>
@endif
@endsection
