<?php

namespace App\Support\Hub;

use App\Models\HubApiToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class HubApiTokenManager
{
    public const SHORT_SESSION_HOURS = 24;
    public const BIOMETRIC_SESSION_DAYS = 30;

    public function issue(User $user, array $meta = []): array
    {
        $biometricEnabled = (bool) ($meta['biometric_enabled'] ?? false);
        $plainTextToken = 'anchub_' . Str::random(72);

        $token = HubApiToken::query()->create([
            'user_id' => $user->id,
            'name' => trim((string) ($meta['name'] ?? 'ancora-hub-android')) ?: 'ancora-hub-android',
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities_json' => Arr::wrap($meta['abilities'] ?? ['hub-api']),
            'device_name' => $this->nullableString($meta['device_name'] ?? null, 160),
            'platform' => $this->nullableString($meta['platform'] ?? 'android', 20) ?? 'android',
            'app_version' => $this->nullableString($meta['app_version'] ?? null, 40),
            'ip_address' => $this->nullableString($meta['ip'] ?? null, 45),
            'user_agent' => $this->nullableString($meta['user_agent'] ?? null),
            'biometric_enabled' => $biometricEnabled,
            'last_used_at' => now(),
            'expires_at' => $this->expiresAt($biometricEnabled),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'token' => $token,
        ];
    }

    public function findFromRequest(Request $request): ?HubApiToken
    {
        $header = trim((string) $request->bearerToken());
        if ($header === '') {
            return null;
        }

        return HubApiToken::query()
            ->with([
                'user.modules' => fn ($query) => $query->where('is_enabled', 1)->orderBy('sort_order')->orderBy('name'),
                'user.routePermissions' => fn ($query) => $query->orderBy('route_permissions.group_key')->orderBy('route_permissions.route_name'),
            ])
            ->where('token_hash', hash('sha256', $header))
            ->first();
    }

    public function touch(HubApiToken $token, Request $request): void
    {
        $now = now();

        $token->forceFill([
            'ip_address' => $this->nullableString($request->ip(), 45),
            'user_agent' => $this->nullableString($request->userAgent()),
            'last_used_at' => $now,
            'expires_at' => $this->expiresAt((bool) $token->biometric_enabled, $now),
        ])->save();
    }

    public function revoke(HubApiToken $token): void
    {
        if ($token->revoked_at) {
            return;
        }

        $token->forceFill([
            'revoked_at' => now(),
        ])->save();
    }

    public function isRejected(?HubApiToken $token, ?User $user = null): bool
    {
        if (!$token || !$user) {
            return true;
        }

        if ($token->revoked_at !== null) {
            return true;
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return true;
        }

        return !(bool) $user->is_active;
    }

    public function revokeOtherTokens(User $user, ?HubApiToken $except = null): array
    {
        $query = HubApiToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at');

        if ($except) {
            $query->where('id', '!=', $except->id);
        }

        $tokenIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($tokenIds !== []) {
            HubApiToken::query()
                ->whereIn('id', $tokenIds)
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return $tokenIds;
    }

    public function sessionPolicy(bool $biometricEnabled): array
    {
        $seconds = $this->sessionLifetimeInSeconds($biometricEnabled);

        return [
            'sliding_expiration' => true,
            'biometric_enabled' => $biometricEnabled,
            'inactive_expires_in_seconds' => $seconds,
            'inactive_expires_in_label' => $biometricEnabled ? '30 dias' : '24 horas',
        ];
    }

    public function sessionLifetimeInSeconds(bool $biometricEnabled): int
    {
        return $biometricEnabled
            ? self::BIOMETRIC_SESSION_DAYS * 24 * 60 * 60
            : self::SHORT_SESSION_HOURS * 60 * 60;
    }

    private function expiresAt(bool $biometricEnabled, ?Carbon $reference = null): Carbon
    {
        $reference ??= now();

        return $biometricEnabled
            ? $reference->copy()->addDays(self::BIOMETRIC_SESSION_DAYS)
            : $reference->copy()->addHours(self::SHORT_SESSION_HOURS);
    }

    private function nullableString(mixed $value, ?int $limit = null): ?string
    {
        $string = trim((string) ($value ?? ''));
        if ($string === '') {
            return null;
        }

        return $limit ? Str::limit($string, $limit, '') : $string;
    }
}
