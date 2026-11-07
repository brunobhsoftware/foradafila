<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envio de e-mails do Fora da Fila (regras, seção 12).
 *
 * Duas contas SMTP da Hostinger (config/mail.php):
 *   - operacao() → fique@foradafila.com.br: cadastro, ingressos, convites, senha — tudo que o usuário pode responder.
 *   - aviso()    → noreply@foradafila.com.br: avisos que não esperam resposta.
 *
 * Toda mensagem passa pela tabela mensagem_envio (fila única): grava destinatário, assunto e o
 * corpo renderizado (prova do que foi enviado) e depois o resultado (enviado / erro).
 * O envio acontece depois que a resposta vai para o navegador: uma falha de SMTP nunca
 * interrompe a operação do usuário.
 */
class Email
{
    public static function operacao(string $tipo, string $para, string $assunto, string $html, string $texto, array $refs = []): void
    {
        self::enfileirar('fique', $tipo, $para, $assunto, $html, $texto, $refs);
    }

    public static function aviso(string $tipo, string $para, string $assunto, string $html, string $texto, array $refs = []): void
    {
        self::enfileirar('noreply', $tipo, $para, $assunto, $html, $texto, $refs);
    }

    /** E-mail no padrão visual da marca. $blocos: HTML já escapado do miolo da mensagem. */
    public static function layout(string $titulo, string $blocos, ?string $botaoTexto = null, ?string $botaoUrl = null, ?string $rodape = null): string
    {
        return view('emails.layout', compact('titulo', 'blocos', 'botaoTexto', 'botaoUrl', 'rodape'))->render();
    }

    private static function enfileirar(string $conta, string $tipo, string $para, string $assunto, string $html, string $texto, array $refs): void
    {
        $id = DB::table('mensagem_envio')->insertGetId([
            'organizador_id' => $refs['organizador_id'] ?? null,
            'modelo_id' => $refs['modelo_id'] ?? null,
            'tipo' => mb_substr($tipo, 0, 40),
            'canal' => 'email',
            'destinatario' => mb_substr($para, 0, 150),
            'comprador_id' => $refs['comprador_id'] ?? null,
            'pedido_id' => $refs['pedido_id'] ?? null,
            'assunto' => mb_substr($assunto, 0, 150),
            'corpo_renderizado' => $html,
            'status' => 'pendente',
            'prioridade' => $refs['prioridade'] ?? 1,
        ]);

        // Links com token (convite, senha) não ficam gravados em texto aberto: só o corpo sem o segredo.
        if (! empty($refs['ocultar'])) {
            DB::table('mensagem_envio')->where('id', $id)->update([
                'corpo_renderizado' => str_replace($refs['ocultar'], '[link de uso único removido]', $html),
            ]);
        }

        dispatch(fn () => self::enviar($id, $conta, $para, $assunto, $html, $texto))->afterResponse();
    }

    private static function enviar(int $id, string $conta, string $para, string $assunto, string $html, string $texto): void
    {
        // Sem senha configurada (desenvolvimento), o e-mail vai para o log em vez do SMTP.
        $mailer = config("mail.mailers.$conta.password") ? $conta : 'log';
        $remetente = config("mail.mailers.$conta.username") ?: "$conta@foradafila.com.br";

        DB::table('mensagem_envio')->where('id', $id)->update(['status' => 'enviando', 'tentativas' => DB::raw('tentativas + 1')]);
        try {
            $enviada = Mail::mailer($mailer)->html($html, function ($m) use ($para, $assunto, $remetente, $texto, $conta) {
                $m->to($para)->from($remetente, 'Fora da Fila')->subject($assunto)->text($texto);
                if ($conta === 'noreply') {
                    $m->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
                }
            });
            DB::table('mensagem_envio')->where('id', $id)->update([
                'status' => 'enviado', 'enviado_em' => now(), 'erro' => null,
                'provedor_msg_id' => $enviada?->getMessageId() ? mb_substr($enviada->getMessageId(), 0, 150) : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Falha no envio de e-mail', ['mensagem_envio_id' => $id, 'erro' => $e->getMessage()]);
            DB::table('mensagem_envio')->where('id', $id)->update(['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
