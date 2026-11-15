@extends('layouts.admin')
@section('titulo', 'Cupons de cadastro')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Cupons de cadastro</h1>
        <p>Códigos promocionais que novos produtores podem usar no cadastro para receber uma taxa especial.</p></div>
</div>

@if ($cupons->isEmpty())
    <div class="alerta alerta--info"><p>Nenhum cupom criado ainda. Use o formulário abaixo para criar o primeiro.</p></div>
@else
<section class="cartao" style="padding:20px;margin-bottom:18px">
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr>
            <th>Código</th><th>Nome / observação</th><th>Taxa aplicada</th><th>Validade</th><th>Usos</th><th>Situação</th><th></th>
        </tr></thead>
        <tbody>
        @foreach ($cupons as $c)
            @php
                $taxa = $c->isento ? 'Isento' : collect([
                    $c->percentual !== null ? numero($c->percentual, 2).'%' : null,
                    $c->valor_fixo !== null ? '+ '.moeda($c->valor_fixo) : null,
                    $c->valor_minimo !== null ? '(mín. '.moeda($c->valor_minimo).')' : null,
                ])->filter()->implode(' ');
                $validade = $c->meses_validade ? $c->meses_validade.' mes(es)' : 'Permanente';
                $limite = $c->limite_usos ? $c->usos.'/'.$c->limite_usos : $c->usos.' (ilimitado)';
            @endphp
            <tr>
                <td><b>{{ $c->codigo }}</b></td>
                <td>{{ $c->nome }}<br>@if ($c->observacao)<small class="suave">{{ $c->observacao }}</small>@endif</td>
                <td>{{ $taxa ?: 'padrão do sistema' }}</td>
                <td>{{ $validade }}</td>
                <td>{{ $limite }}</td>
                <td><span class="selo {{ $c->ativo ? 'selo--verde' : 'selo--cinza' }}">{{ $c->ativo ? 'Ativo' : 'Inativo' }}</span></td>
                <td class="num" style="white-space:nowrap">
                    <button type="button" class="botao botao--contorno botao--pequeno" data-compartilhar="{{ url('/cadastro?cupom='.urlencode($c->codigo)) }}" title="Copiar link de cadastro com este cupom">Copiar link</button>
                    <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="editar-{{ $c->id }}">Editar</button>
                    <form method="post" action="{{ route('admin.cupons.alternar', $c->id) }}" style="display:inline">@csrf
                        <button class="botao botao--{{ $c->ativo ? 'perigo' : 'contorno' }} botao--pequeno">{{ $c->ativo ? 'Desativar' : 'Ativar' }}</button>
                    </form>
                </td>
            </tr>
            <tr class="linha-acoes" id="editar-{{ $c->id }}" @unless ((string) old('_cupom') === (string) $c->id) hidden @endunless><td colspan="7">
                <form method="post" action="{{ route('admin.cupons.update', $c->id) }}" class="form-grade">
                    @csrf @method('PUT')
                    <input type="hidden" name="_cupom" value="{{ $c->id }}">
                    @include('admin.cupons._campos', ['c' => (string) old('_cupom') === (string) $c->id ? null : $c])
                    <div class="col-12"><button class="botao botao--primario botao--pequeno">Salvar alterações</button></div>
                </form>
            </td></tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endif

<section class="cartao" style="padding:20px">
    <h2 style="margin-top:0">Novo cupom de cadastro</h2>
    <form method="post" action="{{ route('admin.cupons.store') }}" class="form-grade">
        @csrf
        @include('admin.cupons._campos', ['c' => null])
        <div class="col-12"><button class="botao botao--primario">Criar cupom</button></div>
    </form>
</section>
@endsection
