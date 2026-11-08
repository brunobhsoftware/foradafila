@extends('layouts.publico')
@section('titulo', 'Solução')
@section('descricao', 'Fora da Fila para produtores: taxa justa (você escolhe quem paga), eventos, lotes, cupons, check-in por QR Code, convidados e equipe.')

@php
    // Somente o que existe hoje no sistema aparece como "Disponível".
    $disponivel = [
        ['icone' => 'evento', 'titulo' => 'Cadastro e gestão de eventos', 'texto' => 'Crie o evento como rascunho, com imagem de divulgação, local, política de cancelamento e capacidade opcional, e publique quando estiver pronto.'],
        ['icone' => 'ingresso', 'titulo' => 'Tipos de ingresso e lotes', 'texto' => 'Setores como Pista e VIP, lotes gratuitos ou pagos, inteira e meia, por data ou em sequência (o próximo abre quando o anterior esgota).'],
        ['icone' => 'dinheiro', 'titulo' => 'Taxa justa, você escolhe quem paga', 'texto' => 'Taxa por ingresso pago, sem mensalidade. Embutida no preço ou repassada ao comprador: padrão da organização e escolha por evento.'],
        ['icone' => 'pagina', 'titulo' => 'Página do evento e do organizador', 'texto' => 'Página pronta para divulgar, com os ingressos, o organizador responsável e a sua página com todos os eventos.'],
        ['icone' => 'qr', 'titulo' => 'Ingresso digital e check-in', 'texto' => 'Ingresso com QR Code assinado em “Meus ingressos”. Na portaria: leitor, câmera do celular ou busca por nome, sem entrada repetida.'],
        ['icone' => 'grafico', 'titulo' => 'Pedidos e participantes', 'texto' => 'Lista com filtros, reenvio de ingressos, cancelamento com o valor a devolver e exportação.'],
        ['icone' => 'cupom', 'titulo' => 'Cupons e divulgadores', 'texto' => 'Cupons de desconto com limites e validade, e códigos de divulgação que mostram quantos ingressos cada pessoa trouxe.'],
        ['icone' => 'equipe', 'titulo' => 'Equipe, convidados e mensagens', 'texto' => 'Níveis de acesso por pessoa, listas de convidados com envio do ingresso e e-mails automáticos personalizáveis.'],
    ];
    $emBreve = [
        'Venda de ingressos pagos com o dinheiro direto na conta do organizador (Mercado Pago primeiro)',
        'Check-in pelo celular sem internet (aplicativo da portaria)',
        'Confirmação de presença de convidados e links de acesso privado',
        'Lembretes automáticos do evento',
    ];
    $icones = [
        'evento' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'ingresso' => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/>',
        'pagina' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17.5 17.5 21 21"/>',
        'equipe' => '<circle cx="12" cy="7" r="3.5"/><path d="M5 21a7 7 0 0 1 14 0"/><circle cx="4.5" cy="10" r="2"/><circle cx="19.5" cy="10" r="2"/>',
        'dinheiro' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .9-3 2.2 0 3 6 1.6 6 4.6 0 1.3-1.3 2.2-3 2.2-1.4 0-2.6-.6-3-1.6M12 6v2M12 16v2"/>',
        'cupom' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
        'grafico' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];
@endphp

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>Tudo para vender e organizar o seu evento, com taxa justa<span class="ponto">.</span></h1>
    <p>O Fora da Fila é uma plataforma de tecnologia para produtores: taxas competitivas, o dinheiro direto na sua conta e a liberdade de decidir se a taxa fica com você ou com o comprador.</p>
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
        <li>Escolha quem paga a taxa (embutida ou repassada).</li>
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
          <tr><td><b>Fora da Fila / BH Software</b></td><td>Fornece a tecnologia: cadastro e divulgação de eventos, emissão de ingressos e ferramentas de gestão. Recebe apenas a taxa da plataforma; o valor dos ingressos vai para o organizador. Não é produtora, realizadora nem patrocinadora dos eventos.</td></tr>
          <tr><td><b>Organizador (produtor)</b></td><td>Publica o evento e é responsável pela sua realização, pelas informações divulgadas e pelo atendimento ao comprador, conforme o <a href="{{ route('termo', 'responsabilidade_organizador') }}">Termo de responsabilidade do organizador</a>.</td></tr>
          <tr><td><b>Comprador</b></td><td>Encontra o evento, garante o ingresso do organizador indicado na página e acessa os ingressos em “Meus ingressos”.</td></tr>
        </tbody>
      </table>
    </div>
    <p style="margin-top:14px"><small>Os termos da plataforma estão na versão provisória 0.1, em revisão jurídica.</small></p>
  </div>
</section>
@endsection
