@extends('layouts.painel')
@section('titulo', 'Check-in')

@php $R = \App\Http\Controllers\Painel\CheckinController::RESULTADOS; @endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Check-in</h1><p>Portaria pelo navegador: leia o QR Code do ingresso, digite o número ou busque pelo nome.</p></div></div>

@if ($eventos->isEmpty())
    <div class="cartao vazio"><h3>Nenhum evento ainda</h3></div>
@else
<form method="get" action="{{ route('checkin.index') }}" class="filtros cartao" style="padding:14px 16px">
    <div class="campo" style="flex:1 1 320px"><label>Evento</label>
        <select name="evento" onchange="this.form.submit()">
            @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($evento && $evento->id === $e->id)>{{ $e->titulo }} — {{ data_local($e->data_inicio, 'd/m/Y') }}</option>@endforeach
        </select></div>
</form>

<div class="checkin" data-checkin data-registrar="{{ route('checkin.registrar', $evento) }}" data-buscar="{{ route('checkin.buscar', $evento) }}">
    <section class="cartao checkin__leitura">
        <div class="segmentado" role="radiogroup" aria-label="Sentido">
            <label><input type="radio" name="sentido" value="entrada" checked><span>Entrada</span></label>
            @if ($evento->permite_reentrada)<label><input type="radio" name="sentido" value="saida"><span>Saída</span></label>@endif
        </div>
        <form class="checkin__form" data-checkin-form autocomplete="off">
            <label for="checkin-codigo">QR Code ou número do ingresso</label>
            <div class="copiar"><input id="checkin-codigo" name="codigo" placeholder="Aponte o leitor ou digite FF-{{ sprintf('%04d', $evento->id) }}-000123" autofocus>
                <button class="botao botao--primario">Validar</button></div>
        </form>
        <div class="acoes-linha" style="justify-content:flex-start;margin-top:10px">
            <button type="button" class="botao botao--escuro botao--pequeno" data-camera>Ler com a câmera</button>
            <small class="suave" data-camera-aviso hidden>Este navegador não lê QR pela câmera. Use um leitor USB/Bluetooth ou digite o número.</small>
        </div>
        <video data-video playsinline muted hidden></video>
        <div class="checkin__resultado" data-resultado hidden role="status" aria-live="assertive"></div>
    </section>

    <section class="cartao checkin__numeros">
        <div><small>Entraram</small><b data-n="entraram">{{ numero($numeros['entraram']) }}</b></div>
        <div><small>Ingressos válidos</small><b data-n="total">{{ numero($numeros['total']) }}</b></div>
        <div><small>Comparecimento</small><b><span data-n="pct">{{ $numeros['pct'] }}</span>%</b></div>
        @if ($evento->permite_reentrada)<div><small>Dentro agora</small><b data-n="dentro">{{ numero($numeros['dentro']) }}</b></div>@endif
        <div class="barra" style="grid-column:1/-1"><span data-barra style="width:{{ $numeros['pct'] }}%"></span></div>
        @if ($evento->abertura_portoes)<p class="suave" style="grid-column:1/-1;margin:0"><small>Portões abrem {{ data_local($evento->abertura_portoes, 'd/m H:i') }}.</small></p>@endif
    </section>

    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Busca manual</h2>
        <input class="campo-busca" data-busca placeholder="Nome, CPF ou número do ingresso" aria-label="Buscar participante">
        <div data-lista class="checkin__lista"></div>
    </section>

    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Últimas leituras</h2>
        @if ($ultimos->isEmpty())<p class="suave">Nenhuma leitura ainda.</p>@else
        <div class="tabela-rolagem"><table class="tabela">
            <thead><tr><th>Hora</th><th>Ingresso</th><th>Resultado</th><th>Operador</th><th></th></tr></thead>
            <tbody>
            @foreach ($ultimos as $u)
                <tr><td>{{ data_local($u->lido_em, 'H:i:s') }}</td>
                    <td>@if ($u->numero){{ sprintf('FF-%04d-%06d', $u->evento_id, $u->numero) }}<br><small>{{ $u->nome }}</small>@else<small>{{ \Illuminate\Support\Str::limit($u->codigo_lido, 24) }}</small>@endif</td>
                    <td><span class="selo {{ $u->resultado === 'aceito' ? 'selo--verde' : 'selo--laranja' }}">{{ $u->sentido === 'saida' && $u->resultado === 'aceito' ? 'Saída' : ($R[$u->resultado] ?? $u->resultado) }}</span></td>
                    <td><small>{{ $u->operador }}</small></td>
                    <td class="num">@can('checkin.configurar')@if ($u->resultado === 'aceito' && $u->sentido === 'entrada' && $u->status === 'utilizado')
                        <form method="post" action="{{ route('checkin.desfazer', $evento) }}" data-confirmar="Desfazer o check-in deste ingresso?">@csrf<input type="hidden" name="ingresso" value="{{ $u->uuid }}"><button class="botao botao--link">Desfazer</button></form>
                    @endif @endcan</td></tr>
            @endforeach
            </tbody>
        </table></div>@endif
    </section>
</div>
@endif
@endsection
