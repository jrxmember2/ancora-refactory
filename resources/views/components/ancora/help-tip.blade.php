@props([
    'text',
])

<span class="group relative inline-flex align-middle">
    <button type="button" tabindex="0" onclick="event.preventDefault(); event.stopPropagation();" class="flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 text-[11px] font-bold text-gray-500 transition hover:border-brand-300 hover:text-brand-600 focus:border-brand-300 focus:text-brand-600 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-700 dark:hover:text-brand-300 dark:focus:border-brand-700 dark:focus:text-brand-300" aria-label="Ajuda do campo">
        ?
    </button>
    <span class="pointer-events-none invisible absolute left-0 top-full z-30 mt-2 w-72 rounded-xl bg-gray-900 px-3 py-2 text-left text-xs leading-5 text-white opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 dark:bg-gray-100 dark:text-gray-900 sm:left-1/2 sm:-translate-x-1/2">
        {{ $text }}
    </span>
</span>
