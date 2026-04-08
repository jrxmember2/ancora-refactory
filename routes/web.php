<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CobrancaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalDocumentController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('ancora.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/esqueci-a-senha', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/esqueci-a-senha', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/resetar-senha/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset.form');
    Route::post('/resetar-senha', [PasswordResetController::class, 'reset'])->name('password.reset.update');
});

Route::middleware('ancora.auth')->group(function () {
    Route::get('/', [HubController::class, 'index'])->name('hub');
    Route::get('/hub', [HubController::class, 'index']);
    Route::get('/desktop', [HubController::class, 'index']);

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('ancora.route:dashboard.index');

    Route::prefix('propostas')->group(function () {
        Route::get('/dashboard', [ProposalController::class, 'dashboard'])->name('propostas.dashboard')->middleware('ancora.route:propostas.dashboard');
        Route::get('/', [ProposalController::class, 'index'])->name('propostas.index')->middleware('ancora.route:propostas.index');
        Route::get('/export/csv', [ProposalController::class, 'exportCsv'])->name('propostas.export.csv')->middleware('ancora.route:propostas.export.csv');
        Route::get('/nova', [ProposalController::class, 'create'])->name('propostas.create')->middleware('ancora.route:propostas.create');
        Route::post('/store', [ProposalController::class, 'store'])->name('propostas.store')->middleware('ancora.route:propostas.store');
        Route::get('/{proposta}', [ProposalController::class, 'show'])->name('propostas.show')->middleware('ancora.route:propostas.show');
        Route::get('/{proposta}/imprimir', [ProposalController::class, 'printView'])->name('propostas.print')->middleware('ancora.route:propostas.print');
        Route::post('/{proposta}/anexos/upload', [ProposalController::class, 'uploadAttachment'])->name('propostas.attachments.upload')->middleware('ancora.route:propostas.attachments.upload');
        Route::get('/{proposta}/anexos/{attachment}/download', [ProposalController::class, 'downloadAttachment'])->name('propostas.attachments.download')->middleware('ancora.route:propostas.attachments.download');
        Route::delete('/{proposta}/anexos/{attachment}', [ProposalController::class, 'deleteAttachment'])->name('propostas.attachments.delete')->middleware('ancora.route:propostas.attachments.delete');
        Route::get('/{proposta}/editar', [ProposalController::class, 'edit'])->name('propostas.edit')->middleware('ancora.route:propostas.edit');
        Route::put('/{proposta}', [ProposalController::class, 'update'])->name('propostas.update')->middleware('ancora.route:propostas.update');
        Route::delete('/{proposta}', [ProposalController::class, 'destroy'])->name('propostas.delete')->middleware('ancora.route:propostas.delete');
        Route::get('/{proposta}/documento', [ProposalDocumentController::class, 'edit'])->name('propostas.document.edit')->middleware('ancora.route:propostas.document.edit');
        Route::post('/{proposta}/documento/save', [ProposalDocumentController::class, 'save'])->name('propostas.document.save')->middleware('ancora.route:propostas.document.save');
        Route::get('/{proposta}/documento/preview', [ProposalDocumentController::class, 'preview'])->name('propostas.document.preview')->middleware('ancora.route:propostas.document.preview');
        Route::get('/{proposta}/documento/pdf', [ProposalDocumentController::class, 'print'])->name('propostas.document.pdf')->middleware('ancora.route:propostas.document.pdf');
    });

    Route::get('/busca', [SearchController::class, 'index'])->name('busca')->middleware('ancora.route:busca.index');

    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index')->middleware('ancora.route:changelog.index');

    Route::get('/config', [ConfigController::class, 'index'])->name('config.index')->middleware(['ancora.superadmin', 'ancora.route:config.index']);
    Route::post('/config/branding/save', [ConfigController::class, 'saveBranding'])->name('config.branding.save')->middleware(['ancora.superadmin', 'ancora.route:config.branding.save']);
    Route::post('/config/favicon/save', [ConfigController::class, 'saveFavicon'])->name('config.favicon.save')->middleware(['ancora.superadmin', 'ancora.route:config.favicon.save']);
    Route::post('/config/modules/save', [ConfigController::class, 'saveModules'])->name('config.modules.save')->middleware(['ancora.superadmin', 'ancora.route:config.modules.save']);
    Route::post('/config/smtp/save', [ConfigController::class, 'saveSmtp'])->name('config.smtp.save')->middleware(['ancora.superadmin', 'ancora.route:config.smtp.save']);
    Route::post('/config/access-profiles/save', [ConfigController::class, 'saveAccessProfiles'])->name('config.access-profiles.save')->middleware(['ancora.superadmin', 'ancora.route:config.access-profiles.save']);
    Route::delete('/config/access-profiles/{slug}', [ConfigController::class, 'deleteAccessProfile'])->name('config.access-profiles.delete')->middleware(['ancora.superadmin', 'ancora.route:config.access-profiles.delete']);
    Route::post('/config/administradoras/store', [ConfigController::class, 'storeAdministradora'])->name('config.administradoras.store')->middleware(['ancora.superadmin', 'ancora.route:config.administradoras.store']);
    Route::put('/config/administradoras/{administradora}', [ConfigController::class, 'updateAdministradora'])->name('config.administradoras.update')->middleware(['ancora.superadmin', 'ancora.route:config.administradoras.update']);
    Route::delete('/config/administradoras/{administradora}', [ConfigController::class, 'deleteAdministradora'])->name('config.administradoras.delete')->middleware(['ancora.superadmin', 'ancora.route:config.administradoras.delete']);
    Route::post('/config/servicos/store', [ConfigController::class, 'storeServico'])->name('config.servicos.store')->middleware(['ancora.superadmin', 'ancora.route:config.servicos.store']);
    Route::put('/config/servicos/{servico}', [ConfigController::class, 'updateServico'])->name('config.servicos.update')->middleware(['ancora.superadmin', 'ancora.route:config.servicos.update']);
    Route::delete('/config/servicos/{servico}', [ConfigController::class, 'deleteServico'])->name('config.servicos.delete')->middleware(['ancora.superadmin', 'ancora.route:config.servicos.delete']);
    Route::post('/config/status/store', [ConfigController::class, 'storeStatus'])->name('config.status.store')->middleware(['ancora.superadmin', 'ancora.route:config.status.store']);
    Route::put('/config/status/{status}', [ConfigController::class, 'updateStatus'])->name('config.status.update')->middleware(['ancora.superadmin', 'ancora.route:config.status.update']);
    Route::delete('/config/status/{status}', [ConfigController::class, 'deleteStatus'])->name('config.status.delete')->middleware(['ancora.superadmin', 'ancora.route:config.status.delete']);
    Route::post('/config/formas-envio/store', [ConfigController::class, 'storeFormaEnvio'])->name('config.formas.store')->middleware(['ancora.superadmin', 'ancora.route:config.formas.store']);
    Route::put('/config/formas-envio/{forma}', [ConfigController::class, 'updateFormaEnvio'])->name('config.formas.update')->middleware(['ancora.superadmin', 'ancora.route:config.formas.update']);
    Route::delete('/config/formas-envio/{forma}', [ConfigController::class, 'deleteFormaEnvio'])->name('config.formas.delete')->middleware(['ancora.superadmin', 'ancora.route:config.formas.delete']);
    Route::post('/config/usuarios/store', [ConfigController::class, 'storeUsuario'])->name('config.users.store')->middleware(['ancora.superadmin', 'ancora.route:config.users.store']);
    Route::put('/config/usuarios/{user}', [ConfigController::class, 'updateUsuario'])->name('config.users.update')->middleware(['ancora.superadmin', 'ancora.route:config.users.update']);
    Route::delete('/config/usuarios/{user}', [ConfigController::class, 'deleteUsuario'])->name('config.users.delete')->middleware(['ancora.superadmin', 'ancora.route:config.users.delete']);

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index')->middleware('ancora.route:logs.index');


    Route::prefix('cobrancas')->group(function () {
        Route::get('/dashboard', [CobrancaController::class, 'dashboard'])->name('cobrancas.dashboard')->middleware('ancora.route:cobrancas.dashboard');
        Route::get('/', [CobrancaController::class, 'index'])->name('cobrancas.index')->middleware('ancora.route:cobrancas.index');
        Route::get('/nova', [CobrancaController::class, 'create'])->name('cobrancas.create')->middleware('ancora.route:cobrancas.create');
        Route::post('/store', [CobrancaController::class, 'store'])->name('cobrancas.store')->middleware('ancora.route:cobrancas.store');
        Route::get('/{cobranca}', [CobrancaController::class, 'show'])->name('cobrancas.show')->middleware('ancora.route:cobrancas.show');
        Route::get('/{cobranca}/editar', [CobrancaController::class, 'edit'])->name('cobrancas.edit')->middleware('ancora.route:cobrancas.edit');
        Route::put('/{cobranca}', [CobrancaController::class, 'update'])->name('cobrancas.update')->middleware('ancora.route:cobrancas.update');
        Route::delete('/{cobranca}', [CobrancaController::class, 'destroy'])->name('cobrancas.delete')->middleware('ancora.route:cobrancas.delete');
        Route::post('/{cobranca}/andamentos', [CobrancaController::class, 'addTimeline'])->name('cobrancas.timeline.store')->middleware('ancora.route:cobrancas.timeline.store');
        Route::post('/{cobranca}/anexos/upload', [CobrancaController::class, 'uploadAttachment'])->name('cobrancas.attachments.upload')->middleware('ancora.route:cobrancas.attachments.upload');
        Route::get('/{cobranca}/anexos/{attachment}/download', [CobrancaController::class, 'downloadAttachment'])->name('cobrancas.attachments.download')->middleware('ancora.route:cobrancas.attachments.download');
        Route::delete('/{cobranca}/anexos/{attachment}', [CobrancaController::class, 'deleteAttachment'])->name('cobrancas.attachments.delete')->middleware('ancora.route:cobrancas.attachments.delete');
    });

    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClientsController::class, 'index'])->name('clientes.index')->middleware('ancora.route:clientes.index');
        Route::get('/avulsos', [ClientsController::class, 'avulsos'])->name('clientes.avulsos')->middleware('ancora.route:clientes.avulsos');
        Route::get('/avulsos/novo', [ClientsController::class, 'avulsoCreate'])->name('clientes.avulsos.create')->middleware('ancora.route:clientes.avulsos.create');
        Route::post('/avulsos/store', [ClientsController::class, 'avulsoStore'])->name('clientes.avulsos.store')->middleware('ancora.route:clientes.avulsos.store');
        Route::get('/avulsos/{avulso}/editar', [ClientsController::class, 'avulsoEdit'])->name('clientes.avulsos.edit')->middleware('ancora.route:clientes.avulsos.edit');
        Route::put('/avulsos/{avulso}', [ClientsController::class, 'avulsoUpdate'])->name('clientes.avulsos.update')->middleware('ancora.route:clientes.avulsos.update');
        Route::delete('/avulsos/{avulso}', [ClientsController::class, 'avulsoDelete'])->name('clientes.avulsos.delete')->middleware('ancora.route:clientes.avulsos.delete');

        Route::get('/contatos', [ClientsController::class, 'contatos'])->name('clientes.contatos')->middleware('ancora.route:clientes.contatos');
        Route::get('/contatos/novo', [ClientsController::class, 'contatoCreate'])->name('clientes.contatos.create')->middleware('ancora.route:clientes.contatos.create');
        Route::post('/contatos/store', [ClientsController::class, 'contatoStore'])->name('clientes.contatos.store')->middleware('ancora.route:clientes.contatos.store');
        Route::get('/contatos/{contato}/editar', [ClientsController::class, 'contatoEdit'])->name('clientes.contatos.edit')->middleware('ancora.route:clientes.contatos.edit');
        Route::put('/contatos/{contato}', [ClientsController::class, 'contatoUpdate'])->name('clientes.contatos.update')->middleware('ancora.route:clientes.contatos.update');
        Route::delete('/contatos/{contato}', [ClientsController::class, 'contatoDelete'])->name('clientes.contatos.delete')->middleware('ancora.route:clientes.contatos.delete');

        Route::get('/condominios', [ClientsController::class, 'condominios'])->name('clientes.condominios')->middleware('ancora.route:clientes.condominios');
        Route::get('/condominios/novo', [ClientsController::class, 'condominioCreate'])->name('clientes.condominios.create')->middleware('ancora.route:clientes.condominios.create');
        Route::post('/condominios/store', [ClientsController::class, 'condominioStore'])->name('clientes.condominios.store')->middleware('ancora.route:clientes.condominios.store');
        Route::get('/condominios/{condominio}/editar', [ClientsController::class, 'condominioEdit'])->name('clientes.condominios.edit')->middleware('ancora.route:clientes.condominios.edit');
        Route::put('/condominios/{condominio}', [ClientsController::class, 'condominioUpdate'])->name('clientes.condominios.update')->middleware('ancora.route:clientes.condominios.update');
        Route::delete('/condominios/{condominio}', [ClientsController::class, 'condominioDelete'])->name('clientes.condominios.delete')->middleware('ancora.route:clientes.condominios.delete');

        Route::get('/unidades', [ClientsController::class, 'unidades'])->name('clientes.unidades')->middleware('ancora.route:clientes.unidades');
        Route::get('/unidades/novo', [ClientsController::class, 'unidadeCreate'])->name('clientes.unidades.create')->middleware('ancora.route:clientes.unidades.create');
        Route::post('/unidades/store', [ClientsController::class, 'unidadeStore'])->name('clientes.unidades.store')->middleware('ancora.route:clientes.unidades.store');
        Route::post('/unidades/importar', [ClientsController::class, 'unidadesImport'])->name('clientes.unidades.import')->middleware('ancora.route:clientes.unidades.store');
        Route::get('/unidades/{unidade}/editar', [ClientsController::class, 'unidadeEdit'])->name('clientes.unidades.edit')->middleware('ancora.route:clientes.unidades.edit');
        Route::put('/unidades/{unidade}', [ClientsController::class, 'unidadeUpdate'])->name('clientes.unidades.update')->middleware('ancora.route:clientes.unidades.update');
        Route::delete('/unidades/{unidade}', [ClientsController::class, 'unidadeDelete'])->name('clientes.unidades.delete')->middleware('ancora.route:clientes.unidades.delete');

        Route::get('/config', [ClientsController::class, 'config'])->name('clientes.config')->middleware('ancora.route:clientes.config');
        Route::post('/config/types/store', [ClientsController::class, 'configTypeStore'])->name('clientes.config.types.store')->middleware('ancora.route:clientes.config.types.store');

        Route::get('/anexos/{attachment}/download', [ClientsController::class, 'attachmentDownload'])->name('clientes.attachments.download')->middleware('ancora.route:clientes.attachments.download');
        Route::delete('/anexos/{attachment}', [ClientsController::class, 'attachmentDelete'])->name('clientes.attachments.delete')->middleware('ancora.route:clientes.attachments.delete');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
