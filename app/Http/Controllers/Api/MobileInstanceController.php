<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AncoraSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileInstanceController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'app' => 'ancora',
            'status' => 'online',
        ]);
    }

    public function instanceInfo(Request $request): JsonResponse
    {
        try {
            $portal = $this->resolvePortalUrls();

            if (!$portal['enabled']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portal do Cliente indisponivel nesta instancia.',
                ], 503);
            }

            $brand = AncoraSettings::brand();
            $instanceName = trim((string) ($brand['company_name'] ?? $brand['app_name'] ?? ''));
            $instanceName = $instanceName !== '' ? $instanceName : 'Ancora';

            $logoPath = trim((string) ($brand['logo_light'] ?? $brand['favicon'] ?? '/imgs/logomarca.svg'));
            if ($logoPath === '') {
                $logoPath = '/imgs/logomarca.svg';
            }

            return response()->json([
                'success' => true,
                'app' => 'ancora',
                'portal_enabled' => true,
                'instance_name' => $instanceName,
                'portal_url' => $portal['portal_url'],
                'login_url' => $portal['login_url'],
                'logo_url' => $this->absoluteAssetUrl($logoPath, $portal['portal_url']),
                'primary_color' => '#941415',
                'support_email' => trim((string) ($brand['company_email'] ?? '')),
                'support_phone' => trim((string) ($brand['company_phone'] ?? '')),
                'version' => (string) config('ancora_version.current.version', 'v0.0.0'),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Nao foi possivel obter informacoes desta instancia agora.',
            ], 500);
        }
    }

    /** @return array{enabled:bool,portal_url:?string,login_url:?string} */
    private function resolvePortalUrls(): array
    {
        $portalDomain = trim((string) config('app.client_portal_domain', ''));
        if ($portalDomain === '') {
            return [
                'enabled' => false,
                'portal_url' => null,
                'login_url' => null,
            ];
        }

        try {
            $loginUrl = route('portal.login');
        } catch (\Throwable) {
            return [
                'enabled' => false,
                'portal_url' => null,
                'login_url' => null,
            ];
        }

        $portalUrl = $this->portalBaseUrlFromLogin($loginUrl);

        return [
            'enabled' => $portalUrl !== null && $loginUrl !== '',
            'portal_url' => $portalUrl,
            'login_url' => $loginUrl !== '' ? $loginUrl : null,
        ];
    }

    private function portalBaseUrlFromLogin(string $loginUrl): ?string
    {
        $parts = parse_url($loginUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $portalPath = Str::endsWith($path, '/login')
            ? Str::beforeLast($path, '/login')
            : $path;

        if ($portalPath === '') {
            $portalPath = '/';
        }

        if (!str_starts_with($portalPath, '/')) {
            $portalPath = '/' . $portalPath;
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }

        return $portalPath === '/' ? $base . '/' : $base . $portalPath;
    }

    private function absoluteAssetUrl(string $path, ?string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $relative = '/' . ltrim($path, '/');
        $parts = $baseUrl ? parse_url($baseUrl) : null;

        if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }

            return $origin . $relative;
        }

        return url($relative);
    }
}
