@extends('layouts.app')

@section('content')
@php
    $selectedBatchIds = collect(old('case_ids', []))->map(fn ($value) => (int) $value)->values()->all();
    $batchSendEmail = old('send_email', data_get($batchNotificationChannels ?? [], 'email_enabled') ? '1' : null);
    $batchSendWhatsapp = old('send_whatsapp', data_get($batchNotificationChannels ?? [], 'whatsapp_enabled') ? '1' : null);
    $batchModalShouldOpen = session('open_collection_batch_notification_modal')
        || $errors->has('case_ids')
        || $errors->has('case_ids.*')
        || $errors->has('batch_channels');
    $batchChannelsAvailable = (data_get($batchNotificationChannels ?? [], 'email_enabled') || data_get($batchNotificationChannels ?? [], 'whatsapp_enabled'));
@endphp

<x-ancora.section-header title="Cobrancas" subtitle="Lista de OS com filtros por condominio, situacao operacional e faturamento.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.dashboard') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Dashboard</a>
        <a href="{{ route('cobrancas.billing.report') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Faturamento</a>
        <a href="{{ route('cobrancas.import.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Importar inadimplencia</a>
        @if($batchChannelsAvailable)
            <button type="button" data-open-collection-batch-modal class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Notificar em lote</button>
        @else
            <span title="Configure o SMTP de cobranca ou a EvolutionAPI para liberar os disparos em lote." class="rounded-xl border border-gray-200 bg-gray-100 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-500">Notificar em lote</span>
        @endif
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova OS</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 xl:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="OS, devedor, processo, unidade..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 xl:col-span-2 dark:border-gray-700 dark:text-white">
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Condominio</option>
            @foreach($filterOptions['condominiums'] as $item)
                <option value="{{ $item->id }}" @selected((int) ($filters['condominium_id'] ?? 0) === (int) $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="charge_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Tipo</option>
            @foreach($filterOptions['chargeTypes'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['charge_type'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="workflow_stage" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Situacao da OS</option>
            @foreach($filterOptions['workflowStages'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['workflow_stage'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="billing_status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Faturamento</option>
            @foreach($filterOptions['billingStatuses'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['billing_status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
        <div class="flex flex-wrap gap-3 xl:col-span-2">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('cobrancas.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $batchNotificationEligibleCount ?? 0 }} OS apta(s) para disparo em lote nesta pagina.</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">A selecao em lote usa os templates da EvolutionAPI e envia para os contatos disponiveis da OS e do proprietario.</div>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="rounded-full border px-3 py-1 {{ data_get($batchNotificationChannels ?? [], 'email_enabled') ? 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300' : 'border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300' }}">
                E-mail {{ data_get($batchNotificationChannels ?? [], 'email_enabled') ? 'ativo' : 'indisponivel' }}
            </span>
            <span class="rounded-full border px-3 py-1 {{ data_get($batchNotificationChannels ?? [], 'whatsapp_enabled') ? 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300' : 'border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300' }}">
                WhatsApp {{ data_get($batchNotificationChannels ?? [], 'whatsapp_enabled') ? 'ativo' : 'indisponivel' }}
            </span>
        </div>
    </div>
</div>

<dialog id="collection-batch-notification-modal" class="fixed inset-0 m-auto w-[96vw] max-w-5xl overflow-hidden rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <form method="post" action="{{ route('cobrancas.notifications.batch') }}" class="flex max-h-[88vh] flex-col">
        @csrf
        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notificar inadimplencia em lote</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Revise as OS selecionadas e escolha os canais. O intervalo configurado na EvolutionAPI sera respeitado entre os envios de WhatsApp.</p>
                </div>
                <button type="button" data-close-collection-batch-modal class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
        </div>

        <div class="overflow-y-auto px-6 py-5">
            <div class="grid gap-6 xl:grid-cols-[1.1fr,1.4fr]">
                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Resumo</div>
                        <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">OS selecionadas: <strong data-collection-batch-count>0</strong></div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full border px-3 py-1 {{ data_get($batchNotificationChannels ?? [], 'email_enabled') ? 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300' : 'border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300' }}">
                                E-mail {{ data_get($batchNotificationChannels ?? [], 'email_enabled') ? 'ativo' : 'indisponivel' }}
                            </span>
                            <span class="rounded-full border px-3 py-1 {{ data_get($batchNotificationChannels ?? [], 'whatsapp_enabled') ? 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300' : 'border-gray-200 bg-gray-100 text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300' }}">
                                WhatsApp {{ data_get($batchNotificationChannels ?? [], 'whatsapp_enabled') ? 'ativo' : 'indisponivel' }}
                            </span>
                        </div>
                        @error('case_ids')
                            <div class="mt-3 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/20 dark:text-error-300">{{ $message }}</div>
                        @enderror
                        @error('batch_channels')
                            <div class="mt-3 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/20 dark:text-error-300">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Canais</div>
                        <div class="mt-4 space-y-3">
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <input type="checkbox" name="send_email" value="1" class="mt-1 rounded border-gray-300 text-brand-500" @checked((string) $batchSendEmail === '1') @disabled(!data_get($batchNotificationChannels ?? [], 'email_enabled'))>
                                <span>
                                    <span class="block font-medium">Enviar e-mail</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Usa o assunto e o corpo configurados em EvolutionAPI para inadimplencia.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <input type="checkbox" name="send_whatsapp" value="1" class="mt-1 rounded border-gray-300 text-brand-500" @checked((string) $batchSendWhatsapp === '1') @disabled(!data_get($batchNotificationChannels ?? [], 'whatsapp_enabled'))>
                                <span>
                                    <span class="block font-medium">Enviar WhatsApp</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Aplica o intervalo em ms configurado na EvolutionAPI entre cada mensagem da fila.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">OS selecionadas</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">O sistema vai disparar para os destinatarios disponiveis em cada OS selecionada.</div>
                        </div>
                        <span class="rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300"><span data-collection-batch-count>0</span> OS</span>
                    </div>
                    <div class="mt-4 space-y-3" data-collection-batch-list></div>
                    <div class="mt-4 rounded-2xl border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400" data-collection-batch-empty>Nenhuma OS selecionada ainda.</div>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 px-6 py-5 dark:border-gray-800">
            <div data-collection-batch-hidden-inputs></div>
            <div class="flex justify-end gap-3">
                <button type="button" data-close-collection-batch-modal class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</button>
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Disparar notificacoes</button>
            </div>
        </div>
    </form>
</dialog>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6">
            <x-ancora.empty-state icon="fa-solid fa-money-bill-wave" title="Nenhuma OS encontrada" subtitle="Ajuste os filtros ou cadastre uma nova cobranca." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">
                            <input type="checkbox" data-collection-batch-toggle class="h-4 w-4 rounded border-gray-300 text-brand-500" aria-label="Selecionar todas as OS aptas da pagina">
                        </th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="os" label="OS" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="condominium" label="Condominio / unidade" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="debtor" label="Devedor" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="stage" label="Situacao" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="agreement_total" label="Acordo" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        @php
                            $batchState = $batchNotificationStates[$item->id] ?? [
                                'available' => false,
                                'can_email' => false,
                                'can_whatsapp' => false,
                                'email_count' => 0,
                                'phone_count' => 0,
                                'reason' => 'Sem configuracao para disparo.',
                            ];
                            $batchReason = $batchState['reason'] ?? 'Sem configuracao para disparo.';
                            $isAvulsa = (string) ($item->case_mode ?? 'condominial') === 'avulsa';
                            $condominiumLabel = $isAvulsa ? 'Cobranca avulsa' : ($item->condominium?->name ?? 'Condominio nao vinculado');
                            $unitLabel = $isAvulsa
                                ? 'Sem vinculo condominial'
                                : trim(($item->block?->name ? $item->block->name . ' - ' : '') . 'Unidade ' . ($item->unit?->unit_number ?? '-'));
                            $quotaCountLabel = $item->quotas_count . ' ' . ($isAvulsa ? 'debito(s)' : 'quota(s)');
                        @endphp
                        <tr class="{{ $batchState['available'] ? '' : 'opacity-80' }}">
                            <td class="px-6 py-4 align-top">
                                <input
                                    type="checkbox"
                                    value="{{ $item->id }}"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500"
                                    data-collection-batch-checkbox
                                    data-os-number="{{ $item->os_number }}"
                                    data-debtor="{{ $item->debtor_name_snapshot }}"
                                    data-condominium="{{ $condominiumLabel }}"
                                    data-unit="{{ $unitLabel }}"
                                    data-email-count="{{ (int) ($batchState['email_count'] ?? 0) }}"
                                    data-phone-count="{{ (int) ($batchState['phone_count'] ?? 0) }}"
                                    data-can-email="{{ !empty($batchState['can_email']) ? '1' : '0' }}"
                                    data-can-whatsapp="{{ !empty($batchState['can_whatsapp']) ? '1' : '0' }}"
                                    data-reason="{{ $batchReason }}"
                                    @checked(in_array((int) $item->id, $selectedBatchIds, true))
                                    @disabled(!$batchState['available'])
                                    title="{{ $batchState['available'] ? 'Selecionar OS para disparo em lote' : $batchReason }}"
                                    aria-label="Selecionar OS {{ $item->os_number }}"
                                >
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $item->os_number }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($item->created_at)->format('d/m/Y') }}</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->charge_type === 'judicial' ? 'Judicial' : 'Extrajudicial' }}</div>
                                @if($isAvulsa)
                                    <div class="mt-2"><span class="rounded-full border border-warning-200 bg-warning-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-warning-800 dark:border-warning-800/70 dark:bg-warning-500/10 dark:text-warning-200">Avulsa</span></div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $condominiumLabel }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $unitLabel }}</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $quotaCountLabel }} - {{ $item->attachments_count }} anexo(s)</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->debtor_name_snapshot }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->debtor_document_snapshot ?: 'Sem documento' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-700 dark:text-gray-200">{{ $filterOptions['workflowStages'][$item->workflow_stage] ?? $item->workflow_stage }}</div>
                                @if($batchState['available'])
                                    <div class="mt-2">
                                        <span class="rounded-full border border-success-200 bg-success-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300">Apta para notificar</span>
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ (int) ($batchState['email_count'] ?? 0) }} e-mail(s) - {{ (int) ($batchState['phone_count'] ?? 0) }} WhatsApp</div>
                                    </div>
                                @else
                                    <div class="mt-2">
                                        <span class="rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">Sem disparo</span>
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $batchReason }}</div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-700 dark:text-gray-200">{{ $item->agreement_total ? 'R$ '.number_format((float) $item->agreement_total, 2, ',', '.') : 'Nao definido' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $filterOptions['billingStatuses'][$item->billing_status] ?? $item->billing_status }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('cobrancas.show', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a>
                                    <a href="{{ route('cobrancas.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <form method="post" action="{{ route('cobrancas.delete', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirm('Excluir esta OS de cobranca?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    const batchModal = document.getElementById('collection-batch-notification-modal');
    const batchOpenButton = document.querySelector('[data-open-collection-batch-modal]');
    const batchSelectAll = document.querySelector('[data-collection-batch-toggle]');
    const batchHiddenInputs = batchModal?.querySelector('[data-collection-batch-hidden-inputs]');
    const batchList = batchModal?.querySelector('[data-collection-batch-list]');
    const batchEmptyState = batchModal?.querySelector('[data-collection-batch-empty]');
    const batchCountTargets = batchModal ? Array.from(batchModal.querySelectorAll('[data-collection-batch-count]')) : [];

    const selectableBatchCheckboxes = () => Array.from(document.querySelectorAll('[data-collection-batch-checkbox]:not(:disabled)'));
    const selectedBatchCheckboxes = () => selectableBatchCheckboxes().filter((checkbox) => checkbox.checked);

    function syncBatchSelectAllState() {
        if (!batchSelectAll) {
            return;
        }

        const checkboxes = selectableBatchCheckboxes();
        const selected = selectedBatchCheckboxes();

        batchSelectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
        batchSelectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
    }

    function buildBatchCaseCard(checkbox) {
        const wrapper = document.createElement('div');
        wrapper.className = 'rounded-2xl border border-gray-200 p-4 dark:border-gray-800';

        const header = document.createElement('div');
        header.className = 'flex flex-wrap items-start justify-between gap-3';

        const titleBlock = document.createElement('div');
        const title = document.createElement('div');
        title.className = 'font-semibold text-gray-900 dark:text-white';
        title.textContent = 'OS ' + (checkbox.dataset.osNumber || checkbox.value);
        const subtitle = document.createElement('div');
        subtitle.className = 'mt-1 text-sm text-gray-500 dark:text-gray-400';
        subtitle.textContent = checkbox.dataset.condominium || '';
        const unit = document.createElement('div');
        unit.className = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
        unit.textContent = checkbox.dataset.unit || '';
        titleBlock.append(title, subtitle, unit);

        const badges = document.createElement('div');
        badges.className = 'flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-[0.16em]';

        const emailBadge = document.createElement('span');
        emailBadge.className = checkbox.dataset.canEmail === '1'
            ? 'rounded-full border border-success-200 bg-success-50 px-3 py-1 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300'
            : 'rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300';
        emailBadge.textContent = 'E-mail ' + (checkbox.dataset.emailCount || '0');

        const whatsappBadge = document.createElement('span');
        whatsappBadge.className = checkbox.dataset.canWhatsapp === '1'
            ? 'rounded-full border border-success-200 bg-success-50 px-3 py-1 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300'
            : 'rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-gray-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300';
        whatsappBadge.textContent = 'WhatsApp ' + (checkbox.dataset.phoneCount || '0');

        badges.append(emailBadge, whatsappBadge);
        header.append(titleBlock, badges);

        const debtor = document.createElement('div');
        debtor.className = 'mt-3 text-sm text-gray-700 dark:text-gray-200';
        debtor.textContent = 'Devedor: ' + (checkbox.dataset.debtor || '-');

        wrapper.append(header, debtor);

        return wrapper;
    }

    function renderBatchSelection() {
        if (!batchModal || !batchHiddenInputs || !batchList || !batchEmptyState) {
            return;
        }

        const selected = selectedBatchCheckboxes();
        batchHiddenInputs.replaceChildren();
        batchList.replaceChildren();

        batchCountTargets.forEach((target) => {
            target.textContent = String(selected.length);
        });

        if (!selected.length) {
            batchEmptyState.hidden = false;
            return;
        }

        batchEmptyState.hidden = true;

        selected.forEach((checkbox) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'case_ids[]';
            input.value = checkbox.value;
            batchHiddenInputs.appendChild(input);
            batchList.appendChild(buildBatchCaseCard(checkbox));
        });
    }

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-collection-batch-toggle]')) {
            selectableBatchCheckboxes().forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
            syncBatchSelectAllState();
            renderBatchSelection();
            return;
        }

        if (event.target.matches('[data-collection-batch-checkbox]')) {
            syncBatchSelectAllState();
            renderBatchSelection();
        }
    });

    batchOpenButton?.addEventListener('click', () => {
        const selected = selectedBatchCheckboxes();
        if (!selected.length) {
            alert('Selecione ao menos uma OS apta para o disparo em lote.');
            return;
        }

        renderBatchSelection();
        batchModal.showModal();
    });

    document.querySelectorAll('[data-close-collection-batch-modal]').forEach((button) => {
        button.addEventListener('click', () => batchModal?.close());
    });

    syncBatchSelectAllState();
    renderBatchSelection();

    if (@json($batchModalShouldOpen)) {
        renderBatchSelection();
        batchModal?.showModal();
    }
</script>
@endpush
