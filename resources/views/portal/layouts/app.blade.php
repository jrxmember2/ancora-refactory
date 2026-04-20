<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal do Cliente' }} | {{ $ancoraBrand['app_name'] ?? 'Ancora' }}</title>
    @include('layouts.partials.asset-loader')
    <link rel="icon" href="{{ $ancoraBrand['favicon'] ?? '/favicon.ico' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="min-h-screen bg-[#f7f2ec] text-gray-900">
    @php
        $portalUser = $clientPortalUser ?? ($portalUser ?? null);
        $canViewProcesses = $portalUser ? (bool) $portalUser->can_view_processes : false;
        $canViewCobrancas = $portalUser ? (bool) $portalUser->can_view_cobrancas : false;
        $canViewDemands = $portalUser ? (bool) $portalUser->can_view_demands : false;
        $canOpenDemands = $portalUser ? (bool) $portalUser->can_open_demands : false;
        $portalNavItems = array_values(array_filter([
            ['route' => 'portal.dashboard', 'match' => 'portal.dashboard', 'label' => 'Dashboard', 'mobile' => 'Dashboard', 'icon' => 'fa-solid fa-chart-line', 'visible' => true],
            ['route' => 'portal.processes.index', 'match' => 'portal.processes.*', 'label' => 'Processos', 'mobile' => 'Processos', 'icon' => 'fa-solid fa-scale-balanced', 'visible' => $canViewProcesses],
            ['route' => 'portal.cobrancas.index', 'match' => 'portal.cobrancas.*', 'label' => 'Cobrancas', 'mobile' => 'Cobrancas', 'icon' => 'fa-solid fa-money-bill-wave', 'visible' => $canViewCobrancas],
            ['route' => $canViewDemands ? 'portal.demands.index' : 'portal.demands.create', 'match' => 'portal.demands.*', 'label' => 'Solicitacoes', 'mobile' => 'Solicitacoes', 'icon' => 'fa-solid fa-inbox', 'visible' => ($canViewDemands || $canOpenDemands)],
            ['route' => 'portal.account', 'match' => 'portal.account', 'label' => 'Minha Conta', 'mobile' => 'Conta', 'icon' => 'fa-solid fa-user-shield', 'visible' => true],
        ], static function ($item) {
            return $item['visible'];
        }));
    @endphp

    <div class="min-h-screen">
        <header class="border-b border-[#eadfd5] bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ $ancoraBrand['logo_light'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="h-11 w-auto">
                    <div class="hidden sm:block">
                        <div class="text-sm font-semibold text-[#941415]">Portal do Cliente</div>
                        <div class="text-xs text-gray-500">{{ $portalUser ? $portalUser->displayClientName() : 'Area segura' }}</div>
                    </div>
                </a>
                <nav class="hidden items-center gap-2 lg:flex">
                    @foreach($portalNavItems as $item)
                        <a href="{{ route($item['route']) }}" class="rounded-xl px-3 py-2 text-sm font-medium {{ request()->routeIs($item['match']) ? 'bg-[#941415] text-white' : 'text-gray-600 hover:bg-[#f2e8df] hover:text-[#941415]' }}">
                            <i class="{{ $item['icon'] }} mr-2"></i>{{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
                <form method="post" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="rounded-xl border border-[#eadfd5] bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-[#941415] hover:text-[#941415]">Sair</button>
                </form>
            </div>
            <div class="border-t border-[#eadfd5] px-4 py-3 lg:hidden">
                <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto">
                    @foreach($portalNavItems as $item)
                        <a href="{{ route($item['route']) }}" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium {{ request()->routeIs($item['match']) ? 'bg-[#941415] text-white' : 'bg-white text-gray-600' }}">{{ $item['mobile'] }}</a>
                    @endforeach
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded-2xl border border-success-200 bg-success-50 px-5 py-4 text-sm text-success-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700">Revise os campos informados antes de continuar.</div>
            @endif
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
