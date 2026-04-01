@extends('layouts.app')

@section('content')
@php
    $firstName = explode(' ', trim($ancoraAuthUser?->name ?? 'Usuário'))[0] ?? 'Usuário';
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');
@endphp

<x-ancora.section-header title="{{ $greeting }}, {{ $firstName }}" subtitle="Selecione um módulo para continuar trabalhando na nova base Laravel.">
    <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Agora</p>
        <div class="mt-2 text-right">
            <div id="hubClockTime" class="text-2xl font-semibold text-gray-900 dark:text-white">--:--</div>
            <div id="hubClockDate" class="text-sm text-gray-500 dark:text-gray-400">--</div>
        </div>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
    @foreach($tiles as $tile)
        <a href="{{ $tile['route'] }}" class="group relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-6 shadow-theme-xs transition duration-300 hover:-translate-y-1 hover:shadow-theme-lg dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="absolute top-0 right-0 h-40 w-40 translate-x-10 -translate-y-10 rounded-full bg-brand-500/5 transition duration-300 group-hover:scale-125"></div>
            <div class="relative flex items-start justify-between gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl {{ $tile['accent'] === 'success' ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' : ($tile['accent'] === 'warning' ? 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400' : ($tile['accent'] === 'gray' ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' : 'bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400')) }}">
                    <i class="{{ $tile['icon_class'] }} text-xl"></i>
                </div>
                <span class="rounded-full border border-gray-200 px-3 py-1 text-xs uppercase tracking-[0.2em] text-gray-500 dark:border-gray-700 dark:text-gray-300">{{ $tile['enabled'] ? 'ativo' : 'inativo' }}</span>
            </div>
            <h3 class="relative mt-6 text-xl font-semibold text-gray-900 dark:text-white">{{ $tile['name'] }}</h3>
            <p class="relative mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $tile['description'] }}</p>
            <div class="relative mt-6 flex items-center gap-2 text-sm font-medium text-brand-500 dark:text-brand-400">
                <span>Acessar módulo</span>
                <i class="fa-solid fa-arrow-right transition duration-300 group-hover:translate-x-1"></i>
            </div>
        </a>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
(function(){
    const timeEl = document.getElementById('hubClockTime');
    const dateEl = document.getElementById('hubClockDate');
    const updateClock = () => {
        const now = new Date();
        timeEl.textContent = now.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        dateEl.textContent = now.toLocaleDateString('pt-BR', {weekday: 'long', day: '2-digit', month: 'long', year: 'numeric'});
    };
    updateClock(); setInterval(updateClock, 1000);
})();
</script>
@endpush
