{{-- Formulário de lote (novo ou edição). Variáveis: $lote, $acao, $metodo, $lotesOpcoes, $vendaFimPadrao --}}
@php
    $novo = ! $lote->exists;
    $formId = $formId ?? 'lote-'.$lote->id;
    // Erros de validação só reabrem os valores no formulário que foi enviado.
    $usarOld = old('_form') === $formId;
    $v = fn ($campo, $padrao = null) => $usarOld ? old($campo, $padrao) : $padrao;
    $cobranca = $v('cobranca', $novo && $lote->preco === null ? 'gratuito' : ((float) $lote->preco > 0 ? 'pago' : 'gratuito'));
    $liberacao = $v('liberacao', $lote->lote_anterior_id ? 'por_lote' : 'por_data');
    $ativo = $usarOld ? (bool) old('ativo') : $lote->status !== 'pausado';
    $comVendas = $lote->temMovimento();
    $outros = collect($lotesOpcoes)->except($lote->id);
@endphp
<form method="post" action="{{ $acao }}" class="form-grade lote-form" style="margin-top:8px" data-lote-form>
    @csrf
    @if ($metodo !== 'POST') @method($metodo) @endif
    <input type="hidden" name="_form" value="{{ $formId }}">
    <input type="hidden" name="ingresso_tipo_id" value="{{ $lote->ingresso_tipo_id }}">

    <div class="campo col-6"><label>Nome do ingresso / lote</label><input name="nome" value="{{ $v('nome', $lote->nome) }}" required maxlength="80"></div>
    <div class="campo col-3"><label>Quantidade</label><input name="quantidade" type="number" min="1" value="{{ $v('quantidade', $lote->quantidade) }}" required></div>
    <div class="campo col-3 lote-form__ativo">
        <label>Situação</label>
        <label class="interruptor"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" @checked($ativo)><span></span> Ingresso ativo</label>
        @if (\App\Support\Esquema::temV014())
        <label class="interruptor" style="margin-top:6px" title="Desligado: o ingresso some da página do evento"><input type="hidden" name="exibir" value="0"><input type="checkbox" name="exibir" value="1" @checked(old('exibir', $lote->exibir ?? true))><span></span> Exibir na página</label>
        @endif
    </div>

    {{-- Gratuito ou pago --}}
    <div class="campo col-6">
        <label>O ingresso é</label>
        <div class="segmentado">
            <label><input type="radio" name="cobranca" value="gratuito" @checked($cobranca === 'gratuito') @disabled($comVendas)><span>Gratuito</span></label>
            <label><input type="radio" name="cobranca" value="pago" @checked($cobranca === 'pago') @disabled($comVendas)><span>Pago</span></label>
        </div>
        @if ($comVendas)<input type="hidden" name="cobranca" value="{{ $cobranca }}"><small>Com vendas, o valor não muda: crie um novo lote.</small>@endif
    </div>
    <div class="campo col-3" data-mostrar-se="cobranca=pago" @if ($cobranca !== 'pago') hidden @endif>
        <label>Valor (R$)</label>
        <input name="preco" type="number" step="0.01" min="0.01" value="{{ $v('preco', (float) $lote->preco > 0 ? $lote->preco : '') }}" @readonly($comVendas) placeholder="0,00">
    </div>
    <div class="campo col-3" data-mostrar-se="cobranca=pago" @if ($cobranca !== 'pago') hidden @endif>
        <label>Modalidade</label>
        <select name="modalidade">@foreach (['inteira' => 'Inteira', 'meia' => 'Meia-entrada'] as $k => $t)<option value="{{ $k }}" @selected($v('modalidade', $lote->modalidade) === $k)>{{ $t }}</option>@endforeach</select>
    </div>

    {{-- Quando será vendido --}}
    <div class="campo col-12">
        <label>Quando o ingresso será vendido</label>
        <div class="segmentado">
            <label><input type="radio" name="liberacao" value="por_data" @checked($liberacao === 'por_data')><span>Por data</span></label>
            <label><input type="radio" name="liberacao" value="por_lote" @checked($liberacao === 'por_lote') @disabled($outros->isEmpty())><span>Por lote</span></label>
        </div>
        @if ($outros->isEmpty())<small>"Por lote" fica disponível a partir do segundo lote.</small>@endif
    </div>
    <div class="campo col-4" data-mostrar-se="liberacao=por_data" @if ($liberacao !== 'por_data') hidden @endif>
        <label>Início das vendas</label>
        <input name="venda_inicio" type="datetime-local" value="{{ $v('venda_inicio', $novo || ! $lote->venda_inicio ? now()->setTimezone(fuso_exibicao())->format('Y-m-d\TH:i') : para_input_data($lote->venda_inicio)) }}">
    </div>
    <div class="campo col-4" data-mostrar-se="liberacao=por_lote" @if ($liberacao !== 'por_lote') hidden @endif>
        <label>Começa quando acabar o lote</label>
        <select name="lote_anterior_id"><option value="">— escolha o lote anterior —</option>
            @foreach ($outros as $id => $nome)<option value="{{ $id }}" @selected((string) $v('lote_anterior_id', $lote->lote_anterior_id) === (string) $id)>{{ $nome }}</option>@endforeach</select>
        <small>As vendas começam quando o lote anterior esgotar, for encerrado ou chegar ao fim das vendas.</small>
    </div>
    <div class="campo col-4"><label>Fim das vendas</label><input name="venda_fim" type="datetime-local" value="{{ $v('venda_fim', $novo ? $vendaFimPadrao : para_input_data($lote->venda_fim)) }}" required></div>

    {{-- Quem pode comprar --}}
    <div class="campo col-12">
        <label>Quem pode comprar</label>
        <div class="opcoes-lote">
            @foreach (\App\Models\Lote::VISIBILIDADES as $k => $t)
                <label class="opcao-cartao"><input type="radio" name="visibilidade" value="{{ $k }}" @checked($v('visibilidade', $lote->visibilidade) === $k)>
                    <span><b>{{ $t }}</b><small>{{ \App\Models\Lote::VISIBILIDADES_AJUDA[$k] }}</small></span></label>
            @endforeach
        </div>
    </div>

    <details class="col-12 lote-form__mais">
        <summary>Limites por pedido</summary>
        <div class="form-grade">
            <div class="campo col-3"><label>Mín. por pedido</label><input name="min_por_pedido" type="number" min="1" value="{{ $v('min_por_pedido', $lote->min_por_pedido) }}" required></div>
            <div class="campo col-3"><label>Máx. por pedido</label><input name="max_por_pedido" type="number" min="1" value="{{ $v('max_por_pedido', $lote->max_por_pedido) }}" required></div>
        </div>
    </details>

    <div class="col-12"><button class="botao botao--escuro botao--pequeno">{{ $novo ? 'Adicionar lote' : 'Salvar lote' }}</button></div>
</form>
