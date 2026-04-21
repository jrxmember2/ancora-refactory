<?php

namespace App\Services\Automation;

use App\Models\AutomationSession;
use App\Models\AutomationSessionMessage;
use App\Support\Automation\AutomationFlow;
use App\Support\Automation\AutomationStatus;
use App\Support\Automation\AutomationStep;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AutomationSessionService
{
    public function __construct(private readonly AutomationAuditService $audit)
    {
    }

    public function findDuplicateResponse(IncomingAutomationMessageData $data): ?array
    {
        if (!$data->externalMessageId) {
            return null;
        }

        $message = AutomationSessionMessage::query()
            ->where('provider', $data->provider)
            ->where('external_message_id', $data->externalMessageId)
            ->first();

        return $message?->response_payload;
    }

    public function findOrCreateSession(IncomingAutomationMessageData $data): AutomationSession
    {
        $session = AutomationSession::query()
            ->when($data->externalContactId || $data->phone, function ($query) use ($data) {
                $query->where(function ($inner) use ($data) {
                    if ($data->externalContactId) {
                        $inner->orWhere('external_contact_id', $data->externalContactId);
                    }

                    if ($data->phone !== '') {
                        $inner->orWhere('phone', $data->phone);
                    }
                });
            })
            ->where('channel', $data->channel)
            ->latest('last_interaction_at')
            ->first();

        if ($session && $this->isReusable($session)) {
            return $this->touch($session, [
                'provider' => $data->provider,
                'external_contact_id' => $data->externalContactId ?: $session->external_contact_id,
                'phone' => $data->phone ?: $session->phone,
            ]);
        }

        if ($session && $session->status === AutomationStatus::ACTIVE && now()->greaterThan($session->expires_at)) {
            $this->expire($session);
        }

        return DB::transaction(function () use ($data) {
            return AutomationSession::query()->create([
                'protocol' => $this->nextProtocol(),
                'channel' => $data->channel,
                'provider' => $data->provider,
                'external_contact_id' => $data->externalContactId,
                'phone' => $data->phone,
                'current_flow' => AutomationFlow::MENU,
                'current_step' => AutomationStep::MENU,
                'status' => AutomationStatus::ACTIVE,
                'started_at' => now(),
                'last_interaction_at' => now(),
                'expires_at' => now()->addMinutes((int) config('automation.session.timeout_minutes', 30)),
                'metadata' => [],
            ]);
        });
    }

    public function recordInboundMessage(AutomationSession $session, IncomingAutomationMessageData $data): AutomationSessionMessage
    {
        $payload = $data->toPayload();

        try {
            return AutomationSessionMessage::query()->create([
                'session_id' => $session->id,
                'direction' => 'inbound',
                'provider' => $data->provider,
                'external_message_id' => $data->externalMessageId,
                'payload' => $payload,
                'normalized_text' => $data->messageText,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (!$data->externalMessageId) {
                throw $exception;
            }

            $message = AutomationSessionMessage::query()
                ->where('provider', $data->provider)
                ->where('external_message_id', $data->externalMessageId)
                ->first();

            if ($message) {
                return $message;
            }

            throw $exception;
        }
    }

    public function recordOutboundMessage(AutomationSession $session, array $response): AutomationSessionMessage
    {
        return AutomationSessionMessage::query()->create([
            'session_id' => $session->id,
            'direction' => 'outbound',
            'provider' => 'system',
            'payload' => $response,
            'normalized_text' => (string) data_get($response, 'action.message'),
            'response_payload' => $response,
            'created_at' => now(),
        ]);
    }

    public function attachResponse(AutomationSessionMessage $message, array $response): void
    {
        $message->forceFill(['response_payload' => $response])->save();
    }

    public function transition(AutomationSession $session, string $flow, string $step, array $attributes = [], array $metadata = []): AutomationSession
    {
        $payload = array_merge($attributes, [
            'current_flow' => $flow,
            'current_step' => $step,
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes((int) config('automation.session.timeout_minutes', 30)),
        ]);

        if ($metadata !== []) {
            $payload['metadata'] = array_replace_recursive($session->metadata ?? [], $metadata);
        }

        $session->update($payload);

        return $session->refresh();
    }

    public function mergeMetadata(AutomationSession $session, array $metadata): AutomationSession
    {
        return $this->transition($session, $session->current_flow, $session->current_step, [], $metadata);
    }

    public function expire(AutomationSession $session): void
    {
        $session->update([
            'status' => AutomationStatus::EXPIRED,
            'current_step' => AutomationStep::EXPIRED,
            'closed_at' => now(),
        ]);
    }

    public function handover(AutomationSession $session): AutomationSession
    {
        $session->update([
            'status' => AutomationStatus::HANDOVER_HUMAN,
            'current_step' => AutomationStep::HANDOVER_HUMAN,
            'closed_at' => now(),
        ]);

        return $session->refresh();
    }

    public function close(AutomationSession $session): AutomationSession
    {
        $session->update([
            'status' => AutomationStatus::CLOSED,
            'current_step' => AutomationStep::CLOSED,
            'closed_at' => now(),
        ]);

        return $session->refresh();
    }

    public function touch(AutomationSession $session, array $attributes = []): AutomationSession
    {
        $session->update(array_merge($attributes, [
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes((int) config('automation.session.timeout_minutes', 30)),
        ]));

        return $session->refresh();
    }

    private function isReusable(AutomationSession $session): bool
    {
        return $session->status === AutomationStatus::ACTIVE
            && $session->closed_at === null
            && now()->lessThanOrEqualTo($session->expires_at);
    }

    private function nextProtocol(): string
    {
        $year = now()->year;
        $prefix = (string) config('automation.session.protocol_prefix', 'AUT');
        $sequence = (int) AutomationSession::query()
            ->whereYear('created_at', $year)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $sequence);
    }
}
