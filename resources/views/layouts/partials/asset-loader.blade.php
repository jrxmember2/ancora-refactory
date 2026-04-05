@php
    $manifestPath = public_path('build/manifest.json');
    $cssFiles = [];
    $jsFiles = [];

    if (is_file($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];

        $pushAsset = function (string $path, string $type) use (&$cssFiles, &$jsFiles) {
            $normalized = '/' . ltrim($path, '/');
            if ($type === 'css' && !in_array($normalized, $cssFiles, true)) {
                $cssFiles[] = $normalized;
            }
            if ($type === 'js' && !in_array($normalized, $jsFiles, true)) {
                $jsFiles[] = $normalized;
            }
        };

        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
            $chunk = $manifest[$entry] ?? null;
            if (!$chunk) {
                continue;
            }

            if ($entry === 'resources/css/app.css') {
                if (!empty($chunk['file'])) {
                    $pushAsset('build/' . ltrim($chunk['file'], '/'), 'css');
                }
                continue;
            }

            if (!empty($chunk['file'])) {
                $pushAsset('build/' . ltrim($chunk['file'], '/'), 'js');
            }

            foreach (($chunk['css'] ?? []) as $css) {
                $pushAsset('build/' . ltrim($css, '/'), 'css');
            }
        }
    }

    if (empty($cssFiles) && file_exists(public_path('build/assets/ancora-app.css'))) {
        $cssFiles[] = '/build/assets/ancora-app.css';
    }

    if (empty($jsFiles) && file_exists(public_path('build/assets/ancora-app.js'))) {
        $jsFiles[] = '/build/assets/ancora-app.js';
    }
@endphp

@foreach($cssFiles as $href)
    <link rel="stylesheet" href="{{ $href }}?v={{ @filemtime(public_path(ltrim($href, '/'))) ?: time() }}">
@endforeach

@foreach($jsFiles as $src)
    <script type="module" src="{{ $src }}?v={{ @filemtime(public_path(ltrim($src, '/'))) ?: time() }}"></script>
@endforeach
