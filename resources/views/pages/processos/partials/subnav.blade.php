<div class="mb-6 flex flex-wrap gap-2">
    <a href="{{ route('processos.dashboard') }}" class="rounded-xl px-4 py-2 text-sm font-medium {{ request()->routeIs('processos.dashboard') ? 'bg-brand-500 text-white' : 'border border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-300' }}">Dashboard</a>
    <a href="{{ route('processos.index') }}" class="rounded-xl px-4 py-2 text-sm font-medium {{ request()->routeIs('processos.index') ? 'bg-brand-500 text-white' : 'border border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-300' }}">Lista</a>
    <a href="{{ route('processos.import.index') }}" class="rounded-xl px-4 py-2 text-sm font-medium {{ request()->routeIs('processos.import.*') ? 'bg-brand-500 text-white' : 'border border-gray-200 text-gray-700 dark:border-gray-800 dark:text-gray-300' }}">Importacao</a>
</div>
