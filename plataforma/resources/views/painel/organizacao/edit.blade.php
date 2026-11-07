@extends('layouts.painel')
@section('titulo', 'Organização')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Organização</h1><p>Dados do organizador e da página pública. O nome e o documento aparecem para o comprador como responsáveis pelo evento.</p></div>
    <span class="selo {{ $org->situacao === 'ativo' ? 'selo--verde' : 'selo--vermelho' }}">{{ \App\Models\Organizador::SITUACOES[$org->situacao] }}</span>
</div>

<form method="post" action="{{ route('organizacao.update') }}" class="cartao">
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
    <section class="secao-form">
        <h2>Página pública</h2>
        <p>Lista os seus eventos publicados.</p>
        <div class="form-grade">
            <x-campo nome="slug" rotulo="Endereço da página" :valor="$org->slug" obrigatorio classe="col-6" ajuda="foradafila.com.br/p/{{ $org->slug }}" />
            <x-campo nome="site" rotulo="Site" tipo="url" :valor="$org->site" classe="col-6" placeholder="https://" />
            <x-campo nome="instagram" rotulo="Instagram" :valor="$org->instagram" classe="col-6" placeholder="@seuperfil" />
            <x-campo nome="whatsapp" rotulo="WhatsApp" :valor="$org->whatsapp" classe="col-6" data-mascara="telefone" />
            <x-campo nome="descricao" rotulo="Sobre a organização" tipo="textarea" :valor="$org->descricao" rows="4" />
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
@endsection
