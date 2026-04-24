<header class="sticky top-0 z-999 border-b border-gray-200 bg-white/80 backdrop-blur-xl dark:border-gray-800 dark:bg-gray-900/80">
    <div class="flex flex-col items-start justify-between gap-4 px-4 py-4 sm:px-6 xl:flex-row xl:items-center xl:px-8">
        <div class="flex w-full items-center gap-3 xl:w-auto">
            <button @click.stop="$store.sidebar.toggleMobileOpen()" class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-300 xl:hidden">
                <i class="fa-solid fa-bars"></i>
            </button>
            <button @click.stop="$store.sidebar.toggleExpanded()" class="hidden xl:flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-300">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400">{{ $ancoraBrand['company_name'] ?? ($ancoraBrand['app_name'] ?? 'Âncora') }}</p>
                <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title ?? 'Painel' }}</h1>
            </div>
        </div>

        <div class="flex w-full items-center justify-between gap-3 xl:w-auto xl:justify-end">
            <form action="{{ route('busca') }}" method="get" class="hidden xl:block">
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar usuários, clientes, propostas..." class="h-11 w-[360px] rounded-xl border border-gray-200 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-white/[0.03] dark:text-white/90">
                </div>
            </form>

            <div class="flex items-center gap-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-success-200 bg-success-50 px-3 py-2 text-xs font-semibold text-success-700 dark:border-success-900/50 dark:bg-success-500/10 dark:text-success-300">
                    <i class="fa-solid fa-signal"></i>
                    <span>{{ $ancoraOnlineUsersCount ?? 0 }} online</span>
                </div>

                <div class="relative" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white px-3 py-2 text-left shadow-theme-xs transition hover:border-brand-200 hover:bg-brand-50/50 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-900/70 dark:hover:bg-brand-500/10">
                        @if($ancoraAuthUser?->avatar_url)
                            <img src="{{ $ancoraAuthUser->avatar_url }}" alt="{{ $ancoraAuthUser->name }}" class="h-11 w-11 rounded-2xl object-cover">
                        @else
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-sm font-semibold text-white">{{ $ancoraAuthUser?->initials }}</span>
                        @endif

                        <span class="hidden min-w-0 sm:block">
                            <span class="block truncate text-sm font-semibold text-gray-800 dark:text-white/90">{{ $ancoraAuthUser?->name }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $ancoraAuthUser?->email }}</span>
                        </span>

                        <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                    </button>

                    <div x-show="open" x-transition.opacity.duration.120ms @click.outside="open = false" class="absolute right-0 z-50 mt-3 w-72 rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-100 px-2 pb-3 dark:border-gray-800">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $ancoraAuthUser?->name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $ancoraAuthUser?->email }}</div>
                        </div>

                        <div class="mt-2 space-y-1">
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/[0.04]">
                                <i class="fa-solid fa-id-card w-4 text-center text-gray-400"></i>
                                <span>Meus dados</span>
                            </a>

                            <button type="button" @click="open = false; $store.theme.toggle(); window.dispatchEvent(new CustomEvent('ancora-theme-save', { detail: { theme: $store.theme.theme } }));" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/[0.04]">
                                <i class="fa-solid fa-circle-half-stroke w-4 text-center text-gray-400"></i>
                                <span x-text="$store.theme.theme === 'dark' ? 'Tema claro' : 'Tema escuro'"></span>
                            </button>

                            <a href="{{ route('changelog.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/[0.04]">
                                <i class="fa-solid fa-bolt w-4 text-center text-gray-400"></i>
                                <span>Novidades</span>
                            </a>
                        </div>

                        <div class="mt-2 border-t border-gray-100 pt-2 dark:border-gray-800">
                            <form action="{{ route('logout') }}" method="post">
                                @csrf
                                <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-error-600 hover:bg-error-50 dark:text-error-300 dark:hover:bg-error-500/10">
                                    <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
                                    <span>Sair</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
