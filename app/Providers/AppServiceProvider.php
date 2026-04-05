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
        if (app()->environment('production')) {
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
