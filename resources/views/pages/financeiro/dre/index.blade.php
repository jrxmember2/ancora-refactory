@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header title="DRE" subtitle="Demonstrativo do resultado do exercicio com agrupamento financeiro, receita liquida, custos, despesas e resultado final.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.dre.pdf', array_merge(request()->query(), ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])) }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Gerar PDF</a>
        <x-financeiro.export-actions scope="dre" :allow-selection="false" />
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <div class="flex gap-3 xl:col-span-2">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Atualizar DRE</button>
        </div>
    </form>
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Receita bruta" :value="$money($data['summary']['receita_bruta'])" hint="Entradas financeiras do periodo." icon="fa-solid fa-arrow-trend-up" />
    <x-ancora.stat-card label="Receita liquida" :value="$money($data['summary']['receita_liquida'])" hint="Receita apos deducoes." icon="fa-solid fa-wallet" />
    <x-ancora.stat-card label="Custos" :value="$money($data['summary']['custos'])" hint="Custos diretos do periodo." icon="fa-solid fa-gavel" />
    <x-ancora.stat-card label="Despesas" :value="$money($data['summary']['despesas'])" hint="Despesas operacionais e administrativas." icon="fa-solid fa-file-invoice-dollar" />
    <x-ancora.stat-card label="Resultado" :value="$money($data['summary']['resultado'])" hint="Lucro ou prejuizo." icon="fa-solid fa-scale-balanced" />
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3">Grupo</th>
                    <th class="px-4 py-3">Valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($data['groups'] as $key => $group)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $group['label'] }}</td>
                        <td class="px-4 py-3 {{ $group['amount'] < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-gray-700 dark:text-gray-200' }}">{{ $money($group['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
