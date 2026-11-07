@extends('layouts.admin')
@section('titulo', 'Pagamento de teste')

@php
    $status = $pagamento['status'] ?? ($q['status'] ?? $q['collection_status'] ?? null);
    $nomes = ['approved' => 'Aprovado', 'pending' => 'Pendente', 'in_process' => 'Em análise', 'rejected' => 'Recusado', 'cancelled' => 'Cancelado', 'refunded' => 'Devolvido', 'null' => 'Não concluído'];
@endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Pagamento de teste</h1><p>Retorno do Checkout Pro do Mercado Pago.</p></div></div>

<section class="cartao" style="padding:20px">
    <p>Situação:
        <span class="selo {{ $status === 'approved' ? 'selo--verde' : ($status === 'rejected' ? 'selo--laranja' : 'selo--amarelo') }}">{{ $nomes[$status] ?? ($status ?: 'desconhecida') }}</span>
        @if ($pagamento)<small class="suave">(conferido na API do Mercado Pago)</small>@endif
    </p>
    <dl class="mp__dados">
        <div><dt>ID do pagamento (payment_id)</dt><dd>{{ $q['payment_id'] ?? $q['collection_id'] ?? '—' }}</dd></div>
        <div><dt>ID do pedido (merchant_order_id)</dt><dd>{{ $q['merchant_order_id'] ?? '—' }}</dd></div>
        <div><dt>Preferência (preference_id)</dt><dd>{{ $q['preference_id'] ?? '—' }}</dd></div>
        <div><dt>Referência externa</dt><dd>{{ $q['external_reference'] ?? '—' }}</dd></div>
        @if ($pagamento)
            <div><dt>Valor</dt><dd>{{ moeda($pagamento['transaction_amount'] ?? 0) }}</dd></div>
            <div><dt>Meio</dt><dd>{{ $pagamento['payment_method_id'] ?? '—' }} ({{ $pagamento['payment_type_id'] ?? '—' }})</dd></div>
            <div><dt>Detalhe</dt><dd>{{ $pagamento['status_detail'] ?? '—' }}</dd></div>
        @endif
    </dl>
    <p style="margin-top:16px"><small class="suave">Copie o ID do pagamento ou do pedido para a "Qualidade da integração" no painel do Mercado Pago.
        Em alguns segundos o aviso (webhook) deve aparecer na página do Mercado Pago da Administração.</small></p>
    <a href="{{ route('admin.mercadopago') }}" class="botao botao--escuro botao--pequeno">Voltar para Mercado Pago</a>
</section>
@endsection
