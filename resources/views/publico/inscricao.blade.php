@extends('layouts.publico')
@section('titulo', 'Garantir ingresso — '.$evento->titulo)

@php $total = $selecao->sum('quantidade'); $i = 0; @endphp

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-evento">
    <form method="post" action="{{ route('inscricao.store', $evento->slug) }}" class="pub-cartao">
      @csrf
      <input type="hidden" name="chave" value="{{ old('chave', $chave) }}">
      @foreach ($selecao as $s)<input type="hidden" name="q[{{ $s['lote']->uuid }}]" value="{{ $s['quantidade'] }}">@endforeach

      <div class="pub-trilha" style="margin-bottom:4px"><a href="{{ route('evento.publico', $evento->slug) }}" style="color:var(--laranja)">‹ Voltar ao evento</a></div>
      <h1 style="font-size:1.7rem;margin-bottom:18px">Garantir ingresso</h1>

      <h2 style="font-size:1.15rem">1. Seus dados</h2>
      @if ($comprador)
        <p>Você está em <b>Meus ingressos</b> como <b>{{ $comprador->nome }}</b> ({{ $comprador->email }}). Os ingressos ficarão nesta conta.</p>
      @else
        <p>Crie a sua conta para acessar os ingressos depois. Já tem conta? <a href="{{ route('comprador.entrar') }}">Entre primeiro</a> e volte para esta página.</p>
        <div class="form-grade" style="margin-bottom:8px">@include('comprador._campos-conta')</div>
      @endif

      <h2 style="font-size:1.15rem;margin-top:24px">2. Quem vai usar {{ $total > 1 ? 'os ingressos' : 'o ingresso' }}</h2>
      <p><small>{{ $evento->nominal ? 'Cada ingresso é nominal: informe o nome de quem vai ao evento.' : 'Informe um nome de referência para cada ingresso.' }}
        {{ $evento->exige_cpf_participante ? 'Este evento exige o CPF de cada participante.' : '' }}</small></p>
      <div style="display:grid;gap:12px">
        @foreach ($selecao as $s)
          @for ($n = 0; $n < $s['quantidade']; $n++, $i++)
            <div class="pub-participante">
              <h3>Ingresso {{ $i + 1 }} · {{ $s['lote']->tipo->nome }} — {{ $s['lote']->nome }}</h3>
              <div class="form-grade">
                <x-campo nome="participantes[{{ $i }}][nome]" rotulo="Nome do participante" obrigatorio classe="col-8" :valor="$i === 0 ? $comprador?->nome : null" />
                <x-campo nome="participantes[{{ $i }}][cpf]" rotulo="CPF" :obrigatorio="$evento->exige_cpf_participante" classe="col-4" data-mascara="cpf" inputmode="numeric" />
              </div>
            </div>
          @endfor
        @endforeach
      </div>

      <h2 style="font-size:1.15rem;margin-top:24px">3. Termos</h2>
      <div style="display:grid;gap:10px;margin-bottom:20px">
        @foreach (['compra_comprador' => 'os Termos de compra', 'privacidade' => 'a Política de privacidade'] as $tipo => $rotulo)
          @isset($termos[$tipo])
            <label class="opcao"><input type="checkbox" name="aceites[{{ $tipo }}]" value="1" @checked(old('aceites.'.$tipo)) required>
              <span>Li e aceito {{ $rotulo }} (<a href="{{ route('termo', $tipo) }}" target="_blank" rel="noopener">ler, versão {{ $termos[$tipo]->versao }}</a>).</span></label>
          @endisset
        @endforeach
        <small>O ingresso é emitido pelo organizador <b>{{ $evento->organizador->nomeExibicao() }}</b>, responsável pelo evento.</small>
      </div>

      <button class="botao botao--amarelo botao--largo">Confirmar inscrição gratuita</button>
    </form>

    <aside class="pub-cartao pub-compra">
      <small>Resumo</small>
      <h2 style="font-size:1.2rem;margin:4px 0 6px">{{ $evento->titulo }}</h2>
      <p style="margin:0 0 12px"><small>{{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }} · {{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small></p>
      @foreach ($selecao as $s)
        <div class="pub-lote"><div><b>{{ $s['quantidade'] }} × {{ $s['lote']->tipo->nome }}</b><small>{{ $s['lote']->nome }}</small></div><span class="pub-preco">Grátis</span></div>
      @endforeach
      <div class="pub-lote" style="border-top:1px solid var(--borda)"><b>Total</b><span class="pub-preco">R$ 0,00</span></div>
    </aside>
  </div>
</section>
@endsection
