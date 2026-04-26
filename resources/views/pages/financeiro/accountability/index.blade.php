@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header title="Prestacao de Contas" subtitle="Relatorio consolidado de entradas, honorarios, custas e repasses por cliente ou condominio.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.accountability.pdf', array_merge(request()->query(), ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])) }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Gerar PDF</a>
        <x-financeiro.export-actions scope="accountability" :allow-selection="false" />
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <select name="client_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os clientes</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>{{ $client->display_name }}</option>
            @endforeach
        </select>
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os condominios</option>
            @foreach($condominiums as $condominium)
                <option value="{{ $condominium->id }}" @selected((string) $condominiumId === (string) $condominium->id)>{{ $condominium->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <div class="flex gap-3 xl:col-span-4">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Atualizar relatorio</button>
        </div>
    </form>
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Entradas" :value="$money($data['summary']['entradas'])" hint="Total recebido no periodo." icon="fa-solid fa-arrow-trend-up" />
    <x-ancora.stat-card label="Honorarios" :value="$money($data['summary']['honorarios'])" hint="Receita de honorarios." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Custas" :value="$money($data['summary']['custas'])" hint="Custas processuais e similares." icon="fa-solid fa-gavel" />
    <x-ancora.stat-card label="Repasses" :value="$money($data['summary']['repasses'])" hint="Valores repassados." icon="fa-solid fa-share-from-square" />
    <x-ancora.stat-card label="Saldo" :value="$money($data['summary']['saldo'])" hint="Resultado liquido do periodo." icon="fa-solid fa-wallet" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Entradas</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Codigo</th>
                        <th class="px-4 py-3">Titulo</th>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Recebido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['receivables'] as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->code }}</td>
                            <td class="px-4 py-3">{{ $item->title }}</td>
                            <td class="px-4 py-3">{{ optional($item->received_at ?: $item->due_date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $money($item->received_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Sem entradas no periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Custas e repasses</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Documento</th>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($data['costs'] as $item)
                        <tr>
                            <td class="px-4 py-3">Custa</td>
                            <td class="px-4 py-3">{{ $item->code }}</td>
                            <td class="px-4 py-3">{{ optional($item->cost_date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $money($item->amount) }}</td>
                        </tr>
                    @endforeach
                    @foreach($data['repasses'] as $item)
                        <tr>
                            <td class="px-4 py-3">Repasse</td>
                            <td class="px-4 py-3">{{ $item->code }}</td>
                            <td class="px-4 py-3">{{ optional($item->transaction_date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $money($item->amount) }}</td>
                        </tr>
                    @endforeach
                    @if($data['costs']->isEmpty() && $data['repasses']->isEmpty())
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Sem custos ou repasses no periodo.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
