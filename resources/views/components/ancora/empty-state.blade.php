@props(['icon' => 'fa-solid fa-box-open', 'title' => 'Nada encontrado', 'subtitle' => 'Ainda não há registros para exibir.'])
<div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-white/[0.03]">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300"><i class="{{ $icon }} text-xl"></i></div>
    <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
</div>
