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
    <form method="post" action="{{ route('inscricao.store', $evento->codigoPublico()) }}" class="pub-cartao">
      @csrf
      <input type="hidden" name="chave" value="{{ old('chave', $chave) }}">
      @foreach ($selecao as $s)<input type="hidden" name="q[{{ $s['lote']->uuid }}]" value="{{ $s['quantidade'] }}">@endforeach

      <div class="pub-trilha" style="margin-bottom:4px"><a href="{{ $evento->linkPublico() }}" style="color:var(--laranja)">‹ Voltar ao evento</a></div>
      <h1 style="font-size:1.7rem;margin-bottom:18px">{{ $pago ? 'Comprar ingresso' : 'Garantir ingresso' }}</h1>

      <h2 style="font-size:1.15rem">1. Seus dados</h2>
      @if ($comprador)
        <p>Você está em <b>Meus ingressos</b> como <b>{{ $comprador->nome }}</b> ({{ $comprador->email }}). Os ingressos ficarão nesta conta.</p>
      @else
        <div class="form-grade" style="margin-bottom:8px" data-compra-cpf data-url="{{ url('/compra/cpf') }}">
          <x-campo nome="cpf" rotulo="Seu CPF" obrigatorio classe="col-4" data-mascara="cpf" inputmode="numeric" autocomplete="off" ajuda="Comece pelo CPF: se você já comprou com a gente, seus dados aparecem." />
          <div class="col-12" data-cpf-achado hidden>
            <p style="margin:0;padding:12px 14px;background:#F2F4F7;border-left:4px solid var(--laranja);border-radius:8px">
              Olá, <b data-achado-nome></b>! Os ingressos vão para <b data-achado-email></b><span data-achado-tel></span>.<br>
              <small>Não é você? Confira o CPF digitado.</small></p>
          </div>
          <div class="col-12" data-cpf-novo hidden><div class="form-grade">
            <x-campo nome="nome" rotulo="Seu nome completo" obrigatorio autocomplete="name" classe="col-12" />
            <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio autocomplete="email" classe="col-6" ajuda="Os ingressos chegam neste e-mail." />
            <x-campo nome="telefone" rotulo="Celular (opcional)" tipo="tel" data-mascara="telefone" autocomplete="tel" classe="col-6" />
          </div></div>
          <div class="col-12"><small>Não precisa criar senha. Se quiser acessar “Meus ingressos” depois, use “Esqueci a senha” com este e-mail.</small></div>
        </div>
      @endif

      <h2 style="font-size:1.15rem;margin-top:24px">2. Quem vai usar {{ $total > 1 ? 'os ingressos' : 'o ingresso' }}</h2>
      <p><small>Informe os dados de quem vai usar cada ingresso. Campos com * são obrigatórios.</small></p>
      <div style="display:grid;gap:12px">
        @foreach ($selecao as $s)
          @for ($n = 0; $n < $s['quantidade']; $n++, $i++)
            <div class="pub-participante">
              <h3>Ingresso {{ $i + 1 }} · {{ $s['lote']->tipo->nome }} — {{ $s['lote']->nome }}</h3>
              @if ($i === 0)
                <input type="hidden" name="participantes[0][usar_comprador]" value="{{ old('participantes.0.usar_comprador') }}" data-usar-comprador>
                <button type="button" class="botao botao--contorno botao--pequeno" style="margin:0 0 10px" data-usar-meus-dados>Usar meus dados neste ingresso</button>
              @endif
              <div class="form-grade">
                @if ($campos)
                  @foreach ($campos as $cp)
                    @php
                      $nomeCampo = "participantes[{$i}][{$cp['chave']}]"; $old = old("participantes.{$i}.{$cp['chave']}");
                      $pre = $i === 0 ? ['nome' => $comprador?->nome, 'email' => $comprador?->email, 'telefone' => $comprador?->telefone][$cp['chave']] ?? null : null;
                      $largo = in_array($cp['tipo'], ['texto_longo', 'aceite', 'checkbox'], true) || in_array($cp['chave'], ['nome', 'logradouro'], true);
                      $tipoInput = ['email' => 'email', 'data' => 'date', 'numero' => 'number'][$cp['tipo']] ?? 'text';
                      $mascara = ['cpf' => 'cpf', 'telefone' => 'telefone', 'cep' => 'cep'][$cp['tipo']] ?? null;
                    @endphp
                    @if (in_array($cp['tipo'], ['aceite', 'checkbox'], true))
                      <div class="campo col-12"><label class="opcao"><input type="checkbox" name="{{ $nomeCampo }}" value="1" @checked($old)> {{ $cp['rotulo'] }}@if ($cp['obrigatorio']) <span class="obrigatorio">*</span>@endif</label>
                        @error("participantes.{$i}.{$cp['chave']}")<small class="erro">{{ $message }}</small>@enderror</div>
                    @elseif ($cp['tipo'] === 'selecao')
                      <div class="campo col-6"><label>{{ $cp['rotulo'] }}@if ($cp['obrigatorio']) <span class="obrigatorio">*</span>@endif</label>
                        <select name="{{ $nomeCampo }}" @required($cp['obrigatorio'])><option value="">Selecione</option>
                          @foreach ($cp['opcoes'] as $op)<option value="{{ str_replace(',', '', $op) }}" @selected($old === str_replace(',', '', $op))>{{ ucfirst(str_replace('_', ' ', $op)) }}</option>@endforeach</select>
                        @error("participantes.{$i}.{$cp['chave']}")<small class="erro">{{ $message }}</small>@enderror</div>
                    @elseif ($cp['tipo'] === 'texto_longo')
                      <div class="campo col-12"><label>{{ $cp['rotulo'] }}@if ($cp['obrigatorio']) <span class="obrigatorio">*</span>@endif</label>
                        <textarea name="{{ $nomeCampo }}" rows="3" @required($cp['obrigatorio'])>{{ $old }}</textarea>
                        @error("participantes.{$i}.{$cp['chave']}")<small class="erro">{{ $message }}</small>@enderror</div>
                    @else
                      <div class="campo {{ $largo ? 'col-8' : 'col-4' }}"><label>{{ $cp['rotulo'] }}@if ($cp['obrigatorio']) <span class="obrigatorio">*</span>@endif</label>
                        <input name="{{ $nomeCampo }}" type="{{ $tipoInput }}" value="{{ $old ?? $pre }}" @required($cp['obrigatorio']) @if ($mascara) data-mascara="{{ $mascara }}" inputmode="numeric" @endif @if ($cp['tipo'] === 'uf') maxlength="2" @endif>
                        @error("participantes.{$i}.{$cp['chave']}")<small class="erro">{{ $message }}</small>@enderror</div>
                    @endif
                  @endforeach
                @else
                <x-campo nome="participantes[{{ $i }}][nome]" rotulo="Nome do participante" obrigatorio classe="col-8" :valor="$i === 0 ? $comprador?->nome : null" />
                <x-campo nome="participantes[{{ $i }}][cpf]" rotulo="CPF" :obrigatorio="$evento->exige_cpf_participante" classe="col-4" data-mascara="cpf" inputmode="numeric" />
                @endif
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
        <label class="opcao"><input type="checkbox" name="aceite_termos" value="1" @checked(old('aceite_termos')) required>
          <span>Li e concordo com os <a href="{{ route('termo', 'compra_comprador') }}" target="_blank" rel="noopener">Termos de compra</a>
            e a <a href="{{ route('termo', 'privacidade') }}" target="_blank" rel="noopener">Política de privacidade</a>.</span></label>
        @error('aceite_termos')<small class="erro">{{ $message }}</small>@enderror
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
