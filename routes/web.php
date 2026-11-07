<?php

use App\Http\Controllers\Auth\CadastroController;
use App\Http\Controllers\Auth\ConviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizacaoSelecaoController;
use App\Http\Controllers\Painel\DashboardController;
use App\Http\Controllers\Painel\EmBreveController;
use App\Http\Controllers\Painel\EquipeController;
use App\Http\Controllers\Painel\EventoController;
use App\Http\Controllers\Painel\LoteController;
use App\Http\Controllers\Painel\OrganizacaoController;
use App\Http\Controllers\Painel\PerfilController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// ---------- Site público ----------
Route::get('/', [SiteController::class, 'inicio'])->name('inicio');
Route::get('/termos/{tipo}', [SiteController::class, 'termo'])->name('termo')
    ->whereIn('tipo', ['uso_organizador', 'responsabilidade_organizador', 'compra_comprador', 'privacidade']);

// ---------- Entrada ----------
Route::middleware('guest')->group(function () {
    Route::get('/cadastro', [CadastroController::class, 'create'])->name('cadastro');
    Route::post('/cadastro', [CadastroController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/entrar', [LoginController::class, 'create'])->name('entrar');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::get('/convite/{token}', [ConviteController::class, 'show'])->name('convite');
Route::post('/convite/{token}', [ConviteController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('sair');
    Route::get('/organizacoes', [OrganizacaoSelecaoController::class, 'index'])->name('organizacoes');
    Route::post('/organizacoes', [OrganizacaoSelecaoController::class, 'store']);
});

// ---------- Área do produtor ----------
Route::middleware(['auth', 'organizador'])->prefix('painel')->group(function () {
    Route::get('/', DashboardController::class)->name('painel');

    Route::get('/eventos', [EventoController::class, 'index'])->name('eventos.index');
    Route::get('/eventos/novo', [EventoController::class, 'create'])->name('eventos.create');
    Route::post('/eventos', [EventoController::class, 'store'])->name('eventos.store');
    Route::get('/eventos/{evento}', [EventoController::class, 'show'])->name('eventos.show');
    Route::get('/eventos/{evento}/editar', [EventoController::class, 'edit'])->name('eventos.edit');
    Route::put('/eventos/{evento}', [EventoController::class, 'update'])->name('eventos.update');
    Route::post('/eventos/{evento}/status', [EventoController::class, 'status'])->name('eventos.status');

    Route::post('/eventos/{evento}/tipos', [LoteController::class, 'storeTipo'])->name('tipos.store');
    Route::put('/tipos/{tipo}', [LoteController::class, 'updateTipo'])->name('tipos.update');
    Route::delete('/tipos/{tipo}', [LoteController::class, 'destroyTipo'])->name('tipos.destroy');
    Route::post('/eventos/{evento}/lotes', [LoteController::class, 'store'])->name('lotes.store');
    Route::put('/lotes/{lote}', [LoteController::class, 'update'])->name('lotes.update');
    Route::delete('/lotes/{lote}', [LoteController::class, 'destroy'])->name('lotes.destroy');

    Route::get('/equipe', [EquipeController::class, 'index'])->name('equipe.index');
    Route::post('/equipe', [EquipeController::class, 'store'])->name('equipe.store');
    Route::put('/equipe/{vinculo}', [EquipeController::class, 'update'])->name('equipe.update');
    Route::post('/equipe/{vinculo}/convite', [EquipeController::class, 'reenviar'])->name('equipe.reenviar');
    Route::delete('/equipe/{vinculo}', [EquipeController::class, 'destroy'])->name('equipe.destroy');

    Route::get('/perfis', [PerfilController::class, 'index'])->name('perfis.index');
    Route::get('/perfis/novo', [PerfilController::class, 'create'])->name('perfis.create');
    Route::post('/perfis', [PerfilController::class, 'store'])->name('perfis.store');
    Route::get('/perfis/{perfil}/editar', [PerfilController::class, 'edit'])->name('perfis.edit');
    Route::put('/perfis/{perfil}', [PerfilController::class, 'update'])->name('perfis.update');

    Route::get('/organizacao', [OrganizacaoController::class, 'edit'])->name('organizacao.edit');
    Route::put('/organizacao', [OrganizacaoController::class, 'update'])->name('organizacao.update');

    // Módulos das próximas fases: a tela explica o que vem e o menu já fica no lugar certo.
    Route::get('/{modulo}', EmBreveController::class)->name('em-breve')
        ->whereIn('modulo', array_keys(EmBreveController::MODULOS));
});
