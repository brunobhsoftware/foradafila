@extends('layouts.painel')
@section('titulo', 'Listas de e-mail')

@section('conteudo')
<div class="cabecalho"><div><h1>Listas de e-mail</h1><p>Contatos para os convites por e-mail: importe um arquivo CSV, cole os e-mails ou traga quem comprou e participou dos seus eventos.</p></div></div>

<section class="cartao">
    @if ($listas->isEmpty())
        <div class="vazio"><h3>Nenhuma lista ainda</h3><p>Crie a primeira abaixo.</p></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Lista</th><th>Contatos</th><th>Criada em</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($listas as $l)
            <tr><td><a href="{{ route('listas-email.show', $l->uuid) }}"><b>{{ $l->nome }}</b></a>@if ($l->descricao)<br><small class="suave">{{ $l->descricao }}</small>@endif</td>
                <td>{{ number_format($l->total, 0, ',', '.') }}</td><td>{{ data_local($l->criado_em, 'd/m/Y') }}</td>
                <td class="num"><a href="{{ route('listas-email.show', $l->uuid) }}" class="botao botao--contorno botao--pequeno">Abrir</a></td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
    @if ($descadastrados)<p class="suave" style="padding:0 16px 14px;margin:0">{{ $descadastrados }} e-mail(s) pediram para não receber mais divulgação da empresa e são pulados nos envios.</p>@endif
</section>

<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Nova lista</h2>
    <form method="post" action="{{ route('listas-email.criar') }}" class="form-grade">@csrf
        <x-campo nome="nome" rotulo="Nome da lista" obrigatorio classe="col-4" placeholder="Ex.: Clientes 2025" />
        <x-campo nome="descricao" rotulo="Descrição (opcional)" classe="col-6" />
        <div class="campo col-2" style="align-self:end"><button class="botao botao--primario">Criar lista</button></div>
    </form>
</section>
@endsection
