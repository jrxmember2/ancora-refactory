@extends('layouts.app')

@php
    $settings = $memory->payload_json['settings'] ?? [];
    $payloadTotals = $memory->payload_json['totals_cents'] ?? [];
    $costsCorrectedAmount = (float) ($memory->costs_corrected_amount ?? (((int) ($payloadTotals['costs_corrected_cents'] ?? 0)) / 100));
    $boletoFeeTotal = (float) ($memory->boleto_fee_total ?? (((int) ($payloadTotals['boleto_fee_cents'] ?? 0)) / 100));
    $boletoCancellationFeeTotal = (float) ($memory->boleto_cancellation_fee_total ?? (((int) ($payloadTotals['boleto_cancellation_fee_cents'] ?? 0)) / 100));
    $attorneyFeeLabel = match ($memory->attorney_fee_type) {
        'fixed' => 'Honorarios fixos',
        'none' => 'Sem honorarios',
        default => 'Honorarios percentuais',
    };
@endphp

@section('content')
<x-ancora.section-header :title="$memory->title" subtitle="Memoria de calculo TJES avulsa salva fora da OS, com historico proprio dentro do modulo de cobrancas.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.monetary.standalone.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
        <a href="{{ route('cobrancas.monetary.standalone.create') }}" class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-800 dark:border-warning-700/60 dark:bg-warning-500/10 dark:text-warning-200">Novo calculo</a>
        <a href="{{ route('cobrancas.monetary.standalone.pdf', $memory) }}" target="_blank" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">PDF</a>
        <form method="post" action="{{ route('cobrancas.monetary.standalone.delete', $memory) }}">
            @csrf
            @method('DELETE')
            <button onclick="return confirm('Excluir esta memoria avulsa?')" class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600 dark:text-error-300">Excluir</button>
        </form>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
    <x-ancora.stat-card label="Total geral" :value="'R$ '.number_format((float) $memory->grand_total, 2, ',', '.')" :hint="'Base final: '.(optional($memory->final_date)->format('d/m/Y') ?: '-')" icon="fa-solid fa-money-bill-wave" />
    <x-ancora.stat-card label="Debito atualizado" :value="'R$ '.number_format((float) $memory->debit_total, 2, ',', '.')" :hint="'Honorarios: R$ '.number_format((float) $memory->attorney_fee_amount, 2, ',', '.')" icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Itens" :value="$memory->items->count()" :hint="$memory->index_code === 'ATM' ? 'Indice do TJES' : $memory->index_code" icon="fa-solid fa-list-check" />
    <x-ancora.stat-card label="Gerado em" :value="optional($memory->created_at)->format('d/m/Y H:i') ?: '-'" :hint="$memory->generator?->email ?: 'Usuario nao identificado'" icon="fa-solid fa-clock-rotate-left" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr,0.8fr]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados do devedor</h3>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Nome</div>
                    <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $memory->debtor_name_snapshot }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">CPF/CNPJ</div>
                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $memory->debtor_document_snapshot ?: 'Nao informado' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">E-mail</div>
                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $memory->debtor_email_snapshot ?: 'Nao informado' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Telefone</div>
                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $memory->debtor_phone_snapshot ?: 'Nao informado' }}</div>
                </div>
            </div>

            @if($memory->description)
                <div class="mt-5 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                    {!! nl2br(e($memory->description)) !!}
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Itens calculados</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $memory->items->count() }} item(ns)</span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-3 pr-4">Referencia</th>
                            <th class="py-3 pr-4">Vencimento</th>
                            <th class="py-3 pr-4">Original</th>
                            <th class="py-3 pr-4">Fator</th>
                            <th class="py-3 pr-4">Corrigido</th>
                            <th class="py-3 pr-4">Juros</th>
                            <th class="py-3 pr-4">Multa</th>
                            <th class="py-3 pr-4">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($memory->items as $item)
                            <tr>
                                <td class="py-3 pr-4 text-gray-900 dark:text-white">{{ $item->reference_label ?: optional($item->due_date)->format('m/Y') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ optional($item->due_date)->format('d/m/Y') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $item->original_amount, 2, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ number_format((float) $item->correction_factor, 10, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $item->corrected_amount, 2, ',', '.') }}</td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $item->interest_amount, 2, ',', '.') }} <span class="text-xs text-gray-400">({{ number_format((float) $item->interest_percent, 2, ',', '.') }}%)</span></td>
                                <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) $item->fine_amount, 2, ',', '.') }}</td>
                                <td class="py-3 pr-4 font-semibold text-gray-900 dark:text-white">R$ {{ number_format((float) $item->total_amount, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parametros da memoria</h3>
            <div class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                <div class="flex items-center justify-between gap-3"><span>Cliente avulso</span><strong>{{ $memory->client?->display_name ?: ($memory->client?->legal_name ?: 'Nao vinculado') }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Indice</span><strong>{{ data_get($settings, 'index_label', $memory->index_code === 'ATM' ? 'Indice do TJES' : $memory->index_code) }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Juros</span><strong>{{ data_get($settings, 'interest_label', $memory->interest_type) }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Multa</span><strong>{{ number_format((float) $memory->fine_percent, 2, ',', '.') }}%</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Honorarios</span><strong>{{ $attorneyFeeLabel }}</strong></div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Composicao financeira</h3>
            <div class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                <div class="flex items-center justify-between gap-3"><span>Valor original</span><strong>R$ {{ number_format((float) $memory->original_total, 2, ',', '.') }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Principal corrigido</span><strong>R$ {{ number_format((float) $memory->corrected_total, 2, ',', '.') }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Juros</span><strong>R$ {{ number_format((float) $memory->interest_total, 2, ',', '.') }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Multa</span><strong>R$ {{ number_format((float) $memory->fine_total, 2, ',', '.') }}</strong></div>
                @if($costsCorrectedAmount > 0)
                    <div class="flex items-center justify-between gap-3"><span>Custas corrigidas</span><strong>R$ {{ number_format($costsCorrectedAmount, 2, ',', '.') }}</strong></div>
                @endif
                @if($boletoFeeTotal > 0)
                    <div class="flex items-center justify-between gap-3"><span>Taxa de boleto</span><strong>R$ {{ number_format($boletoFeeTotal, 2, ',', '.') }}</strong></div>
                @endif
                @if($boletoCancellationFeeTotal > 0)
                    <div class="flex items-center justify-between gap-3"><span>Taxa de cancelamento</span><strong>R$ {{ number_format($boletoCancellationFeeTotal, 2, ',', '.') }}</strong></div>
                @endif
                @if((float) $memory->abatement_amount > 0)
                    <div class="flex items-center justify-between gap-3"><span>Abatimento</span><strong>- R$ {{ number_format((float) $memory->abatement_amount, 2, ',', '.') }}</strong></div>
                @endif
                <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-800"><span>Debito atualizado</span><strong>R$ {{ number_format((float) $memory->debit_total, 2, ',', '.') }}</strong></div>
                <div class="flex items-center justify-between gap-3"><span>Honorarios</span><strong>R$ {{ number_format((float) $memory->attorney_fee_amount, 2, ',', '.') }}</strong></div>
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-warning-900 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-100"><span>Total geral</span><strong>R$ {{ number_format((float) $memory->grand_total, 2, ',', '.') }}</strong></div>
            </div>
        </div>
    </div>
</div>
@endsection
