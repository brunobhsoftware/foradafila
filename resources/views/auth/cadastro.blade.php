@extends('layouts.acesso')
@section('titulo', 'Criar conta de produtor')
@section('chamada', 'Crie sua conta e comece a vender hoje.')

@section('conteudo')
<h2>Criar conta de produtor</h2>
<p class="subtitulo">Leva 2 minutos. Já tem conta? <a href="{{ route('entrar') }}">Entrar</a></p>

@if ($termos->count() < count(\App\Models\Termo::DO_ORGANIZADOR))
    <div class="alerta alerta--info"><p>O cadastro de novos produtores abre em instantes: a plataforma está terminando a configuração dos termos de uso.</p></div>
@endif

<form method="post" action="{{ route('cadastro') }}" novalidate>
    @csrf

    <fieldset>
        <legend><span class="numero">1</span> Você, responsável pela conta</legend>
        <div class="form-grade">
            <x-campo nome="nome" rotulo="Seu nome completo" obrigatorio autocomplete="name" />
            <x-campo nome="email" rotulo="E-mail de acesso" tipo="email" obrigatorio autocomplete="email" classe="col-6" />
            <x-campo nome="telefone" rotulo="Celular / WhatsApp" tipo="tel" obrigatorio data-mascara="telefone" autocomplete="tel" classe="col-6" />
            <x-campo nome="senha" rotulo="Senha" tipo="password" obrigatorio autocomplete="new-password" classe="col-6" ajuda="Mínimo de 8 caracteres, com letras e números." />
            <x-campo nome="senha_confirmation" rotulo="Repita a senha" tipo="password" obrigatorio autocomplete="new-password" classe="col-6" />
        </div>
    </fieldset>

    <fieldset>
        <legend><span class="numero">2</span> A organização que vende os ingressos</legend>
        <div class="form-grade">
            <div class="campo col-12">
                <span class="rotulo">Tipo de cadastro</span>
                <div class="segmentado" role="radiogroup">
                    <label><input type="radio" name="tipo_pessoa" value="PJ" @checked(old('tipo_pessoa', 'PJ') === 'PJ')><span>Empresa (CNPJ)</span></label>
                    <label><input type="radio" name="tipo_pessoa" value="PF" @checked(old('tipo_pessoa') === 'PF')><span>Pessoa física (CPF)</span></label>
                </div>
            </div>
            <div class="campo col-6 {{ $errors->has('documento') ? 'com-erro' : '' }}">
                <label for="c_documento" data-rotulo-pf="CPF" data-rotulo-pj="CNPJ">CNPJ</label>
                <input id="c_documento" name="documento" value="{{ old('documento') }}" inputmode="numeric" data-mascara="documento" required>
                @error('documento')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <div class="campo col-6 {{ $errors->has('razao_social') ? 'com-erro' : '' }}">
                <label for="c_razao_social" data-rotulo-pf="Nome completo (como no CPF)" data-rotulo-pj="Razão social">Razão social</label>
                <input id="c_razao_social" name="razao_social" value="{{ old('razao_social') }}" required>
                @error('razao_social')<span class="erro">{{ $message }}</span>@enderror
            </div>
            <x-campo nome="nome_fantasia" rotulo="Nome que o público conhece" ajuda="Ex.: Choperia do Bruno. Aparece na página dos eventos." />
            <x-campo nome="email_contato" rotulo="E-mail de atendimento ao comprador" tipo="email" obrigatorio classe="col-6" ajuda="Vai no e-mail de confirmação da compra." />
            <x-campo nome="cidade" rotulo="Cidade" classe="col-4" />
            <x-campo nome="uf" rotulo="UF" classe="col-2" maxlength="2" />
        </div>
    </fieldset>

    <fieldset>
        <legend><span class="numero">3</span> Termos</legend>
        <div class="alerta alerta--info">
            <p>O Fora da Fila é uma plataforma de tecnologia. <b>O dinheiro das vendas cai direto na sua conta</b>,
            e a responsabilidade pela realização do evento é do organizador.</p>
        </div>
        <div class="opcoes">
            @php $rotulos = ['uso_organizador' => 'o Termo de uso do organizador', 'responsabilidade_organizador' => 'o Termo de responsabilidade do organizador', 'privacidade' => 'a Política de privacidade']; @endphp
            @foreach (\App\Models\Termo::DO_ORGANIZADOR as $tipo)
                @isset($termos[$tipo])
                    <label class="opcao">
                        <input type="checkbox" name="aceites[{{ $tipo }}]" value="1" @checked(old('aceites.'.$tipo)) required>
                        <span>Li e aceito {{ $rotulos[$tipo] }} (<a href="{{ route('termo', $tipo) }}" target="_blank" rel="noopener">ler, versão {{ $termos[$tipo]->versao }}</a>).</span>
                    </label>
                @endisset
            @endforeach
            @error('aceites')<span class="erro" style="color:var(--vermelho)">{{ $message }}</span>@enderror
        </div>
    </fieldset>

    <button type="submit" class="botao botao--primario botao--largo">Criar minha conta</button>
    <p class="acesso__rodape">Ao criar a conta, a organização já fica liberada para cadastrar eventos.</p>
</form>
@endsection
