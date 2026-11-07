@extends('layouts.painel')
@section('titulo', 'Painel')

@section('conteudo')
<div class="cabecalho">
    <div>
        <h1>Olá, {{ auth()->user()->primeiroNome() }}!</h1>
        <p>Resumo de {{ $organizadorAtual->nomeExibicao() }}</p>
    </div>
    @can('evento.criar')
        <div class="cabecalho__acoes"><a href="{{ route('eventos.create') }}" class="botao botao--primario"><x-icone nome="mais" /> Criar evento</a></div>
    @endcan
</div>

<div class="grade grade-4">
    <div class="cartao kpi"><x-icone nome="evento" /><small>Eventos publicados</small><b>{{ numero($totais->publicados) }}</b><small>de {{ numero($totais->eventos) }} cadastrados</small></div>
    <div class="cartao kpi"><x-icone nome="ingresso" /><small>Ingressos vendidos</small><b>{{ numero($totais->vendidos) }}</b><small>de {{ numero($totais->ofertados) }} ofertados</small></div>
    @can('financeiro.ver')
        <div class="cartao kpi"><x-icone nome="dinheiro" /><small>Receita bruta</small><b>{{ moeda($totais->receita) }}</b><small>Comissão: {{ moeda($totais->comissao) }}</small></div>
    @else
        <div class="cartao kpi"><x-icone nome="grafico" /><small>Ocupação</small><b>{{ $totais->ofertados ? numero(100 * $totais->vendidos / $totais->ofertados, 1) : 0 }}%</b><small>dos ingressos ofertados</small></div>
    @endcan
    <div class="cartao kpi"><x-icone nome="checkin" /><small>Check-ins</small><b>{{ numero($totais->checkins) }}</b><small>entradas registradas</small></div>
</div>

<div class="grade grade-lateral" style="margin-top:18px">
    <section class="cartao">
        <div class="cartao__titulo"><h2>Próximos eventos</h2><a href="{{ route('eventos.index') }}">Ver todos</a></div>
        @if ($proximos->isEmpty())
            <div class="vazio">
                <h3>Nenhum evento por vir</h3>
                <p>Crie o primeiro evento para começar a vender.</p>
                @can('evento.criar')<a href="{{ route('eventos.create') }}" class="botao botao--primario">Criar evento</a>@endcan
            </div>
        @else
            <div class="tabela-rolagem">
                <table class="tabela">
                    <thead><tr><th>Evento</th><th>Data</th><th>Status</th><th>Vendas</th></tr></thead>
                    <tbody>
                    @foreach ($proximos as $e)
                        @php $pct = $e->ingressos_ofertados ? 100 * $e->ingressos_vendidos / $e->ingressos_ofertados : 0; @endphp
                        <tr>
                            <td><b>{{ $e->titulo }}</b></td>
                            <td>{{ data_local($e->data_inicio) }}</td>
                            <td><x-status-evento :status="$e->status" /></td>
                            <td style="min-width:160px">
                                <small>{{ numero($e->ingressos_vendidos) }} / {{ numero($e->ingressos_ofertados) }}</small>
                                <div class="barra"><span style="width: {{ min(100, $pct) }}%"></span></div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <aside class="cartao">
        <div class="cartao__titulo"><h2>Primeiros passos</h2></div>
        <div class="cartao__corpo">
            <ul class="checklist">
                @foreach ($passos as $p)
                    <li class="{{ $p['ok'] ? 'feito' : '' }}"><i class="marca"></i>
                        <span>@if ($p['rota'] && ! $p['ok'])<a href="{{ route($p['rota']) }}">{{ $p['texto'] }}</a>@else{{ $p['texto'] }}@endif</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>
</div>
@endsection
