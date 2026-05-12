@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
@endphp

@section('content')
<div class="space-y-6">
    <x-ancora.section-header title="Historico de Consultas" subtitle="Auditoria das consultas da Leme com filtros por usuario, condominio, modelo, status e periodo." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.ai.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para IA</span>
        </a>
        <a href="{{ route('config.ai.legal-base.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Base Legal Global</span>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Consultas filtradas</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Erros registrados</div>
            <div class="mt-2 text-2xl font-semibold text-error-600 dark:text-error-300">{{ number_format($summary['errors'] ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Consultas marcadas</div>
            <div class="mt-2 text-2xl font-semibold text-brand-600 dark:text-brand-300">{{ number_format($summary['flagged'] ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="get" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Condominio</label>
                <select name="client_condominium_id" class="{{ $inputClass }}">
                    <option value="">Todos</option>
                    @foreach($condominiums as $condominium)
                        <option value="{{ $condominium->id }}" @selected((int) ($filters['client_condominium_id'] ?? 0) === (int) $condominium->id)>{{ $condominium->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario do portal</label>
                <select name="client_portal_user_id" class="{{ $inputClass }}">
                    <option value="">Todos</option>
                    @foreach($portalUsers as $portalUser)
                        <option value="{{ $portalUser->id }}" @selected((int) ($filters['client_portal_user_id'] ?? 0) === (int) $portalUser->id)>
                            {{ $portalUser->name }} - {{ $portalUser->displayClientName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Provedor</label>
                <select name="provider" class="{{ $inputClass }}">
                    <option value="">Todos</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ strtoupper($provider) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo</label>
                <select name="model" class="{{ $inputClass }}">
                    <option value="">Todos</option>
                    @foreach($models as $model)
                        <option value="{{ $model }}" @selected(($filters['model'] ?? '') === $model)>{{ $model }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status" class="{{ $inputClass }}">
                    <option value="">Todos</option>
                    @foreach($statusOptions as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected(($filters['status'] ?? '') === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periodo de</label>
                <input type="date" name="period_from" value="{{ $filters['period_from'] ?? '' }}" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Periodo ate</label>
                <input type="date" name="period_to" value="{{ $filters['period_to'] ?? '' }}" class="{{ $inputClass }}">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Palavra-chave</label>
                <input type="search" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="Pergunta, resposta, documento..." class="{{ $inputClass }}">
            </div>

            <div class="flex items-end gap-2">
                <button class="{{ $buttonClass }}">Filtrar</button>
                <a href="{{ route('config.ai.chat-history.index') }}" class="{{ $softButtonClass }}">Limpar</a>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Consulta</th>
                        <th class="px-6 py-4">Condominio / Usuario</th>
                        <th class="px-6 py-4">Modelo</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Marcacoes</th>
                        <th class="px-6 py-4 text-right">Detalhe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($items as $item)
                        @php($portalUser = $item->conversation?->portalUser)
                        @php($condominium = $item->conversation?->condominium)
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->created_at?->format('d/m/Y H:i') }}</div>
                                <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ \Illuminate\Support\Str::limit($item->questionText(), 110) ?: 'Pergunta nao localizada' }}</div>
                                <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($item->content, 160) }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $condominium?->name ?: 'Sem condominio' }}</div>
                                <div class="mt-1">{{ $portalUser?->name ?: 'Usuario nao localizado' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $portalUser?->login_key ?: '-' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                <div class="font-medium">{{ strtoupper((string) ($item->provider ?: '-')) }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->model ?: 'Modelo nao informado' }}</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Tokens: {{ $item->resolvedTokensTotal() !== null ? number_format($item->resolvedTokensTotal(), 0, ',', '.') : 'n/d' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $item->status === 'error' ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300' : 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' }}">
                                    {{ $item->status === 'error' ? 'Erro' : 'Sucesso' }}
                                </span>
                                @if($item->errorText() !== '')
                                    <div class="mt-2 text-xs text-error-600 dark:text-error-300">{{ \Illuminate\Support\Str::limit($item->errorText(), 90) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex max-w-xs flex-wrap gap-2">
                                    @if($item->is_relevant)
                                        <span class="rounded-full bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-300">Relevante</span>
                                    @endif
                                    @if($item->requires_legal_review)
                                        <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">Requer analise juridica</span>
                                    @endif
                                    @if($item->is_faq_candidate)
                                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Candidata a FAQ</span>
                                    @endif
                                    @if(!$item->is_relevant && !$item->requires_legal_review && !$item->is_faq_candidate)
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">Sem marcacao</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top text-right">
                                <a href="{{ route('config.ai.chat-history.show', $item) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <span>Ver detalhe</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6">
                                <x-ancora.empty-state icon="fa-solid fa-comments" title="Nenhuma consulta encontrada" subtitle="Ajuste os filtros ou aguarde novas consultas da Leme no Portal do Cliente." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    </div>
</div>
@endsection
