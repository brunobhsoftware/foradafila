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

<form method="post" action="{{ route('admin.mercadopago.salvar') }}" class="cartao" style="padding:20px;margin-top:18px" autocomplete="off">
    @csrf @method('PUT')
    <h2 style="margin-top:0">2. Informar as chaves</h2>
    <p class="suave">Os segredos são guardados criptografados e nunca aparecem de novo na tela (só os 4 últimos caracteres). Campo em branco mantém o valor atual.</p>
    <div class="form-grade">
        @foreach (['client_id' => ['MP_CLIENT_ID', 'Client ID', 'text'], 'client_secret' => ['MP_CLIENT_SECRET', 'Client Secret', 'password'], 'webhook_secret' => ['MP_WEBHOOK_SECRET', 'Assinatura secreta dos webhooks', 'password']] as $campo => [$env, $rotulo, $tipo])
            <div class="campo col-12">
                <label for="adm-{{ $campo }}">{{ $rotulo }} <small class="suave">({{ $env }})</small></label>
                <input id="adm-{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" autocomplete="off" spellcheck="false"
                    placeholder="{{ $credenciais[$campo]['definida'] ? 'Atual: '.$credenciais[$campo]['dica'].' — deixe em branco para manter' : 'Cole aqui' }}">
                @error($campo)<small class="erro">{{ $message }}</small>@enderror
            </div>
        @endforeach
    </div>
    <div class="acoes-form"><button class="botao botao--primario">Salvar chaves</button></div>
</form>

<form method="post" action="{{ route('admin.mercadopago.salvar') }}" class="cartao" style="padding:20px;margin-top:18px">
    @csrf @method('PUT')
    <input type="hidden" name="so_pkce" value="1">
    <h2 style="margin-top:0">3. Segurança da conexão (PKCE)</h2>
    <p class="suave">O PKCE é opcional no Mercado Pago. Ele precisa estar <b>igual nos dois lados</b>: se você ativar aqui, ative também na aplicação
        (Suas integrações → aplicação → Detalhes da aplicação → Editar → "fluxo de código de autorização com o PKCE"). Se estiver diferente,
        o Mercado Pago pode recusar a conexão.</p>
    <label class="interruptor"><input type="hidden" name="pkce" value="0"><input type="checkbox" name="pkce" value="1" @checked($pkce)><span></span> Usar PKCE</label>
    <div class="acoes-form"><button class="botao botao--escuro botao--pequeno">Salvar opção</button></div>
</form>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">4. Pagamento de teste</h2>
    <p class="suave">Cria uma cobrança <b>real</b> pelo Checkout Pro, na conta da própria aplicação (BH Software). Use para validar a integração e
        obter o ID do pagamento e do pedido (order) pedidos na "Qualidade da integração" do Mercado Pago. O valor cai na conta da BH Software
        e pode ser devolvido pelo próprio Mercado Pago.</p>
    <form method="post" action="{{ route('admin.mercadopago.teste') }}" class="teste-pagamento">
        @csrf
        <div class="campo"><label for="adm-valor">Valor (R$)</label>
            <input id="adm-valor" name="valor" type="number" step="0.01" min="1" max="100" value="{{ old('valor', '5.00') }}" required>
            @error('valor')<small class="erro">{{ $message }}</small>@enderror</div>
        <button class="botao botao--primario" @disabled(! $configurado)>Pagar teste no Mercado Pago ↗</button>
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
                    <td>@if ($a->assinatura_valida)<span class="selo selo--verde">Válida</span>@else<span class="selo selo--cinza">Inválida</span>@endif</td>
                    <td>{{ $a->status }}@if ($a->erro)<br><small>{{ $a->erro }}</small>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p><small class="suave">"Inválida" costuma indicar que a assinatura secreta cadastrada aqui não é a mesma da aplicação.</small></p>
    @endif
</section>
@endsection
