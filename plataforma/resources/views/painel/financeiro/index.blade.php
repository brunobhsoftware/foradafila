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

@if ($v012)
@php
    $modoAtual = $org->recebimento_modo ?: 'plataforma';
    $propriaAtiva = $propria && $propria->status === 'ativa';
    $webhookPropria = $propria ? route('webhook.mercadopago.empresa', $propria->uuid) : null;
@endphp
<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <h2 style="margin-top:0">Onde as vendas são recebidas</h2>
    @error('recebimento_modo')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror
    <form method="post" action="{{ route('financeiro.modo') }}" class="pilha">@csrf @method('PUT')
        <label class="opcao-cartao"><input type="radio" name="recebimento_modo" value="plataforma" @checked($modoAtual === 'plataforma')>
            <span><b>Pela plataforma, com repasse</b> — o comprador paga na conta do Fora da Fila e você recebe por Pix ou transferência em até {{ \App\Support\Recebimento::PRAZO_DIAS_UTEIS }} dias úteis após o evento, já com a taxa descontada. Nada para configurar além dos dados acima.</span></label>
        <label class="opcao-cartao"><input type="radio" name="recebimento_modo" value="propria" @checked($modoAtual === 'propria') @disabled(! $propriaAtiva)>
            <span><b>Direto na conta do Mercado Pago da empresa</b> — cada venda cai na hora na sua conta, pela aplicação que você criou no Mercado Pago (abaixo). A taxa da plataforma é cobrada à parte, em fatura.
            @unless ($propriaAtiva)<br><small class="suave">Disponível depois de cadastrar e verificar o Access Token de produção.</small>@endunless</span></label>
        @can('financeiro.contas')<div><button class="botao botao--escuro botao--pequeno">Salvar forma de recebimento</button></div>@endcan
    </form>
</section>

@if (! $temPropria)
<section class="cartao" style="padding:18px 24px;margin-top:18px"><b>Mercado Pago da empresa (aplicação própria)</b>
    <p class="suave" style="margin:6px 0 0">Disponível depois que a plataforma atualizar o banco de dados (V012 completo).</p></section>
@else
<details class="cartao" style="padding:18px 24px;margin-top:18px" @if (request('mp') || $verificacao || $errors->hasAny(['client_id','client_secret','access_token','public_key','webhook_secret'])) open @endif>
    <summary style="cursor:pointer"><b style="font-size:1.1rem">Mercado Pago da empresa (aplicação própria)</b>
        @if ($propriaAtiva)<span class="selo selo--verde" style="margin-left:8px">Ativa{{ $propria->email_conta ? ' — '.$propria->email_conta : '' }}</span>
        @elseif ($propria && $propria->ultimo_erro)<span class="selo selo--vermelho" style="margin-left:8px">Token recusado</span>
        @else<span class="selo selo--cinza" style="margin-left:8px">Não configurada</span>@endif</summary>
    <p class="suave" style="margin:10px 0">Para receber as vendas direto na conta da empresa: crie uma aplicação no
        <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener">painel de desenvolvedores do Mercado Pago</a>
        (logado na conta da empresa; tipo <b>Checkout Pro</b>, API de <b>Orders</b>), cadastre nela o endereço de notificações abaixo e cole aqui as chaves de teste e de produção.</p>
    @if ($webhookPropria)
        <div class="form-grade" style="margin-bottom:6px">
            <div class="campo col-12"><label>URL de notificações (webhooks) — cadastre em "Webhooks" da aplicação, nos dois modos (teste e produção)</label>
                <div class="copiar"><input id="mp-prop-webhook" value="{{ $webhookPropria }}" readonly><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="mp-prop-webhook">Copiar</button></div>
                <small class="suave">Eventos: <b>Order (Mercado Pago)</b> e <b>Pagamentos</b>. Depois copie a "assinatura secreta" gerada e cole nos campos abaixo.</small></div>
            <div class="campo col-6"><label>Site da aplicação</label>
                <div class="copiar"><input id="mp-prop-site" value="{{ url('/') }}" readonly><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="mp-prop-site">Copiar</button></div></div>
            <div class="campo col-6"><label>Tipo de solução</label><input value="Checkout Pro — API de Orders" readonly></div>
        </div>
    @endif

    @if ($verificacao)
        <div class="alerta alerta--info"><p><b>De quem são os tokens:</b><br>
            @foreach (['producao' => 'Produção', 'teste' => 'Teste'] as $k => $t)
                {{ $t }}: @if (! $verificacao[$k]) não cadastrado @elseif ($verificacao[$k]['ok']) {{ $verificacao[$k]['email'] ?? '—' }} ({{ $verificacao[$k]['apelido'] ?? '—' }}) — {{ $verificacao[$k]['teste'] ? 'conta de teste' : 'conta real' }} @else <b style="color:var(--vermelho)">recusado pelo Mercado Pago</b> @endif<br>
            @endforeach</p></div>
    @endif

    @can('financeiro.contas')
    @foreach (['teste' => '1. Ambiente de TESTE', 'producao' => '2. Ambiente de PRODUÇÃO'] as $amb => $titulo)
        @php
            $pre = $amb === 'teste' ? 'teste_' : '';
            $tem = fn ($campo) => $propria && $propria->{$campo};
        @endphp
        <form method="post" action="{{ route('financeiro.mp.propria', $amb) }}" class="form-grade" style="margin-top:16px;padding:14px 16px;background:#F4F5F7;border-left:3px solid var(--laranja);border-radius:6px">
            @csrf @method('PUT')
            <h3 class="col-12" style="margin:0">{{ $titulo }}</h3>
            <x-campo nome="client_id" rotulo="Client ID" :valor="$propria?->{$pre.'client_id'}" classe="col-6" autocomplete="off" />
            <x-campo nome="client_secret" rotulo="Client Secret" tipo="password" classe="col-6" autocomplete="new-password" :placeholder="$tem($pre.'client_secret_cript') ? 'salvo — em branco mantém' : ''" />
            <x-campo nome="access_token" rotulo="Access Token" tipo="password" classe="col-6" autocomplete="new-password" :placeholder="$tem($amb === 'teste' ? 'teste_access_token_cript' : 'access_token_cript') ? 'salvo — em branco mantém' : 'APP_USR-...'" />
            <x-campo nome="public_key" rotulo="Public Key" :valor="$propria?->{$amb === 'teste' ? 'teste_public_key' : 'public_key'}" classe="col-6" autocomplete="off" />
            <x-campo nome="webhook_secret" rotulo="Assinatura secreta dos webhooks" tipo="password" classe="col-12" autocomplete="new-password" :placeholder="$tem($pre.'webhook_secret_cript') ? 'salva — em branco mantém' : ''" />
            <div class="col-12" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <button class="botao {{ $amb === 'teste' ? 'botao--contorno' : 'botao--primario' }} botao--pequeno">Salvar chaves {{ $amb === 'teste' ? 'de teste' : 'de produção' }}</button>
                <button type="submit" form="mp-prop-verificar" class="botao botao--escuro botao--pequeno">Verificar de quem é o token</button>
                @if ($verificacao && ($verificacao[$amb] ?? null))
                    <small>@if ($verificacao[$amb]['ok'])✔ {{ $verificacao[$amb]['email'] ?? '—' }} ({{ $verificacao[$amb]['apelido'] ?? '—' }}) — {{ $verificacao[$amb]['teste'] ? 'conta de teste' : 'conta real' }}@else<b style="color:var(--vermelho)">✖ token recusado pelo Mercado Pago</b>@endif</small>
                @endif
            </div>
        </form>
        @php $temToken = $propria && ($amb === 'teste' ? $propria->teste_access_token_cript : $propria->access_token_cript); @endphp
        <form method="post" action="{{ route('financeiro.mp.propria.teste', $amb) }}" class="form-grade" style="margin:8px 0 0;padding:14px 16px;border:1px dashed var(--borda);border-radius:6px">
            @csrf
            <h4 class="col-12" style="margin:0">Pagamento de teste — {{ $amb === 'teste' ? 'homologação (conta compradora de teste)' : 'produção (cobrança real; pague com outra conta)' }}</h4>
            @error('teste_'.$amb)<p class="col-12 erro" style="color:var(--vermelho);margin:0">{{ $message }}</p>@enderror
            <div class="campo col-4"><label>CPF</label><input name="cpf" required data-mascara="cpf" data-busca-cpf inputmode="numeric" value="{{ $amb === 'teste' ? '123.456.789-09' : '' }}"></div>
            <div class="campo col-8"><label>Nome completo</label><input name="nome" required data-pessoa="nome" value="{{ $amb === 'teste' ? 'APRO Teste' : '' }}"></div>
            <div class="campo col-6"><label>E-mail de quem paga</label><input name="email" type="email" required data-pessoa="email" placeholder="{{ $amb === 'teste' ? 'e-mail da conta compradora de teste' : '' }}"></div>
            <div class="campo col-3"><label>Telefone com DDD</label><input name="telefone" data-mascara="telefone" data-pessoa="telefone"></div>
            <div class="campo col-3"><label>Valor (R$)</label><input name="valor" type="number" step="0.01" min="1" max="100" value="5.00" required></div>
            <div class="col-12"><button class="botao botao--escuro botao--pequeno" @disabled(! $temToken)>{{ $amb === 'teste' ? 'Pagar teste no Mercado Pago' : 'Pagar de verdade no Mercado Pago' }} ↗</button>
                @unless ($temToken)<small class="suave">Salve o Access Token {{ $amb === 'teste' ? 'de teste' : 'de produção' }} antes.</small>@endunless</div>
        </form>
    @endforeach
    @if ($propria)
        <form method="post" action="{{ route('financeiro.mp.propria.verificar') }}" id="mp-prop-verificar" style="margin-top:14px">@csrf
            <button class="botao botao--escuro botao--pequeno">Verificar de quem é o token</button>
            <small class="suave">Confere no Mercado Pago a conta dos tokens. O de produção válido ativa a aplicação da empresa.</small></form>
    @endif
    @endcan
</details>
@endif
@endif

@if ($v012 && $saldo)
<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <h2 style="margin-top:0">Extrato do recebimento pela plataforma</h2>
    <div class="grade grade-4" style="gap:12px">
        <div class="cartao kpi"><small>Vendas pagas a repassar</small><b>{{ moeda($saldo['vendas']) }}</b></div>
        <div class="cartao kpi"><small>Taxa da plataforma</small><b>{{ moeda($saldo['comissao']) }}</b></div>
        <div class="cartao kpi"><small>Liberado para repasse</small><b>{{ moeda($saldo['liberado']) }}</b><small>já pode ser transferido</small></div>
        <div class="cartao kpi"><small>Aguardando o fim do evento</small><b>{{ moeda($saldo['aguardando']) }}</b><small>+ {{ \App\Support\Recebimento::PRAZO_DIAS_UTEIS }} dias úteis</small></div>
    </div>
    <h3 style="margin:18px 0 8px;font-size:1rem">Repasses recebidos</h3>
    @if ($repasses->isEmpty())<p class="suave" style="margin:0">Nenhum repasse ainda.</p>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Data</th><th>Destino</th><th class="num">Vendas</th><th class="num">Taxa</th><th class="num">Recebido</th><th>Comprovante</th></tr></thead>
        <tbody>@foreach ($repasses as $r)
            <tr><td>{{ data_local($r->pago_em, 'd/m/Y') }}</td><td><small>{{ $r->destino }}</small></td><td class="num">{{ moeda($r->valor_vendas) }}</td>
                <td class="num">{{ moeda($r->valor_comissao) }}</td><td class="num"><b>{{ moeda($r->valor_repasse) }}</b></td><td><small>{{ $r->comprovante }}</small></td></tr>
        @endforeach</tbody>
    </table></div>
    @endif
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
