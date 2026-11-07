@props(['status'])
@php
    $cor = ['rascunho' => '', 'publicado' => 'verde', 'pausado' => 'amarelo', 'encerrado' => 'azul', 'cancelado' => 'vermelho'][$status] ?? '';
@endphp
<span class="selo {{ $cor ? 'selo--'.$cor : '' }}">{{ \App\Models\Evento::STATUS[$status] ?? $status }}</span>
