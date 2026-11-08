<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Comprador;
use App\Models\Usuario;
use App\Support\Notificacoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

/**
 * "Esqueci a senha" para produtor (usuario) e comprador, sem tabela de tokens:
 * o link é uma URL assinada que vale 60 minutos e carrega uma impressão (HMAC) da
 * senha atual — trocou a senha, o link deixa de valer. A resposta é sempre a mesma,
 * exista ou não a conta, para não revelar quem tem cadastro.
 */
class SenhaController extends Controller
{
    private const AREAS = [
        'produtor' => ['modelo' => Usuario::class, 'nome' => 'área do produtor', 'login' => 'entrar'],
        'comprador' => ['modelo' => Comprador::class, 'nome' => 'Meus ingressos', 'login' => 'comprador.entrar'],
    ];

    public function pedir(string $area)
    {
        return view('auth.senha-pedir', ['area' => $area, 'info' => self::AREAS[$area]]);
    }

    public function enviar(Request $request, string $area)
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email:filter']])['email']));
        $modelo = self::AREAS[$area]['modelo'];
        $conta = $modelo::where('email', $email)->first();

        if ($conta && self::podeRedefinir($conta) && ($area !== 'produtor' || $conta->ativo)) {
            self::enviarLink($conta, $area);
        }

        return back()->with('ok', 'Se houver uma conta com este e-mail, enviamos um link para criar uma nova senha. Confira também a caixa de spam.');
    }

    /** Envia o link de nova senha (também usado pela Administração). */
    public static function enviarLink($conta, string $area): void
    {
        // Assinatura só do caminho (sem domínio/esquema): na Hostinger o HTTPS termina na CDN.
        $url = url(URL::temporarySignedRoute('senha.nova', now()->addMinutes(60), [
            'area' => $area, 'uuid' => $conta->uuid, 'h' => self::impressao(self::base($conta)),
        ], absolute: false));
        Notificacoes::redefinirSenha($conta->nome, $conta->email, $url, self::AREAS[$area]['nome']);
    }

    public function nova(Request $request, string $area, string $uuid)
    {
        $this->conta($request, $area, $uuid);

        return view('auth.senha-nova', ['area' => $area, 'info' => self::AREAS[$area]]);
    }

    public function salvar(Request $request, string $area, string $uuid)
    {
        $conta = $this->conta($request, $area, $uuid);
        $dados = $request->validate(['senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);

        $conta->senha_hash = Hash::make($dados['senha']);
        if ($conta instanceof Usuario) {
            $conta->tentativas_login = 0;
            $conta->bloqueado_ate = null;
        }
        $conta->save();

        if ($conta instanceof Usuario) {
            // Quem foi convidado e criou a senha pelo "Esqueci a senha" provou ter o e-mail: o convite pendente (não vencido)
            // vale como aceito, senão o login seria recusado ("acesso não está ativo em nenhuma organização").
            $pendentes = \App\Models\OrganizadorUsuario::withoutGlobalScopes()->where('usuario_id', $conta->id)->where('status', 'convidado')
                ->where(fn ($q) => $q->whereNull('convite_expira_em')->orWhere('convite_expira_em', '>', now()))->get();
            foreach ($pendentes as $v) {
                $v->forceFill(['status' => 'ativo', 'convite_token_hash' => null, 'convite_expira_em' => null])->save();
                \App\Support\Auditoria::registrar('equipe.aceitar_convite', $v, null, ['via' => 'nova_senha']);
            }
            if ($pendentes->isNotEmpty() && ! $conta->email_verificado_em) {
                $conta->forceFill(['email_verificado_em' => now()])->save();
            }
        }

        return redirect()->route(self::AREAS[$area]['login'])->with('ok', 'Senha alterada. Entre com a nova senha.');
    }

    private function conta(Request $request, string $area, string $uuid)
    {
        abort_unless($request->hasValidSignature(absolute: false), 403, 'Link inválido ou expirado. Peça um novo.');
        $conta = self::AREAS[$area]['modelo']::where('uuid', $uuid)->firstOrFail();
        abort_unless(self::podeRedefinir($conta) && hash_equals(self::impressao(self::base($conta)), (string) $request->query('h')), 403,
            'Este link já foi usado ou expirou. Peça um novo.');

        return $conta;
    }

    /**
     * Tem senha, ou é administrador da plataforma criado sem senha (primeiro acesso pelo link do e-mail).
     * Convites de equipe pendentes (sem senha) continuam fora: entram pelo link do convite.
     */
    private static function podeRedefinir($conta): bool
    {
        // Sem senha (comprador de cortesia, pessoa da equipe convidada que ainda não aceitou, administrador novo):
        // cria a senha pelo link, que só chega a quem tem acesso ao e-mail.
        return true;
    }

    /** O link deixa de valer quando a senha muda (ou é criada, no primeiro acesso). */
    private static function base($conta): string
    {
        return $conta->senha_hash ?: 'primeiro-acesso:'.$conta->uuid;
    }

    private static function impressao(string $senhaHash): string
    {
        return substr(hash_hmac('sha256', $senhaHash, (string) config('app.key')), 0, 24);
    }
}
