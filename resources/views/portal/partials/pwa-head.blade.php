@php
    $portalAppName = trim((string) ($ancoraBrand['app_name'] ?? 'Ancora'));
    $portalShortName = \Illuminate\Support\Str::limit($portalAppName !== '' ? $portalAppName : 'Ancora', 12, '');
@endphp

<meta name="application-name" content="{{ $portalAppName !== '' ? $portalAppName . ' Portal do Cliente' : 'Portal do Cliente' }}">
<meta name="theme-color" content="#941415">
<meta name="background-color" content="#f7f2ec">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $portalShortName }}">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="{{ route('portal.pwa.manifest') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('pwa/icon-512.png') }}">
<script>
(() => {
    const updateViewportHeight = () => {
        const height = window.visualViewport ? window.visualViewport.height : window.innerHeight;
        document.documentElement.style.setProperty('--portal-vh', `${height}px`);
    };

    updateViewportHeight();
    window.addEventListener('resize', updateViewportHeight, { passive: true });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', updateViewportHeight, { passive: true });
    }

    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(@json(route('portal.pwa.service-worker'))).catch(() => {});
        });
    }
})();
</script>
