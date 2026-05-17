<?php

namespace App\Services\Mobile;

use App\Models\ClientPortalApiToken;
use App\Models\ClientPortalAppLoginLog;
use App\Models\ClientPortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientPortalAppLoginLogService
{
    public function __construct(
        private readonly ClientPortalLocationResolver $locationResolver,
    ) {
    }

    public function recordLogin(
        ClientPortalUser $user,
        ClientPortalApiToken $token,
        Request $request,
        array $meta = [],
    ): void {
        $location = $this->locationResolver->resolve($request);

        ClientPortalAppLoginLog::query()->create([
            'client_portal_user_id' => $user->id,
            'client_portal_api_token_id' => $token->id,
            'platform' => $this->nullableString($meta['platform'] ?? 'android', 20) ?? 'android',
            'device_name' => $this->nullableString($meta['device_name'] ?? null, 160),
            'app_version' => $this->nullableString($meta['app_version'] ?? null, 40),
            'ip_address' => $this->nullableString($request->ip(), 45),
            'user_agent' => $this->nullableString($request->userAgent(), 255),
            'country' => $location['country'],
            'region' => $location['region'],
            'city' => $location['city'],
            'location_label' => $location['location_label'],
            'location_source' => $location['location_source'],
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
