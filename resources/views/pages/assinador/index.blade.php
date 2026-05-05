@extends('layouts.app')

@php
    $dateTime = fn ($value) => $value ? $value->format('d/m/Y H:i') : '-';
@endphp

@section('content')
<x-ancora.section-header title="Documentos do Assinador Eletronico" subtitle="Acompanhe assinaturas de contratos, cobrancas e documentos avulsos em um unico lugar.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('assinador.dashboard') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Dashboard</a>
        <a href="{{ route('assinador.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova assinatura</a>
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os status</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="origin" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todas as origens</option>
            <option value="contrato" @selected($filters['origin'] === 'contrato')>Contrato</option>
            <option value="cobranca" @selected($filters['origin'] === 'cobranca')>Cobranca / Termo de acordo</option>
            <option value="avulso" @selected($filters['origin'] === 'avulso')>Avulso</option>
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100" title="Data inicial">
        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100" title="Data final">
        <input type="search" name="document_name" value="{{ $filters['document_name'] }}" placeholder="Nome do documento..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <input type="search" name="signer" value="{{ $filters['signer'] }}" placeholder="Signatario..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <select name="created_by" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="0">Todos os usuarios</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) $filters['created_by'] === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-3 xl:col-span-1">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('assinador.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6">
            <x-ancora.empty-state icon="fa-solid fa-file-signature" title="Sem assinaturas" subtitle="Nenhum documento foi encontrado com os filtros informados." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Documento</th>
                        <th class="px-6 py-4">Origem</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Signatarios</th>
                        <th class="px-6 py-4">Criado por</th>
                        <th class="px-6 py-4">Enviado em</th>
                        <th class="px-6 py-4">Ultima sincronizacao</th>
                        <th class="px-6 py-4">Concluido em</th>
                        <th class="px-6 py-4 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        @php
                            $previewSigners = $item->signers->pluck('name')->filter()->take(2)->implode(', ');
                            $remainingSigners = max(0, $item->signers->count() - 2);
                            $redirectTo = request()->getRequestUri();
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->document_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->source_name }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">
                                <div>{{ $item->source_label }}</div>
                                @if($item->source_url)
                                    <a href="{{ $item->source_url }}" class="text-xs text-brand-600 dark:text-brand-300">Abrir origem</a>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $item->status_badge_class }}">
                                    {{ $statusLabels[$item->status] ?? $item->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">
                                <div>{{ $item->signers->count() }} pessoa(s)</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $previewSigners ?: '-' }}{{ $remainingSigners > 0 ? ' +' . $remainingSigners : '' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->creator?->name ?: '-' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $dateTime($item->created_at) }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $dateTime($item->last_synced_at) }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $dateTime($item->completed_at) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if($item->view_url)
                                        <a href="{{ $item->view_url }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Ver</a>
                                    @endif
                                    <form method="post" action="{{ route('assinador.signatures.sync', $item) }}">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
                                        <button class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Sincronizar</button>
                                    </form>
                                    <a href="{{ route('assinador.signatures.download', ['signature' => $item, 'artifact' => 'original', 'redirect_to' => $redirectTo]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Original</a>
                                    @if($item->signed_pdf_path || $item->status === 'certificated')
                                        <a href="{{ route('assinador.signatures.download', ['signature' => $item, 'artifact' => 'signed', 'redirect_to' => $redirectTo]) }}" class="rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white">Assinado</a>
                                    @endif
                                    @if($item->certificate_pdf_path || $item->status === 'certificated')
                                        <a href="{{ route('assinador.signatures.download', ['signature' => $item, 'artifact' => 'certificate', 'redirect_to' => $redirectTo]) }}" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-200">Certificado</a>
                                    @endif
                                    @if($item->bundle_pdf_path || $item->status === 'certificated')
                                        <a href="{{ route('assinador.signatures.download', ['signature' => $item, 'artifact' => 'bundle', 'redirect_to' => $redirectTo]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Pacote</a>
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
