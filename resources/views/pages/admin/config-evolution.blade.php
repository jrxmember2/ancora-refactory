@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
    $monitoring = $settings['monitoring'] ?? [];
    $formatMonitoringDate = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return 'Ainda sem registro';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    };
@endphp

@section('content')
<div
    class="space-y-6"
    x-data="evolutionConfigPage({
        webhookBaseUrl: @js($settings['webhook_base_url']),
        webhookToken: @js(old('evolution_webhook_token', $settings['evolution_webhook_token'])),
    })"
>
    <x-ancora.section-header title="EvolutionAPI" subtitle="Credenciais do provedor, webhook, templates de WhatsApp e teste rapido de envio em uma unica tela." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para Configuracoes</span>
        </a>
        <button type="button" onclick="testEvolutionConnection(this)" data-test-url="{{ route('config.evolution.test') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-plug-circle-check"></i>
            <span>Testar conexao</span>
        </button>
        <button type="button" onclick="syncEvolutionWebhook(this)" data-sync-url="{{ route('config.evolution.webhook.sync') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Sincronizar webhook</span>
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Ultimo webhook</div>
            <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $monitoring['last_webhook_event_name'] ?: 'Nenhum evento ainda' }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Status: {{ $monitoring['last_webhook_status'] ?: 'sem processamento' }}</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $formatMonitoringDate($monitoring['last_webhook_at'] ?? '') }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Conexao da instancia</div>
            <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $monitoring['last_connection_state'] ?: 'Sem retorno ainda' }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $monitoring['last_connection_instance'] ?: 'Instancia ainda nao informada' }}</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $formatMonitoringDate($monitoring['last_connection_at'] ?? '') }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Ultima inbound</div>
            <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $monitoring['last_inbound_phone'] ?: 'Sem mensagens inbound' }}</div>
            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $monitoring['last_inbound_message'] ?: 'Quando o cliente responder no WhatsApp, o resumo aparece aqui.' }}</p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $formatMonitoringDate($monitoring['last_inbound_at'] ?? '') }}</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Ultimo status</div>
            <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $monitoring['last_message_status'] ?: 'Nenhum status reconciliado' }}</div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $monitoring['last_message_status_module'] ?: 'Modulo ainda nao identificado' }}
                @if(!empty($monitoring['last_message_status_phone']))
                    · {{ $monitoring['last_message_status_phone'] }}
                @endif
            </p>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $formatMonitoringDate($monitoring['last_message_status_at'] ?? '') }}</p>
        </div>
    </div>

    <form id="evolution-config-form" method="post" action="{{ route('config.evolution.save') }}" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Canal de saida WhatsApp</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defina a instancia EvolutionAPI que o Ancora vai usar para disparos e configure o intervalo entre mensagens em lote.</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-success-100 px-3 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/15 dark:text-success-300">
                            Step 1
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200 md:col-span-2">
                            <input type="checkbox" name="evolution_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('evolution_enabled', $settings['evolution_enabled'] ? 1 : 0))>
                            <span class="min-w-0">
                                <span class="block font-medium">EvolutionAPI ativa</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Quando ligada, esta configuracao passa a ser a base oficial dos disparos de WhatsApp do sistema.</span>
                            </span>
                        </label>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">URL base da EvolutionAPI</label>
                            <input type="url" name="evolution_base_url" value="{{ old('evolution_base_url', $settings['evolution_base_url']) }}" class="{{ $inputClass }}" placeholder="https://sua-evolution.exemplo.com">
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Use a URL publica da sua VPS ou do servico no EasyPanel, sempre com protocolo HTTP/HTTPS.</p>
                            @error('evolution_base_url')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome da instancia</label>
                            <input name="evolution_instance_name" value="{{ old('evolution_instance_name', $settings['evolution_instance_name']) }}" class="{{ $inputClass }}" placeholder="ancora-whatsapp">
                            @error('evolution_instance_name')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="relative" x-data="{ show:false }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">API key</label>
                            <input :type="show ? 'text' : 'password'" name="evolution_api_key" value="" placeholder="{{ $settings['evolution_has_api_key'] ? 'Chave atual: ' . $settings['evolution_api_key_masked'] . ' - preencha apenas para trocar' : 'Cole a API key da EvolutionAPI aqui' }}" class="{{ $inputClass }} pr-11">
                            <button type="button" @click="show = !show" class="absolute right-4 top-[43px] -translate-y-1/2 text-gray-500 dark:text-gray-400"><i class="fa-solid" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                            @if($settings['evolution_has_api_key'])
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Chave salva: {{ $settings['evolution_api_key_masked'] }}</p>
                            @endif
                            @error('evolution_api_key')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Intervalo entre mensagens em lote (ms)</label>
                            <input type="number" min="0" max="600000" step="100" name="evolution_message_dispatch_delay_ms" value="{{ old('evolution_message_dispatch_delay_ms', $settings['evolution_message_dispatch_delay_ms']) }}" class="{{ $inputClass }}" placeholder="3000">
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Se houver mais de uma mensagem para enviar, o sistema vai respeitar esse intervalo entre um disparo e outro. Recomendacao inicial: entre 2000 e 5000 ms.</p>
                            @error('evolution_message_dispatch_delay_ms')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Webhook da EvolutionAPI</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">O proprio Ancora recebe os eventos e valida o token antes de aceitar a chamada.</p>
                        </div>
                        <button type="button" onclick="syncEvolutionWebhook(this)" data-sync-url="{{ route('config.evolution.webhook.sync') }}" class="{{ $softButtonClass }} inline-flex items-center justify-center gap-2 whitespace-nowrap">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>Sincronizar agora</span>
                        </button>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="evolution_webhook_enabled" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('evolution_webhook_enabled', $settings['evolution_webhook_enabled'] ? 1 : 0))>
                            <span class="min-w-0">
                                <span class="block font-medium">Webhook ativo</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Mantem a EvolutionAPI enviando eventos para o endpoint do Ancora.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="evolution_webhook_by_events" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('evolution_webhook_by_events', $settings['evolution_webhook_by_events'] ? 1 : 0))>
                            <span class="min-w-0">
                                <span class="block font-medium">Separar webhook por evento</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Quando ligado, a EvolutionAPI adiciona o nome do evento ao final da URL do webhook.</span>
                            </span>
                        </label>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Token do webhook</label>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <input x-model="webhookToken" name="evolution_webhook_token" class="{{ $inputClass }}" placeholder="Token secreto usado para validar o webhook">
                                <button type="button" @click="generateToken()" class="{{ $softButtonClass }} whitespace-nowrap">Gerar novo token</button>
                            </div>
                            @error('evolution_webhook_token')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2 rounded-2xl border border-dashed border-brand-300 bg-brand-50/60 p-4 dark:border-brand-800 dark:bg-brand-500/5">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-700 dark:text-brand-300">URL que sera enviada para a EvolutionAPI</div>
                            <code class="mt-2 block break-all rounded-xl bg-white px-3 py-2 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-100" x-text="webhookUrl()"></code>
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">O token faz parte da propria rota do webhook. Se voce ativar o modo por evento, a EvolutionAPI pode acrescentar o nome do evento depois desse token sem quebrar a validacao.</p>
                        </div>

                        <div class="md:col-span-2">
                            <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Eventos assinados</div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @foreach($webhookEvents as $event)
                                    <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                        <input type="checkbox" name="evolution_webhook_events[]" value="{{ $event['value'] }}" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(in_array($event['value'], old('evolution_webhook_events', $settings['evolution_webhook_events']), true))>
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ $event['label'] }}</span>
                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $event['description'] }}</span>
                                            <span class="mt-2 inline-flex rounded-full bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $event['value'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('evolution_webhook_events')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                            @error('evolution_webhook_events.*')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Templates de mensagem</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Processos vai usar o template de WhatsApp. Cobranca vai usar o template de WhatsApp e tambem o template de e-mail com assunto e corpo variaveis.</p>
                    </div>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Template padrao para Processos</label>
                            <textarea name="evolution_template_process_update" rows="8" class="{{ $textareaClass }}" placeholder="Mensagem usada quando houver novo andamento no processo.">{{ old('evolution_template_process_update', $settings['evolution_template_process_update']) }}</textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Exemplo de uso: informe o processo, o condominio, a unidade, o bloco e o ultimo andamento.</p>
                            @error('evolution_template_process_update')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Template padrao para Cobranca</label>
                            <textarea name="evolution_template_collection_notice" rows="8" class="{{ $textareaClass }}" placeholder="Mensagem usada para comunicacao de inadimplencia e cobranca.">{{ old('evolution_template_collection_notice', $settings['evolution_template_collection_notice']) }}</textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Aqui vale usar variaveis como nome do condominio, unidade, bloco, vencimento e numero da OS.</p>
                            @error('evolution_template_collection_notice')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Template de e-mail para Cobranca</h4>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">O sistema renderiza as variaveis abaixo tanto no assunto quanto no corpo antes de enviar para os proprietarios das unidades.</p>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Assunto do e-mail</label>
                                    <input name="evolution_template_collection_email_subject" value="{{ old('evolution_template_collection_email_subject', $settings['evolution_template_collection_email_subject']) }}" class="{{ $inputClass }}" placeholder="Inadimplencia - @{{condominio_nome}} - Unidade @{{unidade_numero}}">
                                    @error('evolution_template_collection_email_subject')
                                        <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Corpo do e-mail</label>
                                    <textarea name="evolution_template_collection_email_body" rows="10" class="{{ $textareaClass }}" placeholder="Texto usado no e-mail de inadimplencia.">{{ old('evolution_template_collection_email_body', $settings['evolution_template_collection_email_body']) }}</textarea>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Sugestao: use <code>@{{cotas_vencidas}}</code> em uma linha isolada para listar todas as cotas vencidas da OS.</p>
                                    @error('evolution_template_collection_email_body')
                                        <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Variaveis disponiveis</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Estas tags ficam prontas para a fase de disparo dos modulos.</p>
                    <div class="mt-4 space-y-3">
                        @foreach($templateVariables as $variable)
                            @php($token = '{{' . $variable['key'] . '}}')
                            <div class="rounded-2xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <code class="rounded-lg bg-gray-100 px-2 py-1 text-xs text-gray-800 dark:bg-gray-800 dark:text-gray-100">{{ $token }}</code>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $variable['label'] }}</span>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $variable['modules'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Teste rapido</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use um numero com DDI para validar o envio real pela instancia configurada.</p>
                        </div>
                        <i class="fa-brands fa-whatsapp text-lg text-brand-500"></i>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Numero de teste</label>
                            <input name="test_number" value="{{ old('test_number') }}" class="{{ $inputClass }}" placeholder="5511999999999">
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Informe somente numeros, com codigo do pais.</p>
                            @error('test_number')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mensagem de teste</label>
                            <textarea name="test_message" rows="6" class="{{ $textareaClass }}" placeholder="Mensagem enviada apenas no teste manual.">{{ old('test_message', 'Ola! Esta e uma mensagem de teste do Ancora via EvolutionAPI.') }}</textarea>
                            @error('test_message')
                                <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="button" onclick="sendEvolutionTestMessage(this)" data-message-url="{{ route('config.evolution.message.test') }}" class="{{ $softButtonClass }} w-full">
                            Enviar mensagem de teste
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-dashed border-brand-300 bg-brand-50/60 p-6 shadow-theme-xs dark:border-brand-800 dark:bg-brand-500/5">
                    <h3 class="text-base font-semibold text-brand-900 dark:text-brand-100">Boas praticas desta fase</h3>
                    <ul class="mt-4 space-y-2 text-sm text-brand-900/80 dark:text-brand-100/80">
                        <li>A API key fica criptografada em <code>app_settings</code>.</li>
                        <li>O teste de conexao consulta o estado da instancia em tempo real.</li>
                        <li>O webhook pode ser sincronizado sem precisar salvar primeiro.</li>
                        <li>O intervalo em ms sera a base dos disparos em lote para evitar rajadas no numero.</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo do rastreio</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Eventos webhook</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $monitoring['webhook_events_count'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Mensagens rastreadas</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $monitoring['message_logs_count'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Saidas pendentes</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $monitoring['outbound_pending_count'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Saidas com falha</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $monitoring['outbound_failed_count'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Endpoints oficiais usados</h3>
                    <div class="mt-4 space-y-3 text-xs text-gray-600 dark:text-gray-300">
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="font-semibold text-gray-900 dark:text-white">Teste da instancia</div>
                            <code class="mt-1 block break-all">GET /instance/connectionState/{instance}</code>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="font-semibold text-gray-900 dark:text-white">Sincronizacao do webhook</div>
                            <code class="mt-1 block break-all">POST /webhook/set/{instance}</code>
                        </div>
                        <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                            <div class="font-semibold text-gray-900 dark:text-white">Teste de envio</div>
                            <code class="mt-1 block break-all">POST /message/sendText/{instance}</code>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <button class="{{ $buttonClass }}">Salvar configuracoes da EvolutionAPI</button>
                    <button type="button" onclick="testEvolutionConnection(this)" data-test-url="{{ route('config.evolution.test') }}" class="{{ $softButtonClass }}">Testar conexao agora</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function evolutionConfigPage(config) {
    return {
        webhookBaseUrl: config.webhookBaseUrl || '',
        webhookToken: config.webhookToken || '',

        init() {
            if (!this.webhookToken) {
                this.generateToken();
            }
        },

        webhookUrl() {
            if (!this.webhookBaseUrl) {
                return '';
            }

            const base = this.webhookBaseUrl.replace(/\/+$/, '');
            if (!this.webhookToken) {
                return base;
            }

            return `${base}/${encodeURIComponent(this.webhookToken)}`;
        },

        generateToken() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                this.webhookToken = window.crypto.randomUUID().replace(/-/g, '') + window.crypto.randomUUID().replace(/-/g, '').slice(0, 16);
                return;
            }

            this.webhookToken = `${Date.now()}${Math.random().toString(36).slice(2)}${Math.random().toString(36).slice(2)}`;
        },
    };
}

async function testEvolutionConnection(button) {
    await runEvolutionAction(button, button.dataset.testUrl, 'Conexao validada com sucesso.');
}

async function syncEvolutionWebhook(button) {
    await runEvolutionAction(button, button.dataset.syncUrl, 'Webhook sincronizado com sucesso.');
}

async function sendEvolutionTestMessage(button) {
    await runEvolutionAction(button, button.dataset.messageUrl, 'Mensagem de teste enviada com sucesso.');
}

async function runEvolutionAction(button, url, fallbackMessage) {
    const form = document.getElementById('evolution-config-form');
    if (!form || !url) {
        return;
    }

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Processando...';

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
            throw new Error(firstError || data?.message || 'Nao foi possivel concluir esta acao agora.');
        }

        showEvolutionToast(data.message || fallbackMessage, 'success');
    } catch (error) {
        showEvolutionToast(error?.message || 'Erro ao comunicar com a EvolutionAPI.', 'error');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

function showEvolutionToast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `fixed right-6 top-6 z-[999999] rounded-2xl px-4 py-3 text-sm font-medium shadow-theme-lg ${type === 'error' ? 'bg-error-500 text-white' : 'bg-success-500 text-white'}`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2800);
}
</script>
@endpush
