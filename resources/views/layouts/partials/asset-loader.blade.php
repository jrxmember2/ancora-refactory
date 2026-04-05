@if (is_file(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
@php
    $manifestPath = public_path('build/manifest.json');
    $manifest = [];
    if (is_file($manifestPath)) {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($decoded)) {
            $manifest = $decoded;
        }
    }

    $cssFiles = [];
    $jsFiles = [];

    foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
        if (!isset($manifest[$entry])) {
            continue;
        }

        $entryData = $manifest[$entry];
        if (($entryData['file'] ?? null) && str_ends_with((string) $entryData['file'], '.js')) {
            $jsFiles[] = '/build/' . ltrim($entryData['file'], '/');
        }

        foreach (($entryData['css'] ?? []) as $cssFile) {
            $cssFiles[] = '/build/' . ltrim((string) $cssFile, '/');
        }
    }

    if (empty($cssFiles) && is_file(public_path('build/assets/ancora-app.css'))) {
        $cssFiles[] = '/build/assets/ancora-app.css';
    }

    if (empty($jsFiles) && is_file(public_path('build/assets/ancora-app.js'))) {
        $jsFiles[] = '/build/assets/ancora-app.js';
    }

    $cssFiles = array_values(array_unique($cssFiles));
    $jsFiles = array_values(array_unique($jsFiles));
@endphp

@foreach($cssFiles as $href)
    <link rel="stylesheet" href="{{ $href }}">
@endforeach

@foreach($jsFiles as $src)
    <script type="module" src="{{ $src }}"></script>
@endforeach

@endif
