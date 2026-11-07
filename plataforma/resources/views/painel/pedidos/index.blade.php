@extends('layouts.painel')
@section('titulo', 'Pedidos')

@php $S = \App\Models\Pedido::STATUS; @endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Pedidos</h1><p>Compras e inscrições de todos os eventos.</p></div></div>

<form method="get" action="{{ route('pedidos.index') }}" class="filtros cartao" style="padding:14px 16px">
    <div class="campo" style="flex:1 1 260px"><label>Evento</label><select name="evento"><option value="">Todos os eventos</option>
        @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($evento && $evento->id === $e->id)>{{ $e->titulo }}</option>@endforeach</select></div>
    <div class="campo"><label>Situação</label><select name="status"><option value="">Todas</option>@foreach ($S as $k => $t)<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $t }}</option>@endforeach</select></div>
    <div class="campo" style="flex:1 1 220px"><label>Buscar</label><input name="busca" value="{{ $f['busca'] ?? '' }}" placeholder="Código do pedido, nome ou e-mail"></div>
    <button class="botao botao--escuro botao--pequeno">Filtrar</button>
</form>

<div class="grade grade-2" style="margin-top:14px">
    <div class="cartao kpi"><x-icone nome="vendas" /><small>Pedidos confirmados</small><b>{{ numero($totais->n ?? 0) }}</b><small>{{ $evento ? 'neste evento' : 'em todos os eventos' }}</small></div>
    <div class="cartao kpi"><x-icone nome="dinheiro" /><small>Valor dos pedidos</small><b>{{ moeda($totais->total ?? 0) }}</b><small>confirmados</small></div>
</div>

<section class="cartao" style="margin-top:14px">
    @if ($pedidos->isEmpty())
        <div class="vazio"><h3>Nenhum pedido encontrado</h3></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Pedido</th><th>Comprador</th><th>Evento</th><th class="num">Ingressos</th><th class="num">Total</th><th>Situação</th><th>Data</th></tr></thead>
        <tbody>
        @foreach ($pedidos as $p)
            <tr>
                <td><a href="{{ route('pedidos.show', $p) }}"><b>{{ $p->codigo }}</b></a></td>
                <td>{{ $p->comprador?->nome }}<br><small>{{ $p->comprador?->email }}</small></td>
                <td>{{ $p->evento?->titulo }}</td>
                <td class="num">{{ $p->ingressos_count }}</td>
                <td class="num">{{ (float) $p->valor_total > 0 ? moeda($p->valor_total) : 'Grátis' }}</td>
                <td><span class="selo {{ ['pago' => 'selo--verde', 'cancelado' => 'selo--cinza', 'reembolsado' => 'selo--laranja', 'reembolso_parcial' => 'selo--laranja'][$p->status] ?? 'selo--amarelo' }}">{{ $S[$p->status] ?? $p->status }}</span></td>
                <td>{{ data_local($p->criado_em, 'd/m/Y H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
<x-paginacao :paginator="$pedidos" />
@endsection
