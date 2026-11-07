@extends('layouts.base')

@section('corpo')
<header class="topo-painel" style="background:var(--tinta)">
    <a href="{{ route('inicio') }}" style="display:flex;align-items:center;gap:8px;color:#fff;font:700 1.1rem var(--fonte-titulo);margin-right:auto">
        <img src="{{ asset('assets/img/simbolo-branco.svg') }}" alt="" width="36" height="21"> fora da fila<span class="ponto">.</span></a>
</header>
<main class="conteudo" style="margin:0 auto">
    @yield('conteudo')
</main>
@endsection
