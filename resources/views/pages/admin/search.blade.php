@extends('layouts.app')

@section('content')
@php
    $matchedSections = collect($sections)->filter(fn (array $section) => $section['items']->isNotEmpty())->values();
@endphp

<x-ancora.section-header
    title="Busca inteligente"
    subtitle="Pesquisa rapida entre usuarios, clientes, condominios, cobrancas, demandas, processos, contratos, assinaturas e financeiro."
/>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="flex flex-col gap-3 lg:flex-row">
        <input
            type="search"
            name="q"
            value="{{ $term }}"
            placeholder="Digite nome, documento, codigo, protocolo, numero do processo..."
            class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white"
        />
        <button class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">
            Buscar
        </button>
    </form>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach($sections as $section)
            <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-600 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300">
                <i class="{{ $section['icon'] }}"></i>
                <span>{{ $section['label'] }}</span>
            </span>
        @endforeach
    </div>
</div>

@if($term === '')
    <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-8 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03]">
        <x-ancora.empty-state
            icon="fa-solid fa-magnifying-glass"
            title="Comece digitando sua busca"
            subtitle="Voce pode pesquisar por nome, documento, OS, protocolo, numero de processo, contrato, assinatura ou titulo financeiro."
        />
    </div>
@else
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.18em] text-gray-400">Consulta</div>
            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $term }}</div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Termo pesquisado no hub.</div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.18em] text-gray-400">Resultados</div>
            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $totalResults }}</div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Itens encontrados no sistema.</div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.18em] text-gray-400">Modulos</div>
            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $matchedSections->count() }}</div>
            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Areas com resultado para esta pesquisa.</div>
        </div>
    </div>

    @if($totalResults === 0)
        <div class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-white p-8 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03]">
            <x-ancora.empty-state
                icon="fa-solid fa-circle-info"
                title="Nenhum resultado encontrado"
                subtitle="Tente pesquisar por outro nome, documento, protocolo, OS ou numero de processo."
            />
        </div>
    @else
        <div class="mt-6 space-y-6">
            @foreach($matchedSections as $section)
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-800 md:flex-row md:items-start md:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                                <i class="{{ $section['icon'] }}"></i>
                            </span>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $section['label'] }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $section['subtitle'] }}</p>
                            </div>
                        </div>

                        <span class="inline-flex items-center rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            {{ $section['items']->count() }} resultado(s)
                        </span>
                    </div>

                    <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($section['items'] as $item)
                            @php
                                $tag = $item['url'] ? 'a' : 'div';
                            @endphp
                            <{{ $tag }}
                                @if($item['url'])
                                    href="{{ $item['url'] }}"
                                @endif
                                class="block rounded-xl px-1 py-4 transition {{ $item['url'] ? 'hover:bg-gray-50 dark:hover:bg-white/[0.02]' : '' }}"
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

                                    @if(!empty($item['badge']))
                                        <span class="inline-flex shrink-0 items-center rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </div>
                            </{{ $tag }}>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endif
@endsection
