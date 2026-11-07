@extends('layouts.admin')
@section('titulo', 'Administração')

@section('conteudo')
<div class="cabecalho"><div><h1>Administração</h1><p>Área da equipe BH Software. Só usuários marcados como administradores da plataforma entram aqui.</p></div></div>

<div class="grade grade-3">
    <div class="cartao kpi"><x-icone nome="organizacao" /><small>Organizações</small><b>{{ numero($numeros->organizadores) }}</b><small>cadastradas</small></div>
    <div class="cartao kpi"><x-icone nome="evento" /><small>Eventos publicados</small><b>{{ numero($numeros->eventos) }}</b><small>em todas as organizações</small></div>
    <div class="cartao kpi"><x-icone nome="dinheiro" /><small>Contas Mercado Pago</small><b>{{ numero($numeros->contas) }}</b><small>conectadas</small></div>
</div>

<section class="cartao" style="margin-top:18px;padding:20px">
    <h2 style="margin-top:0">Atalhos</h2>
    <p><a href="{{ route('admin.empresas') }}" class="botao botao--escuro botao--pequeno">Empresas</a>
       <a href="{{ route('admin.taxas') }}" class="botao botao--contorno botao--pequeno">Taxas e isenções</a></p>
</section>

<section class="cartao" style="margin-top:18px;padding:20px">
    <h2 style="margin-top:0">Integração com o Mercado Pago</h2>
    @if ($mpConfigurado)
        <p><span class="selo selo--verde">Configurada</span> Os produtores já podem conectar as contas em Financeiro.</p>
    @else
        <p><span class="selo selo--amarelo">Pendente</span> Cadastre o Client ID, o Client Secret e a assinatura secreta da aplicação da BH Software.</p>
    @endif
    <a href="{{ route('admin.mercadopago') }}" class="botao botao--escuro botao--pequeno">Configurar Mercado Pago</a>
</section>
@endsection
