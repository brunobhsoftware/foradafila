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
    <div><h1>Financeiro</h1><p>O dinheiro dos ingressos cai direto na conta da organização na operadora. O Fora da Fila não recebe nem repassa esses valores.</p></div>
</div>

@error('mercadopago')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror

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
        @foreach ([1 => 'Criar a aplicação', 2 => 'Conectar a conta', 3 => 'Homologar e ativar'] as $n => $t)
            <li class="{{ $n < $etapa ? 'feito' : ($n === $etapa ? 'atual' : '') }}" @if ($n === $etapa) aria-current="step" @endif>
                <span>{{ $n < $etapa ? '✓' : $n }}</span>{{ $t }}
            </li>
        @endforeach
    </ol>

    {{-- 1. Aplicação no Mercado Pago (gera o Client ID) --}}
    <details class="mp__bloco" @if ($etapa === 1) open @endif>
        <summary><b>1. Criar a aplicação no Mercado Pago</b>
            <small>{{ $configurado ? 'Concluído: a aplicação já está configurada na plataforma.' : 'Gera o Client ID e o Client Secret.' }}</small></summary>
        <div class="mp__conteudo">
            <ol class="mp__passos">
                <li>Abra o painel de desenvolvedores e clique em <b>Criar aplicação</b> (nome "Fora da Fila", pagamentos on-line).</li>
                <li>Em <b>Configurações de OAuth</b>, cadastre o endereço de retorno abaixo.</li>
                <li>Em <b>Webhooks</b>, cadastre o endereço de avisos, marque o evento <b>Pagamentos</b> e gere a assinatura secreta.</li>
                <li>Copie o <b>Client ID</b>, o <b>Client Secret</b> e a <b>assinatura secreta</b> e envie à equipe do Fora da Fila por um canal seguro (nunca por e-mail aberto ou chat).</li>
            </ol>
            <div class="mp__campos">
                <div class="campo"><label for="mp-retorno">Endereço de retorno (OAuth)</label>
                    <div class="copiar"><input id="mp-retorno" value="{{ \App\Pagamentos\MercadoPago::retornoUrl() }}" readonly><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="mp-retorno">Copiar</button></div></div>
                <div class="campo"><label for="mp-webhook">Endereço dos avisos (Webhooks)</label>
                    <div class="copiar"><input id="mp-webhook" value="{{ url(config('pagamentos.mercadopago.webhook')) }}" readonly><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="mp-webhook">Copiar</button></div></div>
            </div>
            <a href="{{ $painelMp }}" target="_blank" rel="noopener" class="botao botao--escuro">Abrir painel de desenvolvedores do Mercado Pago ↗</a>
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
                    <div class="alerta alerta--info"><p>O botão é liberado quando a aplicação da etapa 1 estiver configurada na plataforma.</p></div>
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

<section class="cartao" style="margin-top:18px;padding:20px">
    <h2 style="margin-top:0">Em breve</h2>
    <p class="suave">Pagamentos recebidos por evento, reembolsos, comissão do Fora da Fila e faturas. PagBank e PayPal também estão previstos.</p>
</section>
@endsection
