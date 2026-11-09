<?php

namespace App\Support;

use App\Models\Comprador;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/** Textos dos e-mails enviados pela plataforma. Envio e registro: App\Support\Email. */
class Notificacoes
{
    private static function e(?string $v): string
    {
        return e((string) $v);
    }

    /** Rodapé fixo: o organizador aparece sempre como responsável pelo evento (regras, seção 12). */
    private static function rodapeOrganizador(Organizador $org): string
    {
        return 'Evento organizado por <b>'.self::e($org->razao_social).'</b> ('.self::e($org->documentoFormatado()).'), responsável pelo evento. '
            .'Atendimento: <a href="mailto:'.self::e($org->email_contato).'" style="color:#F25C05">'.self::e($org->email_contato).'</a>.<br>'
            .'Ingressos emitidos pela plataforma Fora da Fila.';
    }

    /** fique@ — confirmação do pedido com a lista de ingressos (modelo "confirmacao_compra"). */
    /**
     * Assunto de e-mail de evento: sempre "Nome do evento — resto" (decisão do Bruno, 09/11/2026). Tira o nome do evento de
     * onde estiver no texto (inclusive em modelos editados pelo produtor) e o coloca no começo.
     */
    public static function assuntoEvento(string $titulo, string $assunto): string
    {
        $titulo = trim($titulo);
        $resto = trim(preg_replace('/\s{2,}/u', ' ', str_ireplace($titulo, '', $assunto)) ?? '');
        $resto = trim(preg_replace('/^[\s—–\-:|·]+|[\s—–\-:|·]+$/u', '', $resto) ?? '');

        return mb_substr($resto === '' ? $titulo : $titulo.' — '.$resto, 0, 150);
    }

    public static function ingressosConfirmados(Pedido $pedido, Comprador $comprador, Evento $evento, Organizador $org, Collection $ingressos): void
    {
        // Link que abre os ingressos sem login (assinado, 90 dias) e PDF anexo (decisão do Bruno, 09/11/2026).
        $link = \App\Http\Controllers\Publico\PagamentoController::linkIngressos($pedido);
        $linkSenha = ($comprador->senha_hash || ! $comprador->exists || ! $comprador->uuid) ? null : \App\Http\Controllers\Auth\SenhaController::link($comprador, 'comprador');
        $vars = [
            'comprador_nome' => $comprador->nome, 'pedido_codigo' => $pedido->codigo, 'evento_titulo' => $evento->titulo,
            'evento_data' => data_local($evento->data_inicio, 'd/m/Y \à\s H:i'), 'local_nome' => $evento->local?->nome ?? 'online',
            'link_meus_ingressos' => $link, 'organizador_razao_social' => $org->razao_social,
            'organizador_documento' => $org->documentoFormatado(), 'organizador_email_contato' => $org->email_contato,
        ];
        $modelo = ModeloMensagem::renderizar('confirmacao_compra', $org->id, $evento->id, $vars)
            ?? ['assunto' => "Pedido {$pedido->codigo} confirmado — {$evento->titulo}", 'texto' => "Olá, {$comprador->nome}! Seu pedido {$pedido->codigo} para {$evento->titulo} foi confirmado.", 'modelo_id' => null];

        $lista = $ingressos->map(fn ($i) => '<li><b>'.self::e($i->numeroExibicao()).'</b> — '.self::e($i->participante?->nome ?? '').' · '
            .self::e($i->tipo?->nome).' ('.self::e($i->lote?->nome).')</li>')->implode('');
        $html = Email::layout(
            'Ingressos confirmados',
            '<p>'.nl2br(self::e($modelo['texto'])).'</p><p style="margin:16px 0 6px"><b>Seus ingressos</b></p><ul style="padding-left:18px;margin:0">'.$lista.'</ul>'
            .'<p style="margin-top:16px;font-size:13px;color:#667085">Os ingressos estão em PDF neste e-mail. Na entrada, apresente o QR Code de cada ingresso (impresso ou no celular). Não compartilhe o QR Code.</p>'
            .(! $linkSenha ? '' : '<p style="margin-top:12px;font-size:13px;color:#667085">Quer acessar os seus ingressos com e-mail e senha? <a href="'
                .self::e((string) $linkSenha).'" style="color:#F25C05">Crie a sua senha</a> (link válido por 3 dias). É opcional.</p>'),
            'Ver meus ingressos', $link, self::rodapeOrganizador($org)
        );
        $texto = $modelo['texto']."\n\nIngressos:\n".$ingressos->map(fn ($i) => '- '.$i->numeroExibicao().' — '.($i->participante?->nome ?? ''))->implode("\n")."\n\nMeus ingressos: $link";

        $anexos = [];
        try {
            $validos = $ingressos->whereIn('status', ['valido', 'utilizado'])->values();
            if ($validos->isNotEmpty()) {
                $anexos[] = ['nome' => 'ingressos-'.$pedido->codigo.'.pdf', 'mime' => 'application/pdf', 'conteudo' => IngressoPdf::gerar($pedido, $evento, $validos)];
            } else {
                \Illuminate\Support\Facades\Log::warning('E-mail de ingressos sem ingressos válidos para o PDF', ['pedido' => $pedido->codigo]);
            }
        } catch (\Throwable $e) {
            report($e); // sem o PDF o e-mail sai mesmo assim, com o link
        }
        Email::operacao('confirmacao_compra', $comprador->email, self::assuntoEvento($evento->titulo, $modelo['assunto']), $html, $texto, [
            'organizador_id' => $org->id, 'modelo_id' => $modelo['modelo_id'], 'comprador_id' => $comprador->id, 'pedido_id' => $pedido->id,
            'anexos' => $anexos, 'ocultar' => array_filter([$linkSenha ? self::e($linkSenha) : null]),
        ]);
    }

    /** noreply@ — aviso de que a inscrição gratuita foi cancelada pelo próprio comprador. */
    public static function inscricaoCancelada(Pedido $pedido, Comprador $comprador, Evento $evento, Organizador $org): void
    {
        $html = Email::layout('Inscrição cancelada',
            '<p>Olá, '.self::e($comprador->primeiroNome()).'.</p><p>A inscrição do pedido <b>'.self::e($pedido->codigo).'</b> em <b>'.self::e($evento->titulo)
            .'</b> foi cancelada a seu pedido. Os ingressos deste pedido deixaram de valer.</p><p>Se não foi você, fale com o organizador pelo e-mail abaixo.</p>'
            .'<p style="font-size:13px;color:#667085">Este é um aviso automático. Não responda a este e-mail.</p>',
            null, null, self::rodapeOrganizador($org));
        Email::aviso('cancelamento_inscricao', $comprador->email, self::assuntoEvento($evento->titulo, 'Inscrição cancelada'), $html,
            "A inscrição do pedido {$pedido->codigo} em {$evento->titulo} foi cancelada. Os ingressos deixaram de valer.",
            ['organizador_id' => $org->id, 'comprador_id' => $comprador->id, 'pedido_id' => $pedido->id, 'prioridade' => 2]);
    }

    /** fique@ — ingressos cancelados pela organização (com o valor a devolver, se houver). */
    public static function ingressosCanceladosPelaOrganizacao(Pedido $pedido, Comprador $comprador, Evento $evento, Organizador $org, Collection $ingressos, float $valor): void
    {
        $lista = $ingressos->map(fn ($i) => '<li>'.self::e($i->numeroExibicao()).' — '.self::e($i->participante?->nome ?? '').'</li>')->implode('');
        $devolucao = $valor > 0
            ? '<p>O valor de <b>'.self::e(moeda($valor)).'</b> será devolvido pela organização, pelo mesmo meio de pagamento da compra. Em caso de dúvida, fale com a organização pelo contato abaixo.</p>'
            : '';
        $html = Email::layout('Ingressos cancelados',
            '<p>Olá, '.self::e($comprador->primeiroNome()).'.</p><p>A organização de <b>'.self::e($evento->titulo).'</b> cancelou os ingressos abaixo do pedido <b>'
            .self::e($pedido->codigo).'</b>. Eles deixaram de valer para a entrada.</p><ul style="padding-left:18px">'.$lista.'</ul>'.$devolucao,
            null, null, self::rodapeOrganizador($org));
        Email::operacao('cancelamento_organizacao', $comprador->email, self::assuntoEvento($evento->titulo, 'Ingressos cancelados'), $html,
            "A organização de {$evento->titulo} cancelou ingressos do pedido {$pedido->codigo}.".($valor > 0 ? ' Valor a devolver: '.moeda($valor).'.' : ''),
            ['organizador_id' => $org->id, 'comprador_id' => $comprador->id, 'pedido_id' => $pedido->id]);
    }

    /** fique@ — o próprio comprador cancelou um ingresso em Meus ingressos (regra dos 7 dias). */
    public static function ingressoCanceladoPeloComprador(Pedido $pedido, Comprador $comprador, Evento $evento, Organizador $org, $ingresso, array $regra): void
    {
        $devolucao = $regra['valor'] > 0
            ? '<p>Valor devolvido: <b>'.self::e(moeda($regra['valor'])).'</b>'.($regra['taxa_retida'] > 0 ? ' (o valor do ingresso; a taxa de serviço de '.self::e(moeda($regra['taxa_retida'])).' não é devolvida após 7 dias da compra)' : ' (valor integral, dentro de 7 dias da compra)')
              .'. A devolução sai pelo mesmo meio de pagamento; no cartão, aparece na fatura conforme o banco (em geral, até 2 faturas).</p>'
            : '';
        $html = Email::layout('Ingresso cancelado',
            '<p>Olá, '.self::e($comprador->primeiroNome()).'.</p><p>Conforme o seu pedido, o ingresso <b>'.self::e($ingresso->numeroExibicao()).'</b> ('
            .self::e($ingresso->participante?->nome ?? '').') de <b>'.self::e($evento->titulo).'</b>, pedido <b>'.self::e($pedido->codigo).'</b>, foi cancelado e deixou de valer para a entrada.</p>'.$devolucao,
            null, null, self::rodapeOrganizador($org));
        Email::operacao('cancelamento_comprador', $comprador->email, self::assuntoEvento($evento->titulo, 'Ingresso cancelado'), $html,
            "Ingresso {$ingresso->numeroExibicao()} de {$evento->titulo} cancelado.".($regra['valor'] > 0 ? ' Valor devolvido: '.moeda($regra['valor']).'.' : ''),
            ['organizador_id' => $org->id, 'comprador_id' => $comprador->id, 'pedido_id' => $pedido->id]);
    }

    /** fique@ — boas-vindas ao produtor que acabou de criar a conta. */
    public static function boasVindasProdutor(Usuario $usuario, Organizador $org): void
    {
        $link = route('painel');
        $html = Email::layout('Bem-vindo ao Fora da Fila',
            '<p>Olá, '.self::e($usuario->primeiroNome()).'!</p><p>A conta de <b>'.self::e($org->nomeExibicao()).'</b> foi criada. Próximos passos:</p>'
            .'<ol style="padding-left:18px"><li>Complete os dados e a página da organização.</li><li>Em Meus eventos, crie o primeiro evento.</li>'
            .'<li>Cadastre os tipos de ingresso e os lotes e publique.</li><li>Convide a sua equipe com o nível de acesso de cada pessoa.</li></ol>'
            .'<p>Você entra com o e-mail '.self::e($usuario->email).'.</p>',
            'Ir para a área do produtor', $link);
        Email::operacao('boas_vindas_organizador', $usuario->email, 'Sua conta de produtor no Fora da Fila', $html,
            "Olá, {$usuario->primeiroNome()}! A conta de {$org->nomeExibicao()} foi criada. Acesse: $link",
            ['organizador_id' => $org->id, 'prioridade' => 2]);
    }

    /** fique@ — convite para a equipe de um organizador. O link (token) não fica gravado no registro. */
    public static function conviteEquipe(Usuario $convidado, Organizador $org, string $perfil, string $url, ?string $quemConvidou): void
    {
        $html = Email::layout('Convite para a equipe',
            '<p>Olá, '.self::e($convidado->primeiroNome()).'!</p><p>'.self::e($quemConvidou ?: 'A organização').' convidou você para a equipe de <b>'
            .self::e($org->nomeExibicao()).'</b> no Fora da Fila, com o perfil <b>'.self::e($perfil).'</b>.</p>'
            .'<p>O convite vale por 72 horas e só pode ser usado uma vez.</p>',
            'Aceitar convite', $url);
        Email::operacao('convite_equipe', $convidado->email, 'Convite para a equipe de '.$org->nomeExibicao(), $html,
            "Você foi convidado para a equipe de {$org->nomeExibicao()} (perfil {$perfil}). Aceite em até 72 h: $url",
            ['organizador_id' => $org->id, 'ocultar' => $url]);
    }

    /** fique@ — link para redefinir a senha (produtor ou comprador). */
    public static function redefinirSenha(string $nome, string $email, string $url, string $area): void
    {
        $html = Email::layout('Redefinir senha',
            '<p>Olá, '.self::e(strtok($nome, ' ') ?: $nome).'.</p><p>Recebemos um pedido para redefinir a senha da sua conta ('.self::e($area).') no Fora da Fila.</p>'
            .'<p>O link vale por 60 minutos e deixa de funcionar assim que a senha for trocada. Se você não pediu, ignore este e-mail: a senha atual continua valendo.</p>',
            'Criar nova senha', $url);
        Email::operacao('redefinir_senha', $email, 'Redefinir a senha — Fora da Fila', $html,
            "Para criar uma nova senha, acesse em até 60 minutos: $url", ['ocultar' => $url]);
    }

    /**
     * noreply@ — aviso de nova compra/inscrição para quem marcou "Notificar-me cada vez que um participante
     * realizar uma compra" (V004), entre as pessoas ativas da equipe que podem ver o evento.
     */
    public static function novaCompraParaEquipe(Pedido $pedido, Comprador $comprador, Evento $evento, Organizador $org, int $quantidade): void
    {
        if (! Esquema::temV004()) {
            return;
        }
        $destinos = \App\Models\OrganizadorUsuario::withoutGlobalScope('organizador')->with('usuario')
            ->where('organizador_id', $org->id)->where('status', 'ativo')->where('notificar_compra', 1)
            ->where(fn ($q) => $q->where('todos_eventos', 1)
                ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('organizador_usuario_evento as oue')
                    ->whereColumn('oue.organizador_usuario_id', 'organizador_usuario.id')->where('oue.evento_id', $evento->id)))
            ->get();

        $link = route('eventos.show', $evento);
        $tipo = $pedido->ehGratuito() ? 'inscrição gratuita' : 'compra';
        foreach ($destinos as $vinculo) {
            $html = Email::layout('Nova '.$tipo.' em '.$evento->titulo,
                '<p>Olá, '.self::e($vinculo->usuario->primeiroNome()).'.</p><p>O pedido <b>'.self::e($pedido->codigo).'</b> foi confirmado: <b>'
                .$quantidade.' '.($quantidade > 1 ? 'ingressos' : 'ingresso').'</b> para <b>'.self::e($evento->titulo).'</b>'
                .($pedido->ehGratuito() ? '' : ', no valor de '.self::e(moeda($pedido->valor_total))).'.</p>'
                .'<p>Comprador: '.self::e($comprador->nome).'.</p>'
                .'<p style="font-size:13px;color:#667085">Você recebe este aviso porque ativou a notificação de compras nas suas preferências. Este é um aviso automático: não responda.</p>',
                'Ver o evento no painel', $link);
            Email::aviso('aviso_compra_equipe', $vinculo->usuario->email, self::assuntoEvento($evento->titulo, "Nova {$tipo}: {$pedido->codigo}"), $html,
                "Pedido {$pedido->codigo} confirmado: {$quantidade} ingresso(s) para {$evento->titulo}. Ver: $link",
                ['organizador_id' => $org->id, 'pedido_id' => $pedido->id, 'prioridade' => 3]);
        }
    }

    /** fique@ — código de 6 dígitos para confirmar uma ação da conta (trocar senha, trocar e-mail). O código não fica gravado. */
    public static function codigoVerificacao(string $nome, string $email, string $codigo, string $acao): void
    {
        $html = Email::layout('Seu código de verificação',
            '<p>Olá, '.self::e(strtok($nome, ' ') ?: $nome).'.</p><p>Use o código abaixo para '.self::e($acao).' no Fora da Fila:</p>'
            .'<p style="font-size:32px;font-weight:bold;letter-spacing:8px;color:#101828;margin:18px 0">'.self::e($codigo).'</p>'
            .'<p>O código vale por '.CodigoVerificacao::VALIDADE_MIN.' minutos e só pode ser usado uma vez. Se você não pediu, ignore este e-mail e considere trocar a sua senha.</p>');
        Email::operacao('codigo_verificacao', $email, 'Seu código de verificação — Fora da Fila', $html,
            "Seu código para {$acao} no Fora da Fila: {$codigo} (vale por ".CodigoVerificacao::VALIDADE_MIN.' minutos).',
            ['ocultar' => $codigo, 'prioridade' => 1]);
    }

    /** noreply@ — aviso de segurança de que a conta foi alterada (senha ou e-mail). */
    public static function contaAlterada(string $nome, string $email, string $mensagem): void
    {
        $html = Email::layout('Sua conta foi alterada',
            '<p>Olá, '.self::e(strtok($nome, ' ') ?: $nome).'.</p><p>'.self::e($mensagem).'</p><p>Data: '.self::e(data_local(now())).' (horário de Brasília).</p>'
            .'<p>Se não foi você, use “Esqueci a senha” na tela de entrada e fale com o responsável pela sua organização.</p>'
            .'<p style="font-size:13px;color:#667085">Este é um aviso automático. Não responda a este e-mail.</p>');
        Email::aviso('conta_alterada', $email, 'Sua conta no Fora da Fila foi alterada', $html, $mensagem, ['prioridade' => 1]);
    }
}
