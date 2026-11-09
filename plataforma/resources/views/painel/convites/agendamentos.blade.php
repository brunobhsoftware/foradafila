@extends('layouts.painel')
@section('titulo', 'Agendamentos')

@section('conteudo')
<div class="cabecalho"><div><h1>Agendamentos</h1><p>Convites agendados, em envio e pausados.</p></div>
    <a href="{{ route('convites.index') }}" class="botao botao--primario">Novo convite</a></div>

<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">Como funciona o envio</h2>
    <ol style="margin:0;padding-left:20px;line-height:1.7">
        <li>Ao clicar em <b>Enviar para a lista</b>, o sistema copia os e-mails da lista para uma <b>fila</b> (sem quem se descadastrou). Nada é enviado nesse clique. Cada convite manda <b>uma vez</b> para cada e-mail; um novo convite (outro assunto, outra semana) manda de novo para todos.</li>
        <li>No servidor roda uma <b>tarefa automática a cada minuto</b> (o agendador do site; não depende de ninguém estar com o painel aberto). A cada minuto ela pega um pedaço da fila.</li>
        <li>A empresa envia no máximo <b>{{ \App\Support\Convites::LIMITE_DIA }} e-mails por dia</b>, divididos igualmente entre as contas de e-mail ativas: hoje são <b>{{ $cap['contas']->count() }} conta(s) × {{ $cap['cota'] }} por dia</b>. Cada conta também respeita o seu limite por hora.</li>
        <li>Chegou ao limite do dia? A fila para e <b>continua sozinha no dia seguinte</b> (vira à meia-noite), até acabar a lista. Ex.: 2.000 e-mails = 4 dias.</li>
        <li>Vários convites na fila saem na ordem em que foram criados. Pode fechar o navegador; dá para <b>pausar</b>, <b>retomar</b> ou <b>cancelar</b> a qualquer momento.</li>
        <li><b>Agendado</b>: a fila fica esperando e começa sozinha no dia e hora marcados.</li>
    </ol>
    <p style="margin:12px 0 0"><b>Hoje:</b> {{ $cap['hoje'] }} enviados de {{ \App\Support\Convites::LIMITE_DIA }} · restam {{ $cap['restante'] }}.</p>
    <p class="suave" style="margin:12px 0 0">Última rodada da tarefa automática: {{ $ultimaRodada ? data_local($ultimaRodada, 'd/m/Y H:i') : 'ainda não registrada' }}.</p>
</section>

<section class="cartao" style="margin-top:18px">
    @if ($fila->isEmpty())<div class="vazio"><h3>Nada na fila</h3><p>Nenhum convite agendado ou em envio.</p></div>@else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Convite</th><th>Conta</th><th>Situação</th><th>Faltam</th><th>Previsão</th><th class="num"></th></tr></thead>
        <tbody>@foreach ($fila as $c)
            @php
                // previsão: a fila anda na ordem; soma os pendentes até este convite e divide pelo limite diário
                $acumulado = ($acumulado ?? $cap['hoje']) + ($c->status === 'rascunho' ? 0 : $c->pendentes);
                $dias = intdiv(max(0, $acumulado - 1), \App\Support\Convites::LIMITE_DIA);
                $fim = now('America/Sao_Paulo')->addDays($dias);
            @endphp
            <tr><td><b>{{ $c->assunto }}</b><br><small class="suave">{{ $c->evento_titulo }}</small></td>
                <td>{{ $c->conta_nome ?? 'Todas as contas' }}</td>
                <td>@include('painel.convites._situacao', ['s' => $c->status])@if ($c->status === 'agendada')<br><small>começa {{ data_local($c->agendada_para, 'd/m H:i') }}</small>@endif</td>
                <td>{{ $c->pendentes }} de {{ $c->total_destinatarios }}</td>
                <td>@if ($c->status === 'rascunho')<small class="suave">pausado</small>@elseif ($c->pendentes === 0) — @else {{ $dias === 0 ? 'termina hoje' : 'termina por volta de '.$fim->format('d/m') }}@endif</td>
                <td class="num"><a href="{{ route('convites.show', $c->uuid) }}" class="botao botao--contorno botao--pequeno">Abrir</a></td></tr>
        @endforeach</tbody>
    </table></div>@endif
</section>
@endsection
