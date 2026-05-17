<?php

namespace App\Services\Mobile;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientPortalLocationResolver
{
    public function resolve(Request $request): array
    {
        $country = $this->headerValue($request, [
            'CF-IPCountry',
            'X-AppEngine-Country',
            'X-Client-Country',
            'X-Country-Code',
        ]);
        $region = $this->headerValue($request, [
            'X-AppEngine-Region',
            'X-Client-Region',
            'X-Region',
        ]);
        $city = $this->headerValue($request, [
            'X-AppEngine-City',
            'X-Client-City',
            'X-City',
        ]);

        $parts = array_values(array_filter([$city, $region, $country]));

        return [
            'country' => $this->normalize($country, 80, true),
            'region' => $this->normalize($region, 120),
            'city' => $this->normalize($city, 120),
            'location_label' => $parts !== [] ? Str::limit(implode(' / ', $parts), 190, '') : null,
            'location_source' => $parts !== [] ? 'proxy_headers' : null,
        ];
    }

    private function headerValue(Request $request, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $request->header($candidate, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalize(?string $value, int $limit, bool $uppercase = false): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if ($uppercase) {
            $value = mb_strtoupper($value);
        }

        return Str::limit($value, $limit, '');
    }
}
