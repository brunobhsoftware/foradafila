@extends('layouts.painel')
@section('titulo', 'Financeiro')

@php
    $conectada = $conta && $conta->conectada();
    $contaAtiva = $conta && $conta->status === 'ativa';
    $painelMp = 'https://www.mercadopago.com.br/developers/panel/app';
    // Etapa: 1 = liberação, 2 = conectar, 3 = homologar/ativar, 4 = ativa
    $etapa = $contaAtiva ? 4 : ($conectada ? 3 : ($configurado ? 2 : 1));
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

<section class="cartao" id="repasse" style="padding:22px 24px;margin-top:18px;scroll-margin-top:80px">
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
            <div class="grade grade-2" style="gap:12px">
                <label class="opcao-cartao"><input type="radio" name="recebimento_preferido" value="pix" @checked($pref === 'pix')>
                    <span><b>Pix</b><small>O repasse cai na hora, na chave Pix cadastrada abaixo. É a forma mais rápida.</small></span></label>
                <label class="opcao-cartao"><input type="radio" name="recebimento_preferido" value="conta" @checked($pref === 'conta')>
                    <span><b>Transferência para conta bancária</b><small>TED para a conta da organização (banco, agência, conta e favorecido abaixo).</small></span></label>
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

@if (! $temPropria)
<section class="cartao" style="padding:18px 24px;margin-top:18px"><b>Mercado Pago da empresa</b>
    <p class="suave" style="margin:6px 0 0">Disponível depois que a plataforma atualizar o banco de dados (V012 completo).</p></section>
@else
@php
    $temManual = $propria && ($propria->client_id || $propria->access_token_cript || $propria->teste_access_token_cript);
    $mpConfigurado = $conectada || $propriaAtiva || $temManual;
    $mpAbrirDetalhes = request('mp') || $verificacao || $errors->hasAny(['client_id','client_secret','access_token','public_key','webhook_secret']);
    $modoMpInicial = request('mp') || $verificacao || $errors->hasAny(['client_id','client_secret','access_token','public_key','webhook_secret']) ? 'manual' : $mpConfigModo;
@endphp
{{-- "Você já tem conta no Mercado Pago?" (decisão do Bruno, 17/11/2026): some quando a conta já está configurada. --}}
@php $mostrarConfigMp = $mpConfigurado || in_array($mpEscolha, ['tenho', 'criar'], true); @endphp
@if (! $mpConfigurado && $mpEscolha !== 'plataforma')
<section class="cartao mp-ajuda" id="mp-escolha" style="padding:18px 24px;margin-top:18px;scroll-margin-top:80px">
    <div class="mp-ajuda__topo">
        <img src="{{ asset('img/mercadopago-marca.svg') }}" alt="Mercado Pago" width="160" height="41">
        <div><b style="font-size:1.05rem">Você já tem conta no Mercado Pago?</b>
            <small class="suave" style="display:block">Com a sua conta, o dinheiro cai na hora. Sem ela, recebemos por você e repassamos depois do evento.</small></div>
    </div>
    <form method="post" action="{{ route('financeiro.mp.escolha') }}" class="grade grade-3" style="gap:12px;margin-top:14px">@csrf
        <button name="escolha" value="tenho" class="opcao-cartao mp-ajuda__opcao {{ $mpEscolha === 'tenho' ? 'mp-ajuda__opcao--ativa' : '' }}">
            <span><b>✔ Sim, já tenho conta</b><small>Libera a configuração logo abaixo para conectar a conta da empresa.</small></span></button>
        <button name="escolha" value="criar" class="opcao-cartao mp-ajuda__opcao {{ $mpEscolha === 'criar' ? 'mp-ajuda__opcao--ativa' : '' }}">
            <span><b>Ainda não tenho — quero abrir</b><small>Passo a passo e link direto para abrir a conta; depois é só conectar.</small></span></button>
        <button name="escolha" value="plataforma" class="opcao-cartao mp-ajuda__opcao">
            <span><b>Não quero — usar a conta do Fora da Fila</b><small>Recebemos as vendas por você e repassamos por Pix ou conta bancária em até {{ \App\Support\Recebimento::PRAZO_DIAS_UTEIS }} dias úteis após o evento.</small></span></button>
    </form>
</section>
@elseif (! $mpConfigurado && $mpEscolha === 'plataforma')
<section class="cartao" style="padding:16px 24px;margin-top:18px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <span><b>Você recebe pela conta do Fora da Fila</b><br><small class="suave">As vendas são repassadas para o Pix ou a conta bancária de “Dados para o repasse”.</small></span>
    <form method="post" action="{{ route('financeiro.mp.escolha') }}">@csrf
        <button name="escolha" value="refazer" class="botao botao--contorno botao--pequeno">Quero usar minha conta do Mercado Pago</button></form>
</section>
@endif
@if ($mostrarConfigMp)
<details class="cartao" id="mp-config" style="padding:18px 24px;margin-top:18px;scroll-margin-top:80px" open>
    <summary style="cursor:pointer"><b style="font-size:1.1rem">Mercado Pago da empresa</b>
        @if ($contaAtiva)<span class="selo selo--verde" style="margin-left:8px">Ativa{{ $conta->email_conta ? ' — '.$conta->email_conta : '' }}</span>
        @elseif ($conectada)<span class="selo" style="margin-left:8px">Conectada{{ $conta->email_conta ? ' — '.$conta->email_conta : '' }}</span>
        @elseif ($propriaAtiva)<span class="selo selo--verde" style="margin-left:8px">Ativa (manual){{ $propria->email_conta ? ' — '.$propria->email_conta : '' }}</span>
        @elseif ($propria && $propria->ultimo_erro)<span class="selo selo--vermelho" style="margin-left:8px">Token recusado</span>
        @else<span class="selo selo--cinza" style="margin-left:8px">Não configurada</span>@endif</summary>

    <p class="suave" style="margin:10px 0 14px">Para receber as vendas direto na conta da empresa no Mercado Pago. Escolha como configurar:</p>

    <form method="post" action="{{ route('financeiro.mp.config') }}" id="form-mp-config">@csrf @method('PUT')
    <div class="grade grade-2" style="gap:12px;margin-bottom:12px">
        <label class="opcao-cartao"><input type="radio" name="mp_config_modo" value="automatica" data-mp-config @checked($modoMpInicial === 'automatica')>
            <span><b>Configuração automática</b><small>Conecte a conta do Mercado Pago com um clique. O Fora da Fila cuida das chaves e dos webhooks automaticamente.</small></span></label>
        <label class="opcao-cartao"><input type="radio" name="mp_config_modo" value="manual" data-mp-config @checked($modoMpInicial === 'manual')>
            <span><b>Configuração manual</b><small>Crie a aplicação no painel de desenvolvedores do Mercado Pago e cole as chaves aqui. Para quem quer controle total.</small></span></label>
    </div>
    <div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="mp-detalhes" aria-expanded="{{ $mpAbrirDetalhes ? 'true' : 'false' }}">Exibir</button>
        @can('financeiro.contas')<button class="botao botao--escuro botao--pequeno">Salvar preferência</button>@endcan
    </div>
    </form>

    {{-- Detalhes só ao clicar em Exibir; sem nada configurado, escolher uma opção já abre (decisão do Bruno, 16/11/2026) --}}
    <div id="mp-detalhes" @unless ($mpAbrirDetalhes) hidden @endunless @unless ($mpConfigurado) data-mp-abrir-ao-escolher @endunless>

    {{-- === AUTOMÁTICA: OAuth === --}}
    <div id="mp-config-automatica" @if ($modoMpInicial !== 'automatica') hidden @endif>
        <ol class="mp__etapas" aria-label="Etapas da integração" style="margin-bottom:16px">
            @foreach ([1 => 'Liberação', 2 => 'Conectar a conta', 3 => 'Homologar e ativar'] as $n => $t)
                <li class="{{ $n < $etapa ? 'feito' : ($n === $etapa ? 'atual' : '') }}" @if ($n === $etapa) aria-current="step" @endif>
                    <span>{{ $n < $etapa ? '✓' : $n }}</span>{{ $t }}
                </li>
            @endforeach
        </ol>

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

        <details class="mp__bloco" @if ($etapa >= 3) open @endif>
            <summary><b>3. Homologar e ativar</b>
                <small>{{ $contaAtiva ? 'Concluído: a conta está ativa.' : 'Verifique a conexão e ative a conta para vender.' }}</small></summary>
            <div class="mp__conteudo">
                @if ($contaAtiva)
                    <div class="alerta alerta--ok"><p>A conta do Mercado Pago está <b>ativa</b>. Os eventos com ingresso pago já podem ser publicados.</p></div>
                @elseif ($conectada)
                    <p class="suave">A conta está conectada. Clique em <b>Ativar conta</b> para verificar a conexão e liberar as vendas.</p>
                    @can('financeiro.contas')
                        <form method="post" action="{{ route('financeiro.mp.ativar') }}" style="margin-top:10px">
                            @csrf <button class="botao botao--primario">Ativar conta</button>
                        </form>
                    @endcan
                @else
                    <p class="suave">Conecte a conta na etapa 2 primeiro.</p>
                @endif
            </div>
        </details>
    </div>

    {{-- === MANUAL: Client ID, tokens, webhooks === --}}
    <div id="mp-config-manual" @if ($modoMpInicial !== 'manual') hidden @endif>
        <p class="suave" style="margin:0 0 10px">Crie uma aplicação no
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
        <form method="post" action="{{ route('financeiro.mp.propria', 'aplicacao') }}" class="form-grade" style="margin-top:16px;padding:14px 16px;background:#F4F5F7;border-left:3px solid var(--tinta);border-radius:6px">
            @csrf @method('PUT')
            <h3 class="col-12" style="margin:0">1. Aplicação <small class="suave" style="font-weight:400">— vale para teste e produção</small></h3>
            <p class="col-12 suave" style="margin:0"><small>No Mercado Pago, Client ID e Client Secret ficam em <b>Credenciais de produção</b>. São um só para a aplicação.</small></p>
            <x-campo nome="client_id" rotulo="Client ID" :valor="$propria?->client_id" classe="col-6" autocomplete="off" />
            <x-campo nome="client_secret" rotulo="Client Secret" tipo="password" classe="col-6" autocomplete="new-password" :placeholder="$propria?->client_secret_cript ? 'salvo — em branco mantém' : ''" />
            <div class="col-12"><button class="botao botao--escuro botao--pequeno">Salvar dados da aplicação</button></div>
        </form>
        @foreach (['teste' => '2. Ambiente de TESTE', 'producao' => '3. Ambiente de PRODUÇÃO'] as $amb => $titulo)
            @php
                $pre = $amb === 'teste' ? 'teste_' : '';
                $tem = fn ($campo) => $propria && $propria->{$campo};
            @endphp
            <form method="post" action="{{ route('financeiro.mp.propria', $amb) }}" class="form-grade" style="margin-top:16px;padding:14px 16px;background:#F4F5F7;border-left:3px solid var(--laranja);border-radius:6px">
                @csrf @method('PUT')
                <h3 class="col-12" style="margin:0">{{ $titulo }} <small class="suave" style="font-weight:400">— Access Token, Public Key e assinatura dos webhooks {{ $amb === 'teste' ? 'de teste' : 'de produção' }}</small></h3>
                <x-campo nome="access_token" rotulo="Access Token" tipo="password" classe="col-6" autocomplete="new-password" :placeholder="$tem($amb === 'teste' ? 'teste_access_token_cript' : 'access_token_cript') ? 'salvo — em branco mantém' : 'APP_USR-...'" />
                <x-campo nome="public_key" rotulo="Public Key" :valor="$propria?->{$amb === 'teste' ? 'teste_public_key' : 'public_key'}" classe="col-6" autocomplete="off" />
                <x-campo nome="webhook_secret" rotulo="Assinatura secreta dos webhooks ({{ $amb === 'teste' ? 'modo teste' : 'modo produção' }})" tipo="password" classe="col-12" autocomplete="new-password" :placeholder="$tem($pre.'webhook_secret_cript') ? 'salva — em branco mantém' : ''" />
                <div class="col-12" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <button class="botao {{ $amb === 'teste' ? 'botao--contorno' : 'botao--primario' }} botao--pequeno">Salvar chaves {{ $amb === 'teste' ? 'de teste' : 'de produção' }}</button>
                    <button type="submit" form="mp-prop-verificar" class="botao botao--escuro botao--pequeno">Verificar de quem é o token</button>
                    @if ($verificacao && ($verificacao[$amb] ?? null))
                        <small>@if ($verificacao[$amb]['ok'])✔ {{ $verificacao[$amb]['email'] ?? '—' }} ({{ $verificacao[$amb]['apelido'] ?? '—' }}) — {{ $verificacao[$amb]['teste'] ? 'conta de teste' : 'conta real' }}@else<b style="color:var(--vermelho)">✖ token recusado pelo Mercado Pago</b>@endif</small>
                    @endif
                </div>
            </form>
        @endforeach
        @php $tokTeste = $propria?->teste_access_token_cript; $tokProd = $propria?->access_token_cript; $ambTeste = old('ambiente', $tokTeste ? 'teste' : 'producao'); @endphp
        <form method="post" action="{{ route('financeiro.mp.propria.teste') }}" class="form-grade" style="margin-top:16px;padding:14px 16px;border:1px dashed var(--borda-forte);border-radius:6px">
            @csrf
            <h3 class="col-12" style="margin:0">4. Pagamento de teste</h3>
            @error('pagamento_teste')<p class="col-12 erro" style="color:var(--vermelho);margin:0">{{ $message }}</p>@enderror
            <div class="campo col-12"><div class="grade grade-2" style="gap:12px">
                <label class="opcao-cartao"><input type="radio" name="ambiente" value="teste" @checked($ambTeste === 'teste') @disabled(! $tokTeste)>
                    <span><b>Teste (homologação)</b><small>Usa o Access Token de teste. Pague com a conta compradora de teste; nenhum dinheiro real é movimentado.@unless ($tokTeste)<br><b style="font-size:.8rem">Salve o Access Token de teste antes.</b>@endunless</small></span></label>
                <label class="opcao-cartao"><input type="radio" name="ambiente" value="producao" @checked($ambTeste === 'producao') @disabled(! $tokProd)>
                    <span><b>Produção (cobrança real)</b><small>Usa o Access Token de produção: o valor cai de verdade na conta da empresa. Pague com outra conta do Mercado Pago.@unless ($tokProd)<br><b style="font-size:.8rem">Salve o Access Token de produção antes.</b>@endunless</small></span></label>
            </div></div>
            <div class="campo col-4"><label>CPF de quem paga</label><input name="cpf" required data-mascara="cpf" data-busca-cpf inputmode="numeric" value="{{ old('cpf') }}"></div>
            <div class="campo col-8"><label>Nome completo</label><input name="nome" required data-pessoa="nome" value="{{ old('nome') }}"></div>
            <div class="campo col-6"><label>E-mail de quem paga</label><input name="email" type="email" required data-pessoa="email" value="{{ old('email') }}" placeholder="no teste, o e-mail da conta compradora de teste"></div>
            <div class="campo col-3"><label>Telefone com DDD</label><input name="telefone" data-mascara="telefone" data-pessoa="telefone" value="{{ old('telefone') }}"></div>
            <div class="campo col-3"><label>Valor (R$)</label><input name="valor" type="number" step="0.01" min="1" max="100" value="{{ old('valor', '5.00') }}" required></div>
            <div class="col-12"><button class="botao botao--escuro botao--pequeno" @disabled(! $tokTeste && ! $tokProd)>Gerar pagamento no Mercado Pago ↗</button>
                <small class="suave">Abra o link em janela anônima ou no celular, fora da conta da empresa.</small></div>
        </form>
        @if ($propria)
            <form method="post" action="{{ route('financeiro.mp.propria.verificar') }}" id="mp-prop-verificar" style="margin-top:14px">@csrf
                <button class="botao botao--escuro botao--pequeno">Verificar de quem é o token</button>
                <small class="suave">Confere no Mercado Pago a conta dos tokens. O de produção válido ativa a aplicação da empresa.</small></form>
        @endif
        @endcan
    </div>
    </div>
</details>
@endif
@endif

@php $mpAtiva = $propriaAtiva || $conectada; $ocultarPorOauth = $contaAtiva && $mpConfigModo === 'automatica'; @endphp
@if ($mpAtiva && ! $ocultarPorOauth)
<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <h2 style="margin-top:0">Ambiente das vendas</h2>
    <form method="post" action="{{ route('financeiro.ambiente') }}">@csrf @method('PUT')
        <div class="grade grade-2" style="gap:12px">
            <label class="opcao-cartao"><input type="radio" name="ambiente_vendas" value="producao" @checked(! $homologacao)>
                <span><b>Produção</b><small>Vendas reais: o comprador paga de verdade (Pix ou cartão) e o dinheiro entra na conta de recebimento.</small></span></label>
            <label class="opcao-cartao"><input type="radio" name="ambiente_vendas" value="homologacao" @checked($homologacao)>
                <span><b>Homologação (teste)</b><small>Para testar o caminho todo: comprar, receber o ingresso, fazer o check-in e cancelar com devolução. Usa as credenciais de teste; pague com a conta compradora de teste. Nada é cobrado. A página do evento mostra o aviso de teste.</small></span></label>
        </div>
        @can('financeiro.contas')<div style="margin-top:12px"><button class="botao botao--escuro botao--pequeno">Salvar ambiente</button></div>@endcan
    </form>
</section>

<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <h2 style="margin-top:0">Onde as vendas são recebidas</h2>
    @error('recebimento_modo')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror
    <form method="post" action="{{ route('financeiro.modo') }}" class="pilha">@csrf @method('PUT')
        <div class="grade grade-2" style="gap:12px">
        <label class="opcao-cartao"><input type="radio" name="recebimento_modo" value="plataforma" @checked($modoAtual === 'plataforma')>
            <span><b>Pela plataforma, com repasse</b><small>O comprador paga na conta do Fora da Fila e você recebe por Pix ou transferência em até {{ \App\Support\Recebimento::PRAZO_DIAS_UTEIS }} dias úteis após o evento, já com a taxa descontada. Nada para configurar além dos dados para o repasse.</small></span></label>
        <label class="opcao-cartao"><input type="radio" name="recebimento_modo" value="propria" @checked($modoAtual === 'propria') @disabled(! $mpAtiva)>
            <span><b>Direto na conta do Mercado Pago da empresa</b><small>Cada venda cai na hora na sua conta, pela conexão automática ou aplicação manual configurada acima. A taxa da plataforma é cobrada à parte, em fatura.
            @unless ($mpAtiva)<br><b style="font-size:.8rem">Disponível depois de conectar ou configurar o Mercado Pago acima.</b>@endunless</small></span></label>
        </div>
        @can('financeiro.contas')<div><button class="botao botao--escuro botao--pequeno">Salvar forma de recebimento</button></div>@endcan
    </form>
</section>
@elseif ($ocultarPorOauth)
<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <h2 style="margin-top:0">Recebimento das vendas</h2>
    <p>As vendas caem direto na conta do Mercado Pago da empresa (<b>{{ $conta->email_conta }}</b>).
        A comissão da plataforma é retida automaticamente em cada venda.</p>
    <p class="suave" style="margin:8px 0 0;font-size:13px">Conta {{ $conta->conta_externa_id }} · Ambiente de produção</p>
</section>
@endif
@endif


@endsection
