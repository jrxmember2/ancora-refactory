@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Cadastro de clientes" subtitle="Cadastro central para clientes avulsos, parceiros, fornecedores, condôminos, condomínios, blocos e unidades.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('clientes.avulsos') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-users"></i> Avulsos</a>
        <a href="{{ route('clientes.condominios') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600"><i class="fa-solid fa-building"></i> Condomínios</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Entidades" :value="$entityCounts['total']" hint="Registros reutilizáveis do módulo." icon="fa-solid fa-address-book" />
    <x-ancora.stat-card label="Avulsos" :value="$entityCounts['avulsos_total']" hint="Pessoas físicas e jurídicas fora da árvore condominial." icon="fa-solid fa-user" />
    <x-ancora.stat-card label="Condôminos" :value="$entityCounts['condominos_total'] ?? 0" hint="Proprietários, locatários e inquilinos." icon="fa-solid fa-people-roof" />
    <x-ancora.stat-card label="Condomínios" :value="$condominiumCounts['total']" :hint="$condominiumCounts['with_blocks_total'].' com blocos/torres'" icon="fa-solid fa-building" />
    <x-ancora.stat-card label="Unidades" :value="$unitCounts['total']" :hint="$unitCounts['rented_total'].' com locatário'" icon="fa-solid fa-door-open" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Fluxo rápido</h3>
        <div class="mt-4 grid gap-4">
            <a href="{{ route('clientes.avulsos') }}" class="rounded-2xl border border-gray-200 px-5 py-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10"><strong class="block text-gray-900 dark:text-white">Clientes avulsos</strong><span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">PF ou PJ com timeline, anexos e observações.</span></a>
            <a href="{{ route('clientes.contatos') }}" class="rounded-2xl border border-gray-200 px-5 py-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10"><strong class="block text-gray-900 dark:text-white">Parceiros e fornecedores</strong><span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Síndicos, administradoras e imobiliária/corretor.</span></a>
            <a href="{{ route('clientes.condominos') }}" class="rounded-2xl border border-gray-200 px-5 py-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10"><strong class="block text-gray-900 dark:text-white">Condôminos</strong><span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Proprietários e locatários separados da base de parceiros.</span></a>
            <a href="{{ route('clientes.condominios') }}" class="rounded-2xl border border-gray-200 px-5 py-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10"><strong class="block text-gray-900 dark:text-white">Condomínios</strong><span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Árvore condominial com blocos, síndico e banco.</span></a>
            <a href="{{ route('clientes.unidades') }}" class="rounded-2xl border border-gray-200 px-5 py-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10"><strong class="block text-gray-900 dark:text-white">Unidades</strong><span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">Vínculo com proprietário e locatário reutilizáveis.</span></a>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Cadastros recentes</h3>
        <div class="mt-4 space-y-4">
            @forelse($recentEntities as $entity)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">{{ $entity->display_name }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($entity->profile_scope) }} · {{ strtoupper($entity->entity_type) }}</div>
                    </div>
                    <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $entity->role_tag }}</span>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-users" title="Sem registros" subtitle="Ainda não há entidades cadastradas." />
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Condomínios recentes</h3>
        <div class="mt-4 space-y-4">
            @forelse($recentCondominiums as $condominium)
                <div class="rounded-2xl border border-gray-200 px-4 py-4 dark:border-gray-800">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $condominium->name }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tipo: {{ $condominium->condominium_type_name ?? 'Não definido' }}</div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-building" title="Sem condomínios" subtitle="Ainda não há condomínios cadastrados." />
            @endforelse
        </div>
    </div>
</div>
@endsection
