@extends('layouts.painel')
@section('titulo', 'Agendamentos')

@section('conteudo')
<div class="cabecalho"><div><h1>Agendamentos</h1><p>Convites agendados, em envio e pausados.</p></div>
    <a href="{{ route('convites.index') }}" class="botao botao--primario">Novo convite</a></div>

<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">Como funciona o envio</h2>
    <ol style="margin:0;padding-left:20px;line-height:1.7">
        <li>Ao clicar em <b>Enviar para a lista</b>, o sistema copia os e-mails da lista para uma <b>fila</b> (tira quem se descadastrou e quem já recebeu convite do mesmo evento). Nada é enviado nesse clique.</li>
        <li>No servidor roda uma <b>tarefa automática a cada minuto</b> (o agendador do site; não depende de ninguém estar com o painel aberto). A cada minuto ela pega um pedaço da fila e envia pela conta de e-mail escolhida.</li>
        <li>Cada conta tem o seu <b>limite por hora</b>. Com 100 por hora, a tarefa manda até 40 por minuto até somar 100 na última hora; aí espera a hora liberar e continua. Ex.: 1.000 e-mails a 100/hora levam cerca de 10 horas.</li>
        <li>Cada conta tem o seu próprio limite: dividir uma lista grande em convites por contas diferentes envia mais rápido.</li>
        <li>Pode fechar o navegador: o envio continua sozinho. Você pode <b>pausar</b>, <b>retomar</b> ou <b>cancelar</b> a qualquer momento.</li>
        <li><b>Agendado</b>: a fila fica esperando e começa sozinha no dia e hora marcados.</li>
    </ol>
    <p class="suave" style="margin:12px 0 0">Última rodada da tarefa automática: {{ $ultimaRodada ? data_local($ultimaRodada, 'd/m/Y H:i') : 'ainda não registrada' }}.</p>
</section>

<section class="cartao" style="margin-top:18px">
    @if ($fila->isEmpty())<div class="vazio"><h3>Nada na fila</h3><p>Nenhum convite agendado ou em envio.</p></div>@else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Convite</th><th>Conta</th><th>Situação</th><th>Faltam</th><th>Previsão</th><th class="num"></th></tr></thead>
        <tbody>@foreach ($fila as $c)
            @php $horas = $c->limite_hora ? $c->pendentes / $c->limite_hora : 0; $inicio = $c->status === 'agendada' ? \Illuminate\Support\Carbon::parse($c->agendada_para, 'UTC') : now(); @endphp
            <tr><td><b>{{ $c->assunto }}</b><br><small class="suave">{{ $c->evento_titulo }}</small></td>
                <td>{{ $c->conta_nome }}<br><small class="suave">{{ $c->limite_hora }}/hora</small></td>
                <td>@include('painel.convites._situacao', ['s' => $c->status])@if ($c->status === 'agendada')<br><small>começa {{ data_local($c->agendada_para, 'd/m H:i') }}</small>@endif</td>
                <td>{{ $c->pendentes }} de {{ $c->total_destinatarios }}</td>
                <td>@if ($c->status === 'rascunho')<small class="suave">pausado</small>@else termina por volta de {{ data_local($inicio->copy()->addMinutes((int) ceil($horas * 60)), 'd/m H:i') }}@endif</td>
                <td class="num"><a href="{{ route('convites.show', $c->uuid) }}" class="botao botao--contorno botao--pequeno">Abrir</a></td></tr>
        @endforeach</tbody>
    </table></div>@endif
</section>
@endsection
