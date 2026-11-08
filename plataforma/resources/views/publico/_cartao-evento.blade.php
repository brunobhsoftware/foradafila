<a href="{{ $evento->linkPublico() }}" class="pub-evento-card">
  @if ($evento->imagem_capa_url)
    <img class="pub-evento-card__img" src="{{ $evento->imagem_capa_url }}" alt="" width="1600" height="838" loading="lazy">
  @endif
  <div class="pub-evento-card__data">
    <b>{{ data_local($evento->data_inicio, 'd') }}</b>
    <span>{{ \Illuminate\Support\Carbon::parse($evento->data_inicio)->setTimezone(fuso_exibicao())->locale('pt_BR')->translatedFormat('M Y') }}<br>{{ data_local($evento->data_inicio, 'H:i') }}</span>
  </div>
  <div class="pub-evento-card__corpo">
    <h3>{{ $evento->titulo }}</h3>
    @if ($evento->resumo)<p style="margin:0;color:var(--texto-suave)">{{ \Illuminate\Support\Str::limit($evento->resumo, 110) }}</p>@endif
    <small>{{ $evento->local?->enderecoCurto() ?? \App\Models\Evento::FORMATOS[$evento->formato] }}</small>
    @php $preco = $evento->getAttributes()['preco_minimo'] ?? null; @endphp
    @if (! is_null($preco))
      <span class="pub-evento-card__preco">{{ (float) $preco > 0 ? 'A partir de '.moeda($preco) : 'Gratuito' }}</span>
    @endif
  </div>
</a>
