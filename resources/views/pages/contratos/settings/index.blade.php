@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $hasApiKey = trim((string) ($settings['assinafy_api_key'] ?? '')) !== '';
    $hasAccessToken = trim((string) ($settings['assinafy_access_token'] ?? '')) !== '';
@endphp

@section('content')
<x-ancora.section-header title="Configuracoes do modulo" subtitle="Preferencias padrao para geracao de contratos, numeracao, alertas e assinatura digital." />

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Assinatura digital / Assinafy</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configuracao compartilhada para contratos e termos de acordo da cobranca.</p>
        </div>
        <form method="post" action="{{ route('contratos.settings.signatures-webhook') }}">
            @csrf
            <button class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Sincronizar webhook</button>
        </form>
    </div>
</div>

<form method="post" action="{{ route('contratos.settings.save') }}" class="space-y-6">
    @csrf
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Padroes gerais</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cidade padrao</label>
                    <input name="default_city" value="{{ $settings['default_city'] }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Estado padrao</label>
                    <input name="default_state" value="{{ $settings['default_state'] }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Prefixo do codigo</label>
                    <input name="code_prefix" value="{{ $settings['code_prefix'] }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Dias para alerta</label>
                    <input type="number" min="1" max="365" name="due_alert_days" value="{{ $settings['due_alert_days'] }}" class="{{ $inputClass }}">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status padrao</label>
                    <select name="default_status" class="{{ $inputClass }}">
                        @foreach(\App\Support\Contracts\ContractCatalog::statuses() as $key => $label)
                            <option value="{{ $key }}" @selected($settings['default_status'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Layout e assinatura</h3>
            <div class="mt-5 space-y-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Texto padrao de assinatura</label>
                    <input name="signature_text" value="{{ $settings['signature_text'] }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Rodape padrao</label>
                    <textarea name="footer_text" rows="4" class="{{ $textareaClass }}">{{ $settings['footer_text'] }}</textarea>
                </div>
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="show_logo" value="1" @checked($settings['show_logo'] === '1')>
                    Mostrar logo no contrato
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="auto_code" value="1" @checked($settings['auto_code'] === '1')>
                    Numeracao automatica
                </label>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Ambiente</label>
                <select name="assinafy_environment" class="{{ $inputClass }}">
                    <option value="production" @selected(($settings['assinafy_environment'] ?? 'production') === 'production')>Producao</option>
                    <option value="sandbox" @selected(($settings['assinafy_environment'] ?? '') === 'sandbox')>Sandbox</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Workspace / Account ID</label>
                <input name="assinafy_account_id" value="{{ $settings['assinafy_account_id'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">API key</label>
                <input type="password" name="assinafy_api_key" value="" placeholder="{{ $hasApiKey ? 'Ja configurada - preencha apenas para trocar' : 'Cole a API key aqui' }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Access token opcional</label>
                <input type="password" name="assinafy_access_token" value="" placeholder="{{ $hasAccessToken ? 'Ja configurado - preencha apenas para trocar' : 'Opcional' }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail do webhook</label>
                <input type="email" name="assinafy_webhook_email" value="{{ $settings['assinafy_webhook_email'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Token do webhook</label>
                <input value="{{ $settings['assinafy_webhook_token'] ?: 'Sera gerado ao salvar' }}" class="{{ $inputClass }}" readonly>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Mensagem padrao ao signatario</label>
                <textarea name="assinafy_default_signer_message" rows="3" class="{{ $textareaClass }}">{{ $settings['assinafy_default_signer_message'] }}</textarea>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Aceita variaveis dinamicas. Ex.: <code>{{ '{{condominio_nome}}' }}</code>, <code>{{ '{{cliente_nome}}' }}</code>, <code>{{ '{{documento_titulo}}' }}</code>, <code>{{ '{{os_numero}}' }}</code>.</div>
                @if(!empty($signatureMessageVariables))
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($signatureMessageVariables as $variable)
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" title="{{ $variable['label'] }}">
                                {{ $variable['token'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">URL do webhook</label>
                <input value="{{ route('integrations.assinafy.webhook', ['token' => $settings['assinafy_webhook_token'] ?: 'salve-primeiro'], true) }}" class="{{ $inputClass }}" readonly>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Salve as configuracoes para gerar o token e depois use o botao de sincronizacao para cadastrar o webhook na Assinafy.</div>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar configuracoes</button>
    </div>
</form>
@endsection
