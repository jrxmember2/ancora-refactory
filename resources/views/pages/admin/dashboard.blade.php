@extends('layouts.app')

@section('content')
@php
    $dashboard = $executiveDashboard;
    $periodOptions = $dashboard['period_options'] ?? [];
    $range = $dashboard['range'] ?? [];
    $cards = $dashboard['cards'] ?? [];
@endphp

<x-ancora.section-header title="Dashboard Executivo" subtitle="Indicadores macro do escritorio com detalhamento por modal e recorte por periodo.">
    <div class="flex flex-wrap items-stretch justify-end gap-3">
        <form action="{{ route('dashboard') }}" method="get" class="rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center gap-2">
                <label for="dashboard-period" class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Periodo</label>
                <select id="dashboard-period" name="period" class="h-10 rounded-xl border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ $key }}" @selected($dashboard['period'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Aplicar</button>
            </div>
        </form>

        <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Agora</p>
            <div class="mt-2 text-right">
                <div id="executiveClockTime" class="text-2xl font-semibold text-gray-900 dark:text-white">--:--</div>
                <div id="executiveClockDate" class="text-sm text-gray-500 dark:text-gray-400">--</div>
            </div>
        </div>
    </div>
</x-ancora.section-header>

<section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Painel Consolidado</div>
            <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $range['headline'] ?? 'Visao executiva' }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recorte atual: {{ $range['label'] ?? 'periodo selecionado' }}. Clique em um card para abrir o detalhamento.</p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/40">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-400">Recorte</div>
                <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $range['label'] ?? '-' }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/40">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-400">Cards</div>
                <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $dashboard['card_count'] ?? 0 }} indicadores</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/40">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-400">Atualizado</div>
                <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ optional($dashboard['generated_at'] ?? null)->format('d/m/Y H:i') ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
        @forelse($cards as $card)
            @php
                $dialogId = 'executive-card-modal-' . $card['key'];
            @endphp
            <button type="button" onclick="openExecutiveCardModal('{{ $dialogId }}')" class="text-left">
                <x-ancora.stat-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :hint="$card['hint']"
                    :icon="$card['icon']"
                    class="h-full cursor-pointer transition hover:-translate-y-1 hover:border-brand-300 hover:shadow-theme-md dark:hover:border-brand-800"
                />
            </button>
        @empty
            <div class="sm:col-span-2 xl:col-span-4 2xl:col-span-5 rounded-2xl border border-dashed border-gray-300 bg-white p-8 dark:border-gray-700 dark:bg-white/[0.03]">
                <x-ancora.empty-state
                    icon="fa-solid fa-chart-line"
                    title="Sem indicadores disponiveis"
                    subtitle="Os cards executivos aparecerao aqui conforme os modulos liberados para o usuario."
                />
            </div>
        @endforelse
    </div>
</section>

@foreach($cards as $card)
    @php
        $detail = $card['detail'];
        $dialogId = 'executive-card-modal-' . $card['key'];
    @endphp
    <dialog id="{{ $dialogId }}" class="fixed inset-0 m-auto h-[90vh] w-[96vw] max-w-5xl overflow-hidden rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex h-full flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 dark:border-gray-800">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ $detail['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $detail['subtitle'] }}</p>
                </div>
                <button type="button" onclick="closeExecutiveCardModal('{{ $dialogId }}')" class="rounded-full border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                    @foreach($detail['stats'] as $stat)
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/40">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-400">{{ $stat['label'] }}</div>
                            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $stat['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Detalhamento</h4>
                        @if(!empty($detail['action_url']) && !empty($detail['action_label']))
                            <a href="{{ $detail['action_url'] }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">{{ $detail['action_label'] }}</a>
                        @endif
                    </div>

                    <div class="space-y-3">
                        @forelse($detail['items'] as $item)
                            @php
                                $tag = !empty($item['url']) ? 'a' : 'div';
                            @endphp
                            <{{ $tag }}
                                @if(!empty($item['url']))
                                    href="{{ $item['url'] }}"
                                @endif
                                class="block rounded-2xl border border-gray-200 p-4 transition {{ !empty($item['url']) ? 'hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-500/10' : '' }} dark:border-gray-800"
                            >
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                        @if($item['subtitle'] !== '')
                                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $item['subtitle'] }}</div>
                                        @endif
                                        @if($item['meta'] !== '')
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['meta'] }}</div>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 flex-col items-start gap-2 md:items-end">
                                        @if(!empty($item['badge']))
                                            <span class="rounded-full border border-gray-200 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $item['badge'] }}</span>
                                        @endif
                                        @if($item['value'] !== '')
                                            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $item['value'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </{{ $tag }}>
                        @empty
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 dark:border-gray-700 dark:bg-white/[0.03]">
                                <x-ancora.empty-state
                                    icon="{{ $card['icon'] }}"
                                    :title="$detail['empty_title']"
                                    :subtitle="$detail['empty_subtitle']"
                                />
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                <button type="button" onclick="closeExecutiveCardModal('{{ $dialogId }}')" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
        </div>
    </dialog>
@endforeach

@endsection

@push('scripts')
<script>
(function () {
    const timeEl = document.getElementById('executiveClockTime');
    const dateEl = document.getElementById('executiveClockDate');

    const updateClock = () => {
        const now = new Date();

        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('pt-BR', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });
        }
    };

    updateClock();
    setInterval(updateClock, 1000);

    document.querySelectorAll('dialog[id^="executive-card-modal-"]').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            const isBackdropClick = event.clientY < rect.top
                || event.clientY > rect.bottom
                || event.clientX < rect.left
                || event.clientX > rect.right;

            if (isBackdropClick) {
                dialog.close();
            }
        });
    });
})();

function openExecutiveCardModal(id) {
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
}

function closeExecutiveCardModal(id) {
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.close === 'function') {
        dialog.close();
    }
}
</script>
@endpush
