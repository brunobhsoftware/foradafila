<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Organizador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** "Não quero mais receber" dos convites por e-mail: link assinado por empresa + e-mail; também atende o clique único do Gmail (POST). */
class DescadastroController extends Controller
{
    public function mostrar(Request $request, int $org)
    {
        abort_unless($request->hasValidSignature(false), 403);
        $email = $this->email($request);
        $empresa = Organizador::findOrFail($org);
        $feito = DB::table('email_descadastro')->where('organizador_id', $org)->where('email', $email)->exists();

        return view('publico.descadastro', ['empresa' => $empresa, 'email' => $email, 'feito' => $feito]);
    }

    public function confirmar(Request $request, int $org)
    {
        abort_unless($request->hasValidSignature(false), 403);
        $email = $this->email($request);
        Organizador::findOrFail($org);
        DB::table('email_descadastro')->insertOrIgnore(['organizador_id' => $org, 'email' => $email, 'motivo' => mb_substr((string) $request->input('motivo'), 0, 255) ?: null]);

        return $request->expectsJson() || $request->has('List-Unsubscribe')
            ? response('ok')
            : redirect()->to($request->fullUrl())->with('ok', 'Pronto: você não vai mais receber convites desta empresa.');
    }

    private function email(Request $request): string
    {
        $email = mb_strtolower((string) base64_decode((string) $request->query('email'), true));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 404);

        return $email;
    }
}
