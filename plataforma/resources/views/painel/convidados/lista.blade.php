@extends('layouts.painel')
@section('titulo', $l->nome)

@php $S = \App\Http\Controllers\Painel\ConvidadosController::STATUS; @endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>{{ $l->nome }}</h1><p>{{ $evento->titulo }} · lote {{ $lote?->nome }} · até {{ $l->max_acompanhantes }} acompanhante(s) por convidado</p></div>
    <div class="cabecalho__acoes"><a href="{{ route('convidados.index', ['evento' => $evento->uuid]) }}" class="botao botao--contorno">Voltar</a>
        @can('pedido.emitir_manual')
        <form method="post" action="{{ route('convidados.emitir', [$evento, $l->id]) }}" data-confirmar="Emitir os ingressos de todos os convidados pendentes que têm e-mail?">@csrf<input type="hidden" name="todos" value="1">
            <button class="botao botao--primario">Emitir e enviar todos</button></form>
        @endcan
    </div>
</div>

@if ($errors->has('convidados') || $errors->has('lote'))
    <div class="alerta alerta--erro">@foreach (array_merge($errors->get('convidados'), $errors->get('lote')) as $m)<p>{{ $m }}</p>@endforeach</div>
@endif

<section class="cartao">
    @if ($convidados->isEmpty())
        <div class="vazio"><h3>Nenhum convidado ainda</h3></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Convidado</th><th>Acompanhantes</th><th>Situação</th><th>Pedido</th><th class="num">Ações</th></tr></thead>
        <tbody>
        @foreach ($convidados as $c)
            <tr @if ($c->status === 'cancelado') style="opacity:.55" @endif>
                <td><b>{{ $c->nome }}</b><br><small>{{ $c->email ?? 'sem e-mail' }}</small></td>
                <td>{{ $c->acompanhantes }}</td>
                <td><span class="selo {{ ['emitido' => 'selo--verde', 'cancelado' => 'selo--cinza'][$c->status] ?? 'selo--amarelo' }}">{{ $S[$c->status] ?? $c->status }}</span></td>
                <td>@if ($c->pedido_uuid)<a href="{{ route('pedidos.show', $c->pedido_uuid) }}">{{ $c->pedido_codigo }}</a>@else — @endif</td>
                <td class="num"><div class="acoes-linha">
                    @if (in_array($c->status, ['pendente', 'enviado', 'confirmado']))
                        @can('pedido.emitir_manual')
                        <form method="post" action="{{ route('convidados.emitir', [$evento, $l->id]) }}">@csrf<input type="hidden" name="convidado" value="{{ $c->id }}">
                            <button class="botao botao--escuro botao--pequeno" @disabled(! $c->email) title="{{ $c->email ? '' : 'Cadastre o e-mail para enviar' }}">Emitir e enviar</button></form>
                        @endcan
                    @endif
                    @if ($c->status !== 'cancelado')
                        <form method="post" action="{{ route('convidados.remover', [$evento, $l->id, $c->id]) }}" data-confirmar="Remover {{ $c->nome }} da lista?{{ $c->pedido_id ? ' Os ingressos emitidos serão cancelados.' : '' }}">@csrf
                            <button class="botao botao--perigo botao--pequeno">Remover</button></form>
                    @endif
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

<div class="grade grade-2" style="margin-top:18px">
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Adicionar convidado</h2>
        <form method="post" action="{{ route('convidados.adicionar', [$evento, $l->id]) }}" class="form-grade">
            @csrf
            <div class="campo col-12"><label>Nome</label><input name="nome" required maxlength="150"></div>
            <div class="campo col-6"><label>E-mail</label><input name="email" type="email" maxlength="150"></div>
            <div class="campo col-6"><label>Telefone</label><input name="telefone" data-mascara="telefone"></div>
            <div class="campo col-6"><label>Acompanhantes</label><input name="acompanhantes" type="number" min="0" max="{{ $l->max_acompanhantes }}" value="0"></div>
            <div class="col-12"><button class="botao botao--primario botao--pequeno">Adicionar</button></div>
        </form>
    </section>
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Adicionar vários de uma vez</h2>
        <form method="post" action="{{ route('convidados.adicionar', [$evento, $l->id]) }}">
            @csrf
            <textarea name="lote_texto" rows="7" class="campo-busca" placeholder="Um por linha: Nome; e-mail; acompanhantes&#10;Maria Souza; maria@email.com; 1&#10;João Lima; joao@email.com"></textarea>
            <p><small class="suave">Pode colar de uma planilha (colunas separadas por ponto e vírgula, vírgula ou tabulação).</small></p>
            <button class="botao botao--escuro botao--pequeno">Adicionar todos</button>
        </form>
    </section>
</div>

<section class="cartao" style="padding:20px;margin-top:18px">
    <details><summary><b>Editar lista</b></summary>@include('painel.convidados._lista-form', ['l' => $l])</details>
</section>
@endsection
