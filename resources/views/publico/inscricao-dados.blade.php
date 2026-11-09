@extends('layouts.publico')
@section('titulo', 'Seus dados para o cartão — '.$evento->titulo)

@php
    $linhas = $selecao->map(function ($s) use ($evento) {
        $preco = (float) $s['lote']->preco;
        $tx = $preco > 0 ? \App\Support\TaxaServico::paraPreco($evento, $preco, $evento->organizador) : null;
        return $s + ['unit' => $tx ? $tx['comprador_paga'] : 0, 'preco' => $preco];
    });
    $valorTotal = $linhas->sum(fn ($l) => $l['unit'] * $l['quantidade']);
@endphp

@section('conteudo')
<section class="pub-secao">
  <div class="container pub-evento">
    {{-- Pré-cadastro antes do pagamento com cartão: a compra (ingressos, participantes, termos) segue nos campos ocultos. --}}
    <form method="post" action="{{ route('inscricao.store', $evento->codigoPublico()) }}" class="pub-cartao">
      @csrf
      <input type="hidden" name="etapa_dados" value="1">
      @foreach ($ocultos as $nome => $valor)<input type="hidden" name="{{ $nome }}" value="{{ $valor }}">@endforeach

      <h1 style="font-size:1.7rem;margin-bottom:6px">Seus dados para o cartão</h1>
      <p style="margin-top:0">Para pagar com cartão de crédito, o Mercado Pago pede o CPF do titular. Confira os dados e siga para o pagamento.</p>
      <div class="form-grade">
        <div class="campo col-6"><label for="cpf">CPF do titular do cartão <span class="obrigatorio">*</span></label>
          <input id="cpf" name="cpf" value="{{ $pre['cpf'] ?? '' }}" data-mascara="cpf" inputmode="numeric" required autofocus>
          @error('cpf')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-6"><label for="telefone">Celular (opcional)</label>
          <input id="telefone" name="telefone" type="tel" value="{{ $pre['telefone'] ?? '' }}" data-mascara="telefone" autocomplete="tel"></div>
        <div class="campo col-12"><label for="nome">Nome completo <span class="obrigatorio">*</span></label>
          <input id="nome" name="nome" value="{{ $pre['nome'] ?? '' }}" required autocomplete="name">
          @error('nome')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-12"><label for="email">E-mail <span class="obrigatorio">*</span></label>
          <input id="email" name="email" type="email" value="{{ $pre['email'] ?? '' }}" required autocomplete="email">
          @error('email')<small class="erro">{{ $message }}</small>@enderror</div>
      </div>
      <button class="botao botao--amarelo botao--largo" style="margin-top:14px">Ir para o pagamento — {{ moeda($valorTotal) }}</button>
      <p style="margin:10px 0 0;text-align:center"><small><a href="{{ $evento->linkPublico() }}" style="color:var(--laranja)">‹ Voltar ao evento</a></small></p>
    </form>

    <aside class="pub-cartao pub-compra">
      <small>Resumo</small>
      <h2 style="font-size:1.2rem;margin:4px 0 6px">{{ $evento->titulo }}</h2>
      @foreach ($linhas as $s)
        <div class="pub-lote"><div><b>{{ $s['quantidade'] }} × {{ $s['lote']->tipo->nome }}</b><small>{{ $s['lote']->nome }}</small></div>
          <span class="pub-preco">{{ $s['preco'] > 0 ? moeda($s['unit'] * $s['quantidade']) : 'Grátis' }}</span></div>
      @endforeach
      <div class="pub-lote" style="border-top:1px solid var(--borda)"><b>Total</b><span class="pub-preco">{{ moeda($valorTotal) }}</span></div>
      <p style="margin:10px 0 0"><small>Pagamento com cartão de crédito pelo Mercado Pago.</small></p>
    </aside>
  </div>
</section>
@endsection
