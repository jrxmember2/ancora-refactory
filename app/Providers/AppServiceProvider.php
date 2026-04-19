<?php

namespace App\Providers;

use App\Support\AncoraMenu;
use App\Support\AncoraSettings;
use App\Support\AncoraAuth;
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
            $version = config('ancora_version.current', [
                'version' => 'v1.20',
                'date' => '19/04/2026',
                'label' => 'v1.20 • 19/04/2026',
            ]);

            try {
                $user = $request ? AncoraAuth::user($request) : null;
                $brand = AncoraSettings::brand();
                $menuGroups = AncoraMenu::sidebar($user);
            } catch (\Throwable) {
                // Error pages can be rendered before session or database services are available.
            }

            $view->with('ancoraBrand', $brand)
                ->with('ancoraAuthUser', $user)
                ->with('ancoraMenuGroups', $menuGroups)
                ->with('ancoraVersion', $version);
        });
    }
}
