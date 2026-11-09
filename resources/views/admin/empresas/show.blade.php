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

<section class="cartao" style="padding:20px;margin-top:18px" id="marketing">
    <div class="cartao__titulo" style="padding:0;border:0;margin-bottom:12px">
        <h2 style="margin:0">E-mail marketing</h2>
        <span class="selo {{ $marketing ? 'selo--verde' : 'selo--cinza' }}">{{ $marketing ? 'Liberado' : 'Desligado' }}</span>
    </div>
    <p class="suave">Libere o envio de <b>convites por e-mail</b> (Marketing do produtor) e defina quantos e-mails esta empresa pode enviar por dia.
        Desligado, a empresa não envia convites. O padrão é {{ \App\Support\Marketing::LIMITE_PADRAO }} por dia.</p>
    <form method="post" action="{{ route('admin.empresas.marketing', $organizador) }}" class="form-grade">@csrf @method('PUT')
        <div class="campo col-12"><input type="hidden" name="marketing_ativo" value="0">
            <label class="opcao"><input type="checkbox" name="marketing_ativo" value="1" @checked($marketing)>
                <span><b>Liberar e-mail marketing para esta empresa</b></span></label></div>
        <x-campo nome="marketing_limite" rotulo="Limite de e-mails por dia" tipo="number" :valor="$marketingLimite" obrigatorio classe="col-4" min="1" :max="\App\Support\Marketing::LIMITE_MAXIMO" />
        <div class="campo col-8" style="align-self:end"><button class="botao botao--primario">Salvar e-mail marketing</button></div>
    </form>
</section>

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
                <td class="num" style="white-space:nowrap">
                    <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="taxa-{{ $t->id }}">Editar</button>
                    @if (! $t->fim || $t->fim >= $hoje)
                    <form method="post" action="{{ route('admin.empresas.taxas.encerrar', [$organizador, $t->id]) }}" data-confirmar="Encerrar esta taxa específica?" style="display:inline">@csrf<button class="botao botao--perigo botao--pequeno">Encerrar</button></form>@endif</td>
            </tr>
            <tr class="linha-acoes" id="taxa-{{ $t->id }}" @unless ((string) old('_taxa') === (string) $t->id) hidden @endunless><td colspan="5">
                <form method="post" action="{{ route('admin.empresas.taxas.editar', [$organizador, $t->id]) }}" class="form-grade">@csrf @method('PUT')
                    <input type="hidden" name="_taxa" value="{{ $t->id }}">
                    @include('admin.empresas._taxa-campos', ['t' => (string) old('_taxa') === (string) $t->id ? null : $t])
                    <div class="col-12"><button class="botao botao--primario botao--pequeno">Salvar alterações</button></div>
                </form></td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
    <details style="margin-top:18px" @if ($taxas->isEmpty() || (old('operacao') && ! old('_taxa'))) open @endif>
    <summary style="cursor:pointer"><b>Nova taxa ou isenção</b> <small class="suave">— normalmente basta uma; edite a existente para mudar os valores</small></summary>
    <form method="post" action="{{ route('admin.empresas.taxas', $organizador) }}" class="form-grade" style="margin-top:10px">
        @csrf
        @include('admin.empresas._taxa-campos', ['t' => null])
        <div class="col-12"><button class="botao botao--primario botao--pequeno">Registrar</button></div>
    </form>
    </details>
</section>

<div class="grade grade-2" style="margin-top:18px">
    <section class="cartao" style="padding:20px">
        <h2 style="margin-top:0">Usuários</h2>
        @error('email')<p class="erro" style="color:var(--vermelho)">{{ $message }}</p>@enderror
        @forelse ($usuarios as $u)
            <p><b>{{ $u->nome }}</b> · {{ $u->codigo === 'dono' ? 'Produtor' : $u->perfil }} · <span class="suave">{{ $u->status }}</span><br>
                <small>Login: <b>{{ $u->email }}</b>@if (! $u->ativo) · <span style="color:var(--vermelho)">conta desativada</span>@endif @if ($u->ultimo_login_em) · último acesso {{ data_local($u->ultimo_login_em) }}@endif</small></p>
            <form method="post" action="{{ route('admin.empresas.senha', $u->uuid) }}" data-confirmar="Enviar para {{ $u->email }} o link para criar uma nova senha?" style="margin:-6px 0 12px">@csrf
                <button class="botao botao--contorno botao--pequeno">Enviar link de nova senha</button></form>
            @php $emailRuim = ! filter_var($u->email, FILTER_VALIDATE_EMAIL); @endphp
            @if ($emailRuim)<p style="color:var(--vermelho);margin:-4px 0 6px"><small>Este e-mail tem acento, cedilha ou espaço: nenhum e-mail chega nele. Corrija abaixo.</small></p>@endif
            <form method="post" action="{{ route('admin.empresas.email', $u->uuid) }}" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px">@csrf @method('PUT')
                <input name="email" type="text" value="{{ $emailRuim ? \Illuminate\Support\Str::ascii($u->email) : $u->email }}" style="max-width:280px" aria-label="Novo e-mail de login">
                <button class="botao botao--contorno botao--pequeno">Corrigir e-mail de login</button></form>
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
