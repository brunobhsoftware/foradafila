@php $p = max(0, min(100, (int) $pct)); $comp = 251.3; @endphp
<div class="ck-medidor">
  <svg viewBox="0 0 200 120" aria-hidden="true">
    <path d="M20 110 A80 80 0 0 1 180 110" fill="none" stroke="#EAECF0" stroke-width="18" stroke-linecap="round"/>
    <path d="M20 110 A80 80 0 0 1 180 110" fill="none" stroke="{{ $cor }}" stroke-width="18" stroke-linecap="round"
      stroke-dasharray="{{ $comp }}" stroke-dashoffset="{{ round($comp * (1 - $p / 100), 1) }}" @if ($p === 0) stroke-opacity="0" @endif data-medidor="{{ $chave }}"/>
  </svg>
  <div class="num" data-num="{{ $chave }}">{{ number_format($num, 0, ',', '.') }}</div>
  <div class="pct" data-pct="{{ $chave }}">{{ $p }}%</div>
  <div class="rot">{{ $rotulo }}</div>
</div>
