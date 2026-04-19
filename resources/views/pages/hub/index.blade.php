@extends('layouts.app')

@section('content')
@php
    $firstName = explode(' ', trim($ancoraAuthUser?->name ?? 'Usuário'))[0] ?? 'Usuário';
    $now = now();
    $time = $now->format('H:i');
    $greeting = ($time >= '06:00' && $time < '12:00') ? 'Bom dia' : (($time >= '12:00' && $time < '18:00') ? 'Boa tarde' : 'Boa noite');
@endphp

<x-ancora.section-header title="{{ $greeting }}, {{ $firstName }}" subtitle="Selecione um módulo para continuar trabalhando.">
    <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Agora</p>
        <div class="mt-2 text-right">
            <div id="hubClockTime" class="text-2xl font-semibold text-gray-900 dark:text-white">--:--</div>
            <div id="hubClockDate" class="text-sm text-gray-500 dark:text-gray-400">--</div>
        </div>
    </div>
</x-ancora.section-header>

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

@endsection

@push('scripts')
<script>
(function () {
    const timeEl = document.getElementById('hubClockTime');
    const dateEl = document.getElementById('hubClockDate');

    const updateClock = () => {
        const now = new Date();
        timeEl.textContent = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        dateEl.textContent = now.toLocaleDateString('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    };

    updateClock();
    setInterval(updateClock, 1000);
})();
</script>
@endpush
