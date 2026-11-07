@extends('layouts.admin')
@section('titulo', 'Empresas')

@php $S = \App\Http\Controllers\Admin\EmpresasController::SITUACOES; @endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Empresas</h1><p>Todas as organizações cadastradas na plataforma.</p></div></div>

<form method="get" class="cartao filtros" style="padding:16px">
    <div class="campo" style="flex:1 1 240px"><label>Buscar (nome ou CNPJ/CPF)</label><input name="busca" value="{{ $f['busca'] ?? '' }}"></div>
    <div class="campo"><label>Situação</label><select name="situacao"><option value="">Todas</option>
        @foreach ($S as $k => $t)<option value="{{ $k }}" @selected(($f['situacao'] ?? '') === $k)>{{ $t }}</option>@endforeach</select></div>
    <div class="campo"><label>Cadastro de</label><input type="date" name="de" value="{{ $f['de'] ?? '' }}"></div>
    <div class="campo"><label>até</label><input type="date" name="ate" value="{{ $f['ate'] ?? '' }}"></div>
    <button class="botao botao--escuro botao--pequeno">Filtrar</button>
    @if (array_filter($f))<a href="{{ route('admin.empresas') }}" class="botao botao--link">Limpar</a>@endif
</form>

<section class="cartao" style="margin-top:18px">
    @if ($empresas->isEmpty())
        <div class="vazio"><h3>Nenhuma empresa encontrada</h3></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>ID</th><th>Empresa</th><th>CNPJ/CPF</th><th>Plano / taxas</th><th>Situação</th><th>Cadastro</th><th class="num">Ações</th></tr></thead>
        <tbody>
        @foreach ($empresas as $e)
            <tr>
                <td>{{ $e->id }}</td>
                <td><a href="{{ route('admin.empresas.show', $e) }}"><b>{{ $e->nomeExibicao() }}</b></a>@if ($e->nome_fantasia && $e->nome_fantasia !== $e->razao_social)<br><small>{{ $e->razao_social }}</small>@endif</td>
                <td>{{ $e->documentoFormatado() }}</td>
                <td>{{ $planos[$e->id] ?? 'Padrão' }}@if ($especificas[$e->id] ?? 0)<br><span class="selo selo--azul">{{ $especificas[$e->id] }} taxa(s) específica(s)</span>@endif</td>
                <td><span class="selo {{ ['ativo' => 'selo--verde', 'pendente' => 'selo--amarelo', 'suspenso' => 'selo--laranja', 'bloqueado_debito' => 'selo--laranja'][$e->situacao] ?? 'selo--cinza' }}">{{ $S[$e->situacao] ?? $e->situacao }}</span></td>
                <td>{{ data_local($e->criado_em, 'd/m/Y') }}</td>
                <td class="num"><div class="acoes-linha">
                    <form method="post" action="{{ route('admin.empresas.assumir', $e) }}">@csrf<button class="botao botao--escuro botao--pequeno" title="Entrar no painel desta empresa">Assumir conta</button></form>
                    <a href="{{ route('admin.empresas.show', $e) }}#taxas" class="botao botao--contorno botao--pequeno">Taxas e isenções</a>
                    <a href="{{ route('admin.empresas.show', $e) }}#situacao" class="botao botao--contorno botao--pequeno">Status</a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
<x-paginacao :paginator="$empresas" />
@endsection
