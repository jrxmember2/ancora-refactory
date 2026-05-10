<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiResponse;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function providerKey(): string
    {
        return 'openai';
    }

    public function generate(
        array $settings,
        string $systemPrompt,
        string $userQuestion,
        string $documentContext,
        float $temperature,
        int $maxTokens,
    ): AiResponse {
        $apiKey = trim((string) ($settings['openai_api_key'] ?? ''));
        $model = trim((string) ($settings['openai_chat_model'] ?? ''));

        if ($apiKey === '' || $model === '') {
            return AiResponse::failure($this->providerKey(), $model, 'OpenAI nao configurada com chave e modelo de chat.');
        }

        $response = Http::baseUrl('https://api.openai.com/v1')
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout(60)
            ->connectTimeout(15)
            ->post('/responses', [
                'model' => $model,
                'instructions' => $systemPrompt,
                'input' => $this->composeUserPrompt($userQuestion, $documentContext),
                'temperature' => $temperature,
                'max_output_tokens' => $maxTokens,
            ]);

        $payload = $response->json();
        if (!$response->successful()) {
            return AiResponse::failure(
                $this->providerKey(),
                $model,
                $this->resolveErrorMessage($payload, 'Falha ao comunicar com a OpenAI.'),
                meta: ['http_status' => $response->status()]
            );
        }

        $text = $this->extractText($payload);
        if ($text === '') {
            return AiResponse::failure(
                $this->providerKey(),
                $model,
                'A OpenAI respondeu sem texto utilizavel.',
                status: (string) ($payload['status'] ?? 'error'),
                meta: ['http_status' => $response->status()]
            );
        }

        return AiResponse::success(
            text: $text,
            provider: $this->providerKey(),
            model: (string) ($payload['model'] ?? $model),
            status: (string) ($payload['status'] ?? 'completed'),
            tokenEstimate: $this->toInt($payload['usage']['total_tokens'] ?? null),
            inputTokens: $this->toInt($payload['usage']['input_tokens'] ?? null),
            outputTokens: $this->toInt($payload['usage']['output_tokens'] ?? null),
            meta: ['http_status' => $response->status()]
        );
    }

    private function composeUserPrompt(string $userQuestion, string $documentContext): string
    {
        $question = trim($userQuestion);
        $context = trim($documentContext);

        if ($context === '') {
            return $question;
        }

        return "Pergunta do usuario:\n{$question}\n\nContexto documental:\n{$context}";
    }

    private function extractText(mixed $payload): string
    {
        $messages = $payload['output'] ?? [];
        if (!is_array($messages)) {
            return '';
        }

        $chunks = [];

        foreach ($messages as $message) {
            if (($message['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($message['content'] ?? []) as $contentPart) {
                if (($contentPart['type'] ?? null) !== 'output_text') {
                    continue;
                }

                $text = trim((string) ($contentPart['text'] ?? ''));
                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function resolveErrorMessage(mixed $payload, string $fallback): string
    {
        $message = trim((string) ($payload['error']['message'] ?? $payload['message'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
