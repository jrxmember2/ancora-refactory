@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]';
@endphp

@section('content')
<x-ancora.section-header title="Processos" subtitle="Controle processual do escritorio com cadastro, fases, anexos e sincronizacao DataJud.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('processos.create') }}" class="{{ $buttonClass }}">Novo processo</a>
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 xl:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Processo, cliente, adverso, responsavel..." class="{{ $inputClass }} xl:col-span-2">
        <select name="status_option_id" class="{{ $inputClass }}">
            <option value="">Status</option>
            @foreach(($options['status'] ?? collect()) as $option)
                <option value="{{ $option->id }}" @selected((int) ($filters['status_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
        <select name="process_type_option_id" class="{{ $inputClass }}">
            <option value="">Tipo de processo</option>
            @foreach(($options['process_type'] ?? collect()) as $option)
                <option value="{{ $option->id }}" @selected((int) ($filters['process_type_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
        <select name="datajud_court" class="{{ $inputClass }}">
            <option value="">Tribunal DataJud</option>
            @foreach(($options['datajud_court'] ?? collect()) as $option)
                <option value="{{ $option->slug }}" @selected(($filters['datajud_court'] ?? '') === $option->slug)>{{ $option->name }}</option>
            @endforeach
        </select>
        <select name="private" class="{{ $inputClass }}">
            <option value="">Privacidade</option>
            <option value="0" @selected(($filters['private'] ?? '') === '0')>Publicos internos</option>
            <option value="1" @selected(($filters['private'] ?? '') === '1')>Particulares</option>
        </select>
        <div class="xl:col-span-6 flex flex-wrap gap-3">
            <button class="{{ $buttonClass }}">Filtrar</button>
            <a href="{{ route('processos.index') }}" class="{{ $softButtonClass }}">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6">
            <x-ancora.empty-state icon="fa-solid fa-scale-balanced" title="Nenhum processo encontrado" subtitle="Ajuste os filtros ou cadastre o primeiro processo." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4"><x-ancora.sort-link field="process_number" label="Processo" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="client" label="Cliente" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="adverse" label="Adverso" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="status" label="Status" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="responsible" label="Responsavel" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $item->process_number ?: 'Sem numero' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Abertura: {{ $item->opened_at?->format('d/m/Y') ?: 'nao informada' }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if($item->is_private)
                                        <span class="rounded-full bg-error-50 px-2.5 py-1 text-xs font-medium text-error-700 dark:bg-error-500/10 dark:text-error-300">Particular</span>
                                    @endif
                                    @if($item->datajud_court)
                                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">DataJud</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->client_name_snapshot ?: 'Nao informado' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->processTypeOption?->name ?: 'Tipo nao informado' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->adverse_name ?: 'Nao informado' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->phases_count }} fase(s) &middot; {{ $item->attachments_count }} anexo(s)</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                @php($statusColor = $item->statusOption?->color_hex ?: '#6B7280')
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold text-white" style="background-color: {{ $statusColor }}">{{ $item->statusOption?->name ?: 'Sem status' }}</span>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $item->responsible_lawyer ?: 'Nao informado' }}</td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('processos.show', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a>
                                    <a href="{{ route('processos.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <form method="post" action="{{ route('processos.delete', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirm('Excluir este processo?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection
