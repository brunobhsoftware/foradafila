@php $v = fn ($k, $p = null) => old($k, $t ? $t->{$k} : $p); @endphp
<div class="campo col-4"><label>Operação</label><select name="operacao">@foreach ($O as $k => $txt)<option value="{{ $k }}" @selected($v('operacao') === $k)>{{ $txt }}</option>@endforeach</select></div>
<div class="campo col-8"><label>&nbsp;</label><label class="interruptor"><input type="hidden" name="isento" value="0"><input type="checkbox" name="isento" value="1" @checked($v('isento'))><span></span> Isenção total no período</label></div>
<div class="campo col-4"><label>Percentual (%)</label><input name="percentual" type="number" step="0.001" min="0" max="100" value="{{ $v('percentual') }}" placeholder="padrão">@error('percentual')<small class="erro">{{ $message }}</small>@enderror</div>
<div class="campo col-4"><label>Tarifa fixa (R$)</label><input name="valor_fixo" type="number" step="0.01" min="0" value="{{ $v('valor_fixo') }}" placeholder="padrão"></div>
<div class="campo col-4"><label>Taxa mínima (R$)</label><input name="valor_minimo" type="number" step="0.01" min="0" value="{{ $v('valor_minimo') }}" placeholder="padrão"></div>
<div class="campo col-4"><label>Início</label><input name="inicio" type="date" required value="{{ $v('inicio', $hoje) }}"></div>
<div class="campo col-4"><label>Fim (opcional — em branco fica sempre ativa)</label><input name="fim" type="date" value="{{ $v('fim') }}">@error('fim')<small class="erro">{{ $message }}</small>@enderror</div>
<div class="campo col-4"><label>Observação</label><input name="observacao" maxlength="255" value="{{ $v('observacao') }}" placeholder="ex.: parceria de lançamento"></div>
