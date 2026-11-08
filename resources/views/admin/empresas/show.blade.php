@extends('layouts.admin')
@section('titulo', $organizador->nomeExibicao())

@php
    $S = \App\Http\Controllers\Admin\EmpresasController::SITUACOES;
    $O = ['todas' => 'Todas as operações'] + \App\Support\MotorTaxas::OPERACOES;
    $hoje = now()->toDateString();
@endphp

@section('conteudo')
<div class="cabecalho">
    <div><h1>{{ $organizador->nomeExibicao() }}</h1>
        <p>{{ $organizador->razao_social }} · {{ $organizador->documentoFormatado() }} · cadastro em {{ data_local($organizador->criado_em, 'd/m/Y') }}
            · <span class="selo {{ $organizador->situacao === 'ativo' ? 'selo--verde' : 'selo--amarelo' }}">{{ $S[$organizador->situacao] ?? $organizador->situacao }}</span></p></div>
    <div class="cabecalho__acoes">
        <form method="post" action="{{ route('admin.empresas.assumir', $organizador) }}" class="filtros">
            @csrf <div class="campo"><input name="motivo" placeholder="Motivo (opcional, fica no registro)" maxlength="255"></div>
            <button class="botao botao--primario">Assumir conta</button>
        </form>
    </div>
</div>

<div class="grade grade-4">
    <div class="cartao kpi"><x-icone nome="evento" /><small>Eventos</small><b>{{ numero($numeros->eventos) }}</b><small>{{ numero($numeros->publicados) }} publicados</small></div>
    <div class="cartao kpi"><x-icone nome="ingresso" /><small>Ingressos emitidos</small><b>{{ numero($numeros->ingressos) }}</b><small>em todos os lotes</small></div>
    <div class="cartao kpi"><x-icone nome="dinheiro" /><small>Receita bruta</small><b>{{ moeda($numeros->receita) }}</b><small>pedidos pagos</small></div>
    <div class="cartao kpi"><x-icone nome="equipe" /><small>Usuários ativos</small><b>{{ numero($numeros->usuarios) }}</b><small>na equipe</small></div>
</div>

<div class="grade grade-2" style="margin-top:18px">
    <section class="cartao" style="padding:20px" id="situacao">
        <h2 style="margin-top:0">Situação da conta</h2>
        <form method="post" action="{{ route('admin.empresas.situacao', $organizador) }}" class="form-grade">
            @csrf @method('PUT')
            <div class="campo col-6"><label>Situação</label><select name="situacao">
                @foreach (['ativo' => 'Ativo', 'pendente' => 'Em teste', 'suspenso' => 'Suspenso (bloqueado)', 'encerrado' => 'Encerrado (inativo)'] as $k => $t)
                    <option value="{{ $k }}" @selected($organizador->situacao === $k)>{{ $t }}</option>@endforeach</select></div>
            <div class="campo col-6"><label>Motivo</label><input name="motivo" value="{{ $organizador->situacao_motivo }}" maxlength="255"></div>
            <div class="col-12"><small class="suave">Fora de "Ativo", a empresa não cria nem publica eventos (regra do banco).</small></div>
            <div class="col-12"><button class="botao botao--escuro botao--pequeno">Salvar situação</button></div>
        </form>
    </section>
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Integrações</h2>
        @forelse ($contas as $c)
            <p><b>{{ $c->gateway }}</b> · <span class="selo {{ in_array($c->status, ['conectada', 'ativa', 'homologada']) ? 'selo--verde' : 'selo--cinza' }}">{{ \App\Models\ContaPagamento::STATUS[$c->status] ?? $c->status }}</span>
                · {{ $c->ambiente === 'producao' ? 'produção' : 'teste' }}<br><small>{{ $c->email_conta }}@if ($c->conectado_em) · desde {{ data_local($c->conectado_em, 'd/m/Y') }}@endif</small></p>
        @empty
            <p class="suave">Nenhuma conta de recebimento conectada.</p>
        @endforelse
    </section>
</div>

<section class="cartao" style="padding:20px;margin-top:18px" id="taxas">
    <h2 style="margin-top:0">Taxas e isenções desta empresa</h2>
    <p class="suave">Sobrepõem as <a href="{{ route('admin.taxas') }}">taxas padrão</a> no período informado. Campo em branco usa o valor padrão.
        Hoje, um ingresso de R$ 100 pagaria <b>{{ moeda($simulacao['taxa']) }}</b> ({{ $simulacao['origem'] }}).</p>
    @if ($taxas->isNotEmpty())
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Operação</th><th>Condição</th><th>Período</th><th>Observação</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($taxas as $t)
            @php $vigente = $t->inicio <= $hoje && (! $t->fim || $t->fim >= $hoje); @endphp
            <tr>
                <td>{{ $O[$t->operacao] ?? $t->operacao }}</td>
                <td>@if ($t->isento)<span class="selo selo--verde">Isenta</span>@else
                    {{ $t->percentual !== null ? numero($t->percentual, 2).'%' : 'padrão %' }} · fixo {{ $t->valor_fixo !== null ? moeda($t->valor_fixo) : 'padrão' }} · mínimo {{ $t->valor_minimo !== null ? moeda($t->valor_minimo) : 'padrão' }}@endif</td>
                <td>{{ \Illuminate\Support\Carbon::parse($t->inicio)->format('d/m/Y') }} → {{ $t->fim ? \Illuminate\Support\Carbon::parse($t->fim)->format('d/m/Y') : 'sem fim' }}
                    @if ($vigente)<span class="selo selo--azul">vigente</span>@endif</td>
                <td>{{ $t->observacao }}<br><small>{{ $t->autor }}</small></td>
                <td class="num">@if (! $t->fim || $t->fim >= $hoje)
                    <form method="post" action="{{ route('admin.empresas.taxas.encerrar', [$organizador, $t->id]) }}" data-confirmar="Encerrar esta taxa específica?">@csrf<button class="botao botao--perigo botao--pequeno">Encerrar</button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
    <h3 style="margin:18px 0 6px">Nova taxa ou isenção</h3>
    <form method="post" action="{{ route('admin.empresas.taxas', $organizador) }}" class="form-grade">
        @csrf
        <div class="campo col-4"><label>Operação</label><select name="operacao">@foreach ($O as $k => $t)<option value="{{ $k }}" @selected(old('operacao') === $k)>{{ $t }}</option>@endforeach</select></div>
        <div class="campo col-8"><label>&nbsp;</label><label class="interruptor"><input type="hidden" name="isento" value="0"><input type="checkbox" name="isento" value="1" @checked(old('isento'))><span></span> Isenção total no período</label></div>
        <div class="campo col-4"><label>Percentual (%)</label><input name="percentual" type="number" step="0.001" min="0" max="100" value="{{ old('percentual') }}" placeholder="padrão">@error('percentual')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-4"><label>Tarifa fixa (R$)</label><input name="valor_fixo" type="number" step="0.01" min="0" value="{{ old('valor_fixo') }}" placeholder="padrão"></div>
        <div class="campo col-4"><label>Taxa mínima (R$)</label><input name="valor_minimo" type="number" step="0.01" min="0" value="{{ old('valor_minimo') }}" placeholder="padrão"></div>
        <div class="campo col-4"><label>Início</label><input name="inicio" type="date" required value="{{ old('inicio', $hoje) }}"></div>
        <div class="campo col-4"><label>Fim (opcional)</label><input name="fim" type="date" value="{{ old('fim') }}">@error('fim')<small class="erro">{{ $message }}</small>@enderror</div>
        <div class="campo col-4"><label>Observação</label><input name="observacao" maxlength="255" value="{{ old('observacao') }}" placeholder="ex.: parceria de lançamento"></div>
        <div class="col-12"><button class="botao botao--primario botao--pequeno">Registrar</button></div>
    </form>
</section>

<div class="grade grade-2" style="margin-top:18px">
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Usuários</h2>
        @forelse ($usuarios as $u)
            <p><b>{{ $u->nome }}</b> · {{ $u->codigo === 'dono' ? 'Produtor' : $u->perfil }} · <span class="suave">{{ $u->status }}</span><br>
                <small>Login: <b>{{ $u->email }}</b>@if (! $u->ativo) · <span style="color:var(--vermelho)">conta desativada</span>@endif @if ($u->ultimo_login_em) · último acesso {{ data_local($u->ultimo_login_em) }}@endif</small></p>
            <form method="post" action="{{ route('admin.empresas.senha', $u->uuid) }}" data-confirmar="Enviar para {{ $u->email }} o link para criar uma nova senha?" style="margin:-6px 0 12px">@csrf
                <button class="botao botao--contorno botao--pequeno">Enviar link de nova senha</button></form>
        @empty <p class="suave">Nenhum usuário.</p> @endforelse
    </section>
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Acessos da Administração ("assumir conta")</h2>
        @forelse ($impersonacoes as $i)
            <p><b>{{ $i->admin }}</b> · {{ data_local($i->inicio) }} → {{ $i->fim ? data_local($i->fim, 'H:i') : 'em andamento' }} · {{ $i->acoes }} ação(ões)
                <br><small>{{ $i->motivo ?: 'sem motivo informado' }} · IP {{ $i->ip }}</small></p>
        @empty <p class="suave">Nenhum acesso registrado.</p> @endforelse
    </section>
</div>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Histórico de auditoria (últimos 30)</h2>
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Quando</th><th>Ação</th><th>Quem</th><th>Registro</th></tr></thead>
        <tbody>
        @forelse ($auditoria as $a)
            <tr><td>{{ data_local($a->ocorrido_em) }}</td><td>{{ $a->acao }}</td>
                <td>{{ $a->usuario ?? 'sistema' }}@if ($a->admin)<br><span class="selo selo--laranja">Administração: {{ $a->admin }}</span>@endif</td>
                <td><small>{{ $a->tabela }} #{{ $a->registro_id }}</small></td></tr>
        @empty <tr><td colspan="4" class="suave">Sem registros.</td></tr> @endforelse
        </tbody>
    </table></div>
</section>
@endsection
