<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#101828">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Check-in">
  <title>@yield('titulo', 'Check-in') · Fora da Fila</title>
  <link rel="manifest" href="{{ asset('app.webmanifest') }}">
  <link rel="apple-touch-icon" href="{{ asset('app-icons/apple-touch-icon.png') }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/checkin.css') }}?v={{ filemtime(public_path('css/checkin.css')) }}">
</head>
<body class="@yield('classe')">
  @yield('corpo')
  <script>
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/app-sw.js', { scope: '/app' }).catch(() => {});
  </script>
  @stack('scripts')
</body>
</html>
