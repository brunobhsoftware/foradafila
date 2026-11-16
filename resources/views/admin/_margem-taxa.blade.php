{{-- A taxa cobre a tarifa do Mercado Pago? Recebe $percentual, $fixo, $minimo. --}}
@php $mg = \App\Support\MargemTaxas::analisar((float) $percentual, (float) $fixo, (float) $minimo); @endphp
@if (! $mg['configurado'])
    <p class="suave" style="margin:6px 0 0"><small>Cadastre as taxas do Mercado Pago em <a href="{{ route('admin.mercadopago') }}#tarifas">Mercado Pago → Tarifa abatida da comissão</a> para conferir se esta taxa cobre a tarifa.</small></p>
@elseif ($mg['sem_taxa'])
    <p class="suave" style="margin:6px 0 0"><small>Sem taxa: a plataforma não retém comissão e não abate a tarifa do Mercado Pago (a empresa paga a tarifa dela).</small></p>
@else
    @php $ruim = $mg['sem_sobra']['pix'] || $mg['sem_sobra']['cartao']; @endphp
    <details class="margem-taxa" @if ($ruim) open @endif>
        <summary>
            @if ($ruim)<span class="selo selo--vermelho">Não cobre a tarifa do Mercado Pago</span>
            @else<span class="selo selo--verde">Cobre a tarifa do Mercado Pago</span>@endif
            <small class="suave">quanto sobra para a plataforma, por valor do ingresso</small>
        </summary>
        <div class="tabela-rolagem"><table class="tabela tabela--compacta">
            <thead><tr><th class="num">Ingresso</th><th class="num">Taxa</th><th class="num">Sobra no Pix ({{ numero($mg['pct']['pix'], 2) }}%)</th><th class="num">Sobra no cartão ({{ numero($mg['pct']['cartao'], 2) }}%)</th></tr></thead>
            <tbody>
            @foreach ($mg['linhas'] as $l)
                <tr><td class="num">{{ moeda($l['valor']) }}</td><td class="num">{{ moeda($l['taxa']) }}</td>
                    @foreach (['pix', 'cartao'] as $forma)
                        <td class="num">@if ($mg['pct'][$forma] <= 0)—@elseif ($l[$forma]['sobra'] <= 0)<b style="color:var(--vermelho)">{{ moeda($l[$forma]['sobra']) }}</b>@else{{ moeda($l[$forma]['sobra']) }}@endif</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <small class="suave">Conta do pior caso (taxa repassada ao comprador: a tarifa incide sobre ingresso + taxa). Valor negativo = a plataforma não retém nada e a empresa
            recebe menos que o valor cheio.<br>
            @if ((float) $percentual >= $mg['minimo_pct']['cartao'])
                O percentual <b>desta regra</b> ({{ numero($percentual, 2) }}%) já está acima do mínimo que cobre o cartão ({{ numero($mg['minimo_pct']['cartao'], 2) }}%). Não precisa mudar nada.
            @else
                Para cobrir o cartão em qualquer valor de ingresso, o percentual <b>desta regra</b> (hoje {{ numero($percentual, 2) }}%) teria de ser pelo menos
                <b>{{ numero($mg['minimo_pct']['cartao'], 2) }}%</b> (só Pix: {{ numero($mg['minimo_pct']['pix'], 2) }}%) — ou a taxa mínima/fixa compensa nos ingressos baratos, como mostra a tabela.
            @endif
            As taxas do Mercado Pago ({{ numero($mg['pct']['pix'], 2) }}% e {{ numero($mg['pct']['cartao'], 2) }}%) são as que ele cobra da empresa: não aumente esses números para "cobrir".</small>
    </details>
@endif
