@props([
    'field',
    'label',
    'sort' => null,
    'direction' => null,
])

@php
    $currentSort = $sort ?? request('sort');
    $currentDirection = strtolower((string) ($direction ?? request('direction', 'asc'))) === 'desc' ? 'desc' : 'asc';
    $active = $currentSort === $field;
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->except('page'), [
        'sort' => $field,
        'direction' => $nextDirection,
    ]);
    $url = request()->url() . '?' . http_build_query($query);
    $icon = $active
        ? ($currentDirection === 'asc' ? 'fa-solid fa-arrow-up-short-wide' : 'fa-solid fa-arrow-down-wide-short')
        : 'fa-solid fa-sort';
@endphp

<a href="{{ $url }}" class="inline-flex items-center gap-2 transition hover:text-brand-600 dark:hover:text-brand-300">
    <span>{{ $label }}</span>
    <i class="{{ $icon }} text-[10px] {{ $active ? 'text-brand-500' : 'text-gray-400' }}"></i>
</a>
