@extends('layouts.base')

@section('corpo')
<div class="acesso">
    <aside class="acesso__lado">
        <div>
            <a href="{{ route('inicio') }}" class="marca-grande">
                <img src="{{ asset('assets/img/simbolo-branco.svg') }}" alt="" width="56" height="33">
                <span>fora da fila<span class="ponto">.</span></span>
            </a>
            <h1>@yield('chamada', 'Coloque seu evento fora da fila.')</h1>
            <p>Plataforma completa para vender ingressos, controlar a entrada e acompanhar tudo em tempo real.</p>
            <ul>
                <li>Sem taxa para o comprador</li>
                <li>O dinheiro das vendas cai direto na sua conta</li>
                <li>Equipe com níveis de acesso: gerente, financeiro, portaria</li>
                <li>Check-in por QR Code, inclusive sem internet</li>
            </ul>
        </div>
        <img class="ilustracao" src="{{ asset('assets/img/ilustracao-fila.png') }}" alt="" width="1080" height="414">
    </aside>
    <main class="acesso__form">
        <div class="caixa">
            @include('layouts.alertas')
            @yield('conteudo')
        </div>
    </main>
</div>
@endsection
