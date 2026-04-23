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
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#f7f2ec] text-gray-900">
    @php
        $portalUser = $clientPortalUser ?? ($portalUser ?? null);
        $canViewProcesses = $portalUser ? (bool) $portalUser->can_view_processes : false;
        $canViewCobrancas = $portalUser ? (bool) $portalUser->can_view_cobrancas : false;
        $canViewDemands = $portalUser ? (bool) $portalUser->can_view_demands : false;
        $canOpenDemands = $portalUser ? (bool) $portalUser->can_open_demands : false;
        $portalCondominiums = isset($clientPortalCondominiums) ? $clientPortalCondominiums : collect();
        $selectedCondominiumId = isset($clientPortalSelectedCondominiumId) ? $clientPortalSelectedCondominiumId : null;
        $selectedCondominium = isset($clientPortalSelectedCondominium) ? $clientPortalSelectedCondominium : null;
        $contextLabel = $selectedCondominium ? $selectedCondominium->name : ($portalUser ? $portalUser->displayClientName() : 'Area segura');
        $hasCondominiumSwitcher = $portalCondominiums->count() > 1;
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
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('portal.dashboard') }}" class="shrink-0">
                        <img src="{{ $ancoraBrand['logo_light'] ?? '/imgs/logomarca.svg' }}" alt="Logo" class="h-11 w-auto">
                    </a>
                    <div class="min-w-0">
                        @if($hasCondominiumSwitcher)
                            <div x-data="{ open: false }" class="relative">
                                <button type="button" @click="open = !open" @keydown.escape.window="open = false" class="flex max-w-[230px] items-center gap-2 rounded-2xl px-2 py-1 text-left transition hover:bg-[#f7f2ec] sm:max-w-sm">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-[#941415]">Portal do Cliente</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $contextLabel }}</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-xs text-[#941415]"></i>
                                </button>
                                <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute left-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-[#eadfd5] bg-white shadow-xl">
                                    <div class="border-b border-[#eadfd5] px-4 py-3">
                                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-[#941415]">Visualizacao</div>
                                        <div class="mt-1 text-xs text-gray-500">Escolha um condominio ou veja todos.</div>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto p-2">
                                        <form method="post" action="{{ route('portal.context.update') }}">
                                            @csrf
                                            <input type="hidden" name="client_condominium_id" value="all">
                                            <button class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-[#f7f2ec] {{ !$selectedCondominiumId ? 'font-semibold text-[#941415]' : 'text-gray-700' }}">
                                                <span>Todos os condominios</span>
                                                @if(!$selectedCondominiumId)
                                                    <i class="fa-solid fa-check text-xs"></i>
                                                @endif
                                            </button>
                                        </form>
                                        @foreach($portalCondominiums as $condominium)
                                            <form method="post" action="{{ route('portal.context.update') }}">
                                                @csrf
                                                <input type="hidden" name="client_condominium_id" value="{{ $condominium->id }}">
                                                <button class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm hover:bg-[#f7f2ec] {{ (int) $selectedCondominiumId === (int) $condominium->id ? 'font-semibold text-[#941415]' : 'text-gray-700' }}">
                                                    <span class="truncate">{{ $condominium->name }}</span>
                                                    @if((int) $selectedCondominiumId === (int) $condominium->id)
                                                        <i class="fa-solid fa-check text-xs"></i>
                                                    @endif
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('portal.dashboard') }}" class="block rounded-2xl px-2 py-1 transition hover:bg-[#f7f2ec]">
                                <div class="text-sm font-semibold text-[#941415]">Portal do Cliente</div>
                                <div class="max-w-[230px] truncate text-xs text-gray-500 sm:max-w-sm">{{ $contextLabel }}</div>
                            </a>
                        @endif
                    </div>
                </div>
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
