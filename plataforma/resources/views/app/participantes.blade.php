@extends('layouts.app-checkin')
@section('titulo', 'Participantes')

@section('corpo')
<header class="ck-barra">
  <a href="{{ route('app.evento', [$evento, 'aba' => 'checkin']) }}" aria-label="Voltar" style="font-size:1.5rem">‹</a>
  <h1>Participantes <small style="font:500 .85rem Inter;opacity:.75">{{ $numeros['entraram'] }}/{{ $numeros['total'] }}</small></h1>
</header>
<main class="ck-conteudo">
  <form class="ck-busca" method="get">
    <input type="search" name="q" value="{{ $b }}" placeholder="Nome, CPF ou número do ingresso" autocomplete="off">
    <input type="hidden" name="f" value="{{ $filtro }}">
  </form>
  <div class="ck-filtros">
    @foreach (['todos' => 'Todos', 'faltam' => 'Faltam', 'entraram' => 'Entraram'] as $k => $t)
      <a href="?{{ http_build_query(['q' => $b, 'f' => $k]) }}" @class(['ativa' => $filtro === $k])>{{ $t }}</a>
    @endforeach
  </div>
  @unless ($janela['aberta'])<div class="ck-aviso">{{ $janela['motivo'] }} O check-in só funciona no dia do evento.</div>@endunless
  <div class="ck-cartao" data-lista data-url="{{ route('checkin.registrar', $evento) }}">
    @forelse ($lista as $i)
      <div class="ck-pessoa">
        <div class="ck-pessoa__dados"><b>{{ $i->participante?->nome ?? 'Sem nome' }}</b>
          <small>{{ $i->numeroExibicao() }} · {{ $i->tipo?->nome }} — {{ $i->lote?->nome }}</small></div>
        @if ($i->status === 'utilizado')
          <span class="ok">✓ {{ $i->primeiro_checkin_em ? data_local($i->primeiro_checkin_em, 'H:i') : 'Entrou' }}</span>
        @elseif ($janela['aberta'] && Gate::allows('checkin.realizar'))
          <button type="button" data-checkin="{{ $i->uuid }}" data-nome="{{ $i->participante?->nome }}">Check-in</button>
        @endif
      </div>
    @empty
      <div class="ck-cartao__corpo">Ninguém encontrado.</div>
    @endforelse
  </div>
  @if ($lista->hasPages())
    <div class="ck-grade2">
      @if ($lista->previousPageUrl())<a class="ck-botao ck-botao--claro" href="{{ $lista->previousPageUrl() }}">‹ Anteriores</a>@else<span></span>@endif
      @if ($lista->nextPageUrl())<a class="ck-botao ck-botao--claro" href="{{ $lista->nextPageUrl() }}">Próximos ›</a>@endif
    </div>
  @endif
</main>
@endsection

@push('scripts')
<script>
  const listaCk = document.querySelector('[data-lista]'), csrfCk = document.querySelector('meta[name=csrf-token]').content;
  listaCk.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-checkin]'); if (!b) return;
    if (!confirm('Registrar a entrada de ' + (b.dataset.nome || 'este participante') + '?')) return;
    b.disabled = true; b.textContent = '...';
    try {
      const r = await fetch(listaCk.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfCk },
        body: JSON.stringify({ ingresso: b.dataset.checkin, sentido: 'entrada' }) });
      const j = r.status === 409 ? { aceito: false, mensagem: 'Já lido em outro portão' } : await r.json();
      if (j.aceito) { const s = document.createElement('span'); s.className = 'ok'; s.textContent = '✓ Agora'; b.replaceWith(s); if (navigator.vibrate) navigator.vibrate(80); }
      else { alert(j.mensagem || 'Não liberado'); b.disabled = false; b.textContent = 'Check-in'; }
    } catch (er) { alert('Sem conexão. Tente de novo.'); b.disabled = false; b.textContent = 'Check-in'; }
  });
</script>
@endpush
