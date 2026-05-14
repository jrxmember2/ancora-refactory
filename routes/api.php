<?php

use App\Http\Controllers\Api\MobileInstanceController;
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
