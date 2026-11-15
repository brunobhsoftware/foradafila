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
        <thead><tr><th>Pedido</th><th>Evento / comprador</th><th class="num">Total</th><th class="num col-larga">Ações</th></tr></thead>
        <tbody>
        @foreach ($pedidos as $p)
            <tr>
                <td style="white-space:nowrap"><a href="{{ route('pedidos.show', $p) }}"><b>{{ $p->codigo }}</b></a><br>
                    <span class="selo {{ ['pago' => 'selo--verde', 'cancelado' => 'selo--cinza', 'reembolsado' => 'selo--laranja', 'reembolso_parcial' => 'selo--laranja'][$p->status] ?? 'selo--amarelo' }}">{{ $S[$p->status] ?? $p->status }}</span><br>
                    <small>{{ data_local($p->criado_em, 'd/m/y H:i') }}</small></td>
                <td class="celula-quebra"><b>{{ $p->evento?->titulo }}</b><br>{{ $p->comprador?->nome }}<br><small>{{ $p->comprador?->email }}</small></td>
                <td class="num" style="white-space:nowrap">{{ (float) $p->valor_total > 0 ? moeda($p->valor_total) : 'Grátis' }}<br><small>{{ $p->ingressos_count }} {{ $p->ingressos_count == 1 ? 'ingresso' : 'ingressos' }}</small></td>
                <td class="num col-larga"><a href="{{ route('pedidos.show', $p) }}" class="botao botao--contorno botao--pequeno">Abrir</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
{{ $pedidos->links() }}
@endsection
