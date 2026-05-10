@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
@endphp

@section('content')
<div class="space-y-6" x-data="{ activeProvider: '{{ old('ai_active_provider', $settings['ai_active_provider']) }}' }">
    <x-ancora.section-header title="Inteligencia Artificial" subtitle="Configuracao central de provedores, modelos e parametros padrao para toda a camada de IA do sistema." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para Configuracoes</span>
        </a>
    </div>

    <form id="ai-config-form" method="post" action="{{ route('config.ai.save') }}" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Camada central de IA</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Toda integracao futura deve passar pelo `AiService`, sem chamadas diretas a OpenAI ou Gemini.</p>
                        </div>
                        <button type="button" onclick="testAiConnection(this)" data-test-url="{{ route('config.ai.test') }}" class="{{ $softButtonClass }} inline-flex items-center justify-center gap-2 whitespace-nowrap">
                            <i class="fa-solid fa-plug-circle-check"></i>
                            <span>Testar conexao</span>
                        </button>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="ai_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('ai_enabled', $settings['ai_enabled'] ? 1 : 0))>
                            <span>
                                <span class="block font-medium">IA ativa</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Controla se a camada central de IA pode ser usada pelas funcionalidades futuras do sistema.</span>
                            </span>
                        </label>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Provedor ativo</label>
                            <select name="ai_active_provider" x-model="activeProvider" class="{{ $inputClass }}">
                                <option value="openai">OpenAI</option>
                                <option value="gemini">Gemini</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Temperatura padrao</label>
                            <input type="number" step="0.1" min="0" max="2" name="ai_default_temperature" value="{{ old('ai_default_temperature', $settings['ai_default_temperature']) }}" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Maximo de tokens por resposta</label>
                            <input type="number" min="1" max="32768" name="ai_default_max_tokens" value="{{ old('ai_default_max_tokens', $settings['ai_default_max_tokens']) }}" class="{{ $inputClass }}">
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Prompt global padrao</label>
                            <textarea name="ai_default_system_prompt" rows="6" class="{{ $textareaClass }}" placeholder="Defina o comportamento global base da IA.">{{ old('ai_default_system_prompt', $settings['ai_default_system_prompt']) }}</textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Aviso juridico padrao</label>
                            <textarea name="ai_default_legal_notice" rows="4" class="{{ $textareaClass }}" placeholder="Ex.: Esta resposta tem carater informativo e nao substitui analise juridica individual.">{{ old('ai_default_legal_notice', $settings['ai_default_legal_notice']) }}</textarea>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Link padrao para solicitacao de orcamento</label>
                            <input type="url" name="ai_default_budget_request_url" value="{{ old('ai_default_budget_request_url', $settings['ai_default_budget_request_url']) }}" class="{{ $inputClass }}" placeholder="https://...">
                        </div>

                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="ai_old_document_alert_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('ai_old_document_alert_enabled', $settings['ai_old_document_alert_enabled'] ? 1 : 0))>
                            <span>
                                <span class="block font-medium">Alerta de documento antigo ativo</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Mantem a parametrizacao pronta para avisos quando documentos ultrapassarem a idade definida.</span>
                            </span>
                        </label>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantidade de anos para considerar documento antigo</label>
                            <input type="number" min="1" max="100" name="ai_old_document_alert_years" value="{{ old('ai_old_document_alert_years', $settings['ai_old_document_alert_years']) }}" class="{{ $inputClass }}">
                        </div>
                    </div>
                </div>

                <div :class="activeProvider === 'openai' ? 'border-brand-300 shadow-brand-500/5' : 'border-gray-200'" class="rounded-2xl border bg-white p-6 shadow-theme-xs transition dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">OpenAI</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configuracao do provedor OpenAI para chat e, futuramente, embeddings.</p>
                        </div>
                        <span :class="activeProvider === 'openai' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">Provedor ativo</span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200 md:col-span-2">
                            <input type="checkbox" name="openai_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('openai_enabled', $settings['openai_enabled'] ? 1 : 0))>
                            <span>
                                <span class="block font-medium">Status OpenAI ativo</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Se desligado, o provedor nao sera usado mesmo que esteja selecionado como ativo.</span>
                            </span>
                        </label>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">API Key OpenAI</label>
                            <input type="password" name="openai_api_key" value="" placeholder="{{ $settings['openai_has_api_key'] ? 'Chave atual: ' . $settings['openai_api_key_masked'] . ' - preencha apenas para trocar' : 'Cole a API Key da OpenAI aqui' }}" class="{{ $inputClass }}">
                            @if($settings['openai_has_api_key'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Chave salva: {{ $settings['openai_api_key_masked'] }}</p>
                            @endif
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo de chat OpenAI</label>
                            <input name="openai_chat_model" value="{{ old('openai_chat_model', $settings['openai_chat_model']) }}" class="{{ $inputClass }}" placeholder="gpt-4.1-mini">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo de embedding OpenAI</label>
                            <input name="openai_embedding_model" value="{{ old('openai_embedding_model', $settings['openai_embedding_model']) }}" class="{{ $inputClass }}" placeholder="Opcional por enquanto">
                        </div>
                    </div>
                </div>

                <div :class="activeProvider === 'gemini' ? 'border-brand-300 shadow-brand-500/5' : 'border-gray-200'" class="rounded-2xl border bg-white p-6 shadow-theme-xs transition dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Gemini</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configuracao do provedor Gemini para chat e, futuramente, embeddings.</p>
                        </div>
                        <span :class="activeProvider === 'gemini' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">Provedor ativo</span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200 md:col-span-2">
                            <input type="checkbox" name="gemini_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('gemini_enabled', $settings['gemini_enabled'] ? 1 : 0))>
                            <span>
                                <span class="block font-medium">Status Gemini ativo</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Se desligado, o provedor nao sera usado mesmo que esteja selecionado como ativo.</span>
                            </span>
                        </label>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">API Key Gemini</label>
                            <input type="password" name="gemini_api_key" value="" placeholder="{{ $settings['gemini_has_api_key'] ? 'Chave atual: ' . $settings['gemini_api_key_masked'] . ' - preencha apenas para trocar' : 'Cole a API Key da Gemini aqui' }}" class="{{ $inputClass }}">
                            @if($settings['gemini_has_api_key'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Chave salva: {{ $settings['gemini_api_key_masked'] }}</p>
                            @endif
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo de chat Gemini</label>
                            <input name="gemini_chat_model" value="{{ old('gemini_chat_model', $settings['gemini_chat_model']) }}" class="{{ $inputClass }}" placeholder="gemini-2.5-flash">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Modelo de embedding Gemini</label>
                            <input name="gemini_embedding_model" value="{{ old('gemini_embedding_model', $settings['gemini_embedding_model']) }}" class="{{ $inputClass }}" placeholder="Opcional por enquanto">
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo rapido</h3>
                    <div class="mt-5 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">IA ativa</div>
                            <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ old('ai_enabled', $settings['ai_enabled'] ? 1 : 0) ? 'Sim' : 'Nao' }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Provedor principal</div>
                            <div class="mt-1 font-medium text-gray-900 dark:text-white" x-text="activeProvider === 'gemini' ? 'Gemini' : 'OpenAI'"></div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Chaves cadastradas</div>
                            <div class="mt-1 font-medium text-gray-900 dark:text-white">
                                {{ ($settings['openai_has_api_key'] ? 1 : 0) + ($settings['gemini_has_api_key'] ? 1 : 0) }} provedor(es)
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-dashed border-brand-300 bg-brand-50/60 p-6 shadow-theme-xs dark:border-brand-800 dark:bg-brand-500/5">
                    <h3 class="text-base font-semibold text-brand-900 dark:text-brand-100">Boas praticas desta fase</h3>
                    <ul class="mt-4 space-y-2 text-sm text-brand-900/80 dark:text-brand-100/80">
                        <li>As API Keys ficam criptografadas em `app_settings`.</li>
                        <li>O sistema nao exibe a chave completa depois de salva.</li>
                        <li>OpenAI e Gemini passam por um servico central unico.</li>
                        <li>O teste de conexao usa os valores atuais da tela.</li>
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <button class="{{ $buttonClass }}">Salvar configuracoes de IA</button>
                    <button type="button" onclick="testAiConnection(this)" data-test-url="{{ route('config.ai.test') }}" class="{{ $softButtonClass }}">Testar conexao agora</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
async function testAiConnection(button) {
    const form = document.getElementById('ai-config-form');
    const url = button.dataset.testUrl;
    if (!form || !url) {
        return;
    }

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Testando...';

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => null);

        if (!response.ok || !data?.success) {
            const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
            throw new Error(firstError || data?.message || 'Nao foi possivel testar a conexao agora.');
        }

        showAiToast(data.message || 'Conexao validada com sucesso.', 'success');
    } catch (error) {
        showAiToast(error?.message || 'Erro ao testar a conexao.', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

function showAiToast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `fixed right-6 top-6 z-[999999] rounded-2xl px-4 py-3 text-sm font-medium shadow-theme-lg ${type === 'error' ? 'bg-error-500 text-white' : 'bg-success-500 text-white'}`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
}
</script>
@endpush
