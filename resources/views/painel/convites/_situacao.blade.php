@php $rot = ['enviando' => ['Enviando', 'selo--verde'], 'rascunho' => ['Pausado', ''], 'concluida' => ['Concluído', 'selo--verde'], 'cancelada' => ['Cancelado', 'selo--vermelho'], 'agendada' => ['Agendado', '']][$s] ?? [$s, '']; @endphp
<span class="selo {{ $rot[1] }}">{{ $rot[0] }}</span>
