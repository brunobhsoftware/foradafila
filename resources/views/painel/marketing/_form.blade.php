<form method="post" action="{{ $c ? route('marketing.smtp.salvar', $c->uuid) : route('marketing.smtp.criar') }}" class="form-grade" autocomplete="off">@csrf
    <x-campo nome="nome" rotulo="Nome da conta" :valor="$c->nome ?? ''" obrigatorio classe="col-4" placeholder="Ex.: Divulgação" />
    <x-campo nome="remetente_nome" rotulo="Nome do remetente" :valor="$c->remetente_nome ?? ''" obrigatorio classe="col-4" placeholder="Como aparece para quem recebe" />
    <x-campo nome="remetente_email" rotulo="E-mail do remetente" tipo="email" :valor="$c->remetente_email ?? ''" obrigatorio classe="col-4" />
    <x-campo nome="host" rotulo="Servidor SMTP" :valor="$c->host ?? 'smtp.hostinger.com'" obrigatorio classe="col-4" />
    <div class="campo col-2"><label>Porta</label><select name="porta">@foreach ([465, 587, 25, 2525] as $p)<option @selected((int) ($c->porta ?? 465) === $p)>{{ $p }}</option>@endforeach</select></div>
    <div class="campo col-2"><label>Segurança</label><select name="seguranca">@foreach (['ssl' => 'SSL', 'tls' => 'TLS (STARTTLS)', 'nenhuma' => 'Nenhuma'] as $v => $t)<option value="{{ $v }}" @selected(($c->seguranca ?? 'ssl') === $v)>{{ $t }}</option>@endforeach</select></div>
    <x-campo nome="usuario" rotulo="Usuário" :valor="$c->usuario ?? ''" obrigatorio classe="col-4" placeholder="Normalmente o próprio e-mail" />
    <x-campo nome="senha" rotulo="Senha" tipo="password" classe="col-4" :obrigatorio="! $c" :placeholder="$c ? 'Em branco mantém a senha atual' : ''" autocomplete="new-password" />
    <x-campo nome="responder_para" rotulo="Responder para (opcional)" tipo="email" :valor="$c->responder_para ?? ''" classe="col-4" />
    <x-campo nome="limite_hora" rotulo="Limite de envios por hora" tipo="number" :valor="$c->limite_hora ?? 100" obrigatorio classe="col-4" ajuda="Confira o limite do seu provedor (Hostinger: em geral 100 a 300 por hora, conforme o plano)." />
    <div class="campo col-12"><label class="opcao"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" @checked($c->ativo ?? true)> <span><b>Conta ativa</b></span></label></div>
    <div class="campo col-12"><button class="botao botao--primario">{{ $c ? 'Salvar conta' : 'Cadastrar conta' }}</button></div>
</form>
