@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Contratos" subtitle="Gerencie contratos, termos, aditivos, distratos e demais documentos vinculados ao escritorio.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('contratos.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo contrato</a>
        <a href="{{ route('contratos.templates.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Templates</a>
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar por codigo, titulo ou tipo..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <select name="client_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os clientes</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((int) $filters['client_id'] === (int) $client->id)>{{ $client->display_name }}</option>
            @endforeach
        </select>
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os condominios</option>
            @foreach($condominiums as $condominium)
                <option value="{{ $condominium->id }}" @selected((int) $filters['condominium_id'] === (int) $condominium->id)>{{ $condominium->name }}</option>
            @endforeach
        </select>
        <select name="type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os tipos</option>
            @foreach($typeOptions as $type)
                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <select name="category_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todas as categorias</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === (int) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os status</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="start_from" value="{{ $filters['start_from'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100" title="Inicio da vigencia a partir de">
        <input type="date" name="start_to" value="{{ $filters['start_to'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100" title="Inicio da vigencia ate">
        <input type="date" name="end_from" value="{{ $filters['end_from'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100" title="Termino a partir de">
        <input type="date" name="end_to" value="{{ $filters['end_to'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100" title="Termino ate">
        <select name="responsible_user_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os responsaveis</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) $filters['responsible_user_id'] === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="expired_only" value="1" @checked($filters['expired_only'])> Vencidos</label>
        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="upcoming_only" value="1" @checked($filters['upcoming_only'])> Proximos do vencimento</label>
        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="without_pdf_only" value="1" @checked($filters['without_pdf_only'])> Sem PDF</label>
        <div class="flex gap-3 xl:col-span-4">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-file-contract" title="Sem contratos" subtitle="Nenhum contrato foi encontrado com os filtros informados." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4"><x-ancora.sort-link field="code" label="Codigo" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="title" label="Titulo" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="client" label="Cliente" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="condominium" label="Condominio" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="type" label="Tipo" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="value" label="Valor" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="start" label="Inicio" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="end" label="Termino" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="status" label="Status" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="responsible" label="Responsavel" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-6 py-4 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $item->code ?: '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->category?->name ?: 'Sem categoria' }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->client?->display_name ?: '-' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->condominium?->name ?: '-' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->type }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">R$ {{ number_format((float) ($item->contract_value ?? $item->monthly_value ?? $item->total_value ?? 0), 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ optional($item->start_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->indefinite_term ? 'Prazo indeterminado' : (optional($item->end_date)->format('d/m/Y') ?: '-') }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $statusLabels[$item->status] ?? $item->status }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->responsible?->name ?: '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('contratos.show', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Visualizar</a>
                                    <a href="{{ route('contratos.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <a href="{{ route('contratos.show', ['contrato' => $item, 'tab' => 'historico']) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Historico</a>
                                    <form method="post" action="{{ route('contratos.duplicate', $item) }}">@csrf<button class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Duplicar</button></form>
                                    @if($item->final_pdf_path)
                                        <a href="{{ route('contratos.download-pdf', $item) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">PDF</a>
                                    @elseif($item->template_id && $item->content_html)
                                        <form method="post" action="{{ route('contratos.generate-pdf', $item) }}">@csrf<button class="rounded-lg border border-success-300 px-3 py-2 text-xs font-medium text-success-700 dark:border-success-800 dark:text-success-300">Gerar PDF</button></form>
                                    @endif
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
