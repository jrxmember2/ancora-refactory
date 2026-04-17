@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Dashboard de cobrança" subtitle="Visão operacional das OS de cobrança, negociações e judicialização.">
    <div class="flex flex-wrap gap-3">
        <form method="get" class="flex items-center gap-2">
            <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                @foreach(($years->isNotEmpty() ? $years : collect([now()->year])) as $optionYear)
                    <option value="{{ $optionYear }}" @selected((int) $year === (int) $optionYear)>{{ $optionYear }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Aplicar</button>
        </form>
        <a href="{{ route('cobrancas.billing.report') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.03]">Faturamento</a>
        <a href="{{ route('cobrancas.import.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.03]">Importar inadimplência</a>
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl border border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">Nova OS</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="OS no ano" :value="$summary['total']" hint="Cadastro total de cobranças no período." icon="fa-solid fa-folder-open" />
    <x-ancora.stat-card label="Aptas para notificar" :value="$summary['notificar']" hint="Prontas para acionar WhatsApp/e-mail." icon="fa-solid fa-paper-plane" />
    <x-ancora.stat-card label="Em negociação" :value="$summary['negociacao']" hint="OS com tratativa ativa." icon="fa-solid fa-comments-dollar" />
    <x-ancora.stat-card label="Aguardando assinatura" :value="$summary['aguardando_assinatura']" hint="Termos aguardando aceite." icon="fa-solid fa-file-signature" />
    <x-ancora.stat-card label="Aptas para judicializar" :value="$summary['judicializar']" hint="Casos prontos para virar ação." icon="fa-solid fa-gavel" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Acordos ativos" :value="$summary['acordo_ativo']" hint="Parcelamentos em execução." icon="fa-solid fa-receipt" />
    <x-ancora.stat-card label="Ajuizados" :value="$summary['ajuizado']" hint="OS já levadas ao judicial." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Encerrados" :value="$summary['encerrado']" hint="Pagos / finalizados." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Valor em acordos" :value="'R$ '.number_format((float) $summary['agreement_total'], 2, ',', '.')" :hint="'Entradas somadas: R$ '.number_format((float) $summary['entry_total'], 2, ',', '.')" icon="fa-solid fa-money-bill-wave" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Fluxo sugerido</h3>
            <a href="{{ route('cobrancas.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Abrir lista</a>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">1. Triagem e quotas</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Abra a OS, vincule unidade, preencha quotas em aberto e defina o devedor correto.</p>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">2. Notificação</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Registre e-mails e telefones, avance para “Apto para notificar” e use o payload n8n da OS.</p>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">3. Negociação</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Cadastre entrada, honorários, parcelas e acompanhe o histórico completo na timeline.</p>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">4. Judicialização</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Quando necessário, marque a etapa adequada e mantenha GED + andamentos prontos para ação.</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">OS recentes</h3>
            <a href="{{ route('cobrancas.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Ver todas</a>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($latestCases as $item)
                <a href="{{ route('cobrancas.show', $item) }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->os_number }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->condominium?->name ?? 'Condomínio não vinculado' }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->block?->name ? $item->block->name.' · ' : '' }}Unidade {{ $item->unit?->unit_number ?? '—' }}</div>
                        </div>
                        <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $stageLabels[$item->workflow_stage] ?? $item->workflow_stage }}</span>
                    </div>
                    <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">{{ $item->debtor_name_snapshot }}</div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-folder-open" title="Sem OS cadastradas" subtitle="Crie a primeira OS de cobrança para começar o fluxo." />
            @endforelse
        </div>
    </div>
</div>
@endsection
