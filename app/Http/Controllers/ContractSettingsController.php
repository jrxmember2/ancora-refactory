<?php

namespace App\Http\Controllers;

use App\Models\ContractSetting;
use App\Support\ContractSettings;
use App\Support\Contracts\ContractCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractSettingsController extends Controller
{
    public function index(): View
    {
        $settings = [];
        foreach (ContractSettings::defaults() as $key => $default) {
            $settings[$key] = ContractSettings::get($key, $default);
        }

        return view('pages.contratos.settings.index', [
            'title' => 'Configurações de contratos',
            'settings' => $settings,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'default_city' => ['nullable', 'string', 'max:120'],
            'default_state' => ['nullable', 'string', 'max:10'],
            'signature_text' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'show_logo' => ['nullable', 'boolean'],
            'auto_code' => ['nullable', 'boolean'],
            'code_prefix' => ['nullable', 'string', 'max:20'],
            'due_alert_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'default_status' => ['required', 'string', Rule::in(array_keys(ContractCatalog::statuses()))],
        ]);

        foreach (ContractSettings::defaults() as $key => $default) {
            $value = match ($key) {
                'show_logo', 'auto_code' => $request->boolean($key) ? '1' : '0',
                default => (string) $request->input($key, $default),
            };

            ContractSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->route('contratos.settings.index')->with('success', 'Configurações salvas com sucesso.');
    }
}
