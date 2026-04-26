@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

<x-ancora.section-header title="Relatorios Financeiros" subtitle="Central de exportacao, importacao, lotes recentes e atalhos para visoes executivas do Financeiro 360.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.settings.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Configuracoes</a>
        <a href="{{ route('financeiro.dashboard') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Dashboard</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Receita do mes" :value="$money($summary['receita_mes'])" hint="Resumo financeiro corrente." icon="fa-solid fa-sack-dollar" />
    <x-ancora.stat-card label="Saldo liquido" :value="$money($summary['saldo_liquido'])" hint="Entradas menos saidas." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Caixa atual" :value="$money($summary['caixa_atual'])" hint="Contas financeiras ativas." icon="fa-solid fa-wallet" />
    <x-ancora.stat-card label="Inadimplencia" :value="$money($summary['inadimplencia'])" hint="Titulos atrasados." icon="fa-solid fa-triangle-exclamation" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Exportar dados</h3>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach($exportScopes as $scope => $label)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $label }}</div>
                    <div class="mt-3">
                        <x-financeiro.export-actions :scope="$scope" label="Exportar" :allow-selection="false" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Importar dados</h3>
        <div class="mt-4 space-y-4">
            @foreach($importScopes as $scope => $label)
                <form method="post" action="{{ route('financeiro.import.preview', $scope) }}" enctype="multipart/form-data" class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    @csrf
                    <div class="font-medium text-gray-900 dark:text-white">{{ $label }}</div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a href="{{ route('financeiro.import.template', $scope) }}" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Baixar modelo</a>
                        <input type="file" name="import_file" accept=".csv,.xlsx,.ofx" class="block text-sm text-gray-600 dark:text-gray-300">
                        <button class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-medium text-white">Preview</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lotes recentes</h3>
    @if($recentImports->isEmpty())
        <div class="mt-4"><x-ancora.empty-state icon="fa-solid fa-file-arrow-up" title="Sem importacoes recentes" subtitle="Quando voce analisar ou processar lotes, eles aparecerao aqui." /></div>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Escopo</th>
                        <th class="px-4 py-3">Formato</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Linhas</th>
                        <th class="px-4 py-3">Processado</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($recentImports as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">#{{ $item->id }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $importScopes[$item->scope] ?? $item->scope }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ strtoupper($item->source_format) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->status }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->preview_rows_count }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ optional($item->processed_at)->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('financeiro.import.show', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
