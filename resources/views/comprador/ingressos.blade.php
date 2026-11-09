@extends('layouts.publico')
@section('titulo', 'Meus ingressos')

@section('conteudo')
@include('comprador._abas')

<section class="pub-secao">
  <div class="container">
    @if ($pedidos->isEmpty())
      <div class="pub-vazio">
        <h2>Você ainda não tem ingressos</h2>
        <p>Encontre um evento e garanta o seu.</p>
        <a href="{{ route('vitrine') }}" class="botao botao--amarelo">Ver eventos</a>
      </div>
    @endif

    @php $proximos = $pedidos->filter(fn ($p) => $p->evento && $p->evento->data_fim?->isFuture()); $anteriores = $pedidos->diff($proximos); @endphp
    @foreach ($proximos->concat($anteriores) as $pedido)
      @if ($loop->first && $proximos->isNotEmpty())<h2 style="font-size:1.2rem;margin:0 0 12px">Próximos eventos</h2>@endif
      @if ($anteriores->isNotEmpty() && $pedido->is($anteriores->first()))<h2 style="font-size:1.2rem;margin:24px 0 12px">Eventos que você já foi</h2>@endif
      @php
        $evento = $pedido->evento; $org = $pedido->organizadorPublico;
        $podeCancelar = \App\Http\Controllers\Publico\MeusIngressosController::podeCancelarGratuito($pedido);
        $corStatus = ['pago' => '', 'cancelado' => 'pub-selo--vermelho', 'reembolsado' => 'pub-selo--vermelho'][$pedido->status] ?? 'pub-selo--amarelo';
      @endphp
      <article class="pub-pedido">
        <div class="pub-pedido__topo">
          <div>
            <h2><a href="{{ $evento->linkPublico() }}" style="color:var(--tinta)">{{ $evento->titulo }}</a></h2>
            <small>{{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }} · {{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small>
          </div>
          <div style="text-align:right">
            <span class="pub-selo {{ $corStatus }}">{{ \App\Models\Pedido::STATUS[$pedido->status] ?? $pedido->status }}</span><br>
            <small>Pedido <b>{{ $pedido->codigo }}</b> · {{ data_local($pedido->criado_em, 'd/m/Y') }} · {{ $pedido->ehGratuito() ? 'Gratuito' : moeda($pedido->valor_total) }}</small>
          </div>
        </div>
        @if ($pedido->status === 'aguardando_pagamento')
          <p style="margin:10px 0"><small>Aguardando o pagamento. Os ingressos são emitidos assim que o Mercado Pago confirmar.</small>
            <a href="{{ route('pedido.retorno', $pedido->uuid) }}" class="botao botao--amarelo botao--pequeno">Pagar ou conferir pagamento</a></p>
        @endif
        <div class="pub-pedido__ingressos">
          @foreach ($pedido->ingressos as $ingresso)
            <div>
              <span><b>{{ $ingresso->participante?->nome ?? 'Ingresso' }}</b><br>
                <small>{{ $ingresso->numeroExibicao() }} · {{ $ingresso->tipo->nome }} — {{ $ingresso->lote->nome }}</small></span>
              <span style="display:flex;gap:10px;align-items:center">
                <span class="pub-selo {{ $ingresso->status === 'valido' ? '' : 'pub-selo--vermelho' }}">{{ \App\Models\Ingresso::STATUS[$ingresso->status] }}</span>
                <a href="{{ route('comprador.ingresso', $ingresso->uuid) }}" class="botao botao--borda botao--pequeno">{{ $ingresso->status === 'valido' ? 'Abrir ingresso' : 'Ver' }}</a>
              </span>
            </div>
          @endforeach
        </div>
        <div class="pub-pedido__rodape">
          <small>Dúvidas, alterações ou reembolso: fale com o organizador <b>{{ $org->nomeExibicao() }}</b> — <a href="mailto:{{ $org->email_contato }}?subject={{ rawurlencode('Pedido '.$pedido->codigo.' — '.$evento->titulo) }}">{{ $org->email_contato }}</a></small>
          @if ($podeCancelar)
            <form method="post" action="{{ route('comprador.cancelar', $pedido->uuid) }}" data-confirmar="Cancelar a inscrição do pedido {{ $pedido->codigo }}? Os ingressos deixam de valer e a vaga volta a ficar disponível.">
              @csrf <button class="botao botao--perigo botao--pequeno">Cancelar inscrição</button>
            </form>
          @endif
        </div>
      </article>
    @endforeach
  </div>
</section>
@endsection
