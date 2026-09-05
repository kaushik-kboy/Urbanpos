<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Laravel's 'date' validation rule only checks that a value parses as a date;
     * it does not normalize it. Columns cast to 'date' still store whatever string
     * is written, so this strips any time component before it reaches the DB.
     */
    protected function normalizeDate(?string $value): ?string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : null;
    }
}
