@extends('layouts.app')

@section('content')
<x-ancora.section-header title="TJES avulso" subtitle="Memorias de calculo TJES fora da estrutura de condominios e OS, usando a mesma base de fatores e regras do modulo de cobrancas.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista de OS</a>
        <a href="{{ route('cobrancas.monetary.standalone.create') }}" class="rounded-xl bg-warning-500 px-4 py-3 text-sm font-medium text-white hover:bg-warning-600">Novo calculo</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

@if(!($storageReady ?? false))
    <div class="rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
        Rode a migration do TJES avulso para liberar o historico e o salvamento das memorias de calculo fora da OS.
    </div>
@else
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="get" action="{{ route('cobrancas.monetary.standalone.index') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr),280px,auto]">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Titulo, devedor, documento ou cliente avulso" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente avulso</label>
                <select name="client_entity_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="0">Todos</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((int) ($filters['client_entity_id'] ?? 0) === (int) $client->id)>{{ $client->display_name ?: $client->legal_name ?: ('Cliente #' . $client->id) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
                <a href="{{ route('cobrancas.monetary.standalone.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
            </div>
        </form>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Historico do TJES avulso</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cada memoria salva aqui permanece independente das OS de cobranca.</p>
            </div>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ method_exists($items, 'total') ? $items->total() : $items->count() }} registro(s)
            </span>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="py-3 pr-4">Identificacao</th>
                        <th class="py-3 pr-4">Devedor</th>
                        <th class="py-3 pr-4">Cliente avulso</th>
                        <th class="py-3 pr-4">Base final</th>
                        <th class="py-3 pr-4">Itens</th>
                        <th class="py-3 pr-4">Total geral</th>
                        <th class="py-3 pr-4">Criado em</th>
                        <th class="py-3 pr-0"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($items as $item)
                        <tr>
                            <td class="py-4 pr-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $item->title }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->index_code === 'ATM' ? 'Indice do TJES' : $item->index_code }}</div>
                            </td>
                            <td class="py-4 pr-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->debtor_name_snapshot }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->debtor_document_snapshot ?: 'Documento nao informado' }}</div>
                            </td>
                            <td class="py-4 pr-4 text-gray-700 dark:text-gray-200">{{ $item->client?->display_name ?: ($item->client?->legal_name ?: 'Nao vinculado') }}</td>
                            <td class="py-4 pr-4 text-gray-700 dark:text-gray-200">{{ optional($item->final_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="py-4 pr-4 text-gray-700 dark:text-gray-200">{{ $item->items->count() }}</td>
                            <td class="py-4 pr-4 font-semibold text-gray-900 dark:text-white">R$ {{ number_format((float) $item->grand_total, 2, ',', '.') }}</td>
                            <td class="py-4 pr-4 text-gray-700 dark:text-gray-200">{{ optional($item->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="py-4 pr-0">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('cobrancas.monetary.standalone.show', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a>
                                    <a href="{{ route('cobrancas.monetary.standalone.pdf', $item) }}" target="_blank" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">PDF</a>
                                    <form method="post" action="{{ route('cobrancas.monetary.standalone.delete', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirm('Excluir esta memoria avulsa?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8">
                                <x-ancora.empty-state icon="fa-solid fa-scale-balanced" title="Sem memorias avulsas" subtitle="Use o botao Novo calculo para registrar a primeira memoria TJES fora da OS." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="mt-6">{{ $items->links() }}</div>
        @endif
    </div>
@endif
@endsection
