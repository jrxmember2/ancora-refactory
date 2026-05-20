@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
    $formatDate = function ($value) {
        if (!$value) {
            return 'Ainda sem registro';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
@endphp

@section('content')
<div
    class="space-y-6"
    x-data="pushDispatchPage({
        usersUrl: @js(route('config.push.users')),
        submitUrl: @js(route('config.push.send')),
        csrfToken: @js(csrf_token()),
    })"
>
    <x-ancora.section-header title="Notificacoes Push" subtitle="Disparo administrativo de push para o app Android do Portal do Cliente, com historico, fila e tratamento de tokens invalidos." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para Configuracoes</span>
        </a>
    </div>

    @unless($fcmReady)
        <div class="rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-900/60 dark:bg-warning-500/10 dark:text-warning-200">
            <div class="font-semibold">Firebase Cloud Messaging ainda nao esta pronto neste ambiente.</div>
            <p class="mt-2">Para ativar o envio real, confira as variaveis <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">FCM_ENABLED</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">FCM_PROJECT_ID</code> e <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">FCM_SERVICE_ACCOUNT_JSON_BASE64</code>.</p>
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">FCM</div>
            <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $fcmReady ? 'Configurado' : 'Pendente' }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $fcmProjectId !== '' ? $fcmProjectId : 'Projeto nao informado' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Usuarios ativos</div>
            <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format((int) ($summary['active_portal_users'] ?? 0), 0, ',', '.') }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Base ativa do Portal do Cliente.</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Com app/token</div>
            <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format((int) ($summary['users_with_active_app'] ?? 0), 0, ',', '.') }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Usuarios com ao menos um dispositivo ativo.</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Tokens ativos</div>
            <div class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format((int) ($summary['active_device_tokens'] ?? 0), 0, ',', '.') }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ultimo disparo: {{ $formatDate($summary['last_dispatch_at'] ?? null) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Novo disparo</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">O envio entra na fila e continua em segundo plano. O historico abaixo mostra sucesso, erro e tokens invalidados.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-200">
                        Fila + historico
                    </span>
                </div>

                <form x-ref="form" method="post" action="{{ route('config.push.send') }}" class="mt-6 space-y-5" @submit.prevent="submitForm">
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Titulo</label>
                        <input name="title" x-model="form.title" class="{{ $inputClass }}" maxlength="180" placeholder="Ex.: Assembleia extraordinaria hoje as 19h">
                        <template x-if="errors.title">
                            <p class="mt-2 text-xs text-error-600" x-text="errors.title[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Texto da notificacao</label>
                        <textarea name="body" x-model="form.body" rows="5" class="{{ $textareaClass }}" maxlength="4000" placeholder="Escreva a mensagem que vai para o app Android."></textarea>
                        <template x-if="errors.body">
                            <p class="mt-2 text-xs text-error-600" x-text="errors.body[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de notificacao</label>
                        <select name="notification_type" x-model="form.notification_type" class="{{ $inputClass }}">
                            @foreach($typeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <template x-if="errors.notification_type">
                            <p class="mt-2 text-xs text-error-600" x-text="errors.notification_type[0]"></p>
                        </template>
                    </div>

                    <div>
                        <div class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Destinatarios</div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label :class="form.recipient_mode === 'global' ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200' : 'border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-200'" class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition">
                                <input type="radio" name="recipient_mode" value="global" x-model="form.recipient_mode" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                <span>
                                    <span class="block font-medium">Envio global</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Envia para quem estiver com app/token ativo no momento do disparo.</span>
                                </span>
                            </label>
                            <label :class="form.recipient_mode === 'specific' ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200' : 'border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-200'" class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition">
                                <input type="radio" name="recipient_mode" value="specific" x-model="form.recipient_mode" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                <span>
                                    <span class="block font-medium">Usuarios especificos</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Permite selecionar um ou mais usuarios do portal para o disparo.</span>
                                </span>
                            </label>
                        </div>
                        <template x-if="errors.recipient_mode">
                            <p class="mt-2 text-xs text-error-600" x-text="errors.recipient_mode[0]"></p>
                        </template>
                    </div>

                    <template x-if="form.recipient_mode === 'specific'">
                        <div class="space-y-4 rounded-2xl border border-gray-200 p-4 dark:border-gray-800" @click.away="searchOpen = false">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar usuarios</label>
                                <div class="relative">
                                    <input
                                        type="search"
                                        x-model="search"
                                        @focus="openSearch()"
                                        @input="scheduleSearch()"
                                        class="{{ $inputClass }} pr-11"
                                        placeholder="Nome, login, e-mail ou condominio"
                                    >
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-400">
                                        <i class="fa-solid" :class="searchLoading ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i>
                                    </span>
                                </div>

                                <div x-show="searchOpen" x-transition class="absolute z-30 mt-2 w-full max-w-3xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                                    <div class="max-h-80 overflow-y-auto">
                                        <template x-if="searchResults.length === 0 && !searchLoading">
                                            <div class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">Nenhum usuario encontrado para esta busca.</div>
                                        </template>

                                        <template x-for="user in searchResults" :key="user.id">
                                            <button type="button" @click="toggleUser(user)" class="flex w-full items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 text-left last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]">
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white" x-text="user.name"></span>
                                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400" x-text="user.login_key + (user.email ? ' - ' + user.email : '')"></span>
                                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400" x-text="user.condominiums_label || user.client_name"></span>
                                                </span>
                                                <span class="flex flex-col items-end gap-2">
                                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="user.active_device_count > 0 ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300'" x-text="user.active_device_count > 0 ? user.active_device_count + ' app(s)' : 'Sem app ativo'"></span>
                                                    <span class="text-xs font-medium text-brand-600 dark:text-brand-300" x-text="hasUser(user.id) ? 'Remover' : 'Selecionar'"></span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Selecionados</div>
                                <div class="flex min-h-14 flex-wrap gap-2 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                    <template x-if="selectedUsers.length === 0">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Nenhum usuario selecionado ainda.</span>
                                    </template>

                                    <template x-for="user in selectedUsers" :key="user.id">
                                        <div class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">
                                            <input type="hidden" name="selected_user_ids[]" :value="user.id">
                                            <span x-text="user.name"></span>
                                            <span class="text-xs opacity-75" x-text="user.active_device_count > 0 ? '(' + user.active_device_count + ' app)' : '(sem app ativo)'"></span>
                                            <button type="button" @click="removeUser(user.id)" class="text-brand-700 hover:text-brand-900 dark:text-brand-200 dark:hover:text-white">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                                <template x-if="errors.selected_user_ids">
                                    <p class="mt-2 text-xs text-error-600" x-text="errors.selected_user_ids[0]"></p>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="{{ $buttonClass }} inline-flex items-center gap-2" :disabled="submitting">
                            <i class="fa-solid" :class="submitting ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                            <span x-text="submitting ? 'Enfileirando disparo...' : 'Enviar push'"></span>
                        </button>
                        <p class="text-xs text-gray-500 dark:text-gray-400">O payload segue como <code>data message</code> e o app monta a notificacao localmente.</p>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Painel do disparo</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Acompanhe o processamento logo depois de enviar, sem travar a tela.</p>

                <div class="mt-5 space-y-4" x-show="currentDispatch">
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white" x-text="currentDispatch?.title || 'Disparo em andamento'"></div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="(currentDispatch?.notification_type_label || '') + ' - ' + (currentDispatch?.recipient_mode_label || '')"></div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClass(currentDispatch?.status)" x-text="currentDispatch?.status_label || ''"></span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-gray-900/50">
                                <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Destinatarios</div>
                                <div class="mt-2 text-xl font-semibold text-gray-900 dark:text-white" x-text="currentDispatch?.total_recipients || 0"></div>
                            </div>
                            <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-gray-900/50">
                                <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Sucesso</div>
                                <div class="mt-2 text-xl font-semibold text-success-700 dark:text-success-300" x-text="currentDispatch?.success_count || 0"></div>
                            </div>
                            <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-gray-900/50">
                                <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Erro</div>
                                <div class="mt-2 text-xl font-semibold text-error-700 dark:text-error-300" x-text="currentDispatch?.error_count || 0"></div>
                            </div>
                            <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-gray-900/50">
                                <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Tokens invalidados</div>
                                <div class="mt-2 text-xl font-semibold text-warning-700 dark:text-warning-300" x-text="currentDispatch?.invalid_token_count || 0"></div>
                            </div>
                        </div>

                        <div class="mt-4 text-sm text-gray-600 dark:text-gray-300" x-text="currentDispatch?.status_message || ''"></div>
                    </div>
                </div>

                <div x-show="!currentDispatch" class="mt-5 rounded-2xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Assim que um disparo for enviado, o andamento aparece aqui com total de sucessos e erros.
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Boas praticas</h3>
                <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <li>Use <strong>Envio global</strong> para comunicados amplos e <strong>Usuarios especificos</strong> quando a mensagem for segmentada.</li>
                    <li>Usuarios especificos sem app/token ativo continuam no historico, mas contam como erro no fechamento do disparo.</li>
                    <li>Notificacoes de emergencia entram no canal mais importante dentro do app Android.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Historico de disparos</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Auditoria completa do que foi enviado, por quem, quando e com qual resultado.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Disparo</th>
                        <th class="px-6 py-4">Tipo</th>
                        <th class="px-6 py-4">Modo</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Resultado</th>
                        <th class="px-6 py-4">Responsavel</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($dispatches as $dispatch)
                        @php
                            $snapshots = collect((array) $dispatch->recipient_snapshots_json);
                            $names = $snapshots->pluck('name')->filter()->values();
                            $extraNames = max(0, $names->count() - 4);
                            $visibleNames = $names->take(4)->implode(', ');
                            $statusClass = match ((string) $dispatch->status) {
                                'completed' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                                'completed_with_errors' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                                'failed' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
                                'processing' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200',
                                default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                            };
                        @endphp
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $dispatch->title }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($dispatch->body, 110) }}</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $dispatch->created_at?->format('d/m/Y H:i') ?: '-' }}</div>
                                @if((string) $dispatch->recipient_mode === 'specific' && $visibleNames !== '')
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        <strong>Usuarios:</strong> {{ $visibleNames }}@if($extraNames > 0) +{{ $extraNames }}@endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ \App\Support\Mobile\ClientPortalPushCatalog::typeLabel($dispatch->notification_type) }}</td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ \App\Support\Mobile\ClientPortalPushCatalog::recipientModeLabel($dispatch->recipient_mode) }}</td>
                            <td class="px-6 py-4 align-top">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ \App\Support\Mobile\ClientPortalPushCatalog::statusLabel($dispatch->status) }}</span>
                                @if($dispatch->finished_at)
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Fim: {{ $dispatch->finished_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700 dark:bg-gray-800 dark:text-gray-200">Total {{ number_format((int) $dispatch->total_recipients, 0, ',', '.') }}</span>
                                    <span class="rounded-full bg-success-50 px-2.5 py-1 text-success-700 dark:bg-success-500/10 dark:text-success-300">OK {{ number_format((int) $dispatch->success_count, 0, ',', '.') }}</span>
                                    <span class="rounded-full bg-error-50 px-2.5 py-1 text-error-700 dark:bg-error-500/10 dark:text-error-300">Erro {{ number_format((int) $dispatch->error_count, 0, ',', '.') }}</span>
                                    <span class="rounded-full bg-warning-50 px-2.5 py-1 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">Tokens {{ number_format((int) $dispatch->invalid_token_count, 0, ',', '.') }}</span>
                                </div>
                                @if($dispatch->failure_reason)
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($dispatch->failure_reason, 140) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                {{ $dispatch->creator?->name ?: 'Sistema' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10">
                                <x-ancora.empty-state icon="fa-solid fa-bell" title="Sem disparos ainda" subtitle="O historico passa a ser preenchido assim que o primeiro push for enviado." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            {{ $dispatches->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pushDispatchPage(config) {
    return {
        form: {
            title: '',
            body: '',
            notification_type: 'general',
            recipient_mode: 'global',
        },
        search: '',
        searchResults: [],
        selectedUsers: [],
        searchLoading: false,
        searchOpen: false,
        searchTimer: null,
        submitting: false,
        errors: {},
        currentDispatch: null,
        pollTimer: null,
        usersUrl: config.usersUrl,
        submitUrl: config.submitUrl,
        csrfToken: config.csrfToken,

        openSearch() {
            this.searchOpen = true;
            this.scheduleSearch();
        },

        scheduleSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.fetchUsers(), 220);
        },

        async fetchUsers() {
            this.searchLoading = true;
            try {
                const url = new URL(this.usersUrl, window.location.origin);
                if (this.search.trim() !== '') {
                    url.searchParams.set('q', this.search.trim());
                }

                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json();
                this.searchResults = Array.isArray(payload.items) ? payload.items : [];
            } catch (error) {
                this.searchResults = [];
            } finally {
                this.searchLoading = false;
            }
        },

        hasUser(id) {
            return this.selectedUsers.some((user) => Number(user.id) === Number(id));
        },

        toggleUser(user) {
            if (this.hasUser(user.id)) {
                this.removeUser(user.id);
                return;
            }

            this.selectedUsers.push(user);
        },

        removeUser(id) {
            this.selectedUsers = this.selectedUsers.filter((user) => Number(user.id) !== Number(id));
        },

        async submitForm() {
            this.submitting = true;
            this.errors = {};

            try {
                const response = await fetch(this.submitUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: new FormData(this.$refs.form),
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok) {
                    this.errors = payload?.errors || {};
                    const firstError = Object.values(this.errors).flat().find((value) => typeof value === 'string' && value.trim() !== '');
                    this.showToast(firstError || payload?.message || 'Revise os dados do disparo antes de continuar.', 'error');
                    return;
                }

                this.currentDispatch = payload.dispatch || null;
                const toastType = this.currentDispatch?.status === 'failed' ? 'error' : 'success';
                this.showToast(payload?.message || 'Disparo de push registrado com sucesso.', toastType);

                if (this.currentDispatch && !this.currentDispatch.is_finished) {
                    this.startPolling(this.currentDispatch.status_url);
                }
            } catch (error) {
                this.showToast('Nao foi possivel registrar o disparo agora.', 'error');
            } finally {
                this.submitting = false;
            }
        },

        startPolling(url) {
            clearInterval(this.pollTimer);
            this.pollTimer = setInterval(() => this.fetchDispatchStatus(url), 2200);
            this.fetchDispatchStatus(url);
        },

        async fetchDispatchStatus(url) {
            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload?.dispatch) {
                    return;
                }

                this.currentDispatch = payload.dispatch;
                if (this.currentDispatch.is_finished) {
                    clearInterval(this.pollTimer);
                    this.showToast('Processamento do disparo atualizado.');
                }
            } catch (error) {
                clearInterval(this.pollTimer);
            }
        },

        statusBadgeClass(status) {
            return {
                completed: 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                completed_with_errors: 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                failed: 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
                processing: 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200',
                queued: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            }[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
        },

        showToast(message, type = 'success') {
            const el = document.createElement('div');
            el.className = `fixed right-6 top-6 z-[999999] rounded-2xl px-4 py-3 text-sm font-medium shadow-theme-lg ${type === 'error' ? 'bg-error-500 text-white' : 'bg-success-500 text-white'}`;
            el.textContent = message;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 2600);
        },
    }
}
</script>
@endpush
