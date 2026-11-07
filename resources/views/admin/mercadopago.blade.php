@extends('layouts.admin')
@section('titulo', 'Mercado Pago')

@php
    $retorno = \App\Pagamentos\MercadoPago::retornoUrl();
    $webhook = url(config('pagamentos.mercadopago.webhook'));
    $prompt = "Quero integrar o Fora da Fila (plataforma de venda de ingressos, foradafila.com.br) com o Mercado Pago. "
        ."Crie na minha conta uma aplicação chamada \"Fora da Fila\" para pagamentos on-line no modelo marketplace, "
        ."em que cada organizador de eventos conecta a própria conta por OAuth e recebe o dinheiro das vendas direto na conta dele. "
        ."Configure: (1) URL de redirecionamento do OAuth: {$retorno} ; (2) Webhooks em {$webhook} com o evento Pagamentos (payment), e gere a assinatura secreta. "
        ."No final, me mostre só estes três valores, cada um em uma linha, sem nenhum outro texto entre eles:\n"
        ."MP_CLIENT_ID=...\nMP_CLIENT_SECRET=...\nMP_WEBHOOK_SECRET=...";
@endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>Mercado Pago</h1><p>Aplicação única da BH Software. Cada produtor só clica em "Conectar" e o dinheiro das vendas cai direto na conta dele.</p></div>
    <div>@if ($configurado)<span class="selo selo--verde">Configurada</span>@else<span class="selo selo--amarelo">Pendente</span>@endif</div>
</div>

<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">1. Criar a aplicação</h2>
    <div class="mp__ia">
        <div class="mp__ia-topo"><span class="selo selo--verde">Automático com IA</span><b>Em 2 passos</b></div>
        <div class="mp__ia-passo">
            <b>Conecte seu agente de IA ao Mercado Pago</b>
            <small>No Claude Code (terminal):</small>
            <div class="copiar"><input id="adm-plugin" value="claude plugin install mercadopago" readonly class="mono"><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="adm-plugin">Copiar</button></div>
        </div>
        <div class="mp__ia-passo">
            <b>Cole este prompt: ele cria a aplicação e devolve os três valores</b>
            <div class="copiar"><textarea id="adm-prompt" readonly rows="9">{{ $prompt }}</textarea><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="adm-prompt">Copiar</button></div>
            <small>Copie os três valores que a IA mostrar e cole nos campos abaixo. Não envie esses valores por chat ou e-mail. Ajuda: <a href="https://www.mercadopago.com.br/developers/pt/docs/ai-resources" target="_blank" rel="noopener">guia de uso</a>.</small>
        </div>
    </div>
    <p class="mp__ou" style="margin:16px 0"><span>ou manualmente</span></p>
    <div class="mp__campos">
        <div class="campo"><label for="adm-retorno">URL de redirecionamento (OAuth) <small class="suave">— campo "URLs de redirecionamento"</small></label>
            <div class="copiar"><input id="adm-retorno" value="{{ $retorno }}" readonly><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="adm-retorno">Copiar</button></div></div>
        <div class="campo"><label for="adm-webhook">Webhooks (evento Pagamentos) <small class="suave">— "Configuração de Webhooks"</small></label>
            <div class="copiar"><input id="adm-webhook" value="{{ $webhook }}" readonly><button type="button" class="botao botao--contorno botao--pequeno" data-copiar="adm-webhook">Copiar</button></div></div>
    </div>
    <p style="margin-top:14px"><a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener" class="botao botao--escuro botao--pequeno">Abrir painel de desenvolvedores do Mercado Pago ↗</a></p>
</section>

@php
    $blocos = [
        'producao' => ['titulo' => '2. Chaves de PRODUÇÃO', 'classe' => 'chaves--producao', 'selo' => ['selo--verde', 'Vendas reais'],
            'ajuda' => 'No painel do Mercado Pago: Credenciais com o seletor em "Produção" e Webhooks no modo produção. Usadas no "Conectar Mercado Pago" dos produtores e nas vendas.',
            'campos' => ['client_id' => ['Client ID', 'text'], 'client_secret' => ['Client Secret', 'password'], 'webhook_secret' => ['Assinatura secreta dos webhooks', 'password']]],
        'teste' => ['titulo' => '3. Chaves de TESTE', 'classe' => 'chaves--teste', 'selo' => ['selo--azul', 'Sem dinheiro real'],
            'ajuda' => 'No painel do Mercado Pago: Credenciais com o seletor em "Teste" e Webhooks no modo teste. Usadas só no pagamento de teste (Qualidade da integração).',
            'campos' => ['teste_client_id' => ['Client ID de teste', 'text'], 'teste_client_secret' => ['Client Secret de teste', 'password'],
                'teste_access_token' => ['Access Token de teste (opcional se informar o Client ID/Secret de teste)', 'password'], 'teste_webhook_secret' => ['Assinatura secreta dos webhooks de teste (se for diferente da de produção)', 'password']]],
    ];
@endphp
@foreach ($blocos as $bloco)
<form method="post" action="{{ route('admin.mercadopago.salvar') }}" class="cartao chaves {{ $bloco['classe'] }}" style="padding:20px;margin-top:18px" autocomplete="off">
    @csrf @method('PUT')
    <div class="chaves__topo"><h2 style="margin:0">{{ $bloco['titulo'] }}</h2><span class="selo {{ $bloco['selo'][0] }}">{{ $bloco['selo'][1] }}</span></div>
    <p class="suave">{{ $bloco['ajuda'] }} Os segredos ficam criptografados e só mostram os 4 últimos caracteres. Campo em branco mantém o valor atual.</p>
    <div class="form-grade">
        @foreach ($bloco['campos'] as $campo => [$rotulo, $tipo])
            <div class="campo col-12">
                <label for="adm-{{ $campo }}">{{ $rotulo }}</label>
                <input id="adm-{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" autocomplete="off" spellcheck="false"
                    placeholder="{{ $credenciais[$campo]['definida'] ? 'Atual: '.$credenciais[$campo]['dica'].' — deixe em branco para manter' : 'Cole aqui' }}">
                @error($campo)<small class="erro">{{ $message }}</small>@enderror
            </div>
        @endforeach
    </div>
    <div class="acoes-form"><button class="botao botao--primario">Salvar chaves de {{ $loop->first ? 'produção' : 'teste' }}</button></div>
</form>
@endforeach

<form method="post" action="{{ route('admin.mercadopago.salvar') }}" class="cartao" style="padding:20px;margin-top:18px">
    @csrf @method('PUT')
    <input type="hidden" name="so_pkce" value="1">
    <h2 style="margin-top:0">4. Segurança da conexão (PKCE)</h2>
    <p class="suave">O PKCE é opcional no Mercado Pago. Ele precisa estar <b>igual nos dois lados</b>: se você ativar aqui, ative também na aplicação
        (Suas integrações → aplicação → Detalhes da aplicação → Editar → "fluxo de código de autorização com o PKCE"). Se estiver diferente,
        o Mercado Pago pode recusar a conexão.</p>
    <label class="interruptor"><input type="hidden" name="pkce" value="0"><input type="checkbox" name="pkce" value="1" @checked($pkce)><span></span> Usar PKCE</label>
    <div class="acoes-form"><button class="botao botao--escuro botao--pequeno">Salvar opção</button></div>
</form>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">5. Pagamento de teste</h2>
    <p class="suave">Cria uma cobrança pelo Checkout Pro na conta da própria aplicação (BH Software) e devolve o ID do pagamento e do pedido (order).
        <b>Teste</b> usa o Access Token de teste: pague entrando com a <b>conta de teste compradora</b> (Contas de teste, no painel) ou com os cartões de teste —
        é o que a "Qualidade da integração" pede. <b>Produção</b> cobra de verdade.</p>
    <form method="post" action="{{ route('admin.mercadopago.verificar') }}" style="margin-bottom:14px">
        @csrf <button class="botao botao--contorno botao--pequeno">Verificar de quem são os tokens</button>
        <small class="suave">Para o teste funcionar, o token de <b>teste</b> precisa ser de uma conta de teste (ou da credencial de teste da aplicação), nunca o de produção.</small>
    </form>
    @if ($v = session('verificacao'))
        <div class="tabela-rolagem" style="margin-bottom:16px"><table class="tabela">
            <thead><tr><th>Token</th><th>Conta (User ID)</th><th>Apelido / e-mail</th><th>Tipo de conta</th></tr></thead>
            <tbody>
            @foreach (['producao' => 'Produção (Client ID/Secret)', 'teste' => 'Access Token de TESTE'] as $k => $rot)
                @php $i = $v[$k]; @endphp
                <tr><td>{{ $rot }}</td>
                    @if (! $i)<td colspan="3" class="suave">não cadastrado</td>
                    @elseif (isset($i['erro']))<td colspan="3">{{ $i['erro'] }}</td>
                    @else
                        <td>{{ $i['id'] }}</td><td>{{ $i['apelido'] }}<br><small>{{ $i['email'] }}</small></td>
                        <td>@if ($i['teste'])<span class="selo selo--azul">Conta de teste</span>@else<span class="selo selo--cinza">Conta real</span>@endif</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @if (($v['producao']['id'] ?? 1) === ($v['teste']['id'] ?? 2))
            <p class="alerta alerta--info" style="padding:10px 12px">Os dois tokens são <b>da mesma conta</b>. Se o "Access Token de TESTE" for o de produção, o pagamento com cartão de teste dá
                "uma das partes é de teste". Copie o Access Token com o seletor <b>Teste</b> em Credenciais, ou o de produção de uma <b>conta vendedora de teste</b>.</p>
        @endif
    @endif
    <form method="post" action="{{ route('admin.mercadopago.teste') }}" class="teste-pagamento">
        @csrf
        <div class="campo"><label>Ambiente</label>
            <div class="segmentado">
                <label><input type="radio" name="ambiente" value="teste" @checked(old('ambiente', 'teste') === 'teste')><span>Teste</span></label>
                <label><input type="radio" name="ambiente" value="producao" @checked(old('ambiente') === 'producao')><span>Produção</span></label>
            </div></div>
        <div class="campo"><label for="adm-valor">Valor (R$)</label>
            <input id="adm-valor" name="valor" type="number" step="0.01" min="1" max="100" value="{{ old('valor', '5.00') }}" required>
            @error('valor')<small class="erro">{{ $message }}</small>@enderror</div>
        <button class="botao botao--primario">Pagar teste no Mercado Pago ↗</button>
    </form>
</section>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Últimos avisos recebidos (webhooks)</h2>
    @if ($avisos->isEmpty())
        <p class="suave">Nenhum aviso recebido ainda. Depois do pagamento de teste, o Mercado Pago deve mandar um aviso para cá.</p>
    @else
        <div class="tabela-rolagem"><table class="tabela">
            <thead><tr><th>Recebido</th><th>Tipo / ação</th><th>ID do recurso</th><th>Assinatura</th><th>Situação</th></tr></thead>
            <tbody>
            @foreach ($avisos as $a)
                <tr>
                    <td>{{ data_local($a->recebido_em) }}</td>
                    <td>{{ $a->tipo ?? '—' }}@if ($a->acao)<br><small>{{ $a->acao }}</small>@endif</td>
                    <td>{{ $a->recurso_id ?? '—' }}</td>
                    <td>@if ($a->assinatura_valida)<span class="selo selo--verde">Válida{{ $a->erro === 'Assinatura de teste' ? ' (teste)' : '' }}</span>@else<span class="selo selo--cinza">Inválida</span>@endif</td>
                    <td>{{ $a->status }}@if ($a->erro && ! $a->assinatura_valida)<br><small>{{ $a->erro }}</small>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p><small class="suave">"Inválida" costuma indicar que a assinatura secreta cadastrada aqui não é a mesma da aplicação.</small></p>
    @endif
</section>
@endsection
