@extends('layouts.painel')
@section('titulo', 'Formulários')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Formulários</h1><p>Os dados pedidos para cada participante na compra do ingresso. Cada evento escolhe o seu; quem não escolher usa o padrão.</p></div>
    @can('evento.criar')<div class="cabecalho__acoes"><a href="{{ route('formularios.novo') }}" class="botao botao--primario"><x-icone nome="mais" /> Novo formulário</a></div>@endcan
</div>

<section class="cartao">
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Formulário</th><th class="num">Campos</th><th class="num">Eventos</th><th></th></tr></thead>
        <tbody>
        @foreach ($formularios as $f)
            <tr><td><b>{{ $f->nome }}</b> @if ($f->padrao)<span class="selo selo--verde">Padrão</span>@endif</td>
                <td class="num">{{ $f->campos }}</td><td class="num">{{ $f->eventos }}</td>
                <td class="num" style="white-space:nowrap">
                    @can('evento.criar')
                        @unless ($f->padrao)<form method="post" action="{{ route('formularios.padrao', $f->id) }}" style="display:inline">@csrf <button class="botao botao--contorno botao--pequeno">Tornar padrão</button></form>@endunless
                        <a href="{{ route('formularios.editar', $f->id) }}" class="botao botao--contorno botao--pequeno">Editar</a>
                        @unless ($f->padrao)<form method="post" action="{{ route('formularios.excluir', $f->id) }}" style="display:inline" data-confirmar="Excluir o formulário “{{ $f->nome }}”?">@csrf @method('DELETE') <button class="botao botao--perigo botao--pequeno">Excluir</button></form>@endunless
                    @endcan
                </td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endsection
