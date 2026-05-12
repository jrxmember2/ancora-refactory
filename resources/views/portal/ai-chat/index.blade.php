@extends('portal.layouts.app')

@php
    $cardClass = 'rounded-3xl border border-[#eadfd5] bg-white shadow-sm';
    $activeConversationId = $activeConversation?->id;
    $activeCondominiumName = $activeCondominium?->name;
    $usageMessage = $usageStatus['message'] ?? '';
    $commercialAlertDocuments = collect($commercialAlert['documents'] ?? []);
    $assistantMetaDocuments = static function ($message) {
        return collect($message->meta_json['documents'] ?? [])->take(4);
    };
@endphp

@push('head')
<style>
    .portal-chat-shell{min-height:calc(var(--portal-vh,100dvh) - 15rem)}
    .portal-chat-messages{scroll-behavior:smooth;overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
    .portal-chat-composer{position:sticky;bottom:0;z-index:5}
    .portal-chat-textarea{min-height:4.75rem;max-height:11rem;resize:none}
    .portal-chat-send{width:100%;min-height:3.75rem}
    .portal-chat-sidepanel summary{list-style:none;cursor:pointer}
    .portal-chat-sidepanel summary::-webkit-details-marker{display:none}
    @media (max-width: 1023px){
        .portal-chat-shell{min-height:calc(var(--portal-vh,100dvh) - 12rem)}
    }
    @media (min-width: 640px){
        .portal-chat-send{width:auto;min-width:10.625rem}
    }
</style>
@endpush

@section('content')
<section class="rounded-[2rem] bg-[#941415] p-6 text-white shadow-xl shadow-[#941415]/20 sm:p-8">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/70">Leme</p>
            <h1 class="mt-3 text-3xl font-semibold">A IA que ajuda o usuario a pilotar a gestao</h1>
            <p class="mt-2 max-w-3xl text-sm text-white/80 sm:text-base">
                Consulte Convencao, Regimento, ATAs e Base Legal Global com o mesmo contexto seguro do seu condominio.
            </p>
        </div>
        <div class="flex flex-col gap-3 lg:items-end">
            <a href="{{ route('portal.ai-chat.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/20 bg-white px-4 py-3 text-sm font-semibold text-[#941415] transition hover:bg-[#fdf1f1]">
                <i class="fa-solid fa-plus mr-2"></i>Novo chat
            </a>
            <div class="rounded-3xl border border-white/20 bg-white/10 px-5 py-4 text-sm text-white/90">
                <div class="font-semibold">Condominio em foco</div>
                <div class="mt-1">{{ $activeCondominiumName ?: 'Selecione um condominio para iniciar.' }}</div>
            </div>
        </div>
    </div>
</section>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <section class="{{ $cardClass }} overflow-hidden">
        <div class="border-b border-[#eadfd5] px-5 py-4 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950">{{ $activeConversation?->displayTitle() ?: 'Novo chat' }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $usageMessage !== '' ? $usageMessage : 'Envie uma pergunta para consultar a base documental do condominio com a Leme.' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($activeConversation && $activeCondominiumName)
                        <span class="inline-flex w-fit rounded-full bg-[#f7f2ec] px-3 py-1 text-xs font-semibold text-[#941415]">{{ $activeCondominiumName }}</span>
                    @endif
                    <a href="{{ route('portal.ai-chat.index') }}" class="inline-flex items-center justify-center rounded-full border border-[#eadfd5] bg-white px-3 py-1.5 text-xs font-semibold text-[#941415] transition hover:border-[#941415]/40 hover:bg-[#fdf8f4]">
                        <i class="fa-solid fa-plus mr-1.5"></i>Novo chat
                    </a>
                </div>
            </div>
        </div>

        @if($conversationUsesDifferentCondominium && $activeCondominiumName)
            <div class="border-b border-warning-200 bg-warning-50 px-5 py-4 text-sm text-warning-700 sm:px-6">
                Esta conversa usa o contexto de <strong>{{ $activeCondominiumName }}</strong>. Se quiser trocar para o condominio selecionado no topo, clique em <strong>Novo chat</strong>.
            </div>
        @endif

        @if($chatDisabledReason)
            <div class="border-b border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 sm:px-6">
                {{ $chatDisabledReason }}
            </div>
        @endif

        @if($legalNotice)
            <div class="border-b border-[#eadfd5] bg-[#fdf8f4] px-5 py-4 text-xs text-gray-600 sm:px-6">
                <span class="font-semibold text-[#941415]">Aviso juridico:</span> {{ $legalNotice }}
            </div>
        @endif

        @if($commercialAlert)
            <div class="border-b border-[#f3d9b4] bg-[#fff7ed] px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-sm font-semibold text-[#9a3412]">
                            <i class="fa-solid fa-file-shield"></i>
                            <span>Revisao documental recomendada</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-[#7c2d12]">
                            {{ $commercialAlert['message'] }}
                        </p>

                        @if($commercialAlertDocuments->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($commercialAlertDocuments as $document)
                                    <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-[#9a3412]">
                                        {{ $document['label'] ?? 'Documento' }}{{ !empty($document['date_br']) ? ' | ' . $document['date_br'] : '' }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if(!empty($commercialAlert['action_url']))
                        <a
                            href="{{ $commercialAlert['action_url'] }}"
                            class="inline-flex shrink-0 items-center justify-center rounded-2xl bg-[#941415] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#7e1111]"
                            @if(!empty($commercialAlert['action_target_blank'])) target="_blank" rel="noopener noreferrer" @endif
                        >
                            <i class="fa-solid fa-file-signature mr-2"></i>{{ $commercialAlert['button_label'] ?? 'Solicitar orcamento de atualizacao' }}
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="portal-chat-shell flex flex-col">
            <div class="border-b border-[#eadfd5] bg-[#fcfaf7] px-4 py-3 sm:px-6 xl:hidden">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <details class="portal-chat-sidepanel rounded-2xl border border-[#eadfd5] bg-white">
                        <summary class="flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-gray-900">
                            <span><i class="fa-solid fa-clock-rotate-left mr-2 text-[#941415]"></i>Historico recente</span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                        </summary>
                        <div class="border-t border-[#eadfd5] px-3 py-3">
                            <div class="space-y-3">
                                @forelse($recentConversations as $conversation)
                                    <a href="{{ route('portal.ai-chat.show', $conversation) }}" class="block rounded-2xl border px-4 py-3 transition {{ $activeConversation && (int) $activeConversation->id === (int) $conversation->id ? 'border-[#941415] bg-[#fdf2f2]' : 'border-[#eadfd5] bg-white hover:border-[#941415]/40' }}">
                                        <div class="text-sm font-semibold text-gray-900">{{ $conversation->displayTitle() }}</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $conversation->condominium?->name ?: 'Sem condominio' }} | {{ $conversation->last_message_at?->format('d/m/Y H:i') ?: $conversation->updated_at?->format('d/m/Y H:i') }}
                                        </div>
                                    </a>
                                @empty
                                    <div class="rounded-2xl border border-dashed border-[#eadfd5] bg-[#fdf8f4] px-4 py-4 text-sm text-gray-500">
                                        Nenhuma conversa registrada ainda.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </details>

                    <details class="portal-chat-sidepanel rounded-2xl border border-[#eadfd5] bg-white">
                        <summary class="flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-gray-900">
                            <span><i class="fa-solid fa-compass mr-2 text-[#941415]"></i>Contexto atual</span>
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                        </summary>
                        <div class="border-t border-[#eadfd5] px-4 py-4">
                            <dl class="space-y-3 text-sm text-gray-600">
                                <div>
                                    <dt class="font-semibold text-gray-900">Condominio</dt>
                                    <dd class="mt-1">{{ $activeCondominiumName ?: 'Nenhum selecionado' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-900">Base documental</dt>
                                    <dd class="mt-1">{{ $hasKnowledgeBase ? 'Pronta para consulta' : 'Aguardando processamento' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-gray-900">Consultas neste mes</dt>
                                    <dd class="mt-1">{{ $usageMessage !== '' ? $usageMessage : 'Sem informacao de consumo.' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </details>
                </div>
            </div>

            <div id="chatMessages" class="portal-chat-messages flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6">
                @if($activeMessages->isEmpty())
                    <div id="emptyState" class="rounded-3xl border border-dashed border-[#eadfd5] bg-[#fdf8f4] p-6 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#941415] text-white">
                            <i class="fa-solid fa-comments text-xl"></i>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-950">A Leme esta pronta para ajudar na gestao do condominio</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Pergunte sobre regras da Convencao, do Regimento, ATAs recentes ou fundamentos da Base Legal Global.
                        </p>

                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach($sampleQuestions as $sampleQuestion)
                                <button type="button" data-sample-question="{{ $sampleQuestion }}" class="rounded-2xl border border-[#eadfd5] bg-white px-4 py-3 text-left text-sm font-medium text-gray-700 transition hover:border-[#941415]/40 hover:text-[#941415]">
                                    {{ $sampleQuestion }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @else
                    @foreach($activeMessages as $message)
                        @php($isUserMessage = $message->role === 'user')
                        <div class="flex {{ $isUserMessage ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}">
                            <div class="max-w-[92%] rounded-3xl px-4 py-3 shadow-sm sm:max-w-[80%] {{ $isUserMessage ? 'bg-[#941415] text-white' : 'border border-[#eadfd5] bg-[#fdf8f4] text-gray-800' }}">
                                <div class="whitespace-pre-line text-sm leading-6">{{ $message->content }}</div>

                                @if(!$isUserMessage && $assistantMetaDocuments($message)->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($assistantMetaDocuments($message) as $document)
                                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-[#941415]">
                                                {{ $document['document_kind_label'] ?? ($document['title'] ?? 'Documento') }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-3 text-[11px] {{ $isUserMessage ? 'text-white/70' : 'text-gray-500' }}">
                                    {{ $message->created_at?->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="portal-chat-composer border-t border-[#eadfd5] bg-white/95 px-4 py-4 backdrop-blur sm:px-6" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                <div id="chatFeedback" class="mb-3 hidden rounded-2xl border px-4 py-3 text-sm"></div>

                <form id="chatForm" method="post" action="{{ route('portal.ai-chat.ask') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="conversation_id" id="conversationId" value="{{ $activeConversationId }}">

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label for="chatQuestion" class="sr-only">Sua pergunta</label>
                        <textarea
                            id="chatQuestion"
                            name="question"
                            rows="2"
                            maxlength="4000"
                            placeholder="Digite sua pergunta para a Leme sobre Convencao, Regimento, ATAs ou Base Legal Global..."
                            class="portal-chat-textarea w-full rounded-2xl border border-gray-200 px-4 py-4 text-sm text-gray-900 outline-none transition focus:border-[#941415] focus:ring-4 focus:ring-[#941415]/10 {{ $chatCanSubmit ? '' : 'cursor-not-allowed bg-gray-100 text-gray-500' }}"
                            enterkeyhint="send"
                            autocomplete="off"
                            @disabled(!$chatCanSubmit)
                        >{{ old('question') }}</textarea>

                        <button
                            type="submit"
                            id="chatSubmitButton"
                            class="portal-chat-send inline-flex h-14 items-center justify-center rounded-2xl bg-[#941415] px-6 text-sm font-semibold text-white transition hover:bg-[#7e1111] disabled:cursor-not-allowed disabled:bg-gray-300"
                            @disabled(!$chatCanSubmit)
                        >
                            <i class="fa-solid fa-paper-plane mr-2"></i>Enviar
                        </button>
                    </div>

                    <div class="flex flex-col gap-2 text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            Use perguntas objetivas para ajudar a Leme a localizar os trechos mais relevantes.
                        </div>
                        <div id="usageStatusText" class="font-medium text-[#941415]">
                            {{ $usageMessage }}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <aside class="hidden xl:block xl:space-y-6">
        <section class="{{ $cardClass }} p-5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-[#941415]">Contexto atual</h3>
            <dl class="mt-4 space-y-3 text-sm text-gray-600">
                <div>
                    <dt class="font-semibold text-gray-900">Condominio</dt>
                    <dd class="mt-1">{{ $activeCondominiumName ?: 'Nenhum selecionado' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-900">Base documental</dt>
                    <dd class="mt-1">{{ $hasKnowledgeBase ? 'Pronta para consulta' : 'Aguardando processamento' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-900">Consultas neste mes</dt>
                    <dd class="mt-1">{{ $usageMessage !== '' ? $usageMessage : 'Sem informacao de consumo.' }}</dd>
                </div>
            </dl>
        </section>

        <section class="{{ $cardClass }} p-5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-[#941415]">Historico recente da Leme</h3>
            <div class="mt-4 space-y-3">
                @forelse($recentConversations as $conversation)
                    <a href="{{ route('portal.ai-chat.show', $conversation) }}" class="block rounded-2xl border px-4 py-3 transition {{ $activeConversation && (int) $activeConversation->id === (int) $conversation->id ? 'border-[#941415] bg-[#fdf2f2]' : 'border-[#eadfd5] bg-white hover:border-[#941415]/40' }}">
                        <div class="text-sm font-semibold text-gray-900">{{ $conversation->displayTitle() }}</div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ $conversation->condominium?->name ?: 'Sem condominio' }} | {{ $conversation->last_message_at?->format('d/m/Y H:i') ?: $conversation->updated_at?->format('d/m/Y H:i') }}
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#eadfd5] bg-[#fdf8f4] px-4 py-4 text-sm text-gray-500">
                        Nenhuma conversa registrada ainda.
                    </div>
                @endforelse
            </div>
        </section>
    </aside>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('chatForm');
    const textarea = document.getElementById('chatQuestion');
    const submitButton = document.getElementById('chatSubmitButton');
    const messages = document.getElementById('chatMessages');
    const feedback = document.getElementById('chatFeedback');
    const conversationIdInput = document.getElementById('conversationId');
    const usageStatusText = document.getElementById('usageStatusText');
    const emptyState = document.getElementById('emptyState');
    let isSubmitting = false;

    const autoResizeTextarea = () => {
        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 176)}px`;
    };

    const scrollComposerIntoView = () => {
        window.requestAnimationFrame(() => {
            form.scrollIntoView({ block: 'end', behavior: 'smooth' });
        });
    };

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const scrollToBottom = () => {
        window.requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    };

    const setFeedback = (message, level = 'error') => {
        if (!message) {
            feedback.className = 'mb-3 hidden rounded-2xl border px-4 py-3 text-sm';
            feedback.textContent = '';
            return;
        }

        const palette = level === 'success'
            ? 'mb-3 rounded-2xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700'
            : 'mb-3 rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700';

        feedback.className = palette;
        feedback.textContent = message;
    };

    const renderDocuments = (documents) => {
        if (!Array.isArray(documents) || documents.length === 0) {
            return '';
        }

        return `
            <div class="mt-3 flex flex-wrap gap-2">
                ${documents.slice(0, 4).map((document) => `
                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-[#941415]">
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
            ? 'bg-[#941415] text-white'
            : 'border border-[#eadfd5] bg-[#fdf8f4] text-gray-800';
        const timestampClass = isUser ? 'text-white/70' : 'text-gray-500';

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
        submitButton.setAttribute('aria-busy', submitting ? 'true' : 'false');
        submitButton.innerHTML = submitting
            ? '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Consultando...'
            : '<i class="fa-solid fa-paper-plane mr-2"></i>Enviar';
    };

    const lockFormPermanently = () => {
        submitButton.dataset.locked = '1';
        textarea.dataset.locked = '1';
        submitButton.disabled = true;
        textarea.disabled = true;
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

    textarea.addEventListener('input', autoResizeTextarea);
    textarea.addEventListener('focus', () => {
        autoResizeTextarea();
        scrollComposerIntoView();
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            if (document.activeElement === textarea) {
                scrollComposerIntoView();
                scrollToBottom();
            }
        });
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

        const formData = new FormData(form);
        formData.set('question', question);

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
                <div class="max-w-[92%] rounded-3xl border border-[#eadfd5] bg-[#fdf8f4] px-4 py-3 text-gray-800 shadow-sm sm:max-w-[80%]">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[#941415] animate-pulse"></span>
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
            }

            if (payload.conversation_url) {
                window.history.replaceState({}, '', payload.conversation_url);
            }

            if (payload.usage_status && payload.usage_status.message) {
                usageStatusText.textContent = payload.usage_status.message;
                if (!payload.usage_status.allowed) {
                    lockFormPermanently();
                }
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
