<?php

use App\Http\Controllers\Auth\CadastroController;
use App\Http\Controllers\Auth\ConviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OrganizacaoSelecaoController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\Painel\DashboardController;
use App\Http\Controllers\Painel\EmBreveController;
use App\Http\Controllers\Painel\EquipeController;
use App\Http\Controllers\Painel\EventoController;
use App\Http\Controllers\Painel\LoteController;
use App\Http\Controllers\Painel\MinhaContaController;
use App\Http\Controllers\Painel\FinanceiroController;
use App\Http\Controllers\Painel\OrganizacaoController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Painel\PerfilController;
use App\Http\Controllers\Painel\PreferenciasController;
use App\Http\Controllers\Publico\CompradorAuthController;
use App\Http\Controllers\Publico\EventoPublicoController;
use App\Http\Controllers\Publico\InscricaoController;
use App\Http\Controllers\Publico\MeusIngressosController;
use App\Http\Controllers\MidiaController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// ---------- Site público ----------
Route::get('/', [SiteController::class, 'inicio'])->name('inicio');
Route::get('/termos/{tipo}', [SiteController::class, 'termo'])->name('termo')
    ->whereIn('tipo', ['uso_organizador', 'responsabilidade_organizador', 'compra_comprador', 'privacidade']);
Route::get('/midia/{pasta}/{arquivo}', MidiaController::class)->name('midia')
    ->where(['pasta' => 'eventos|logomarcas', 'arquivo' => '[a-z0-9-]+_[0-9a-f-]{36}\.(jpg|png|gif)']);
Route::view('/solucao', 'site.solucao')->name('solucao');
Route::view('/precos', 'site.precos')->name('precos');
Route::view('/ajuda', 'site.ajuda')->name('ajuda');

// ---------- Eventos (público) ----------
Route::get('/eventos', [EventoPublicoController::class, 'vitrine'])->name('vitrine');
Route::get('/e/{slug}', [EventoPublicoController::class, 'show'])->name('evento.publico');
Route::get('/p/{slug}', [EventoPublicoController::class, 'organizador'])->name('organizador.publico');
Route::get('/e/{slug}/inscricao', [InscricaoController::class, 'create'])->name('inscricao.create');
Route::post('/e/{slug}/inscricao', [InscricaoController::class, 'store'])->name('inscricao.store')->middleware('throttle:15,1,inscricao');

// ---------- Área do comprador (guard próprio, separado do produtor) ----------
Route::prefix('minha-conta')->name('comprador.')->group(function () {
    Route::middleware('guest:comprador')->group(function () {
        Route::get('/entrar', [CompradorAuthController::class, 'entrar'])->name('entrar');
        Route::post('/entrar', [CompradorAuthController::class, 'autenticar'])->middleware('throttle:20,1,login-comprador');
        Route::get('/criar', [CompradorAuthController::class, 'criar'])->name('criar');
        Route::post('/criar', [CompradorAuthController::class, 'registrar'])->middleware('throttle:10,1,cadastro-comprador');
    });
    Route::middleware('auth:comprador')->group(function () {
        Route::get('/', [MeusIngressosController::class, 'index'])->name('ingressos');
        Route::get('/ingressos/{uuid}', [MeusIngressosController::class, 'show'])->name('ingresso');
        Route::post('/pedidos/{uuid}/cancelar', [MeusIngressosController::class, 'cancelar'])->name('cancelar');
        Route::post('/sair', [CompradorAuthController::class, 'sair'])->name('sair');
    });
});

// ---------- Entrada ----------
Route::middleware('guest')->group(function () {
    Route::get('/cadastro', [CadastroController::class, 'create'])->name('cadastro');
    Route::post('/cadastro', [CadastroController::class, 'store'])->middleware('throttle:10,1,cadastro');
    Route::get('/entrar', [LoginController::class, 'create'])->name('entrar');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:20,1,login');
});

// ---------- Esqueci a senha (produtor e comprador) ----------
Route::get('/esqueci-a-senha/{area}', [SenhaController::class, 'pedir'])->name('senha.pedir')->whereIn('area', ['produtor', 'comprador']);
Route::post('/esqueci-a-senha/{area}', [SenhaController::class, 'enviar'])->whereIn('area', ['produtor', 'comprador'])->middleware('throttle:5,10,esqueci-senha');
Route::get('/nova-senha/{area}/{uuid}', [SenhaController::class, 'nova'])->name('senha.nova')->whereIn('area', ['produtor', 'comprador']);
Route::post('/nova-senha/{area}/{uuid}', [SenhaController::class, 'salvar'])->whereIn('area', ['produtor', 'comprador'])->middleware('throttle:10,10,nova-senha');

Route::get('/convite/{token}', [ConviteController::class, 'show'])->name('convite');
Route::post('/convite/{token}', [ConviteController::class, 'store'])->middleware('throttle:10,1,convite');

Route::middleware('auth')->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy'])->name('sair');
    Route::get('/organizacoes', [OrganizacaoSelecaoController::class, 'index'])->name('organizacoes');
    Route::post('/organizacoes', [OrganizacaoSelecaoController::class, 'store']);
});

// ---------- Avisos das operadoras de pagamento (sem login e sem CSRF) ----------
Route::post('/webhooks/mercadopago', [WebhookController::class, 'mercadoPago'])->name('webhook.mercadopago')
    ->middleware('throttle:120,1,webhook-mp');

// ---------- Administração da plataforma (equipe BH Software) ----------
// Retorno do pagamento de teste: aberto (o pagamento costuma ser feito numa janela anônima, sem login).
// Sem login mostra só os IDs que vieram na URL; a conferência na API do Mercado Pago é só para administrador.
Route::get('/admin/mercadopago/teste/retorno', [\App\Http\Controllers\Admin\AdminController::class, 'retornoTeste'])
    ->name('admin.mercadopago.teste.retorno')->middleware('throttle:30,1,admin-mp-retorno');
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'inicio'])->name('inicio');
    Route::get('/mercadopago', [\App\Http\Controllers\Admin\AdminController::class, 'mercadoPago'])->name('mercadopago');
    Route::get('/empresas', [\App\Http\Controllers\Admin\EmpresasController::class, 'index'])->name('empresas');
    Route::get('/empresas/{organizador}', [\App\Http\Controllers\Admin\EmpresasController::class, 'show'])->name('empresas.show');
    Route::put('/empresas/{organizador}/situacao', [\App\Http\Controllers\Admin\EmpresasController::class, 'situacao'])->name('empresas.situacao');
    Route::post('/empresas/{organizador}/assumir', [\App\Http\Controllers\Admin\EmpresasController::class, 'assumir'])->name('empresas.assumir');
    Route::post('/empresas/{organizador}/taxas', [\App\Http\Controllers\Admin\EmpresasController::class, 'salvarTaxa'])->name('empresas.taxas');
    Route::post('/empresas/{organizador}/taxas/{taxa}/encerrar', [\App\Http\Controllers\Admin\EmpresasController::class, 'encerrarTaxa'])->name('empresas.taxas.encerrar')->whereNumber('taxa');
    Route::get('/taxas', [\App\Http\Controllers\Admin\TaxasController::class, 'index'])->name('taxas');
    Route::post('/taxas', [\App\Http\Controllers\Admin\TaxasController::class, 'salvar'])->name('taxas.criar');
    Route::put('/taxas/{regra}', [\App\Http\Controllers\Admin\TaxasController::class, 'salvar'])->name('taxas.salvar')->whereNumber('regra');
    Route::put('/mercadopago', [\App\Http\Controllers\Admin\AdminController::class, 'salvarMercadoPago'])->name('mercadopago.salvar')
        ->middleware('throttle:20,10,admin-mp');
    Route::post('/mercadopago/verificar', [\App\Http\Controllers\Admin\AdminController::class, 'verificarTokens'])->name('mercadopago.verificar')
        ->middleware('throttle:20,10,admin-mp-verificar');
    Route::post('/mercadopago/teste', [\App\Http\Controllers\Admin\AdminController::class, 'testarPagamento'])->name('mercadopago.teste')
        ->middleware('throttle:10,10,admin-mp-teste');
});

// Sair do "assumir conta" (fora do grupo admin: é chamado de dentro do painel da empresa).
Route::post('/admin/sair-da-conta', [\App\Http\Controllers\Admin\EmpresasController::class, 'sairDaConta'])->middleware(['auth', 'admin'])->name('admin.sair-da-conta');

// ---------- Área do produtor ----------
Route::middleware(['auth', 'organizador'])->prefix('painel')->group(function () {
    Route::get('/', DashboardController::class)->name('painel');

    Route::get('/financeiro', [FinanceiroController::class, 'index'])->name('financeiro');
    Route::post('/financeiro/mercadopago/conectar', [FinanceiroController::class, 'conectar'])->name('financeiro.mp.conectar');
    Route::get('/financeiro/mercadopago/retorno', [FinanceiroController::class, 'retorno'])->name('financeiro.mp.retorno');
    Route::post('/financeiro/mercadopago/desconectar', [FinanceiroController::class, 'desconectar'])->name('financeiro.mp.desconectar');

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
    Route::post('/lotes/{lote}/alternar', [LoteController::class, 'alternar'])->name('lotes.alternar');

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

    // Conta de acesso da pessoa logada: trocar senha e e-mail com código enviado por e-mail.
    Route::get('/minha-conta/senha', [MinhaContaController::class, 'senha'])->name('conta.senha');
    Route::post('/minha-conta/senha/codigo', [MinhaContaController::class, 'enviarCodigoSenha'])->name('conta.senha.codigo')->middleware('throttle:5,15,codigo-senha');
    Route::put('/minha-conta/senha', [MinhaContaController::class, 'salvarSenha'])->name('conta.senha.salvar')->middleware('throttle:10,15,salvar-senha');
    Route::get('/minha-conta/email', [MinhaContaController::class, 'email'])->name('conta.email');
    Route::post('/minha-conta/email/codigo', [MinhaContaController::class, 'enviarCodigoEmail'])->name('conta.email.codigo')->middleware('throttle:5,15,codigo-email');
    Route::put('/minha-conta/email', [MinhaContaController::class, 'salvarEmail'])->name('conta.email.salvar')->middleware('throttle:10,15,salvar-email');

    Route::get('/preferencias', [PreferenciasController::class, 'edit'])->name('preferencias.edit');
    Route::put('/preferencias', [PreferenciasController::class, 'update'])->name('preferencias.update');

    // Módulos das próximas fases: a tela explica o que vem e o menu já fica no lugar certo.
    Route::get('/{modulo}', EmBreveController::class)->name('em-breve')
        ->whereIn('modulo', array_keys(EmBreveController::MODULOS));
});
