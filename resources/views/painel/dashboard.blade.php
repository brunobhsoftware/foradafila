@extends('layouts.painel')
@section('titulo', 'Painel')

@section('conteudo')
<div class="cabecalho">
    <div>
        <h1>Olá, {{ auth()->user()->primeiroNome() }}!</h1>
        <p>Resumo de {{ $organizadorAtual->nomeExibicao() }}</p>
    </div>
    @can('evento.criar')
        <div class="cabecalho__acoes"><a href="{{ route('eventos.create') }}" class="botao botao--primario"><x-icone nome="mais" /> Novo evento</a></div>
    @endcan
</div>

@php $org = $organizadorAtual; @endphp
<section class="cartao atalhos-org" aria-label="Sua organização">
    <div class="atalhos-org__id">
        @if ($org->logo_url)
            <img src="{{ $org->logo_url }}" alt="" class="conta-menu__logo" width="56" height="56">
        @else
            <span class="conta-menu__logo conta-menu__logo--iniciais" aria-hidden="true">{{ collect(explode(' ', $org->nomeExibicao()))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</span>
        @endif
        <div style="min-width:0">
            <b>{{ $org->nomeExibicao() }}</b>
            <small>{{ $org->email_contato }}</small>
        </div>
    </div>
    <div class="atalhos-org__botoes">
        <a href="{{ route('organizador.publico', $org->slug) }}" target="_blank" rel="noopener" class="botao botao--contorno botao--pequeno"><x-icone nome="olho" /> Minha página de eventos</a>
        <a href="{{ route('preferencias.edit') }}" class="botao botao--contorno botao--pequeno"><x-icone nome="perfil" /> Minhas preferências</a>
        <a href="{{ route('conta.senha') }}" class="botao botao--contorno botao--pequeno"><x-icone nome="editar" /> Trocar senha</a>
        <a href="{{ route('inicio') }}" class="botao botao--contorno botao--pequeno"><x-icone nome="painel" /> Ir para o site</a>
        <form method="post" action="{{ route('sair') }}">@csrf<button type="submit" class="botao botao--perigo botao--pequeno"><x-icone nome="sair" /> Sair</button></form>
    </div>
</section>

<div class="grade grade-4" style="margin-top:18px">
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
