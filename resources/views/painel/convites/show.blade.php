@extends('layouts.painel')
@section('titulo', 'Convite — '.$c->assunto)

@section('conteudo')
<div class="cabecalho">
    <div><h1>{{ $c->assunto }}</h1><p>{{ $evento?->titulo }} · lista {{ $lista?->nome ?? '(excluída)' }} · {{ $c->smtp_conta_id ? 'pela conta '.$conta?->nome.' ('.$conta?->remetente_email.')' : 'por todas as contas da empresa' }}</p></div>
    <a href="{{ route('convites.index') }}" class="botao botao--contorno">Todos os convites</a>
</div>

@php $pct = $c->total_destinatarios ? round(100 * ($c->enviados + $c->erros + $pulados) / $c->total_destinatarios) : 100; @endphp
<section class="cartao" style="padding:20px">
    <p style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:0 0 10px">@include('painel.convites._situacao', ['s' => $c->status]) <span>{{ $pct }}% concluído</span></p>
    <div style="height:12px;background:#f2f4f7;border-radius:99px;overflow:hidden"><div style="height:100%;width:{{ $pct }}%;background:#F25C05"></div></div>
    <div class="grade grade-4" style="margin-top:16px;gap:10px">
        <div><small class="suave">Na lista</small><br><b style="font-size:1.4rem">{{ $c->total_destinatarios }}</b></div>
        <div><small class="suave">Enviados</small><br><b style="font-size:1.4rem">{{ $c->enviados }}</b></div>
        <div><small class="suave">Faltam</small><br><b style="font-size:1.4rem">{{ $pendentes }}</b></div>
        <div><small class="suave">Erros / pulados</small><br><b style="font-size:1.4rem">{{ $c->erros }} / {{ $pulados }}</b></div>
    </div>
    @if ($c->status === 'agendada')<p style="margin:14px 0 0"><b>Começa em {{ data_local($c->agendada_para, 'd/m/Y \à\s H:i') }}.</b></p>@endif
    @if ($c->status === 'enviando' && $pendentes)
        <p class="suave" style="margin:14px 0 0">Os e-mails saem aos poucos (a cada minuto um pouco), até {{ $limiteDia }} por dia para a empresa. O que não sair hoje continua amanhã. Pode fechar esta tela.</p>
    @endif
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
        @if ($c->status === 'enviando' && $pendentes)<form method="post" action="{{ route('convites.situacao', [$c->uuid, 'pausar']) }}">@csrf<button class="botao botao--contorno">Pausar</button></form>@endif
        @if ($c->status === 'rascunho')<form method="post" action="{{ route('convites.situacao', [$c->uuid, 'retomar']) }}">@csrf<button class="botao botao--primario">Retomar</button></form>@endif
        @if (in_array($c->status, ['enviando', 'rascunho', 'agendada']) && $pendentes)<form method="post" action="{{ route('convites.situacao', [$c->uuid, 'cancelar']) }}" onsubmit="return confirm('Cancelar o envio dos que faltam?')">@csrf<button class="botao botao--perigo">Cancelar envio</button></form>@endif
    </div>
</section>

@if ($ultimosErros->isNotEmpty())
<section class="cartao" style="margin-top:18px">
    <div class="cartao__titulo"><h2>Últimos erros</h2></div>
    <div class="tabela-rolagem"><table class="tabela"><thead><tr><th>E-mail</th><th>Erro</th></tr></thead><tbody>
        @foreach ($ultimosErros as $x)<tr><td>{{ $x->email }}</td><td><small>{{ $x->erro }}</small></td></tr>@endforeach
    </tbody></table></div>
</section>
@endif
@endsection
