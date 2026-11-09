@extends('layouts.painel')
@section('titulo', $lista->nome)

@section('conteudo')
<div class="cabecalho">
    <div><h1>{{ $lista->nome }}</h1><p>{{ number_format($total, 0, ',', '.') }} contato(s){{ $lista->descricao ? ' · '.$lista->descricao : '' }}</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('listas-email.index') }}" class="botao botao--contorno">Todas as listas</a>
        @if ($total)<a href="{{ route('listas-email.exportar', $lista->uuid) }}" class="botao botao--contorno">Exportar CSV</a>@endif
    </div>
</div>

<div class="grade grade-3" style="gap:14px">
    <section class="cartao" style="padding:18px">
        <h2 style="margin-top:0">Importar arquivo CSV</h2>
        <p class="suave">Separado por ; ou vírgula, até 10 MB. Com cabeçalho, reconhecemos as colunas <b>email</b>, <b>nome</b> e <b>telefone</b>; sem cabeçalho, a coluna com @.</p>
        <form method="post" action="{{ route('listas-email.importar', $lista->uuid) }}" enctype="multipart/form-data">@csrf
            <input type="file" name="arquivo" accept=".csv,.txt,text/csv" required>
            <label class="opcao" style="display:flex;gap:6px;margin-top:8px"><input type="checkbox" name="declaracao" value="1" required> <small>{{ \App\Http\Controllers\Painel\ListasEmailController::DECLARACAO }}.</small></label>
            <button class="botao botao--primario" style="margin-top:10px">Importar</button>
        </form>
    </section>
    <section class="cartao" style="padding:18px">
        <h2 style="margin-top:0">Colar e-mails</h2>
        <form method="post" action="{{ route('listas-email.colar', $lista->uuid) }}">@csrf
            <textarea name="texto" rows="5" placeholder="Um por linha: e-mail ou Nome; e-mail" style="width:100%" required></textarea>
            <label class="opcao" style="display:flex;gap:6px;margin-top:8px"><input type="checkbox" name="declaracao" value="1" required> <small>{{ \App\Http\Controllers\Painel\ListasEmailController::DECLARACAO }}.</small></label>
            <button class="botao botao--primario" style="margin-top:10px">Adicionar</button>
        </form>
    </section>
    <section class="cartao" style="padding:18px">
        <h2 style="margin-top:0">Trazer de eventos</h2>
        @if ($eventos->isEmpty())<p class="suave">Nenhum evento ainda.</p>@else
        <form method="post" action="{{ route('listas-email.eventos', $lista->uuid) }}">@csrf
            <div style="max-height:150px;overflow:auto;border:1px solid #e4e7ec;border-radius:8px;padding:8px">
                @foreach ($eventos as $e)<label class="opcao" style="display:flex;gap:6px;margin:2px 0"><input type="checkbox" name="eventos[]" value="{{ $e->uuid }}"> <span>{{ $e->titulo }} <small class="suave">{{ data_local($e->data_inicio, 'd/m/Y') }}</small></span></label>@endforeach
            </div>
            <select name="quem" style="margin-top:8px;width:100%">
                <option value="todos">Compradores e participantes</option><option value="compradores">Só compradores</option><option value="participantes">Só participantes</option>
            </select>
            <label class="opcao" style="display:flex;gap:6px;margin-top:8px"><input type="checkbox" name="declaracao" value="1" required> <small>{{ \App\Http\Controllers\Painel\ListasEmailController::DECLARACAO }}.</small></label>
            <button class="botao botao--primario" style="margin-top:10px">Trazer contatos</button>
        </form>
        @endif
    </section>
</div>

<section class="cartao" style="margin-top:18px">
    <form method="get" class="filtros" style="padding:14px 16px">
        <div class="campo" style="flex:1 1 280px"><label>Buscar</label><input name="busca" value="{{ $busca }}" placeholder="Nome ou e-mail"></div>
        <div class="campo" style="align-self:end"><button class="botao botao--contorno">Buscar</button></div>
    </form>
    @if ($contatos->isEmpty())
        <div class="vazio"><h3>{{ $busca ? 'Nada encontrado' : 'Lista vazia' }}</h3></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>E-mail</th><th>Nome</th><th>Telefone</th><th>Origem</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($contatos as $c)
            <tr><td>{{ $c->email }} @if ($c->descadastrado_em)<span class="selo selo--vermelho">Descadastrado</span>@endif</td><td>{{ $c->nome }}</td><td>{{ $c->telefone }}</td>
                <td><small>{{ str_starts_with($c->origem, 'evento:') ? 'Evento' : ($c->origem === 'csv' ? 'Arquivo CSV' : 'Manual') }}</small></td>
                <td class="num"><form method="post" action="{{ route('listas-email.contato.remover', [$lista->uuid, $c->id]) }}">@csrf<button class="botao botao--contorno botao--pequeno">Remover</button></form></td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div style="padding:12px 16px">{{ $contatos->links() }}</div>
    @endif
</section>

<section class="cartao" style="padding:20px;margin-top:18px">
    <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="lista-editar">Renomear ou excluir a lista</button>
    <div id="lista-editar" hidden style="margin-top:14px">
        <form method="post" action="{{ route('listas-email.renomear', $lista->uuid) }}" class="form-grade">@csrf
            <x-campo nome="nome" rotulo="Nome da lista" :valor="$lista->nome" obrigatorio classe="col-4" />
            <x-campo nome="descricao" rotulo="Descrição" :valor="$lista->descricao" classe="col-6" />
            <div class="campo col-2" style="align-self:end"><button class="botao botao--primario">Salvar</button></div>
        </form>
        <form method="post" action="{{ route('listas-email.excluir', $lista->uuid) }}" onsubmit="return confirm('Excluir a lista e todos os contatos dela?')" style="margin-top:10px">@csrf
            <button class="botao botao--perigo botao--pequeno">Excluir esta lista</button></form>
    </div>
</section>
@endsection
