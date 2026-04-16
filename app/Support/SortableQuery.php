<?php

namespace App\Support;

use Illuminate\Http\Request;

class SortableQuery
{
    public static function apply(mixed $query, Request $request, array $allowed, string $defaultSort, string $defaultDirection = 'asc'): array
    {
        $sort = (string) $request->input('sort', $defaultSort);
        if (!array_key_exists($sort, $allowed)) {
            $sort = $defaultSort;
        }

        $direction = strtolower((string) $request->input('direction', $defaultDirection)) === 'desc' ? 'desc' : 'asc';
        $handler = $allowed[$sort];

        if ($handler instanceof \Closure) {
            $handler($query, $direction);
        } else {
            $query->orderBy((string) $handler, $direction);
        }

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }
}
