@extends('layouts.admin')
@section('titulo', 'E-mails enviados')

@section('conteudo')
<div class="cabecalho"><div><h1>E-mails enviados</h1>
    <p>Os 100 últimos e-mails da plataforma: se saíram ou por que falharam. O conteúdo não aparece aqui.</p></div></div>

<form method="get" class="filtros" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <input name="busca" value="{{ $busca }}" placeholder="E-mail do destinatário" style="max-width:320px">
    <select name="status"><option value="">Todas as situações</option>
        @foreach (['enviado' => 'Enviado', 'erro' => 'Erro', 'pendente' => 'Pendente'] as $v => $t)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $t }}</option>@endforeach</select>
    <button class="botao botao--contorno botao--pequeno">Filtrar</button>
</form>

<section class="cartao">
    @if ($envios->isEmpty())
        <div class="vazio"><h3>Nenhum e-mail encontrado</h3></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Quando</th><th>Para</th><th>Assunto</th><th>Situação</th></tr></thead>
        <tbody>
        @foreach ($envios as $m)
            <tr><td><small>{{ data_local($m->criado_em) }}</small></td><td>{{ $m->destinatario }}<br><small class="suave">{{ $m->tipo }}</small></td><td>{{ $m->assunto }}</td>
                <td>@if ($m->status === 'enviado')<span class="selo selo--verde">Enviado</span>@elseif ($m->status === 'erro')<span class="selo selo--vermelho">Erro</span><br><small>{{ $m->erro }}</small>@else<span class="selo selo--amarelo">{{ ucfirst($m->status) }}</span>@endif</td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>
@endsection
