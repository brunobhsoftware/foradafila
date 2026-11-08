@extends('layouts.base')

@php
    $menuAdmin = [
        ['rota' => 'admin.inicio', 'icone' => 'painel', 'texto' => 'Início'],
        ['rota' => 'admin.empresas', 'icone' => 'organizacao', 'texto' => 'Empresas'],
        ['rota' => 'admin.taxas', 'icone' => 'financeiro', 'texto' => 'Taxas e isenções'],
        ['rota' => 'admin.mercadopago', 'icone' => 'dinheiro', 'texto' => 'Mercado Pago'],
        ['rota' => 'admin.banco', 'icone' => 'organizacao', 'texto' => 'Atualizações do banco'],
    ];
    $emBreve = [];
@endphp

@section('corpo')
<div class="app">
    <aside class="menu-lateral menu-lateral--admin" id="menu-lateral" aria-label="Menu da administração">
        <a href="{{ route('admin.inicio') }}" class="menu-lateral__marca">
            <img src="{{ asset('assets/img/simbolo-branco.svg') }}" alt="" width="42" height="25">
            <span>fora da fila<span class="ponto">.</span></span>
        </a>
        <div class="menu-lateral__grupo">Administração</div>
        <nav>
            @foreach ($menuAdmin as $item)
                @php $ativo = request()->routeIs($item['rota'].'*'); @endphp
                <a href="{{ route($item['rota']) }}" class="{{ $ativo ? 'ativo' : '' }}" @if ($ativo) aria-current="page" @endif><x-icone :nome="$item['icone']" /> {{ $item['texto'] }}</a>
            @endforeach
            @foreach ($emBreve as $item)
                <a href="#" aria-disabled="true" onclick="return false"><x-icone :nome="$item['icone']" /> {{ $item['texto'] }} <span class="selo-breve">em breve</span></a>
            @endforeach
        </nav>
    </aside>

    <div class="principal">
        <header class="topo-painel">
            <button type="button" class="topo-painel__menu" data-menu-alternar aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menu"><x-icone nome="menu" /></button>
            <span class="topo-admin__selo">Administração da plataforma</span>
            <span class="topo-admin__quem">{{ auth()->user()->nome }}</span>
            @if (auth()->user()->vinculosAtivos()->exists())<a href="{{ route('painel') }}" class="botao botao--contorno botao--pequeno">Área do produtor</a>@endif
            <form method="post" action="{{ route('sair') }}">@csrf<button class="botao botao--perigo botao--pequeno">Sair</button></form>
        </header>
        <main class="conteudo" id="conteudo">
            @include('layouts.alertas')
            @yield('conteudo')
        </main>
    </div>
</div>
@endsection
