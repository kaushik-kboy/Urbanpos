<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides whitelisted, database-level sorting for listing pages.
 * Works seamlessly with search, filters, pagination, and PaginatesDeep.
 */
trait HasSorting
{
    /**
     * Apply safe, whitelisted database-level sorting to an Eloquent query.
     *
     * @param Builder $query
     * @param array<string, string|callable|\Closure> $allowedSorts Map of sort key => db column / subquery callback
     * @param array<string, string>|string|null $defaultSort Default column and direction, e.g. ['id' => 'desc']
     * @return Builder
     */
    protected function applySorting(Builder $query, array $allowedSorts, array|string|null $defaultSort = ['id' => 'desc']): Builder
    {
        $requestedSort = request()->input('sort');
        $requestedDirection = strtolower((string) request()->input('direction', 'asc'));

        // Whitelist direction strictly to 'asc' or 'desc'
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';

        // Check if user requested a valid whitelisted sort key
        if ($requestedSort && array_key_exists($requestedSort, $allowedSorts)) {
            $sortTarget = $allowedSorts[$requestedSort];

            if (is_callable($sortTarget)) {
                $sortTarget($query, $direction);
            } elseif (is_string($sortTarget)) {
                $query->orderBy($sortTarget, $direction);
            }

            return $query;
        }

        // If no sort or invalid sort, apply default sort
        if ($defaultSort === null) {
            return $query;
        }

        if (is_string($defaultSort)) {
            $defaultSort = [$defaultSort => 'desc'];
        }

        foreach ($defaultSort as $col => $dir) {
            if (is_callable($col)) {
                $col($query, $dir);
            } elseif (is_int($col) && is_string($dir)) {
                $query->orderBy($dir, 'asc');
            } else {
                $query->orderBy($col, strtolower((string) $dir) === 'asc' ? 'asc' : 'desc');
            }
        }

        return $query;
    }
}
