{{-- Formulário de cancelamento (ingresso ou pedido). Variáveis: $acao, $valor (a devolver), $rotulo --}}
<form method="post" action="{{ $acao }}" class="form-grade cancelar-form" style="text-align:left;margin-top:8px"
      data-confirmar="Confirmar o cancelamento? Os ingressos deixam de valer para a entrada.">
    @csrf
    @if ($valor > 0)
        <div class="col-12 alerta alerta--info" style="margin:0"><p>Valor pago a devolver ao comprador: <b>{{ moeda($valor) }}</b>.
            A devolução é responsabilidade da organização, pelo mesmo meio de pagamento da compra.</p></div>
    @else
        <div class="col-12"><small class="suave">Ingresso gratuito: não há valor a devolver.</small></div>
    @endif
    <div class="campo col-6"><label>Motivo</label><select name="motivo">@foreach (\App\Support\CancelamentoIngressos::MOTIVOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
    <div class="campo col-6"><label>Observação (opcional)</label><input name="observacao" maxlength="500"></div>
    @if ($valor > 0)
        <div class="col-12"><label class="interruptor"><input type="hidden" name="devolvido" value="0"><input type="checkbox" name="devolvido" value="1"><span></span> Já devolvi o valor ao comprador</label></div>
    @endif
    <div class="col-12"><label class="interruptor"><input type="hidden" name="avisar" value="0"><input type="checkbox" name="avisar" value="1" checked><span></span> Avisar o comprador por e-mail</label></div>
    <div class="col-12"><button class="botao botao--perigo botao--pequeno">{{ $rotulo }}</button></div>
</form>
