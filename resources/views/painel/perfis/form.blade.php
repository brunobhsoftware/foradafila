@extends('layouts.painel')
@php $editando = $perfil->exists; @endphp
@section('titulo', $editando ? 'Editar perfil' : 'Novo perfil')

@section('conteudo')
<div class="cabecalho">
    <div>
        <div class="trilha"><a href="{{ route('perfis.index') }}">Níveis de acesso</a> › {{ $editando ? $perfil->nome : 'Novo perfil' }}</div>
        <h1>{{ $editando ? 'Editar perfil' : 'Novo perfil' }}</h1>
        <p>Marque o que as pessoas com este perfil podem fazer.</p>
    </div>
</div>

<form method="post" action="{{ $editando ? route('perfis.update', $perfil) : route('perfis.store') }}" class="cartao">
    @csrf
    @if ($editando) @method('PUT') @endif
    <section class="secao-form">
        <div class="form-grade">
            <x-campo nome="nome" rotulo="Nome do perfil" :valor="$perfil->nome" obrigatorio classe="col-4" placeholder="Ex.: Bilheteria" />
            <x-campo nome="descricao" rotulo="Descrição" :valor="$perfil->descricao" classe="col-8" />
        </div>
    </section>
    @php $marcadasAtuais = array_map('intval', old('permissoes', $marcadas)); @endphp
    @foreach ($permissoes as $modulo => $lista)
        <section class="secao-form">
            <h3 style="margin-bottom:10px">{{ \App\Models\Permissao::MODULOS[$modulo] ?? $modulo }}</h3>
            <div class="opcoes">
                @foreach ($lista as $p)
                    <label class="opcao"><input type="checkbox" name="permissoes[]" value="{{ $p->id }}" @checked(in_array($p->id, $marcadasAtuais))>
                        <span>{{ $p->descricao }} <small>· {{ $p->codigo }}</small></span></label>
                @endforeach
            </div>
        </section>
    @endforeach
    <div class="acoes-form">
        <a href="{{ route('perfis.index') }}" class="botao botao--contorno">Cancelar</a>
        <button class="botao botao--primario">{{ $editando ? 'Salvar perfil' : 'Criar perfil' }}</button>
    </div>
</form>
@endsection
