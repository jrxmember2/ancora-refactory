<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portal do Cliente' }} | {{ $ancoraBrand['app_name'] ?? 'Ancora' }}</title>
    @include('layouts.partials.asset-loader')
    @include('portal.partials.pwa-head')
    <link rel="icon" href="{{ $ancoraBrand['favicon'] ?? '/favicon.ico' }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        [x-cloak]{display:none!important}
        :root{--portal-vh:100dvh}
        .portal-body{min-height:var(--portal-vh,100dvh)}
        .portal-header{position:sticky;top:0;z-index:40}
        .portal-main-shell{padding-bottom:2rem}
        .portal-mobile-nav{display:none}
        @media (max-width: 1023px){
            .portal-main-shell{padding-bottom:calc(6.75rem + env(safe-area-inset-bottom))}
            .portal-mobile-nav{display:block;position:fixed;left:.75rem;right:.75rem;bottom:calc(.75rem + env(safe-area-inset-bottom));z-index:60}
            .portal-mobile-nav-track{display:grid;grid-template-columns:repeat(var(--portal-mobile-nav-count),minmax(0,1fr));gap:.5rem;border:1px solid #eadfd5;border-radius:1.5rem;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);box-shadow:0 10px 30px rgba(61,22,22,.12);padding:.5rem}
            .portal-mobile-nav-link{display:flex;min-width:0;flex-direction:column;align-items:center;justify-content:center;gap:.3rem;border-radius:1rem;padding:.65rem .4rem;color:#6b7280;font-size:.72rem;font-weight:600;line-height:1.05;text-align:center;transition:all .2s ease}
            .portal-mobile-nav-link i{font-size:1rem}
            .portal-mobile-nav-link.is-active{background:#941415;color:#fff;box-shadow:0 10px 18px rgba(148,20,21,.22)}
            .portal-mobile-nav-link:not(.is-active){background:transparent}
            .portal-mobile-nav-link:active{transform:scale(.98)}
        }
    </style>
    @stack('head')
</head>
<body class="portal-body min-h-screen bg-[#f7f2ec] text-gray-900">
    @php
        $portalUser = $clientPortalUser ?? ($portalUser ?? null);
        $canViewProcesses = $portalUser ? (bool) $portalUser->can_view_processes : false;
        $canViewCobrancas = $portalUser ? (bool) $portalUser->can_view_cobrancas : false;
        $canViewDemands = $portalUser ? (bool) $portalUser->can_view_demands : false;
        $canOpenDemands = $portalUser ? (bool) $portalUser->can_open_demands : false;
        $portalCondominiums = isset($clientPortalCondominiums) ? $clientPortalCondominiums : collect();
        $selectedCondominiumId = isset($clientPortalSelectedCondominiumId) ? $clientPortalSelectedCondominiumId : null;
        $selectedCondominium = isset($clientPortalSelectedCondominium) ? $clientPortalSelectedCondominium : null;
        $aiMenuEnabled = isset($clientPortalAiMenuEnabled) ? (bool) $clientPortalAiMenuEnabled : false;
        $contextLabel = $selectedCondominium ? $selectedCondominium->name : ($portalUser ? $portalUser->displayClientName() : 'Area segura');
        $hasCondominiumSwitcher = $portalCondominiums->count() > 1;
        $isAiChatRoute = request()->routeIs('portal.ai-chat.*');
        $portalNavItems = array_values(array_filter([
            ['route' => 'portal.dashboard', 'match' => 'portal.dashboard', 'label' => 'Dashboard', 'mobile' => 'Dashboard', 'icon' => 'fa-solid fa-chart-line', 'visible' => true],
            ['route' => 'portal.processes.index', 'match' => 'portal.processes.*', 'label' => 'Processos', 'mobile' => 'Processos', 'icon' => 'fa-solid fa-scale-balanced', 'visible' => $canViewProcesses],
            ['route' => 'portal.cobrancas.index', 'match' => 'portal.cobrancas.*', 'label' => 'Cobrancas', 'mobile' => 'Cobrancas', 'icon' => 'fa-solid fa-money-bill-wave', 'visible' => $canViewCobrancas],
            ['route' => $canViewDemands ? 'portal.demands.index' : 'portal.demands.create', 'match' => 'portal.demands.*', 'label' => 'Solicitacoes', 'mobile' => 'Solicitacoes', 'icon' => 'fa-solid fa-inbox', 'visible' => ($canViewDemands || $canOpenDemands)],
            ['route' => 'portal.ai-chat.index', 'match' => 'portal.ai-chat.*', 'label' => 'Leme', 'mobile' => 'Leme', 'icon' => 'fa-solid fa-comments', 'visible' => $aiMenuEnabled],
            ['route' => 'portal.account', 'match' => 'portal.account', 'label' => 'Minha Conta', 'mobile' => 'Conta', 'icon' => 'fa-solid fa-user-shield', 'visible' => true],
        ], static function ($item) {
            return $item['visible'];
        }));
    @endphp

    <div class="min-h-screen">
        <header class="portal-header border-b border-[#eadfd5] bg-white/90 backdrop-blur">
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
                                            @if($isAiChatRoute)
                                                <input type="hidden" name="redirect_to_ai_chat" value="1">
                                            @endif
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
                                                @if($isAiChatRoute)
                                                    <input type="hidden" name="redirect_to_ai_chat" value="1">
                                                @endif
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
        </header>

        <main class="portal-main-shell mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
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

        @if($portalNavItems !== [])
            <nav class="portal-mobile-nav lg:hidden" aria-label="Navegacao principal do portal">
                <div class="portal-mobile-nav-track" style="--portal-mobile-nav-count: {{ max(1, count($portalNavItems)) }};">
                    @foreach($portalNavItems as $item)
                        <a href="{{ route($item['route']) }}" class="portal-mobile-nav-link {{ request()->routeIs($item['match']) ? 'is-active' : '' }}">
                            <i class="{{ $item['icon'] }}"></i>
                            <span>{{ $item['mobile'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </div>
    @stack('scripts')
</body>
</html>
