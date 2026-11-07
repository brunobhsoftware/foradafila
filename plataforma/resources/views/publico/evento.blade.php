@extends('layouts.publico')
@section('titulo', $evento->titulo)
@section('descricao', $evento->resumo ?: 'Ingressos para '.$evento->titulo.' no Fora da Fila.')
@push('meta')
  <meta property="og:title" content="{{ $evento->titulo }}">
  <meta property="og:description" content="{{ $evento->resumo }}">
  @if ($evento->imagem_capa_url)<meta property="og:image" content="{{ url($evento->imagem_capa_url) }}">@endif
@endpush

@php
    $org = $evento->organizador;
    $gratuitos = $aVenda->filter(fn ($l) => (float) $l->preco === 0.0);
    $max = $evento->max_ingressos_pedido;
@endphp

@section('conteudo')
<section class="pub-cabecalho">
  <div class="container">
    <div class="pub-trilha"><a href="{{ route('vitrine') }}">Eventos</a> › {{ $evento->categoria?->nome ?? 'Evento' }}</div>
    <h1>{{ $evento->titulo }}</h1>
    <p>{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::parse($evento->data_inicio)->setTimezone(fuso_exibicao())->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y \à\s H:i')) }}
       · {{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</p>
  </div>
</section>

<section class="pub-secao">
  <div class="container pub-evento">
    <div class="pub-evento__info">
      @if ($evento->imagem_capa_url)
        <img class="pub-evento__capa" src="{{ $evento->imagem_capa_url }}" alt="Imagem de divulgação de {{ $evento->titulo }}" width="1600" height="838">
      @endif
      @if ($evento->status === 'cancelado')
        <div class="pub-aviso" style="background:#FEE4E2;border-color:#FECDCA;color:#7A271A"><div><b>Evento cancelado pelo organizador.</b> {{ $evento->cancelamento_motivo }}</div></div>
      @elseif ($evento->status === 'encerrado')
        <div class="pub-aviso"><div><b>Este evento já foi encerrado.</b></div></div>
      @elseif ($evento->status === 'pausado')
        <div class="pub-aviso"><div><b>As vendas estão pausadas pelo organizador.</b></div></div>
      @endif

      <div class="pub-fatos">
        <div><svg class="i" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
          <span><b>Início:</b> {{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }} · <b>Término:</b> {{ data_local($evento->data_fim, 'd/m/Y \à\s H:i') }}
          @if ($evento->abertura_portoes)<br><b>Abertura dos portões:</b> {{ data_local($evento->abertura_portoes, 'H:i') }}@endif
          <br><small>Horário de Brasília</small></span></div>
        @if ($evento->local)
          <div><svg class="i" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <span><b>{{ $evento->local->nome }}</b><br>{{ collect([$evento->local->logradouro ? $evento->local->logradouro.($evento->local->numero ? ', '.$evento->local->numero : '') : null, $evento->local->bairro, $evento->local->cidade.'/'.$evento->local->uf])->filter()->implode(' · ') }}</span></div>
        @endif
        @if ($evento->formato !== 'presencial')
          <div><svg class="i" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg><span><b>{{ \App\Models\Evento::FORMATOS[$evento->formato] }}:</b> o link da transmissão é enviado só para quem tem ingresso.</span></div>
        @endif
        <div><svg class="i" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
          <span><b>Classificação:</b> {{ \App\Models\Evento::CLASSIFICACOES[$evento->classificacao] }}
          · {{ $evento->nominal ? 'Ingresso nominal' : 'Ingresso ao portador' }}{{ $evento->exige_cpf_participante ? ', com CPF do participante' : '' }}</span></div>
      </div>

      @if ($evento->descricao)
        <div><h2>Sobre o evento</h2><div class="pub-evento__descricao">{{ $evento->descricao }}</div></div>
      @endif

      @if ($evento->politica_cancelamento)
        <div><h2>Política de cancelamento</h2><div class="pub-evento__descricao">{{ $evento->politica_cancelamento }}</div></div>
      @endif

      <div class="pub-cartao pub-organizador">
        <small>Organizado e realizado por</small>
        <h3 style="margin-top:4px">{{ $org->nomeExibicao() }}</h3>
        <p style="margin:0 0 6px">{{ $org->razao_social }} · {{ $org->tipo_pessoa === 'PJ' ? 'CNPJ' : 'CPF' }} {{ $org->tipo_pessoa === 'PJ' ? $org->documentoFormatado() : '***.'.substr($org->documento, 3, 3).'.'.substr($org->documento, 6, 3).'-**' }}</p>
        <p style="margin:0 0 10px">Atendimento: <a href="mailto:{{ $org->email_contato }}">{{ $org->email_contato }}</a></p>
        <p style="margin:0"><small>O organizador é o responsável pela realização do evento e pelo atendimento ao comprador.
          O Fora da Fila fornece a tecnologia de emissão de ingressos.
          <a href="{{ route('termo', 'compra_comprador') }}">Termos de compra</a>.</small></p>
        <p style="margin:10px 0 0"><a href="{{ route('organizador.publico', $org->slug) }}">Outros eventos deste organizador</a></p>
      </div>
    </div>

    <aside class="pub-cartao pub-compra" aria-labelledby="titulo-ingressos">
      <h2 id="titulo-ingressos" style="margin:0 0 6px">Ingressos</h2>
      @if ($lotes->isEmpty())
        <p>O organizador ainda não disponibilizou ingressos.</p>
      @else
        <form method="get" action="{{ route('inscricao.create', $evento->slug) }}">
          @foreach ($lotes as $lote)
            @php
              $disponivel = $aVenda->contains('id', $lote->id);
              $gratis = (float) $lote->preco === 0.0;
              $limite = min($max, $lote->max_por_pedido, $lote->quantidade - $lote->reservado - $lote->vendido);
            @endphp
            <div class="pub-lote">
              <div>
                <b>{{ $lote->tipo->nome }} · {{ $lote->nome }}</b>
                <small>{{ \App\Models\Lote::MODALIDADES[$lote->modalidade] }}
                  @if (! $disponivel)
                    · @if ($lote->status === 'esgotado' || $lote->quantidade - $lote->vendido - $lote->reservado <= 0) esgotado
                      @elseif ($lote->venda_inicio->isFuture()) vendas a partir de {{ data_local($lote->venda_inicio, 'd/m H:i') }}
                      @elseif ($lote->lote_anterior_id && $evento->status === 'publicado') abre quando o lote anterior encerrar
                      @else indisponível @endif
                  @elseif (! $gratis) · venda online em breve
                  @else · até {{ data_local($lote->venda_fim, 'd/m H:i') }} @endif
                </small>
              </div>
              <div style="text-align:right">
                <span class="pub-preco">{{ $gratis ? 'Grátis' : moeda($lote->preco) }}</span>
                @if ($disponivel && $gratis && $limite >= $lote->min_por_pedido)
                  <div style="margin-top:6px">
                    <label class="oculto-visual" for="q_{{ $lote->uuid }}">Quantidade de {{ $lote->nome }}</label>
                    <select id="q_{{ $lote->uuid }}" name="q[{{ $lote->uuid }}]">
                      <option value="0">0</option>
                      @for ($n = $lote->min_por_pedido; $n <= $limite; $n++)<option value="{{ $n }}">{{ $n }}</option>@endfor
                    </select>
                  </div>
                @endif
              </div>
            </div>
          @endforeach

          @if ($gratuitos->isNotEmpty())
            <button class="botao botao--amarelo botao--largo" style="margin-top:16px">Garantir ingresso</button>
            <p style="margin:10px 0 0"><small>Sem taxa para você. Ingresso digital com QR Code em “Meus ingressos”.</small></p>
          @endif
        </form>
        @if ($aVenda->isNotEmpty() && $gratuitos->count() < $aVenda->count())
          <p style="margin:14px 0 0"><small><b>Ingressos pagos:</b> a compra online está em desenvolvimento e ainda não é possível pagar pelo site.</small></p>
        @endif
      @endif
    </aside>
  </div>
</section>
@endsection
