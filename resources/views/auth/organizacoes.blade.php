@extends('layouts.acesso')
@section('titulo', 'Escolher organização')
@section('chamada', 'Em qual organização você vai trabalhar agora?')

@section('conteudo')
<h2>Escolha a organização</h2>
<p class="subtitulo">Você faz parte de mais de uma equipe.</p>
<div class="opcoes">
    @foreach ($vinculos as $vinculo)
        @php $org = $organizadores[$vinculo->organizador_id]; @endphp
        <form method="post" action="{{ route('organizacoes') }}">
            @csrf
            <input type="hidden" name="organizador_id" value="{{ $org->id }}">
            <button type="submit" class="cartao cartao__corpo" style="width:100%;text-align:left;cursor:pointer;font:inherit">
                <b style="color:var(--tinta)">{{ $org->nomeExibicao() }}</b><br>
                <small>{{ $vinculo->perfil->nome }} · {{ $org->documentoFormatado() }}</small>
            </button>
        </form>
    @endforeach
</div>
<form method="post" action="{{ route('sair') }}" class="acesso__rodape">@csrf<button class="botao botao--link">Sair</button></form>
@endsection
