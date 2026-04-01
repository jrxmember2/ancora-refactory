@props(['label', 'value', 'hint' => null, 'icon' => 'fa-solid fa-chart-column'])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]']) }}>
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <h3 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</h3>
            @if($hint)
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
            @endif
        </div>
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
            <i class="{{ $icon }}"></i>
        </div>
    </div>
</div>
