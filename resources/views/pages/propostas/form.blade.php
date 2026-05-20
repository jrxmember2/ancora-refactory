@extends('layouts.app')

@php
    $followupHistory = collect($proposal?->history ?? [])
        ->filter(fn ($item) => $item->action === 'followup')
        ->sortByDesc('created_at')
        ->map(function ($item) {
            $payload = $item->payload_json;
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }

            $payload = is_array($payload) ? $payload : [];

            return (object) [
                'summary' => $item->summary,
                'created_at' => $item->created_at,
                'user_email' => $item->user_email,
                'contact_type' => $payload['contact_type'] ?? '',
                'contact_type_label' => $payload['contact_type_label'] ?? '',
                'note' => $payload['note'] ?? '',
            ];
        })
        ->values();
@endphp

@section('content')
<x-ancora.section-header :title="$proposal ? 'Editar proposta' : 'Nova proposta'" subtitle="Formulario principal portado para a nova base Laravel." />
<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data da proposta</label>
                <input type="date" name="proposal_date" value="{{ old('proposal_date', optional($proposal?->proposal_date)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                <input type="text" name="client_name" value="{{ old('client_name', $proposal?->client_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Administradora / sindico</label>
                <select name="administradora_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($administradoras as $item)
                        <option value="{{ $item->id }}" @selected((int) old('administradora_id', $proposal?->administradora_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-2">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="flex-1">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Servico</label>
                        <select name="service_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($servicos as $item)
                                <option value="{{ $item->id }}" @selected((int) old('service_id', $proposal?->service_id) === (int) $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="inline-flex h-11 items-center gap-3 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="without_amount" value="1" data-proposal-without-amount @checked((bool) old('without_amount', $proposal?->without_amount))>
                        Sem valor
                    </label>
                </div>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor da proposta</label>
                <input type="text" name="proposal_total" value="{{ old('proposal_total', $proposal?->proposal_total) }}" inputmode="decimal" data-proposal-money data-proposal-value-input class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor fechado</label>
                <input type="text" name="closed_total" value="{{ old('closed_total', $proposal?->closed_total) }}" inputmode="decimal" data-proposal-money data-proposal-value-input class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Solicitante</label>
                <input type="text" name="requester_name" value="{{ old('requester_name', $proposal?->requester_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</label>
                <input type="text" name="requester_phone" value="{{ old('requester_phone', $proposal?->requester_phone) }}" inputmode="tel" autocomplete="tel" data-proposal-phone class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                <input type="email" name="contact_email" value="{{ old('contact_email', $proposal?->contact_email) }}" inputmode="email" autocomplete="email" spellcheck="false" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de envio</label>
                <select name="send_method_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($formasEnvio as $item)
                        <option value="{{ $item->id }}" @selected((int) old('send_method_id', $proposal?->send_method_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="response_status_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($statusRetorno as $item)
                        <option value="{{ $item->id }}" @selected((int) old('response_status_id', $proposal?->response_status_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Follow-up</label>
                <input type="date" name="followup_date" value="{{ old('followup_date', optional($proposal?->followup_date)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Validade (dias)</label>
                <input type="number" name="validity_days" value="{{ old('validity_days', $proposal?->validity_days ?? 30) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Indicacao</label>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-[180px,1fr]">
                    <label class="inline-flex h-11 items-center gap-3 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200"><input type="checkbox" name="has_referral" value="1" @checked((bool) old('has_referral', $proposal?->has_referral))> Houve indicacao</label>
                    <input type="text" name="referral_name" value="{{ old('referral_name', $proposal?->referral_name) }}" placeholder="Nome da indicacao" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
                </div>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Motivo da recusa</label>
                <textarea name="refusal_reason" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ old('refusal_reason', $proposal?->refusal_reason) }}</textarea>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes</label>
                <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ old('notes', $proposal?->notes) }}</textarea>
            </div>
        </div>
    </div>

    @if($proposal)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Andamento de follow-up</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Registre aqui como foi feito o contato comercial e o resumo da tratativa.</p>
                </div>
                <div class="rounded-xl border border-gray-200 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    {{ $followupHistory->count() }} registro(s)
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-[220px,1fr]">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de contato</label>
                    <select name="followup_contact_type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <option value="">Selecione</option>
                        <option value="whatsapp" @selected(old('followup_contact_type') === 'whatsapp')>WhatsApp</option>
                        <option value="email" @selected(old('followup_contact_type') === 'email')>E-mail</option>
                        <option value="telefone" @selected(old('followup_contact_type') === 'telefone')>Telefone</option>
                        <option value="presencial" @selected(old('followup_contact_type') === 'presencial')>Presencial</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Resumo do follow-up</label>
                    <textarea name="followup_summary" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Ex.: cliente pediu retorno na sexta, aguardando aprovacao interna.">{{ old('followup_summary') }}</textarea>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse($followupHistory as $event)
                    @php
                        $icon = match ($event->contact_type) {
                            'whatsapp' => 'fa-brands fa-whatsapp',
                            'email' => 'fa-solid fa-envelope',
                            'telefone' => 'fa-solid fa-phone',
                            'presencial' => 'fa-solid fa-handshake',
                            default => 'fa-solid fa-comment-dots',
                        };
                    @endphp
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                                    <i class="{{ $icon }}"></i>
                                    <span>{{ $event->contact_type_label ?: 'Follow-up' }}</span>
                                </div>
                                <div class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $event->note ?: $event->summary }}</div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ optional($event->created_at)->format('d/m/Y H:i') }}<br>
                                {{ $event->user_email }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Nenhum follow-up registrado ainda.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">{{ $submitLabel }}</button>
        @if($proposal)
            <a href="{{ route('propostas.show', $proposal) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
        @else
            <a href="{{ route('propostas.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    function proposalDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function proposalFormatMoney(input) {
        if (!input) {
            return;
        }

        const digits = proposalDigits(input.value);
        if (!digits) {
            input.value = '';
            return;
        }

        const amount = Number(digits) / 100;
        input.value = amount.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function proposalOnlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function proposalFormatPhone(value) {
        let digits = proposalOnlyDigits(value);

        if (digits.length >= 12 && digits.startsWith('55')) {
            digits = digits.slice(2);
        }

        digits = digits.slice(0, 11);

        if (digits.length <= 2) return digits ? `(${digits}` : '';
        if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
        if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;

        return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
    }

    const withoutAmount = document.querySelector('[data-proposal-without-amount]');
    const valueInputs = Array.from(document.querySelectorAll('[data-proposal-value-input]'));

    function syncWithoutAmount() {
        const checked = !!withoutAmount?.checked;

        valueInputs.forEach((input) => {
            input.disabled = checked;
            if (checked) {
                input.value = '';
            } else if (input.value) {
                proposalFormatMoney(input);
            }
        });
    }

    document.querySelectorAll('[data-proposal-money]').forEach((input) => {
        proposalFormatMoney(input);

        input.addEventListener('input', () => {
            proposalFormatMoney(input);
        });

        input.addEventListener('blur', () => {
            proposalFormatMoney(input);
        });
    });

    document.querySelectorAll('[data-proposal-phone]').forEach((input) => {
        input.value = proposalFormatPhone(input.value);

        input.addEventListener('input', () => {
            input.value = proposalFormatPhone(input.value);
        });

        input.addEventListener('blur', () => {
            input.value = proposalFormatPhone(input.value);
        });
    });

    withoutAmount?.addEventListener('change', syncWithoutAmount);
    syncWithoutAmount();
});
</script>
@endpush
