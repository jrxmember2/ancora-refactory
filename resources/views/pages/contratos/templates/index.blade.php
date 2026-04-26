@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Templates" subtitle="Modelos base para contratos, termos, aditivos, distratos e notificações.">
    <a href="{{ route('contratos.templates.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo template</a>
</x-ancora.section-header>

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-layer-group" title="Sem templates" subtitle="Cadastre o primeiro modelo para começar a gerar contratos." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Nome</th>
                        <th class="px-6 py-4">Tipo</th>
                        <th class="px-6 py-4">Categoria</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->description ?: 'Sem descrição' }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->document_type }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->category?->name ?: '—' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->is_active ? 'Ativo' : 'Inativo' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('contratos.templates.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <form method="post" action="{{ route('contratos.templates.delete', $item) }}">@csrf @method('DELETE')<button onclick="return confirm('Excluir este template?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button></form>
                                </div>
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
