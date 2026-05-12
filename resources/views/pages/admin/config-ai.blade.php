@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
@endphp

@section('content')
<div
    class="space-y-6"
    x-data="aiConfigPage({
        activeProvider: @js(old('ai_active_provider', $settings['ai_active_provider'])),
        openAiModel: @js(old('openai_chat_model', $settings['openai_chat_model'])),
        geminiModel: @js(old('gemini_chat_model', $settings['gemini_chat_model'])),
        temperature: @js((string) old('ai_default_temperature', $settings['ai_default_temperature'])),
        maxTokens: @js((int) old('ai_default_max_tokens', $settings['ai_default_max_tokens'])),
        temperatureMin: @js($catalog['temperature_min']),
        temperatureMax: @js($catalog['temperature_max']),
        temperatureStep: @js($catalog['temperature_step']),
        tokenMin: @js($catalog['token_min']),
        temperaturePresets: @js($catalog['temperature_presets']),
        tokenPresets: @js($catalog['token_presets']),
        openAiChatModels: @js($catalog['openai_chat_models']),
        geminiChatModels: @js($catalog['gemini_chat_models']),
    })"
>
    <x-ancora.section-header title="Inteligencia Artificial" subtitle="Configuracao central de provedores, modelos e parametros padrao para toda a camada de IA do sistema." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para Configuracoes</span>
        </a>
        <a href="{{ route('config.ai.chat-history.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-timeline"></i>
            <span>Historico de Consultas</span>
        </a>
        <a href="{{ route('config.ai.legal-base.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-scale-balanced"></i>
            <span>Base Legal Global</span>
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
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Toda integracao futura deve passar pelo <code>AiService</code>, sem chamadas diretas a OpenAI ou Gemini.</p>
                        </div>
                        <button type="button" onclick="testAiConnection(this)" data-test-url="{{ route('config.ai.test') }}" class="{{ $softButtonClass }} inline-flex items-center justify-center gap-2 whitespace-nowrap">
                            <i class="fa-solid fa-plug-circle-check"></i>
                            <span>Testar conexao</span>
                        </button>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200 md:col-span-2">
                            <input type="checkbox" name="ai_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('ai_enabled', $settings['ai_enabled'] ? 1 : 0))>
                            <span class="min-w-0">
                                <span class="flex items-center gap-2 font-medium">
                                    <span>IA ativa</span>
                                    <x-ancora.help-tip :text="$catalog['tooltips']['ai_enabled']" />
                                </span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Desligado: nada usa IA. Ligado: o sistema pode usar o provedor escolhido.</span>
                            </span>
                        </label>
                        @error('ai_enabled')
                            <p class="md:col-span-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror

                        <div class="md:col-span-2">
                            <div class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Provedor ativo</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['ai_active_provider']" />
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <label
                                    :class="activeProvider === 'openai' ? 'border-success-300 bg-success-50/70 dark:border-success-800 dark:bg-success-500/10' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]'"
                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition"
                                >
                                    <input type="radio" name="ai_active_provider" value="openai" x-model="activeProvider" @change="clampMaxTokens()" class="mt-1 border-gray-300 text-brand-500 focus:ring-brand-500">
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="font-medium text-gray-900 dark:text-white">OpenAI</span>
                                            <span :class="activeProvider === 'openai' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-300'" class="rounded-full px-3 py-1 text-xs font-semibold">
                                                <span x-text="activeProvider === 'openai' ? 'Ativo' : 'Inativo'"></span>
                                            </span>
                                        </span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Indicado quando voce quer o ecossistema GPT como provedor principal.</span>
                                    </span>
                                </label>

                                <label
                                    :class="activeProvider === 'gemini' ? 'border-success-300 bg-success-50/70 dark:border-success-800 dark:bg-success-500/10' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]'"
                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition"
                                >
                                    <input type="radio" name="ai_active_provider" value="gemini" x-model="activeProvider" @change="clampMaxTokens()" class="mt-1 border-gray-300 text-brand-500 focus:ring-brand-500">
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="font-medium text-gray-900 dark:text-white">Gemini</span>
                                            <span :class="activeProvider === 'gemini' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-300'" class="rounded-full px-3 py-1 text-xs font-semibold">
                                                <span x-text="activeProvider === 'gemini' ? 'Ativo' : 'Inativo'"></span>
                                            </span>
                                        </span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Indicado quando voce quer o ecossistema Gemini como provedor principal.</span>
                                    </span>
                                </label>
                            </div>
                            @error('ai_active_provider')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2 rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        <span>Temperatura padrao</span>
                                        <x-ancora.help-tip :text="$catalog['tooltips']['ai_default_temperature']" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Faixa configurada nesta tela: 0,0 a 2,0. Para este projeto juridico, o padrao sugerido continua em 0,20 para respostas mais controladas.</p>
                                </div>
                                <div class="w-full lg:w-32">
                                    <input x-model="temperature" type="number" step="{{ $catalog['temperature_step'] }}" min="{{ $catalog['temperature_min'] }}" max="{{ $catalog['temperature_max'] }}" name="ai_default_temperature" value="{{ old('ai_default_temperature', $settings['ai_default_temperature']) }}" class="{{ $inputClass }}">
                                </div>
                            </div>
                            <input x-model="temperature" type="range" min="{{ $catalog['temperature_min'] }}" max="{{ $catalog['temperature_max'] }}" step="{{ $catalog['temperature_step'] }}" class="mt-4 h-2 w-full cursor-pointer rounded-lg accent-brand-500">
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($catalog['temperature_presets'] as $preset)
                                    <button type="button" @click="applyTemperaturePreset({{ $preset }})" :class="isTemperaturePresetActive({{ $preset }}) ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'" class="rounded-full border px-3 py-1 text-xs font-medium transition">
                                        {{ number_format($preset, 2, ',', '.') }}
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                Leitura atual:
                                <span class="font-semibold text-gray-700 dark:text-gray-200" x-text="temperatureLabel()"></span>
                            </p>
                            <p x-show="activeProvider === 'gemini' && activeModelId().startsWith('gemini-3')" class="mt-2 text-xs text-warning-700 dark:text-warning-300">
                                Nos modelos Gemini 3, a documentacao oficial recomenda manter a temperatura em 1,00. Se voce escolher essa familia, vale testar com mais cuidado antes de baixar demais.
                            </p>
                            @error('ai_default_temperature')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2 rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        <span>Maximo de tokens por resposta</span>
                                        <x-ancora.help-tip :text="$catalog['tooltips']['ai_default_max_tokens']" />
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Minimo permitido: {{ number_format($catalog['token_min'], 0, ',', '.') }} tokens.
                                        Limite do modelo ativo:
                                        <span class="font-semibold text-gray-700 dark:text-gray-200" x-text="formatTokens(activeModelMaxTokens())"></span>.
                                    </p>
                                </div>
                                <div class="w-full lg:w-40">
                                    <input x-model.number="maxTokens" @blur="clampMaxTokens()" type="number" min="{{ $catalog['token_min'] }}" :max="activeModelMaxTokens()" name="ai_default_max_tokens" value="{{ old('ai_default_max_tokens', $settings['ai_default_max_tokens']) }}" class="{{ $inputClass }}">
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <template x-for="preset in tokenPresetOptions()" :key="preset">
                                    <button type="button" @click="maxTokens = preset" :class="Number(maxTokens) === Number(preset) ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'" class="rounded-full border px-3 py-1 text-xs font-medium transition" x-text="formatTokens(preset)"></button>
                                </template>
                            </div>
                            @error('ai_default_max_tokens')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Prompt global padrao</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['ai_default_system_prompt']" />
                            </label>
                            <textarea name="ai_default_system_prompt" rows="7" class="{{ $textareaClass }}" placeholder="Defina o comportamento global base da IA.">{{ old('ai_default_system_prompt', $settings['ai_default_system_prompt']) }}</textarea>
                            @error('ai_default_system_prompt')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Aviso juridico padrao</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['ai_default_legal_notice']" />
                            </label>
                            <textarea name="ai_default_legal_notice" rows="4" class="{{ $textareaClass }}" placeholder="Texto padrao de salvaguarda juridica.">{{ old('ai_default_legal_notice', $settings['ai_default_legal_notice']) }}</textarea>
                            @error('ai_default_legal_notice')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Link padrao para solicitacao de orcamento</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['ai_default_budget_request_url']" />
                            </label>
                            <input type="url" name="ai_default_budget_request_url" value="{{ old('ai_default_budget_request_url', $settings['ai_default_budget_request_url']) }}" class="{{ $inputClass }}" placeholder="https://...">
                            @error('ai_default_budget_request_url')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="ai_old_document_alert_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('ai_old_document_alert_enabled', $settings['ai_old_document_alert_enabled'] ? 1 : 0))>
                            <span class="min-w-0">
                                <span class="flex items-center gap-2 font-medium">
                                    <span>Alerta de documento antigo ativo</span>
                                    <x-ancora.help-tip :text="$catalog['tooltips']['ai_old_document_alert_enabled']" />
                                </span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Mantem pronta a regra que vai avisar quando documentos ficarem velhos para uso da IA.</span>
                            </span>
                        </label>

                        <div>
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Quantidade de anos para considerar documento antigo</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['ai_old_document_alert_years']" />
                            </label>
                            <input type="number" min="1" max="100" name="ai_old_document_alert_years" value="{{ old('ai_old_document_alert_years', $settings['ai_old_document_alert_years']) }}" class="{{ $inputClass }}">
                            @error('ai_old_document_alert_years')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div :class="activeProvider === 'openai' ? 'border-success-300 shadow-success-500/5 dark:border-success-800' : 'border-gray-200 dark:border-gray-800'" class="rounded-2xl border bg-white p-6 shadow-theme-xs transition dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">OpenAI</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configuracao do provedor OpenAI para chat e, futuramente, embeddings.</p>
                        </div>
                        <span :class="activeProvider === 'openai' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-300'" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                            <span x-text="activeProvider === 'openai' ? 'Ativo' : 'Inativo'"></span>
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>API Key OpenAI</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['openai_api_key']" />
                            </label>
                            <input type="password" name="openai_api_key" value="" placeholder="{{ $settings['openai_has_api_key'] ? 'Chave atual: ' . $settings['openai_api_key_masked'] . ' - preencha apenas para trocar' : 'Cole a API Key da OpenAI aqui' }}" class="{{ $inputClass }}">
                            @if($settings['openai_has_api_key'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Chave salva: {{ $settings['openai_api_key_masked'] }}</p>
                            @endif
                            @error('openai_api_key')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Modelo de chat OpenAI</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['openai_chat_model']" />
                            </label>
                            <select name="openai_chat_model" x-model="openAiModel" @change="clampMaxTokens()" class="{{ $inputClass }}">
                                @foreach($catalog['openai_chat_models'] as $model)
                                    <option value="{{ $model['id'] }}">
                                        {{ $model['name'] }}{{ !empty($model['recommended']) ? ' - recomendado' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Lista ordenada do mais basico ao mais forte. Saida maxima do modelo selecionado: <span x-text="formatTokens(modelMaxTokens('openai', openAiModel))"></span> tokens.</p>
                            @error('openai_chat_model')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Modelo de embedding OpenAI</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['openai_embedding_model']" />
                            </label>
                            <select name="openai_embedding_model" class="{{ $inputClass }}">
                                @foreach($catalog['openai_embedding_models'] as $model)
                                    <option value="{{ $model['id'] }}" @selected(old('openai_embedding_model', $settings['openai_embedding_model']) === $model['id'])>
                                        {{ $model['name'] }}{{ !empty($model['recommended']) ? ' - recomendado' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('openai_embedding_model')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div :class="activeProvider === 'gemini' ? 'border-success-300 shadow-success-500/5 dark:border-success-800' : 'border-gray-200 dark:border-gray-800'" class="rounded-2xl border bg-white p-6 shadow-theme-xs transition dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Gemini</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configuracao do provedor Gemini para chat e, futuramente, embeddings.</p>
                        </div>
                        <span :class="activeProvider === 'gemini' ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-300'" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">
                            <span x-text="activeProvider === 'gemini' ? 'Ativo' : 'Inativo'"></span>
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>API Key Gemini</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['gemini_api_key']" />
                            </label>
                            <input type="password" name="gemini_api_key" value="" placeholder="{{ $settings['gemini_has_api_key'] ? 'Chave atual: ' . $settings['gemini_api_key_masked'] . ' - preencha apenas para trocar' : 'Cole a API Key da Gemini aqui' }}" class="{{ $inputClass }}">
                            @if($settings['gemini_has_api_key'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Chave salva: {{ $settings['gemini_api_key_masked'] }}</p>
                            @endif
                            @error('gemini_api_key')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Modelo de chat Gemini</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['gemini_chat_model']" />
                            </label>
                            <select name="gemini_chat_model" x-model="geminiModel" @change="clampMaxTokens()" class="{{ $inputClass }}">
                                @foreach($catalog['gemini_chat_models'] as $model)
                                    <option value="{{ $model['id'] }}">
                                        {{ $model['name'] }}{{ !empty($model['recommended']) ? ' - recomendado' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Lista ordenada do mais basico ao mais forte. Saida maxima do modelo selecionado: <span x-text="formatTokens(modelMaxTokens('gemini', geminiModel))"></span> tokens.</p>
                            @error('gemini_chat_model')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <span>Modelo de embedding Gemini</span>
                                <x-ancora.help-tip :text="$catalog['tooltips']['gemini_embedding_model']" />
                            </label>
                            <select name="gemini_embedding_model" class="{{ $inputClass }}">
                                @foreach($catalog['gemini_embedding_models'] as $model)
                                    <option value="{{ $model['id'] }}" @selected(old('gemini_embedding_model', $settings['gemini_embedding_model']) === $model['id'])>
                                        {{ $model['name'] }}{{ !empty($model['recommended']) ? ' - recomendado' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gemini_embedding_model')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
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
                            <div class="mt-1 font-medium text-gray-900 dark:text-white" x-text="providerLabel()"></div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Modelo de chat ativo</div>
                            <div class="mt-1 font-medium text-gray-900 dark:text-white" x-text="activeModelName()"></div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Maximo configurado</div>
                            <div class="mt-1 font-medium text-gray-900 dark:text-white" x-text="formatTokens(Number(maxTokens)) + ' tokens'"></div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">O que e embedding?</h3>
                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">Embedding e uma forma de transformar texto em vetores numericos para comparar significado. Em pratica, isso serve para busca semantica, ranking de documentos, recuperacao de contexto e respostas mais bem fundamentadas.</p>
                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">Nesta fase o chat principal ainda nao depende disso para funcionar, mas ja deixamos o campo pronto e preconfigurado para a proxima etapa do modulo de IA.</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Base Legal Global</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">Aqui ficam documentos juridicos globais, como Codigo Civil, para compor a base compartilhada do Chat do Sindico.</p>
                        </div>
                        <i class="fa-solid fa-scale-balanced text-lg text-brand-500"></i>
                    </div>
                    <a href="{{ route('config.ai.legal-base.index') }}" class="{{ $softButtonClass }} mt-4 inline-flex items-center gap-2">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        <span>Abrir Base Legal Global</span>
                    </a>
                </div>

                <div class="rounded-2xl border border-dashed border-brand-300 bg-brand-50/60 p-6 shadow-theme-xs dark:border-brand-800 dark:bg-brand-500/5">
                    <h3 class="text-base font-semibold text-brand-900 dark:text-brand-100">Boas praticas desta fase</h3>
                    <ul class="mt-4 space-y-2 text-sm text-brand-900/80 dark:text-brand-100/80">
                        <li>As API Keys ficam criptografadas em <code>app_settings</code>.</li>
                        <li>So pode existir um provedor ativo por vez.</li>
                        <li>Os endpoints oficiais ja estao definidos internamente no sistema.</li>
                        <li>OpenAI e Gemini passam por um servico central unico.</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Endpoints internos</h3>
                    <div class="mt-4 space-y-3 text-xs text-gray-600 dark:text-gray-300">
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="font-semibold text-gray-900 dark:text-white">OpenAI</div>
                            <code class="mt-1 block break-all">https://api.openai.com/v1/responses</code>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="font-semibold text-gray-900 dark:text-white">Gemini</div>
                            <code class="mt-1 block break-all">https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent</code>
                        </div>
                    </div>
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
function aiConfigPage(config) {
    return {
        activeProvider: config.activeProvider || 'openai',
        openAiModel: config.openAiModel || '',
        geminiModel: config.geminiModel || '',
        temperature: String(config.temperature ?? '0.20'),
        maxTokens: Number(config.maxTokens || config.tokenMin || 64),
        temperatureMin: Number(config.temperatureMin || 0),
        temperatureMax: Number(config.temperatureMax || 2),
        temperatureStep: Number(config.temperatureStep || 0.05),
        tokenMin: Number(config.tokenMin || 64),
        temperaturePresets: config.temperaturePresets || [],
        tokenPresets: config.tokenPresets || [],
        openAiChatModels: config.openAiChatModels || [],
        geminiChatModels: config.geminiChatModels || [],

        init() {
            this.clampMaxTokens();
        },

        providerLabel() {
            return this.activeProvider === 'gemini' ? 'Gemini' : 'OpenAI';
        },

        activeModelId() {
            return this.activeProvider === 'gemini' ? this.geminiModel : this.openAiModel;
        },

        activeModelName() {
            const meta = this.findModel(this.activeProvider, this.activeModelId());
            return meta ? meta.name : this.activeModelId();
        },

        modelMaxTokens(provider, modelId) {
            const meta = this.findModel(provider, modelId);
            return Number(meta?.max_output_tokens || this.tokenMin);
        },

        activeModelMaxTokens() {
            return this.modelMaxTokens(this.activeProvider, this.activeModelId());
        },

        findModel(provider, modelId) {
            const list = provider === 'gemini' ? this.geminiChatModels : this.openAiChatModels;
            return list.find((item) => item.id === modelId) || null;
        },

        applyTemperaturePreset(value) {
            this.temperature = Number(value).toFixed(2);
        },

        isTemperaturePresetActive(value) {
            return Math.abs(Number(this.temperature) - Number(value)) < 0.001;
        },

        temperatureLabel() {
            const value = Number(this.temperature || 0);
            if (value <= 0.05) return 'Deterministica';
            if (value <= 0.25) return 'Segura para respostas juridicas e objetivas';
            if (value <= 0.50) return 'Equilibrada';
            if (value <= 0.85) return 'Mais natural';
            if (value <= 1.25) return 'Criativa';
            return 'Maxima variacao';
        },

        tokenPresetOptions() {
            const values = [...this.tokenPresets.filter((value) => Number(value) <= this.activeModelMaxTokens()), this.activeModelMaxTokens()];
            return [...new Set(values)].sort((a, b) => a - b);
        },

        clampMaxTokens() {
            let value = Number(this.maxTokens || this.tokenMin);
            if (!Number.isFinite(value)) {
                value = this.tokenMin;
            }

            value = Math.max(this.tokenMin, Math.min(this.activeModelMaxTokens(), Math.round(value)));
            this.maxTokens = value;
        },

        formatTokens(value) {
            return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
        },
    };
}

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
