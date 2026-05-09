@extends('layouts.app')

@section('content')
@php
    $firstName = explode(' ', trim($ancoraAuthUser?->name ?? 'Usuario'))[0] ?? 'Usuario';
    $now = now();
    $time = $now->format('H:i');
    $greeting = ($time >= '06:00' && $time < '12:00') ? 'Bom dia' : (($time >= '12:00' && $time < '18:00') ? 'Boa tarde' : 'Boa noite');
    $dashboard = $executiveDashboard;
    $periodOptions = $dashboard['period_options'] ?? [];
    $range = $dashboard['range'] ?? [];
    $cards = $dashboard['cards'] ?? [];
@endphp

<x-ancora.section-header title="{{ $greeting }}, {{ $firstName }}" subtitle="Painel executivo consolidado do escritorio e acesso rapido aos modulos do Ancora Hub.">
    <div class="flex flex-wrap items-stretch justify-end gap-3">
        <form action="{{ route('hub') }}" method="get" class="rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-center gap-2">
                <label for="hub-period" class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Periodo</label>
                <select id="hub-period" name="period" class="h-10 rounded-xl border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
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
                <div id="hubClockTime" class="text-2xl font-semibold text-gray-900 dark:text-white">--:--</div>
                <div id="hubClockDate" class="text-sm text-gray-500 dark:text-gray-400">--</div>
            </div>
        </div>
    </div>
</x-ancora.section-header>

<section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Dashboard Executivo</div>
            <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $range['headline'] ?? 'Visao executiva' }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Indicadores macro do escritorio com detalhamento por modal. Recorte atual: {{ $range['label'] ?? 'periodo selecionado' }}.</p>
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
                $dialogId = 'hub-card-modal-' . $card['key'];
            @endphp
            <button type="button" onclick="openHubCardModal('{{ $dialogId }}')" class="text-left">
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

<section class="mt-8">
    <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Modulos</div>
            <h2 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">Acesso rapido do sistema</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Selecione um modulo para continuar trabalhando no fluxo operacional.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-5">
        @foreach($tiles as $tile)
            <a href="{{ $tile['route'] }}" class="group relative min-h-[210px] overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-theme-xs ring-1 ring-transparent transition duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-theme-md hover:ring-brand-100 dark:border-gray-800 dark:bg-white/[0.04] dark:hover:border-brand-800 dark:hover:ring-brand-900/40">
                <div class="absolute -right-14 -top-14 h-36 w-36 rounded-full bg-brand-500/10 blur-2xl transition duration-300 group-hover:scale-125 group-hover:bg-brand-500/15"></div>
                <div class="absolute bottom-0 left-0 h-1 w-full origin-left scale-x-0 bg-brand-500 transition duration-300 group-hover:scale-x-100"></div>
                <div class="relative flex items-start justify-between gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl shadow-sm {{ $tile['accent'] === 'success' ? 'bg-success-50 text-success-600 ring-1 ring-success-100 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/20' : ($tile['accent'] === 'warning' ? 'bg-warning-50 text-warning-600 ring-1 ring-warning-100 dark:bg-warning-500/10 dark:text-warning-400 dark:ring-warning-500/20' : ($tile['accent'] === 'gray' ? 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700' : 'bg-brand-50 text-brand-500 ring-1 ring-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20')) }}">
                        <i class="{{ $tile['icon_class'] }} text-lg"></i>
                    </div>
                    <span class="rounded-full border border-gray-200 bg-white/80 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300">{{ $tile['enabled'] ? 'ativo' : 'inativo' }}</span>
                </div>
                <h3 class="relative mt-5 text-lg font-semibold text-gray-950 dark:text-white">{{ $tile['name'] }}</h3>
                <p class="relative mt-2 line-clamp-3 min-h-[60px] text-xs leading-5 text-gray-600 dark:text-gray-300">{{ $tile['description'] }}</p>
                <div class="relative mt-5 inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-2 text-xs font-semibold text-brand-600 transition duration-300 group-hover:bg-brand-500 group-hover:text-white dark:bg-white/[0.06] dark:text-brand-300"><span>Acessar</span><i class="fa-solid fa-arrow-right transition duration-300 group-hover:translate-x-1"></i></div>
            </a>
        @endforeach
    </div>
</section>

@foreach($cards as $card)
    @php
        $detail = $card['detail'];
        $dialogId = 'hub-card-modal-' . $card['key'];
    @endphp
    <dialog id="{{ $dialogId }}" class="fixed inset-0 m-auto h-[90vh] w-[96vw] max-w-5xl overflow-hidden rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex h-full flex-col">
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5 dark:border-gray-800">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ $detail['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $detail['subtitle'] }}</p>
                </div>
                <button type="button" onclick="closeHubCardModal('{{ $dialogId }}')" class="rounded-full border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
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
                <button type="button" onclick="closeHubCardModal('{{ $dialogId }}')" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
        </div>
    </dialog>
@endforeach

@endsection

@push('scripts')
<script>
(function () {
    const timeEl = document.getElementById('hubClockTime');
    const dateEl = document.getElementById('hubClockDate');

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

    document.querySelectorAll('dialog[id^="hub-card-modal-"]').forEach((dialog) => {
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

function openHubCardModal(id) {
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
}

function closeHubCardModal(id) {
    const dialog = document.getElementById(id);
    if (dialog && typeof dialog.close === 'function') {
        dialog.close();
    }
}
</script>
@endpush
