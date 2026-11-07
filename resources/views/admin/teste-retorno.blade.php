@extends($admin ? 'layouts.admin' : 'layouts.simples')
@section('titulo', 'Pagamento de teste')

@php
    $pag = $order['transactions']['payments'][0] ?? [];
    $status = $order['status'] ?? null;
    $nomes = ['processed' => 'Aprovado', 'processing' => 'Em processamento', 'action_required' => 'Aguardando ação', 'failed' => 'Recusado',
        'canceled' => 'Cancelado', 'refunded' => 'Devolvido', 'created' => 'Criado (não pago)'];
    $resultados = ['sucesso' => 'Pagamento concluído', 'falha' => 'Pagamento não concluído', 'pendente' => 'Pagamento pendente'];
@endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Pagamento de teste</h1><p>Retorno do Checkout Pro do Mercado Pago (API de Orders).</p></div></div>

<section class="cartao" style="padding:20px">
    <p>
        @if ($status)
            Situação: <span class="selo {{ $status === 'processed' ? 'selo--verde' : ($status === 'failed' ? 'selo--laranja' : 'selo--amarelo') }}">{{ $nomes[$status] ?? $status }}</span>
            <small class="suave">(conferido na API do Mercado Pago)</small>
        @else
            Retorno: <b>{{ $resultados[$resultado] ?? 'desconhecido' }}</b>
        @endif
        @if ($info)<span class="selo {{ $info['ambiente'] === 'teste' ? 'selo--azul' : 'selo--cinza' }}">{{ $info['ambiente'] === 'teste' ? 'Credenciais de teste' : 'Produção' }}</span>@endif
    </p>
    <dl class="mp__dados">
        <div><dt>ID da order</dt><dd>{{ $info['order'] ?? ($order['id'] ?? '—') }}</dd></div>
        <div><dt>ID do pagamento</dt><dd>{{ $pag['reference']['id'] ?? ($extras['payment_id'] ?? '—') }}</dd></div>
        <div><dt>Referência externa</dt><dd>{{ $ref ?: '—' }}</dd></div>
        @if ($order)
            <div><dt>Valor</dt><dd>{{ moeda((float) ($order['total_amount'] ?? 0)) }}</dd></div>
            <div><dt>Meio</dt><dd>{{ $pag['payment_method']['id'] ?? '—' }} ({{ $pag['payment_method']['type'] ?? '—' }})</dd></div>
            <div><dt>Detalhe</dt><dd>{{ $order['status_detail'] ?? '—' }}</dd></div>
        @endif
    </dl>
    <p style="margin-top:16px"><small class="suave">O ID da order é o que a "Qualidade da integração" do Mercado Pago usa para medir. A medição pode levar algumas horas para atualizar.</small></p>
    @if ($admin)
        <a href="{{ route('admin.mercadopago') }}" class="botao botao--escuro botao--pequeno">Voltar para Mercado Pago</a>
    @else
        <p><small class="suave">Você está numa janela sem login: anote o ID da order. Logado na Administração, este mesmo endereço mostra a situação conferida no Mercado Pago.</small></p>
    @endif
</section>
@endsection
