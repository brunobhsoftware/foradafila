{{-- Campos compartilhados entre criar e editar cupom de cadastro. $c = objeto ou null (criação). --}}
<x-campo nome="codigo" rotulo="Código" obrigatorio classe="col-3" :valor="old('codigo', $c->codigo ?? '')"
    ajuda="Letras e números, sem espaço. Ex: LANCAMENTO" maxlength="50" />

<div class="campo col-9 {{ $errors->has('nome') ? 'com-erro' : '' }}">
    <label for="campo_nome">Descrição interna *</label>
    <textarea id="campo_nome" name="nome" rows="2" maxlength="150" required style="resize:vertical">{{ old('nome', $c->nome ?? '') }}</textarea>
    @error('nome')<span class="erro">{{ $message }}</span>@enderror
</div>

<div class="campo col-12">
    <label class="opcao"><input type="checkbox" name="isento" value="1" @checked(old('isento', $c->isento ?? 0))>
        <span><b>Isento de taxa</b> <small>— o produtor não paga taxa da plataforma no período do benefício</small></span></label>
</div>
<small class="suave col-12" style="margin-top:-6px">Ou defina valores específicos (deixe em branco para usar o padrão da plataforma):</small>
<x-campo nome="percentual" rotulo="Percentual (%)" tipo="number" classe="col-3" :valor="old('percentual', $c->percentual ?? '')" min="0" max="100" step="0.01" ajuda="Ex: 2 para 2%" />
<x-campo nome="valor_fixo" rotulo="Valor fixo (R$)" tipo="number" classe="col-3" :valor="old('valor_fixo', $c->valor_fixo ?? '')" min="0" step="0.01" />
<x-campo nome="valor_minimo" rotulo="Valor mínimo (R$)" tipo="number" classe="col-3" :valor="old('valor_minimo', $c->valor_minimo ?? '')" min="0" step="0.01" />

<x-campo nome="meses_validade" rotulo="Duração (meses)" tipo="number" classe="col-3" :valor="old('meses_validade', $c->meses_validade ?? '')" min="1" ajuda="Em branco = permanente" />
<x-campo nome="limite_usos" rotulo="Limite de usos" tipo="number" classe="col-3" :valor="old('limite_usos', $c->limite_usos ?? '')" min="1" ajuda="Em branco = ilimitado" />

<div class="campo col-12 {{ $errors->has('observacao') ? 'com-erro' : '' }}">
    <label for="campo_observacao">Observação interna</label>
    <textarea id="campo_observacao" name="observacao" rows="3" maxlength="500" style="resize:vertical" placeholder="Detalhes adicionais sobre esta promoção (uso interno, não aparece para o produtor)">{{ old('observacao', $c->observacao ?? '') }}</textarea>
    @error('observacao')<span class="erro">{{ $message }}</span>@enderror
</div>
