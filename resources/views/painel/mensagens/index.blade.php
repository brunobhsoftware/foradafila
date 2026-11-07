@extends('layouts.painel')
@section('titulo', 'Mensagens')

@php
    $T = \App\Http\Controllers\Painel\MensagensController::TIPOS;
    $atual = $proprio ?? $herdado;
    $statusEnvio = ['enviado' => 'selo--verde', 'erro' => 'selo--laranja', 'pendente' => 'selo--amarelo'];
@endphp

@section('conteudo')
<div class="cabecalho"><div><h1>Mensagens</h1><p>Textos dos e-mails automáticos, comunicados para os participantes e histórico de envios.</p></div></div>

{{-- 1. Modelos --}}
<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">E-mails automáticos</h2>
    <form method="get" action="{{ route('mensagens.index') }}" class="filtros" style="margin-bottom:14px">
        <div class="campo"><label>Mensagem</label><select name="tipo" onchange="this.form.submit()">@foreach ($T as $k => $t)<option value="{{ $k }}" @selected($tipo === $k)>{{ $t['nome'] }}</option>@endforeach</select></div>
        <div class="campo" style="flex:1 1 260px"><label>Vale para</label><select name="evento" onchange="this.form.submit()">
            <option value="">Todos os eventos da organização</option>
            @foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected($eventoSel && $eventoSel->id === $e->id)>Só o evento: {{ $e->titulo }}</option>@endforeach</select></div>
    </form>
    <p class="suave">{{ $T[$tipo]['quando'] }}
        @if ($proprio) <span class="selo selo--azul">Texto personalizado</span>
        @elseif ($eventoSel) <span class="selo selo--cinza">Usando o texto {{ $herdado?->organizador_id ? 'da organização' : 'padrão' }}</span>
        @else <span class="selo selo--cinza">Usando o texto padrão</span> @endif</p>

    <form method="post" action="{{ route('mensagens.modelo') }}" class="form-grade" data-modelo-form data-previa="{{ route('mensagens.previa') }}">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}"><input type="hidden" name="evento" value="{{ $eventoSel?->uuid }}">
        <div class="campo col-12"><label>Assunto</label><input name="assunto" required maxlength="150" value="{{ old('assunto', $atual?->assunto) }}"></div>
        <div class="campo col-12"><label>Texto</label><textarea name="corpo_texto" rows="7" required maxlength="5000" class="campo-busca">{{ old('corpo_texto', $atual?->corpo_texto) }}</textarea>
            <small class="suave">Clique para inserir: @foreach ($T[$tipo]['variaveis'] as $var)<button type="button" class="chip-var" data-inserir="{{ str_repeat('{', 2).$var.str_repeat('}', 2) }}">{{ $var }}</button>@endforeach</small></div>
        @if ($tipo === 'lembrete_evento')
            <div class="campo col-4"><label>Enviar quantas horas antes</label><input name="antecedencia_h" type="number" min="1" max="720" value="{{ old('antecedencia_h', $atual?->antecedencia_h ?? 24) }}"></div>
        @endif
        <div class="col-12 acoes-linha" style="justify-content:flex-start">
            <button class="botao botao--primario botao--pequeno">Salvar texto</button>
            <button type="button" class="botao botao--contorno botao--pequeno" data-ver-previa>Pré-visualizar</button>
        </div>
        <div class="col-12 previa-msg" data-previa-caixa hidden><b data-previa-assunto></b><p data-previa-texto></p></div>
    </form>
    @if ($proprio)
        <form method="post" action="{{ route('mensagens.restaurar') }}" data-confirmar="Apagar o texto personalizado e voltar ao padrão?" style="margin-top:10px">
            @csrf <input type="hidden" name="tipo" value="{{ $tipo }}"><input type="hidden" name="evento" value="{{ $eventoSel?->uuid }}">
            <button class="botao botao--link">{{ $eventoSel ? 'Remover o texto próprio deste evento' : 'Restaurar o texto padrão' }}</button>
        </form>
    @endif
</section>

{{-- 2. Comunicado --}}
<section class="cartao" style="padding:20px;margin-top:18px" id="comunicado">
    <h2 style="margin-top:0">Enviar comunicado</h2>
    <p class="suave">Uma mensagem para quem tem ingresso válido no evento (ex.: mudança de horário, orientações de acesso). Até {{ \App\Http\Controllers\Painel\MensagensController::LIMITE_COMUNICADO }} e-mails por envio.</p>
    @error('comunicado')<div class="alerta alerta--erro"><p>{{ $message }}</p></div>@enderror
    @if ($eventos->isEmpty())<p class="suave">Nenhum evento ainda.</p>@else
    <form method="post" action="{{ route('mensagens.comunicado') }}" class="form-grade" data-confirmar="Enviar o comunicado agora?">
        @csrf
        <div class="campo col-6"><label>Evento</label><select name="evento">@foreach ($eventos as $e)<option value="{{ $e->uuid }}" @selected(old('evento') === $e->uuid)>{{ $e->titulo }} — {{ data_local($e->data_inicio, 'd/m/Y') }}</option>@endforeach</select></div>
        <div class="campo col-6"><label>Para</label><select name="destino">
            <option value="compradores" @selected(old('destino') === 'compradores')>Compradores (um e-mail por pedido)</option>
            <option value="participantes" @selected(old('destino') === 'participantes')>Participantes com e-mail</option></select></div>
        <div class="campo col-12"><label>Assunto</label><input name="assunto" required maxlength="150" value="{{ old('assunto') }}"></div>
        <div class="campo col-12"><label>Mensagem</label><textarea name="texto" rows="5" required maxlength="5000" class="campo-busca">{{ old('texto') }}</textarea></div>
        <div class="col-12"><button class="botao botao--escuro botao--pequeno">Enviar comunicado</button></div>
    </form>
    @endif
</section>

{{-- 3. Histórico --}}
<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Últimos envios</h2>
    @if ($historico->isEmpty())<p class="suave">Nenhum e-mail enviado ainda.</p>@else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Quando</th><th>Tipo</th><th>Para</th><th>Assunto</th><th>Situação</th></tr></thead>
        <tbody>
        @foreach ($historico as $h)
            <tr><td>{{ data_local($h->criado_em, 'd/m H:i') }}</td><td><small>{{ $h->tipo }}</small></td><td>{{ $h->destinatario }}</td><td>{{ $h->assunto }}</td>
                <td><span class="selo {{ $statusEnvio[$h->status] ?? 'selo--cinza' }}">{{ $h->status }}</span>@if ($h->erro)<br><small>{{ \Illuminate\Support\Str::limit($h->erro, 60) }}</small>@endif</td></tr>
        @endforeach
        </tbody>
    </table></div>@endif
</section>
@endsection
