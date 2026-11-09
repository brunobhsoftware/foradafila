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
          <a href="{{ $evento->linkPublico() }}" class="pub-pedido__capa" aria-label="Página do evento {{ $evento->titulo }}">
            @if ($evento->imagem_capa_url)<img src="{{ $evento->imagem_capa_url }}" alt="" loading="lazy">@else<span>{{ mb_substr($evento->titulo, 0, 1) }}</span>@endif
          </a>
          <div style="flex:1;min-width:200px">
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
                @if ($ingresso->status === 'valido')
                  <form method="post" action="{{ route('comprador.ingresso.enviar', $ingresso->uuid) }}" style="margin:0">@csrf
                    <button class="botao botao--contorno botao--pequeno">Enviar para meu e-mail</button></form>
                  @php $regra = \App\Http\Controllers\Publico\MeusIngressosController::regraCancelamento($ingresso, $pedido); @endphp
                  @if ($regra['pode'])
                    <button type="button" class="botao botao--perigo botao--pequeno" data-alternar="canc-{{ $ingresso->uuid }}" aria-expanded="false">Cancelar ingresso</button>
                  @endif
                @endif
              </span>
            </div>
            @if ($ingresso->status === 'valido' && ($regra['pode'] ?? false))
              <div id="canc-{{ $ingresso->uuid }}" class="pub-cancelar" hidden>
                <b>Cancelar o ingresso {{ $ingresso->numeroExibicao() }}</b>
                <p>Compra em {{ data_local($regra['compra'], 'd/m/Y \à\s H:i') }} ({{ $regra['dias'] === 0 ? 'hoje' : $regra['dias'].' dia(s) atrás' }}).
                  @if ((float) $ingresso->valor_pago <= 0)
                    Ingresso gratuito: a vaga volta a ficar disponível.
                  @elseif ($regra['dentro'])
                    Dentro de {{ \App\Http\Controllers\Publico\MeusIngressosController::DIAS_ARREPENDIMENTO }} dias da compra: devolvemos <b>tudo, {{ moeda($regra['valor']) }}</b> (ingresso e taxa).
                  @else
                    Mais de {{ \App\Http\Controllers\Publico\MeusIngressosController::DIAS_ARREPENDIMENTO }} dias da compra: devolvemos <b>{{ moeda($regra['valor']) }}</b>, o valor do ingresso{{ $regra['taxa_retida'] > 0 ? '; a taxa de serviço de '.moeda($regra['taxa_retida']).' não é devolvida' : '' }}.
                  @endif</p>
                <p><small>O ingresso é cancelado na hora e deixa de valer para a entrada. @if ((float) $ingresso->valor_pago > 0)A devolução sai pelo mesmo meio de pagamento (no cartão, aparece na fatura conforme o banco). @endif Enviamos a confirmação por e-mail.</small></p>
                <form method="post" action="{{ route('comprador.ingresso.cancelar', $ingresso->uuid) }}" data-confirmar="Confirmar o cancelamento do ingresso {{ $ingresso->numeroExibicao() }}? Esta ação não pode ser desfeita.">@csrf
                  <button class="botao botao--perigo botao--pequeno">Confirmar cancelamento</button></form>
              </div>
            @endif
          @endforeach
        </div>
        <div class="pub-pedido__rodape">
          @if (in_array($pedido->status, ['pago', 'confirmado', 'reembolso_parcial'], true) && $pedido->ingressos->whereIn('status', ['valido', 'utilizado'])->isNotEmpty())
            <form method="post" action="{{ route('comprador.reenviar', $pedido->uuid) }}">@csrf
              <button class="botao botao--contorno botao--pequeno">Enviar para meu e-mail</button></form>
          @endif
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
