@extends('layouts.publico')
@section('titulo', 'Solução')
@section('descricao', 'Ferramentas do Fora da Fila para produtores: cadastro de eventos, ingressos e lotes, equipe com níveis de acesso e ingresso digital com QR Code.')

@php
    // Somente o que existe hoje no sistema aparece como "Disponível".
    $disponivel = [
        ['icone' => 'evento', 'titulo' => 'Cadastro e gestão de eventos', 'texto' => 'Crie o evento como rascunho, defina data, local, capacidade e regras do ingresso, e publique quando estiver pronto. Uma lista mostra o que falta antes da publicação.'],
        ['icone' => 'ingresso', 'titulo' => 'Tipos de ingresso e lotes', 'texto' => 'Setores como Pista e VIP, cada um com lotes de preço, quantidade e período de venda próprios. A soma dos lotes nunca passa da capacidade do evento.'],
        ['icone' => 'pagina', 'titulo' => 'Página do evento', 'texto' => 'Cada evento publicado ganha uma página com as informações, os ingressos disponíveis e a identificação do organizador responsável.'],
        ['icone' => 'qr', 'titulo' => 'Ingresso digital com QR Code', 'texto' => 'Em eventos gratuitos, o público garante o ingresso na página do evento e acessa em “Meus ingressos”, com segunda via para imprimir.'],
        ['icone' => 'equipe', 'titulo' => 'Equipe e níveis de acesso', 'texto' => 'Convide gerente, financeiro, portaria e segurança. Seis perfis prontos e a opção de criar perfis próprios ou limitar alguém a eventos específicos.'],
        ['icone' => 'grafico', 'titulo' => 'Painel do produtor', 'texto' => 'Acompanhe eventos publicados, ingressos emitidos, ocupação e próximos eventos em “Meus eventos”.'],
    ];
    $emBreve = [
        'Venda de ingressos pagos, com o dinheiro caindo direto na conta do organizador (Mercado Pago primeiro)',
        'Leitura do QR Code na portaria pelo celular, inclusive sem internet',
        'Cupons de desconto e links de promoters',
        'Listas de convidados e links de acesso privado',
        'E-mails de confirmação e lembretes do evento',
        'Lista de participantes com filtros e exportação',
        'Financeiro: recebimentos, comissão e faturas',
    ];
    $icones = [
        'evento' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'ingresso' => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/>',
        'pagina' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17.5 17.5 21 21"/>',
        'equipe' => '<circle cx="12" cy="7" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/><circle cx="4.5" cy="10" r="2"/><circle cx="19.5" cy="10" r="2"/>',
        'grafico' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];
@endphp

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>Tudo para publicar, vender e organizar o seu evento<span class="ponto">.</span></h1>
    <p>O Fora da Fila é uma plataforma de tecnologia para produtores de eventos: você cadastra o evento, define os ingressos e a sua equipe, e o público garante o ingresso pela internet.</p>
  </div>
</section>

<section class="pub-secao">
  <div class="container">
    <h2>Disponível hoje</h2>
    <p class="pub-intro">Estas ferramentas já funcionam na área do produtor.</p>
    <div class="pub-grade pub-grade-3">
      @foreach ($disponivel as $item)
        <article class="pub-cartao">
          <svg class="i" viewBox="0 0 24 24" aria-hidden="true">{!! $icones[$item['icone']] !!}</svg>
          <h3>{{ $item['titulo'] }} <span class="pub-selo">Disponível</span></h3>
          <p>{{ $item['texto'] }}</p>
        </article>
      @endforeach
    </div>
  </div>
</section>

<section class="pub-secao pub-secao--creme">
  <div class="container pub-grade pub-grade-2" style="align-items:start">
    <div>
      <span class="traco"></span>
      <h2>Em desenvolvimento</h2>
      <p class="pub-intro">Próximas etapas da plataforma. Ainda não estão disponíveis.</p>
      <ul class="lista-breve">
        @foreach ($emBreve as $item)<li>{{ $item }} <span class="pub-selo pub-selo--breve">em breve</span></li>@endforeach
      </ul>
    </div>
    <div class="pub-cartao">
      <h3 style="margin-top:0">Como começar</h3>
      <ol class="pub-texto" style="padding-left:20px">
        <li>Crie a conta de produtor (CPF ou CNPJ) e aceite os termos.</li>
        <li>Em <b>Meus eventos</b>, clique em <b>Novo evento</b> e preencha as informações.</li>
        <li>Cadastre os tipos de ingresso e os lotes.</li>
        <li>Publique. O evento ganha a página pública para divulgação.</li>
      </ol>
      <p style="margin-top:16px"><a href="{{ route('cadastro') }}" class="botao botao--amarelo">Criar conta de produtor</a></p>
    </div>
  </div>
</section>

<section class="pub-secao">
  <div class="container">
    <span class="traco"></span>
    <h2>Quem faz o quê</h2>
    <p class="pub-intro">O Fora da Fila é uma plataforma de tecnologia da <b>BH Software</b>, que desenvolve e mantém o sistema. Quem realiza cada evento é o organizador indicado na página do evento.</p>
    <div style="overflow-x:auto">
      <table class="pub-tabela">
        <thead><tr><th>Parte</th><th>Papel</th></tr></thead>
        <tbody>
          <tr><td><b>Fora da Fila / BH Software</b></td><td>Fornece a tecnologia: cadastro e divulgação de eventos, emissão de ingressos e ferramentas de gestão. Não é produtora, realizadora nem patrocinadora dos eventos.</td></tr>
          <tr><td><b>Organizador (produtor)</b></td><td>Publica o evento e é responsável pela sua realização, pelas informações divulgadas e pelo atendimento ao comprador, conforme o <a href="{{ route('termo', 'responsabilidade_organizador') }}">Termo de responsabilidade do organizador</a>.</td></tr>
          <tr><td><b>Comprador</b></td><td>Encontra o evento, garante o ingresso do organizador indicado na página e acessa os ingressos em “Meus ingressos”.</td></tr>
        </tbody>
      </table>
    </div>
    <p style="margin-top:14px"><small>Os termos da plataforma estão na versão provisória 0.1, em revisão jurídica.</small></p>
  </div>
</section>
@endsection
