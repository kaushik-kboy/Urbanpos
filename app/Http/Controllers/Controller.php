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
        if (empty($value)) {
            return null;
        }
        $v = trim((string) $value);
        if (empty($v)) {
            return null;
        }

        // 8 digits like 10012026 (DDMMYYYY)
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Separated DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $v, $m)) {
            $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            return "{$m[3]}-{$month}-{$day}";
        }

        try {
            return \Illuminate\Support\Carbon::parse($v)->format('Y-m-d');
        } catch (\Exception $e) {
            return $v;
        }
    }
}
