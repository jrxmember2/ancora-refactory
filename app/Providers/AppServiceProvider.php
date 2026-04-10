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
            $user = $request ? AncoraAuth::user($request) : null;
            $brand = AncoraSettings::brand();
            $version = config('ancora_version.current', [
                'version' => 'v11',
                'date' => '09/04/2026',
                'label' => 'v11 • 09/04/2026',
            ]);

            $view->with('ancoraBrand', $brand)
                ->with('ancoraAuthUser', $user)
                ->with('ancoraMenuGroups', AncoraMenu::sidebar($user))
                ->with('ancoraVersion', $version);
        });
    }
}
