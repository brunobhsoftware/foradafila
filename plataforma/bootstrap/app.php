<?php

use App\Http\Middleware\IdentificarOrganizador;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'organizador' => IdentificarOrganizador::class,
        ]);
        // O organizador precisa estar definido ANTES de resolver {evento}, {lote} etc. pela URL:
        // assim o filtro por organizador_id vale também para o route model binding.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: IdentificarOrganizador::class,
        );
        $middleware->redirectGuestsTo(fn () => route('entrar'));
        $middleware->redirectUsersTo(fn () => route('painel'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Em produção (Hostinger) o .env fica FORA do public_html, uma pasta acima da
// aplicação: /home/<conta>/domains/foradafila.com.br/.env.foradafila
// Assim as credenciais nunca ficam dentro da pasta publicada nem no Git.
$pastaExterna = dirname(__DIR__, 2);
if (is_file($pastaExterna.'/.env.foradafila')) {
    $app->useEnvironmentPath($pastaExterna);
    $app->loadEnvironmentFrom('.env.foradafila');
}

return $app;
