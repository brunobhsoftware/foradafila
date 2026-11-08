@extends('layouts.painel')
@section('titulo', 'Financeiro')

@php
    $conectada = $conta && $conta->conectada();
    $painelMp = 'https://www.mercadopago.com.br/developers/panel/app';
    // Etapa atual: 1 = criar a aplicação, 2 = conectar a conta, 3 = conectada (homologação a seguir)
    $etapa = $conectada ? 3 : ($configurado ? 2 : 1);
@endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>Financeiro</h1><p>Como você recebe o dinheiro dos ingressos pagos e para onde vão os repasses.</p></div>
</div>

@error('mercadopago')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

@if ($v012)
@php
    $prazo = \App\Support\Recebimento::PRAZO_DIAS_UTEIS;
    $calc = \App\Support\Esquema::temV008() ? \App\Support\MotorTaxas::calcular($org, 'ingresso_pago', 100) : null;
    $tx = (float) ($calc['taxa'] ?? 0); $ilustr = $tx <= 0; if ($ilustr) { $tx = 5.00; }
    $pref = old('recebimento_preferido', $org->recebimento_preferido ?: (\App\Support\Recebimento::temConta($org) && ! $org->pix_chave ? 'conta' : 'pix'));
@endphp
<section class="cartao" style="padding:22px 24px">
    <h2 style="margin-top:0">Como você recebe</h2>
    <div class="grade grade-3" style="gap:14px;margin:6px 0 16px">
        <div class="exemplo-taxa" style="padding:12px 14px;background:#F4F5F7;border-left:3px solid var(--laranja);border-radius:6px">
            <b>1. O comprador paga</b><br><small>Pix, cartão ou boleto pelo Mercado Pago, na conta da plataforma Fora da Fila (BH Software). O ingresso sai na hora em que o pagamento é aprovado.</small></div>
        <div class="exemplo-taxa" style="padding:12px 14px;background:#F4F5F7;border-left:3px solid var(--laranja);border-radius:6px">
            <b>2. A taxa já fica separada</b><br><small>A taxa da plataforma é descontada das vendas (ou paga pelo comprador, se você escolheu repassá-la). Você não precisa pagar fatura depois.</small></div>
        <div class="exemplo-taxa" style="padding:12px 14px;background:#F4F5F7;border-left:3px solid var(--laranja);border-radius:6px">
            <b>3. Você recebe o repasse</b><br><small>Em até {{ $prazo }} dias úteis depois do fim do evento, por Pix ou transferência para a conta cadastrada abaixo, com o extrato das vendas.</small></div>
    </div>
    <p style="margin:0 0 4px"><b>Exemplo{{ $ilustr ? ' (valores ilustrativos, taxa de 5%)' : '' }}:</b> 10 ingressos de {{ moeda(100) }}.</p>
    <ul style="margin:0 0 6px;padding-left:20px">
        <li><b>Taxa embutida:</b> o público paga {{ moeda(1000) }} e você recebe {{ moeda(1000 - 10 * $tx) }} ({{ moeda(10 * $tx) }} de taxa).</li>
        <li><b>Taxa repassada ao comprador:</b> o público paga {{ moeda(1000 + 10 * $tx) }} e você recebe {{ moeda(1000) }}.</li>
    </ul>
    <p class="suave" style="margin:0"><small>Escolha embutida ou repassada em Organização → Taxa da plataforma (ou em cada evento). Tarifas do meio de pagamento e devoluções aos compradores são descontadas no repasse e aparecem no extrato.</small></p>
</section>

<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
        <h2 style="margin:0">Dados para o repasse</h2>
        @if ($recebimento && $recebimento->status === 'ativa')<span class="selo selo--verde">Pronto para vender ingressos pagos</span>
        @else<span class="selo selo--amarelo">Preencha para vender ingressos pagos</span>@endif
    </div>
    <p class="suave" style="margin:6px 0 14px">Informe a chave Pix ou a conta bancária da organização (pode cadastrar as duas e escolher a preferida). Use uma conta em nome da organização ou do representante legal.</p>
    @can('financeiro.contas')
    <form method="post" action="{{ route('financeiro.recebimento') }}" class="form-grade">
        @csrf @method('PUT')
        <div class="campo col-12"><label>Como prefere receber</label>
            <div style="display:flex;gap:18px;flex-wrap:wrap">
                <label class="opcao-cartao"><input type="radio" name="recebimento_preferido" value="pix" @checked($pref === 'pix')> Pix</label>
                <label class="opcao-cartao"><input type="radio" name="recebimento_preferido" value="conta" @checked($pref === 'conta')> Transferência para conta bancária</label>
            </div></div>
        <h3 class="col-12" style="margin:8px 0 0;font-size:1rem">Chave Pix</h3>
        <x-campo nome="pix_tipo" rotulo="Tipo da chave" tipo="select" :opcoes="['' => 'Selecione'] + \App\Support\Recebimento::PIX_TIPOS" :valor="$org->pix_tipo" classe="col-4" />
        <x-campo nome="pix_chave" rotulo="Chave Pix" :valor="$org->pix_chave" classe="col-8" autocomplete="off" />
        <h3 class="col-12" style="margin:8px 0 0;font-size:1rem">Conta bancária</h3>
        <x-campo nome="banco" rotulo="Banco" :valor="$org->banco" classe="col-6" list="lista-bancos" placeholder="Ex.: 341 - Itaú Unibanco" autocomplete="off" />
        <x-campo nome="agencia" rotulo="Agência" :valor="$org->agencia" classe="col-2" inputmode="numeric" placeholder="1234" />
        <x-campo nome="conta" rotulo="Conta" :valor="$org->conta" classe="col-4" placeholder="12345-6" ajuda="Com o dígito." />
        <x-campo nome="favorecido" rotulo="Favorecido" :valor="$org->favorecido" classe="col-12" ajuda="Titular da conta, como está no banco." />
        <datalist id="lista-bancos">@foreach (\App\Http\Controllers\Painel\OrganizacaoController::BANCOS as $b)<option value="{{ $b }}">@endforeach</datalist>
        <div class="col-12 acoes-form"><button class="botao botao--primario">Salvar dados de recebimento</button></div>
    </form>
    @else
        <p>Destino dos repasses: <b>{{ \App\Support\Recebimento::destino($org) ?? 'não cadastrado' }}</b></p>
    @endcan
</section>
@endif

@if ($conexaoDireta || ! $v012)

<section class="cartao mp">
    <header class="mp__topo">
        <div class="mp__marca" aria-hidden="true"><x-icone nome="dinheiro" /></div>
        <div class="mp__titulo">
            <h2>Mercado Pago</h2>
            <small>Pix, cartão de crédito (até 12x) e boleto. Necessário para vender ingressos pagos.</small>
        </div>
        @if ($conectada)
            <span class="selo selo--verde">{{ \App\Models\ContaPagamento::STATUS[$conta->status] }}</span>
        @else
            <span class="selo selo--cinza">Não conectada</span>
        @endif
    </header>

    <ol class="mp__etapas" aria-label="Etapas da integração">
        @foreach ([1 => 'Liberação', 2 => 'Conectar a conta', 3 => 'Homologar e ativar'] as $n => $t)
            <li class="{{ $n < $etapa ? 'feito' : ($n === $etapa ? 'atual' : '') }}" @if ($n === $etapa) aria-current="step" @endif>
                <span>{{ $n < $etapa ? '✓' : $n }}</span>{{ $t }}
            </li>
        @endforeach
    </ol>

    {{-- 1. Aplicação do Fora da Fila no Mercado Pago: feita uma vez pela equipe da plataforma (Administração) --}}
    <details class="mp__bloco" @if ($etapa === 1) open @endif>
        <summary><b>1. Liberação pela plataforma</b>
            <small>{{ $configurado ? 'Concluído: a integração está liberada.' : 'A equipe do Fora da Fila está configurando a integração.' }}</small></summary>
        <div class="mp__conteudo">
            <p class="suave">O Fora da Fila tem uma aplicação única no Mercado Pago. Você não precisa criar nada nem copiar chaves:
                basta conectar a sua conta na etapa 2, e o dinheiro das vendas cai direto nela.</p>
            @if (auth()->user()->admin_plataforma)
                <a href="{{ route('admin.mercadopago') }}" class="botao botao--escuro botao--pequeno">Configurar na Administração</a>
            @endif
        </div>
    </details>

    {{-- 2. Conectar a conta da organização --}}
    <details class="mp__bloco" @if ($etapa === 2 || $etapa === 3) open @endif>
        <summary><b>2. Conectar a conta da organização</b>
            <small>{{ $conectada ? 'Concluído.' : 'Entre com a conta que vai receber as vendas.' }}</small></summary>
        <div class="mp__conteudo">
            @if ($conectada)
                <dl class="mp__dados">
                    <div><dt>Conta</dt><dd>{{ $conta->email_conta ?? '—' }}@if ($conta->apelido_externo) <small>({{ $conta->apelido_externo }})</small>@endif</dd></div>
                    <div><dt>Identificador</dt><dd>{{ $conta->conta_externa_id }}</dd></div>
                    <div><dt>Ambiente</dt><dd>{{ $conta->ambiente === 'producao' ? 'Produção' : 'Testes (sandbox)' }}</dd></div>
                    <div><dt>Conectada em</dt><dd>{{ $conta->conectado_em ? data_local($conta->conectado_em) : '—' }}</dd></div>
                    <div><dt>Autorização válida até</dt><dd>{{ $conta->token_expira_em ? data_local($conta->token_expira_em, 'd/m/Y') : '—' }}</dd></div>
                </dl>
                @if ($conta->ultimo_erro)<div class="alerta alerta--erro"><p>{{ $conta->ultimo_erro }}</p></div>@endif
                @can('financeiro.contas')
                    <form method="post" action="{{ route('financeiro.mp.desconectar') }}" data-confirmar="Desconectar a conta do Mercado Pago? Os ingressos pagos deixam de ser vendidos.">
                        @csrf <button class="botao botao--perigo botao--pequeno">Desconectar</button>
                    </form>
                @endcan
            @else
                <ol class="mp__passos">
                    <li>Clique em <b>Conectar Mercado Pago</b> e entre com a conta que vai receber as vendas.</li>
                    <li>Autorize o Fora da Fila a criar cobranças em nome da organização. Você não precisa copiar chaves.</li>
                    <li>Você volta para esta tela com a conta conectada.</li>
                </ol>
                @if ($conta && $conta->status === 'revogada')<p><small>Conta desconectada em {{ data_local($conta->desconectado_em) }}.</small></p>@endif
                @unless ($configurado)
                    <div class="alerta alerta--info"><p>O botão é liberado assim que a equipe do Fora da Fila concluir a configuração.</p></div>
                @endunless
                @can('financeiro.contas')
                    <form method="post" action="{{ route('financeiro.mp.conectar') }}">
                        @csrf <button class="botao botao--primario" @disabled(! $configurado)>Conectar Mercado Pago</button>
                    </form>
                @else
                    <p><small>Só quem tem a permissão "contas de recebimento" pode conectar a conta.</small></p>
                @endcan
            @endif
        </div>
    </details>

    {{-- 3. Homologação --}}
    <details class="mp__bloco" @if ($etapa === 3) open @endif>
        <summary><b>3. Homologar e ativar</b><small>Testes de Pix, cartão, aviso de pagamento e reembolso.</small></summary>
        <div class="mp__conteudo">
            <p class="suave">Com a conta conectada, fazemos os testes obrigatórios no ambiente de testes do Mercado Pago. Depois deles a conta fica
                <b>ativa</b> e os eventos com ingresso pago podem ser publicados. Esta etapa será liberada em breve.</p>
        </div>
    </details>
</section>
@else
<section class="cartao" style="margin-top:18px;padding:20px 24px;opacity:.85">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
        <h2 style="margin:0">Receber direto na sua conta do Mercado Pago</h2><span class="selo selo--cinza">Em breve</span></div>
    <p class="suave" style="margin:8px 0 12px">Em breve você poderá conectar a conta do Mercado Pago da organização para receber cada venda direto, com a taxa da plataforma separada automaticamente. Até lá, as vendas são recebidas pela plataforma e repassadas como explicado acima.</p>
    <button class="botao botao--contorno" disabled>Conectar Mercado Pago</button>
</section>
@endif

<section class="cartao" style="margin-top:18px;padding:20px">
    <h2 style="margin-top:0">Em breve</h2>
    <p class="suave">Pagamentos recebidos por evento, reembolsos, comissão do Fora da Fila e faturas. PagBank e PayPal também estão previstos.</p>
</section>
@endsection
