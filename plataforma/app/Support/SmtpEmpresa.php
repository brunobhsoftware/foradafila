<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Contas SMTP da própria empresa (Organização → Opções de marketing, V017). Usadas só para divulgação (convites por e-mail);
 * os e-mails de ingressos, senha e avisos continuam saindo pelas contas do Fora da Fila (App\Support\Email).
 */
class SmtpEmpresa
{
    public const MAXIMO = 5;

    /** Mailer montado na hora com os dados da conta (a senha só é descriptografada aqui). */
    public static function mailer(object $conta)
    {
        return Mail::build([
            'transport' => 'smtp',
            'scheme' => $conta->seguranca === 'ssl' ? 'smtps' : 'smtp',
            'host' => $conta->host,
            'port' => (int) $conta->porta,
            'username' => $conta->usuario,
            'password' => $conta->senha_cripto ? Crypt::decryptString($conta->senha_cripto) : '',
            'timeout' => 20,
            'auto_tls' => $conta->seguranca !== 'nenhuma',
        ]);
    }

    /** Envia um e-mail pela conta. Devolve null se deu certo ou a mensagem de erro. */
    public static function enviar(object $conta, string $para, string $assunto, string $html, string $texto, array $cabecalhos = []): ?string
    {
        try {
            self::mailer($conta)->html($html, function ($m) use ($conta, $para, $assunto, $texto, $cabecalhos) {
                $m->to($para)->from($conta->remetente_email, $conta->remetente_nome)->subject($assunto)->text($texto);
                if ($conta->responder_para) {
                    $m->replyTo($conta->responder_para);
                }
                foreach ($cabecalhos as $nome => $valor) {
                    $m->getHeaders()->addTextHeader($nome, $valor);
                }
            });

            return null;
        } catch (Throwable $e) {
            return mb_substr($e->getMessage(), 0, 500);
        }
    }
}
