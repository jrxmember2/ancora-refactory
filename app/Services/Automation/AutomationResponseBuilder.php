<?php

namespace App\Services\Automation;

use App\Models\AutomationSession;

class AutomationResponseBuilder
{
    public function reply(
        AutomationSession $session,
        string $message,
        array $options = [],
        array $data = [],
        bool $humanHandover = false,
        bool $closeSession = false,
        array $meta = [],
    ): array {
        return [
            'ok' => true,
            'version' => 'v1',
            'meta' => $meta,
            'session' => [
                'protocol' => $session->protocol,
                'current_flow' => $session->current_flow,
                'current_step' => $session->current_step,
                'status' => $session->status,
            ],
            'action' => [
                'type' => 'reply',
                'message' => $message,
                'options' => array_values($options),
                'human_handover' => $humanHandover,
                'close_session' => $closeSession,
            ],
            'data' => array_merge([
                'condominium' => null,
                'unit' => null,
                'debts' => null,
                'proposal' => null,
            ], $data),
        ];
    }

    public function error(string $message, int $status = 500, array $meta = []): array
    {
        return [
            'ok' => false,
            'version' => 'v1',
            'meta' => array_merge($meta, ['http_status' => $status]),
            'error' => [
                'message' => $message,
            ],
        ];
    }
}
