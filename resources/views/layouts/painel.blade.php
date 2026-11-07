@extends('layouts.base')

@php
    // Menu único da área do produtor. Cada item aparece só para quem tem a permissão.
    $menu = [
        null => [
            ['rota' => 'painel', 'ativo' => 'painel', 'icone' => 'painel', 'texto' => 'Painel', 'pode' => ['dashboard.ver']],
            ['rota' => 'eventos.index', 'ativo' => 'eventos.*', 'icone' => 'evento', 'texto' => 'Meus eventos', 'pode' => ['evento.ver']],
        ],
        'Vendas' => [
            ['rota' => 'pedidos.index', 'ativo' => 'pedidos.*', 'icone' => 'vendas', 'texto' => 'Pedidos', 'pode' => ['pedido.ver']],
            ['rota' => 'participantes.index', 'ativo' => 'participantes.*', 'icone' => 'participantes', 'texto' => 'Participantes', 'pode' => ['participante.ver']],
            ['rota' => 'checkin.index', 'ativo' => 'checkin.*', 'icone' => 'checkin', 'texto' => 'Check-in', 'pode' => ['checkin.realizar']],
        ],
        'Divulgação' => [
            ['modulo' => 'cupons', 'icone' => 'cupom', 'texto' => 'Cupons', 'pode' => ['cupom.gerenciar', 'promoter.gerenciar']],
            ['modulo' => 'convidados', 'icone' => 'convidados', 'texto' => 'Convidados', 'pode' => ['convidado.gerenciar', 'acesso_privado.gerenciar']],
            ['modulo' => 'mensagens', 'icone' => 'mensagem', 'texto' => 'Mensagens', 'pode' => ['mensagem.gerenciar']],
        ],
        'Gestão' => [
            ['rota' => 'financeiro', 'ativo' => 'financeiro*', 'icone' => 'financeiro', 'texto' => 'Financeiro', 'pode' => ['financeiro.ver']],
            ['rota' => 'equipe.index', 'ativo' => 'equipe.*', 'icone' => 'equipe', 'texto' => 'Equipe', 'pode' => ['equipe.gerenciar']],
            ['rota' => 'perfis.index', 'ativo' => 'perfis.*', 'icone' => 'perfil', 'texto' => 'Níveis de acesso', 'pode' => ['equipe.gerenciar']],
            ['rota' => 'organizacao.edit', 'ativo' => 'organizacao.*', 'icone' => 'organizacao', 'texto' => 'Organização', 'pode' => ['organizador.editar']],
        ],
    ];
@endphp

@section('corpo')
<div class="app">
    <aside class="menu-lateral" id="menu-lateral" aria-label="Menu principal">
        <a href="{{ route('painel') }}" class="menu-lateral__marca">
            <img src="{{ asset('assets/img/simbolo-branco.svg') }}" alt="" width="42" height="25">
            <span>fora da fila<span class="ponto">.</span></span>
        </a>
        <nav>
            @foreach ($menu as $grupo => $itens)
                @php $visiveis = array_filter($itens, fn ($i) => $tenant->pode(...$i['pode'])); @endphp
                @continue(empty($visiveis))
                @if ($grupo)<div class="menu-lateral__grupo">{{ $grupo }}</div>@endif
                @foreach ($visiveis as $item)
                    @php
                        $emBreve = isset($item['modulo']);
                        $url = $emBreve ? route('em-breve', $item['modulo']) : route($item['rota']);
                        $ativo = $emBreve ? request()->is('painel/'.$item['modulo']) : request()->routeIs($item['ativo']);
                    @endphp
                    <a href="{{ $url }}" class="{{ $ativo ? 'ativo' : '' }}" @if ($ativo) aria-current="page" @endif>
                        <x-icone :nome="$item['icone']" /> {{ $item['texto'] }}
                        @if ($emBreve)<span class="selo-breve">em breve</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    <div class="principal">
        <header class="topo-painel">
            <button type="button" class="topo-painel__menu" data-menu-alternar aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menu"><x-icone nome="menu" /></button>
            <a href="{{ route('painel') }}" class="topo-painel__marca"><img src="{{ asset('assets/img/simbolo-branco.svg') }}" alt="" width="36" height="21"> fora da fila<span class="ponto">.</span></a>
            @include('layouts.conta-menu')
        </header>
        @isset($impersonacao)
            <div class="faixa-impersonacao" role="status">
                <span>Você está navegando como a empresa <b>{{ $impersonacao['nome'] }}</b>. Tudo o que fizer fica registrado em seu nome.</span>
                <form method="post" action="{{ route('admin.sair-da-conta') }}">@csrf<button class="botao botao--pequeno">Sair do modo "assumir conta"</button></form>
            </div>
        @endisset
        <main class="conteudo" id="conteudo">
            @include('layouts.alertas')
            @yield('conteudo')
        </main>
    </div>
</div>
@endsection
