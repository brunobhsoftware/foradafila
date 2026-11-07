{{-- Formulário de lote (novo ou edição). Variáveis: $lote, $acao, $metodo, $tiposOpcoes, $lotesOpcoes, $vendaFimPadrao --}}
@php
    $novo = ! $lote->exists;
    // Erros de validação só reabrem os valores no formulário que foi enviado.
    $usarOld = old('_form') === ($formId ?? 'lote-'.$lote->id);
    $v = fn ($campo, $padrao = null) => $usarOld ? old($campo, $padrao) : $padrao;
@endphp
<form method="post" action="{{ $acao }}" class="form-grade" style="margin-top:8px">
    @csrf
    @if ($metodo !== 'POST') @method($metodo) @endif
    <input type="hidden" name="_form" value="{{ $formId ?? 'lote-'.$lote->id }}">
    <input type="hidden" name="ingresso_tipo_id" value="{{ $lote->ingresso_tipo_id }}">

    <div class="campo col-4"><label>Nome do lote</label><input name="nome" value="{{ $v('nome', $lote->nome) }}" required maxlength="80"></div>
    <div class="campo col-4"><label>Modalidade</label>
        <select name="modalidade">@foreach (\App\Models\Lote::MODALIDADES as $k => $t)<option value="{{ $k }}" @selected($v('modalidade', $lote->modalidade) === $k)>{{ $t }}</option>@endforeach</select></div>
    <div class="campo col-2"><label>Preço (R$)</label><input name="preco" type="number" step="0.01" min="0" value="{{ $v('preco', $lote->preco ?? '') }}" required
        @if ($lote->temMovimento()) readonly title="Lote com vendas: crie um novo lote para mudar o preço" @endif></div>
    <div class="campo col-2"><label>Quantidade</label><input name="quantidade" type="number" min="1" value="{{ $v('quantidade', $lote->quantidade) }}" required></div>

    <div class="campo col-3"><label>Início das vendas</label><input name="venda_inicio" type="datetime-local" value="{{ $v('venda_inicio', $novo ? now()->setTimezone(fuso_exibicao())->format('Y-m-d\TH:i') : para_input_data($lote->venda_inicio)) }}" required></div>
    <div class="campo col-3"><label>Fim das vendas</label><input name="venda_fim" type="datetime-local" value="{{ $v('venda_fim', $novo ? $vendaFimPadrao : para_input_data($lote->venda_fim)) }}" required></div>
    <div class="campo col-3"><label>Mín. por pedido</label><input name="min_por_pedido" type="number" min="1" value="{{ $v('min_por_pedido', $lote->min_por_pedido) }}" required></div>
    <div class="campo col-3"><label>Máx. por pedido</label><input name="max_por_pedido" type="number" min="1" value="{{ $v('max_por_pedido', $lote->max_por_pedido) }}" required></div>

    <div class="campo col-4"><label>Virada para o próximo lote</label>
        <select name="virada">@foreach (\App\Models\Lote::VIRADAS as $k => $t)<option value="{{ $k }}" @selected($v('virada', $lote->virada) === $k)>{{ $t }}</option>@endforeach</select></div>
    <div class="campo col-4"><label>Abre depois de</label>
        <select name="lote_anterior_id"><option value="">— abre na data de início —</option>
            @foreach ($lotesOpcoes as $id => $nome) @continue($id === $lote->id)<option value="{{ $id }}" @selected((string) $v('lote_anterior_id', $lote->lote_anterior_id) === (string) $id)>{{ $nome }}</option>@endforeach</select></div>
    <div class="campo col-4"><label>Quem pode comprar</label>
        <select name="visibilidade">@foreach (\App\Models\Lote::VISIBILIDADES as $k => $t)<option value="{{ $k }}" @selected($v('visibilidade', $lote->visibilidade) === $k)>{{ $t }}</option>@endforeach</select></div>

    @unless ($novo)
        <div class="campo col-4"><label>Status</label>
            <select name="status">@foreach (['agendado', 'ativo', 'pausado', 'encerrado'] as $k)<option value="{{ $k }}" @selected($lote->status === $k)>{{ \App\Models\Lote::STATUS[$k] }}</option>@endforeach
                @if ($lote->status === 'esgotado')<option value="" selected>Esgotado (automático)</option>@endif</select></div>
    @endunless
    <div class="col-12"><button class="botao botao--escuro botao--pequeno">{{ $novo ? 'Adicionar lote' : 'Salvar lote' }}</button></div>
</form>
