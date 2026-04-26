@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Relatórios" subtitle="Acompanhe o acervo contratual por cliente, condomínio, status, tipo e receita contratada prevista.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('contratos.reports.export.csv', request()->query()) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Exportar CSV</a>
        <a href="{{ route('contratos.reports.export.pdf', request()->query()) }}" target="_blank" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Exportar PDF</a>
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <select name="client_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100"><option value="">Todos os clientes</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((int) $filters['client_id'] === (int) $client->id)>{{ $client->display_name }}</option>@endforeach</select>
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100"><option value="">Todos os condomínios</option>@foreach($condominiums as $condominium)<option value="{{ $condominium->id }}" @selected((int) $filters['condominium_id'] === (int) $condominium->id)>{{ $condominium->name }}</option>@endforeach</select>
        <select name="type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100"><option value="">Todos os tipos</option>@foreach($typeOptions as $type)<option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>@endforeach</select>
        <select name="category_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100"><option value="">Todas as categorias</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) $filters['category_id'] === (int) $category->id)>{{ $category->name }}</option>@endforeach</select>
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100"><option value="">Todos os status</option>@foreach($statusLabels as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select>
        <input type="date" name="start_from" value="{{ $filters['start_from'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <input type="date" name="end_to" value="{{ $filters['end_to'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <div class="flex gap-3 xl:col-span-4"><button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Aplicar</button><a href="{{ route('contratos.reports.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a></div>
    </form>
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Total de contratos" :value="$summary['total']" hint="Resultado do filtro atual." icon="fa-solid fa-file-contract" />
    <x-ancora.stat-card label="Ativos" :value="$summary['active']" hint="Status ativo no filtro atual." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Vencidos" :value="$summary['expired']" hint="Vigência encerrada." icon="fa-solid fa-calendar-xmark" />
    <x-ancora.stat-card label="Receita prevista" :value="'R$ ' . number_format((float) $summary['contracted_revenue'], 2, ',', '.')" hint="Soma de valores principais." icon="fa-solid fa-sack-dollar" />
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                    <th class="px-6 py-4">Código</th>
                    <th class="px-6 py-4">Título</th>
                    <th class="px-6 py-4">Cliente</th>
                    <th class="px-6 py-4">Condomínio</th>
                    <th class="px-6 py-4">Tipo</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($items as $item)
                    <tr>
                        <td class="px-6 py-4 text-gray-900 dark:text-white">{{ $item->code ?: '—' }}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->title }}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->client?->display_name ?: '—' }}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->condominium?->name ?: '—' }}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->type }}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $statusLabels[$item->status] ?? $item->status }}</td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) ($item->monthly_value ?? $item->contract_value ?? $item->total_value ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8"><x-ancora.empty-state icon="fa-solid fa-chart-column" title="Sem dados" subtitle="Não há contratos no recorte atual." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
