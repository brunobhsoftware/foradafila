@extends('layouts.app-checkin')
@section('titulo', 'Eventos')

@section('corpo')
<header class="ck-barra">
  <h1>fora da fila<span class="ck-ponto">.</span> <small style="font:500 .85rem Inter;opacity:.75">check-in</small></h1>
  <form method="post" action="{{ route('sair') }}">@csrf<button aria-label="Sair">Sair</button></form>
</header>
<main class="ck-conteudo">
  <p style="margin:2px 4px 0;color:var(--suave)">Olá, {{ auth()->user()->nome }} · {{ app(\App\Support\Tenant::class)->organizador()?->nomeExibicao() }}</p>
  @foreach (['Hoje — check-in liberado' => $hoje, 'Próximos eventos' => $proximos, 'Últimos 30 dias' => $passados] as $titulo => $lista)
    @if ($lista->isNotEmpty())
      <div class="ck-secao">{{ $titulo }}</div>
      @foreach ($lista as $evento)
        @php $dl = $evento->data_inicio->copy()->setTimezone('America/Sao_Paulo'); $mes = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'][$dl->month - 1]; @endphp
        <a class="ck-evento" href="{{ route('app.evento', $evento) }}">
          <div class="ck-evento__capa" @if ($evento->imagem_capa_url) style="background-image:url('{{ $evento->imagem_capa_url }}')" @endif></div>
          @if ($hoje->contains('id', $evento->id))<span class="ck-selo">Check-in aberto</span>@endif
          <div class="ck-evento__titulo"><h2>{{ $evento->titulo }}</h2><small>{{ $dl->format('H:i') }} · {{ $evento->local?->cidade ?? 'Online' }}</small></div>
          <div class="ck-data"><small>{{ $mes }}.</small><b>{{ $dl->format('d') }}</b></div>
        </a>
      @endforeach
    @endif
  @endforeach
  @if ($hoje->isEmpty() && $proximos->isEmpty() && $passados->isEmpty())
    <div class="ck-cartao"><div class="ck-cartao__corpo">Nenhum evento publicado para você. O produtor libera os eventos em Equipe.</div></div>
  @endif
</main>
@endsection
