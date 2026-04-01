<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\SystemModule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfigController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.config', [
            'title' => 'Configurações',
            'settings' => AppSetting::query()->orderBy('setting_key')->get(),
            'users' => User::query()->orderByDesc('is_protected')->orderBy('name')->get(),
            'modules' => SystemModule::query()->orderBy('sort_order')->get(),
        ]);
    }
}
