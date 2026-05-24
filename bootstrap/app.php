<?php

use App\Http\Middleware\EnsureAncoraAuthenticated;
use App\Http\Middleware\EnsureInternalAutomationAccess;
use App\Http\Middleware\AuditUserAction;
use App\Http\Middleware\EnsureClientPortalAuthenticated;
use App\Http\Middleware\EnsureClientPortalGuest;
use App\Http\Middleware\EnsureHubApiAuthenticated;
use App\Http\Middleware\EnsureMobileApiAuthenticated;
use App\Http\Middleware\EnsureGuest;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\TrackAncoraSessionActivity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'ancora.activity' => TrackAncoraSessionActivity::class,
            'ancora.guest' => EnsureGuest::class,
            'ancora.superadmin' => EnsureSuperadmin::class,
            'ancora.route' => EnsureRoutePermission::class,
            'audit.activity' => AuditUserAction::class,
            'portal.auth' => EnsureClientPortalAuthenticated::class,
            'portal.guest' => EnsureClientPortalGuest::class,
            'mobile.api.auth' => EnsureMobileApiAuthenticated::class,
            'hub.api.auth' => EnsureHubApiAuthenticated::class,
            'automation.internal' => EnsureInternalAutomationAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'openai_api_key',
            'gemini_api_key',
        ]);

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (!$request->is('api/hub') && !$request->is('api/hub/*')) {
                return null;
            }

            $message = mb_strtolower($exception->getMessage(), 'UTF-8');
            $hubTables = [
                'hub_api_tokens',
                'hub_device_tokens',
                'hub_notifications',
                'hub_push_dispatches',
                'hub_app_login_logs',
            ];

            $referencesMissingHubTable = str_contains($message, 'base table')
                && collect($hubTables)->contains(
                    static fn (string $table) => str_contains($message, $table),
                );

            if (!$referencesMissingHubTable) {
                return null;
            }

            return response()->json([
                'message' => 'A instância do Âncora Hub ainda não foi configurada. Execute as migrations do Hub antes de acessar o aplicativo.',
            ], 503);
        });
    })->create();
