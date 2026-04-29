@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $hasApiKey = trim((string) ($settings['assinafy_api_key'] ?? '')) !== '';
    $hasAccessToken = trim((string) ($settings['assinafy_access_token'] ?? '')) !== '';
    $defaultSigners = collect(old('assinafy_default_signers', $defaultSigners ?? []))->values();
    if ($defaultSigners->isEmpty()) {
        $defaultSigners = collect([[
            'name' => '',
            'email' => '',
            'phone' => '',
            'document_number' => '',
            'role_label' => 'Testemunha',
        ]]);
    }
@endphp

@section('content')
<x-ancora.section-header title="Configuracoes do modulo" subtitle="Preferencias padrao para geracao de contratos, numeracao, alertas e assinatura digital." />

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Assinatura digital / Assinafy</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configuracao compartilhada para contratos e termos de acordo da cobranca.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="button" onclick="document.getElementById('signature-history-modal').showModal()" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Historico</button>
            <form method="post" action="{{ route('contratos.settings.signatures-webhook') }}">
                @csrf
                <button class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Sincronizar webhook</button>
            </form>
        </div>
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
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                <div class="mt-3 rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-white/[0.03]">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Variaveis disponiveis na mensagem</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($signatureMessageVariables as $variable)
                            @php
                                $token = '{' . '{' . $variable['key'] . '}' . '}';
                            @endphp
                            <span class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200" title="{{ $variable['description'] }}">{{ $token }}</span>
                        @endforeach
                    </div>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Exemplo: Olá, segue o documento referente ao <code>@{{condominio_nome}}</code> para assinatura digital.</div>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">URL do webhook</label>
                <input value="{{ route('integrations.assinafy.webhook', ['token' => $settings['assinafy_webhook_token'] ?: 'salve-primeiro'], true) }}" class="{{ $inputClass }}" readonly>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Salve as configuracoes para gerar o token e depois use o botao de sincronizacao para cadastrar o webhook na Assinafy.</div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Signatarios e testemunhas pre-cadastrados</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cadastre aqui as pessoas que voce reutiliza com frequencia nas assinaturas. Depois, nas telas de assinatura, basta inclui-las uma a uma.</p>
            </div>
            <button type="button" data-add-default-signer class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Adicionar cadastrado</button>
        </div>

        <div class="mt-5 space-y-4" data-default-signers-container>
            @foreach($defaultSigners as $index => $signer)
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800" data-default-signer-row>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div class="xl:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                            <input name="assinafy_default_signers[{{ $index }}][name]" value="{{ $signer['name'] ?? '' }}" class="{{ $inputClass }}" placeholder="Nome completo">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                            <input type="email" name="assinafy_default_signers[{{ $index }}][email]" value="{{ $signer['email'] ?? '' }}" class="{{ $inputClass }}" placeholder="assinante@exemplo.com">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</label>
                            <input name="assinafy_default_signers[{{ $index }}][phone]" value="{{ $signer['phone'] ?? '' }}" class="{{ $inputClass }}" placeholder="(00) 00000-0000" data-phone-mask>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                            <input name="assinafy_default_signers[{{ $index }}][document_number]" value="{{ $signer['document_number'] ?? '' }}" class="{{ $inputClass }}" placeholder="000.000.000-00" data-document-mask>
                        </div>
                        <div class="md:col-span-2 xl:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel no documento</label>
                            <select name="assinafy_default_signers[{{ $index }}][role_label]" class="{{ $inputClass }}">
                                <option value="">Selecione</option>
                                @foreach($signatureRoleOptions as $label)
                                    <option value="{{ $label }}" @selected(($signer['role_label'] ?? '') === $label)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="button" data-remove-default-signer class="w-full rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:text-error-300">Remover</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar configuracoes</button>
    </div>
</form>

<dialog id="signature-history-modal" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-6xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <div class="p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Historico geral de assinaturas</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Acompanhe todos os envios de assinatura digital feitos pelo sistema e abra rapidamente a OS ou o contrato de origem.</p>
            </div>
            <button type="button" onclick="document.getElementById('signature-history-modal').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
        </div>

        <div class="mt-5">
            @if(!($signatureHistoryReady ?? false))
                <div class="rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
                    Rode as migrations de assinatura digital para habilitar o historico consolidado.
                </div>
            @elseif(($signatureHistory ?? collect())->isEmpty())
                <x-ancora.empty-state icon="fa-solid fa-file-signature" title="Sem assinaturas registradas" subtitle="Assim que contratos ou OS forem enviados para assinatura, eles aparecerao aqui." />
            @else
                <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-4">Documento</th>
                                <th class="px-5 py-4">Origem</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Assinaturas</th>
                                <th class="px-5 py-4">Criado em</th>
                                <th class="px-5 py-4 text-right">Acao</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($signatureHistory as $historyItem)
                                @php
                                    $signableType = $historyItem->signable_type;
                                    $openUrl = null;
                                    $originLabel = 'Documento';
                                    if ($signableType === \App\Models\Contract::class && $historyItem->signable) {
                                        $openUrl = route('contratos.show', ['contrato' => $historyItem->signable, 'tab' => 'assinaturas']);
                                        $originLabel = 'Contrato';
                                    } elseif ($signableType === \App\Models\CobrancaCase::class && $historyItem->signable) {
                                        $openUrl = route('cobrancas.show', $historyItem->signable);
                                        $originLabel = 'OS de cobranca';
                                    }
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $historyItem->document_name }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $historyItem->creator?->name ?: 'Sistema' }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $originLabel }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ \App\Services\DocumentSignatureService::requestStatusLabels()[$historyItem->status] ?? $historyItem->status }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $historyItem->signers->where('completed', true)->count() }}/{{ $historyItem->signers->count() }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-200">{{ optional($historyItem->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="px-5 py-4 text-right">
                                        @if($openUrl)
                                            <a href="{{ $openUrl }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a>
                                        @else
                                            <span class="text-xs text-gray-400">Indisponivel</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</dialog>

<template id="default-signature-signer-template">
    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800" data-default-signer-row>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input data-field="name" class="{{ $inputClass }}" placeholder="Nome completo">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                <input type="email" data-field="email" class="{{ $inputClass }}" placeholder="assinante@exemplo.com">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</label>
                <input data-field="phone" class="{{ $inputClass }}" placeholder="(00) 00000-0000" data-phone-mask>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                <input data-field="document_number" class="{{ $inputClass }}" placeholder="000.000.000-00" data-document-mask>
            </div>
            <div class="md:col-span-2 xl:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel no documento</label>
                <select data-field="role_label" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach($signatureRoleOptions as $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" data-remove-default-signer class="w-full rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:text-error-300">Remover</button>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('[data-default-signers-container]');
    const template = document.getElementById('default-signature-signer-template');
    const addButton = document.querySelector('[data-add-default-signer]');

    const digits = (value) => String(value || '').replace(/\D/g, '');
    const formatPhone = (value) => {
        let clean = digits(value);
        if (clean.startsWith('55') && clean.length > 11) {
            clean = clean.slice(2);
        }
        if (clean.length > 11) {
            clean = clean.slice(0, 11);
        }
        if (clean.length > 10) {
            return clean.replace(/(\d{2})(\d{5})(\d{0,4})/, function (_, ddd, first, second) {
                return `(${ddd}) ${first}${second ? '-' + second : ''}`;
            });
        }
        if (clean.length > 6) {
            return clean.replace(/(\d{2})(\d{4})(\d{0,4})/, function (_, ddd, first, second) {
                return `(${ddd}) ${first}${second ? '-' + second : ''}`;
            });
        }
        if (clean.length > 2) {
            return clean.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        }
        return clean;
    };
    const formatDocument = (value) => {
        let clean = digits(value);
        if (clean.length > 14) {
            clean = clean.slice(0, 14);
        }
        if (clean.length > 11) {
            return clean.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})(\d{0,2})/, function (_, a, b, c, d, e) {
                return `${a}.${b}.${c}/${d}${e ? '-' + e : ''}`;
            });
        }
        return clean.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, function (_, a, b, c, d) {
            return `${a}.${b}.${c}${d ? '-' + d : ''}`;
        });
    };
    const bindMasks = (scope) => {
        scope.querySelectorAll('[data-phone-mask]').forEach((field) => {
            field.addEventListener('input', () => {
                field.value = formatPhone(field.value);
            });
            field.value = formatPhone(field.value);
        });
        scope.querySelectorAll('[data-document-mask]').forEach((field) => {
            field.addEventListener('input', () => {
                field.value = formatDocument(field.value);
            });
            field.value = formatDocument(field.value);
        });
    };

    if (container && template && addButton) {
        const reindex = () => {
            Array.from(container.querySelectorAll('[data-default-signer-row]')).forEach((row, index) => {
                row.querySelectorAll('[data-field]').forEach((field) => {
                    field.name = `assinafy_default_signers[${index}][${field.dataset.field}]`;
                });
            });
        };

        addButton.addEventListener('click', () => {
            const clone = template.content.firstElementChild.cloneNode(true);
            container.appendChild(clone);
            bindMasks(clone);
            reindex();
        });

        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-default-signer]');
            if (!button) {
                return;
            }

            const row = button.closest('[data-default-signer-row]');
            if (!row) {
                return;
            }

            const rows = container.querySelectorAll('[data-default-signer-row]');
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach((field) => field.value = '');
                row.querySelectorAll('select').forEach((field) => field.value = '');
                return;
            }

            row.remove();
            reindex();
        });

        bindMasks(document);
        reindex();
    }
});
</script>
@endpush
