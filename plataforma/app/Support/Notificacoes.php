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
    public static function ingressosConfirmados(Pedido $pedido, Comprador $comprador, Evento $evento, Organizador $org, Collection $ingressos): void
    {
        $link = route('comprador.ingressos');
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
            .'<p style="margin-top:16px;font-size:13px;color:#667085">Na entrada, apresente o QR Code de cada ingresso, que fica em Meus ingressos. Não compartilhe o QR Code.</p>',
            'Abrir Meus ingressos', $link, self::rodapeOrganizador($org)
        );
        $texto = $modelo['texto']."\n\nIngressos:\n".$ingressos->map(fn ($i) => '- '.$i->numeroExibicao().' — '.($i->participante?->nome ?? ''))->implode("\n")."\n\nMeus ingressos: $link";

        Email::operacao('confirmacao_compra', $comprador->email, $modelo['assunto'], $html, $texto, [
            'organizador_id' => $org->id, 'modelo_id' => $modelo['modelo_id'], 'comprador_id' => $comprador->id, 'pedido_id' => $pedido->id,
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
        Email::aviso('cancelamento_inscricao', $comprador->email, "Inscrição cancelada — {$evento->titulo}", $html,
            "A inscrição do pedido {$pedido->codigo} em {$evento->titulo} foi cancelada. Os ingressos deixaram de valer.",
            ['organizador_id' => $org->id, 'comprador_id' => $comprador->id, 'pedido_id' => $pedido->id, 'prioridade' => 2]);
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
}
