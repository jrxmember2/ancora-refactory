<?php

use App\Http\Controllers\Api\Hub\HubInstanceController;
use App\Http\Controllers\Api\Hub\V1\AuthController as HubAuthController;
use App\Http\Controllers\Api\Hub\V1\ClientController as HubClientController;
use App\Http\Controllers\Api\Hub\V1\CollectionController as HubCollectionController;
use App\Http\Controllers\Api\Hub\V1\CondominiumController as HubCondominiumController;
use App\Http\Controllers\Api\Hub\V1\DashboardController as HubDashboardController;
use App\Http\Controllers\Api\Hub\V1\DemandController as HubDemandController;
use App\Http\Controllers\Api\Hub\V1\DeviceController as HubDeviceController;
use App\Http\Controllers\Api\Hub\V1\DocumentController as HubDocumentController;
use App\Http\Controllers\Api\Hub\V1\NotificationController as HubNotificationController;
use App\Http\Controllers\Api\Hub\V1\ProcessController as HubProcessController;
use App\Http\Controllers\Api\Hub\V1\UnitController as HubUnitController;
use App\Http\Controllers\Api\MobileInstanceController;
use App\Http\Controllers\Api\Mobile\V1\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\V1\CondominiumController as MobileCondominiumController;
use App\Http\Controllers\Api\Mobile\V1\DashboardController as MobileDashboardController;
use App\Http\Controllers\Api\Mobile\V1\DemandController as MobileDemandController;
use App\Http\Controllers\Api\Mobile\V1\DeviceController as MobileDeviceController;
use App\Http\Controllers\Api\Mobile\V1\LemeController as MobileLemeController;
use App\Http\Controllers\Api\Mobile\V1\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\Mobile\V1\ProcessController as MobileProcessController;
use App\Http\Controllers\AssinafyWebhookController;
use App\Http\Controllers\EvolutionWebhookController;
use App\Http\Controllers\Internal\AutomationController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')
    ->middleware('automation.internal')
    ->group(function () {
        Route::post('/automation/whatsapp/process-message', [AutomationController::class, 'processWhatsappMessage']);
    });

Route::post('/integrations/assinafy/webhook', AssinafyWebhookController::class)
    ->name('integrations.assinafy.webhook');

Route::post('/integrations/evolution/webhook/{token}/{event?}', EvolutionWebhookController::class)
    ->where('event', '.*')
    ->name('integrations.evolution.webhook');

Route::prefix('mobile')->group(function () {
    Route::get('/health', [MobileInstanceController::class, 'health'])->name('api.mobile.health');
    Route::get('/instance-info', [MobileInstanceController::class, 'instanceInfo'])->name('api.mobile.instance-info');
});

Route::prefix('hub')->group(function () {
    Route::get('/health', [HubInstanceController::class, 'health'])->name('api.hub.health');
    Route::get('/instance-info', [HubInstanceController::class, 'instanceInfo'])->name('api.hub.instance-info');
});

Route::prefix('hub/v1')->name('api.hub.v1.')->group(function () {
    Route::get('/health', [HubInstanceController::class, 'health'])->name('health');
    Route::post('/auth/login', [HubAuthController::class, 'login'])->name('auth.login');

    Route::middleware('hub.api.auth')->group(function () {
        Route::post('/auth/logout', [HubAuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/change-password', [HubAuthController::class, 'changePassword'])->name('auth.change-password');
        Route::get('/me', [HubAuthController::class, 'me'])->name('me');
        Route::put('/me', [HubAuthController::class, 'updateProfile'])->name('me.update');

        Route::post('/devices/register', [HubDeviceController::class, 'register'])->name('devices.register');
        Route::post('/devices/unregister', [HubDeviceController::class, 'unregister'])->name('devices.unregister');

        Route::get('/notifications', [HubNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [HubNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [HubNotificationController::class, 'read'])->name('notifications.read');

        Route::get('/dashboard', HubDashboardController::class)->name('dashboard');

        Route::get('/demands', [HubDemandController::class, 'index'])->name('demands.index');
        Route::get('/demands/{demand}', [HubDemandController::class, 'show'])->name('demands.show');
        Route::post('/demands/{demand}/reply', [HubDemandController::class, 'reply'])->name('demands.reply');
        Route::post('/demands/{demand}/status', [HubDemandController::class, 'updateStatus'])->name('demands.status');
        Route::post('/demands/{demand}/assign', [HubDemandController::class, 'assign'])->name('demands.assign');

        Route::get('/processes', [HubProcessController::class, 'index'])->name('processes.index');
        Route::get('/processes/{process}', [HubProcessController::class, 'show'])->name('processes.show');
        Route::get('/processes/{process}/movements', [HubProcessController::class, 'movements'])->name('processes.movements');
        Route::get('/processes/{process}/attachments', [HubProcessController::class, 'attachments'])->name('processes.attachments');

        Route::get('/collections', [HubCollectionController::class, 'index'])->name('collections.index');
        Route::get('/collections/{collection}', [HubCollectionController::class, 'show'])->name('collections.show');
        Route::get('/collections/{collection}/installments', [HubCollectionController::class, 'installments'])->name('collections.installments');
        Route::get('/collections/{collection}/timeline', [HubCollectionController::class, 'timeline'])->name('collections.timeline');
        Route::get('/collections/{collection}/attachments', [HubCollectionController::class, 'attachments'])->name('collections.attachments');

        Route::get('/clients', [HubClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/{client}', [HubClientController::class, 'show'])->name('clients.show');

        Route::get('/condominiums', [HubCondominiumController::class, 'index'])->name('condominiums.index');
        Route::get('/condominiums/{condominium}', [HubCondominiumController::class, 'show'])->name('condominiums.show');
        Route::get('/condominiums/{condominium}/units', [HubCondominiumController::class, 'units'])->name('condominiums.units');
        Route::get('/condominiums/{condominium}/documents', [HubCondominiumController::class, 'documents'])->name('condominiums.documents');

        Route::get('/units/{unit}', [HubUnitController::class, 'show'])->name('units.show');
        Route::get('/units/{unit}/documents', [HubUnitController::class, 'documents'])->name('units.documents');

        Route::get('/documents/{document}/download', [HubDocumentController::class, 'download'])->name('documents.download');
    });
});

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::get('/health', [MobileInstanceController::class, 'health'])->name('health');
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->name('auth.login');

    Route::middleware('mobile.api.auth')->group(function () {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/change-password', [MobileAuthController::class, 'changePassword'])->name('auth.change-password');
        Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
        Route::post('/me', [MobileAuthController::class, 'updateProfile'])->name('me.update');

        Route::post('/devices/register', [MobileDeviceController::class, 'register'])->name('devices.register');
        Route::post('/devices/unregister', [MobileDeviceController::class, 'unregister'])->name('devices.unregister');

        Route::get('/dashboard', MobileDashboardController::class)->name('dashboard');

        Route::get('/condominiums', [MobileCondominiumController::class, 'index'])->name('condominiums.index');
        Route::post('/context/condominium', [MobileCondominiumController::class, 'updateContext'])->name('context.condominium');

        Route::get('/processes', [MobileProcessController::class, 'index'])->name('processes.index');
        Route::get('/processes/{process}', [MobileProcessController::class, 'show'])->name('processes.show');

        Route::get('/demands', [MobileDemandController::class, 'index'])->name('demands.index');
        Route::post('/demands', [MobileDemandController::class, 'store'])->name('demands.store');
        Route::get('/demands/{demand}', [MobileDemandController::class, 'show'])->name('demands.show');
        Route::post('/demands/{demand}/reply', [MobileDemandController::class, 'reply'])->name('demands.reply');
        Route::post('/demands/{demand}/cancel', [MobileDemandController::class, 'cancel'])->name('demands.cancel');
        Route::get('/demand-categories', [MobileDemandController::class, 'categories'])->name('demand-categories.index');
        Route::get('/demands/{demand}/attachments/{attachment}/download', [MobileDemandController::class, 'downloadAttachment'])->name('demands.attachments.download');

        Route::get('/notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [MobileNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [MobileNotificationController::class, 'read'])->name('notifications.read');

        Route::get('/leme/history', [MobileLemeController::class, 'history'])->name('leme.history');
        Route::post('/leme/chat', [MobileLemeController::class, 'chat'])->name('leme.chat');
        Route::delete('/leme/history', [MobileLemeController::class, 'clearHistory'])->name('leme.history.clear');
    });
});
