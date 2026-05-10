<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiResponse;

interface AiProviderInterface
{
    public function providerKey(): string;

    public function generate(
        array $settings,
        string $systemPrompt,
        string $userQuestion,
        string $documentContext,
        float $temperature,
        int $maxTokens,
    ): AiResponse;
}
