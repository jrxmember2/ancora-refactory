<?php

namespace App\Services\Ai;

use App\Models\AppSetting;
use App\Models\ClientPortalUser;
use Illuminate\Support\Carbon;

class AiUsageLimiter
{
    public const MESSAGE_DISABLED = 'Este recurso ainda não está habilitado para seu acesso.';
    public const MESSAGE_LIMIT_REACHED = 'Seu limite mensal de consultas foi atingido. Entre em contato com o escritório para ampliar seu plano.';
    public const MESSAGE_UNLIMITED = 'Você ainda possui consultas ilimitadas neste mês.';

    private ?bool $globalAiEnabled = null;

    public function statusForUser(ClientPortalUser $portalUser, bool $persistReset = false): array
    {
        $portalUser = $this->syncMonthlyWindow($portalUser, $persistReset);

        $limit = $this->normalizeLimit($portalUser->ai_monthly_question_limit);
        $used = max(0, (int) ($portalUser->ai_questions_used_current_month ?? 0));
        $remaining = $limit === null ? null : max(0, $limit - $used);

        if (!$this->globalAiEnabled() || !$portalUser->is_active || !$portalUser->ai_enabled) {
            return $this->buildStatus(
                portalUser: $portalUser,
                allowed: false,
                limit: $limit,
                used: $used,
                remaining: $remaining,
                reason: 'disabled',
                message: self::MESSAGE_DISABLED,
            );
        }

        if ($remaining !== null && $remaining <= 0) {
            return $this->buildStatus(
                portalUser: $portalUser,
                allowed: false,
                limit: $limit,
                used: $used,
                remaining: 0,
                reason: 'limit_reached',
                message: self::MESSAGE_LIMIT_REACHED,
            );
        }

        return $this->buildStatus(
            portalUser: $portalUser,
            allowed: true,
            limit: $limit,
            used: $used,
            remaining: $remaining,
            reason: 'available',
            message: $remaining === null
                ? self::MESSAGE_UNLIMITED
                : sprintf('Você ainda possui %d consultas neste mês.', $remaining),
        );
    }

    public function incrementUsageOnSuccess(ClientPortalUser $portalUser): array
    {
        $portalUser = $this->syncMonthlyWindow($portalUser, true);
        $status = $this->statusForUser($portalUser, false);

        if (!$status['allowed']) {
            return $status;
        }

        $portalUser->forceFill([
            'ai_questions_used_current_month' => max(0, (int) ($portalUser->ai_questions_used_current_month ?? 0)) + 1,
            'ai_usage_reset_at' => $this->referenceDate()->toDateString(),
        ])->save();

        return $this->statusForUser($portalUser->fresh(), false);
    }

    public function resetPortalUser(ClientPortalUser $portalUser, ?Carbon $referenceDate = null): ClientPortalUser
    {
        $referenceDate ??= $this->referenceDate();

        $portalUser->forceFill([
            'ai_questions_used_current_month' => 0,
            'ai_usage_reset_at' => $referenceDate->toDateString(),
        ])->save();

        return $portalUser->refresh();
    }

    public function resetEligibleActiveUsers(?Carbon $referenceDate = null): int
    {
        $referenceDate ??= $this->referenceDate();
        $monthStart = $referenceDate->copy()->startOfMonth()->toDateString();

        return ClientPortalUser::query()
            ->where('is_active', true)
            ->where(function ($query) use ($monthStart) {
                $query->whereNull('ai_usage_reset_at')
                    ->orWhereDate('ai_usage_reset_at', '<', $monthStart);
            })
            ->update([
                'ai_questions_used_current_month' => 0,
                'ai_usage_reset_at' => $referenceDate->toDateString(),
                'updated_at' => now(),
            ]);
    }

    public function remainingQuestions(ClientPortalUser $portalUser, bool $persistReset = false): ?int
    {
        return $this->statusForUser($portalUser, $persistReset)['remaining'];
    }

    private function syncMonthlyWindow(ClientPortalUser $portalUser, bool $persistReset): ClientPortalUser
    {
        $referenceDate = $this->referenceDate();

        if (!$this->needsMonthlyReset($portalUser, $referenceDate)) {
            return $portalUser;
        }

        $portalUser->ai_questions_used_current_month = 0;
        $portalUser->ai_usage_reset_at = $referenceDate->toDateString();

        if ($persistReset) {
            $portalUser->save();
        }

        return $portalUser;
    }

    private function needsMonthlyReset(ClientPortalUser $portalUser, Carbon $referenceDate): bool
    {
        if (!$portalUser->ai_usage_reset_at) {
            return true;
        }

        return $portalUser->ai_usage_reset_at->copy()->startOfMonth()->lt($referenceDate->copy()->startOfMonth());
    }

    private function globalAiEnabled(): bool
    {
        return $this->globalAiEnabled ??= AppSetting::getValue('ai_enabled', '0') === '1';
    }

    private function normalizeLimit(mixed $limit): ?int
    {
        if ($limit === null || $limit === '') {
            return null;
        }

        return max(0, (int) $limit);
    }

    private function referenceDate(): Carbon
    {
        return now()->startOfDay();
    }

    private function buildStatus(
        ClientPortalUser $portalUser,
        bool $allowed,
        ?int $limit,
        int $used,
        ?int $remaining,
        string $reason,
        string $message,
    ): array {
        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'message' => $message,
            'global_ai_enabled' => $this->globalAiEnabled(),
            'user_ai_enabled' => (bool) $portalUser->ai_enabled,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'has_limit' => $limit !== null,
            'reset_at' => $portalUser->ai_usage_reset_at,
        ];
    }
}
