@php $dl = \Illuminate\Support\Carbon::parse($evento->data_inicio)->setTimezone('America/Sao_Paulo'); $mes = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'][$dl->month - 1]; @endphp
<div class="ck-capa-grande" @if ($evento->imagem_capa_url) style="background-image:url('{{ $evento->imagem_capa_url }}')" @endif>
  <a href="{{ $voltar }}" class="ck-voltar" aria-label="Voltar">‹</a>
  <div class="ck-evento__titulo"><h2>{{ $evento->titulo }}</h2>
    <small>{{ $evento->local ? trim($evento->local->nome.' · '.$evento->local->cidade.'/'.$evento->local->uf, ' ·/') : 'Evento online' }}</small></div>
  <div class="ck-data"><small>{{ $mes }}.</small><b>{{ $dl->format('d') }}</b></div>
</div>
