@extends('layouts.painel')
@section('titulo', 'Mercado Pago')

{{-- Autoajuda para abrir a conta no Mercado Pago (decisão do Bruno, 17/11/2026). Aberta pelo Financeiro → "Ainda não tenho — quero abrir". --}}
@section('conteudo')
<div class="cabecalho">
    <div><h1>Mercado Pago</h1><p>Como abrir a conta da sua empresa para receber as vendas dos ingressos.</p></div>
    <a href="{{ route('financeiro') }}" class="botao botao--contorno botao--pequeno">← Voltar ao Financeiro</a>
</div>

<section class="cartao" style="padding:22px 24px">
    <div class="mp-ajuda__topo">
        <img src="{{ asset('img/mercadopago-marca.svg') }}" alt="Mercado Pago" width="200" height="51">
        <div>
            <h2 style="margin:0 0 4px">Por que o Mercado Pago?</h2>
            <p class="suave" style="margin:0">É por ele que o Fora da Fila recebe os pagamentos dos seus compradores. A conta é gratuita e não tem mensalidade.</p>
        </div>
    </div>
    <div class="grade grade-3" style="gap:12px;margin-top:18px">
        <div class="cartao kpi"><small>Pix</small><b style="font-size:1.05rem">Aprovado na hora</b><small>o ingresso chega ao comprador em segundos</small></div>
        <div class="cartao kpi"><small>Cartão de crédito</small><b style="font-size:1.05rem">Em até 12x</b><small>o comprador escolhe as parcelas</small></div>
        <div class="cartao kpi"><small>O dinheiro</small><b style="font-size:1.05rem">Na sua conta</b><small>você acompanha tudo no app do Mercado Pago</small></div>
    </div>
</section>

<section class="cartao" style="padding:22px 24px;margin-top:18px">
    <h2 style="margin-top:0">Passo a passo</h2>
    <ol class="mp-passos">
        <li><b>Abra a conta no site do Mercado Pago</b>
            Clique no botão abaixo — ele abre o site do Mercado Pago em outra aba. Escolha <b>“Criar conta”</b>.
            <div style="margin-top:10px"><a href="https://www.mercadopago.com.br/" target="_blank" rel="noopener" class="botao botao--primario botao--pequeno">Clique aqui para abrir sua conta ↗</a></div></li>
        <li><b>Escolha conta para empresa (CNPJ)</b>
            Se a organização tem CNPJ, abra a conta no nome da empresa — o mesmo CNPJ cadastrado em Organização. Sem CNPJ, use a conta pessoal (CPF) do responsável.</li>
        <li><b>Confirme seus dados</b>
            Informe e-mail e celular, crie a senha e confirme a identidade pelo app do Mercado Pago (foto do documento e selfie). Normalmente leva poucos minutos.</li>
        <li><b>Volte aqui e conecte</b>
            Com a conta pronta, volte ao Financeiro, escolha <b>Configuração automática</b> e conecte a conta. Pronto: os ingressos pagos já podem ser vendidos.</li>
    </ol>
    <div class="alerta" style="margin-top:16px"><p style="margin:0"><b>Dica:</b> use um e-mail da empresa que você acessa sempre — o Mercado Pago avisa por ele cada pagamento recebido.
        O Fora da Fila nunca pede a senha da sua conta do Mercado Pago.</p></div>
    <div class="acoes-linha" style="justify-content:flex-start;margin-top:16px;gap:8px;flex-wrap:wrap">
        <a href="https://www.mercadopago.com.br/" target="_blank" rel="noopener" class="botao botao--primario">Abrir minha conta no Mercado Pago ↗</a>
        <a href="{{ route('financeiro') }}#mp-config" class="botao botao--contorno">Já criei minha conta — conectar</a>
    </div>
</section>
@endsection
