{{--
  Menu superior único do site público. Liga as três áreas:
  site institucional, área do produtor (guard web) e área do comprador (guard comprador).
  Cada área continua protegida pelo próprio login; o menu só mostra os atalhos de quem está logado.
--}}
@php
    $produtor = auth('web')->user();
    $comprador = auth('comprador')->user();
    $itens = [
        ['rota' => 'solucao', 'texto' => 'Solução'],
        ['rota' => 'precos', 'texto' => 'Preços'],
        ['rota' => 'vitrine', 'texto' => 'Eventos', 'ativo' => ['vitrine', 'evento.publico']],
        ['rota' => 'ajuda', 'texto' => 'Ajuda'],
    ];
@endphp
<header class="topo">
  <div class="container topo__linha">
    <a href="{{ route('inicio') }}" class="marca" aria-label="fora da fila — início">
      <img src="{{ asset('assets/img/simbolo.svg') }}" alt="" class="marca__simbolo" width="70" height="41">
      <span class="marca__nome">fora da fila<span class="ponto">.</span></span>
    </a>
    <button class="menu-botao" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu">
      <span></span><span></span><span></span>
    </button>
    <nav class="menu" id="menu" aria-label="Principal">
      @foreach ($itens as $item)
        @php $ativo = request()->routeIs(...($item['ativo'] ?? [$item['rota']])); @endphp
        <a href="{{ route($item['rota']) }}" @class(['ativo' => $ativo]) @if ($ativo) aria-current="page" @endif>{{ $item['texto'] }}</a>
      @endforeach

      <span class="menu__divisor" aria-hidden="true"></span>

      @if ($comprador)
        <a href="{{ route('comprador.ingressos') }}" @class(['ativo' => request()->routeIs('comprador.ingressos')])>Meus ingressos</a>
        <a href="{{ route('comprador.dados') }}" class="so-celular-pub">Meus dados</a>
      @endif
      @if ($produtor)
        <a href="{{ route('painel') }}" class="so-celular-pub">Painel do produtor</a>
        <a href="{{ route('eventos.index') }}">Meus eventos</a>
        <a href="{{ route('app.index') }}" class="so-celular-pub">App de check-in</a>
        <a href="{{ route('eventos.create') }}" class="botao botao--amarelo botao--pequeno">+ Novo evento</a>
      @else
        @unless ($comprador)
          <a href="{{ route('comprador.ingressos') }}">Meus ingressos</a>
        @endunless
        <a href="{{ route('entrar') }}">Área do produtor</a>
        <a href="{{ route('cadastro') }}" class="botao botao--amarelo botao--pequeno">Criar evento</a>
      @endif
      @if ($produtor || $comprador)
        <form method="post" action="{{ route($produtor ? 'sair' : 'comprador.sair') }}" class="so-celular-pub menu__sair">@csrf<button type="submit">Sair</button></form>
      @endif
    </nav>
  </div>
</header>
