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
                <p class="text-xs uppercase tracking-[0.2em] text-gray-400">{{ $ancoraBrand['app_name'] ?? 'Âncora' }}</p>
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
                <button @click="$store.theme.toggle()" class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    <i class="fa-solid fa-moon hidden dark:block"></i>
                    <i class="fa-solid fa-sun dark:hidden"></i>
                </button>
                <div class="hidden sm:block text-right">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $ancoraAuthUser?->name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $ancoraAuthUser?->email }}</p>
                </div>
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-sm transition hover:bg-brand-600">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="hidden sm:inline">Sair</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
