@extends('layouts.painel')
@section('titulo', 'Opções de marketing')

@section('conteudo')
<div class="cabecalho">
    <div><h1>Opções de marketing</h1><p>Contas de e-mail da sua empresa para os convites por e-mail (Divulgação). Os e-mails de ingressos, senha e avisos continuam saindo pelo Fora da Fila.</p></div>
    <a href="{{ route('organizacao.edit') }}" class="botao botao--contorno">Voltar para Organização</a>
</div>

<section class="cartao">
    <div class="cartao__titulo"><h2>Contas de e-mail (SMTP) da empresa</h2><span class="selo">{{ $contas->count() }} de {{ \App\Support\SmtpEmpresa::MAXIMO }}</span></div>
    @if ($contas->isEmpty())
        <div class="vazio"><h3>Nenhuma conta cadastrada</h3><p>Cadastre abaixo os dados do e-mail da sua empresa (os mesmos que você usa no Outlook ou no celular).</p></div>
    @else
    <div class="tabela-rolagem"><table class="tabela">
        <thead><tr><th>Conta</th><th>Remetente</th><th>Servidor</th><th>Limite</th><th>Situação</th><th class="num"></th></tr></thead>
        <tbody>
        @foreach ($contas as $c)
            <tr>
                <td><b>{{ $c->nome }}</b></td>
                <td>{{ $c->remetente_nome }}<br><small class="suave">{{ $c->remetente_email }}</small></td>
                <td>{{ $c->host }}:{{ $c->porta }} <small class="suave">{{ strtoupper($c->seguranca) }}</small></td>
                <td>{{ $c->limite_hora }}/hora</td>
                <td>
                    @if (! $c->ativo)<span class="selo">Desativada</span>
                    @elseif ($c->ultimo_erro)<span class="selo selo--vermelho" title="{{ $c->ultimo_erro }}">Erro no teste</span>
                    @elseif ($c->testado_em)<span class="selo selo--verde">Testada {{ data_local($c->testado_em, 'd/m H:i') }}</span>
                    @else<span class="selo">Não testada</span>@endif
                </td>
                <td class="num" style="white-space:nowrap">
                    <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="smtp-teste-{{ $c->id }}">Enviar teste</button>
                    <button type="button" class="botao botao--contorno botao--pequeno" data-alternar="smtp-editar-{{ $c->id }}">Editar</button>
                </td>
            </tr>
            <tr class="linha-acoes" id="smtp-teste-{{ $c->id }}" hidden><td colspan="6">
                <form method="post" action="{{ route('marketing.smtp.testar', $c->uuid) }}" class="form-grade">@csrf
                    <x-campo nome="para" rotulo="Enviar um e-mail de teste para" tipo="email" :valor="auth()->user()->email" classe="col-6" />
                    <div class="campo col-6" style="align-self:end"><button class="botao botao--primario">Enviar teste agora</button></div>
                </form>
                @if ($c->ultimo_erro)<p class="suave" style="margin:8px 0 0"><b>Último erro:</b> {{ $c->ultimo_erro }}</p>@endif
            </td></tr>
            <tr class="linha-acoes" id="smtp-editar-{{ $c->id }}" hidden><td colspan="6">
                @include('painel.marketing._form', ['c' => $c])
                <form method="post" action="{{ route('marketing.smtp.remover', $c->uuid) }}" onsubmit="return confirm('Remover a conta {{ $c->nome }}?')" style="margin-top:10px">@csrf
                    <button class="botao botao--perigo botao--pequeno">Remover esta conta</button></form>
            </td></tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

@if ($contas->count() < \App\Support\SmtpEmpresa::MAXIMO)
<section class="cartao" style="padding:20px;margin-top:18px">
    <h2 style="margin-top:0">Nova conta de e-mail</h2>
    <p class="suave">Na Hostinger: servidor <b>smtp.hostinger.com</b>, porta <b>465</b> (SSL), usuário = o próprio e-mail. No Gmail/Google Workspace: smtp.gmail.com, 465, com uma "senha de app".</p>
    @include('painel.marketing._form', ['c' => null])
</section>
@endif

<section class="cartao" style="padding:20px;margin-top:18px">
    <div class="cartao__titulo" style="padding:0;border:0"><h2 style="margin:0">E-mail marketing do Fora da Fila</h2><span class="selo">Em breve</span></div>
    <p class="suave" style="margin:8px 0 0">Pacotes de envio pela plataforma, sem precisar de conta própria, para listas grandes.</p>
</section>
@endsection
