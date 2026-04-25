@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('content')
<x-ancora.section-header title="Kanban de Demandas" subtitle="Arraste uma demanda para outra tag para atualizar o fluxo, status publico e SLA.">
    <a href="{{ route('demandas.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova demanda</a>
    <a href="{{ route('demandas.dashboard') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Dashboard</a>
    <a href="{{ route('demandas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-3 lg:grid-cols-5">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Protocolo, assunto ou cliente..." class="{{ $inputClass }} lg:col-span-2">
        <select name="client_condominium_id" class="{{ $inputClass }}">
            <option value="">Todos os condominios</option>
            @foreach($condominiums as $condominium)
                <option value="{{ $condominium->id }}" @selected((int) ($filters['client_condominium_id'] ?? 0) === (int) $condominium->id)>{{ $condominium->name }}</option>
            @endforeach
        </select>
        <select name="assigned_user_id" class="{{ $inputClass }}">
            <option value="">Todos os responsaveis</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) ($filters['assigned_user_id'] ?? 0) === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('demandas.kanban') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-x-auto pb-4" data-kanban-board>
    <div class="flex min-w-max gap-5">
        @foreach($tags as $tag)
            @php($items = $itemsByTag[$tag->id] ?? collect())
            <section class="flex h-[calc(100vh-260px)] w-80 shrink-0 flex-col overflow-hidden rounded-3xl border border-gray-200 bg-gray-50 shadow-theme-xs dark:border-gray-800 dark:bg-gray-950/40" data-kanban-column data-tag-id="{{ $tag->id }}">
                <div class="border-b border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $tag->color_hex }}"></span>
                            <h2 class="font-semibold text-gray-900 dark:text-white">{{ $tag->name }}</h2>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300" data-column-count>{{ $items->count() }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-white px-2 py-1 text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800">{{ $tag->sla_hours ? $tag->sla_hours.'h SLA' : 'Sem SLA' }}</span>
                        <span class="rounded-full bg-white px-2 py-1 text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800">{{ $tag->show_on_portal ? 'Portal ve' : 'Interno' }}</span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3" data-kanban-dropzone>
                    @forelse($items as $item)
                        <article draggable="true" data-demand-card data-demand-id="{{ $item->id }}" data-current-tag-id="{{ $tag->id }}" data-update-url="{{ route('demandas.tag.update', $item) }}" class="cursor-grab rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ route('demandas.show', $item) }}" class="text-sm font-semibold text-brand-600 dark:text-brand-300">{{ $item->protocol }}</a>
                                <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $item->slaStatus() === 'overdue' ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-200' : ($item->slaStatus() === 'at_risk' ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300') }}">
                                    {{ $item->slaStatusLabel() }}
                                </span>
                            </div>
                            <a href="{{ route('demandas.show', $item) }}" class="mt-2 block text-sm font-medium leading-5 text-gray-900 dark:text-white">{{ $item->subject }}</a>
                            <div class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <div><i class="fa-solid fa-building mr-1"></i>{{ $item->clientName() }}</div>
                                <div><i class="fa-solid fa-user mr-1"></i>{{ $item->assignee?->name ?: 'Nao atribuido' }}</div>
                                @if($item->sla_due_at)
                                    <div><i class="fa-solid fa-clock mr-1"></i>{{ $item->sla_due_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full" style="width: {{ $item->slaProgressPercent() }}%; background-color: {{ $tag->color_hex }}"></div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400" data-empty-column>Sem demandas nesta tag.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
if (typeof window.showConfigToast !== 'function') {
    window.showConfigToast = function(message, type = 'success') {
        const el = document.createElement('div');
        el.className = `fixed right-6 top-6 z-[999999] rounded-2xl px-4 py-3 text-sm font-medium shadow-theme-lg ${type === 'error' ? 'bg-error-500 text-white' : 'bg-success-500 text-white'}`;
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 2200);
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let dragged = null;

    function refreshCounts() {
        document.querySelectorAll('[data-kanban-column]').forEach((column) => {
            const count = column.querySelectorAll('[data-demand-card]').length;
            const badge = column.querySelector('[data-column-count]');
            if (badge) badge.textContent = count;
            const empty = column.querySelector('[data-empty-column]');
            if (empty) empty.style.display = count ? 'none' : '';
        });
    }

    document.querySelectorAll('[data-demand-card]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            dragged = card;
            event.dataTransfer.effectAllowed = 'move';
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
            dragged = null;
        });
    });

    document.querySelectorAll('[data-kanban-dropzone]').forEach((zone) => {
        zone.addEventListener('dragover', (event) => {
            event.preventDefault();
            zone.classList.add('ring-2', 'ring-brand-300');
        });
        zone.addEventListener('dragleave', () => {
            zone.classList.remove('ring-2', 'ring-brand-300');
        });
        zone.addEventListener('drop', async (event) => {
            event.preventDefault();
            zone.classList.remove('ring-2', 'ring-brand-300');
            if (!dragged) return;

            const column = zone.closest('[data-kanban-column]');
            const tagId = column?.dataset.tagId;
            const oldParent = dragged.parentElement;
            const oldTagId = dragged.dataset.currentTagId;
            if (!tagId || oldTagId === tagId) return;

            zone.appendChild(dragged);
            dragged.dataset.currentTagId = tagId;
            refreshCounts();

            try {
                const response = await fetch(dragged.dataset.updateUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ demand_tag_id: tagId }),
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error('move_failed');
                showConfigToast('Demanda movida com sucesso.');
            } catch (error) {
                oldParent.appendChild(dragged);
                dragged.dataset.currentTagId = oldTagId;
                refreshCounts();
                showConfigToast('Nao foi possivel mover a demanda.', 'error');
            }
        });
    });
});
</script>
@endpush
