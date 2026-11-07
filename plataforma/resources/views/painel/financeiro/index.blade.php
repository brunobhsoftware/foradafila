@extends('layouts.painel')
@section('titulo', 'Financeiro')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Financeiro</h1><p>O dinheiro dos ingressos cai direto na conta da organização na operadora. O Fora da Fila não recebe nem repassa esses valores.</p></div>
</div>

@error('mercadopago')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

<section class="cartao conta-operadora">
    <div class="conta-operadora__topo">
        <div>
            <h2>Mercado Pago</h2>
            <small>Pix, cartão de crédito (até 12x) e boleto. Necessário para vender ingressos pagos.</small>
        </div>
        @if ($conta && $conta->conectada())
            <span class="selo selo--verde">{{ \App\Models\ContaPagamento::STATUS[$conta->status] }}</span>
        @else
            <span class="selo selo--cinza">Não conectada</span>
        @endif
    </div>

    @if ($conta && $conta->conectada())
        <dl class="conta-operadora__dados">
            <div><dt>Conta</dt><dd>{{ $conta->email_conta ?? '—' }}@if ($conta->apelido_externo) <small>({{ $conta->apelido_externo }})</small>@endif</dd></div>
            <div><dt>Identificador</dt><dd>{{ $conta->conta_externa_id }}</dd></div>
            <div><dt>Ambiente</dt><dd>{{ $conta->ambiente === 'producao' ? 'Produção' : 'Testes (sandbox)' }}</dd></div>
            <div><dt>Conectada em</dt><dd>{{ $conta->conectado_em ? data_local($conta->conectado_em) : '—' }}</dd></div>
            <div><dt>Autorização válida até</dt><dd>{{ $conta->token_expira_em ? data_local($conta->token_expira_em, 'd/m/Y') : '—' }}</dd></div>
        </dl>
        @if ($conta->ultimo_erro)<div class="alerta alerta--erro"><p>{{ $conta->ultimo_erro }}</p></div>@endif
        <div class="alerta alerta--info"><p><b>Próximo passo:</b> homologação (Pix, cartão, aviso de pagamento e reembolso em ambiente de testes).
            Depois dela a conta fica <b>ativa</b> e os eventos com ingresso pago podem ser publicados.</p></div>
        @can('financeiro.contas')
            <form method="post" action="{{ route('financeiro.mp.desconectar') }}" data-confirmar="Desconectar a conta do Mercado Pago? Os ingressos pagos deixam de ser vendidos.">
                @csrf <button class="botao botao--perigo botao--pequeno">Desconectar</button>
            </form>
        @endcan
    @else
        <ol class="passos-conexao">
            <li>Clique em <b>Conectar Mercado Pago</b> e entre com a conta que vai receber as vendas.</li>
            <li>Autorize o Fora da Fila a criar cobranças em nome da organização. Você não precisa copiar chaves.</li>
            <li>Você volta para esta tela com a conta conectada.</li>
        </ol>
        @if ($conta && $conta->status === 'revogada')<p><small>Conta desconectada em {{ data_local($conta->desconectado_em) }}.</small></p>@endif
        @if (! $configurado)
            <div class="alerta alerta--info"><p>A conexão com o Mercado Pago está sendo preparada pela equipe do Fora da Fila e será liberada em breve.</p></div>
        @endif
        @can('financeiro.contas')
            <form method="post" action="{{ route('financeiro.mp.conectar') }}">
                @csrf <button class="botao botao--primario" @disabled(! $configurado)>Conectar Mercado Pago</button>
            </form>
        @else
            <p><small>Só quem tem a permissão "contas de recebimento" pode conectar a conta.</small></p>
        @endcan
    @endif
</section>

<section class="cartao" style="margin-top:18px">
    <h2>Em breve</h2>
    <p class="suave">Pagamentos recebidos por evento, reembolsos, comissão do Fora da Fila e faturas. PagBank e PayPal também estão previstos.</p>
</section>
@endsection
