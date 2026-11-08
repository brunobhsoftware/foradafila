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
    <h2 style="margin-top:0">Criar a aplicação (feito uma vez)</h2>
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
    $ambientes = [
        'teste' => ['n' => 2, 'titulo' => 'Ambiente de TESTE', 'classe' => 'chaves--teste', 'selo' => ['selo--azul', 'Sem dinheiro real'],
            'ajuda' => 'No painel do Mercado Pago: Credenciais com o seletor em "Teste" e Webhooks no modo teste. Usado para validar a integração (Qualidade da integração).',
            'pagar' => 'Pagar teste no Mercado Pago', 'pagarAjuda' => 'Abra o link numa janela anônima, sem entrar em conta nenhuma, e pague com cartão de teste (titular APRO, CPF 12345678909).'],
        'producao' => ['n' => 3, 'titulo' => 'Ambiente de PRODUÇÃO', 'classe' => 'chaves--producao', 'selo' => ['selo--verde', 'Vendas reais'],
            'ajuda' => 'No painel do Mercado Pago: Credenciais com o seletor em "Produção" e Webhooks no modo produção. Usado no "Conectar Mercado Pago" dos produtores e nas vendas.',
            'pagar' => 'Pagar de verdade no Mercado Pago', 'pagarAjuda' => 'Cobrança real: pague com uma conta diferente da BH Software. O valor cai na conta da BH Software.'],
    ];
    $prefixo = ['teste' => 'teste_', 'producao' => ''];
    // Client ID, Client Secret e assinatura dos webhooks são da APLICAÇÃO (um só); cada ambiente tem só o Access Token.
    $nomesApp = ['client_id' => ['Client ID', 'text'], 'client_secret' => ['Client Secret', 'password']];
    $nomes = ['access_token' => ['Access Token', 'password'], 'webhook_secret' => ['Assinatura secreta dos webhooks', 'password']];
    $v = session('verificacao');
@endphp

<section class="cartao chaves" style="padding:20px;margin-top:18px" id="aplicacao">
    <div class="chaves__topo"><h2 style="margin:0">1. Aplicação</h2><span class="selo selo--cinza">Vale para teste e produção</span></div>
    <p class="suave">No Mercado Pago, Client ID e Client Secret ficam em <b>Credenciais de produção</b>. São um só para a aplicação.</p>
    <form method="post" action="{{ route('admin.mercadopago.salvar') }}" autocomplete="off">
        @csrf @method('PUT')
        <div class="form-grade">
            @foreach ($nomesApp as $campo => [$rotulo, $tipo])
                <div class="campo col-6">
                    <label for="adm-{{ $campo }}">{{ $rotulo }}</label>
                    <input id="adm-{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" autocomplete="off" spellcheck="false"
                        placeholder="{{ $credenciais[$campo]['definida'] ? 'Atual: '.$credenciais[$campo]['dica'].' — em branco mantém' : 'Cole aqui' }}">
                    @error($campo)<small class="erro">{{ $message }}</small>@enderror
                </div>
            @endforeach
        </div>
        <div class="acoes-form"><button class="botao botao--escuro">Salvar dados da aplicação</button></div>
    </form>
</section>

@foreach ($ambientes as $amb => $a)
<section class="cartao chaves {{ $a['classe'] }}" style="padding:20px;margin-top:18px" id="ambiente-{{ $amb }}">
    <div class="chaves__topo"><h2 style="margin:0">{{ $a['n'] }}. {{ $a['titulo'] }}</h2><span class="selo {{ $a['selo'][0] }}">{{ $a['selo'][1] }}</span></div>
    <p class="suave">{{ $a['ajuda'] }}</p>

    <form method="post" action="{{ route('admin.mercadopago.salvar') }}" autocomplete="off">
        @csrf @method('PUT')
        <h3 style="margin:6px 0 0">Chaves</h3>
        <p class="suave" style="margin-top:2px"><small>Os segredos ficam criptografados e só mostram os 4 últimos caracteres. Campo em branco mantém o valor atual.
            @if ($amb === 'producao') O Access Token de produção é opcional: sem ele, o token é gerado com o Client ID e o Client Secret da aplicação.@else Cole o Access Token de teste (TEST-… ou APP_USR- da conta de teste).@endif</small></p>
        <div class="form-grade">
            @foreach ($nomes as $base => [$rotulo, $tipo])
                @php $campo = $prefixo[$amb].$base; @endphp
                <div class="campo col-6">
                    <label for="adm-{{ $campo }}">{{ $rotulo }}{{ $amb === 'teste' ? ' de teste' : '' }}</label>
                    <input id="adm-{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" autocomplete="off" spellcheck="false"
                        placeholder="{{ $credenciais[$campo]['definida'] ? 'Atual: '.$credenciais[$campo]['dica'].' — em branco mantém' : 'Cole aqui' }}">
                    @error($campo)<small class="erro">{{ $message }}</small>@enderror
                </div>
            @endforeach
        </div>
        <div class="acoes-form"><button class="botao botao--primario">Salvar chaves de {{ $amb === 'teste' ? 'teste' : 'produção' }}</button></div>
    </form>

    <h3 style="margin:18px 0 6px">Conferir e pagar</h3>
    <div class="teste-pagamento">
        <form method="post" action="{{ route('admin.mercadopago.verificar') }}">@csrf
            <input type="hidden" name="ambiente" value="{{ $amb }}">
            <button class="botao botao--contorno botao--pequeno">Verificar de quem é o token</button></form>
        @if ($v && isset($v[$amb]))
            @php $i = $v[$amb]; @endphp
            <span class="verificacao">
                @if (! $i) <span class="suave">Token não cadastrado.</span>
                @elseif (isset($i['erro'])) <span class="erro">{{ $i['erro'] }}</span>
                @else Conta <b>{{ $i['id'] }}</b> · {{ $i['apelido'] }} <small>{{ $i['email'] }}</small>
                    @if ($i['teste'])<span class="selo selo--azul">Conta de teste</span>@else<span class="selo selo--cinza">Conta real</span>@endif
                @endif
            </span>
        @endif
    </div>
</section>
@endforeach

@php $o = fn ($k, $p = '') => old($k, $p); $ambTeste = old('ambiente', 'teste'); @endphp
<section class="cartao" style="padding:20px;margin-top:18px" id="pagamento-teste">
    <h2 style="margin-top:0">4. Pagamento de teste</h2>
    <form method="post" action="{{ route('admin.mercadopago.teste') }}" class="form-grade">
        @csrf
        <div class="campo col-12"><div class="grade grade-2" style="gap:12px">
            <label class="opcao-cartao"><input type="radio" name="ambiente" value="teste" @checked($ambTeste === 'teste')>
                <span><b>Teste (homologação)</b><small>{{ $ambientes['teste']['pagarAjuda'] }}</small></span></label>
            <label class="opcao-cartao"><input type="radio" name="ambiente" value="producao" @checked($ambTeste === 'producao')>
                <span><b>Produção (cobrança real)</b><small>{{ $ambientes['producao']['pagarAjuda'] }}</small></span></label>
        </div></div>
        <div class="campo col-4"><label>CPF</label><input name="cpf" required data-mascara="cpf" data-busca-cpf value="{{ $o('cpf', '123.456.789-09') }}">@error('cpf')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-8"><label>Nome completo</label><input name="nome" required data-pessoa="nome" value="{{ $o('nome', 'APRO Teste') }}">@error('nome')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-6"><label>E-mail do comprador</label><input name="email" type="email" required data-pessoa="email" value="{{ $o('email') }}"
            placeholder="no teste, o e-mail da conta compradora de teste">@error('email')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-3"><label>Telefone com DDD</label><input name="telefone" data-mascara="telefone" data-pessoa="telefone" value="{{ $o('telefone') }}">@error('telefone')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-3"><label for="adm-valor">Valor (R$)</label>
            <input id="adm-valor" name="valor" type="number" step="0.01" min="1" max="100" value="{{ $o('valor', '5.00') }}" required></div>
        <div class="col-12">@error('valor')<p class="erro" style="color:var(--vermelho);margin:0 0 8px">{{ $message }}</p>@enderror
            <button class="botao botao--primario">Gerar pagamento no Mercado Pago ↗</button>
            <small class="suave">Você recebe o link e o QR Code para abrir fora da conta logada.</small></div>
    </form>
</section>

<form method="post" action="{{ route('admin.mercadopago.salvar') }}" class="cartao" style="padding:20px;margin-top:18px">
    @csrf @method('PUT')
    <input type="hidden" name="so_pkce" value="1">
    <h2 style="margin-top:0">Segurança da conexão (PKCE)</h2>
    <p class="suave">O PKCE é opcional no Mercado Pago. Ele precisa estar <b>igual nos dois lados</b>: se você ativar aqui, ative também na aplicação
        (Suas integrações → aplicação → Detalhes da aplicação → Editar → "fluxo de código de autorização com o PKCE"). Se estiver diferente,
        o Mercado Pago pode recusar a conexão.</p>
    <label class="interruptor"><input type="hidden" name="pkce" value="0"><input type="checkbox" name="pkce" value="1" @checked($pkce)><span></span> Usar PKCE</label>
    <div class="acoes-form"><button class="botao botao--escuro botao--pequeno">Salvar opção</button></div>
</form>

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
