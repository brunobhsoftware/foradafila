@extends('layouts.admin')
@section('titulo', 'Log de erros')

@section('conteudo')
<div class="cabecalho"><div><h1>Log de erros</h1>
    <p>Os 200 últimos erros registrados pela plataforma.</p></div></div>

<form method="get" class="filtros" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <input name="busca" value="{{ $busca }}" placeholder="Contexto ou mensagem" style="max-width:320px">
    <button class="botao botao--contorno botao--pequeno">Filtrar</button>
</form>

@if ($erros->isEmpty())
    <div class="vazio"><h3>Nenhum erro registrado</h3></div>
@else
    <div class="tabela-rolagem">
        <table class="tabela">
            <thead><tr><th>Data</th><th>Contexto</th><th>Mensagem</th><th>Org</th><th>URL</th></tr></thead>
            <tbody>
            @foreach ($erros as $e)
                <tr>
                    <td style="white-space:nowrap"><small>{{ \Illuminate\Support\Carbon::parse($e->criado_em)->timezone('America/Sao_Paulo')->format('d/m H:i:s') }}</small></td>
                    <td><code>{{ $e->contexto }}</code></td>
                    <td style="max-width:420px;word-break:break-all"><small>{{ \Illuminate\Support\Str::limit($e->mensagem, 300) }}</small></td>
                    <td>{{ $e->organizador_id }}</td>
                    <td style="max-width:200px;word-break:break-all"><small>{{ $e->url ? parse_url($e->url, PHP_URL_PATH) : '' }}</small></td>
                </tr>
                @if ($e->detalhes)
                    <tr><td colspan="5" style="padding:4px 12px 12px;border-top:0">
                        <details><summary style="cursor:pointer;font-size:.85rem;color:var(--suave)">Detalhes</summary>
                            <pre style="font-size:.78rem;white-space:pre-wrap;word-break:break-all;margin:6px 0 0;max-height:300px;overflow:auto">{{ $e->detalhes }}</pre>
                        </details>
                    </td></tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
