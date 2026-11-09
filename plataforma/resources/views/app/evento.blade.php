@extends('layouts.app-checkin')
@section('titulo', $evento->titulo)

@section('corpo')
@include('app._capa', ['voltar' => route('app.index')])
<nav class="ck-abas">
  @can('pedido.ver')<a href="?aba=vendas" @class(['ativa' => $aba === 'vendas'])>Vendas</a>@endcan
  <a href="?aba=checkin" @class(['ativa' => $aba === 'checkin'])>Check-in</a>
  <a href="?aba=ferramentas" @class(['ativa' => $aba === 'ferramentas'])>Ferramentas</a>
</nav>
<main class="ck-conteudo">
  @if ($aba === 'vendas' && $vendas)
    <div class="ck-cartao">
      <div class="ck-cartao__topo">Total em vendas · {{ now()->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') }}</div>
      <div class="ck-cartao__corpo">
        <div class="ck-rotulo">Total vendido</div><div class="ck-numero-grande">{{ moeda($vendas['total']) }}</div>
        <div class="ck-grade2" style="margin-top:14px">
          <div><div class="ck-rotulo">Ingressos vendidos</div><div class="ck-numero-grande">{{ number_format($vendas['ingressos'], 0, ',', '.') }}</div></div>
          <div><div class="ck-rotulo">Vendido hoje</div><div class="ck-numero-grande">{{ moeda($vendas['hoje']) }}</div></div>
        </div>
      </div>
    </div>
    @if ($vendas['por_lote']->isNotEmpty())
      <div class="ck-secao">Por ingresso</div>
      <div class="ck-cartao">
        @foreach ($vendas['por_lote'] as $l)
          <div class="ck-item"><span><b>{{ $l->tipo }}</b><small>{{ $l->lote }}</small></span>
            <span style="text-align:right"><b>{{ $l->qtd }}</b><small>{{ moeda($l->valor) }}</small></span></div>
        @endforeach
      </div>
    @endif
  @elseif ($aba === 'ferramentas')
    <div class="ck-cartao">
      @if (Gate::any(['participante.ver', 'checkin.realizar']))
        <a class="ck-item" href="{{ route('app.participantes', $evento) }}"><span><b>Participantes</b><small>Busque por nome, CPF ou número e faça o check-in</small></span><span>›</span></a>
      @endif
      @can('pedido.emitir_manual')
        <a class="ck-item" href="{{ route('convidados.index', ['evento' => $evento->uuid]) }}"><span><b>Adicionar pedido</b><small>Emita ingressos de cortesia e convidados</small></span><span>›</span></a>
      @endcan
      <a class="ck-item" href="#" data-divulgar="{{ $evento->linkPublico() }}" data-titulo="{{ $evento->titulo }}"><span><b>Divulgue</b><small>Compartilhe o link do evento nas redes</small></span><span>›</span></a>
      @can('cupom.gerenciar')
        <a class="ck-item" href="{{ route('cupons.index', ['evento' => $evento->uuid]) }}"><span><b>Códigos promocionais</b><small>Crie e acompanhe cupons e códigos de divulgação</small></span><span>›</span></a>
      @endcan
      <a class="ck-item" href="{{ route('painel') }}"><span><b>Abrir o painel completo</b><small>Todas as funções do produtor</small></span><span>›</span></a>
    </div>
  @else
    @php $restam = max(0, $numeros['total'] - $numeros['entraram']); @endphp
    <div class="ck-cartao">
      <div class="ck-cartao__topo">Atualizado em <span data-atualizado>{{ now()->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i') }}</span></div>
      <div class="ck-cartao__corpo">
        @if (Gate::any(['participante.ver', 'checkin.realizar']))
          <a href="{{ route('app.participantes', $evento) }}" class="ck-botao" style="max-width:300px;margin:0 auto">Participantes</a>
        @endif
        <div class="ck-medidores">
          @include('app._medidor', ['chave' => 'entraram', 'num' => $numeros['entraram'], 'pct' => $numeros['pct'], 'cor' => '#12B76A', 'rotulo' => 'Realizados'])
          @include('app._medidor', ['chave' => 'restam', 'num' => $restam, 'pct' => $numeros['total'] ? 100 - $numeros['pct'] : 0, 'cor' => '#7A5AF8', 'rotulo' => 'Restantes'])
        </div>
      </div>
      <div class="ck-cartao__rodape" data-situacao>
        @if ($janela['aberta']) <b style="color:var(--verde)">Check-in aberto</b> até {{ $janela['fecha']->copy()->setTimezone('America/Sao_Paulo')->format('d/m \à\s H:i') }}
        @else {{ $janela['motivo'] }} @endif
        @if ($numeros['dentro'] && $evento->permite_reentrada) · {{ $numeros['dentro'] }} dentro agora @endif
      </div>
    </div>
    @if (! $janela['aberta'])
      <div class="ck-aviso">O check-in só funciona no dia do evento, a partir da abertura dos portões ({{ $janela['abre']->copy()->setTimezone('America/Sao_Paulo')->format('d/m \à\s H:i') }}) até o término.</div>
    @endif
  @endif
</main>
@can('checkin.realizar')
  <a href="{{ route('app.ler', $evento) }}" class="ck-flutuante {{ $janela['aberta'] ? '' : 'desativado' }}" aria-label="Ler QR Code">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V3h4M17 3h4v4M21 17v4h-4M7 21H3v-4"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><path d="M13 13h4v4h-4z" stroke-dasharray="2 2"/></svg>
  </a>
@endcan
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-divulgar]').forEach((a) => a.addEventListener('click', async (e) => {
    e.preventDefault();
    const url = a.dataset.divulgar;
    if (navigator.share) { try { await navigator.share({ title: a.dataset.titulo, url }); } catch (er) {} return; }
    try { await navigator.clipboard.writeText(url); alert('Link copiado: ' + url); } catch (er) { prompt('Copie o link:', url); }
  }));
  // atualiza os números a cada 15 s
  (function () {
    if (!document.querySelector('[data-num]')) return;
    const comp = 251.3, url = @json(route('app.numeros', $evento));
    setInterval(async () => {
      try {
        const n = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
        const restam = Math.max(0, n.total - n.entraram), pr = n.total ? 100 - n.pct : 0;
        [['entraram', n.entraram, n.pct], ['restam', restam, pr]].forEach(([k, v, p]) => {
          document.querySelector(`[data-num="${k}"]`).textContent = v.toLocaleString('pt-BR');
          document.querySelector(`[data-pct="${k}"]`).textContent = p + '%';
          const m = document.querySelector(`[data-medidor="${k}"]`); m.setAttribute('stroke-dashoffset', (comp * (1 - p / 100)).toFixed(1)); m.setAttribute('stroke-opacity', p ? 1 : 0);
        });
        document.querySelector('[data-atualizado]').textContent = new Date().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).replace(',', ' às');
      } catch (e) {}
    }, 15000);
  })();
</script>
@endpush
