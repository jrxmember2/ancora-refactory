<?php

namespace App\Services\Automation;

use App\Models\AutomationAuditLog;
use App\Models\AutomationSession;
use Throwable;

class AutomationAuditService
{
    public function info(string $event, string $message, array $context = [], ?AutomationSession $session = null): void
    {
        $this->write('info', $event, $message, $context, $session);
    }

    public function warning(string $event, string $message, array $context = [], ?AutomationSession $session = null): void
    {
        $this->write('warning', $event, $message, $context, $session);
    }

    public function error(string $event, string $message, array $context = [], ?AutomationSession $session = null): void
    {
        $this->write('error', $event, $message, $context, $session);
    }

    public function exception(Throwable $exception, string $event, array $context = [], ?AutomationSession $session = null): void
    {
        $this->write('error', $event, $exception->getMessage(), array_merge($context, [
            'exception' => get_class($exception),
            'trace_head' => collect($exception->getTrace())->take(5)->values()->all(),
        ]), $session);
    }

    private function write(string $level, string $event, string $message, array $context, ?AutomationSession $session): void
    {
        try {
            AutomationAuditLog::query()->create([
                'session_id' => $session?->id,
                'level' => $level,
                'event' => $event,
                'message' => mb_substr($message, 0, 255),
                'context' => $context !== [] ? $context : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            //
        }
    }
}
