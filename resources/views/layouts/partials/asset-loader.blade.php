@php
    $viteEntries = ['resources/css/app.css', 'resources/js/app.js'];
    $hotFile = public_path('hot');
    $manifestPath = public_path('build/manifest.json');
    $manifest = file_exists($manifestPath)
        ? json_decode((string) file_get_contents($manifestPath), true)
        : [];

    $appCssEntry = $manifest['resources/css/app.css']['file'] ?? null;
    $appJsEntry = $manifest['resources/js/app.js']['file'] ?? null;
    $appJsCssEntries = $manifest['resources/js/app.js']['css'] ?? [];

    $stableCss = 'build/assets/ancora-app.css';
    $stableExtraCss = 'build/assets/ancora-app-extra.css';
    $stableJs = 'build/assets/ancora-app.js';
@endphp

@if (app()->environment('local') && file_exists($hotFile))
    @vite($viteEntries)
@elseif ($appCssEntry || $appJsEntry)
    @if ($appCssEntry)
        <link rel="stylesheet" href="{{ asset('build/' . ltrim($appCssEntry, '/')) }}">
    @endif

    @foreach ($appJsCssEntries as $cssFile)
        <link rel="stylesheet" href="{{ asset('build/' . ltrim($cssFile, '/')) }}">
    @endforeach

    @if ($appJsEntry)
        <script type="module" src="{{ asset('build/' . ltrim($appJsEntry, '/')) }}"></script>
    @endif
@else
    @if (file_exists(public_path($stableCss)))
        <link rel="stylesheet" href="{{ asset($stableCss) }}">
    @endif

    @if (file_exists(public_path($stableExtraCss)))
        <link rel="stylesheet" href="{{ asset($stableExtraCss) }}">
    @endif

    @if (file_exists(public_path($stableJs)))
        <script type="module" src="{{ asset($stableJs) }}"></script>
    @endif
@endif
