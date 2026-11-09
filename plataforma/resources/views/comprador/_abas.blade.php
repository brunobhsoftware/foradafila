@php $comprador = auth('comprador')->user(); @endphp
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>{{ request()->routeIs('comprador.dados') ? 'Meus dados' : 'Meus ingressos' }}<span class="ponto">.</span></h1>
    <p>Olá, {{ $comprador->primeiroNome() }} ({{ $comprador->email }}).</p>
    <p style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
      <a href="{{ route('comprador.ingressos') }}" class="botao botao--pequeno {{ request()->routeIs('comprador.ingressos') ? 'botao--escuro' : 'botao--contorno' }}">Meus ingressos</a>
      <a href="{{ route('comprador.dados') }}" class="botao botao--pequeno {{ request()->routeIs('comprador.dados') ? 'botao--escuro' : 'botao--contorno' }}">Meus dados</a>
      <form method="post" action="{{ route('comprador.sair') }}" style="display:inline">@csrf<button class="botao botao--contorno botao--pequeno">Sair</button></form>
    </p>
  </div>
</section>
