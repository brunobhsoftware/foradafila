@extends('layouts.painel')
@section('titulo', 'Pedido '.$pedido->codigo)

@php
    $S = \App\Models\Pedido::STATUS; $SI = \App\Models\Ingresso::STATUS;
    $validos = $pedido->ingressos->where('status', 'valido');
    $aDevolver = (float) $validos->sum('valor_pago');
@endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>Pedido {{ $pedido->codigo }}</h1>
        <p>{{ $pedido->evento->titulo }} · {{ data_local($pedido->criado_em, 'd/m/Y H:i') }} ·
            <span class="selo {{ $pedido->status === 'pago' ? 'selo--verde' : 'selo--amarelo' }}">{{ $S[$pedido->status] ?? $pedido->status }}</span></p></div>
    <div class="cabecalho__acoes">
        <a href="{{ route('pedidos.index') }}" class="botao botao--contorno">Voltar</a>
        @if ($pedido->ingressos->whereIn('status', ['valido', 'utilizado'])->isNotEmpty())
            <form method="post" action="{{ route('pedidos.reenviar', $pedido) }}">@csrf<button class="botao botao--escuro">Reenviar confirmação</button></form>
        @endif
    </div>
</div>

@error('cancelamento')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

<div class="grade grade-2">
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Comprador</h2>
        <p><b>{{ $pedido->comprador->nome }}</b><br>{{ $pedido->comprador->email }}@if ($pedido->comprador->telefone)<br>{{ $pedido->comprador->telefone }}@endif</p>
    </section>
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Pagamento</h2>
        <p>Total: <b>{{ (float) $pedido->valor_total > 0 ? moeda($pedido->valor_total) : 'Grátis' }}</b>@if ((float) $pedido->valor_desconto > 0) (desconto {{ moeda($pedido->valor_desconto) }})@endif
            <br>Meio: {{ $pedido->metodo_pagamento ?? '—' }} @if ($pedido->pago_em)· confirmado em {{ data_local($pedido->pago_em) }}@endif</p>
    </section>
</div>

<section class="cartao" style="margin-top:18px">
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Ingresso</th><th>Participante</th><th>Tipo / lote</th><th class="num">Valor pago</th><th>Situação</th></tr></thead>
        <tbody>
        @foreach ($pedido->ingressos->sortBy('numero') as $i)
            <tr><td><b>{{ $i->numeroExibicao() }}</b></td><td>{{ $i->participante?->nome }}<br><small>{{ $i->participante?->email }}</small></td>
                <td>{{ $i->tipo?->nome }}<br><small>{{ $i->lote?->nome }}</small></td><td class="num">{{ (float) $i->valor_pago > 0 ? moeda($i->valor_pago) : 'Grátis' }}</td>
                <td>{{ $SI[$i->status] ?? $i->status }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>

@can('pedido.reembolsar')
    @if ($validos->isNotEmpty())
        <section class="cartao" style="padding:20px;margin-top:18px">
            <h2 style="margin-top:0">Cancelar o pedido</h2>
            <p class="suave">Cancela os {{ $validos->count() }} ingresso(s) válido(s) deste pedido. Ingressos já usados no check-in não são cancelados.</p>
            @include('painel._cancelar-form', ['acao' => route('pedidos.cancelar', $pedido), 'valor' => $aDevolver, 'rotulo' => 'Cancelar pedido'])
        </section>
    @endif
@endcan

@if ($reembolsos->isNotEmpty())
    <section class="cartao" style="padding:20px;margin-top:18px">
        <h2 style="margin-top:0">Devoluções</h2>
        @foreach ($reembolsos as $r)
            <p><b>{{ moeda($r->valor) }}</b> · {{ \App\Support\CancelamentoIngressos::MOTIVOS[$r->motivo] ?? $r->motivo }} ·
                <span class="selo {{ $r->status === 'concluido' ? 'selo--verde' : 'selo--amarelo' }}">{{ $r->status === 'concluido' ? 'Devolvido' : 'A devolver' }}</span>
                <br><small>{{ data_local($r->criado_em) }}{{ $r->observacao ? ' · '.$r->observacao : '' }}</small></p>
        @endforeach
    </section>
@endif
@endsection
