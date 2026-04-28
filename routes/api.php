<?php

use App\Http\Controllers\AssinafyWebhookController;
use App\Http\Controllers\Internal\AutomationController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')
    ->middleware('automation.internal')
    ->group(function () {
        Route::post('/automation/whatsapp/process-message', [AutomationController::class, 'processWhatsappMessage']);
    });

Route::post('/integrations/assinafy/webhook', AssinafyWebhookController::class)
    ->name('integrations.assinafy.webhook');
