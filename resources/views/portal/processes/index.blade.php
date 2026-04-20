@extends('portal.layouts.app')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Processos</p>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">Acompanhamento processual</h1>
        <p class="mt-2 text-sm text-gray-500">Consulta segura com apenas informações liberadas ao cliente.</p>
    </div>
</div>

<div class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-5 shadow-sm">
    <form method="get" class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar processo, parte..." class="h-12 rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415] md:col-span-2">
        <select name="status_option_id" class="h-12 rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
            <option value="">Todos os status</option>
            @foreach($statuses as $status)
                <option value="{{ $status->id }}" @selected((int) ($filters['status_option_id'] ?? 0) === (int) $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Filtrar</button>
            <a href="{{ route('portal.processes.index') }}" class="rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 grid grid-cols-1 gap-4">
    @forelse($items as $item)
        <a href="{{ route('portal.processes.show', $item) }}" class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm transition hover:border-[#941415]/40">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-gray-950">{{ $item->process_number ?: 'Processo #' . $item->id }}</div>
                    <div class="mt-2 text-sm text-gray-500">{{ $item->processTypeOption?->name ?: 'Tipo não informado' }} · {{ $item->actionTypeOption?->name ?: 'Ação não informada' }}</div>
                    <div class="mt-3 text-sm text-gray-600">Última atualização: {{ $item->last_public_phase_at ? \Illuminate\Support\Carbon::parse($item->last_public_phase_at)->format('d/m/Y') : $item->updated_at?->format('d/m/Y') }}</div>
                </div>
                @php($statusColor = $item->statusOption?->color_hex ?: '#6B7280')
                <span class="w-fit rounded-full px-3 py-1 text-xs font-semibold text-white" style="background-color: {{ $statusColor }}">{{ $item->statusOption?->name ?: 'Sem status' }}</span>
            </div>
        </a>
    @empty
        <div class="rounded-3xl border border-[#eadfd5] bg-white p-8 text-center text-gray-500">Nenhum processo disponível para sua conta.</div>
    @endforelse
</div>

<div class="mt-6">{{ $items->links() }}</div>
@endsection
