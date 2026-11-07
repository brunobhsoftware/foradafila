@extends('layouts.painel')
@section('titulo', 'Equipe')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Equipe</h1><p>Quem acessa a área do produtor e o que cada pessoa pode fazer.</p></div>
    <div class="cabecalho__acoes"><a href="{{ route('perfis.index') }}" class="botao botao--contorno"><x-icone nome="perfil" /> Níveis de acesso</a></div>
</div>

@if ($convite = session('link_convite'))
    <div class="alerta alerta--info">
        <div style="width:100%">
            <b>O convite foi enviado por e-mail para {{ $convite['nome'] }}.</b> Se preferir, mande também este link por WhatsApp. Ele vale por 72 horas e só pode ser usado uma vez.
            <div class="copiar"><input id="link-convite" value="{{ $convite['url'] }}" readonly><button type="button" class="botao botao--escuro botao--pequeno" data-copiar="link-convite">Copiar link</button></div>
        </div>
    </div>
@endif

<div class="grade grade-lateral">
    <section class="cartao">
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Pessoa</th><th>Perfil</th><th>Eventos</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                @foreach ($vinculos as $v)
                    <tr>
                        <td><b>{{ $v->usuario->nome }}</b>@if ($v->usuario_id === auth()->id()) <small>(você)</small>@endif<br><small>{{ $v->usuario->email }}</small></td>
                        <td>{{ $v->perfil->nome }}</td>
                        <td><small>{{ $v->todos_eventos ? 'Todos' : $v->eventos->pluck('titulo')->implode(', ') }}</small></td>
                        <td><span class="selo {{ ['ativo' => 'selo--verde', 'convidado' => 'selo--amarelo', 'suspenso' => 'selo--vermelho'][$v->status] ?? '' }}">{{ \App\Models\OrganizadorUsuario::STATUS[$v->status] }}</span>
                            @if ($v->status === 'convidado' && $v->convite_expira_em)<br><small>{{ $v->convite_expira_em->isPast() ? 'expirou' : 'até '.data_local($v->convite_expira_em, 'd/m H:i') }}</small>@endif</td>
                        <td class="num" style="white-space:nowrap">
                            @if ($v->status === 'convidado')
                                <form method="post" action="{{ route('equipe.reenviar', $v) }}" style="display:inline-block;margin-right:6px">
                                    @csrf <button class="botao botao--escuro botao--pequeno" title="Gera um novo link (o anterior deixa de valer) e envia por e-mail">Reenviar convite</button>
                                </form>
                            @endif
                            <details class="painel-recolhivel" style="display:inline-block"><summary class="botao botao--contorno botao--pequeno">Alterar</summary>
                                <form method="post" action="{{ route('equipe.update', $v) }}" style="text-align:left;min-width:min(380px,80vw)" class="pilha">
                                    @csrf @method('PUT')
                                    @include('painel.equipe.campos-acesso', ['perfilAtual' => $v->perfil_id, 'todos' => $v->todos_eventos, 'marcados' => $v->eventos->pluck('id')->all(), 'sufixo' => $v->id])
                                    @if ($v->status === 'convidado')
                                        <input type="hidden" name="status" value="convidado">
                                    @else
                                        <x-campo nome="status" rotulo="Situação" tipo="select" :opcoes="['ativo' => 'Ativo', 'suspenso' => 'Suspenso (sem acesso)']" :valor="$v->status" />
                                    @endif
                                    <button class="botao botao--escuro botao--pequeno">Salvar</button>
                                </form>
                                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
                                    @unless ($v->usuario_id === auth()->id())
                                        <form method="post" action="{{ route('equipe.destroy', $v) }}" data-confirmar="Remover {{ $v->usuario->nome }} da equipe?">@csrf @method('DELETE')<button class="botao botao--perigo botao--pequeno">Remover</button></form>
                                    @endunless
                                </div>
                            </details>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <aside class="cartao">
        <div class="cartao__titulo"><h2>Convidar pessoa</h2></div>
        <form method="post" action="{{ route('equipe.store') }}" class="cartao__corpo pilha">
            @csrf
            <x-campo nome="nome" rotulo="Nome" obrigatorio />
            <x-campo nome="email" rotulo="E-mail" tipo="email" obrigatorio />
            @include('painel.equipe.campos-acesso', ['perfilAtual' => old('perfil_id'), 'todos' => old('alcance', 'todos') === 'todos', 'marcados' => old('eventos', []), 'sufixo' => 'novo'])
            <button class="botao botao--primario botao--largo">Gerar convite</button>
            <small>Ex.: para a portaria do dia do evento, use o perfil <b>Check-in</b> e marque só aquele evento.</small>
        </form>
    </aside>
</div>
@endsection
