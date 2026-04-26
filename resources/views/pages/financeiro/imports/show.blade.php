@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Preview da Importacao" subtitle="Revise as colunas, linhas e validacoes antes de executar o lote financeiro.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.reports.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Relatorios</a>
        @if(($item->status ?? null) !== 'processed')
            <form method="post" action="{{ route('financeiro.import.process', $item) }}">
                @csrf
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Processar lote</button>
            </form>
        @endif
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 xl:grid-cols-4">
    <x-ancora.stat-card label="Escopo" :value="$scopeLabel" hint="Tipo de importacao." icon="fa-solid fa-file-import" />
    <x-ancora.stat-card label="Formato" :value="strtoupper($item->source_format)" hint="Arquivo enviado." icon="fa-solid fa-file-arrow-up" />
    <x-ancora.stat-card label="Linhas em preview" :value="$item->preview_rows_count" hint="Linhas identificadas no arquivo." icon="fa-solid fa-list-ol" />
    <x-ancora.stat-card label="Status" :value="$item->status" hint="Situacao atual do lote." icon="fa-solid fa-circle-info" />
</div>

@if(!empty($errors))
    <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
        <h3 class="text-base font-semibold">Pendencias encontradas</h3>
        <ul class="mt-3 space-y-2">
            @foreach($errors as $error)
                <li>- {{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if(empty($rows))
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-file-circle-xmark" title="Sem linhas para importar" subtitle="O arquivo nao retornou dados validos para processamento." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        @foreach($headers as $header)
                            <th class="px-4 py-3">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($rows as $row)
                        <tr>
                            @foreach($headers as $header)
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row[$header] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
