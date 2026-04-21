<?php

use App\Http\Middleware\EnsureAncoraAuthenticated;
use App\Http\Middleware\EnsureInternalAutomationAccess;
use App\Http\Middleware\AuditUserAction;
use App\Http\Middleware\EnsureClientPortalAuthenticated;
use App\Http\Middleware\EnsureClientPortalGuest;
use App\Http\Middleware\EnsureGuest;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ancora.auth' => EnsureAncoraAuthenticated::class,
            'ancora.guest' => EnsureGuest::class,
            'ancora.superadmin' => EnsureSuperadmin::class,
            'ancora.route' => EnsureRoutePermission::class,
            'audit.activity' => AuditUserAction::class,
            'portal.auth' => EnsureClientPortalAuthenticated::class,
            'portal.guest' => EnsureClientPortalGuest::class,
            'automation.internal' => EnsureInternalAutomationAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
