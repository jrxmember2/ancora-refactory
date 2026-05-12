<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\AncoraSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClientPortalPwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $brand = AncoraSettings::brand();
        $appName = trim((string) ($brand['app_name'] ?? 'Ancora'));
        $portalName = $appName !== '' ? $appName . ' Portal do Cliente' : 'Portal do Cliente';

        return response()
            ->json([
                'id' => '/',
                'name' => $portalName,
                'short_name' => $appName !== '' ? $appName : 'Ancora',
                'description' => 'Acompanhe processos, cobrancas, solicitacoes e o Chat do Sindico com a Leme em uma experiencia otimizada para mobile.',
                'lang' => 'pt-BR',
                'dir' => 'ltr',
                'start_url' => '/',
                'scope' => '/',
                'display' => 'standalone',
                'orientation' => 'portrait',
                'theme_color' => '#941415',
                'background_color' => '#f7f2ec',
                'prefer_related_applications' => false,
                'icons' => [
                    [
                        'src' => asset('pwa/icon-192.png'),
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ],
                    [
                        'src' => asset('pwa/icon-512.png'),
                        'sizes' => '512x512',
                        'type' => 'image/png',
                    ],
                    [
                        'src' => asset('pwa/icon-192-maskable.png'),
                        'sizes' => '192x192',
                        'type' => 'image/png',
                        'purpose' => 'any maskable',
                    ],
                    [
                        'src' => asset('pwa/icon-512-maskable.png'),
                        'sizes' => '512x512',
                        'type' => 'image/png',
                        'purpose' => 'any maskable',
                    ],
                ],
            ])
            ->header('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function serviceWorker(): Response
    {
        $cacheName = 'portal-static-v2.08';
        $script = <<<JS
const CACHE_NAME = '{$cacheName}';
const STATIC_PREFIXES = ['/build/', '/pwa/', '/favicon', '/branding/', '/imgs/'];
const STATIC_EXTENSIONS = ['.css', '.js', '.png', '.jpg', '.jpeg', '.svg', '.ico', '.webp', '.woff', '.woff2'];

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (request.mode === 'navigate' || request.destination === 'document') {
    return;
  }

  const isStaticPrefix = STATIC_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
  const isStaticExtension = STATIC_EXTENSIONS.some((extension) => url.pathname.endsWith(extension));
  if (!isStaticPrefix && !isStaticExtension) {
    return;
  }

  event.respondWith((async () => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    const networkPromise = fetch(request)
      .then((response) => {
        if (response && response.ok) {
          cache.put(request, response.clone());
        }
        return response;
      })
      .catch(() => cached);

    return cached || networkPromise;
  })());
});
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
