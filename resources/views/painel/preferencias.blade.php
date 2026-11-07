@extends('layouts.painel')
@section('titulo', 'Preferências')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Preferências</h1><p>As notificações valem só para você ({{ auth()->user()->email }}); a exibição de eventos passados vale para a organização.</p></div>
</div>

@unless ($temV004)
    <div class="alerta alerta--info"><p>Esta tela fica disponível depois que o script <b>banco/V004</b> for importado no banco de dados.</p></div>
@else
    <form method="post" action="{{ route('preferencias.update') }}" class="cartao">
        @csrf @method('PUT')
        <section class="secao-form">
            <h2>Notificações por e-mail</h2>
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
        <section class="secao-form">
            <h2>Página pública da organização</h2>
            @if ($podeEditarOrg)
                <input type="hidden" name="mostrar_eventos_passados" value="0">
            @endif
            <label class="opcao">
                <input type="checkbox" name="mostrar_eventos_passados" value="1" @checked($org->mostrar_eventos_passados) @disabled(! $podeEditarOrg)>
                <span><b>Mostrar eventos passados</b><br>
                <small>A página pública ({{ route('organizador.publico', $org->slug) }}) lista também os eventos que já aconteceram.
                    Vale para toda a organização{{ $podeEditarOrg ? '' : '; só quem pode editar a organização altera esta opção' }}.</small></span>
            </label>
        </section>
        <div class="acoes-form"><button class="botao botao--primario">Salvar preferências</button></div>
    </form>
@endunless
@endsection
