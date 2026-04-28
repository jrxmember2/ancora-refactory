<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClientExportController;
use App\Http\Controllers\ClientPortalUserController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ContractCategoryController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractReportController;
use App\Http\Controllers\ContractSettingsController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\ContractVariableController;
use App\Http\Controllers\CobrancaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemandController;
use App\Http\Controllers\DocumentSignatureController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\FinancialExportController;
use App\Http\Controllers\FinancialImportController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Portal\ClientPortalAccountController;
use App\Http\Controllers\Portal\ClientPortalAuthController;
use App\Http\Controllers\Portal\ClientPortalContextController;
use App\Http\Controllers\Portal\ClientPortalCobrancaController;
use App\Http\Controllers\Portal\ClientPortalDashboardController;
use App\Http\Controllers\Portal\ClientPortalDemandController;
use App\Http\Controllers\Portal\ClientPortalProcessController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProcessNotificationController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalDocumentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::domain(config('app.client_portal_domain'))->name('portal.')->group(function () {
    Route::middleware('portal.guest')->group(function () {
        Route::get('/login', [ClientPortalAuthController::class, 'loginForm'])->name('login');
        Route::post('/login', [ClientPortalAuthController::class, 'login'])->name('login.store');
        Route::get('/esqueci-minha-senha', [ClientPortalAuthController::class, 'forgotPassword'])->name('password.request');
    });

    Route::middleware('portal.auth')->group(function () {
        Route::get('/', ClientPortalDashboardController::class)->name('dashboard');
        Route::get('/dashboard', ClientPortalDashboardController::class);
        Route::post('/contexto-condominio', [ClientPortalContextController::class, 'update'])->name('context.update');
        Route::get('/trocar-senha', [ClientPortalAuthController::class, 'passwordEdit'])->name('password.edit');
        Route::post('/trocar-senha', [ClientPortalAuthController::class, 'passwordUpdate'])->name('password.update');

        Route::get('/processos', [ClientPortalProcessController::class, 'index'])->name('processes.index');
        Route::get('/processos/{processo}', [ClientPortalProcessController::class, 'show'])->name('processes.show');

        Route::get('/cobrancas', [ClientPortalCobrancaController::class, 'index'])->name('cobrancas.index');
        Route::get('/cobrancas/{cobranca}', [ClientPortalCobrancaController::class, 'show'])->name('cobrancas.show');

        Route::get('/solicitacoes', [ClientPortalDemandController::class, 'index'])->name('demands.index');
        Route::get('/solicitacoes/nova', [ClientPortalDemandController::class, 'create'])->name('demands.create');
        Route::post('/solicitacoes', [ClientPortalDemandController::class, 'store'])->name('demands.store');
        Route::get('/solicitacoes/{demand}', [ClientPortalDemandController::class, 'show'])->name('demands.show');
        Route::post('/solicitacoes/{demand}/editar', [ClientPortalDemandController::class, 'update'])->name('demands.update');
        Route::post('/solicitacoes/{demand}/cancelar', [ClientPortalDemandController::class, 'cancel'])->name('demands.cancel');
        Route::post('/solicitacoes/{demand}/responder', [ClientPortalDemandController::class, 'reply'])->name('demands.reply');
        Route::get('/solicitacoes/{demand}/anexos/{attachment}/download', [ClientPortalDemandController::class, 'downloadAttachment'])->name('demands.attachments.download');

        Route::get('/minha-conta', [ClientPortalAccountController::class, 'edit'])->name('account');
        Route::post('/minha-conta/senha', [ClientPortalAccountController::class, 'updatePassword'])->name('account.password');
        Route::post('/logout', [ClientPortalAuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('ancora.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/esqueci-a-senha', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/esqueci-a-senha', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/resetar-senha/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset.form');
    Route::post('/resetar-senha', [PasswordResetController::class, 'reset'])->name('password.reset.update');
});

Route::middleware(['ancora.auth', 'ancora.activity', 'audit.activity'])->group(function () {
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
        Route::match(['post', 'delete'], '/{proposta}/anexos/{attachment}', [ProposalController::class, 'deleteAttachment'])->name('propostas.attachments.delete')->middleware('ancora.route:propostas.attachments.delete');
        Route::get('/{proposta}/editar', [ProposalController::class, 'edit'])->name('propostas.edit')->middleware('ancora.route:propostas.edit');
        Route::match(['post', 'put'], '/{proposta}', [ProposalController::class, 'update'])->name('propostas.update')->middleware('ancora.route:propostas.update');
        Route::match(['post', 'delete'], '/{proposta}/excluir', [ProposalController::class, 'destroy'])->name('propostas.delete')->middleware('ancora.route:propostas.delete');
        Route::get('/{proposta}/documento', [ProposalDocumentController::class, 'edit'])->name('propostas.document.edit')->middleware('ancora.route:propostas.document.edit');
        Route::post('/{proposta}/documento/save', [ProposalDocumentController::class, 'save'])->name('propostas.document.save')->middleware('ancora.route:propostas.document.save');
        Route::get('/{proposta}/documento/preview', [ProposalDocumentController::class, 'preview'])->name('propostas.document.preview')->middleware('ancora.route:propostas.document.preview');
        Route::get('/{proposta}/documento/pdf', [ProposalDocumentController::class, 'print'])->name('propostas.document.pdf')->middleware('ancora.route:propostas.document.pdf');
    });

    Route::get('/busca', [SearchController::class, 'index'])->name('busca')->middleware('ancora.route:busca.index');

    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index')->middleware('ancora.route:changelog.index');

    Route::get('/meus-dados', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/meus-dados', [UserProfileController::class, 'update'])->name('profile.update');
    Route::post('/meus-dados/tema', [UserProfileController::class, 'updateTheme'])->name('profile.theme');

    Route::get('/config', [ConfigController::class, 'index'])->name('config.index')->middleware(['ancora.superadmin', 'ancora.route:config.index']);
    Route::get('/config/automacao/documentacao', [ConfigController::class, 'automationDocumentation'])->name('config.automation.documentation')->middleware(['ancora.superadmin', 'ancora.route:config.automation.documentation']);
    Route::post('/config/automacao/save', [ConfigController::class, 'saveAutomation'])->name('config.automation.save')->middleware(['ancora.superadmin', 'ancora.route:config.automation.save']);
    Route::post('/config/branding/save', [ConfigController::class, 'saveBranding'])->name('config.branding.save')->middleware(['ancora.superadmin', 'ancora.route:config.branding.save']);
    Route::post('/config/favicon/save', [ConfigController::class, 'saveFavicon'])->name('config.favicon.save')->middleware(['ancora.superadmin', 'ancora.route:config.favicon.save']);
    Route::post('/config/modules/save', [ConfigController::class, 'saveModules'])->name('config.modules.save')->middleware(['ancora.superadmin', 'ancora.route:config.modules.save']);
    Route::post('/config/alerta-global/save', [ConfigController::class, 'saveSystemAlert'])->name('config.system-alert.save')->middleware(['ancora.superadmin', 'ancora.route:config.system-alert.save']);
    Route::post('/config/smtp/save', [ConfigController::class, 'saveSmtp'])->name('config.smtp.save')->middleware(['ancora.superadmin', 'ancora.route:config.smtp.save']);
    Route::post('/config/smtp/test', [ConfigController::class, 'testSmtp'])->name('config.smtp.test')->middleware(['ancora.superadmin']);
    Route::post('/config/cobranca/smtp/save', [ConfigController::class, 'saveBillingSmtp'])->name('config.billing-smtp.save')->middleware(['ancora.superadmin', 'ancora.route:config.billing-smtp.save']);
    Route::post('/config/cobranca/smtp/test', [ConfigController::class, 'testBillingSmtp'])->name('config.billing-smtp.test')->middleware(['ancora.superadmin']);
    Route::post('/config/cobranca/imap/save', [ConfigController::class, 'saveBillingImap'])->name('config.billing-imap.save')->middleware(['ancora.superadmin', 'ancora.route:config.billing-imap.save']);
    Route::post('/config/access-profiles/save', [ConfigController::class, 'saveAccessProfiles'])->name('config.access-profiles.save')->middleware(['ancora.superadmin', 'ancora.route:config.access-profiles.save']);
    Route::match(['post', 'delete'], '/config/access-profiles/{slug}', [ConfigController::class, 'deleteAccessProfile'])->name('config.access-profiles.delete')->middleware(['ancora.superadmin', 'ancora.route:config.access-profiles.delete']);

    Route::post('/config/tjes-indices/store', [ConfigController::class, 'storeTjesIndexFactor'])->name('config.tjes-factors.store')->middleware(['ancora.superadmin', 'ancora.route:config.tjes-factors.store']);
    Route::post('/config/demandas/tags/store', [ConfigController::class, 'storeDemandTag'])->name('config.demand-tags.store')->middleware(['ancora.superadmin', 'ancora.route:config.demand-tags.store']);
    Route::match(['post', 'put'], '/config/demandas/tags/{tag}', [ConfigController::class, 'updateDemandTag'])->name('config.demand-tags.update')->middleware(['ancora.superadmin', 'ancora.route:config.demand-tags.update']);
    Route::match(['post', 'delete'], '/config/demandas/tags/{tag}/excluir', [ConfigController::class, 'deleteDemandTag'])->name('config.demand-tags.delete')->middleware(['ancora.superadmin', 'ancora.route:config.demand-tags.delete']);
    Route::post('/config/servicos/store', [ConfigController::class, 'storeServico'])->name('config.servicos.store')->middleware(['ancora.superadmin', 'ancora.route:config.servicos.store']);
    Route::match(['post', 'put'], '/config/servicos/{servico}', [ConfigController::class, 'updateServico'])->name('config.servicos.update')->middleware(['ancora.superadmin', 'ancora.route:config.servicos.update']);
    Route::match(['post', 'delete'], '/config/servicos/{servico}/excluir', [ConfigController::class, 'deleteServico'])->name('config.servicos.delete')->middleware(['ancora.superadmin', 'ancora.route:config.servicos.delete']);
    Route::post('/config/status/store', [ConfigController::class, 'storeStatus'])->name('config.status.store')->middleware(['ancora.superadmin', 'ancora.route:config.status.store']);
    Route::match(['post', 'put'], '/config/status/{status}', [ConfigController::class, 'updateStatus'])->name('config.status.update')->middleware(['ancora.superadmin', 'ancora.route:config.status.update']);
    Route::match(['post', 'delete'], '/config/status/{status}/excluir', [ConfigController::class, 'deleteStatus'])->name('config.status.delete')->middleware(['ancora.superadmin', 'ancora.route:config.status.delete']);
    Route::post('/config/formas-envio/store', [ConfigController::class, 'storeFormaEnvio'])->name('config.formas.store')->middleware(['ancora.superadmin', 'ancora.route:config.formas.store']);
    Route::match(['post', 'put'], '/config/formas-envio/{forma}', [ConfigController::class, 'updateFormaEnvio'])->name('config.formas.update')->middleware(['ancora.superadmin', 'ancora.route:config.formas.update']);
    Route::match(['post', 'delete'], '/config/formas-envio/{forma}/excluir', [ConfigController::class, 'deleteFormaEnvio'])->name('config.formas.delete')->middleware(['ancora.superadmin', 'ancora.route:config.formas.delete']);
    Route::post('/config/processos/opcoes/store', [ConfigController::class, 'storeProcessOption'])->name('config.process-options.store')->middleware(['ancora.superadmin', 'ancora.route:config.process-options.store']);
    Route::match(['post', 'put'], '/config/processos/opcoes/{option}', [ConfigController::class, 'updateProcessOption'])->name('config.process-options.update')->middleware(['ancora.superadmin', 'ancora.route:config.process-options.update']);
    Route::match(['post', 'delete'], '/config/processos/opcoes/{option}/excluir', [ConfigController::class, 'deleteProcessOption'])->name('config.process-options.delete')->middleware(['ancora.superadmin', 'ancora.route:config.process-options.delete']);
    Route::post('/config/usuarios/store', [ConfigController::class, 'storeUsuario'])->name('config.users.store')->middleware(['ancora.superadmin', 'ancora.route:config.users.store']);
    Route::match(['post', 'put'], '/config/usuarios/{user}', [ConfigController::class, 'updateUsuario'])->name('config.users.update')->middleware(['ancora.superadmin', 'ancora.route:config.users.update']);
    Route::match(['post', 'delete'], '/config/usuarios/{user}/excluir', [ConfigController::class, 'deleteUsuario'])->name('config.users.delete')->middleware(['ancora.superadmin', 'ancora.route:config.users.delete']);

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index')->middleware('ancora.route:logs.index');

    Route::prefix('demandas')->group(function () {
        Route::get('/dashboard', [DemandController::class, 'dashboard'])->name('demandas.dashboard')->middleware('ancora.route:demandas.dashboard');
        Route::get('/kanban', [DemandController::class, 'kanban'])->name('demandas.kanban')->middleware('ancora.route:demandas.kanban');
        Route::get('/', [DemandController::class, 'index'])->name('demandas.index')->middleware('ancora.route:demandas.index');
        Route::get('/nova', [DemandController::class, 'create'])->name('demandas.create')->middleware('ancora.route:demandas.create');
        Route::post('/store', [DemandController::class, 'store'])->name('demandas.store')->middleware('ancora.route:demandas.store');
        Route::get('/{demanda}', [DemandController::class, 'show'])->name('demandas.show')->middleware('ancora.route:demandas.show');
        Route::match(['post', 'put'], '/{demanda}', [DemandController::class, 'update'])->name('demandas.update')->middleware('ancora.route:demandas.update');
        Route::post('/{demanda}/tag', [DemandController::class, 'updateTag'])->name('demandas.tag.update')->middleware('ancora.route:demandas.tag.update');
        Route::post('/{demanda}/responder', [DemandController::class, 'reply'])->name('demandas.reply')->middleware('ancora.route:demandas.reply');
        Route::get('/{demanda}/anexos/{attachment}/download', [DemandController::class, 'downloadAttachment'])->name('demandas.attachments.download')->middleware('ancora.route:demandas.attachments.download');
    });

    Route::prefix('processos')->group(function () {
        Route::post('/notificacoes/ciente', [ProcessNotificationController::class, 'acknowledge'])->name('processos.notifications.ack');
        Route::get('/dashboard', [ProcessController::class, 'dashboard'])->name('processos.dashboard')->middleware('ancora.route:processos.dashboard');
        Route::get('/', [ProcessController::class, 'index'])->name('processos.index')->middleware('ancora.route:processos.index');
        Route::get('/novo', [ProcessController::class, 'create'])->name('processos.create')->middleware('ancora.route:processos.create');
        Route::post('/store', [ProcessController::class, 'store'])->name('processos.store')->middleware('ancora.route:processos.store');
        Route::get('/{processo}', [ProcessController::class, 'show'])->name('processos.show')->middleware('ancora.route:processos.show');
        Route::get('/{processo}/editar', [ProcessController::class, 'edit'])->name('processos.edit')->middleware('ancora.route:processos.edit');
        Route::match(['post', 'put'], '/{processo}', [ProcessController::class, 'update'])->name('processos.update')->middleware('ancora.route:processos.update');
        Route::match(['post', 'delete'], '/{processo}/excluir', [ProcessController::class, 'destroy'])->name('processos.delete')->middleware('ancora.route:processos.delete');
        Route::post('/{processo}/fases', [ProcessController::class, 'storePhase'])->name('processos.phases.store')->middleware('ancora.route:processos.phases.store');
        Route::post('/{processo}/anexos/upload', [ProcessController::class, 'uploadAttachment'])->name('processos.attachments.upload')->middleware('ancora.route:processos.attachments.upload');
        Route::get('/{processo}/anexos/{attachment}/download', [ProcessController::class, 'downloadAttachment'])->name('processos.attachments.download')->middleware('ancora.route:processos.attachments.download');
        Route::match(['post', 'delete'], '/{processo}/anexos/{attachment}', [ProcessController::class, 'deleteAttachment'])->name('processos.attachments.delete')->middleware('ancora.route:processos.attachments.delete');
        Route::post('/{processo}/datajud/sincronizar', [ProcessController::class, 'syncDataJud'])->name('processos.datajud.sync')->middleware('ancora.route:processos.datajud.sync');
    });

    Route::prefix('contratos')->group(function () {
        Route::get('/dashboard', [ContractController::class, 'dashboard'])->name('contratos.dashboard')->middleware('ancora.route:contratos.dashboard');
        Route::get('/', [ContractController::class, 'index'])->name('contratos.index')->middleware('ancora.route:contratos.index');
        Route::get('/novo', [ContractController::class, 'create'])->name('contratos.create')->middleware('ancora.route:contratos.create');
        Route::post('/store', [ContractController::class, 'store'])->name('contratos.store')->middleware('ancora.route:contratos.store');
        Route::post('/preview-template', [ContractController::class, 'resolvePreview'])->name('contratos.preview.resolve')->middleware('ancora.route:contratos.preview.resolve');
        Route::get('/templates', [ContractTemplateController::class, 'index'])->name('contratos.templates.index')->middleware('ancora.route:contratos.templates.index');
        Route::get('/templates/novo', [ContractTemplateController::class, 'create'])->name('contratos.templates.create')->middleware('ancora.route:contratos.templates.create');
        Route::post('/templates/store', [ContractTemplateController::class, 'store'])->name('contratos.templates.store')->middleware('ancora.route:contratos.templates.store');
        Route::get('/templates/{template}/editar', [ContractTemplateController::class, 'edit'])->name('contratos.templates.edit')->middleware('ancora.route:contratos.templates.edit');
        Route::match(['post', 'put'], '/templates/{template}', [ContractTemplateController::class, 'update'])->name('contratos.templates.update')->middleware('ancora.route:contratos.templates.update');
        Route::match(['post', 'delete'], '/templates/{template}/excluir', [ContractTemplateController::class, 'destroy'])->name('contratos.templates.delete')->middleware('ancora.route:contratos.templates.delete');
        Route::get('/categorias', [ContractCategoryController::class, 'index'])->name('contratos.categories.index')->middleware('ancora.route:contratos.categories.index');
        Route::post('/categorias/store', [ContractCategoryController::class, 'store'])->name('contratos.categories.store')->middleware('ancora.route:contratos.categories.store');
        Route::match(['post', 'put'], '/categorias/{category}', [ContractCategoryController::class, 'update'])->name('contratos.categories.update')->middleware('ancora.route:contratos.categories.update');
        Route::match(['post', 'delete'], '/categorias/{category}/excluir', [ContractCategoryController::class, 'destroy'])->name('contratos.categories.delete')->middleware('ancora.route:contratos.categories.delete');
        Route::get('/variaveis', [ContractVariableController::class, 'index'])->name('contratos.variables.index')->middleware('ancora.route:contratos.variables.index');
        Route::match(['post', 'put'], '/variaveis/{variable}', [ContractVariableController::class, 'update'])->name('contratos.variables.update')->middleware('ancora.route:contratos.variables.update');
        Route::get('/relatorios', [ContractReportController::class, 'index'])->name('contratos.reports.index')->middleware('ancora.route:contratos.reports.index');
        Route::get('/relatorios/export/csv', [ContractReportController::class, 'exportCsv'])->name('contratos.reports.export.csv')->middleware('ancora.route:contratos.reports.export.csv');
        Route::get('/relatorios/export/pdf', [ContractReportController::class, 'exportPdf'])->name('contratos.reports.export.pdf')->middleware('ancora.route:contratos.reports.export.pdf');
        Route::get('/configuracoes', [ContractSettingsController::class, 'index'])->name('contratos.settings.index')->middleware('ancora.route:contratos.settings.index');
        Route::post('/configuracoes/save', [ContractSettingsController::class, 'save'])->name('contratos.settings.save')->middleware('ancora.route:contratos.settings.save');
        Route::post('/configuracoes/assinaturas/webhook', [ContractSettingsController::class, 'syncWebhook'])->name('contratos.settings.signatures-webhook')->middleware('ancora.route:contratos.settings.signatures-webhook');
        Route::get('/{contrato}/assinaturas/nova', [DocumentSignatureController::class, 'createContract'])->name('contratos.signatures.create')->middleware('ancora.route:contratos.signatures.create');
        Route::post('/{contrato}/assinaturas', [DocumentSignatureController::class, 'storeContract'])->name('contratos.signatures.store')->middleware('ancora.route:contratos.signatures.store');
        Route::post('/{contrato}/assinaturas/{signature}/sincronizar', [DocumentSignatureController::class, 'syncContract'])->name('contratos.signatures.sync')->middleware('ancora.route:contratos.signatures.sync');
        Route::get('/{contrato}/assinaturas/{signature}/download/{artifact}', [DocumentSignatureController::class, 'downloadContract'])->name('contratos.signatures.download')->middleware('ancora.route:contratos.signatures.download');
        Route::get('/{contrato}', [ContractController::class, 'show'])->name('contratos.show')->middleware('ancora.route:contratos.show');
        Route::get('/{contrato}/editar', [ContractController::class, 'edit'])->name('contratos.edit')->middleware('ancora.route:contratos.edit');
        Route::match(['post', 'put'], '/{contrato}', [ContractController::class, 'update'])->name('contratos.update')->middleware('ancora.route:contratos.update');
        Route::match(['post', 'delete'], '/{contrato}/excluir', [ContractController::class, 'destroy'])->name('contratos.delete')->middleware('ancora.route:contratos.delete');
        Route::post('/{contrato}/duplicar', [ContractController::class, 'duplicate'])->name('contratos.duplicate')->middleware('ancora.route:contratos.duplicate');
        Route::post('/{contrato}/arquivar', [ContractController::class, 'archive'])->name('contratos.archive')->middleware('ancora.route:contratos.archive');
        Route::post('/{contrato}/rescindir', [ContractController::class, 'rescind'])->name('contratos.rescind')->middleware('ancora.route:contratos.rescind');
        Route::post('/{contrato}/gerar-pdf', [ContractController::class, 'generatePdf'])->name('contratos.generate-pdf')->middleware('ancora.route:contratos.generate-pdf');
        Route::get('/{contrato}/pdf', [ContractController::class, 'downloadPdf'])->name('contratos.download-pdf')->middleware('ancora.route:contratos.download-pdf');
        Route::get('/{contrato}/versoes/{version}', [ContractController::class, 'viewVersion'])->name('contratos.versions.view')->middleware('ancora.route:contratos.versions.view');
        Route::get('/{contrato}/versoes/{version}/download', [ContractController::class, 'downloadVersion'])->name('contratos.versions.download')->middleware('ancora.route:contratos.versions.download');
        Route::post('/{contrato}/anexos/upload', [ContractController::class, 'uploadAttachment'])->name('contratos.attachments.upload')->middleware('ancora.route:contratos.attachments.upload');
        Route::get('/{contrato}/anexos/{attachment}/download', [ContractController::class, 'downloadAttachment'])->name('contratos.attachments.download')->middleware('ancora.route:contratos.attachments.download');
        Route::match(['post', 'delete'], '/{contrato}/anexos/{attachment}', [ContractController::class, 'deleteAttachment'])->name('contratos.attachments.delete')->middleware('ancora.route:contratos.attachments.delete');
    });

    Route::prefix('financeiro')->group(function () {
        Route::get('/dashboard', [FinancialController::class, 'dashboard'])->name('financeiro.dashboard')->middleware('ancora.route:financeiro.dashboard');
        Route::match(['get', 'post'], '/fluxo-caixa', [FinancialController::class, 'cashFlowIndex'])->name('financeiro.cash-flow.index')->middleware('ancora.route:financeiro.cash-flow.index');
        Route::post('/fluxo-caixa/store', [FinancialController::class, 'cashFlowStore'])->name('financeiro.cash-flow.store')->middleware('ancora.route:financeiro.cash-flow.store');

        Route::get('/contas-receber', [FinancialController::class, 'receivablesIndex'])->name('financeiro.receivables.index')->middleware('ancora.route:financeiro.receivables.index');
        Route::get('/contas-receber/nova', [FinancialController::class, 'receivablesCreate'])->name('financeiro.receivables.create')->middleware('ancora.route:financeiro.receivables.create');
        Route::post('/contas-receber/store', [FinancialController::class, 'receivablesStore'])->name('financeiro.receivables.store')->middleware('ancora.route:financeiro.receivables.store');
        Route::get('/contas-receber/{receivable}', [FinancialController::class, 'receivablesShow'])->name('financeiro.receivables.show')->middleware('ancora.route:financeiro.receivables.show');
        Route::get('/contas-receber/{receivable}/editar', [FinancialController::class, 'receivablesEdit'])->name('financeiro.receivables.edit')->middleware('ancora.route:financeiro.receivables.edit');
        Route::match(['post', 'put'], '/contas-receber/{receivable}', [FinancialController::class, 'receivablesUpdate'])->name('financeiro.receivables.update')->middleware('ancora.route:financeiro.receivables.update');
        Route::match(['post', 'delete'], '/contas-receber/{receivable}/excluir', [FinancialController::class, 'receivablesDestroy'])->name('financeiro.receivables.delete')->middleware('ancora.route:financeiro.receivables.delete');
        Route::post('/contas-receber/{receivable}/duplicar', [FinancialController::class, 'receivablesDuplicate'])->name('financeiro.receivables.duplicate')->middleware('ancora.route:financeiro.receivables.duplicate');
        Route::post('/contas-receber/{receivable}/baixa', [FinancialController::class, 'receivablesSettle'])->name('financeiro.receivables.settle')->middleware('ancora.route:financeiro.receivables.settle');
        Route::post('/contas-receber/{receivable}/parcelar', [FinancialController::class, 'receivablesParcel'])->name('financeiro.receivables.parcel')->middleware('ancora.route:financeiro.receivables.parcel');
        Route::post('/contas-receber/{receivable}/renegociar', [FinancialController::class, 'receivablesRenegotiate'])->name('financeiro.receivables.renegotiate')->middleware('ancora.route:financeiro.receivables.renegotiate');
        Route::get('/contas-receber/{receivable}/recibo', [FinancialController::class, 'receivablesReceipt'])->name('financeiro.receivables.receipt')->middleware('ancora.route:financeiro.receivables.receipt');
        Route::get('/contas-receber/{receivable}/pdf', [FinancialController::class, 'receivablesPdf'])->name('financeiro.receivables.pdf')->middleware('ancora.route:financeiro.receivables.pdf');
        Route::post('/contas-receber/{receivable}/anexos/upload', [FinancialController::class, 'receivablesUploadAttachment'])->name('financeiro.receivables.attachments.upload')->middleware('ancora.route:financeiro.receivables.attachments.upload');
        Route::get('/contas-receber/{receivable}/anexos/{attachment}/download', [FinancialController::class, 'receivablesDownloadAttachment'])->name('financeiro.receivables.attachments.download')->middleware('ancora.route:financeiro.receivables.attachments.download');
        Route::match(['post', 'delete'], '/contas-receber/{receivable}/anexos/{attachment}', [FinancialController::class, 'receivablesDeleteAttachment'])->name('financeiro.receivables.attachments.delete')->middleware('ancora.route:financeiro.receivables.attachments.delete');

        Route::get('/contas-pagar', [FinancialController::class, 'payablesIndex'])->name('financeiro.payables.index')->middleware('ancora.route:financeiro.payables.index');
        Route::get('/contas-pagar/nova', [FinancialController::class, 'payablesCreate'])->name('financeiro.payables.create')->middleware('ancora.route:financeiro.payables.create');
        Route::post('/contas-pagar/store', [FinancialController::class, 'payablesStore'])->name('financeiro.payables.store')->middleware('ancora.route:financeiro.payables.store');
        Route::get('/contas-pagar/{payable}', [FinancialController::class, 'payablesShow'])->name('financeiro.payables.show')->middleware('ancora.route:financeiro.payables.show');
        Route::get('/contas-pagar/{payable}/editar', [FinancialController::class, 'payablesEdit'])->name('financeiro.payables.edit')->middleware('ancora.route:financeiro.payables.edit');
        Route::match(['post', 'put'], '/contas-pagar/{payable}', [FinancialController::class, 'payablesUpdate'])->name('financeiro.payables.update')->middleware('ancora.route:financeiro.payables.update');
        Route::match(['post', 'delete'], '/contas-pagar/{payable}/excluir', [FinancialController::class, 'payablesDestroy'])->name('financeiro.payables.delete')->middleware('ancora.route:financeiro.payables.delete');
        Route::post('/contas-pagar/{payable}/duplicar', [FinancialController::class, 'payablesDuplicate'])->name('financeiro.payables.duplicate')->middleware('ancora.route:financeiro.payables.duplicate');
        Route::post('/contas-pagar/{payable}/baixa', [FinancialController::class, 'payablesSettle'])->name('financeiro.payables.settle')->middleware('ancora.route:financeiro.payables.settle');
        Route::post('/contas-pagar/{payable}/anexos/upload', [FinancialController::class, 'payablesUploadAttachment'])->name('financeiro.payables.attachments.upload')->middleware('ancora.route:financeiro.payables.attachments.upload');
        Route::get('/contas-pagar/{payable}/anexos/{attachment}/download', [FinancialController::class, 'payablesDownloadAttachment'])->name('financeiro.payables.attachments.download')->middleware('ancora.route:financeiro.payables.attachments.download');
        Route::match(['post', 'delete'], '/contas-pagar/{payable}/anexos/{attachment}', [FinancialController::class, 'payablesDeleteAttachment'])->name('financeiro.payables.attachments.delete')->middleware('ancora.route:financeiro.payables.attachments.delete');

        Route::get('/faturamento', [FinancialController::class, 'billingIndex'])->name('financeiro.billing.index')->middleware('ancora.route:financeiro.billing.index');
        Route::post('/faturamento/contrato/{contract}', [FinancialController::class, 'billingGenerateContract'])->name('financeiro.billing.generate-contract')->middleware('ancora.route:financeiro.billing.generate-contract');

        Route::get('/bancos-contas', [FinancialController::class, 'accountsIndex'])->name('financeiro.accounts.index')->middleware('ancora.route:financeiro.accounts.index');
        Route::post('/bancos-contas/store', [FinancialController::class, 'accountsStore'])->name('financeiro.accounts.store')->middleware('ancora.route:financeiro.accounts.store');
        Route::match(['post', 'put'], '/bancos-contas/{account}', [FinancialController::class, 'accountsUpdate'])->name('financeiro.accounts.update')->middleware('ancora.route:financeiro.accounts.update');
        Route::match(['post', 'delete'], '/bancos-contas/{account}/excluir', [FinancialController::class, 'accountsDestroy'])->name('financeiro.accounts.delete')->middleware('ancora.route:financeiro.accounts.delete');

        Route::get('/conciliacao-bancaria', [FinancialController::class, 'reconciliationIndex'])->name('financeiro.reconciliation.index')->middleware('ancora.route:financeiro.reconciliation.index');
        Route::post('/conciliacao-bancaria/upload', [FinancialController::class, 'reconciliationUpload'])->name('financeiro.reconciliation.upload')->middleware('ancora.route:financeiro.reconciliation.upload');
        Route::post('/conciliacao-bancaria/{statement}/conciliar', [FinancialController::class, 'reconciliationConciliate'])->name('financeiro.reconciliation.conciliate')->middleware('ancora.route:financeiro.reconciliation.conciliate');

        Route::get('/cobrancas', [FinancialController::class, 'collectionIndex'])->name('financeiro.collection.index')->middleware('ancora.route:financeiro.collection.index');
        Route::get('/inadimplencia', [FinancialController::class, 'delinquencyIndex'])->name('financeiro.delinquency.index')->middleware('ancora.route:financeiro.delinquency.index');

        Route::get('/centros-custo', [FinancialController::class, 'costCentersIndex'])->name('financeiro.cost-centers.index')->middleware('ancora.route:financeiro.cost-centers.index');
        Route::post('/centros-custo/store', [FinancialController::class, 'costCentersStore'])->name('financeiro.cost-centers.store')->middleware('ancora.route:financeiro.cost-centers.store');
        Route::match(['post', 'put'], '/centros-custo/{costCenter}', [FinancialController::class, 'costCentersUpdate'])->name('financeiro.cost-centers.update')->middleware('ancora.route:financeiro.cost-centers.update');
        Route::match(['post', 'delete'], '/centros-custo/{costCenter}/excluir', [FinancialController::class, 'costCentersDestroy'])->name('financeiro.cost-centers.delete')->middleware('ancora.route:financeiro.cost-centers.delete');

        Route::get('/categorias-financeiras', [FinancialController::class, 'categoriesIndex'])->name('financeiro.categories.index')->middleware('ancora.route:financeiro.categories.index');
        Route::post('/categorias-financeiras/store', [FinancialController::class, 'categoriesStore'])->name('financeiro.categories.store')->middleware('ancora.route:financeiro.categories.store');
        Route::match(['post', 'put'], '/categorias-financeiras/{category}', [FinancialController::class, 'categoriesUpdate'])->name('financeiro.categories.update')->middleware('ancora.route:financeiro.categories.update');
        Route::match(['post', 'delete'], '/categorias-financeiras/{category}/excluir', [FinancialController::class, 'categoriesDestroy'])->name('financeiro.categories.delete')->middleware('ancora.route:financeiro.categories.delete');

        Route::get('/parcelamentos', [FinancialController::class, 'installmentsIndex'])->name('financeiro.installments.index')->middleware('ancora.route:financeiro.installments.index');
        Route::get('/parcelamentos/{installment}', [FinancialController::class, 'installmentsShow'])->name('financeiro.installments.show')->middleware('ancora.route:financeiro.installments.show');
        Route::post('/parcelamentos/store', [FinancialController::class, 'installmentsStore'])->name('financeiro.installments.store')->middleware('ancora.route:financeiro.installments.store');
        Route::match(['post', 'delete'], '/parcelamentos/{installment}/excluir', [FinancialController::class, 'installmentsDestroy'])->name('financeiro.installments.delete')->middleware('ancora.route:financeiro.installments.delete');

        Route::get('/reembolsos', [FinancialController::class, 'reimbursementsIndex'])->name('financeiro.reimbursements.index')->middleware('ancora.route:financeiro.reimbursements.index');
        Route::get('/reembolsos/novo', [FinancialController::class, 'reimbursementsCreate'])->name('financeiro.reimbursements.create')->middleware('ancora.route:financeiro.reimbursements.create');
        Route::post('/reembolsos/store', [FinancialController::class, 'reimbursementsStore'])->name('financeiro.reimbursements.store')->middleware('ancora.route:financeiro.reimbursements.store');
        Route::get('/reembolsos/{reimbursement}/editar', [FinancialController::class, 'reimbursementsEdit'])->name('financeiro.reimbursements.edit')->middleware('ancora.route:financeiro.reimbursements.edit');
        Route::match(['post', 'put'], '/reembolsos/{reimbursement}', [FinancialController::class, 'reimbursementsUpdate'])->name('financeiro.reimbursements.update')->middleware('ancora.route:financeiro.reimbursements.update');
        Route::match(['post', 'delete'], '/reembolsos/{reimbursement}/excluir', [FinancialController::class, 'reimbursementsDestroy'])->name('financeiro.reimbursements.delete')->middleware('ancora.route:financeiro.reimbursements.delete');

        Route::get('/custas-processuais', [FinancialController::class, 'processCostsIndex'])->name('financeiro.process-costs.index')->middleware('ancora.route:financeiro.process-costs.index');
        Route::get('/custas-processuais/nova', [FinancialController::class, 'processCostsCreate'])->name('financeiro.process-costs.create')->middleware('ancora.route:financeiro.process-costs.create');
        Route::post('/custas-processuais/store', [FinancialController::class, 'processCostsStore'])->name('financeiro.process-costs.store')->middleware('ancora.route:financeiro.process-costs.store');
        Route::get('/custas-processuais/{processCost}/editar', [FinancialController::class, 'processCostsEdit'])->name('financeiro.process-costs.edit')->middleware('ancora.route:financeiro.process-costs.edit');
        Route::match(['post', 'put'], '/custas-processuais/{processCost}', [FinancialController::class, 'processCostsUpdate'])->name('financeiro.process-costs.update')->middleware('ancora.route:financeiro.process-costs.update');
        Route::match(['post', 'delete'], '/custas-processuais/{processCost}/excluir', [FinancialController::class, 'processCostsDestroy'])->name('financeiro.process-costs.delete')->middleware('ancora.route:financeiro.process-costs.delete');

        Route::get('/prestacao-contas', [FinancialController::class, 'accountabilityIndex'])->name('financeiro.accountability.index')->middleware('ancora.route:financeiro.accountability.index');
        Route::get('/prestacao-contas/pdf', [FinancialController::class, 'accountabilityPdf'])->name('financeiro.accountability.pdf')->middleware('ancora.route:financeiro.accountability.pdf');
        Route::get('/dre', [FinancialController::class, 'dreIndex'])->name('financeiro.dre.index')->middleware('ancora.route:financeiro.dre.index');
        Route::get('/dre/pdf', [FinancialController::class, 'drePdf'])->name('financeiro.dre.pdf')->middleware('ancora.route:financeiro.dre.pdf');
        Route::get('/relatorios', [FinancialController::class, 'reportsIndex'])->name('financeiro.reports.index')->middleware('ancora.route:financeiro.reports.index');

        Route::get('/exportar/{scope}/{format}', [FinancialExportController::class, 'export'])->name('financeiro.export')->middleware('ancora.route:financeiro.export');
        Route::get('/importacao/{scope}/modelo', [FinancialImportController::class, 'template'])->name('financeiro.import.template')->middleware('ancora.route:financeiro.import.template');
        Route::post('/importacao/{scope}/preview', [FinancialImportController::class, 'preview'])->name('financeiro.import.preview')->middleware('ancora.route:financeiro.import.preview');
        Route::get('/importacao/lote/{import}', [FinancialImportController::class, 'show'])->name('financeiro.import.show')->middleware('ancora.route:financeiro.import.show');
        Route::post('/importacao/lote/{import}/processar', [FinancialImportController::class, 'process'])->name('financeiro.import.process')->middleware('ancora.route:financeiro.import.process');

        Route::get('/configuracoes', [FinancialController::class, 'settingsIndex'])->name('financeiro.settings.index')->middleware('ancora.route:financeiro.settings.index');
        Route::post('/configuracoes/save', [FinancialController::class, 'settingsSave'])->name('financeiro.settings.save')->middleware('ancora.route:financeiro.settings.save');
    });

    Route::prefix('cobrancas')->group(function () {
        Route::get('/dashboard', [CobrancaController::class, 'dashboard'])->name('cobrancas.dashboard')->middleware('ancora.route:cobrancas.dashboard');
        Route::get('/', [CobrancaController::class, 'index'])->name('cobrancas.index')->middleware('ancora.route:cobrancas.index');
        Route::get('/nova', [CobrancaController::class, 'create'])->name('cobrancas.create')->middleware('ancora.route:cobrancas.create');
        Route::post('/store', [CobrancaController::class, 'store'])->name('cobrancas.store')->middleware('ancora.route:cobrancas.store');
        Route::get('/importacao', [CobrancaController::class, 'importIndex'])->name('cobrancas.import.index')->middleware('ancora.route:cobrancas.import.index');
        Route::post('/importacao/preview', [CobrancaController::class, 'importPreview'])->name('cobrancas.import.preview')->middleware('ancora.route:cobrancas.import.preview');
        Route::get('/importacao/modelo', [CobrancaController::class, 'downloadImportTemplate'])->name('cobrancas.import.template')->middleware('ancora.route:cobrancas.import.index');
        Route::get('/importacao/{batch}', [CobrancaController::class, 'importShow'])->name('cobrancas.import.show')->middleware('ancora.route:cobrancas.import.show');
        Route::post('/importacao/{batch}/processar', [CobrancaController::class, 'importProcess'])->name('cobrancas.import.process')->middleware('ancora.route:cobrancas.import.process');
        Route::get('/faturamento', [CobrancaController::class, 'billingReport'])->name('cobrancas.billing.report')->middleware('ancora.route:cobrancas.billing.report');
        Route::get('/faturamento/pdf', [CobrancaController::class, 'billingReportPdf'])->name('cobrancas.billing.report.pdf')->middleware('ancora.route:cobrancas.billing.report.pdf');
        Route::get('/{cobranca}/termo-acordo', [CobrancaController::class, 'agreementEdit'])->name('cobrancas.agreement.edit')->middleware('ancora.route:cobrancas.agreement.edit');
        Route::post('/{cobranca}/termo-acordo', [CobrancaController::class, 'agreementSave'])->name('cobrancas.agreement.save')->middleware('ancora.route:cobrancas.agreement.save');
        Route::get('/{cobranca}/termo-acordo/pdf', [CobrancaController::class, 'agreementPrint'])->name('cobrancas.agreement.pdf')->middleware('ancora.route:cobrancas.agreement.pdf');
        Route::get('/{cobranca}/assinaturas/nova', [DocumentSignatureController::class, 'createCobranca'])->name('cobrancas.signatures.create')->middleware('ancora.route:cobrancas.signatures.create');
        Route::post('/{cobranca}/assinaturas', [DocumentSignatureController::class, 'storeCobranca'])->name('cobrancas.signatures.store')->middleware('ancora.route:cobrancas.signatures.store');
        Route::post('/{cobranca}/assinaturas/{signature}/sincronizar', [DocumentSignatureController::class, 'syncCobranca'])->name('cobrancas.signatures.sync')->middleware('ancora.route:cobrancas.signatures.sync');
        Route::get('/{cobranca}/assinaturas/{signature}/download/{artifact}', [DocumentSignatureController::class, 'downloadCobranca'])->name('cobrancas.signatures.download')->middleware('ancora.route:cobrancas.signatures.download');
        Route::post('/{cobranca}/atualizacao-monetaria/preview', [CobrancaController::class, 'monetaryPreview'])->name('cobrancas.monetary.preview')->middleware('ancora.route:cobrancas.monetary.preview');
        Route::post('/{cobranca}/atualizacao-monetaria', [CobrancaController::class, 'monetaryStore'])->name('cobrancas.monetary.store')->middleware('ancora.route:cobrancas.monetary.store');
        Route::post('/{cobranca}/atualizacao-monetaria/{update}/aplicar', [CobrancaController::class, 'monetaryApply'])->name('cobrancas.monetary.apply')->middleware('ancora.route:cobrancas.monetary.apply');
        Route::get('/{cobranca}/atualizacao-monetaria/{update}/pdf', [CobrancaController::class, 'monetaryPdf'])->name('cobrancas.monetary.pdf')->middleware('ancora.route:cobrancas.monetary.pdf');
        Route::get('/{cobranca}', [CobrancaController::class, 'show'])->name('cobrancas.show')->middleware('ancora.route:cobrancas.show');
        Route::get('/{cobranca}/editar', [CobrancaController::class, 'edit'])->name('cobrancas.edit')->middleware('ancora.route:cobrancas.edit');
        Route::match(['post', 'put'], '/{cobranca}', [CobrancaController::class, 'update'])->name('cobrancas.update')->middleware('ancora.route:cobrancas.update');
        Route::match(['post', 'delete'], '/{cobranca}/excluir', [CobrancaController::class, 'destroy'])->name('cobrancas.delete')->middleware('ancora.route:cobrancas.delete');
        Route::post('/{cobranca}/solicitar-boleto', [CobrancaController::class, 'requestBoleto'])->name('cobrancas.boleto.request')->middleware('ancora.route:cobrancas.boleto.request');
        Route::get('/{cobranca}/emails/{history}', [CobrancaController::class, 'showEmailHistory'])->name('cobrancas.email-history.show')->middleware('ancora.route:cobrancas.email-history.show');
        Route::get('/{cobranca}/emails/{history}/anexo', [CobrancaController::class, 'downloadEmailHistoryAttachment'])->name('cobrancas.email-history.download')->middleware('ancora.route:cobrancas.email-history.download');
        Route::post('/{cobranca}/andamentos', [CobrancaController::class, 'addTimeline'])->name('cobrancas.timeline.store')->middleware('ancora.route:cobrancas.timeline.store');
        Route::post('/{cobranca}/anexos/upload', [CobrancaController::class, 'uploadAttachment'])->name('cobrancas.attachments.upload')->middleware('ancora.route:cobrancas.attachments.upload');
        Route::get('/{cobranca}/anexos/{attachment}/download', [CobrancaController::class, 'downloadAttachment'])->name('cobrancas.attachments.download')->middleware('ancora.route:cobrancas.attachments.download');
        Route::match(['post', 'delete'], '/{cobranca}/anexos/{attachment}', [CobrancaController::class, 'deleteAttachment'])->name('cobrancas.attachments.delete')->middleware('ancora.route:cobrancas.attachments.delete');
    });

    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClientsController::class, 'index'])->name('clientes.index')->middleware('ancora.route:clientes.index');
        Route::get('/exportar/{scope}/{format}', [ClientExportController::class, 'export'])->name('clientes.export')->middleware('ancora.route:clientes.export');
        Route::get('/avulsos', [ClientsController::class, 'avulsos'])->name('clientes.avulsos')->middleware('ancora.route:clientes.avulsos');
        Route::get('/avulsos/novo', [ClientsController::class, 'avulsoCreate'])->name('clientes.avulsos.create')->middleware('ancora.route:clientes.avulsos.create');
        Route::post('/avulsos/store', [ClientsController::class, 'avulsoStore'])->name('clientes.avulsos.store')->middleware('ancora.route:clientes.avulsos.store');
        Route::get('/avulsos/{avulso}/editar', [ClientsController::class, 'avulsoEdit'])->name('clientes.avulsos.edit')->middleware('ancora.route:clientes.avulsos.edit');
        Route::match(['post', 'put'], '/avulsos/{avulso}', [ClientsController::class, 'avulsoUpdate'])->name('clientes.avulsos.update')->middleware('ancora.route:clientes.avulsos.update');
        Route::match(['post', 'delete'], '/avulsos/{avulso}/excluir', [ClientsController::class, 'avulsoDelete'])->name('clientes.avulsos.delete')->middleware('ancora.route:clientes.avulsos.delete');

        Route::get('/contatos', [ClientsController::class, 'contatos'])->name('clientes.contatos')->middleware('ancora.route:clientes.contatos');
        Route::get('/contatos/novo', [ClientsController::class, 'contatoCreate'])->name('clientes.contatos.create')->middleware('ancora.route:clientes.contatos.create');
        Route::post('/contatos/store', [ClientsController::class, 'contatoStore'])->name('clientes.contatos.store')->middleware('ancora.route:clientes.contatos.store');
        Route::get('/contatos/{contato}/editar', [ClientsController::class, 'contatoEdit'])->name('clientes.contatos.edit')->middleware('ancora.route:clientes.contatos.edit');
        Route::match(['post', 'put'], '/contatos/{contato}', [ClientsController::class, 'contatoUpdate'])->name('clientes.contatos.update')->middleware('ancora.route:clientes.contatos.update');
        Route::match(['post', 'delete'], '/contatos/{contato}/excluir', [ClientsController::class, 'contatoDelete'])->name('clientes.contatos.delete')->middleware('ancora.route:clientes.contatos.delete');

        Route::get('/condominos', [ClientsController::class, 'condominos'])->name('clientes.condominos')->middleware('ancora.route:clientes.condominos');

        Route::get('/portal/usuarios', [ClientPortalUserController::class, 'index'])->name('clientes.portal-users.index')->middleware('ancora.route:clientes.portal-users.index');
        Route::post('/portal/usuarios', [ClientPortalUserController::class, 'store'])->name('clientes.portal-users.store')->middleware('ancora.route:clientes.portal-users.store');
        Route::match(['post', 'put'], '/portal/usuarios/{portalUser}', [ClientPortalUserController::class, 'update'])->name('clientes.portal-users.update')->middleware('ancora.route:clientes.portal-users.update');
        Route::match(['post', 'delete'], '/portal/usuarios/{portalUser}/excluir', [ClientPortalUserController::class, 'destroy'])->name('clientes.portal-users.delete')->middleware('ancora.route:clientes.portal-users.delete');

        Route::get('/condominios', [ClientsController::class, 'condominios'])->name('clientes.condominios')->middleware('ancora.route:clientes.condominios');
        Route::get('/condominios/novo', [ClientsController::class, 'condominioCreate'])->name('clientes.condominios.create')->middleware('ancora.route:clientes.condominios.create');
        Route::post('/condominios/store', [ClientsController::class, 'condominioStore'])->name('clientes.condominios.store')->middleware('ancora.route:clientes.condominios.store');
        Route::get('/condominios/{condominio}/editar', [ClientsController::class, 'condominioEdit'])->name('clientes.condominios.edit')->middleware('ancora.route:clientes.condominios.edit');
        Route::match(['post', 'put'], '/condominios/{condominio}', [ClientsController::class, 'condominioUpdate'])->name('clientes.condominios.update')->middleware('ancora.route:clientes.condominios.update');
        Route::match(['post', 'delete'], '/condominios/{condominio}/excluir', [ClientsController::class, 'condominioDelete'])->name('clientes.condominios.delete')->middleware('ancora.route:clientes.condominios.delete');

        Route::get('/unidades', [ClientsController::class, 'unidades'])->name('clientes.unidades')->middleware('ancora.route:clientes.unidades');
        Route::get('/unidades/novo', [ClientsController::class, 'unidadeCreate'])->name('clientes.unidades.create')->middleware('ancora.route:clientes.unidades.create');
        Route::post('/unidades/store', [ClientsController::class, 'unidadeStore'])->name('clientes.unidades.store')->middleware('ancora.route:clientes.unidades.store');
        Route::post('/unidades/importar', [ClientsController::class, 'unidadesImportPreview'])->name('clientes.unidades.import')->middleware('ancora.route:clientes.unidades.store');
        Route::post('/unidades/importar/executar', [ClientsController::class, 'unidadesImportExecute'])->name('clientes.unidades.import.execute')->middleware('ancora.route:clientes.unidades.store');
        Route::match(['post', 'delete'], '/unidades/excluir-massa', [ClientsController::class, 'unidadesBulkDelete'])->name('clientes.unidades.bulk-delete')->middleware('ancora.route:clientes.unidades.delete');
        Route::get('/unidades/{unidade}/editar', [ClientsController::class, 'unidadeEdit'])->name('clientes.unidades.edit')->middleware('ancora.route:clientes.unidades.edit');
        Route::match(['post', 'put'], '/unidades/{unidade}', [ClientsController::class, 'unidadeUpdate'])->name('clientes.unidades.update')->middleware('ancora.route:clientes.unidades.update');
        Route::match(['post', 'delete'], '/unidades/{unidade}/excluir', [ClientsController::class, 'unidadeDelete'])->name('clientes.unidades.delete')->middleware('ancora.route:clientes.unidades.delete');

        Route::get('/config', [ClientsController::class, 'config'])->name('clientes.config')->middleware('ancora.route:clientes.config');
        Route::post('/config/types/store', [ClientsController::class, 'configTypeStore'])->name('clientes.config.types.store')->middleware('ancora.route:clientes.config.types.store');

        Route::get('/anexos/{attachment}/download', [ClientsController::class, 'attachmentDownload'])->name('clientes.attachments.download')->middleware('ancora.route:clientes.attachments.download');
        Route::match(['post', 'delete'], '/anexos/{attachment}', [ClientsController::class, 'attachmentDelete'])->name('clientes.attachments.delete')->middleware('ancora.route:clientes.attachments.delete');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
