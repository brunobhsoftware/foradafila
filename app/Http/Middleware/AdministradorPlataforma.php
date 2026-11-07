<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Só a equipe da plataforma (usuario.admin_plataforma = 1). Os demais recebem 404, sem revelar que a área existe. */
class AdministradorPlataforma
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->admin_plataforma, 404);

        return $next($request);
    }
}
