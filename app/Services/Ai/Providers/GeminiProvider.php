<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiResponse;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    public function providerKey(): string
    {
        return 'gemini';
    }

    public function generate(
        array $settings,
        string $systemPrompt,
        string $userQuestion,
        string $documentContext,
        float $temperature,
        int $maxTokens,
    ): AiResponse {
        $apiKey = trim((string) ($settings['gemini_api_key'] ?? ''));
        $model = trim((string) ($settings['gemini_chat_model'] ?? ''));

        if ($apiKey === '' || $model === '') {
            return AiResponse::failure($this->providerKey(), $model, 'Gemini nao configurada com chave e modelo de chat.');
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $this->composeUserPrompt($userQuestion, $documentContext),
                ]],
            ]],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if (trim($systemPrompt) !== '') {
            $payload['systemInstruction'] = [
                'parts' => [[
                    'text' => trim($systemPrompt),
                ]],
            ];
        }

        $response = Http::baseUrl('https://generativelanguage.googleapis.com/v1beta')
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
            ])
            ->timeout(60)
            ->connectTimeout(15)
            ->post('/models/' . $model . ':generateContent', $payload);

        $data = $response->json();
        if (!$response->successful()) {
            return AiResponse::failure(
                $this->providerKey(),
                $model,
                $this->resolveErrorMessage($data, 'Falha ao comunicar com a Gemini.'),
                meta: ['http_status' => $response->status()]
            );
        }

        $text = $this->extractText($data);
        if ($text === '') {
            return AiResponse::failure(
                $this->providerKey(),
                $model,
                'A Gemini respondeu sem texto utilizavel.',
                status: $this->resolveStatus($data),
                meta: ['http_status' => $response->status()]
            );
        }

        return AiResponse::success(
            text: $text,
            provider: $this->providerKey(),
            model: (string) ($data['modelVersion'] ?? $model),
            status: $this->resolveStatus($data),
            tokenEstimate: $this->toInt($data['usageMetadata']['totalTokenCount'] ?? null),
            inputTokens: $this->toInt($data['usageMetadata']['promptTokenCount'] ?? null),
            outputTokens: $this->toInt($data['usageMetadata']['candidatesTokenCount'] ?? null),
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
        $candidates = $payload['candidates'] ?? [];
        if (!is_array($candidates)) {
            return '';
        }

        $chunks = [];

        foreach ($candidates as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                $text = trim((string) ($part['text'] ?? ''));
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

    private function resolveStatus(mixed $payload): string
    {
        $finishReason = trim((string) ($payload['candidates'][0]['finishReason'] ?? ''));

        return $finishReason !== '' ? strtolower($finishReason) : 'completed';
    }

    private function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
