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
Route::get('/pedido/{pedido}/pagamento', [\App\Http\Controllers\Publico\PagamentoController::class, 'retorno'])->name('pedido.retorno')
    ->whereUuid('pedido')->middleware('throttle:30,1,pedido-retorno');
Route::post('/webhooks/mercadopago/{conta}', [WebhookController::class, 'mercadoPagoEmpresa'])->name('webhook.mercadopago.empresa')->whereUuid('conta')
    ->middleware('throttle:120,1,webhook-mp-empresa');
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
    Route::post('/usuarios/{usuario}/senha', [\App\Http\Controllers\Admin\EmpresasController::class, 'enviarSenha'])->name('empresas.senha')->middleware('throttle:20,10,admin-senha');
    Route::put('/usuarios/{usuario}/email', [\App\Http\Controllers\Admin\EmpresasController::class, 'corrigirEmail'])->name('empresas.email');
    Route::get('/repasses', [\App\Http\Controllers\Admin\RepassesController::class, 'index'])->name('repasses');
    Route::post('/repasses/{organizador}', [\App\Http\Controllers\Admin\RepassesController::class, 'registrar'])->name('repasses.registrar');
    Route::get('/emails', [\App\Http\Controllers\Admin\AdminController::class, 'emails'])->name('emails');
    Route::get('/banco', [\App\Http\Controllers\Admin\AdminController::class, 'banco'])->name('banco');
    Route::post('/banco', [\App\Http\Controllers\Admin\AdminController::class, 'aplicarBanco'])->name('banco.aplicar')->middleware('throttle:10,10,admin-banco');
    Route::get('/empresas', [\App\Http\Controllers\Admin\EmpresasController::class, 'index'])->name('empresas');
    Route::get('/empresas/{organizador}', [\App\Http\Controllers\Admin\EmpresasController::class, 'show'])->name('empresas.show');
    Route::put('/empresas/{organizador}/situacao', [\App\Http\Controllers\Admin\EmpresasController::class, 'situacao'])->name('empresas.situacao');
    Route::post('/empresas/{organizador}/assumir', [\App\Http\Controllers\Admin\EmpresasController::class, 'assumir'])->name('empresas.assumir');
    Route::post('/empresas/{organizador}/taxas', [\App\Http\Controllers\Admin\EmpresasController::class, 'salvarTaxa'])->name('empresas.taxas');
    Route::put('/empresas/{organizador}/taxas/{taxa}', [\App\Http\Controllers\Admin\EmpresasController::class, 'salvarTaxa'])->name('empresas.taxas.editar')->whereNumber('taxa');
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
// Cadastro único (V011): busca pelo CPF, só para a equipe logada; cada consulta vai para a auditoria.
Route::get('/compra/cadastro', [\App\Http\Controllers\Publico\CadastroCompradorController::class, 'mostrar'])->middleware('throttle:20,1,compra-cadastro')->name('compra.cadastro');
Route::post('/compra/cadastro/codigo', [\App\Http\Controllers\Publico\CadastroCompradorController::class, 'enviarCodigo'])->middleware('throttle:5,10,compra-cadastro-codigo')->name('compra.cadastro.codigo');
Route::post('/compra/cadastro', [\App\Http\Controllers\Publico\CadastroCompradorController::class, 'salvar'])->middleware('throttle:10,10,compra-cadastro-salvar')->name('compra.cadastro.salvar');
Route::get('/compra/cpf/{cpf}', [\App\Http\Controllers\Publico\InscricaoController::class, 'cpf'])->middleware('throttle:10,1,compra-cpf')->where('cpf', '[0-9]{11}')->name('compra.cpf');
Route::get('/cadastro-unico/{cpf}', function (string $cpf) {
    $p = \App\Support\CadastroPessoa::buscar($cpf);
    \App\Support\Auditoria::registrar('pessoa.consulta', auth()->user(), null, ['cpf_final' => substr(preg_replace('/\D/', '', $cpf), -4), 'encontrado' => (bool) $p]);

    return response()->json($p ? ['encontrado' => true] + $p : ['encontrado' => false]);
})->middleware(['auth', 'throttle:30,1,cadastro-unico'])->where('cpf', '[0-9]{11}')->name('cadastro-unico');

Route::middleware(['auth', 'organizador'])->prefix('painel')->group(function () {
    Route::get('/', DashboardController::class)->name('painel');

    Route::get('/formularios', [\App\Http\Controllers\Painel\FormulariosController::class, 'index'])->name('formularios.index');
    Route::get('/formularios/novo', [\App\Http\Controllers\Painel\FormulariosController::class, 'editar'])->name('formularios.novo');
    Route::post('/formularios', [\App\Http\Controllers\Painel\FormulariosController::class, 'salvar'])->name('formularios.criar');
    Route::get('/formularios/{id}', [\App\Http\Controllers\Painel\FormulariosController::class, 'editar'])->name('formularios.editar')->whereNumber('id');
    Route::post('/formularios/{id}', [\App\Http\Controllers\Painel\FormulariosController::class, 'salvar'])->name('formularios.salvar')->whereNumber('id');
    Route::post('/formularios/{id}/padrao', [\App\Http\Controllers\Painel\FormulariosController::class, 'padrao'])->name('formularios.padrao')->whereNumber('id');
    Route::delete('/formularios/{id}', [\App\Http\Controllers\Painel\FormulariosController::class, 'excluir'])->name('formularios.excluir')->whereNumber('id');
    Route::get('/financeiro', [FinanceiroController::class, 'index'])->name('financeiro');
    Route::put('/financeiro/mercadopago/propria/{ambiente}', [FinanceiroController::class, 'salvarPropria'])->name('financeiro.mp.propria')->whereIn('ambiente', ['aplicacao', 'teste', 'producao']);
    Route::post('/financeiro/mercadopago/propria/verificar', [FinanceiroController::class, 'verificarPropria'])->name('financeiro.mp.propria.verificar')->middleware('throttle:10,1,mp-propria-verificar');
    Route::post('/financeiro/mercadopago/propria/teste', [FinanceiroController::class, 'testePropria'])->name('financeiro.mp.propria.teste')
        ->middleware('throttle:10,10,mp-propria-teste');
    Route::put('/financeiro/ambiente-vendas', [FinanceiroController::class, 'ambienteVendas'])->name('financeiro.ambiente');
    Route::put('/financeiro/modo', [FinanceiroController::class, 'modoRecebimento'])->name('financeiro.modo');
    Route::put('/financeiro/recebimento', [FinanceiroController::class, 'salvarRecebimento'])->name('financeiro.recebimento');
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

    Route::get('/participantes', [\App\Http\Controllers\Painel\ParticipantesController::class, 'index'])->name('participantes.index');
    Route::get('/participantes/exportar', [\App\Http\Controllers\Painel\ParticipantesController::class, 'exportar'])->name('participantes.exportar');
    Route::post('/ingressos/{ingresso}/reenviar', [\App\Http\Controllers\Painel\ParticipantesController::class, 'reenviar'])->name('ingressos.reenviar')->middleware('throttle:30,10,reenviar-ingresso');
    Route::post('/ingressos/{ingresso}/cancelar', [\App\Http\Controllers\Painel\ParticipantesController::class, 'cancelar'])->name('ingressos.cancelar');
    Route::get('/pedidos', [\App\Http\Controllers\Painel\PedidosController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/{pedido}', [\App\Http\Controllers\Painel\PedidosController::class, 'show'])->name('pedidos.show');
    Route::post('/pedidos/{pedido}/reenviar', [\App\Http\Controllers\Painel\PedidosController::class, 'reenviar'])->name('pedidos.reenviar')->middleware('throttle:30,10,reenviar-pedido');
    Route::post('/pedidos/{pedido}/cancelar', [\App\Http\Controllers\Painel\PedidosController::class, 'cancelar'])->name('pedidos.cancelar');

    Route::get('/checkin', [\App\Http\Controllers\Painel\CheckinController::class, 'index'])->name('checkin.index');
    Route::post('/checkin/{evento}/registrar', [\App\Http\Controllers\Painel\CheckinController::class, 'registrar'])->name('checkin.registrar')->middleware('throttle:240,1,checkin');
    Route::get('/checkin/{evento}/buscar', [\App\Http\Controllers\Painel\CheckinController::class, 'buscar'])->name('checkin.buscar')->middleware('throttle:240,1,checkin-busca');
    Route::post('/checkin/{evento}/desfazer', [\App\Http\Controllers\Painel\CheckinController::class, 'desfazer'])->name('checkin.desfazer');

    Route::get('/cupons', [\App\Http\Controllers\Painel\CuponsController::class, 'index'])->name('cupons.index');
    Route::post('/cupons', [\App\Http\Controllers\Painel\CuponsController::class, 'store'])->name('cupons.store');
    Route::put('/cupons/{cupom}', [\App\Http\Controllers\Painel\CuponsController::class, 'update'])->name('cupons.update');
    Route::post('/cupons/{cupom}/alternar', [\App\Http\Controllers\Painel\CuponsController::class, 'alternar'])->name('cupons.alternar');

    Route::get('/convidados', [\App\Http\Controllers\Painel\ConvidadosController::class, 'index'])->name('convidados.index');
    Route::post('/convidados/{evento}/listas', [\App\Http\Controllers\Painel\ConvidadosController::class, 'salvarLista'])->name('convidados.lista.criar');
    Route::get('/convidados/{evento}/listas/{lista}', [\App\Http\Controllers\Painel\ConvidadosController::class, 'lista'])->name('convidados.lista')->whereNumber('lista');
    Route::post('/convidados/{evento}/listas/{lista}', [\App\Http\Controllers\Painel\ConvidadosController::class, 'salvarLista'])->name('convidados.lista.salvar')->whereNumber('lista');
    Route::post('/convidados/{evento}/listas/{lista}/convidados', [\App\Http\Controllers\Painel\ConvidadosController::class, 'adicionar'])->name('convidados.adicionar')->whereNumber('lista');
    Route::post('/convidados/{evento}/listas/{lista}/emitir', [\App\Http\Controllers\Painel\ConvidadosController::class, 'emitir'])->name('convidados.emitir')->whereNumber('lista')->middleware('throttle:30,10,convidados-emitir');
    Route::post('/convidados/{evento}/listas/{lista}/convidados/{convidado}/remover', [\App\Http\Controllers\Painel\ConvidadosController::class, 'remover'])->name('convidados.remover')->whereNumber(['lista', 'convidado']);

    Route::get('/mensagens', [\App\Http\Controllers\Painel\MensagensController::class, 'index'])->name('mensagens.index');
    Route::post('/mensagens/modelo', [\App\Http\Controllers\Painel\MensagensController::class, 'salvarModelo'])->name('mensagens.modelo');
    Route::post('/mensagens/modelo/restaurar', [\App\Http\Controllers\Painel\MensagensController::class, 'restaurarModelo'])->name('mensagens.restaurar');
    Route::post('/mensagens/previa', [\App\Http\Controllers\Painel\MensagensController::class, 'previa'])->name('mensagens.previa');
    Route::post('/mensagens/comunicado', [\App\Http\Controllers\Painel\MensagensController::class, 'comunicado'])->name('mensagens.comunicado')->middleware('throttle:5,60,comunicado');

    // Módulos das próximas fases: a tela explica o que vem e o menu já fica no lugar certo.
    Route::get('/{modulo}', EmBreveController::class)->name('em-breve')
        ->whereIn('modulo', array_keys(EmBreveController::MODULOS));
});
