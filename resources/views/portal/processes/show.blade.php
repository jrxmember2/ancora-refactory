@extends('portal.layouts.app')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <a href="{{ route('portal.processes.index') }}" class="text-sm font-semibold text-[#941415]">Voltar aos processos</a>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">{{ $case->process_number ?: 'Processo #' . $case->id }}</h1>
        <p class="mt-2 text-sm text-gray-500">Informações públicas e resumidas do processo.</p>
    </div>
    @php($statusColor = $case->statusOption?->color_hex ?: '#6B7280')
    <span class="w-fit rounded-full px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ $statusColor }}">{{ $case->statusOption?->name ?: 'Sem status' }}</span>
</div>

<section class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-3">
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Tipo</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $case->processTypeOption?->name ?: 'Não informado' }}</div>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Natureza</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $case->natureOption?->name ?: 'Não informada' }}</div>
    </div>
    <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Tribunal DataJud</div>
        <div class="mt-2 font-semibold text-gray-950">{{ $case->datajud_court ?: 'Não configurado' }}</div>
    </div>
</section>

<section class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-gray-950">Andamentos liberados</h2>
    <div class="mt-5 space-y-4">
        @forelse($case->phases as $phase)
            <div class="rounded-2xl border border-gray-100 p-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div class="font-semibold text-gray-950">{{ $phase->description }}</div>
                    <div class="text-sm text-gray-500">{{ $phase->phase_date?->format('d/m/Y') ?: $phase->created_at?->format('d/m/Y') }}</div>
                </div>
                <div class="mt-2 text-xs font-semibold uppercase tracking-[0.14em] text-[#941415]">{{ $phase->source === 'datajud' ? 'Movimentação DataJud' : 'Andamento informado pelo escritório' }}</div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Ainda não há andamentos públicos liberados para este processo.</p>
        @endforelse
    </div>
</section>
@endsection
