<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@hasSection('titulo')@yield('titulo') · fora da fila @else Fora da Fila — Plataforma de ingressos para eventos @endif</title>
  <meta name="description" content="@yield('descricao', 'Venda ingressos para o seu evento sem complicação. O produtor escolhe quem paga a taxa.')">
  <meta name="theme-color" content="#101828">
  @stack('meta')
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}?v={{ filemtime(public_path('assets/css/site.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets/css/publico.css') }}?v={{ filemtime(public_path('assets/css/publico.css')) }}">
</head>
<body class="@yield('classe_body')">

@include('site._topo')

<main id="conteudo">
  @hasSection('sem_alertas')
  @else
    <div class="container pub-alertas">@include('layouts.alertas')</div>
  @endif
  @yield('conteudo')
</main>

@include('site._rodape')

<script>
  document.querySelector('.menu-botao')?.addEventListener('click', (e) => {
    const aberto = document.body.classList.toggle('menu-aberto');
    e.currentTarget.setAttribute('aria-expanded', aberto);
  });
</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
