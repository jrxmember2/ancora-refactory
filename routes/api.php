<?php

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
