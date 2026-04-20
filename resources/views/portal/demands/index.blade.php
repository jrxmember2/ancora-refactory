@extends('portal.layouts.app')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Solicitações</p>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">Minhas solicitações</h1>
        <p class="mt-2 text-sm text-gray-500">Acompanhe o histórico de atendimento com o escritório.</p>
    </div>
    @if($clientPortalUser->can_open_demands)
        <a href="{{ route('portal.demands.create') }}" class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Nova solicitação</a>
    @endif
</div>

<div class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-5 shadow-sm">
    <form method="get" class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar protocolo ou assunto..." class="h-12 rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415] md:col-span-2">
        <select name="status" class="h-12 rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
            <option value="">Todos os status</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Filtrar</button>
            <a href="{{ route('portal.demands.index') }}" class="rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 grid grid-cols-1 gap-4">
    @forelse($items as $item)
        <a href="{{ route('portal.demands.show', $item) }}" class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm transition hover:border-[#941415]/40">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="text-sm font-semibold text-[#941415]">{{ $item->protocol }}</div>
                    <div class="mt-1 text-lg font-semibold text-gray-950">{{ $item->subject }}</div>
                    <div class="mt-2 text-sm text-gray-500">{{ $item->category?->name ?: 'Sem categoria' }} · Atualizada em {{ $item->updated_at?->format('d/m/Y H:i') }}</div>
                </div>
                <span class="w-fit rounded-full bg-[#f7f2ec] px-3 py-1 text-xs font-semibold text-[#941415]">{{ $statusLabels[$item->status] ?? $item->status }}</span>
            </div>
        </a>
    @empty
        <div class="rounded-3xl border border-[#eadfd5] bg-white p-8 text-center text-gray-500">Nenhuma solicitação encontrada.</div>
    @endforelse
</div>

<div class="mt-6">{{ $items->links() }}</div>
@endsection
