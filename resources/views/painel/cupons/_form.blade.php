@php
    $id = $c?->id ?? 'novo';
    $usa = old('_cupom') == $id;
    $v = fn ($k, $p = null) => $usa ? old($k, $p) : $p;
    $eventoUuid = $v('evento', $c?->evento?->uuid ?? ($eventoFiltro?->uuid ?? ''));
    $lotesSel = collect($v('lotes', $c ? $c->lotes->pluck('id')->all() : []))->map(fn ($x) => (int) $x)->all();
@endphp
<form method="post" action="{{ $c ? route('cupons.update', $c) : route('cupons.store') }}" class="form-grade" data-cupom-form style="text-align:left;margin-top:8px">
    @csrf @if ($c) @method('PUT') @endif
    <input type="hidden" name="_cupom" value="{{ $id }}">
    <div class="campo col-4"><label>Código</label><input name="codigo" required maxlength="30" value="{{ $v('codigo', $c?->codigo) }}" placeholder="EX.: AMIGOS10" style="text-transform:uppercase">
        @if ($usa)@error('codigo')<small class="erro">{{ $message }}</small>@enderror @endif</div>
    <div class="campo col-8"><label>Descrição (só para a equipe)</label><input name="descricao" maxlength="150" value="{{ $v('descricao', $c?->descricao) }}" placeholder="ex.: divulgação no Instagram"></div>
    <div class="campo col-6"><label>Vale para</label><select name="evento" data-cupom-evento>
        <option value="">Todos os eventos da organização</option>
        @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($eventoUuid === $e->uuid)>{{ $e->titulo }}</option>@endforeach</select></div>
    <div class="campo col-3"><label>Tipo de desconto</label><select name="tipo_desconto">
        <option value="percentual" @selected($v('tipo_desconto', $c?->tipo_desconto ?? 'percentual') === 'percentual')>Percentual (%)</option>
        <option value="valor_fixo" @selected($v('tipo_desconto', $c?->tipo_desconto) === 'valor_fixo')>Valor fixo (R$)</option></select></div>
    <div class="campo col-3"><label>Desconto</label><input name="valor" type="number" step="0.01" min="0.01" required value="{{ $v('valor', $c ? (float) $c->valor : '') }}">
        @if ($usa)@error('valor')<small class="erro">{{ $message }}</small>@enderror @endif</div>
    <div class="campo col-3"><label>Limite de usos</label><input name="max_usos" type="number" min="1" value="{{ $v('max_usos', $c?->max_usos) }}" placeholder="sem limite">
        @if ($usa)@error('max_usos')<small class="erro">{{ $message }}</small>@enderror @endif</div>
    <div class="campo col-3"><label>Limite por CPF</label><input name="max_usos_cpf" type="number" min="1" value="{{ $v('max_usos_cpf', $c?->max_usos_cpf) }}" placeholder="sem limite"></div>
    <div class="campo col-3"><label>Pedido mínimo (R$)</label><input name="valor_minimo_pedido" type="number" step="0.01" min="0" value="{{ $v('valor_minimo_pedido', $c?->valor_minimo_pedido) }}" placeholder="sem mínimo"></div>
    <div class="campo col-3"><label>Válido de</label><input name="valido_de" type="datetime-local" value="{{ $v('valido_de', $c?->valido_de ? para_input_data($c->valido_de) : '') }}"></div>
    <div class="campo col-3"><label>Válido até</label><input name="valido_ate" type="datetime-local" value="{{ $v('valido_ate', $c?->valido_ate ? para_input_data($c->valido_ate) : '') }}">
        @if ($usa)@error('valido_ate')<small class="erro">{{ $message }}</small>@enderror @endif</div>
    <div class="campo col-12" data-cupom-lotes>
        <label>Lotes em que o cupom vale <small class="suave">(nenhum marcado = todos os lotes do evento)</small></label>
        <p class="suave" data-sem-evento @if ($eventoUuid) hidden @endif><small>Escolha um evento para limitar a lotes específicos.</small></p>
        @foreach ($eventos as $e)
            <div class="opcoes-lotes" data-lotes-evento="{{ $e->uuid }}" @if ($eventoUuid !== $e->uuid) hidden @endif>
                @forelse ($lotes[$e->id] ?? [] as $l)
                    <label class="opcao"><input type="checkbox" name="lotes[]" value="{{ $l->id }}" @checked(in_array($l->id, $lotesSel)) @disabled($eventoUuid !== $e->uuid)>
                        <span>{{ $l->tipo?->nome }} — {{ $l->nome }} ({{ (float) $l->preco > 0 ? moeda($l->preco) : 'grátis' }})</span></label>
                @empty <small class="suave">Este evento ainda não tem lotes.</small> @endforelse
            </div>
        @endforeach
    </div>
    <div class="col-12"><label class="interruptor"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" @checked($v('ativo', $c ? $c->ativo : true))><span></span> Cupom ativo</label></div>
    <div class="col-12"><button class="botao botao--primario botao--pequeno">{{ $c ? 'Salvar cupom' : 'Criar cupom' }}</button></div>
</form>
