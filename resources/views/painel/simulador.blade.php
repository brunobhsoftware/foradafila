@extends('layouts.painel')
@section('titulo', 'Simulador de vendas')

@php
    $formas = ['pix' => 'Pix', 'cartao' => 'Cartão de crédito'];
    $semTarifa = $pct['pix'] <= 0 && $pct['cartao'] <= 0;
@endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Simulador de vendas</h1>
    <p>Quanto a sua empresa recebe por ingresso vendido, no Pix e no cartão, com a configuração de hoje.</p></div></div>

<form method="get" action="{{ route('simulador') }}" class="cartao" style="padding:18px 20px">
    <div class="grade grade-2">
        <div class="campo"><label for="sim-valor">Valor do ingresso (R$)</label>
            <input id="sim-valor" name="valor" type="number" step="0.01" min="1" value="{{ number_format($valor, 2, '.', '') }}" required>
            @error('valor')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo"><label>Quem paga a taxa da plataforma</label>
            <div class="grade grade-2">
                <label class="opcao-cartao"><input type="radio" name="taxa" value="embutida" @checked(! $repassada)>
                    <span><b>Embutida</b><small>Sai do valor do ingresso</small></span></label>
                <label class="opcao-cartao"><input type="radio" name="taxa" value="repassada" @checked($repassada)>
                    <span><b>Repassada</b><small>O comprador paga à parte</small></span></label>
            </div></div>
    </div>
    <button class="botao botao--primario">Simular</button>
</form>

<div class="grade grade-2" style="margin-top:14px">
    @foreach ($formas as $forma => $nome)
        @php $f = $simulacao[$forma]; @endphp
        <section class="cartao simulador-cartao">
            <h2>{{ $nome }}</h2>
            <dl>
                <div><dt>O comprador paga</dt><dd>{{ moeda($simulacao['total']) }}</dd></div>
                <div><dt>Taxa da plataforma{{ $modo === 'propria' ? ' (cobrada em fatura)' : '' }}</dt><dd>− {{ moeda($simulacao['taxa']) }}</dd></div>
                @if ($f['pct'] > 0)
                    @if ($f['coberta'] > 0)
                        <div class="simulador-coberta"><dt>Tarifa do Mercado Pago ({{ numero($f['pct'], 2) }}%) — paga pela plataforma</dt><dd>{{ moeda($f['coberta']) }}</dd></div>
                    @endif
                    @if ($f['da_empresa'] > 0)
                        <div><dt>Tarifa do Mercado Pago ({{ numero($f['pct'], 2) }}%){{ $f['coberta'] > 0 ? ' — parte da empresa' : '' }}</dt><dd>− {{ moeda($f['da_empresa']) }}</dd></div>
                    @endif
                @endif
                <div class="simulador-total"><dt>Sua empresa recebe</dt><dd>{{ moeda($f['recebe']) }}</dd></div>
            </dl>
        </section>
    @endforeach
</div>

<section class="cartao" style="padding:18px 20px;margin-top:14px">
    <h2 style="margin-top:0;font-size:1.05rem">Como a conta é feita</h2>
    @if ($modo === 'direta')
        <p>As vendas caem direto na conta do Mercado Pago da sua empresa. A plataforma retém a taxa dela na hora da venda e
            <b>paga a tarifa do Mercado Pago por você</b> @if (! $semTarifa) (até {{ numero($pct['pix'], 2) }}% no Pix e {{ numero($pct['cartao'], 2) }}% no cartão) @endif</p>
        <p class="suave" style="margin-bottom:0">Se a sua conta do Mercado Pago cobrar mais do que isso (por exemplo, por receber o dinheiro do cartão na hora ou oferecer parcelamento sem juros),
            a diferença é da empresa e sai do valor recebido.</p>
    @elseif ($modo === 'propria')
        <p>As vendas caem na conta do Mercado Pago da sua empresa, pela aplicação configurada manualmente. A tarifa do Mercado Pago é descontada na sua conta
            e a taxa da plataforma é cobrada depois, em fatura.</p>
        <p class="suave" style="margin-bottom:0">A tarifa mostrada é uma estimativa @if (! $semTarifa) ({{ numero($pct['pix'], 2) }}% no Pix e {{ numero($pct['cartao'], 2) }}% no cartão) @endif — a real depende da sua conta no Mercado Pago.</p>
    @else
        <p>As vendas caem na conta da plataforma, que paga a tarifa do Mercado Pago, retém a taxa dela e repassa o restante
            em até {{ \App\Support\Recebimento::PRAZO_DIAS_UTEIS }} dias úteis depois do fim do evento.</p>
    @endif
    @if ($simulacao['taxa'] <= 0)<p class="suave" style="margin:8px 0 0">Hoje a sua empresa não paga taxa da plataforma neste valor de ingresso.</p>@endif
</section>

<section class="cartao" style="margin-top:14px">
    <div class="cartao__titulo"><h2>Outros valores de ingresso</h2></div>
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th class="num">Ingresso</th><th class="num">Comprador paga</th><th class="num">Taxa da plataforma</th><th class="num">Você recebe no Pix</th><th class="num">Você recebe no cartão</th></tr></thead>
        <tbody>
        @foreach ($tabela as $l)
            <tr><td class="num">{{ moeda($l['valor']) }}</td><td class="num">{{ moeda($l['total']) }}</td><td class="num">{{ moeda($l['taxa']) }}</td>
                <td class="num"><b>{{ moeda($l['pix']['recebe']) }}</b></td><td class="num"><b>{{ moeda($l['cartao']['recebe']) }}</b></td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endsection
