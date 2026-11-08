@extends('layouts.publico')
@section('titulo', 'Preços')
@section('descricao', 'Preços do Fora da Fila: o produtor escolhe se absorve a taxa ou se a repassa ao comprador. Criar evento é grátis e evento gratuito não tem taxa.')

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>Preço justo, e você escolhe quem paga<span class="ponto">.</span></h1>
    <p>O comprador paga só o valor do ingresso. O produtor paga uma comissão sobre as vendas.</p>
  </div>
</section>

<section class="pub-secao">
  <div class="container">
    <div class="pub-aviso" role="note">
      <div><b>Condições comerciais em validação.</b> A tabela completa das faixas e as regras de cobrança ainda estão sendo definidas
      e serão publicadas aqui antes de qualquer cobrança. Hoje a venda de ingressos pagos ainda não está disponível na plataforma,
      então nenhuma comissão é cobrada no momento.</div>
    </div>

    <div class="pub-grade pub-grade-2">
      <article class="pub-cartao">
        <span class="pub-selo">Para quem compra</span>
        <h3>Taxa embutida ou repassada</h3>
        <p>Em cada evento, você decide: <b>absorver a taxa</b> (o comprador paga só o preço do ingresso) ou <b>repassá-la ao comprador</b> (ele vê a taxa separada do preço e você recebe o valor cheio). Ingressos gratuitos não têm taxa.</p>
      </article>
      <article class="pub-cartao">
        <span class="pub-selo pub-selo--amarelo">Para o produtor</span>
        <h3>Taxa justa por ingresso pago</h3>
        <p>Criar conta e publicar evento é grátis, sem mensalidade. Cobramos só uma <b>taxa por ingresso pago</b>; ingressos gratuitos e cortesias não pagam nada.
        A tabela de valores será publicada em breve.</p>
      </article>
    </div>
  </div>
</section>

<section class="pub-secao pub-secao--creme">
  <div class="container">
    <h2>Como funciona</h2>
    <ul class="pub-texto">
      <li>Criar a conta, cadastrar e publicar eventos é grátis.</li>
      <li>Evento gratuito não tem taxa.</li>
      <li>Nos ingressos pagos há uma taxa por ingresso. Você escolhe se ela fica embutida no preço ou se é repassada ao comprador.</li>
      <li>O valor das vendas cai direto na sua conta no meio de pagamento.</li>
    </ul>
    <p style="margin-top:20px"><a href="{{ route('cadastro') }}" class="botao botao--amarelo">Criar conta de produtor</a>
       <a href="{{ route('ajuda') }}" class="botao botao--borda" style="margin-left:8px">Dúvidas frequentes</a></p>
  </div>
</section>
@endsection
