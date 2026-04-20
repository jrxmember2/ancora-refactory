@extends('portal.layouts.app')

@php($money = fn ($value) => $value !== null && $value !== '' ? 'R$ ' . number_format((float) $value, 2, ',', '.') : 'Não informado')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <a href="{{ route('portal.cobrancas.index') }}" class="text-sm font-semibold text-[#941415]">Voltar às cobranças</a>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">{{ $case->os_number }}</h1>
        <p class="mt-2 text-sm text-gray-500">Resumo executivo da cobrança.</p>
    </div>
    <span class="w-fit rounded-full bg-[#f7f2ec] px-4 py-2 text-sm font-semibold text-[#941415]">{{ $stageLabels[$case->workflow_stage] ?? $case->workflow_stage }}</span>
</div>

<section class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-4">
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Unidade</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $case->block?->name ? $case->block->name.' · ' : '' }}{{ $case->unit?->unit_number ?? '-' }}</div>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Situação</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $situationLabels[$case->situation] ?? $case->situation }}</div>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Acordo</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $money($case->agreement_total) }}</div>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Último andamento</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $case->last_progress_at?->format('d/m/Y') ?: 'Não informado' }}</div>
    </div>
</section>

<section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-950">Quotas</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-[0.14em] text-gray-500">
                    <tr><th class="py-3 pr-4">Referência</th><th class="py-3 pr-4">Vencimento</th><th class="py-3 pr-4">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($case->quotas as $quota)
                        <tr>
                            <td class="py-3 pr-4">{{ $quota->reference_label ?: '-' }}</td>
                            <td class="py-3 pr-4">{{ $quota->due_date?->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4">{{ ucfirst(str_replace('_', ' ', $quota->status)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-gray-500">Sem quotas cadastradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-950">Parcelas / acordo</h2>
        <div class="mt-4 space-y-3">
            @forelse($case->installments as $installment)
                <div class="rounded-2xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold text-gray-950">{{ $installment->label ?: 'Parcela ' . $installment->installment_number }}</div>
                        <span class="text-sm text-gray-500">{{ $installment->due_date?->format('d/m/Y') }}</span>
                    </div>
                    <div class="mt-1 text-sm text-gray-500">{{ $money($installment->amount) }} · {{ ucfirst(str_replace('_', ' ', $installment->status)) }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Nenhuma parcela cadastrada.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
