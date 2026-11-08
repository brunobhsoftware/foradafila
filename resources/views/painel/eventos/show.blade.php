@extends('layouts.painel')
@section('titulo', $evento->titulo)

@php
    $transicoes = \App\Models\Evento::TRANSICOES[$evento->status] ?? [];
    $podeLotes = auth()->user()->can('lote.gerenciar') && ! in_array($evento->status, ['encerrado', 'cancelado']);
    $tiposOpcoes = $evento->tipos->pluck('nome', 'id');
    $lotesOpcoes = $evento->lotes()->get()->mapWithKeys(fn ($l) => [$l->id => $l->nome]);
    $vendaFimPadrao = para_input_data($evento->data_fim ?? $evento->data_inicio);
    $somaLotes = $evento->tipos->flatMap->lotes->sum('quantidade');
@endphp

@section('conteudo')
<div class="cabecalho">
    <div>
        <div class="trilha"><a href="{{ route('eventos.index') }}">Eventos</a> › {{ $evento->titulo }}</div>
        <h1>{{ $evento->titulo }} <x-status-evento :status="$evento->status" /></h1>
        <p>{{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }} · {{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</p>
    </div>
    <div class="cabecalho__acoes">
        @if (in_array($evento->status, \App\Http\Controllers\Publico\EventoPublicoController::STATUS_PUBLICOS) && $evento->visibilidade !== 'privado')
            <a href="{{ route('evento.publico', $evento->slug) }}" class="botao botao--contorno" target="_blank" rel="noopener"><x-icone nome="olho" /> Ver página pública</a>
        @endif
        @can('evento.criar')
            @unless (in_array($evento->status, ['encerrado', 'cancelado']))
                <a href="{{ route('eventos.edit', $evento) }}" class="botao botao--contorno"><x-icone nome="editar" /> Editar</a>
            @endunless
        @endcan
        @foreach (['publicado' => $evento->status === 'pausado' ? 'Retomar vendas' : 'Publicar evento', 'pausado' => 'Pausar vendas', 'encerrado' => 'Encerrar'] as $novo => $rotulo)
            @if (isset($transicoes[$novo]) && auth()->user()->can($transicoes[$novo]))
                <form method="post" action="{{ route('eventos.status', $evento) }}" @if ($novo === 'encerrado') data-confirmar="Encerrar o evento? Depois disso ele não pode mais ser editado." @endif>
                    @csrf <input type="hidden" name="status" value="{{ $novo }}">
                    <button class="botao {{ $novo === 'publicado' ? 'botao--primario' : 'botao--contorno' }}"
                        @if ($novo === 'publicado' && $evento->status === 'rascunho' && ! $evento->podePublicar()) disabled title="Resolva os itens da lista abaixo" @endif>{{ $rotulo }}</button>
                </form>
            @endif
        @endforeach
    </div>
</div>

@if ($checklist)
    <div class="cartao" style="margin-bottom:18px">
        <div class="cartao__titulo"><h2>Para publicar</h2><small>Regras verificadas também pelo banco de dados</small></div>
        <div class="cartao__corpo">
            <ul class="checklist">
                @foreach ($checklist as $item)
                    <li class="{{ $item['ok'] ? 'feito' : '' }}"><i class="marca"></i><span>{{ $item['texto'] }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if ($evento->imagem_capa_url)
    <img class="capa-evento" src="{{ $evento->imagem_capa_url }}" alt="Imagem de divulgação" width="1600" height="838" style="max-height:280px;margin-bottom:18px">
@endif

<div class="grade grade-4">
    <div class="cartao kpi"><small>Ingressos vendidos</small><b>{{ numero($indicadores->ingressos_vendidos) }}</b><small>{{ numero($indicadores->ingressos_reservados) }} reservados agora</small></div>
    <div class="cartao kpi"><small>Ofertados nos lotes</small><b>{{ numero($indicadores->ingressos_ofertados) }}</b><small>capacidade {{ $evento->capacidadeTexto() }}</small></div>
    @can('financeiro.ver')
        <div class="cartao kpi"><small>Receita bruta</small><b>{{ moeda($indicadores->receita_bruta) }}</b><small>{{ numero($indicadores->pedidos_pagos) }} pedidos pagos</small></div>
    @else
        <div class="cartao kpi"><small>Pedidos pagos</small><b>{{ numero($indicadores->pedidos_pagos) }}</b><small>&nbsp;</small></div>
    @endcan
    <div class="cartao kpi"><small>Check-ins</small><b>{{ numero($indicadores->checkins_realizados) }}</b><small>entradas</small></div>
</div>

<section class="cartao" style="margin-top:18px">
    <div class="cartao__titulo">
        <div><h2>Ingressos e lotes</h2><small>Setores (Pista, VIP…) e os lotes de cada um, com preço, quantidade e período de venda.</small></div>
        <small>{{ numero($somaLotes) }} {{ $evento->capacidade === null ? 'ingressos em lotes · sem capacidade máxima' : 'de '.numero($evento->capacidade).' lugares em lotes' }}</small>
    </div>

    @forelse ($evento->tipos as $tipo)
        <div class="secao-form">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                <div><h3>{{ $tipo->nome }} @unless ($tipo->ativo)<span class="selo">inativo</span>@endunless</h3>
                    @if ($tipo->descricao)<small>{{ $tipo->descricao }}</small>@endif</div>
                @if ($podeLotes)
                    <div style="display:flex;gap:8px">
                        <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="editar-tipo-{{ $tipo->id }}" aria-expanded="false">Editar tipo</button>
                        @if ($tipo->lotes->isEmpty())
                            <form method="post" action="{{ route('tipos.destroy', $tipo) }}" data-confirmar="Excluir o tipo “{{ $tipo->nome }}”?">
                                @csrf @method('DELETE') <button class="botao botao--perigo botao--pequeno">Excluir</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            @if ($podeLotes)
                <div class="bloco-edicao" id="editar-tipo-{{ $tipo->id }}" hidden>
                    <p class="bloco-edicao__titulo">Editar tipo “{{ $tipo->nome }}”</p>
                    <form method="post" action="{{ route('tipos.update', $tipo) }}" class="form-grade">
                        @csrf @method('PUT')
                        <x-campo nome="nome" rotulo="Nome" :valor="$tipo->nome" obrigatorio classe="col-6" />
                        <x-campo nome="capacidade" rotulo="Capacidade do setor" tipo="number" :valor="$tipo->capacidade" classe="col-6" />
                        <x-campo nome="descricao" rotulo="Descrição" :valor="$tipo->descricao" />
                        <input type="hidden" name="ativo" value="0">
                        <label class="opcao col-12"><input type="checkbox" name="ativo" value="1" @checked($tipo->ativo)> Ativo</label>
                        <div class="col-12 acoes-linha" style="justify-content:flex-start">
                            <button class="botao botao--escuro botao--pequeno">Salvar tipo</button>
                            <button type="button" class="botao botao--link" data-alternar="editar-tipo-{{ $tipo->id }}">Fechar</button></div>
                    </form>
                </div>
            @endif

            @if ($tipo->lotes->isNotEmpty())
                <div class="tabela-rolagem" style="margin-top:12px">
                    <table class="tabela">
                        <thead><tr><th>Lote</th><th>Modalidade</th><th class="num">Preço</th><th>Vendas</th><th>Período</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($tipo->lotes as $lote)
                            @php $pct = $lote->quantidade ? 100 * $lote->vendido / $lote->quantidade : 0; @endphp
                            <tr>
                                <td><b>{{ $lote->nome }}</b>@if ($lote->visibilidade !== 'publico')<br><small>{{ \App\Models\Lote::VISIBILIDADES[$lote->visibilidade] }}</small>@endif</td>
                                <td>{{ \App\Models\Lote::MODALIDADES[$lote->modalidade] }}</td>
                                <td class="num">{{ $lote->preco > 0 ? moeda($lote->preco) : 'Grátis' }}</td>
                                <td style="min-width:140px"><small>{{ numero($lote->vendido) }} / {{ numero($lote->quantidade) }}</small><div class="barra"><span style="width:{{ min(100, $pct) }}%"></span></div></td>
                                <td><small>@if ($lote->lote_anterior_id)Após “{{ $lotesOpcoes[$lote->lote_anterior_id] ?? 'lote anterior' }}”@else{{ data_local($lote->venda_inicio, 'd/m H:i') }}@endif → {{ data_local($lote->venda_fim, 'd/m H:i') }}</small></td>
                                <td><span class="selo {{ ['ativo' => 'selo--verde', 'esgotado' => 'selo--laranja', 'pausado' => 'selo--cinza', 'encerrado' => 'selo--azul'][$lote->status] ?? '' }}">{{ \App\Models\Lote::STATUS[$lote->status] }}</span></td>
                                <td class="num">
                                    @if ($podeLotes)
                                        <form method="post" action="{{ route('lotes.alternar', $lote) }}" style="display:inline">@csrf
                                            <button class="botao botao--contorno botao--pequeno" title="{{ $lote->status === 'pausado' ? 'Volta a aparecer e a ser vendido' : 'Deixa de aparecer e de ser vendido' }}">{{ $lote->status === 'pausado' ? 'Ativar' : 'Desativar' }}</button>
                                        </form>
                                        <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="editar-lote-{{ $lote->id }}" aria-expanded="false">Editar</button>
                                    @endif
                                </td>
                            </tr>
                            @if ($podeLotes)
                            <tr class="linha-acoes" id="editar-lote-{{ $lote->id }}" @unless (old('_form') === 'lote-'.$lote->id) hidden @endunless>
                                <td colspan="7">
                                    <p class="bloco-edicao__titulo">Editar lote “{{ $lote->nome }}”</p>
                                    @include('painel.eventos.lote-form', ['lote' => $lote, 'acao' => route('lotes.update', $lote), 'metodo' => 'PUT'])
                                    <div class="acoes-linha" style="justify-content:flex-start;margin-top:10px">
                                        @unless ($lote->temMovimento())
                                            <form method="post" action="{{ route('lotes.destroy', $lote) }}" data-confirmar="Excluir o lote “{{ $lote->nome }}”?">
                                                @csrf @method('DELETE') <button class="botao botao--perigo botao--pequeno">Excluir lote</button>
                                            </form>
                                        @endunless
                                        <button type="button" class="botao botao--link" data-alternar="editar-lote-{{ $lote->id }}">Fechar</button>
                                    </div>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($podeLotes)
                <details class="painel-recolhivel" style="margin-top:12px" @if (old('_form') === 'lote-'.$tipo->id) open @endif>
                    <summary><x-icone nome="mais" /> Adicionar lote em {{ $tipo->nome }}</summary>
                    @include('painel.eventos.lote-form', ['lote' => new \App\Models\Lote(['ingresso_tipo_id' => $tipo->id, 'nome' => ($tipo->lotes->count() + 1).'º lote', 'modalidade' => 'inteira', 'status' => 'ativo', 'min_por_pedido' => 1, 'max_por_pedido' => $evento->max_ingressos_pedido, 'virada' => 'esgotar_ou_data', 'visibilidade' => 'publico', 'lote_anterior_id' => $tipo->lotes->last()?->id]), 'acao' => route('lotes.store', $evento), 'metodo' => 'POST', 'formId' => 'lote-'.$tipo->id])
                </details>
            @endif
        </div>
    @empty
        <div class="vazio">
            <h3>Nenhum tipo de ingresso ainda</h3>
            <p>Crie pelo menos um tipo (ex.: Pista, VIP, Ingresso único) e depois os lotes dele.</p>
        </div>
    @endforelse

    @if ($podeLotes)
        <div class="secao-form" style="background:#FCFCFD;border-radius:0 0 var(--raio) var(--raio)">
            <h3 style="margin-bottom:12px">Novo tipo de ingresso</h3>
            <form method="post" action="{{ route('tipos.store', $evento) }}" class="form-grade">
                @csrf
                <x-campo nome="nome" rotulo="Nome do tipo" placeholder="Ex.: Pista, VIP, Camarote" obrigatorio classe="col-4" :valor="$evento->tipos->isEmpty() ? 'Ingresso' : ''" />
                <x-campo nome="descricao" rotulo="Descrição (opcional)" classe="col-4" placeholder="O que este ingresso inclui" />
                <x-campo nome="capacidade" rotulo="Limite do setor (opcional)" tipo="number" min="1" classe="col-2" />
                <div class="campo col-2" style="align-self:end"><button class="botao botao--escuro" style="width:100%">Criar tipo</button></div>
            </form>
        </div>
    @endif
</section>

<div class="grade grade-2" style="margin-top:18px">
    <section class="cartao">
        <div class="cartao__titulo"><h2>Detalhes</h2></div>
        <div class="cartao__corpo">
            <p><b>Visibilidade:</b> {{ \App\Models\Evento::VISIBILIDADES[$evento->visibilidade] }}</p>
            <p><b>Classificação:</b> {{ \App\Models\Evento::CLASSIFICACOES[$evento->classificacao] }} · <b>Categoria:</b> {{ $evento->categoria?->nome ?? '—' }}</p>
            <p><b>Término:</b> {{ data_local($evento->data_fim) }} · <b>Portões:</b> {{ data_local($evento->abertura_portoes) }}</p>
            <p><b>Por pedido:</b> até {{ $evento->max_ingressos_pedido }} ingressos{{ $evento->max_ingressos_cpf ? ' · até '.$evento->max_ingressos_cpf.' por CPF' : '' }}</p>
            <p><b>Ingresso:</b> {{ $evento->nominal ? 'nominal' : 'ao portador' }}{{ $evento->exige_cpf_participante ? ', com CPF' : '' }}{{ $evento->permite_transferencia ? ', transferível até '.$evento->transferencia_ate_h.' h antes' : '' }}{{ $evento->permite_reentrada ? ', com reentrada' : '' }}</p>
            <p style="margin:0"><b>Endereço da página:</b> {{ route('evento.publico', $evento->slug) }}@if ($evento->status === 'rascunho') <small>(no ar depois de publicar)</small>@endif</p>
        </div>
    </section>
    @if (isset($transicoes['cancelado']) && auth()->user()->can('evento.cancelar'))
        <section class="cartao">
            <div class="cartao__titulo"><h2>Cancelar evento</h2></div>
            <form method="post" action="{{ route('eventos.status', $evento) }}" class="cartao__corpo" data-confirmar="Cancelar o evento? Essa ação não pode ser desfeita.">
                @csrf <input type="hidden" name="status" value="cancelado">
                <p class="suave">Os compradores serão avisados e os reembolsos ficam sob responsabilidade do organizador.</p>
                <x-campo nome="cancelamento_motivo" rotulo="Motivo do cancelamento" tipo="textarea" rows="2" obrigatorio />
                <button class="botao botao--perigo" style="margin-top:12px">Cancelar evento</button>
            </form>
        </section>
    @elseif ($evento->status === 'cancelado')
        <section class="cartao"><div class="cartao__corpo"><h2>Evento cancelado</h2><p>{{ data_local($evento->cancelado_em) }} — {{ $evento->cancelamento_motivo }}</p></div></section>
    @endif
</div>
@endsection
