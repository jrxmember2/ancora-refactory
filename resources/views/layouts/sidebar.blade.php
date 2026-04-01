@php($currentPath = request()->path())
<aside id="sidebar"
    class="fixed top-0 left-0 z-99999 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out dark:border-gray-800 dark:bg-gray-900"
    x-data="{ openSubmenus: {}, isActive(path){ return window.location.pathname === path; }, toggleSubmenu(key){ this.openSubmenus[key] = !this.openSubmenus[key]; } }"
    :class="{ 'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered, 'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered, 'translate-x-0': $store.sidebar.isMobileOpen, '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen }"
    @mouseenter="if(!$store.sidebar.isExpanded) $store.sidebar.setHovered(true)"
    @mouseleave="$store.sidebar.setHovered(false)">
    <div class="flex pt-8 pb-7" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : 'justify-start'">
        <a href="{{ route('hub') }}" class="flex items-center gap-3 overflow-hidden">
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" src="{{ $ancoraBrand['logo_light'] ?? '/branding/logo-light.svg' }}" alt="Logo" class="w-auto dark:hidden" style="height: {{ max(24, (int) ($ancoraBrand['logo_height_desktop'] ?? 44)) }}px" />
            <img x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" src="{{ $ancoraBrand['logo_dark'] ?? '/branding/logo-dark.svg' }}" alt="Logo" class="hidden w-auto dark:block" style="height: {{ max(24, (int) ($ancoraBrand['logo_height_desktop'] ?? 44)) }}px" />
            <div x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen" class="flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-500 text-white shadow-theme-sm">
                <i class="fa-solid fa-anchor text-lg"></i>
            </div>
        </a>
    </div>

    <div class="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
        <nav class="mb-6 flex flex-col gap-6">
            @foreach($ancoraMenuGroups as $groupIndex => $group)
                <div>
                    <h2 class="mb-4 flex text-xs leading-[20px] uppercase text-gray-400" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'justify-center' : 'justify-start'">
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

        <div class="mt-auto pb-6 text-xs text-gray-400" x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
            <p class="font-medium text-gray-600 dark:text-gray-300">{{ $ancoraBrand['app_name'] ?? 'Âncora' }}</p>
            <p class="mt-1">Core Laravel + TailAdmin</p>
        </div>
    </div>
</aside>
