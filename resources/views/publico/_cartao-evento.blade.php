<a href="{{ route('evento.publico', $evento->slug) }}" class="pub-evento-card">
  <div class="pub-evento-card__data">
    <b>{{ data_local($evento->data_inicio, 'd') }}</b>
    <span>{{ \Illuminate\Support\Carbon::parse($evento->data_inicio)->setTimezone(fuso_exibicao())->locale('pt_BR')->translatedFormat('M Y') }}<br>{{ data_local($evento->data_inicio, 'H:i') }}</span>
  </div>
  <div class="pub-evento-card__corpo">
    <h3>{{ $evento->titulo }}</h3>
    @if ($evento->resumo)<p style="margin:0;color:var(--texto-suave)">{{ \Illuminate\Support\Str::limit($evento->resumo, 110) }}</p>@endif
    <small>{{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small>
  </div>
</a>
