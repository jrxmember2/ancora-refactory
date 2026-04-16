@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Condôminos" subtitle="Proprietários e locatários vinculados às unidades, separados de parceiros e fornecedores." />
@include('pages.clientes.partials.subnav')

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar condômino..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <select name="vinculo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os vínculos</option>
            <option value="proprietario" @selected(($filters['vinculo'] ?? '') === 'proprietario')>Proprietários</option>
            <option value="locatario" @selected(($filters['vinculo'] ?? '') === 'locatario')>Locatários</option>
        </select>
        <div class="flex gap-3">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('clientes.condominos') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-people-roof" title="Sem condôminos" subtitle="Nenhum proprietário ou locatário vinculado a unidades foi encontrado." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4"><x-ancora.sort-link field="name" label="Nome" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="role" label="Vínculo" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="document" label="Documento" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4">Unidades</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        @php
                            $owned = $item->ownedUnits ?? collect();
                            $rented = $item->rentedUnits ?? collect();
                            $unitLinks = $owned->map(fn ($unit) => ['type' => 'Proprietário', 'unit' => $unit])
                                ->merge($rented->map(fn ($unit) => ['type' => 'Locatário', 'unit' => $unit]));
                            $address = $item->primary_address_json ?? [];
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->display_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->legal_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @if(($item->owned_units_count ?? 0) > 0)
                                        <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Proprietário</span>
                                    @endif
                                    @if(($item->rented_units_count ?? 0) > 0)
                                        <span class="rounded-full bg-warning-50 px-3 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">Locatário</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->cpf_cnpj ?: '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">
                                @forelse($unitLinks->take(3) as $link)
                                    @php($unit = $link['unit'])
                                    <div>{{ $link['type'] }} · {{ $unit->condominium?->name ?: 'Condomínio' }} · Unidade {{ $unit->unit_number }}</div>
                                @empty
                                    <span class="text-gray-500 dark:text-gray-400">—</span>
                                @endforelse
                                @if($unitLinks->count() > 3)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">+ {{ $unitLinks->count() - 3 }} vínculo(s)</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="document.getElementById('view-condomino-{{ $item->id }}').showModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Visualizar</button>
                                    <a href="{{ route('clientes.contatos.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                </div>
                                <dialog id="view-condomino-{{ $item->id }}" class="fixed inset-0 m-auto w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
                                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $item->display_name }}</h3>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ ($item->owned_units_count ?? 0) }} como proprietário · {{ ($item->rented_units_count ?? 0) }} como locatário</p>
                                            </div>
                                            <button type="button" onclick="document.getElementById('view-condomino-{{ $item->id }}').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs dark:border-gray-700">Fechar</button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2 text-sm">
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">CPF / CNPJ</span><div class="mt-1 text-gray-900 dark:text-white">{{ $item->cpf_cnpj ?: '—' }}</div></div>
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Telefone(s)</span><div class="mt-1 text-gray-900 dark:text-white">{{ collect($item->phones_json ?? [])->pluck('number')->filter()->implode(', ') ?: '—' }}</div></div>
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">E-mail(s)</span><div class="mt-1 text-gray-900 dark:text-white">{{ collect($item->emails_json ?? [])->pluck('email')->filter()->implode(', ') ?: '—' }}</div></div>
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Endereço</span><div class="mt-1 text-gray-900 dark:text-white">{{ collect([$address['street'] ?? null, $address['number'] ?? null, $address['neighborhood'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $address['zip'] ?? null])->filter()->implode(', ') ?: '—' }}</div></div>
                                        <div class="md:col-span-2">
                                            <span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Unidades vinculadas</span>
                                            <div class="mt-2 space-y-2 text-gray-900 dark:text-white">
                                                @foreach($unitLinks as $link)
                                                    @php($unit = $link['unit'])
                                                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                                                        {{ $link['type'] }} · {{ $unit->condominium?->name ?: 'Condomínio não informado' }} · {{ $unit->block?->name ? $unit->block->name.' · ' : '' }}Unidade {{ $unit->unit_number }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </dialog>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection
