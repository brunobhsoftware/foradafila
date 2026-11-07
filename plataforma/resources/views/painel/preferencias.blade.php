@extends('layouts.painel')
@section('titulo', 'Preferências de notificação')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Preferências de notificação</h1><p>Valem só para você ({{ auth()->user()->email }}) em {{ $organizadorAtual->nomeExibicao() }}.</p></div>
</div>

@unless ($temV004)
    <div class="alerta alerta--info"><p>Esta tela fica disponível depois que o script <b>banco/V004</b> for importado no banco de dados.</p></div>
@else
    <form method="post" action="{{ route('preferencias.update') }}" class="cartao">
        @csrf @method('PUT')
        <section class="secao-form">
            <div class="opcoes">
                <label class="opcao">
                    <input type="checkbox" name="notificar_compra" value="1" @checked($vinculo->notificar_compra)>
                    <span><b>Notificar-me cada vez que um participante realizar uma compra</b><br>
                    <small>Um e-mail por pedido confirmado, nos eventos que você pode ver.</small></span>
                </label>
                <label class="opcao">
                    <input type="checkbox" name="receber_novidades" value="1" @checked($vinculo->receber_novidades_em)>
                    <span><b>Quero receber as novidades do Fora da Fila por e-mail</b><br>
                    <small>@if ($vinculo->receber_novidades_em) Autorizado em {{ data_local($vinculo->receber_novidades_em) }}. @endif
                    Você pode desmarcar quando quiser.</small></span>
                </label>
            </div>
        </section>
        <div class="acoes-form"><button class="botao botao--primario">Salvar preferências</button></div>
    </form>
@endunless
@endsection
