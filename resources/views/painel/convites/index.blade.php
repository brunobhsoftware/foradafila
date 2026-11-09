@extends('layouts.painel')
@section('titulo', 'Convite por e-mail')

@section('conteudo')
<div class="cabecalho"><div><h1>Convite por e-mail</h1><p>Convide uma lista de e-mails para um evento. O e-mail é montado com a imagem, a data, o local e a descrição do evento; você só escreve o assunto.</p></div></div>

@unless ($marketing)
    <div class="alerta alerta--info"><p><b>E-mail marketing não liberado.</b> O envio de convites por e-mail é um recurso contratado à parte.
        Fale com o Fora da Fila para liberar o envio para a sua empresa.</p></div>
@elseif ($contas->isEmpty())
    <div class="alerta alerta--info"><p>Para enviar convites, cadastre antes uma conta de e-mail da sua empresa em
        @can('organizador.editar')<a href="{{ route('marketing.index') }}">Organização → Opções de marketing</a>@else Organização → Opções de marketing (peça ao produtor)@endcan.</p></div>
@elseif ($listas->isEmpty())
    <div class="alerta alerta--info"><p>Crie uma lista em <a href="{{ route('listas-email.index') }}">Listas de e-mail</a> antes de enviar.</p></div>
@elseif ($eventos->isEmpty())
    <div class="alerta alerta--info"><p>Nenhum evento publicado e futuro para convidar.</p></div>
@else
<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">Novo convite</h2>
    <form method="post" action="{{ route('convites.criar') }}" class="form-grade" data-convite>@csrf
        <div class="campo col-6"><label>Evento</label><select name="evento" data-convite-evento>
            @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected(old('evento') === $e->uuid)>{{ $e->titulo }} — {{ data_local($e->data_inicio, 'd/m/Y') }}</option>@endforeach</select></div>
        <div class="campo col-6"><label>Lista</label><select name="lista">
            @foreach ($listas as $l)<option value="{{ $l->uuid }}" @selected(old('lista') === $l->uuid)>{{ $l->nome }} ({{ number_format($l->total, 0, ',', '.') }})</option>@endforeach</select></div>
        @php $porConta = intdiv($limiteDia, max(1, $contas->count())); @endphp
        <div class="campo col-6"><label>Enviar pela conta</label><select name="smtp">
            <option value="">Todas as contas — divide automaticamente ({{ $contas->count() }} × {{ $porConta }} por dia)</option>
            @foreach ($contas as $c)<option value="{{ $c->uuid }}" @selected(old('smtp') === $c->uuid)>{{ $c->nome }} — {{ $c->remetente_email }} (só ela: {{ $porConta }} por dia)</option>@endforeach</select></div>
        <x-campo nome="assunto" rotulo="Assunto" obrigatorio classe="col-6" placeholder="Ex.: Você está convidado para a nossa festa!" maxlength="150" />
        <div class="campo col-12"><label>Quando enviar</label>
            <div class="grade grade-2" style="gap:10px">
                <label class="opcao-cartao"><input type="radio" name="quando" value="agora" @checked(old('quando', 'agora') === 'agora') data-quando>
                    <span><b>Começar agora</b><small>Os e-mails começam a sair no próximo minuto.</small></span></label>
                <label class="opcao-cartao"><input type="radio" name="quando" value="agendar" @checked(old('quando') === 'agendar') data-quando>
                    <span><b>Agendar</b><small>Escolha o dia e a hora para começar.</small></span></label>
            </div>
        </div>
        <x-campo nome="agendar_para" rotulo="Começar em" tipo="datetime-local" classe="col-6" data-agendar />
        <div class="campo col-12" style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="botao botao--primario" onclick="return confirm('Colocar o convite na fila de envio para toda a lista?')">Enviar para a lista</button>
            <button class="botao botao--contorno" formaction="{{ route('convites.teste') }}" formnovalidate>Enviar um teste para mim</button>
        </div>
    </form>
    <h3 style="margin:18px 0 8px">Pré-visualização</h3>
    <iframe data-convite-previa src="{{ route('convites.previa', ['evento' => old('evento', $eventos->first()->uuid)]) }}" title="Pré-visualização do convite" style="width:100%;height:560px;border:1px solid #e4e7ec;border-radius:10px;background:#fff"></iframe>
</section>
<script>
  const agendarCampo = document.querySelector('[data-agendar]').closest('.campo');
  const verQuando = () => { agendarCampo.hidden = document.querySelector('[data-quando]:checked')?.value !== 'agendar'; };
  document.querySelectorAll('[data-quando]').forEach((r) => r.addEventListener('change', verQuando)); verQuando();
  document.querySelector('[data-convite-evento]').addEventListener('change', (e) => {
    document.querySelector('[data-convite-previa]').src = @json(route('convites.previa')) + '?evento=' + encodeURIComponent(e.target.value);
  });
</script>
@endif

<section class="cartao" style="margin-top:18px">
    <div class="cartao__titulo"><h2>Convites enviados</h2></div>
    @if ($campanhas->isEmpty())<div class="vazio"><h3>Nenhum convite ainda</h3></div>@else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Assunto</th><th>Evento</th><th>Lista</th><th>Enviados</th><th>Situação</th><th class="num"></th></tr></thead>
        <tbody>@foreach ($campanhas as $c)
            <tr><td><b>{{ $c->assunto }}</b><br><small class="suave">{{ data_local($c->criado_em, 'd/m/Y H:i') }}</small></td><td>{{ $c->evento_titulo }}</td><td>{{ $c->lista_nome }}</td>
                <td>{{ $c->enviados }} / {{ $c->total_destinatarios }}@if ($c->erros) <small class="suave">({{ $c->erros }} erro(s))</small>@endif</td>
                <td>@include('painel.convites._situacao', ['s' => $c->status])</td>
                <td class="num"><a href="{{ route('convites.show', $c->uuid) }}" class="botao botao--contorno botao--pequeno">Acompanhar</a></td></tr>
        @endforeach</tbody>
    </table></div>@endif
</section>
@endsection
