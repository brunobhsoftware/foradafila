@extends('layouts.base')
@section('titulo', $termo->titulo)

@section('corpo')
<div class="pagina-simples">
    <a href="{{ route('inicio') }}" class="menu-lateral__marca" style="color:var(--tinta);padding:0 0 24px">
        <img src="{{ asset('assets/img/simbolo.svg') }}" alt="" width="42" height="25"> fora da fila<span class="ponto">.</span>
    </a>
    <h1>{{ $termo->titulo }}</h1>
    <p class="suave">Versão {{ $termo->versao }} · vigente desde {{ data_local($termo->vigente_desde, 'd/m/Y') }}</p>
    <div class="cartao cartao__corpo" style="margin-top:20px">{!! $termo->conteudo_html !!}</div>
</div>
@endsection
