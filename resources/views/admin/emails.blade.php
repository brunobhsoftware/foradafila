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

@if ($contas)
<section class="cartao" style="padding:18px 20px;margin-bottom:14px">
    <h2 style="margin-top:0;font-size:1.05rem">Quem tem esse e-mail</h2>
    @forelse ($contas['produtores'] as $u)
        <p style="margin:0 0 8px"><b>{{ $u->nome }}</b> — login de produtor <b>{{ $u->email }}</b>
            @if (! $u->ativo)<span class="selo selo--vermelho">desativado: não recebe o link</span>@endif
            @if (! $u->senha_hash)<span class="selo selo--amarelo">ainda sem senha</span>@endif</p>
        <form method="post" action="{{ route('admin.empresas.senha', $u->uuid) }}" style="margin:0 0 12px">@csrf <button class="botao botao--contorno botao--pequeno">Enviar link de nova senha</button></form>
    @empty
        <p class="suave" style="margin:0 0 8px">Nenhum login de produtor com esse e-mail — por isso o "Esqueci a senha" não envia nada.</p>
    @endforelse
    @foreach ($contas['organizacoes'] as $o)
        <p style="margin:0" class="suave">É o e-mail de contato da organização <b>{{ $o->nome_fantasia ?: $o->razao_social }}</b> (não serve para entrar; o login é o e-mail do usuário).</p>
    @endforeach
</section>
@endif

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
