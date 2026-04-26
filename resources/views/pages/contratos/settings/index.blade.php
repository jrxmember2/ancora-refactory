@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('content')
<x-ancora.section-header title="Configuracoes do modulo" subtitle="Preferencias padrao para geracao de contratos, numeracao, alertas e identidade documental." />

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

    <div class="flex justify-end gap-3">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar configuracoes</button>
    </div>
</form>
@endsection
