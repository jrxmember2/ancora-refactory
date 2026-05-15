@extends('layouts.app')

@php
    $cardClass = 'rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
    $activeConversationId = $activeConversation?->id;
    $activeScopeType = $activeConversation?->scope_type ?: $selectedScopeType;
    $activeScopeLabel = $activeConversation
        ? $activeConversation->scopeLabel()
        : ($selectedScopeType === \App\Services\Ai\OfficeAiChatService::SCOPE_LEGAL_BASE ? 'Base Legal Global' : ($selectedCondominium?->name ?: 'Selecione um condominio'));
    $assistantMetaDocuments = static function ($message) {
        return collect($message->meta_json['documents'] ?? [])->take(4);
    };
@endphp

@push('head')
<style>
    .office-chat-shell{min-height:calc(100dvh - 18rem)}
    .office-chat-messages{scroll-behavior:smooth;overscroll-behavior:contain}
    .office-chat-composer{position:sticky;bottom:0;z-index:5}
    .office-chat-textarea{min-height:5rem;max-height:13rem;resize:none}
    .office-chat-sidepanel summary{list-style:none;cursor:pointer}
    .office-chat-sidepanel summary::-webkit-details-marker{display:none}
</style>
@endpush

@section('content')
<div class="space-y-6">
    <section class="overflow-hidden rounded-[2rem] bg-gradient-to-r from-brand-600 via-brand-500 to-[#941415] p-6 text-white shadow-2xl shadow-brand-500/20 sm:p-8">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/70">Leme Escritorio</p>
                <h1 class="mt-3 text-3xl font-semibold">A IA global do escritorio, com escopo controlado</h1>
                <p class="mt-2 max-w-3xl text-sm text-white/80 sm:text-base">
                    Consulte Base Legal Global ou entre no contexto de um condominio especifico sem misturar documentos de escopos diferentes.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('config.ai.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/20">
                    <i class="fa-solid fa-brain"></i>
                    <span>Configuracoes de IA</span>
                </a>
                <a href="{{ route('ia.office-chat.index') }}" class="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">
                    <i class="fa-solid fa-plus"></i>
                    <span>Novo chat</span>
                </a>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 2xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="{{ $cardClass }} overflow-hidden">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 id="officeChatTitle" class="text-lg font-semibold text-gray-900 dark:text-white">{{ $activeConversation?->displayTitle() ?: 'Nova conversa' }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $activeConversation ? 'Esta conversa permanece travada no escopo escolhido quando foi iniciada.' : 'Defina o escopo da consulta antes de enviar sua primeira pergunta.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span id="officeChatScopeBadge" class="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                            {{ $activeScopeLabel }}
                        </span>
                        <a href="{{ route('ia.office-chat.index') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-brand-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-brand-300 dark:hover:bg-white/[0.03]">
                            <i class="fa-solid fa-plus"></i>
                            <span>Novo chat</span>
                        </a>
                        @if($activeConversation)
                            <form method="post" action="{{ route('ia.office-chat.delete', $activeConversation) }}" onsubmit="return confirm('Excluir este chat da sua lista?');">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ route('ia.office-chat.index') }}">
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-error-200 bg-error-50 text-error-600 transition hover:bg-error-100 dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-300" title="Excluir chat">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            @if($chatDisabledReason)
                <div class="border-b border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300 sm:px-6">
                    {{ $chatDisabledReason }}
                </div>
            @endif

            @if($legalNotice)
                <div class="border-b border-gray-200 bg-gray-50 px-5 py-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300 sm:px-6">
                    <span class="font-semibold text-gray-900 dark:text-white">Aviso juridico:</span> {{ $legalNotice }}
                </div>
            @endif

            @if(!$activeConversation)
                <div class="border-b border-gray-200 bg-gray-50/70 px-5 py-4 dark:border-gray-800 dark:bg-gray-900/30 sm:px-6">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[14rem_minmax(0,1fr)]">
                        <div>
                            <label for="scopeTypeSelect" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Escopo</label>
                            <select id="scopeTypeSelect" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                @foreach($scopeOptions as $scopeKey => $scopeLabel)
                                    <option value="{{ $scopeKey }}" @selected($selectedScopeType === $scopeKey)>{{ $scopeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="condominiumSelectWrapper" @class([
                            'hidden' => $selectedScopeType === \App\Services\Ai\OfficeAiChatService::SCOPE_LEGAL_BASE,
                        ])>
                            <label for="condominiumSelect" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Condominio</label>
                            <select id="condominiumSelect" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="">Selecione um condominio</option>
                                @foreach($condominiums as $condominium)
                                    <option value="{{ $condominium->id }}" @selected((int) ($selectedCondominium?->id ?? 0) === (int) $condominium->id)>{{ $condominium->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif

            <div class="office-chat-shell flex flex-col">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/40 sm:px-6 2xl:hidden">
                    <details class="office-chat-sidepanel rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                        <summary class="flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">
                            <span><i class="fa-solid fa-clock-rotate-left mr-2 text-brand-500"></i>Historico recente</span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                        </summary>
                        <div class="space-y-3 border-t border-gray-200 px-3 py-3 dark:border-gray-800">
                            @forelse($recentConversations as $conversation)
                                @php($isActiveConversation = $activeConversation && (int) $activeConversation->id === (int) $conversation->id)
                                <div class="rounded-2xl border {{ $isActiveConversation ? 'border-brand-300 bg-brand-50/70 dark:border-brand-700 dark:bg-brand-500/10' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]' }} p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <a href="{{ route('ia.office-chat.show', $conversation) }}" class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $conversation->displayTitle() }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $conversation->scopeLabel() }} | {{ $conversation->last_message_at?->format('d/m/Y H:i') ?: $conversation->updated_at?->format('d/m/Y H:i') }}
                                            </div>
                                        </a>
                                        <form method="post" action="{{ route('ia.office-chat.delete', $conversation) }}" onsubmit="return confirm('Excluir este chat da sua lista?');">
                                            @csrf
                                            <input type="hidden" name="return_to" value="{{ $isActiveConversation ? route('ia.office-chat.index') : url()->current() }}">
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-error-200 bg-error-50 text-error-600 transition hover:bg-error-100 dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-300" title="Excluir chat">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-400">
                                    Nenhuma conversa registrada ainda.
                                </div>
                            @endforelse
                        </div>
                    </details>
                </div>

                <div id="officeChatMessages" class="office-chat-messages flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6">
                    @if($activeMessages->isEmpty())
                        <div id="officeEmptyState" class="rounded-3xl border border-dashed border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-800 dark:bg-gray-900/40">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500 text-white">
                                <i class="fa-solid fa-comments text-xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">A Leme esta pronta para apoiar o escritorio</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Escolha entre Base Legal Global ou um condominio especifico e envie perguntas objetivas para localizar os trechos mais relevantes.
                            </p>
                            <div class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                @foreach($sampleQuestions as $sampleQuestion)
                                    <button type="button" data-sample-question="{{ $sampleQuestion }}" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-left text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:border-brand-700">
                                        {{ $sampleQuestion }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @foreach($activeMessages as $message)
                            @php($isUserMessage = $message->role === 'user')
                            <div class="flex {{ $isUserMessage ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}">
                                <div class="max-w-[92%] rounded-3xl px-4 py-3 shadow-sm sm:max-w-[80%] {{ $isUserMessage ? 'bg-brand-500 text-white' : 'border border-gray-200 bg-gray-50 text-gray-800 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-100' }}">
                                    <div class="whitespace-pre-line text-sm leading-6">{{ $message->content }}</div>

                                    @if(!$isUserMessage && $assistantMetaDocuments($message)->isNotEmpty())
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($assistantMetaDocuments($message) as $document)
                                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                                    {{ $document['document_kind_label'] ?? ($document['title'] ?? 'Documento') }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="mt-3 text-[11px] {{ $isUserMessage ? 'text-white/70' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $message->created_at?->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="office-chat-composer border-t border-gray-200 bg-white/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
                    <div id="officeChatFeedback" class="mb-3 hidden rounded-2xl border px-4 py-3 text-sm"></div>

                    <form id="officeChatForm" method="post" action="{{ route('ia.office-chat.ask') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="conversation_id" id="officeConversationId" value="{{ $activeConversationId }}">
                        <input type="hidden" name="scope_type" id="officeScopeType" value="{{ $activeScopeType }}">
                        <input type="hidden" name="client_condominium_id" id="officeCondominiumId" value="{{ (int) ($activeConversation?->client_condominium_id ?: $selectedCondominium?->id ?: 0) }}">

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <label for="officeChatQuestion" class="sr-only">Sua pergunta</label>
                            <textarea
                                id="officeChatQuestion"
                                name="question"
                                rows="2"
                                maxlength="4000"
                                placeholder="Digite sua pergunta para a Leme Escritorio..."
                                class="office-chat-textarea w-full rounded-2xl border border-gray-300 bg-white px-4 py-4 text-sm text-gray-900 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 {{ $chatCanSubmit ? '' : 'cursor-not-allowed bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-500' }}"
                                enterkeyhint="send"
                                autocomplete="off"
                                @disabled(!$chatCanSubmit)
                            >{{ old('question') }}</textarea>

                            <button
                                type="submit"
                                id="officeChatSubmitButton"
                                class="inline-flex h-14 items-center justify-center rounded-2xl bg-brand-500 px-6 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-gray-300"
                                @disabled(!$chatCanSubmit)
                            >
                                <i class="fa-solid fa-paper-plane mr-2"></i>Enviar
                            </button>
                        </div>

                        <div class="flex flex-col gap-2 text-xs text-gray-500 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                            <div>Use perguntas objetivas e escolha o escopo correto antes de iniciar um novo chat.</div>
                            <div class="font-medium text-brand-600 dark:text-brand-300">
                                {{ $activeScopeLabel }}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <aside class="hidden 2xl:block 2xl:space-y-6">
            <section class="{{ $cardClass }} p-5">
                <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-300">Contexto atual</h3>
                <dl class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Escopo</dt>
                        <dd class="mt-1">{{ $activeScopeLabel }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Modo</dt>
                        <dd class="mt-1">{{ $activeScopeType === \App\Services\Ai\OfficeAiChatService::SCOPE_LEGAL_BASE ? 'Base Legal Global' : 'Condominio especifico + Base Legal Global' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Historico</dt>
                        <dd class="mt-1">{{ $recentConversations->count() }} conversa(s) recente(s)</dd>
                    </div>
                </dl>
            </section>

            <section class="{{ $cardClass }} p-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-300">Historico recente</h3>
                    <a href="{{ route('ia.office-chat.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200">Novo</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse($recentConversations as $conversation)
                        @php($isActiveConversation = $activeConversation && (int) $activeConversation->id === (int) $conversation->id)
                        <div class="rounded-2xl border {{ $isActiveConversation ? 'border-brand-300 bg-brand-50/70 dark:border-brand-700 dark:bg-brand-500/10' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]' }} p-4">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ route('ia.office-chat.show', $conversation) }}" class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $conversation->displayTitle() }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $conversation->scopeLabel() }} | {{ $conversation->last_message_at?->format('d/m/Y H:i') ?: $conversation->updated_at?->format('d/m/Y H:i') }}
                                    </div>
                                </a>
                                <form method="post" action="{{ route('ia.office-chat.delete', $conversation) }}" onsubmit="return confirm('Excluir este chat da sua lista?');">
                                    @csrf
                                    <input type="hidden" name="return_to" value="{{ $isActiveConversation ? route('ia.office-chat.index') : url()->current() }}">
                                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-error-200 bg-error-50 text-error-600 transition hover:bg-error-100 dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-300" title="Excluir chat">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-400">
                            Nenhuma conversa registrada ainda.
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('officeChatForm');
    const textarea = document.getElementById('officeChatQuestion');
    const submitButton = document.getElementById('officeChatSubmitButton');
    const messages = document.getElementById('officeChatMessages');
    const feedback = document.getElementById('officeChatFeedback');
    const conversationIdInput = document.getElementById('officeConversationId');
    const scopeTypeHidden = document.getElementById('officeScopeType');
    const condominiumIdHidden = document.getElementById('officeCondominiumId');
    const scopeTypeSelect = document.getElementById('scopeTypeSelect');
    const condominiumSelect = document.getElementById('condominiumSelect');
    const condominiumSelectWrapper = document.getElementById('condominiumSelectWrapper');
    const emptyState = document.getElementById('officeEmptyState');
    const scopeBadge = document.getElementById('officeChatScopeBadge');
    const titleEl = document.getElementById('officeChatTitle');
    let isSubmitting = false;

    const hasLockedConversation = () => conversationIdInput.value.trim() !== '';

    const truncate = (value, limit = 90) => {
        const stringValue = String(value || '').trim();
        return stringValue.length > limit ? stringValue.slice(0, limit - 3) + '...' : stringValue;
    };

    const autoResizeTextarea = () => {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 208)}px`;
    };

    const scrollToBottom = () => {
        window.requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    };

    const scrollComposerIntoView = () => {
        window.requestAnimationFrame(() => {
            form.scrollIntoView({ block: 'end', behavior: 'smooth' });
        });
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const setFeedback = (message, level = 'error') => {
        if (!message) {
            feedback.className = 'mb-3 hidden rounded-2xl border px-4 py-3 text-sm';
            feedback.textContent = '';
            return;
        }

        feedback.className = level === 'success'
            ? 'mb-3 rounded-2xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-900/40 dark:bg-success-950/30 dark:text-success-300'
            : 'mb-3 rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300';
        feedback.textContent = message;
    };

    const renderDocuments = (documents) => {
        if (!Array.isArray(documents) || documents.length === 0) {
            return '';
        }

        return `
            <div class="mt-3 flex flex-wrap gap-2">
                ${documents.slice(0, 4).map((document) => `
                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        ${escapeHtml(document.document_kind_label || document.title || 'Documento')}
                    </span>
                `).join('')}
            </div>
        `;
    };

    const renderMessage = (message) => {
        const isUser = message.role === 'user';
        const wrapperClass = isUser ? 'justify-end' : 'justify-start';
        const bubbleClass = isUser
            ? 'bg-brand-500 text-white'
            : 'border border-gray-200 bg-gray-50 text-gray-800 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-100';
        const timestampClass = isUser ? 'text-white/70' : 'text-gray-500 dark:text-gray-400';

        return `
            <div class="flex ${wrapperClass}" data-message-id="${escapeHtml(message.id || '')}">
                <div class="max-w-[92%] rounded-3xl px-4 py-3 shadow-sm sm:max-w-[80%] ${bubbleClass}">
                    <div class="whitespace-pre-line text-sm leading-6">${escapeHtml(message.content || '')}</div>
                    ${isUser ? '' : renderDocuments(message.documents || [])}
                    <div class="mt-3 text-[11px] ${timestampClass}">${escapeHtml(message.created_at || '')}</div>
                </div>
            </div>
        `;
    };

    const appendHtml = (html) => {
        messages.insertAdjacentHTML('beforeend', html);
        if (emptyState) {
            emptyState.remove();
        }
        scrollToBottom();
    };

    const setSubmitting = (submitting) => {
        isSubmitting = submitting;
        submitButton.disabled = submitting || submitButton.dataset.locked === '1';
        textarea.disabled = submitting || textarea.dataset.locked === '1';
        submitButton.innerHTML = submitting
            ? '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Consultando...'
            : '<i class="fa-solid fa-paper-plane mr-2"></i>Enviar';
    };

    const currentScopeLabel = () => {
        if ((scopeTypeHidden.value || '') === 'legal_base') {
            return 'Base Legal Global';
        }

        if (condominiumSelect && condominiumSelect.selectedOptions.length > 0 && condominiumSelect.selectedOptions[0].value) {
            return condominiumSelect.selectedOptions[0].textContent.trim();
        }

        return 'Selecione um condominio';
    };

    const syncScopeState = () => {
        if (!scopeTypeSelect) {
            return;
        }

        scopeTypeHidden.value = scopeTypeSelect.value || 'condominium';
        const useLegalBase = scopeTypeHidden.value === 'legal_base';

        if (condominiumSelectWrapper) {
            condominiumSelectWrapper.classList.toggle('hidden', useLegalBase);
        }

        if (useLegalBase) {
            condominiumIdHidden.value = '';
        } else if (condominiumSelect) {
            condominiumIdHidden.value = condominiumSelect.value || '';
        }

        if (scopeBadge) {
            scopeBadge.textContent = currentScopeLabel();
        }
    };

    const lockScopeSelectors = () => {
        if (scopeTypeSelect) {
            scopeTypeSelect.disabled = true;
        }

        if (condominiumSelect) {
            condominiumSelect.disabled = true;
        }
    };

    const validateScope = () => {
        if ((scopeTypeHidden.value || '') === 'legal_base') {
            return true;
        }

        if ((condominiumIdHidden.value || '').trim() === '') {
            setFeedback('Selecione um condominio antes de enviar a pergunta.');
            condominiumSelect?.focus();
            return false;
        }

        return true;
    };

    document.querySelectorAll('[data-sample-question]').forEach((button) => {
        button.addEventListener('click', () => {
            if (textarea.disabled) {
                return;
            }

            textarea.value = button.getAttribute('data-sample-question') || '';
            autoResizeTextarea();
            textarea.focus();
            scrollComposerIntoView();
        });
    });

    if (scopeTypeSelect) {
        scopeTypeSelect.addEventListener('change', syncScopeState);
    }

    if (condominiumSelect) {
        condominiumSelect.addEventListener('change', syncScopeState);
    }

    textarea.addEventListener('input', autoResizeTextarea);
    textarea.addEventListener('focus', () => {
        autoResizeTextarea();
        scrollComposerIntoView();
    });

    syncScopeState();
    if (hasLockedConversation()) {
        lockScopeSelectors();
    }

    autoResizeTextarea();
    scrollToBottom();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting || submitButton.disabled) {
            return;
        }

        const question = textarea.value.trim();
        if (!question) {
            setFeedback('Digite uma pergunta antes de enviar.');
            textarea.focus();
            return;
        }

        if (!validateScope()) {
            return;
        }

        const formData = new FormData(form);
        formData.set('question', question);
        formData.set('scope_type', scopeTypeHidden.value || 'condominium');
        formData.set('client_condominium_id', condominiumIdHidden.value || '');

        setFeedback('');
        setSubmitting(true);

        const tempUserId = 'temp-user-' + Date.now();
        const tempAssistantId = 'temp-assistant-' + Date.now();

        appendHtml(renderMessage({
            id: tempUserId,
            role: 'user',
            content: question,
            created_at: 'enviando...',
            documents: [],
        }));

        appendHtml(`
            <div class="flex justify-start" data-message-id="${tempAssistantId}">
                <div class="max-w-[92%] rounded-3xl border border-gray-200 bg-gray-50 px-4 py-3 text-gray-800 shadow-sm sm:max-w-[80%] dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-100">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-brand-500 animate-pulse"></span>
                        A Leme esta analisando sua pergunta e cruzando os documentos relevantes...
                    </div>
                </div>
            </div>
        `);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const payload = await response.json();

            document.querySelector(`[data-message-id="${tempUserId}"]`)?.remove();
            document.querySelector(`[data-message-id="${tempAssistantId}"]`)?.remove();

            if (!response.ok || !payload.ok) {
                setFeedback(payload.message || 'Nao foi possivel concluir sua consulta agora.');
                scrollToBottom();
                return;
            }

            payload.messages.forEach((message) => appendHtml(renderMessage(message)));

            if (payload.conversation_id) {
                conversationIdInput.value = payload.conversation_id;
                lockScopeSelectors();
            }

            if (payload.conversation_url) {
                window.history.replaceState({}, '', payload.conversation_url);
            }

            if (payload.scope && payload.scope.label && scopeBadge) {
                scopeBadge.textContent = payload.scope.label;
            }

            if (titleEl && titleEl.textContent.trim() === 'Nova conversa') {
                titleEl.textContent = truncate(question);
            }

            textarea.value = '';
            autoResizeTextarea();
            setFeedback('Resposta gerada com sucesso.', 'success');
            scrollToBottom();
        } catch (error) {
            document.querySelector(`[data-message-id="${tempUserId}"]`)?.remove();
            document.querySelector(`[data-message-id="${tempAssistantId}"]`)?.remove();
            setFeedback('Nao foi possivel concluir sua consulta agora. Tente novamente em instantes.');
            scrollToBottom();
        } finally {
            setSubmitting(false);
        }
    });
});
</script>
@endpush
@endsection
