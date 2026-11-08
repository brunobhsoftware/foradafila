@extends('layouts.painel')
@section('titulo', $f ? 'Editar formulário' : 'Novo formulário')

@php
    $grupos = ['identificacao' => 'Identificação', 'contato' => 'Contato', 'endereco' => 'Endereço', 'perfil' => 'Perfil', 'evento' => 'Do evento', 'aceites' => 'Aceites'];
    $pers = collect($personalizados)->values()->concat([null, null, null]);
@endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>{{ $f ? 'Editar formulário' : 'Novo formulário' }}</h1>
        <p>Marque os dados que serão pedidos para <b>cada participante</b> na compra. O nome completo é sempre pedido.</p></div>
    <div class="cabecalho__acoes"><a href="{{ route('formularios.index') }}" class="botao botao--contorno">Voltar</a></div>
</div>

<form method="post" action="{{ $f ? route('formularios.salvar', $f->id) : route('formularios.criar') }}" class="pilha">
    @csrf
    <section class="cartao" style="padding:20px 22px">
        <x-campo nome="nome" rotulo="Nome do formulário" :valor="old('nome', $f?->nome)" obrigatorio placeholder="Ex.: Show — nome, e-mail e CPF" />
    </section>

    <section class="cartao" style="padding:6px 0 10px">
        <div class="tabela-rolagem"><table class="tabela">
            <thead><tr><th>Campo</th><th>Como aparece para o comprador</th><th class="num">Pedir</th><th class="num">Obrigatório</th></tr></thead>
            <tbody>
            @foreach ($catalogo->groupBy('grupo') as $grupo => $itens)
                <tr><td colspan="4" style="background:#F4F5F7"><b>{{ $grupos[$grupo] ?? $grupo }}</b></td></tr>
                @foreach ($itens as $c)
                    @php $atual = $doCatalogo[$c->id] ?? null; $sempre = $c->codigo === 'nome'; @endphp
                    <tr>
                        <td>{{ $c->rotulo_padrao }}</td>
                        <td><input name="cat[{{ $c->id }}][rotulo]" value="{{ old("cat.{$c->id}.rotulo", $atual?->rotulo ?? $c->rotulo_padrao) }}" maxlength="120" style="max-width:320px"></td>
                        <td class="num"><input type="checkbox" name="cat[{{ $c->id }}][pedir]" value="1" @checked($sempre || old("cat.{$c->id}.pedir", $atual ? 1 : 0)) @disabled($sempre) aria-label="Pedir {{ $c->rotulo_padrao }}"></td>
                        <td class="num"><input type="checkbox" name="cat[{{ $c->id }}][obrigatorio]" value="1" @checked($sempre || old("cat.{$c->id}.obrigatorio", $atual?->obrigatorio)) @disabled($sempre) aria-label="{{ $c->rotulo_padrao }} obrigatório"></td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="cartao" style="padding:20px 22px">
        <h2 style="margin-top:0">Campos personalizados</h2>
        <p class="suave" style="margin-top:0">Perguntas próprias do evento (ex.: "Lote da empresa", "Número da camisa"). Deixe em branco para não usar. Para "Lista de opções", separe as opções por ponto e vírgula.</p>
        @foreach ($pers as $k => $p)
            <div class="form-grade" style="margin-bottom:8px;padding:10px 12px;background:#F4F5F7;border-radius:6px">
                <input type="hidden" name="pers[{{ $k }}][id]" value="{{ $p?->id }}">
                <div class="campo col-4"><label>Pergunta</label><input name="pers[{{ $k }}][rotulo]" value="{{ old("pers.$k.rotulo", $p?->rotulo) }}" maxlength="120"></div>
                <div class="campo col-3"><label>Tipo</label><select name="pers[{{ $k }}][tipo]">
                    @foreach (\App\Support\Formularios::TIPOS_PERSONALIZADOS as $v => $t)<option value="{{ $v }}" @selected(old("pers.$k.tipo", $p?->tipo_personalizado) === $v)>{{ $t }}</option>@endforeach</select></div>
                <div class="campo col-3"><label>Opções (lista)</label><input name="pers[{{ $k }}][opcoes]" value="{{ old("pers.$k.opcoes", $p && $p->opcoes ? implode('; ', json_decode($p->opcoes, true) ?: []) : '') }}" placeholder="P; M; G"></div>
                <div class="campo col-2" style="align-self:end"><label class="opcao"><input type="checkbox" name="pers[{{ $k }}][obrigatorio]" value="1" @checked(old("pers.$k.obrigatorio", $p?->obrigatorio))> Obrigatório</label></div>
            </div>
        @endforeach
        <p class="suave"><small>Precisa de mais perguntas? Salve e edite de novo: aparecem mais linhas em branco.</small></p>
    </section>

    <div class="acoes-form"><button class="botao botao--primario">Salvar formulário</button></div>
</form>
@endsection
