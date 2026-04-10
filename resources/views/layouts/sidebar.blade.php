@php($currentPath = request()->path())
<aside id="sidebar"
    class="fixed top-0 left-0 z-99999 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900"
    x-data="{ openSubmenus: {}, isActive(path){ return window.location.pathname === path; }, toggleSubmenu(key){ this.openSubmenus[key] = !this.openSubmenus[key]; } }"
    :class="{ 'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered, 'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered, 'translate-x-0': $store.sidebar.isMobileOpen, '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen }"
    @mouseenter="if(!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">
    <div class="flex items-center py-6" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center min-h-[84px]' : 'justify-center min-h-[84px]'">
        <a href="{{ route('hub') }}" class="flex items-center gap-3 overflow-hidden self-center">
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" src="{{ $ancoraBrand['logo_light'] ?? '/branding/logo-light.svg' }}" alt="Logo" class="w-auto dark:hidden" style="height: {{ max(24, (int) ($ancoraBrand['logo_height_desktop'] ?? 44)) }}px" />
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" src="{{ $ancoraBrand['logo_dark'] ?? '/branding/logo-dark.svg' }}" alt="Logo" class="hidden w-auto dark:block" style="height: {{ max(24, (int) ($ancoraBrand['logo_height_desktop'] ?? 44)) }}px" />
            <div x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen" class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-white shadow-theme-sm">
                <i class="fa-solid fa-anchor text-lg"></i>
            </div>
        </a>
    </div>

    <div class="no-scrollbar flex min-h-0 flex-1 flex-col overflow-y-auto duration-300 ease-linear">
        <nav class="mb-6 flex flex-col gap-6">
            @foreach($ancoraMenuGroups as $groupIndex => $group)
                <div>
                    <h2 class="mb-4 flex text-xs leading-[20px] uppercase tracking-[0.16em] text-gray-400" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : 'justify-start'">
                        <template x-if="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen"><span>{{ $group['title'] }}</span></template>
                        <template x-if="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen"><span>•••</span></template>
                    </h2>
                    <ul class="flex flex-col gap-1">
                        @foreach($group['items'] as $itemIndex => $item)
                            @php($key = $groupIndex.'-'.$itemIndex)
                            <li>
                                @if(!empty($item['subItems']))
                                    <button type="button" @click="toggleSubmenu('{{ $key }}')" class="menu-item group menu-item-inactive">
                                        <span class="menu-item-icon menu-item-icon-inactive"><i class="{{ $item['icon'] }}"></i></span>
                                        <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" class="truncate">{{ $item['label'] }}</span>
                                        <i x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" class="fa-solid fa-chevron-down ml-auto text-xs transition-transform" :class="openSubmenus['{{ $key }}'] ? 'rotate-180' : ''"></i>
                                    </button>
                                    <ul x-show="openSubmenus['{{ $key }}'] || @js(collect($item['subItems'])->contains(fn($sub) => request()->fullUrlIs($sub['path'])) )" class="mt-1 ml-9 space-y-1" x-cloak>
                                        @foreach($item['subItems'] as $subItem)
                                            <li>
                                                <a href="{{ $subItem['path'] }}" class="menu-item {{ request()->fullUrlIs($subItem['path']) ? 'menu-item-active' : 'menu-item-inactive' }}">
                                                    <span class="truncate">{{ $subItem['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <a href="{{ $item['path'] }}" class="menu-item group {{ request()->fullUrlIs($item['path']) ? 'menu-item-active' : 'menu-item-inactive' }}">
                                        <span class="menu-item-icon {{ request()->fullUrlIs($item['path']) ? 'menu-item-icon-active' : 'menu-item-icon-inactive' }}"><i class="{{ $item['icon'] }}"></i></span>
                                        <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" class="truncate">{{ $item['label'] }}</span>
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="mt-auto rounded-2xl border border-gray-200 bg-gray-50/80 p-4 text-xs text-gray-500 dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-300" x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-cloak>
            <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ $ancoraBrand['company_name'] ?? ($ancoraBrand['app_name'] ?? 'Âncora') }}</p>
            @if(!empty($ancoraBrand['slogan']))
                <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $ancoraBrand['slogan'] }}</p>
            @endif
            <div class="mt-3 space-y-1 leading-relaxed">
                @if(!empty($ancoraBrand['company_phone']))<p><i class="fa-solid fa-phone mr-2"></i>{{ $ancoraBrand['company_phone'] }}</p>@endif
                @if(!empty($ancoraBrand['company_email']))<p><i class="fa-solid fa-envelope mr-2"></i>{{ $ancoraBrand['company_email'] }}</p>@endif
                @if(!empty($ancoraBrand['company_address']))<p><i class="fa-solid fa-location-dot mr-2"></i>{{ $ancoraBrand['company_address'] }}</p>@endif
            </div>
            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-800">
                <p class="text-[11px] uppercase tracking-[0.18em] text-gray-400">Powered by</p>
                <a href="{{ $ancoraBrand['powered_by_url'] ?? 'https://serratech.tec.br' }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-2 font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">
                    {{ $ancoraBrand['powered_by_name'] ?? 'Serratech Soluções em TI' }}
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="pointer-events-none absolute bottom-3 left-1.5 z-10 flex items-end">
        <span class="select-none text-[9px] font-medium uppercase tracking-[0.18em] text-gray-300 [writing-mode:vertical-rl] rotate-180 dark:text-gray-600">{{ $ancoraVersion['label'] ?? 'v11 • 09/04/2026' }}</span>
    </div>

</aside>
