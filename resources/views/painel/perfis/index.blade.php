@extends('layouts.painel')
@section('titulo', 'Níveis de acesso')

@section('conteudo')
<div class="cabecalho">
    <div>
        <div class="trilha"><a href="{{ route('equipe.index') }}">Equipe</a> › Níveis de acesso</div>
        <h1>Níveis de acesso</h1>
        <p>Cada perfil é um conjunto de permissões. Os perfis padrão não mudam; para ajustar, crie um perfil próprio a partir deles.</p>
    </div>
    <div class="cabecalho__acoes"><a href="{{ route('perfis.create') }}" class="botao botao--primario"><x-icone nome="mais" /> Novo perfil</a></div>
</div>

<div class="grade grade-3" style="margin-bottom:18px">
    @foreach ($perfis as $perfil)
        <div class="cartao cartao__corpo">
            <h3>{{ $perfil->nome }} <span class="selo {{ $perfil->ehDoSistema() ? '' : 'selo--laranja' }}">{{ $perfil->ehDoSistema() ? 'padrão' : 'personalizado' }}</span></h3>
            <p class="suave" style="margin:6px 0 10px;font-size:.9rem">{{ $perfil->descricao }}</p>
            <small>{{ $perfil->permissoes->count() }} permissões</small>
            <div style="margin-top:10px;display:flex;gap:8px">
                @if ($perfil->ehDoSistema())
                    @unless ($perfil->codigo === 'dono')<a href="{{ route('perfis.create', ['base' => $perfil->id]) }}" class="botao botao--contorno botao--pequeno">Duplicar e ajustar</a>@endunless
                @else
                    <a href="{{ route('perfis.edit', $perfil) }}" class="botao botao--contorno botao--pequeno">Editar</a>
                @endif
            </div>
        </div>
    @endforeach
</div>

<section class="cartao">
    <div class="cartao__titulo"><h2>O que cada perfil pode fazer</h2></div>
    <div class="tabela-rolagem">
        <table class="tabela matriz">
            <thead><tr><th>Permissão</th>@foreach ($perfis as $perfil)<th>{{ $perfil->nome }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($permissoes as $modulo => $lista)
                <tr class="modulo"><td colspan="{{ $perfis->count() + 1 }}">{{ \App\Models\Permissao::MODULOS[$modulo] ?? $modulo }}</td></tr>
                @foreach ($lista as $permissao)
                    <tr>
                        <td>{{ $permissao->descricao }}<br><small>{{ $permissao->codigo }}</small></td>
                        @foreach ($perfis as $perfil)
                            @php $tem = $perfil->permissoes->contains('id', $permissao->id); @endphp
                            <td><span class="{{ $tem ? 'sim' : 'nao' }}" aria-label="{{ $tem ? 'sim' : 'não' }}">{{ $tem ? '✓' : '—' }}</span></td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
