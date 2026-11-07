@extends('layouts.publico')
@section('titulo', 'Ajuda')
@section('descricao', 'Dúvidas frequentes sobre o Fora da Fila: compra de ingressos, Meus ingressos, segunda via, cancelamento e área do produtor.')

@php
    $grupos = [
        'Sobre a plataforma' => [
            ['O que é o Fora da Fila?', '<p>Uma plataforma de tecnologia para publicação e gestão de eventos, emissão de ingressos e controle de acesso do público. Produtores cadastram e gerenciam seus eventos; o público encontra os eventos e garante os ingressos.</p>'],
            ['Qual é o papel da BH Software?', '<p>O Fora da Fila é uma plataforma de tecnologia da BH Software, que desenvolve e mantém o sistema. A BH Software e o Fora da Fila não são produtores, realizadores nem patrocinadores dos eventos publicados.</p>'],
            ['Para quem é a plataforma?', '<p>Para produtores e organizadores de eventos de todos os portes — pessoa física (CPF) ou empresa (CNPJ) — e para o público que quer encontrar eventos e acessar os seus ingressos.</p>'],
            ['Quem é responsável pela realização do evento e pelo atendimento?', '<p>O organizador indicado na página do evento. Ele é o responsável pela realização do evento, pelas informações divulgadas e pelo atendimento ao comprador, incluindo alterações, adiamentos, cancelamentos e reembolsos devidos, conforme o <a href="'.route('termo', 'responsabilidade_organizador').'">Termo de responsabilidade do organizador</a> e os <a href="'.route('termo', 'compra_comprador').'">Termos de compra</a>.</p><p><small>Os termos estão na versão provisória 0.1, em revisão jurídica.</small></p>'],
        ],
        'Para quem compra' => [
            ['Como encontro e garanto um ingresso?', '<p>Veja os eventos em <a href="'.route('vitrine').'">Eventos</a> ou abra o link que o organizador divulgou. Na página do evento, escolha a quantidade e siga os passos. Hoje a plataforma emite ingressos de <b>eventos gratuitos</b>; a venda de ingressos pagos está em desenvolvimento e ainda não é possível pagar pelo site.</p>'],
            ['Onde acesso os ingressos que garanti?', '<p>Em <a href="'.route('comprador.ingressos').'">Meus ingressos</a>, entrando com o e-mail e a senha que você criou ao garantir o ingresso. Lá aparecem os seus pedidos e cada ingresso com o QR Code.</p>'],
            ['Como obtenho ou imprimo a segunda via?', '<p>Em Meus ingressos, abra o ingresso e use <b>Imprimir / salvar em PDF</b>. A segunda via é o mesmo ingresso, com o mesmo QR Code: não gera um ingresso novo nem muda o titular.</p>'],
            ['Como cancelo ou peço reembolso?', '<p><b>Ingresso gratuito:</b> em Meus ingressos você pode cancelar a inscrição até o início do evento. O ingresso deixa de valer e a vaga volta a ficar disponível.</p><p><b>Ingresso pago:</b> a venda paga ainda não está disponível. Quando estiver, o pedido de cancelamento ou reembolso será encaminhado ao organizador, que analisa e decide conforme a política do evento, os termos e a lei. Fazer o pedido não significa que ele foi aprovado nem que o reembolso foi realizado.</p>'],
            ['Com quem falo sobre o evento ou o meu ingresso?', '<p>Com o organizador. O e-mail de atendimento dele aparece na página do evento e no seu ingresso.</p>'],
            ['Preciso pagar alguma taxa?', '<p>Não. O Fora da Fila não cobra taxa do comprador; você paga apenas o valor do ingresso definido pelo organizador.</p>'],
        ],
        'Para produtores' => [
            ['Como acesso e gerencio meus eventos?', '<p>Crie a conta em <a href="'.route('cadastro').'">Criar conta de produtor</a> e entre pela <a href="'.route('entrar').'">Área do produtor</a>. Em <b>Meus eventos</b> você vê os eventos, cria um novo, cadastra tipos de ingresso e lotes, publica, pausa e encerra. A equipe entra por convite, com o nível de acesso que você definir.</p>'],
            ['Como funciona o preço?', '<p>No primeiro evento do produtor a comissão é de 1% sobre o valor das vendas de ingressos, com percentual que aumenta a cada R$ 1.000 em vendas. A tabela completa das faixas ainda está em definição. Veja os detalhes e o que falta confirmar em <a href="'.route('precos').'">Preços</a>.</p>'],
            ['O dinheiro das vendas passa pelo Fora da Fila?', '<p>O modelo previsto é que o pagamento do comprador caia diretamente na conta de recebimento do organizador no meio de pagamento escolhido. Essa integração (Mercado Pago primeiro) está em desenvolvimento; até lá, só eventos gratuitos emitem ingressos.</p>'],
        ],
    ];
@endphp

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <span class="traco"></span>
    <h1>Ajuda e dúvidas frequentes<span class="ponto">.</span></h1>
    <p>Respostas para quem compra e para quem organiza eventos.</p>
  </div>
</section>

<section class="pub-secao">
  <div class="container">
    <div class="pub-faq">
      @foreach ($grupos as $grupo => $perguntas)
        <h3>{{ $grupo }}</h3>
        @foreach ($perguntas as [$pergunta, $resposta])
          <details @if ($loop->parent->first && $loop->first) open @endif>
            <summary>{{ $pergunta }}</summary>
            <div>{!! $resposta !!}</div>
          </details>
        @endforeach
      @endforeach
    </div>
  </div>
</section>
@endsection
