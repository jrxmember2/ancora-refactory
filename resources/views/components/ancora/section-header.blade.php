@props(['title', 'subtitle' => null])
<div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
