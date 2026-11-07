@php
    $id = $r->id ?? 'nova';
    $usa = old('_regra') == $id;
    $v = fn ($k, $p = null) => $usa ? old($k, $p) : ($r->{$k} ?? $p);
@endphp
<form method="post" action="{{ $r ? route('admin.taxas.salvar', $r->id) : route('admin.taxas.criar') }}" class="form-grade" style="margin-top:8px">
    @csrf @if ($r) @method('PUT') @endif
    <input type="hidden" name="_regra" value="{{ $id }}">
    <div class="campo col-6"><label>Nome da regra</label><input name="nome" required maxlength="80" value="{{ $v('nome') }}" placeholder="ex.: Faixa 1 — até R$ 10 mil"></div>
    <div class="campo col-6"><label>Operação</label><select name="operacao">@foreach (\App\Support\MotorTaxas::OPERACOES as $k => $t)<option value="{{ $k }}" @selected($v('operacao', 'ingresso_pago') === $k)>{{ $t }}</option>@endforeach</select></div>
    <div class="campo col-4"><label>Percentual (%)</label><input name="percentual" type="number" step="0.001" min="0" max="100" required value="{{ $v('percentual', '0') }}"></div>
    <div class="campo col-4"><label>Tarifa fixa (R$)</label><input name="valor_fixo" type="number" step="0.01" min="0" required value="{{ $v('valor_fixo', '0') }}"></div>
    <div class="campo col-4"><label>Taxa mínima (R$)</label><input name="valor_minimo" type="number" step="0.01" min="0" required value="{{ $v('valor_minimo', '0') }}"></div>
    <div class="campo col-4"><label>Faixa: volume no mês a partir de (R$)</label><input name="volume_min" type="number" step="0.01" min="0" required value="{{ $v('volume_min', '0') }}">
        @if ($usa)@error('volume_min')<small class="erro">{{ $message }}</small>@enderror @endif</div>
    <div class="campo col-4"><label>até (R$, em branco = sem limite)</label><input name="volume_max" type="number" step="0.01" min="0" value="{{ $v('volume_max') }}">
        @if ($usa)@error('volume_max')<small class="erro">{{ $message }}</small>@enderror @endif</div>
    <div class="campo col-4"><label>Carência para conta nova (dias)</label><input name="carencia_dias" type="number" min="0" required value="{{ $v('carencia_dias', '0') }}"></div>
    <div class="col-12"><label class="interruptor"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" @checked($v('ativo', 1))><span></span> Regra ativa</label></div>
    <div class="col-12"><button class="botao botao--primario botao--pequeno">{{ $r ? 'Salvar regra' : 'Criar regra' }}</button></div>
</form>
