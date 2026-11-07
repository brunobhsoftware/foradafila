{{-- Menu da conta, no alto do painel: empresa, e-mail da empresa e atalhos da pessoa logada. --}}
@php
    $org = $organizadorAtual;
    $iniciais = collect(explode(' ', $org->nomeExibicao()))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<details class="conta-menu">
    <summary aria-label="Menu da conta">
        @if ($org->logo_url)
            <img src="{{ $org->logo_url }}" alt="" class="conta-menu__logo" width="38" height="38">
        @else
            <span class="conta-menu__logo conta-menu__logo--iniciais" aria-hidden="true">{{ $iniciais }}</span>
        @endif
        <span class="conta-menu__texto">
            <b>{{ $org->nomeExibicao() }}</b>
            <small>{{ $org->email_contato }}</small>
        </span>
        <svg class="i conta-menu__seta" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="conta-menu__painel">
        <div class="conta-menu__quem">
            <b>{{ auth()->user()->nome }}</b>
            <small>{{ auth()->user()->email }} · {{ $tenant->vinculo()->perfil->nome }}</small>
        </div>
        <a href="{{ route('preferencias.edit') }}"><x-icone nome="perfil" /> Minhas preferências</a>
        <a href="{{ route('conta.senha') }}"><x-icone nome="editar" /> Trocar senha</a>
        <a href="{{ route('organizador.publico', $org->slug) }}" target="_blank" rel="noopener"><x-icone nome="olho" /> Minha página de eventos</a>
        @if ($temOutrasOrganizacoes)
            <a href="{{ route('organizacoes') }}"><x-icone nome="organizacao" /> Trocar de organização</a>
        @endif
        <a href="{{ route('inicio') }}"><x-icone nome="painel" /> Ir para o site</a>
        <form method="post" action="{{ route('sair') }}">
            @csrf
            <button type="submit"><x-icone nome="sair" /> Sair</button>
        </form>
    </div>
</details>
