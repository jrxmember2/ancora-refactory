<?php

namespace App\Services\Ai;

class AiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $status,
        public readonly ?string $error = null,
        public readonly ?int $tokenEstimate = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly array $meta = [],
    ) {
    }

    public static function success(
        string $text,
        string $provider,
        string $model,
        string $status = 'success',
        ?int $tokenEstimate = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        array $meta = [],
    ): self {
        return new self(
            text: $text,
            provider: $provider,
            model: $model,
            status: $status,
            error: null,
            tokenEstimate: $tokenEstimate,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            meta: $meta,
        );
    }

    public static function failure(
        string $provider,
        string $model,
        string $error,
        string $status = 'error',
        array $meta = [],
    ): self {
        return new self(
            text: '',
            provider: $provider,
            model: $model,
            status: $status,
            error: $error,
            tokenEstimate: null,
            inputTokens: null,
            outputTokens: null,
            meta: $meta,
        );
    }

    public function ok(): bool
    {
        return $this->error === null && in_array($this->status, ['success', 'completed'], true);
    }
}
