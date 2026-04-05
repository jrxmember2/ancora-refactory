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
        $appUrl = (string) config('app.url', '');
        $forwardedProto = strtolower((string) ($request?->header('X-Forwarded-Proto') ?? ''));
        $shouldForceHttps = app()->environment('production')
            || str_starts_with($appUrl, 'https://')
            || $forwardedProto === 'https';

        if ($shouldForceHttps) {
            URL::forceScheme('https');
        }

        View::composer('*', function ($view) {
            $request = request();
            $user = $request ? AncoraAuth::user($request) : null;
            $brand = AncoraSettings::brand();

            $view->with('ancoraBrand', $brand)
                ->with('ancoraAuthUser', $user)
                ->with('ancoraMenuGroups', AncoraMenu::sidebar($user));
        });
    }
}
