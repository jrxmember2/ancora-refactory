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

<div class="mb-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="max-w-3xl">
        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-brand-500">Ecossistema Âncora</div>
        <h2 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">Organize clientes, condomínios, propostas e acessos em uma operação mais clara, profissional e pronta para o dia a dia do seu escritório.</h2>
        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">Centralize cadastros, documentos, histórico comercial e permissões em um único ambiente, reduzindo retrabalho e dando mais previsibilidade à gestão.</p>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-sm font-semibold text-gray-900 dark:text-white">Clientes e condomínios</div><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cadastros estruturados para síndicos, administradoras, unidades e documentos.</p></div>
        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-sm font-semibold text-gray-900 dark:text-white">Propostas premium</div><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fluxo comercial com histórico, anexos e documento profissional.</p></div>
        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-sm font-semibold text-gray-900 dark:text-white">Permissões e segurança</div><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Controle de acesso por módulo e por rota para trabalhar em equipe.</p></div>
        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-sm font-semibold text-gray-900 dark:text-white">Rotina mais eficiente</div><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Mais organização, menos retrabalho e melhor visão da operação.</p></div>
    </div>
</div>

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
            <div class="relative mt-6 flex items-center gap-2 text-sm font-medium text-brand-500 dark:text-brand-400"><span>Acessar</span><i class="fa-solid fa-arrow-right transition duration-300 group-hover:translate-x-1"></i></div>
        </a>
    @endforeach
</div>

<div class="fixed bottom-4 right-4 z-[99999]">
    <a href="https://www.serratech.tec.br" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/95 px-3 py-2 text-[11px] font-medium text-gray-600 shadow-theme-lg backdrop-blur hover:text-brand-600 dark:border-gray-800 dark:bg-gray-900/95 dark:text-gray-300 dark:hover:text-brand-400">
        <span>Powered by Serratech</span>
        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
    </a>
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
