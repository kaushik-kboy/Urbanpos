<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * paginate() that stays flat at any depth.
 *
 * Plain `LIMIT 20 OFFSET n` fetches and discards n full rows (one primary-key lookup each): measured on 2.75 lakh
 * bills it is 3 ms at page 1, 90 ms at offset 3,000 and 636 ms at offset 9,980. A "deferred join" finds the 20 ids from
 * the index alone (offset skipping is index-only) and only then loads those 20 rows: ~80 ms at ANY depth.
 * Shallow pages keep the plain query (it is faster there, ~2 ms), so the switch only happens past deepOffset().
 *
 * Same contract as paginate(): returns a LengthAwarePaginator (total, page links, withQueryString), so views and
 * callers do not change. The $query must already carry its filters and ORDER BY; pass an Eloquent builder.
 */
trait PaginatesDeep
{
    /** Offsets below this stay on the plain query (measured crossover is ~3,000). Overridable for tests. */
    protected function deepOffset(): int
    {
        return 2000;
    }

    protected function paginateDeep(Builder $query, int $perPage = 20): LengthAwarePaginatorContract
    {
        $page = max(1, Paginator::resolveCurrentPage());

        if (($page - 1) * $perPage < $this->deepOffset()) {
            return $query->paginate($perPage)->withQueryString();
        }

        $total = (clone $query)->toBase()->getCountForPagination();

        // pluck() selects only the key: no hydration, no eager loads, index-only offset skip.
        $ids = (clone $query)->forPage($page, $perPage)->pluck($query->getModel()->getKeyName())->all();

        $items = collect();
        if ($ids !== []) {
            $order = array_flip($ids);
            $items = $query->getModel()->newQuery()
                ->with($query->getEagerLoads())
                ->whereKey($ids)
                ->get()
                ->sortBy(fn ($row) => $order[$row->getKey()])
                ->values();
        }

        return (new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]))->withQueryString();
    }
}
