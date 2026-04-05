<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Entrar' }} | {{ $ancoraBrand['app_name'] ?? 'Âncora' }}</title>
    @include('layouts.partials.asset-loader')
    <link rel="icon" href="{{ $ancoraBrand['favicon'] ?? '/favicon.ico' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() { this.theme = localStorage.getItem('theme') || 'dark'; this.updateTheme(); },
                theme: 'dark',
                toggle() { this.theme = this.theme === 'light' ? 'dark' : 'light'; localStorage.setItem('theme', this.theme); this.updateTheme(); },
                updateTheme() { document.documentElement.classList.toggle('dark', this.theme === 'dark'); document.body.classList.toggle('dark', this.theme === 'dark'); document.body.classList.toggle('bg-gray-900', this.theme === 'dark'); }
            });
        });
    </script>
</head>
<body x-data="{ loaded: true }">
    <x-common.preloader />
    @yield('content')
    @stack('scripts')
</body>
</html>
