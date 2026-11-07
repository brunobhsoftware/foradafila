@extends('layouts.admin')
@section('titulo', 'Taxas e isenções')

@php $O = \App\Support\MotorTaxas::OPERACOES; @endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Taxas e isenções</h1>
    <p>Taxa = maior valor entre (valor × percentual + tarifa fixa) e a taxa mínima. A faixa é escolhida pelo volume movimentado pela empresa no mês.
        Taxas específicas de uma empresa sobrepõem estas.</p></div></div>

<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">1. Taxas padrão e faixas por volume</h2>
    @forelse ($regras as $operacao => $lista)
        <h3 style="margin:16px 0 6px">{{ $O[$operacao] ?? $operacao }}</h3>
        <div class="tabela-rolagem"><table class="tabela">
            <thead><tr><th>Regra</th><th>Faixa de volume no mês</th><th class="num">%</th><th class="num">Fixo</th><th class="num">Mínimo</th><th>Carência</th><th>Situação</th><th class="num"></th></tr></thead>
            <tbody>
            @foreach ($lista as $r)
                <tr>
                    <td><b>{{ $r->nome }}</b></td>
                    <td>{{ moeda($r->volume_min) }} → {{ $r->volume_max !== null ? moeda($r->volume_max) : 'sem limite' }}</td>
                    <td class="num">{{ numero($r->percentual, 2) }}%</td><td class="num">{{ moeda($r->valor_fixo) }}</td><td class="num">{{ moeda($r->valor_minimo) }}</td>
                    <td>{{ $r->carencia_dias ? $r->carencia_dias.' dias' : '—' }}</td>
                    <td>@if ($r->ativo)<span class="selo selo--verde">Ativa</span>@else<span class="selo selo--cinza">Inativa</span>@endif</td>
                    <td class="num"><details class="painel-recolhivel" style="display:inline-block" @if (old('_regra') == $r->id) open @endif><summary class="botao botao--contorno botao--pequeno">Editar</summary>
                        <div style="text-align:left;min-width:min(640px,84vw)">@include('admin._regra-form', ['r' => $r])</div></details></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @empty
        <p class="suave">Nenhuma regra cadastrada.</p>
    @endforelse

    <details class="painel-recolhivel" style="margin-top:16px" @if (old('_regra') === 'nova') open @endif>
        <summary><x-icone nome="mais" /> Nova regra ou faixa</summary>
        @include('admin._regra-form', ['r' => null])
    </details>
</section>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">2. Taxas específicas e isenções vigentes por empresa</h2>
    <p class="suave">Para criar ou encerrar, abra a empresa em <a href="{{ route('admin.empresas') }}">Empresas</a> → "Taxas e isenções".</p>
    @if ($especificas->isEmpty())
        <p class="suave">Nenhuma taxa específica vigente.</p>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Empresa</th><th>Operação</th><th>Condição</th><th>Período</th></tr></thead>
        <tbody>
        @foreach ($especificas as $t)
            <tr><td><a href="{{ route('admin.empresas.show', $t->org_uuid) }}#taxas">{{ $t->nome_fantasia ?: $t->razao_social }}</a></td>
                <td>{{ $t->operacao === 'todas' ? 'Todas' : ($O[$t->operacao] ?? $t->operacao) }}</td>
                <td>@if ($t->isento)<span class="selo selo--verde">Isenta</span>@else{{ $t->percentual !== null ? numero($t->percentual, 2).'%' : 'padrão' }} / {{ $t->valor_fixo !== null ? moeda($t->valor_fixo) : 'padrão' }}@endif</td>
                <td>{{ \Illuminate\Support\Carbon::parse($t->inicio)->format('d/m/Y') }} → {{ $t->fim ? \Illuminate\Support\Carbon::parse($t->fim)->format('d/m/Y') : 'sem fim' }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
@endsection
