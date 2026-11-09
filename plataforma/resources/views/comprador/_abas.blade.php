@php $comprador = auth('comprador')->user(); @endphp
<section class="pub-cabecalho" style="padding-bottom:40px">
  <div class="container">
    <span class="traco"></span>
    <h1>{{ ['comprador.dados' => 'Meus dados', 'comprador.senha' => 'Alterar senha', 'comprador.email' => 'Alterar meu e-mail'][request()->route()->getName()] ?? 'Meus ingressos' }}<span class="ponto">.</span></h1>
    <p style="margin-bottom:0">Olá, {{ $comprador->nome }}</p>
    <p style="margin-top:2px;font-size:.98rem;opacity:.85">{{ $comprador->email }}</p>
  </div>
</section>
{{-- Abas numa faixa clara, separadas do fundo escuro do cabeçalho --}}
<nav class="pub-abas" aria-label="Minha conta">
  <div class="container">
    <a href="{{ route('comprador.ingressos') }}" @class(['ativa' => request()->routeIs('comprador.ingressos')])>Meus ingressos</a>
    <a href="{{ route('comprador.dados') }}" @class(['ativa' => request()->routeIs('comprador.dados')])>Meus dados</a>
    @if ($comprador->senha_hash)
      <a href="{{ route('comprador.senha') }}" @class(['ativa' => request()->routeIs('comprador.senha')])>Alterar senha</a>
    @endif
    <form method="post" action="{{ route('comprador.sair') }}">@csrf<button type="submit">Sair</button></form>
  </div>
</nav>
