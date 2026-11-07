@extends('layouts.painel')
@section('titulo', 'Participantes')

@php $S = \App\Models\Ingresso::STATUS; @endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>Participantes</h1><p>Quem tem ingresso em cada evento: reenvie o ingresso, cancele e exporte a lista.</p></div>
    @if ($evento)
        @can('participante.exportar')
            <div class="cabecalho__acoes"><a href="{{ route('participantes.exportar', array_filter(['evento' => $evento->uuid] + $f)) }}" class="botao botao--contorno">Exportar (CSV)</a></div>
        @endcan
    @endif
</div>

@error('reenvio')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror
@error('cancelamento')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

@if ($eventos->isEmpty())
    <div class="cartao vazio"><h3>Nenhum evento ainda</h3><p>Crie um evento em Meus eventos.</p></div>
@else
    <form method="get" action="{{ route('participantes.index') }}" class="filtros cartao" style="padding:14px 16px">
        <div class="campo" style="flex:1 1 280px"><label>Evento</label>
            <select name="evento" onchange="this.form.submit()">
                @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($evento && $evento->id === $e->id)>{{ $e->titulo }} — {{ data_local($e->data_inicio, 'd/m/Y') }}</option>@endforeach
            </select></div>
        <div class="campo" style="flex:1 1 220px"><label>Buscar</label><input name="busca" value="{{ $f['busca'] ?? '' }}" placeholder="Nome, e-mail, nº do ingresso ou pedido"></div>
        <div class="campo"><label>Situação</label><select name="status"><option value="">Todas</option>@foreach ($S as $k => $t)<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $t }}</option>@endforeach</select></div>
        <div class="campo"><label>Lote</label><select name="lote"><option value="">Todos</option>@foreach ($lotes as $id => $nome)<option value="{{ $id }}" @selected(($f['lote'] ?? '') == $id)>{{ $nome }}</option>@endforeach</select></div>
        <button class="botao botao--escuro botao--pequeno">Filtrar</button>
    </form>

    <div class="grade grade-4" style="margin-top:14px">
        <div class="cartao kpi"><x-icone nome="ingresso" /><small>Válidos</small><b>{{ numero($resumo['valido'] ?? 0) }}</b><small>aguardando entrada</small></div>
        <div class="cartao kpi"><x-icone nome="checkin" /><small>Com check-in</small><b>{{ numero($resumo['utilizado'] ?? 0) }}</b><small>já entraram</small></div>
        <div class="cartao kpi"><x-icone nome="participantes" /><small>Total emitido</small><b>{{ numero($resumo->sum()) }}</b><small>ingressos</small></div>
        <div class="cartao kpi"><x-icone nome="dinheiro" /><small>Cancelados / devolvidos</small><b>{{ numero(($resumo['cancelado'] ?? 0) + ($resumo['reembolsado'] ?? 0)) }}</b><small>ingressos</small></div>
    </div>

    <section class="cartao" style="margin-top:14px">
        @if ($ingressos->isEmpty())
            <div class="vazio"><h3>Nenhum participante encontrado</h3><p>Quando alguém se inscrever ou comprar, aparece aqui.</p></div>
        @else
        <div class="tabela-rolagem"><table class="tabela">
            <thead><tr><th>Ingresso</th><th>Participante</th><th>Tipo / lote</th><th class="num">Valor pago</th><th>Pedido</th><th>Situação</th><th class="num">Ações</th></tr></thead>
            <tbody>
            @foreach ($ingressos as $i)
                <tr>
                    <td style="white-space:nowrap"><b>{{ $i->numeroExibicao() }}</b></td>
                    <td>{{ $i->participante?->nome ?? '—' }}<br><small>{{ $i->participante?->email ?? "comprador: ".$i->pedido?->comprador?->email }}</small></td>
                    <td>{{ $i->tipo?->nome }}<br><small>{{ $i->lote?->nome }}</small></td>
                    <td class="num">{{ (float) $i->valor_pago > 0 ? moeda($i->valor_pago) : 'Grátis' }}</td>
                    <td>@can('pedido.ver')<a href="{{ route('pedidos.show', $i->pedido) }}">{{ $i->pedido?->codigo }}</a>@else{{ $i->pedido?->codigo }}@endcan<br><small>{{ $i->pedido?->comprador?->nome }}</small></td>
                    <td><span class="selo {{ ['valido' => 'selo--verde', 'utilizado' => 'selo--azul', 'cancelado' => 'selo--cinza', 'reembolsado' => 'selo--laranja'][$i->status] ?? '' }}">{{ $S[$i->status] ?? $i->status }}</span>
                        @if ($i->primeiro_checkin_em)<br><small>{{ data_local($i->primeiro_checkin_em, 'd/m H:i') }}</small>@endif</td>
                    <td class="num">
                        @if (in_array($i->status, ['valido', 'utilizado']))
                            <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="acoes-{{ $i->uuid }}" aria-expanded="false">Ações</button>
                        @endif
                    </td>
                </tr>
                @if (in_array($i->status, ['valido', 'utilizado']))
                <tr class="linha-acoes" id="acoes-{{ $i->uuid }}" @unless (old('_ingresso') === $i->uuid) hidden @endunless>
                    <td colspan="7">
                        <div class="linha-acoes__grade">
                            <div>
                                <p class="linha-acoes__titulo">Reenviar ingresso por e-mail</p>
                                <div class="acoes-linha" style="justify-content:flex-start">
                                    <form method="post" action="{{ route('ingressos.reenviar', $i) }}">@csrf<input type="hidden" name="destino" value="participante">
                                        <button class="botao botao--escuro botao--pequeno" @disabled(! $i->participante?->email)>Para o participante</button></form>
                                    <form method="post" action="{{ route('ingressos.reenviar', $i) }}">@csrf<input type="hidden" name="destino" value="comprador">
                                        <button class="botao botao--contorno botao--pequeno">Para o comprador</button></form>
                                </div>
                                <small class="suave">Participante: {{ $i->participante?->email ?? 'sem e-mail' }} · Comprador: {{ $i->pedido?->comprador?->email }}</small>
                            </div>
                            @can('pedido.reembolsar')
                                <div>
                                    <p class="linha-acoes__titulo">Cancelar este ingresso</p>
                                    @if ($i->status === 'valido')
                                        @include('painel._cancelar-form', ['acao' => route('ingressos.cancelar', $i), 'valor' => (float) $i->valor_pago, 'rotulo' => 'Cancelar ingresso'])
                                    @else
                                        <p class="suave"><small>Ingresso já usado no check-in: não pode ser cancelado.</small></p>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    </td>
                </tr>
                @endif
            @endforeach
            </tbody>
        </table></div>
        @endif
    </section>
    @if ($ingressos instanceof \Illuminate\Pagination\LengthAwarePaginator)<x-paginacao :paginator="$ingressos" />@endif
@endif
@endsection
