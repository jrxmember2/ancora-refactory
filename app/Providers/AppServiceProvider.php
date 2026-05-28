<?php

namespace App\Providers;

use App\Models\Demand;
use App\Models\DemandMessage;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use App\Models\User;
use App\Observers\DemandMessageObserver;
use App\Observers\DemandObserver;
use App\Observers\ProcessCaseObserver;
use App\Observers\ProcessCasePhaseObserver;
use App\Support\AncoraAuth;
use App\Support\AncoraMenu;
use App\Support\AncoraSettings;
use App\Support\ProcessMovementNotifier;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Demand::observe(DemandObserver::class);
        DemandMessage::observe(DemandMessageObserver::class);
        ProcessCase::observe(ProcessCaseObserver::class);
        ProcessCasePhase::observe(ProcessCasePhaseObserver::class);

        $request = request();
        $forwardedProto = strtolower((string) $request?->header('x-forwarded-proto', ''));
        $appUrl = (string) config('app.url', '');

        if (app()->environment('production') || str_starts_with($appUrl, 'https://') || $forwardedProto === 'https') {
            URL::forceScheme('https');
        }

        View::composer('*', function ($view) {
            $request = request();
            $user = null;
            $brand = [
                'app_name' => config('app.name', 'Ancora'),
                'company_name' => config('app.name', 'Ancora'),
                'favicon' => '/favicon.ico',
            ];
            $menuGroups = [];
            $processMovementNotification = null;
            $onlineUsersCount = 0;
            $systemAlert = [
                'is_active' => false,
                'title' => '',
                'message' => '',
                'level' => 'warning',
                'visible_until' => null,
            ];
            $version = config('ancora_version.current', [
                'version' => 'v2.13',
                'date' => '28/05/2026',
                'label' => 'v2.13 - 28/05/2026',
            ]);

            try {
                $user = $request ? AncoraAuth::user($request) : null;
                $brand = AncoraSettings::brand();
                $menuGroups = AncoraMenu::sidebar($user);
                $systemAlert = AncoraSettings::systemAlert();
                $routePermissions = ($request && method_exists($request, 'hasSession') && $request->hasSession())
                    ? $request->session()->get('auth_user.route_permissions', [])
                    : [];
                if ($user) {
                    $onlineUsersCount = User::query()
                        ->active()
                        ->whereNotNull('last_seen_at')
                        ->where('last_seen_at', '>=', now()->subMinutes(5))
                        ->count();
                }
                $canSeeProcessNotifications = $user?->isSuperadmin() || in_array('processos.show', $routePermissions, true);
                if ($user && $request && $canSeeProcessNotifications && AncoraAuth::hasModule($request, 'processos')) {
                    $processMovementNotification = app(ProcessMovementNotifier::class)->forUser($user);
                }
            } catch (\Throwable) {
                // Error pages can be rendered before session or database services are available.
            }

            $view->with('ancoraBrand', $brand)
                ->with('ancoraAuthUser', $user)
                ->with('ancoraMenuGroups', $menuGroups)
                ->with('processMovementNotification', $processMovementNotification)
                ->with('ancoraOnlineUsersCount', $onlineUsersCount)
                ->with('globalSystemAlert', $systemAlert)
                ->with('ancoraVersion', $version);
        });
    }
}
