<?php

namespace App\Http\Controllers\Concerns;

/**
 * Lets index() pull the page size from ?per_page=, restricted to a fixed
 * set of options so users can't request unbounded result sets.
 */
trait HasPerPage
{
    /** @return array<int> */
    protected function perPageOptions(): array
    {
        return [10, 20, 50, 100];
    }

    protected function perPage(int $default = 20): int
    {
        $requested = (int) request('per_page', $default);

        return in_array($requested, $this->perPageOptions(), true) ? $requested : $default;
    }
}
