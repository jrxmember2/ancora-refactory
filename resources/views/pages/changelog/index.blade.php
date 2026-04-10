@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Versionamento e changelog" subtitle="Histórico consolidado das evoluções da nova base Âncora." />

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[280px,1fr]">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Visão geral</h3>
        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Este histórico resume as entregas feitas na reescrita do Âncora em Laravel/TailAdmin, com foco em branding, clientes, propostas, permissões e operação em VPS/EasyPanel.</p>
        <div class="mt-5 space-y-3 text-sm">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Base atual</div>
                <div class="mt-1 font-semibold text-gray-900 dark:text-white">Laravel + TailAdmin</div>
                @if(!empty($ancoraVersion['label']))
                    <div class="mt-2 text-xs font-medium tracking-[0.14em] text-brand-500">{{ $ancoraVersion['label'] }}</div>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Escopo</div>
                <div class="mt-1 font-semibold text-gray-900 dark:text-white">Hub, Clientes, Cobrança, Propostas, Config, Logs</div>
            </div>
        </div>
    </div>

    <div class="space-y-5">
        @foreach($releases as $release)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-[0.16em] text-brand-500">{{ $release['version'] }} • {{ $release['date'] }}</div>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $release['title'] }}</h3>
                    </div>
                </div>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    @foreach($release['items'] as $item)
                        <li class="flex gap-3"><i class="fa-solid fa-check mt-1 text-brand-500"></i><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
@endsection
