@php
    $hotFile = public_path('hot');
    $manifestPath = public_path('build/manifest.json');
    $stableCss = public_path('build/assets/ancora-app.css');
    $stableJs = public_path('build/assets/ancora-app.js');
    $usingHot = file_exists($hotFile) && app()->environment('local');
@endphp

@if($usingHot)
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    @php
        $manifest = [];
        if (file_exists($manifestPath)) {
            $decoded = json_decode(file_get_contents($manifestPath), true);
            $manifest = is_array($decoded) ? $decoded : [];
        }

        $cssEntry = $manifest['resources/css/app.css'] ?? null;
        $jsEntry = $manifest['resources/js/app.js'] ?? null;
        $cssFiles = [];
        $jsFiles = [];

        if (is_array($cssEntry)) {
            if (!empty($cssEntry['file'])) {
                $cssFiles[] = asset('build/' . ltrim($cssEntry['file'], '/'));
            }
            foreach (($cssEntry['css'] ?? []) as $cssFile) {
                $cssFiles[] = asset('build/' . ltrim($cssFile, '/'));
            }
        }

        if (is_array($jsEntry) && !empty($jsEntry['file'])) {
            $jsFiles[] = asset('build/' . ltrim($jsEntry['file'], '/'));
            foreach (($jsEntry['css'] ?? []) as $cssFile) {
                $cssFiles[] = asset('build/' . ltrim($cssFile, '/'));
            }
        }

        $cssFiles = array_values(array_unique(array_filter($cssFiles)));
        $jsFiles = array_values(array_unique(array_filter($jsFiles)));

        if (empty($cssFiles) && file_exists($stableCss)) {
            $cssFiles[] = asset('build/assets/ancora-app.css');
        }

        if (empty($jsFiles) && file_exists($stableJs)) {
            $jsFiles[] = asset('build/assets/ancora-app.js');
        }
    @endphp

    @foreach($cssFiles as $cssFile)
        <link rel="stylesheet" href="{{ $cssFile }}">
    @endforeach

    @foreach($jsFiles as $jsFile)
        <script type="module" src="{{ $jsFile }}"></script>
    @endforeach
@endif
