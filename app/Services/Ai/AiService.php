<?php

namespace App\Services\Ai;

use App\Models\AppSetting;
use App\Services\Ai\Providers\AiProviderInterface;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;

class AiService
{
    /** @var array<string, AiProviderInterface> */
    private array $providers;

    public function __construct(
        OpenAiProvider $openAiProvider,
        GeminiProvider $geminiProvider,
    ) {
        $this->providers = [
            $openAiProvider->providerKey() => $openAiProvider,
            $geminiProvider->providerKey() => $geminiProvider,
        ];
    }

    public function generate(
        ?string $systemPrompt,
        string $userQuestion,
        string|array|null $documentContext = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $settingsOverride = null,
    ): AiResponse {
        $settings = $this->settings($settingsOverride);
        $providerKey = $this->activeProvider($settings);
        $provider = $this->providers[$providerKey] ?? null;

        if (($settings['ai_enabled'] ?? false) !== true) {
            return AiResponse::failure($providerKey ?: 'unknown', '', 'A Inteligencia Artificial esta desativada nas configuracoes.');
        }

        if (!$provider) {
            return AiResponse::failure($providerKey ?: 'unknown', '', 'Provedor de IA invalido ou nao suportado.');
        }

        $providerEnabled = (bool) ($settings[$providerKey . '_enabled'] ?? false);
        if (!$providerEnabled) {
            return AiResponse::failure($providerKey, '', 'O provedor selecionado esta inativo nas configuracoes.');
        }

        $contextText = is_array($documentContext)
            ? trim(implode("\n\n", array_filter(array_map(fn ($item) => trim((string) $item), $documentContext))))
            : trim((string) $documentContext);

        $resolvedSystemPrompt = trim((string) $systemPrompt);
        if ($resolvedSystemPrompt === '') {
            $resolvedSystemPrompt = trim((string) ($settings['ai_default_system_prompt'] ?? ''));
        }

        $resolvedTemperature = $temperature ?? (float) ($settings['ai_default_temperature'] ?? 0.2);
        $resolvedMaxTokens = $maxTokens ?? (int) ($settings['ai_default_max_tokens'] ?? 1024);

        return $provider->generate(
            settings: $settings,
            systemPrompt: $resolvedSystemPrompt,
            userQuestion: trim($userQuestion),
            documentContext: $contextText,
            temperature: $resolvedTemperature,
            maxTokens: $resolvedMaxTokens,
        );
    }

    public function testConnection(?array $settingsOverride = null): AiResponse
    {
        $settings = $this->settings($settingsOverride);
        $providerKey = $this->activeProvider($settings);
        $provider = $this->providers[$providerKey] ?? null;

        if (!$provider) {
            return AiResponse::failure($providerKey ?: 'unknown', '', 'Provedor de IA invalido ou nao suportado.');
        }

        if (($settings[$providerKey . '_enabled'] ?? false) !== true) {
            return AiResponse::failure($providerKey, '', 'Ative o provedor selecionado antes de testar a conexao.');
        }

        $effectiveSettings = $settings;
        $effectiveSettings['ai_enabled'] = true;

        return $this->generate(
            systemPrompt: 'Voce deve responder exatamente com a frase solicitada pelo usuario, sem complemento.',
            userQuestion: 'Responda apenas: conexão funcionando.',
            documentContext: '',
            temperature: 0.0,
            maxTokens: 32,
            settingsOverride: $effectiveSettings,
        );
    }

    public function settings(?array $overrides = null): array
    {
        $base = [
            'ai_enabled' => AppSetting::getValue('ai_enabled', '0') === '1',
            'ai_active_provider' => $this->normalizeProvider(AppSetting::getValue('ai_active_provider', 'openai')),
            'ai_default_temperature' => $this->toFloat(AppSetting::getValue('ai_default_temperature', '0.2'), 0.2),
            'ai_default_max_tokens' => $this->toInt(AppSetting::getValue('ai_default_max_tokens', '1024'), 1024),
            'ai_default_system_prompt' => (string) AppSetting::getValue('ai_default_system_prompt', ''),
            'ai_default_legal_notice' => (string) AppSetting::getValue('ai_default_legal_notice', ''),
            'ai_default_budget_request_url' => (string) AppSetting::getValue('ai_default_budget_request_url', ''),
            'ai_old_document_alert_enabled' => AppSetting::getValue('ai_old_document_alert_enabled', '1') === '1',
            'ai_old_document_alert_years' => $this->toInt(AppSetting::getValue('ai_old_document_alert_years', '5'), 5),
            'openai_enabled' => AppSetting::getValue('ai_openai_enabled', '1') === '1',
            'openai_api_key' => (string) AppSetting::getDecryptedValue('ai_openai_api_key', ''),
            'openai_chat_model' => (string) AppSetting::getValue('ai_openai_chat_model', 'gpt-4.1-mini'),
            'openai_embedding_model' => (string) AppSetting::getValue('ai_openai_embedding_model', ''),
            'gemini_enabled' => AppSetting::getValue('ai_gemini_enabled', '1') === '1',
            'gemini_api_key' => (string) AppSetting::getDecryptedValue('ai_gemini_api_key', ''),
            'gemini_chat_model' => (string) AppSetting::getValue('ai_gemini_chat_model', 'gemini-2.5-flash'),
            'gemini_embedding_model' => (string) AppSetting::getValue('ai_gemini_embedding_model', ''),
        ];

        if (!$overrides) {
            return $base;
        }

        $merged = array_merge($base, $overrides);
        $merged['ai_active_provider'] = $this->normalizeProvider($merged['ai_active_provider'] ?? 'openai');
        $merged['ai_enabled'] = (bool) ($merged['ai_enabled'] ?? false);
        $merged['openai_enabled'] = (bool) ($merged['openai_enabled'] ?? false);
        $merged['gemini_enabled'] = (bool) ($merged['gemini_enabled'] ?? false);
        $merged['ai_default_temperature'] = $this->toFloat($merged['ai_default_temperature'] ?? 0.2, 0.2);
        $merged['ai_default_max_tokens'] = $this->toInt($merged['ai_default_max_tokens'] ?? 1024, 1024);
        $merged['ai_old_document_alert_years'] = $this->toInt($merged['ai_old_document_alert_years'] ?? 5, 5);

        return $merged;
    }

    private function activeProvider(array $settings): string
    {
        return $this->normalizeProvider($settings['ai_active_provider'] ?? 'openai');
    }

    private function normalizeProvider(?string $provider): string
    {
        return strtolower(trim((string) $provider)) === 'gemini' ? 'gemini' : 'openai';
    }

    private function toInt(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function toFloat(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }
}
