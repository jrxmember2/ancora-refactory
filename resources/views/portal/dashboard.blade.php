@extends('portal.layouts.app')

@php
    $cardClass = 'rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm';
    $portalContextLabel = isset($clientPortalSelectedCondominium) && $clientPortalSelectedCondominium
        ? $clientPortalSelectedCondominium->name
        : $portalUser->displayClientName();
@endphp

@section('content')
<section class="rounded-[2rem] bg-[#941415] p-8 text-white shadow-xl shadow-[#941415]/20">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/70">Bem-vindo(a)</p>
            <h1 class="mt-3 text-3xl font-semibold">{{ $portalUser->name }}</h1>
            <p class="mt-2 max-w-2xl text-white/80">Voce esta visualizando a area segura de {{ $portalContextLabel }}.</p>
        </div>
        @if($portalUser->can_open_demands)
            <a href="{{ route('portal.demands.create') }}" class="inline-flex rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-[#941415]">Abrir solicitação</a>
        @endif
    </div>
</section>

<section class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    @if($portalUser->can_view_processes)
    <div class="{{ $cardClass }}">
        <div class="text-sm text-gray-500">Processos ativos</div>
        <div class="mt-3 text-3xl font-semibold text-gray-950">{{ $summary['processes_active'] }}</div>
        <a href="{{ route('portal.processes.index') }}" class="mt-4 inline-flex text-sm font-semibold text-[#941415]">Ver processos</a>
    </div>
    @endif
    @if($portalUser->can_view_cobrancas)
    <div class="{{ $cardClass }}">
        <div class="text-sm text-gray-500">Cobranças em andamento</div>
        <div class="mt-3 text-3xl font-semibold text-gray-950">{{ $summary['cobrancas_active'] }}</div>
        <a href="{{ route('portal.cobrancas.index') }}" class="mt-4 inline-flex text-sm font-semibold text-[#941415]">Ver cobranças</a>
    </div>
    @endif
    @if($portalUser->can_view_demands)
    <div class="{{ $cardClass }}">
        <div class="text-sm text-gray-500">Solicitações abertas</div>
        <div class="mt-3 text-3xl font-semibold text-gray-950">{{ $summary['demands_open'] }}</div>
        <a href="{{ route('portal.demands.index') }}" class="mt-4 inline-flex text-sm font-semibold text-[#941415]">Acompanhar</a>
    </div>
    <div class="{{ $cardClass }}">
        <div class="text-sm text-gray-500">Aguardando você</div>
        <div class="mt-3 text-3xl font-semibold text-gray-950">{{ $summary['demands_waiting_client'] }}</div>
        <a href="{{ route('portal.demands.index', ['status' => 'aguardando_cliente']) }}" class="mt-4 inline-flex text-sm font-semibold text-[#941415]">Responder pendências</a>
    </div>
    @elseif($portalUser->can_open_demands)
    <div class="{{ $cardClass }}">
        <div class="text-sm text-gray-500">Solicitações</div>
        <div class="mt-3 text-2xl font-semibold text-gray-950">Canal aberto</div>
        <a href="{{ route('portal.demands.create') }}" class="mt-4 inline-flex text-sm font-semibold text-[#941415]">Abrir solicitação</a>
    </div>
    @endif
</section>

<section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    @if($portalUser->can_view_processes)
    <div class="{{ $cardClass }}">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-gray-950">Processos recentes</h2>
            <a href="{{ route('portal.processes.index') }}" class="text-sm font-semibold text-[#941415]">Todos</a>
        </div>
        <div class="space-y-3">
            @forelse($latestProcesses as $item)
                <a href="{{ route('portal.processes.show', $item) }}" class="block rounded-2xl border border-gray-100 p-4 hover:border-[#941415]/30">
                    <div class="font-semibold text-gray-950">{{ $item->process_number ?: 'Processo #' . $item->id }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ $item->statusOption?->name ?: 'Sem status' }} · {{ $item->processTypeOption?->name ?: 'Tipo não informado' }}</div>
                </a>
            @empty
                <p class="text-sm text-gray-500">Nenhum processo disponível no portal.</p>
            @endforelse
        </div>
    </div>
    @endif

    @if($portalUser->can_view_cobrancas)
    <div class="{{ $cardClass }}">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-gray-950">Cobranças recentes</h2>
            <a href="{{ route('portal.cobrancas.index') }}" class="text-sm font-semibold text-[#941415]">Todas</a>
        </div>
        <div class="space-y-3">
            @forelse($latestCobrancas as $item)
                <a href="{{ route('portal.cobrancas.show', $item) }}" class="block rounded-2xl border border-gray-100 p-4 hover:border-[#941415]/30">
                    <div class="font-semibold text-gray-950">{{ $item->os_number }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ $item->block?->name ? $item->block->name.' · ' : '' }}Unidade {{ $item->unit?->unit_number ?? '-' }}</div>
                </a>
            @empty
                <p class="text-sm text-gray-500">Nenhuma cobrança disponível no portal.</p>
            @endforelse
        </div>
    </div>
    @endif

    @if($portalUser->can_view_demands)
    <div class="{{ $cardClass }}">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-gray-950">Solicitações</h2>
            <a href="{{ route('portal.demands.index') }}" class="text-sm font-semibold text-[#941415]">Todas</a>
        </div>
        <div class="space-y-3">
            @forelse($latestDemands as $item)
                <a href="{{ route('portal.demands.show', $item) }}" class="block rounded-2xl border border-gray-100 p-4 hover:border-[#941415]/30">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-semibold text-gray-950">{{ $item->protocol }}</div>
                        <span class="rounded-full bg-[#f7f2ec] px-2.5 py-1 text-xs font-semibold text-[#941415]">{{ $demandStatusLabels[$item->status] ?? $item->status }}</span>
                    </div>
                    <div class="mt-1 text-sm text-gray-500">{{ $item->subject }}</div>
                </a>
            @empty
                <p class="text-sm text-gray-500">Nenhuma solicitação aberta.</p>
            @endforelse
        </div>
    </div>
    @endif
</section>
@endsection
