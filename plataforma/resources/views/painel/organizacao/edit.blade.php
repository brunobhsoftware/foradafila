@extends('layouts.painel')
@section('titulo', 'Organização')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Organização</h1><p>Dados do organizador e da página pública. O nome e o documento aparecem para o comprador como responsáveis pelo evento.</p></div>
    <span class="selo {{ $org->situacao === 'ativo' ? 'selo--verde' : 'selo--vermelho' }}">{{ \App\Models\Organizador::SITUACOES[$org->situacao] }}</span>
</div>

@unless ($temV004)
    <div class="alerta alerta--info"><p><b>Campos novos aguardando o banco de dados:</b> representante legal, dados bancários e chave de acesso da API
        aparecem aqui depois que o script <b>banco/V004</b> for importado no phpMyAdmin.</p></div>
@elseif (! $org->dadosCompletos())
    <div class="alerta alerta--info"><p><b>Complete os campos obrigatórios:</b> representante legal, CPF do representante e dados bancários.</p></div>
@endif



<form method="post" action="{{ route('organizacao.update') }}" class="cartao" enctype="multipart/form-data">
    @csrf @method('PUT')
    <section class="secao-form">
        <h2>Identificação</h2>
        <p>{{ $org->tipo_pessoa === 'PJ' ? 'CNPJ' : 'CPF' }} {{ $org->documentoFormatado() }} — para trocar o documento, fale com o suporte.</p>
        <div class="form-grade">
            <x-campo nome="razao_social" :rotulo="$org->tipo_pessoa === 'PJ' ? 'Razão social' : 'Nome completo'" :valor="$org->razao_social" obrigatorio classe="col-6" />
            <x-campo nome="nome_fantasia" rotulo="Nome que o público conhece" :valor="$org->nome_fantasia" classe="col-6" />
            <x-campo nome="email_contato" rotulo="E-mail de atendimento ao comprador" tipo="email" :valor="$org->email_contato" obrigatorio classe="col-6" />
            <x-campo nome="email_financeiro" rotulo="E-mail financeiro" tipo="email" :valor="$org->email_financeiro" obrigatorio classe="col-6" ajuda="Recebe faturas e avisos de pagamento." />
        </div>
    </section>

    @if ($temV004)
        <section class="secao-form">
            <h2>Representante legal</h2>
            <p>Pessoa que responde legalmente pela organização.</p>
            <div class="form-grade">
                <x-campo nome="representante_nome" rotulo="Representante legal" :valor="$org->representante_nome" obrigatorio classe="col-8" autocomplete="name" />
                <x-campo nome="representante_cpf" rotulo="CPF do representante legal" :valor="$org->representante_cpf" obrigatorio classe="col-4" data-mascara="cpf" inputmode="numeric" />
            </div>
        </section>

        <section class="secao-form">
            <h2>Dados bancários</h2>
            <p>Conta da organização para repasses e acertos com o Fora da Fila.</p>
            <div class="form-grade">
                <x-campo nome="banco" rotulo="Banco" :valor="$org->banco" obrigatorio classe="col-6" list="lista-bancos" placeholder="Ex.: 341 - Itaú Unibanco" autocomplete="off" />
                <x-campo nome="agencia" rotulo="Agência" :valor="$org->agencia" obrigatorio classe="col-2" inputmode="numeric" placeholder="1234" />
                <x-campo nome="conta" rotulo="Conta" :valor="$org->conta" obrigatorio classe="col-4" placeholder="12345-6" ajuda="Com o dígito." />
                <x-campo nome="favorecido" rotulo="Favorecido" :valor="$org->favorecido ?? $org->razao_social" obrigatorio classe="col-12" ajuda="Titular da conta, como está no banco." />
            </div>
            <datalist id="lista-bancos">@foreach (\App\Http\Controllers\Painel\OrganizacaoController::BANCOS as $b)<option value="{{ $b }}">@endforeach</datalist>
        </section>
    @endif

    <section class="secao-form">
        <h2>Logomarca</h2>
        <div class="form-grade">
            <x-upload-imagem nome="logo" rotulo="Logo" :atual="$org->logo_url" :largura="136" :altura="136"
                texto="Dimensão recomendada 136x136. Formato JPEG, GIF ou PNG. Tamanho máximo de 2MB." />
        </div>
    </section>

    <section class="secao-form">
        <h2>Página pública</h2>
        <p>Lista os seus eventos publicados.</p>
        <div class="form-grade">
            <x-campo nome="slug" rotulo="Endereço da página" :valor="$org->slug" obrigatorio classe="col-6" ajuda="foradafila.com.br/p/{{ $org->slug }}" />
            <x-campo nome="site" rotulo="Site" tipo="url" :valor="$org->site" classe="col-6" placeholder="https://" />
            <x-campo nome="instagram" rotulo="Instagram" :valor="$org->instagram" classe="col-6" placeholder="@seuperfil" />
            <x-campo nome="whatsapp" rotulo="WhatsApp" :valor="$org->whatsapp" classe="col-6" data-mascara="telefone" />
            <x-campo nome="descricao" rotulo="Sobre a organização" tipo="textarea" :valor="$org->descricao" rows="4" />
            @if ($temV004)
                <div class="campo col-12">
                    <input type="hidden" name="mostrar_eventos_passados" value="0">
                    <label class="opcao"><input type="checkbox" name="mostrar_eventos_passados" value="1" @checked(old('mostrar_eventos_passados', $org->mostrar_eventos_passados))>
                        <span><b>Mostrar eventos passados</b><br><small>A página pública lista também os eventos que já aconteceram, abaixo dos próximos.</small></span></label>
                </div>
            @endif
        </div>
    </section>
    <section class="secao-form">
        <h2>Endereço</h2>
        <div class="form-grade">
            <x-campo nome="cep" rotulo="CEP" :valor="$org->cep" classe="col-3" data-mascara="cep" data-cep="" inputmode="numeric" />
            <x-campo nome="logradouro" rotulo="Endereço" :valor="$org->logradouro" classe="col-6" />
            <x-campo nome="numero" rotulo="Número" :valor="$org->numero" classe="col-3" />
            <x-campo nome="complemento" rotulo="Complemento" :valor="$org->complemento" classe="col-4" />
            <x-campo nome="bairro" rotulo="Bairro" :valor="$org->bairro" classe="col-4" />
            <x-campo nome="cidade" rotulo="Cidade" :valor="$org->cidade" classe="col-3" />
            <x-campo nome="uf" rotulo="UF" :valor="$org->uf" classe="col-1" maxlength="2" />
        </div>
    </section>
    <div class="acoes-form"><button class="botao botao--primario">Salvar</button></div>
</form>

@if ($temV004)
    <section class="cartao" style="margin-top:18px">
        <div class="cartao__titulo"><h2>Chave de acesso da API</h2><span class="selo">Em breve</span></div>
        <div class="cartao__corpo">
            <div class="campo">
                <label for="c_chave_api">Chave de acesso da API</label>
                <input id="c_chave_api" value="{{ $org->api_chave_prefixo ? $org->api_chave_prefixo.'…' : '' }}" placeholder="Bloqueado — a geração do token de integração entra em uma próxima etapa" disabled>
                <span class="ajuda">Campo bloqueado. Mais adiante haverá um botão para gerar o token de integração com sistemas da sua organização.</span>
            </div>
        </div>
    </section>
@endif

<p style="margin-top:18px"><a href="{{ route('preferencias.edit') }}">Minhas preferências de notificação ›</a></p>
@endsection
