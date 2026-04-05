@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Condomínios" subtitle="Cadastro da área condominial com tipo, endereço, síndico e banco.">
    <a href="{{ route('clientes.condominios.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo condomínio</a>
</x-ancora.section-header>
@include('pages.clientes.partials.subnav')

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar condomínio..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <div class="flex gap-3"><button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button><a href="{{ route('clientes.condominios') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a></div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count()===0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-building-circle-xmark" title="Sem condomínios" subtitle="Nenhum condomínio foi cadastrado ainda." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40"><tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400"><th class="px-6 py-4">Condomínio</th><th class="px-6 py-4">Tipo</th><th class="px-6 py-4">Síndico</th><th class="px-6 py-4">Blocos</th><th class="px-6 py-4 text-right">Ações</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        @php($address = $item->address_json ?? [])
                        <tr>
                            <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $item->name }}</div><div class="text-xs text-gray-500">{{ $item->cnpj ?: 'Sem CNPJ' }}</div></td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->type?->name ?: '—' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->syndic?->display_name ?: '—' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->has_blocks ? 'Sim' : 'Não' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="document.getElementById('view-condo-{{ $item->id }}').showModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Visualizar</button>
                                    <a href="{{ route('clientes.condominios.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <form method="post" action="{{ route('clientes.condominios.delete', $item) }}">@csrf @method('DELETE')<button onclick="return confirm('Excluir este condomínio?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button></form>
                                </div>
                                <dialog id="view-condo-{{ $item->id }}" class="fixed inset-0 m-auto w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
                                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800"><div class="flex items-center justify-between gap-3"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $item->name }}</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->type?->name ?: 'Tipo não definido' }}</p></div><button type="button" onclick="document.getElementById('view-condo-{{ $item->id }}').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs dark:border-gray-700">Fechar</button></div></div>
                                    <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2 text-sm">
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">CNPJ</span><div class="mt-1 text-gray-900 dark:text-white">{{ $item->cnpj ?: '—' }}</div></div>
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Síndico</span><div class="mt-1 text-gray-900 dark:text-white">{{ $item->syndic?->display_name ?: '—' }}</div></div>
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Administradora</span><div class="mt-1 text-gray-900 dark:text-white">{{ $item->administradora?->display_name ?: '—' }}</div></div>
                                        <div><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Status</span><div class="mt-1 text-gray-900 dark:text-white">{{ $item->is_active ? 'Ativo' : 'Inativo' }}</div></div>
                                        <div class="md:col-span-2"><span class="block text-xs uppercase tracking-[0.16em] text-gray-500">Endereço</span><div class="mt-1 text-gray-900 dark:text-white">{{ collect([$address['street'] ?? null, $address['number'] ?? null, $address['neighborhood'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $address['zip'] ?? null])->filter()->implode(', ') ?: '—' }}</div></div>
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
