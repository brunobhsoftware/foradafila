@extends('layouts.painel')
@section('titulo', 'Eventos')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Meus eventos</h1><p>Os eventos da organização que você pode acessar.</p></div>
    @can('evento.criar')
        <div class="cabecalho__acoes"><a href="{{ route('eventos.create') }}" class="botao botao--primario"><x-icone nome="mais" /> Novo evento</a></div>
    @endcan
</div>

<div class="cartao">
    <form method="get" class="cartao__corpo form-grade" style="padding-bottom:14px">
        <x-campo nome="busca" rotulo="Buscar" :valor="request('busca')" placeholder="Nome do evento" classe="col-6" />
        <x-campo nome="status" rotulo="Status" tipo="select" :opcoes="\App\Models\Evento::STATUS" :valor="request('status')" vazio="Todos" classe="col-4" />
        <div class="campo col-2" style="align-self:end"><button class="botao botao--contorno" style="width:100%">Filtrar</button></div>
    </form>

    @if ($eventos->isEmpty())
        <div class="vazio">
            <h3>{{ request()->hasAny(['busca', 'status']) ? 'Nenhum evento encontrado' : 'Nenhum evento ainda' }}</h3>
            <p>Comece pelo cadastro do evento; depois você cria os tipos de ingresso e os lotes.</p>
            @can('evento.criar')<a href="{{ route('eventos.create') }}" class="botao botao--primario">Criar evento</a>@endcan
        </div>
    @else
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Evento</th><th>Data</th><th>Status</th><th class="num">Vendidos</th><th class="num">Capacidade</th><th></th></tr></thead>
                <tbody>
                @foreach ($eventos as $evento)
                    @php $ind = $indicadores[$evento->id] ?? null; @endphp
                    <tr>
                        <td><a href="{{ route('eventos.show', $evento) }}"><b>{{ $evento->titulo }}</b></a><br>
                            <small>{{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small></td>
                        <td>{{ data_local($evento->data_inicio, 'd/m/Y') }}<br><small>{{ data_local($evento->data_inicio, 'H:i') }}</small></td>
                        <td><x-status-evento :status="$evento->status" /></td>
                        <td class="num">{{ numero($ind->ingressos_vendidos ?? 0) }}</td>
                        <td class="num">{{ numero($evento->capacidade) }}</td>
                        <td class="num"><a href="{{ route('eventos.show', $evento) }}" class="botao botao--contorno botao--pequeno">Abrir</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $eventos->links() }}
    @endif
</div>
@endsection
