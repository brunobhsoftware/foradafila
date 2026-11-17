@extends('layouts.painel')
@section('titulo', 'Eventos')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Meus eventos</h1><p>Os eventos da organização que você pode acessar.</p></div>
    @can('evento.criar')
        <div class="cabecalho__acoes"><a href="{{ route('eventos.create') }}" class="botao botao--primario"><x-icone nome="mais" /> Novo evento</a></div>
    @endcan
</div>

<div class="cartao">
    <form method="get" class="cartao__corpo form-grade" style="padding-bottom:14px">
        <x-campo nome="busca" rotulo="Buscar" :valor="request('busca')" placeholder="Nome do evento" classe="col-6" />
        <x-campo nome="status" rotulo="Status" tipo="select" :opcoes="\App\Models\Evento::STATUS" :valor="request('status')" vazio="Todos" classe="col-4" />
        <div class="campo col-2" style="align-self:end"><button class="botao botao--contorno" style="width:100%">Filtrar</button></div>
    </form>

    @if ($eventos->isEmpty())
        <div class="vazio">
            <h3>{{ request()->hasAny(['busca', 'status']) ? 'Nenhum evento encontrado' : 'Nenhum evento ainda' }}</h3>
            <p>Comece pelo cadastro do evento; depois você cria os tipos de ingresso e os lotes.</p>
            @can('evento.criar')<a href="{{ route('eventos.create') }}" class="botao botao--primario">Criar evento</a>@endcan
        </div>
    @else
        <div class="lista-eventos">
        @foreach ($eventos as $evento)
            @php
                $link = $evento->linkPublico();
                $noAr = in_array($evento->status, ['publicado', 'pausado'], true);
                $v = $valores[$evento->id];
            @endphp
            <article class="evento-painel">
                <div class="evento-painel__topo">
                    <div>
                        <a href="{{ route('eventos.show', $evento) }}" class="evento-painel__titulo">{{ $evento->titulo }}</a>
                        <small class="suave">{{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small><br>
                        <small><b>{{ data_local($evento->data_inicio, 'd/m/Y H:i') }}</b> até {{ data_local($evento->data_fim, $evento->data_fim && data_local($evento->data_fim, 'd/m/Y') === data_local($evento->data_inicio, 'd/m/Y') ? 'H:i' : 'd/m/Y H:i') }}</small>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <x-status-evento :status="$evento->status" />
                        <a href="{{ route('eventos.show', $evento) }}" class="botao botao--contorno botao--pequeno">Abrir</a>
                        <a href="{{ route('eventos.show', $evento) }}#ingressos" class="botao botao--contorno botao--pequeno">Ingressos</a>
                        @can('evento.criar')@unless (in_array($evento->status, ['encerrado', 'cancelado']))<a href="{{ route('eventos.edit', $evento) }}" class="botao botao--primario botao--pequeno">Editar</a>@endunless
                        <form method="post" action="{{ route('eventos.duplicar', $evento) }}" style="display:inline" data-confirmar="Duplicar o evento “{{ $evento->titulo }}”? A cópia é criada como rascunho, com os mesmos dados, ingressos e lotes (sem vendas).">@csrf<button class="botao botao--contorno botao--pequeno">Duplicar</button></form>@endcan
                    </div>
                </div>
                <div class="evento-painel__numeros">
                    <div><small>Ingressos vendidos</small><b>{{ numero($v->ingressos) }}</b><small>de {{ numero($v->ofertados) }} ofertados</small></div>
                    <div><small>Total vendido</small><b>{{ moeda($v->total) }}</b><small>{{ numero($v->pedidos) }} {{ $v->pedidos == 1 ? 'pedido' : 'pedidos' }}</small></div>
                    <div><small>Descontos (cupons)</small><b>{{ moeda($v->descontos) }}</b><small>{{ numero($v->cupons) }} {{ $v->cupons == 1 ? 'cupom' : 'cupons' }}</small></div>
                    <div><small>Taxa da plataforma</small><b>{{ moeda($v->taxa) }}</b><small>comissão de serviço</small></div>
                    <div><small>Devolvido</small><b>{{ moeda($v->devolvido) }}</b><small>{{ numero($v->cancelados) }} {{ $v->cancelados == 1 ? 'cancelamento' : 'cancelamentos' }}</small></div>
                    <div class="destaque"><small>Líquido a receber</small><b>{{ moeda($v->liquido) }}</b><small>total − taxa − devolvido</small></div>
                </div>
                <div class="evento-painel__link">
                    <div class="copiar"><input id="link-ev-{{ $evento->id }}" value="{{ $link }}" readonly aria-label="Link do evento">
                        <button type="button" class="botao botao--contorno botao--pequeno" data-copiar="link-ev-{{ $evento->id }}">Copiar</button>
                        <button type="button" class="botao botao--escuro botao--pequeno" data-compartilhar="{{ $link }}" data-titulo="{{ $evento->titulo }}">Compartilhar</button>
                        <a class="botao botao--contorno botao--pequeno" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($evento->titulo.' — ingressos: '.$link) }}">WhatsApp</a></div>
                    @unless ($noAr)<small class="suave">O link fica no ar depois de publicar o evento.</small>@endunless
                </div>
            </article>
        @endforeach
        </div>
        {{ $eventos->links() }}
    @endif
</div>
@endsection
