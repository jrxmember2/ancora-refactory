@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Agenda - lista" subtitle="Todos os prazos e compromissos, com filtros.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Ver calendario</a>
        <a href="{{ route('agenda.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo compromisso</a>
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Buscar..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <select name="type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os tipos</option>
            @foreach($typeOptions as $key => $label)
                <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os status</option>
            @foreach($statusOptions as $key => $label)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="responsible_user_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os responsaveis</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" @selected((int) $filters['responsible_user_id'] === (int) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" name="fatal_only" value="1" @checked($filters['fatal_only'])> Apenas prazos fatais</label>
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" name="overdue_only" value="1" @checked($filters['overdue_only'])> Apenas atrasados</label>
        <div class="flex gap-3 xl:col-span-2">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('agenda.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">Limpar</a>
        </div>
    </form>
</div>

<form method="post" id="agenda-bulk-form">
    @csrf
    <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <span class="text-gray-500 dark:text-gray-400"><span data-selected-count>0</span> selecionado(s)</span>
        <div class="ml-auto flex flex-wrap gap-2">
            <button type="submit" formaction="{{ route('agenda.bulk-complete') }}"
                onclick="return document.querySelectorAll('[data-select-item]:checked').length ? confirm('Concluir os compromissos selecionados?') : (alert('Selecione ao menos um compromisso.'), false);"
                class="rounded-xl border border-success-300 bg-success-50 px-4 py-2 text-xs font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-300">Concluir selecionados</button>
            <button type="submit" formaction="{{ route('agenda.bulk-delete') }}"
                onclick="return document.querySelectorAll('[data-select-item]:checked').length ? confirm('Excluir os compromissos selecionados? Esta acao nao pode ser desfeita.') : (alert('Selecione ao menos um compromisso.'), false);"
                class="rounded-xl border border-rose-200 px-4 py-2 text-xs font-medium text-rose-600 dark:border-rose-900 dark:text-rose-300">Excluir selecionados</button>
        </div>
    </div>

    <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                    <th class="px-4 py-3">Quando</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Titulo</th>
                    <th class="px-4 py-3">Responsavel</th>
                    <th class="px-4 py-3">Vinculo</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($events as $event)
                    <tr>
                        <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $event->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $event->start_at->format('d/m/Y') }}
                            <span class="text-gray-400">{{ $event->all_day ? '' : $event->start_at->format('H:i') }}</span>
                            @if($event->is_fatal)<span class="ml-1 rounded bg-warning-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">Fatal</span>@endif
                        </td>
                        <td class="px-4 py-3">{{ $typeOptions[$event->type] ?? $event->type }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            @if($event->hasColor())<span class="mr-1 inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $event->color }}"></span>@endif
                            <a href="{{ route('agenda.show', $event) }}" class="hover:underline">{{ $event->title }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $event->responsible?->name ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                            @if($event->process){{ $event->process->process_number }}@elseif($event->client){{ $event->client->display_name }}@else-@endif
                        </td>
                        <td class="px-4 py-3">
                            @php $eff = $event->effectiveStatus(); @endphp
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium
                                {{ $eff === 'atrasado' ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300'
                                    : ($eff === 'concluido' ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300'
                                    : ($eff === 'cancelado' ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                    : 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200')) }}">
                                {{ $eff === 'atrasado' ? 'Atrasado' : ($statusOptions[$event->status] ?? $event->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('agenda.show', $event) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10"><x-ancora.empty-state icon="fa-solid fa-calendar-days" title="Nenhum compromisso" subtitle="Crie prazos e compromissos para acompanhar a agenda." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
</form>

<script>
(function () {
    var form = document.getElementById('agenda-bulk-form');
    if (!form) {
        return;
    }

    var selectAll = form.querySelector('[data-select-all]');
    var counter = form.querySelector('[data-selected-count]');

    function refreshCount() {
        if (counter) {
            counter.textContent = form.querySelectorAll('[data-select-item]:checked').length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            form.querySelectorAll('[data-select-item]').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            refreshCount();
        });
    }

    form.querySelectorAll('[data-select-item]').forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshCount);
    });

    refreshCount();
})();
</script>
@endsection
