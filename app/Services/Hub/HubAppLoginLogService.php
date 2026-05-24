<?php

namespace App\Services\Hub;

use App\Models\HubApiToken;
use App\Models\HubAppLoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HubAppLoginLogService
{
    public function recordAttempt(
        ?User $user,
        ?HubApiToken $token,
        Request $request,
        array $meta = [],
        bool $success = true,
        ?string $failureReason = null,
    ): HubAppLoginLog {
        return HubAppLoginLog::query()->create([
            'user_id' => $user?->id,
            'hub_api_token_id' => $token?->id,
            'platform' => $this->nullableString($meta['platform'] ?? 'android', 20),
            'device_name' => $this->nullableString($meta['device_name'] ?? null, 160),
            'app_version' => $this->nullableString($meta['app_version'] ?? null, 40),
            'ip_address' => $this->nullableString($request->ip(), 45),
            'user_agent' => $this->nullableString($request->userAgent(), 255),
            'success' => $success,
            'failure_reason' => $this->nullableString($failureReason, 190),
        ]);
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
