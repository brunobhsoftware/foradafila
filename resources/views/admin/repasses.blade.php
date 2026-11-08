@extends('layouts.admin')
@section('titulo', 'Repasses')

@section('conteudo')
<div class="cabecalho"><div><h1>Repasses</h1>
    <p>Vendas recebidas na conta da BH Software (recebimento pela plataforma), já sem a taxa. Faça o Pix/TED pelo banco e registre aqui.
        O valor de cada evento fica liberado {{ \App\Support\Recebimento::PRAZO_DIAS_UTEIS }} dias úteis depois do fim do evento.</p></div></div>
@error('comprovante')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

<section class="cartao">
    @if ($linhas->isEmpty())
        <div class="vazio"><h3>Nada a repassar</h3><p>Quando houver vendas pagas pela plataforma, elas aparecem aqui.</p></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Produtora</th><th>Destino</th><th class="num">Liberado agora</th><th class="num">Aguardando</th><th class="num">Taxa retida</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($linhas as $l)
            <tr><td><b>{{ $l->org->nomeExibicao() }}</b>@if ($l->devolver)<br><small style="color:var(--vermelho)">{{ $l->devolver }} pagamento(s) de pedido cancelado: devolver ao comprador</small>@endif</td>
                <td><small>{{ $l->destino ?? 'sem dados para repasse' }}</small></td>
                <td class="num"><b>{{ moeda($l->liberado) }}</b></td>
                <td class="num">{{ moeda($l->aguardando) }}@if ($l->proxima)<br><small>a partir de {{ \Illuminate\Support\Carbon::parse($l->proxima)->format('d/m') }}</small>@endif</td>
                <td class="num">{{ moeda($l->comissao) }}</td>
                <td class="num">@if ($l->liberado > 0)<button type="button" class="botao botao--primario botao--pequeno" data-alternar="rep-{{ $l->org->id }}">Registrar repasse</button>@endif</td></tr>
            @if ($l->liberado > 0)
            <tr class="linha-acoes" id="rep-{{ $l->org->id }}" hidden><td colspan="6">
                <form method="post" action="{{ route('admin.repasses.registrar', $l->org->uuid) }}" class="form-grade"
                      data-confirmar="Confirmar o repasse de {{ moeda($l->liberado) }} para {{ $l->org->nomeExibicao() }}?">@csrf
                    <p class="col-12" style="margin:0">Transfira <b>{{ moeda($l->liberado) }}</b> para <b>{{ $l->destino ?? '—' }}</b> e registre:</p>
                    <x-campo nome="pago_em" rotulo="Data da transferência" tipo="date" :valor="now('America/Sao_Paulo')->toDateString()" classe="col-3" obrigatorio />
                    <x-campo nome="comprovante" rotulo="Identificador (E2E do Pix, nº do TED)" classe="col-5" obrigatorio />
                    <x-campo nome="observacao" rotulo="Observação" classe="col-4" />
                    <div class="col-12"><button class="botao botao--primario botao--pequeno">Registrar repasse de {{ moeda($l->liberado) }}</button></div>
                </form></td></tr>
            @endif
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

<section class="cartao" style="margin-top:18px">
    <h2 style="padding:16px 20px 0;margin:0">Repasses feitos</h2>
    @if ($feitos->isEmpty())<div class="vazio"><p>Nenhum repasse registrado ainda.</p></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Data</th><th>Produtora</th><th>Destino</th><th class="num">Vendas</th><th class="num">Taxa</th><th class="num">Repassado</th><th>Comprovante</th></tr></thead>
        <tbody>@foreach ($feitos as $r)
            <tr><td>{{ data_local($r->pago_em, 'd/m/Y') }}</td><td>{{ $r->nome_fantasia ?: $r->razao_social }}</td><td><small>{{ $r->destino }}</small></td>
                <td class="num">{{ moeda($r->valor_vendas) }}</td><td class="num">{{ moeda($r->valor_comissao) }}</td><td class="num"><b>{{ moeda($r->valor_repasse) }}</b></td><td><small>{{ $r->comprovante }}</small></td></tr>
        @endforeach</tbody>
    </table></div>
    @endif
</section>
@endsection
