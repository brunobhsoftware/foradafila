<?php

namespace App\Http\Middleware;

use App\Models\Organizador;
use App\Models\OrganizadorUsuario;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o organizador da requisição a partir da sessão e confere se o usuário
 * logado tem vínculo ativo com ele. Roda antes do route model binding
 * (ver bootstrap/app.php), para que nenhum registro de outro organizador
 * seja carregado pela URL.
 */
class IdentificarOrganizador
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Administrador da plataforma "assumindo a conta" de uma empresa: vínculo virtual com o perfil Produtor.
        if (\App\Support\Esquema::temV008() && ($imp = \App\Support\Impersonacao::ativa())) {
            $organizador = Organizador::find($imp['organizador_id']);
            if ($organizador) {
                $vinculo = new OrganizadorUsuario(['organizador_id' => $organizador->id, 'status' => 'ativo', 'todos_eventos' => 1]);
                $vinculo->setRelation('perfil', \App\Models\PerfilAcesso::whereNull('organizador_id')->where('codigo', 'dono')->firstOrFail());
                app(Tenant::class)->definir($organizador, $vinculo);
                view()->share('tenant', app(Tenant::class));
                view()->share('organizadorAtual', $organizador);
                view()->share('temOutrasOrganizacoes', false);
                view()->share('impersonacao', $imp);

                return $next($request);
            }
            \App\Support\Impersonacao::encerrar();
        }

        $vinculos = $usuario->vinculosAtivos()->with('perfil')->get();

        if ($vinculos->isEmpty() && $usuario->admin_plataforma) {
            return redirect()->route('admin.inicio');
        }
        if ($vinculos->isEmpty()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('entrar')->withErrors(['email' => 'Seu acesso não está ativo em nenhuma organização.']);
        }

        $vinculo = $vinculos->firstWhere('organizador_id', $request->session()->get('organizador_id'));

        if (! $vinculo) {
            if ($vinculos->count() > 1) {
                return redirect()->route('organizacoes');
            }
            $vinculo = $vinculos->first();
            $request->session()->put('organizador_id', $vinculo->organizador_id);
        }

        $organizador = Organizador::findOrFail($vinculo->organizador_id);
        app(Tenant::class)->definir($organizador, $vinculo);

        view()->share('tenant', app(Tenant::class));
        view()->share('organizadorAtual', $organizador);
        view()->share('temOutrasOrganizacoes', $vinculos->count() > 1);

        return $next($request);
    }
}
