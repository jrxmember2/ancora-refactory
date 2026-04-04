<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Painel' }} | {{ $ancoraBrand['app_name'] ?? 'Âncora' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" href="{{ $ancoraBrand['favicon'] ?? '/favicon.ico' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme') || '{{ $ancoraAuthUser?->theme_preference ?? 'dark' }}';
                    this.theme = savedTheme === 'light' ? 'light' : 'dark';
                    this.updateTheme();
                },
                theme: 'dark',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });
            Alpine.store('sidebar', {
                isExpanded: window.innerWidth >= 1280,
                isMobileOpen: false,
                isHovered: false,
                toggleExpanded() { this.isExpanded = !this.isExpanded; this.isMobileOpen = false; },
                toggleMobileOpen() { this.isMobileOpen = !this.isMobileOpen; },
                setMobileOpen(val) { this.isMobileOpen = val; },
                setHovered(val) { if (window.innerWidth >= 1280 && !this.isExpanded) this.isHovered = val; }
            });
        });
    </script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || '{{ $ancoraAuthUser?->theme_preference ?? 'dark' }}';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-gray-900');
            }
        })();
    </script>
</head>
<body class="{{ request()->routeIs('clientes.*') ? 'clientes-page' : '' }}" x-data="{ loaded: true }" x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280; window.addEventListener('resize', () => { if(window.innerWidth < 1280){ $store.sidebar.setMobileOpen(false); $store.sidebar.isExpanded = false; } else { $store.sidebar.isMobileOpen = false; $store.sidebar.isExpanded = true; } });">
    <x-common.preloader />
    <div class="min-h-screen xl:flex">
        @include('layouts.backdrop')
        @include('layouts.sidebar')
        <div class="flex-1 transition-all duration-300 ease-in-out" :class="{ 'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered, 'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered, 'ml-0': $store.sidebar.isMobileOpen }">
            @include('layouts.app-header')
            <main class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @if(session('success'))
                    <div class="mb-6 rounded-2xl border border-success-200 bg-success-50 px-5 py-4 text-sm text-success-700 dark:border-success-900/40 dark:bg-success-950/30 dark:text-success-300">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300">{{ session('error') }}</div>
                @endif
                @if(session('errors_list'))
                    <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300">
                        <ul class="space-y-1 list-disc list-inside">
                            @foreach(session('errors_list') as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
