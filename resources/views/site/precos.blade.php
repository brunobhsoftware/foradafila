@extends('layouts.publico')
@section('titulo', 'Preços')
@section('descricao', 'Preços do Fora da Fila: sem taxa para o comprador. Para o produtor, 1% no primeiro evento, com percentual progressivo por faixa de vendas.')

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>Preço justo, sem taxa para quem compra<span class="ponto">.</span></h1>
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
        <h3>Sem taxa para o comprador</h3>
        <p>O valor pago é o preço do ingresso definido pelo organizador. Não há taxa de conveniência ou de serviço cobrada do público.</p>
      </article>
      <article class="pub-cartao">
        <span class="pub-selo pub-selo--amarelo">Para o produtor</span>
        <h3>1% no primeiro evento</h3>
        <p>No primeiro evento do produtor, a comissão é de <b>1% sobre o valor das vendas de ingressos</b>.
        O percentual aumenta de forma progressiva <b>a cada R$ 1.000 em vendas</b>.</p>
      </article>
    </div>
  </div>
</section>

<section class="pub-secao pub-secao--creme">
  <div class="container">
    <h2>Faixas de comissão</h2>
    <div style="overflow-x:auto">
      <table class="pub-tabela">
        <thead><tr><th>Vendas do evento</th><th>Comissão</th></tr></thead>
        <tbody>
          <tr><td>Primeiro evento do produtor</td><td>1% sobre o valor das vendas de ingressos</td></tr>
          <tr><td>A cada R$ 1.000 em vendas</td><td>O percentual aumenta progressivamente. <span class="pub-selo pub-selo--breve">tabela em definição</span></td></tr>
        </tbody>
      </table>
    </div>

    <h3 style="margin:28px 0 10px">O que ainda será confirmado e publicado</h3>
    <ul class="pub-texto">
      <li>Os percentuais de cada faixa de R$ 1.000.</li>
      <li>Se a regra vale por evento, por produtor ou por período, e como se define o “primeiro evento”.</li>
      <li>A base de cálculo exata (valor de venda dos ingressos, com ou sem descontos).</li>
      <li>Como e quando a comissão é cobrada: retida pelo meio de pagamento na própria venda ou cobrada em fatura mensal.</li>
      <li>As condições a partir do segundo evento e para ingressos gratuitos e cortesias.</li>
    </ul>
    <p style="margin-top:20px"><a href="{{ route('cadastro') }}" class="botao botao--amarelo">Criar conta de produtor</a>
       <a href="{{ route('ajuda') }}" class="botao botao--borda" style="margin-left:8px">Dúvidas frequentes</a></p>
  </div>
</section>
@endsection
