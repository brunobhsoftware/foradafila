@extends('layouts.publico')
@section('titulo', 'Pagamento do pedido '.$pedido->codigo)

@section('conteudo')
<section class="pub-secao">
  <div class="container" style="max-width:640px">
    <div class="pub-cartao" style="text-align:center">
      <small>Pedido {{ $pedido->codigo }} · {{ $evento?->titulo }}</small>
      @if ($situacao === 'aprovado')
        <h1 style="font-size:1.6rem">Pagamento aprovado</h1>
        <p>Os ingressos foram emitidos e enviados para o seu e-mail.</p>
        <a href="{{ route('comprador.ingressos') }}" class="botao botao--amarelo">Ver meus ingressos</a>
      @elseif ($semEstoque)
        <h1 style="font-size:1.6rem">Pagamento recebido, mas os ingressos esgotaram</h1>
        <p>O pagamento foi confirmado depois que a reserva venceu e o lote esgotou. O valor será devolvido integralmente; se tiver dúvidas, fale com o organizador.</p>
      @elseif ($situacao === 'recusado' || in_array($pedido->status, ['recusado', 'expirado', 'cancelado'], true))
        <h1 style="font-size:1.6rem">Pagamento não concluído</h1>
        <p>O pagamento foi recusado, cancelado ou o prazo da reserva acabou. Nenhum valor foi cobrado por este pedido.</p>
        <a href="{{ route('evento.publico', $evento->slug) }}" class="botao botao--amarelo">Tentar de novo</a>
      @else
        <h1 style="font-size:1.6rem">Aguardando o pagamento</h1>
        <p>Assim que o Mercado Pago confirmar (no Pix costuma ser na hora), os ingressos aparecem em “Meus ingressos” e chegam por e-mail.
          Os ingressos ficam reservados até {{ data_local($pedido->expira_em, 'H:i') }}.</p>
        <p style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <a href="{{ request()->fullUrlWithQuery(['resultado' => 'conferir']) }}" class="botao botao--contorno">Já paguei, conferir</a>
          @if ($link)<a href="{{ $link }}" class="botao botao--amarelo">Voltar ao pagamento</a>@endif
        </p>
      @endif
    </div>
  </div>
</section>
@endsection
