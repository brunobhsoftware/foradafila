{{-- Passo 1: enviar o código ao e-mail atual. Variáveis: $rota, $codigoEnviado --}}
<section class="secao-form">
    <h2>1. Receber o código</h2>
    <p>Enviaremos um código de 6 números para <b>{{ auth()->user()->email }}</b>. Ele vale por {{ \App\Support\CodigoVerificacao::VALIDADE_MIN }} minutos.</p>
    <form method="post" action="{{ route($rota) }}">
        @csrf
        <button class="botao {{ $codigoEnviado ? 'botao--contorno' : 'botao--escuro' }}">{{ $codigoEnviado ? 'Enviar outro código' : 'Enviar código por e-mail' }}</button>
        @if ($codigoEnviado)<small class="suave" style="margin-left:8px">Já enviamos um código. Confira a caixa de entrada e o spam.</small>@endif
    </form>
</section>
