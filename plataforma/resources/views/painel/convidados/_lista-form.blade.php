<form method="post" action="{{ $l ? route('convidados.lista.salvar', [$evento, $l->id]) : route('convidados.lista.criar', $evento) }}" class="form-grade">
    @csrf
    <div class="campo col-6"><label>Nome da lista</label><input name="nome" required maxlength="80" value="{{ old('nome', $l?->nome) }}" placeholder="ex.: Lista VIP, Imprensa"></div>
    <div class="campo col-6"><label>Lote usado para emitir</label><select name="lote">
        @foreach ($lotes as $lt)<option value="{{ $lt->id }}" @selected(old('lote', $l?->lote_id) == $lt->id)>{{ $lt->tipo?->nome }} — {{ $lt->nome }} ({{ \App\Models\Lote::VISIBILIDADES[$lt->visibilidade] ?? '' }}, {{ max(0, $lt->quantidade - $lt->vendido - $lt->reservado) }} disponíveis)</option>@endforeach</select>
        <small class="suave">Dica: crie um lote gratuito "Para ser adicionado manualmente" só para convidados.</small></div>
    <div class="campo col-6"><label>Limite de convidados</label><input name="limite_convidados" type="number" min="1" value="{{ old('limite_convidados', $l?->limite_convidados) }}" placeholder="sem limite"></div>
    <div class="campo col-6"><label>Acompanhantes por convidado (máximo)</label><input name="max_acompanhantes" type="number" min="0" max="20" required value="{{ old('max_acompanhantes', $l?->max_acompanhantes ?? 0) }}"></div>
    <div class="col-12"><button class="botao botao--primario botao--pequeno">{{ $l ? 'Salvar lista' : 'Criar lista' }}</button></div>
</form>
