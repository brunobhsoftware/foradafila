@extends('layouts.publico')
@section('titulo', 'Garantir ingresso — '.$evento->titulo)

@php
    $total = $selecao->sum('quantidade'); $i = 0;
    $pago = $selecao->contains(fn ($s) => (float) $s['lote']->preco > 0);
    $linhas = $selecao->map(function ($s) use ($evento) {
        $preco = (float) $s['lote']->preco;
        $tx = $preco > 0 ? \App\Support\TaxaServico::paraPreco($evento, $preco, $evento->organizador) : null;
        return $s + ['unit' => $tx ? $tx['comprador_paga'] : 0, 'taxa' => $tx && $tx['repassada'] ? $tx['taxa'] : 0, 'preco' => $preco];
    });
    $valorTotal = $linhas->sum(fn ($l) => $l['unit'] * $l['quantidade']);
@endphp

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-evento">
    <form method="post" action="{{ route('inscricao.store', $evento->slug) }}" class="pub-cartao">
      @csrf
      <input type="hidden" name="chave" value="{{ old('chave', $chave) }}">
      @foreach ($selecao as $s)<input type="hidden" name="q[{{ $s['lote']->uuid }}]" value="{{ $s['quantidade'] }}">@endforeach

      <div class="pub-trilha" style="margin-bottom:4px"><a href="{{ $evento->linkPublico() }}" style="color:var(--laranja)">‹ Voltar ao evento</a></div>
      <h1 style="font-size:1.7rem;margin-bottom:18px">{{ $pago ? 'Comprar ingresso' : 'Garantir ingresso' }}</h1>

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

      <div class="campo" style="margin-top:20px">
        <label for="cupom">Código de divulgação ou cupom <small>(opcional)</small></label>
        <input id="cupom" name="cupom" maxlength="30" value="{{ old('cupom', $cupom) }}" style="text-transform:uppercase" placeholder="Se alguém te indicou, digite o código">
        @error('cupom')<small class="erro">{{ $message }}</small>@enderror
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

      @if ($pago && \App\Support\Checkout::modoTeste($evento->organizador_id))
        <p style="margin:0 0 10px;padding:10px 12px;background:#FFF4D6;border:1px solid var(--amarelo);border-radius:8px"><small><b>Ambiente de teste:</b> pague com a conta compradora de teste do Mercado Pago. Nada é cobrado.</small></p>
      @endif
      @if ($pago)
        <button class="botao botao--amarelo botao--largo">Ir para o pagamento — {{ moeda($valorTotal) }}</button>
        <p style="margin:10px 0 0;text-align:center"><small>Você será levado ao Mercado Pago (Pix ou cartão). Os ingressos ficam reservados por {{ \App\Support\Checkout::RESERVA_MIN }} minutos e são emitidos assim que o pagamento é aprovado. Cupom de desconto é aplicado no total.</small></p>
      @else
        <button class="botao botao--amarelo botao--largo">Confirmar inscrição gratuita</button>
      @endif
    </form>

    <aside class="pub-cartao pub-compra">
      <small>Resumo</small>
      <h2 style="font-size:1.2rem;margin:4px 0 6px">{{ $evento->titulo }}</h2>
      <p style="margin:0 0 12px"><small>{{ data_local($evento->data_inicio, 'd/m/Y \à\s H:i') }} · {{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small></p>
      @foreach ($linhas as $s)
        <div class="pub-lote"><div><b>{{ $s['quantidade'] }} × {{ $s['lote']->tipo->nome }}</b><small>{{ $s['lote']->nome }}@if ($s['taxa'] > 0) · {{ moeda($s['preco']) }} + {{ moeda($s['taxa']) }} de taxa @endif</small></div>
          <span class="pub-preco">{{ $s['preco'] > 0 ? moeda($s['unit'] * $s['quantidade']) : 'Grátis' }}</span></div>
      @endforeach
      <div class="pub-lote" style="border-top:1px solid var(--borda)"><b>Total</b><span class="pub-preco">{{ moeda($valorTotal) }}</span></div>
    </aside>
  </div>
</section>
@endsection
