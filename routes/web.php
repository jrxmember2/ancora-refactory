<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('ancora.guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('ancora.auth')->group(function () {
    Route::get('/', [HubController::class, 'index'])->name('hub');
    Route::get('/hub', [HubController::class, 'index']);
    Route::get('/desktop', [HubController::class, 'index']);

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('propostas')->group(function () {
        Route::get('/dashboard', [ProposalController::class, 'dashboard'])->name('propostas.dashboard');
        Route::get('/', [ProposalController::class, 'index'])->name('propostas.index');
        Route::get('/nova', [ProposalController::class, 'create'])->name('propostas.create');
        Route::post('/store', [ProposalController::class, 'store'])->name('propostas.store');
        Route::get('/{proposta}', [ProposalController::class, 'show'])->name('propostas.show');
        Route::get('/{proposta}/editar', [ProposalController::class, 'edit'])->name('propostas.edit');
        Route::post('/{proposta}/update', [ProposalController::class, 'update'])->name('propostas.update');
        Route::post('/{proposta}/delete', [ProposalController::class, 'destroy'])->name('propostas.delete');
    });

    Route::get('/busca', [SearchController::class, 'index'])->name('busca');
    Route::get('/config', [ConfigController::class, 'index'])->name('config.index');
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClientsController::class, 'index'])->name('clientes.index');
        Route::get('/avulsos', [ClientsController::class, 'avulsos'])->name('clientes.avulsos');
        Route::get('/contatos', [ClientsController::class, 'contatos'])->name('clientes.contatos');
        Route::get('/condominios', [ClientsController::class, 'condominios'])->name('clientes.condominios');
        Route::get('/unidades', [ClientsController::class, 'unidades'])->name('clientes.unidades');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
