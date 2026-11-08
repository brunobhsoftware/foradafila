<?php

namespace App\Providers;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Um Tenant por requisição.
        $this->app->scoped(Tenant::class);
    }

    public function boot(): void
    {
        $sessoes = config('session.files');
        if (config('session.driver') === 'file' && $sessoes && ! is_dir($sessoes)) {
            @mkdir($sessoes, 0750, true);
        }

        // Em desenvolvimento, acusa erro ao preencher coluna fora do $fillable ou ler coluna inexistente.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('components.paginacao');

        // Permissões no formato modulo.acao vêm do perfil do usuário no organizador atual
        // (perfil_permissao). Ex.: @can('evento.publicar'), Gate::authorize('equipe.gerenciar').
        Gate::before(function ($usuario, string $permissao) {
            if (str_contains($permissao, '.')) {
                return app(Tenant::class)->pode($permissao);
            }

            return null;
        });
    }
}
