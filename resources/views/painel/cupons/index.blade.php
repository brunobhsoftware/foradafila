@extends('layouts.painel')
@section('titulo', 'Cupons')

@php $sit = ['ativo' => ['selo--verde', 'Ativo'], 'esgotado' => ['selo--laranja', 'Esgotado'], 'expirado' => ['selo--cinza', 'Expirado'], 'agendado' => ['selo--azul', 'Agendado'], 'desativado' => ['selo--cinza', 'Desativado']]; @endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Cupons de desconto</h1>
    <p>O comprador digita o código na compra e ganha o desconto. Os cupons valem na venda paga (online em breve).</p></div></div>

<form method="get" class="filtros cartao" style="padding:14px 16px">
    <div class="campo" style="flex:1 1 300px"><label>Mostrar cupons de</label><select name="evento" onchange="this.form.submit()">
        <option value="">Todos os eventos</option>
        @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($eventoFiltro && $eventoFiltro->id === $e->id)>{{ $e->titulo }}</option>@endforeach</select></div>
</form>

<section class="cartao" style="margin-top:14px">
    @if ($cupons->isEmpty())
        <div class="vazio"><h3>Nenhum cupom ainda</h3><p>Crie o primeiro abaixo.</p></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Código</th><th>Desconto</th><th>Vale para</th><th>Usos</th><th>Validade</th><th>Situação</th><th class="num">Ações</th></tr></thead>
        <tbody>
        @foreach ($cupons as $c)
            @php [$cl, $tx] = $sit[$c->situacao()]; @endphp
            <tr>
                <td><b>{{ $c->codigo }}</b>@if ($c->descricao)<br><small>{{ $c->descricao }}</small>@endif</td>
                <td>{{ $c->descontoTexto() }}@if ($c->valor_minimo_pedido)<br><small>pedido mín. {{ moeda($c->valor_minimo_pedido) }}</small>@endif</td>
                <td>{{ $c->evento?->titulo ?? 'Todos os eventos' }}@if ($c->lotes->isNotEmpty())<br><small>{{ $c->lotes->pluck('nome')->implode(', ') }}</small>@endif</td>
                <td>{{ numero($c->usos) }} / {{ $c->max_usos ? numero($c->max_usos) : '∞' }}</td>
                <td><small>{{ $c->valido_de ? data_local($c->valido_de, 'd/m H:i') : 'já' }} → {{ $c->valido_ate ? data_local($c->valido_ate, 'd/m H:i') : 'sem fim' }}</small></td>
                <td><span class="selo {{ $cl }}">{{ $tx }}</span></td>
                <td class="num"><div class="acoes-linha">
                    <form method="post" action="{{ route('cupons.alternar', $c) }}">@csrf<button class="botao botao--contorno botao--pequeno">{{ $c->ativo ? 'Desativar' : 'Ativar' }}</button></form>
                    <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="cupom-{{ $c->id }}">Editar</button>
                </div></td>
            </tr>
            <tr class="linha-acoes" id="cupom-{{ $c->id }}" @unless (old('_cupom') == $c->id) hidden @endunless><td colspan="7">@include('painel.cupons._form', ['c' => $c])</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Novo cupom</h2>
    @include('painel.cupons._form', ['c' => null])
</section>
@endsection
