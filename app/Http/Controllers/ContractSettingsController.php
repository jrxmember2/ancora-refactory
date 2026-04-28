<?php

namespace App\Http\Controllers;

use App\Models\ContractSetting;
use App\Services\AssinafyService;
use App\Services\DocumentSignatureMessageService;
use App\Support\ContractSettings;
use App\Support\Contracts\ContractCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractSettingsController extends Controller
{
    public function index(DocumentSignatureMessageService $messageService): View
    {
        $settings = [];
        foreach (ContractSettings::defaults() as $key => $default) {
            $settings[$key] = ContractSettings::get($key, $default);
        }

        return view('pages.contratos.settings.index', [
            'title' => 'Configuracoes de contratos',
            'settings' => $settings,
            'signatureMessageVariables' => $messageService->availableVariables(),
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
            'assinafy_environment' => ['nullable', 'string', Rule::in(['production', 'sandbox'])],
            'assinafy_account_id' => ['nullable', 'string', 'max:120'],
            'assinafy_api_key' => ['nullable', 'string', 'max:255'],
            'assinafy_access_token' => ['nullable', 'string', 'max:2048'],
            'assinafy_webhook_email' => ['nullable', 'email', 'max:180'],
            'assinafy_default_signer_message' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = ContractSettings::all();

        foreach (ContractSettings::defaults() as $key => $default) {
            $value = match ($key) {
                'show_logo', 'auto_code' => $request->boolean($key) ? '1' : '0',
                'assinafy_api_key', 'assinafy_access_token' => trim((string) $request->input($key, '')) !== ''
                    ? trim((string) $request->input($key, ''))
                    : (string) ($existing[$key] ?? $default),
                'assinafy_webhook_token' => trim((string) ($existing[$key] ?? '')) !== ''
                    ? trim((string) $existing[$key])
                    : Str::random(48),
                default => (string) $request->input($key, $existing[$key] ?? $default),
            };

            ContractSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->route('contratos.settings.index')->with('success', 'Configuracoes salvas com sucesso.');
    }

    public function syncWebhook(AssinafyService $assinafyService): RedirectResponse
    {
        if (!$assinafyService->isConfigured()) {
            return redirect()
                ->route('contratos.settings.index')
                ->with('error', 'Configure a Assinafy antes de sincronizar o webhook: ' . implode(', ', $assinafyService->missingConfig()) . '.');
        }

        try {
            $assinafyService->syncWebhookSubscription();
        } catch (\Throwable $e) {
            return redirect()->route('contratos.settings.index')->with('error', 'Nao foi possivel sincronizar o webhook da Assinafy: ' . $e->getMessage());
        }

        return redirect()->route('contratos.settings.index')->with('success', 'Webhook da Assinafy sincronizado com sucesso.');
    }
}
