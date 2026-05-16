<?php

namespace App\Support\Mobile;

use App\Models\ClientPortalApiToken;
use App\Models\ClientPortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ClientPortalApiTokenManager
{
    public function issue(
        ClientPortalUser $user,
        ?int $selectedCondominiumId = null,
        array $meta = [],
    ): array {
        $plainTextToken = 'ancm_' . Str::random(72);
        $abilities = Arr::wrap($meta['abilities'] ?? ['mobile-api']);
        $token = ClientPortalApiToken::query()->create([
            'client_portal_user_id' => $user->id,
            'name' => trim((string) ($meta['name'] ?? 'android')) ?: 'android',
            'platform' => trim((string) ($meta['platform'] ?? 'android')) ?: 'android',
            'device_name' => $this->nullableString($meta['device_name'] ?? null, 160),
            'app_version' => $this->nullableString($meta['app_version'] ?? null, 40),
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities_json' => $abilities,
            'context_json' => $selectedCondominiumId ? ['selected_condominium_id' => (int) $selectedCondominiumId] : [],
            'last_ip' => $this->nullableString($meta['ip'] ?? null, 45),
            'last_user_agent' => $this->nullableString($meta['user_agent'] ?? null),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(max(1, (int) config('mobile.token_ttl_days', 30))),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'token' => $token,
        ];
    }

    public function findFromRequest(Request $request): ?ClientPortalApiToken
    {
        $header = trim((string) $request->bearerToken());
        if ($header === '') {
            return null;
        }

        return ClientPortalApiToken::query()
            ->with(['portalUser.condominiums', 'portalUser.condominium'])
            ->where('token_hash', hash('sha256', $header))
            ->first();
    }

    public function touch(ClientPortalApiToken $token, Request $request): void
    {
        $token->forceFill([
            'last_ip' => $this->nullableString($request->ip(), 45),
            'last_user_agent' => $this->nullableString($request->userAgent()),
            'last_used_at' => now(),
        ])->save();
    }

    public function revoke(ClientPortalApiToken $token): void
    {
        if ($token->revoked_at) {
            return;
        }

        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function revokeOtherTokens(ClientPortalUser $user, ?ClientPortalApiToken $except = null): void
    {
        $query = ClientPortalApiToken::query()
            ->where('client_portal_user_id', $user->id)
            ->whereNull('revoked_at');

        if ($except) {
            $query->where('id', '!=', $except->id);
        }

        $query->update([
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function selectedCondominiumId(ClientPortalApiToken $token, ClientPortalUser $user): ?int
    {
        $selected = (int) data_get($token->context_json, 'selected_condominium_id', 0);
        if ($selected <= 0) {
            return null;
        }

        return in_array($selected, $user->accessibleCondominiumIds(), true) ? $selected : null;
    }

    public function updateSelectedCondominium(ClientPortalApiToken $token, ClientPortalUser $user, ?int $condominiumId): ?int
    {
        $condominiumId = (int) ($condominiumId ?? 0);
        $selected = null;

        if ($condominiumId > 0) {
            $selected = in_array($condominiumId, $user->accessibleCondominiumIds(), true) ? $condominiumId : null;
        }

        $token->forceFill([
            'context_json' => $selected ? ['selected_condominium_id' => $selected] : [],
        ])->save();

        return $selected;
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
