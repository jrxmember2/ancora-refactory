@php
    $hotFile = public_path('hot');
    $stableCss = public_path('build/assets/ancora-app.css');
    $stableJs = public_path('build/assets/ancora-app.js');
    $manifestPath = public_path('build/manifest.json');
    $useHot = is_file($hotFile) && app()->environment('local');
@endphp

@if ($useHot)
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@elseif (is_file($stableCss) || is_file($stableJs))
    @if (is_file($stableCss))
        <link rel="stylesheet" href="/build/assets/ancora-app.css?v={{ @filemtime($stableCss) ?: time() }}">
    @endif

    @if (is_file($stableJs))
        <script type="module" src="/build/assets/ancora-app.js?v={{ @filemtime($stableJs) ?: time() }}"></script>
    @endif
@elseif (is_file($manifestPath))
    @php
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $manifest = is_array($manifest) ? $manifest : [];
        $cssFiles = [];
        $jsFiles = [];

        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
            if (!isset($manifest[$entry]) || !is_array($manifest[$entry])) {
                continue;
            }

            $entryData = $manifest[$entry];

            if (!empty($entryData['file'])) {
                $file = '/build/' . ltrim((string) $entryData['file'], '/');
                if (str_ends_with((string) $entryData['file'], '.css')) {
                    $cssFiles[] = $file;
                }
                if (str_ends_with((string) $entryData['file'], '.js')) {
                    $jsFiles[] = $file;
                }
            }

            foreach (($entryData['css'] ?? []) as $cssFile) {
                $cssFiles[] = '/build/' . ltrim((string) $cssFile, '/');
            }
        }

        $cssFiles = array_values(array_unique($cssFiles));
        $jsFiles = array_values(array_unique($jsFiles));
    @endphp

    @foreach ($cssFiles as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach

    @foreach ($jsFiles as $src)
        <script type="module" src="{{ $src }}"></script>
    @endforeach
@endif
