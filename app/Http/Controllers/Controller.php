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

    /**
     * Resolve the active branch ID for the current request.
     * 1. If user is restricted to a single branch, forces their branch_id.
     * 2. If 'all' is explicitly requested and allowed, returns 'all'.
     * 3. If explicit numeric branch_id is passed in request, returns that int.
     * 4. Otherwise, falls back to session('active_branch_id').
     * 5. If still empty, falls back to first active DB branch (or 1).
     */
    protected function resolveActiveBranchId(?\Illuminate\Http\Request $request = null, bool $allowAll = false): int|string
    {
        $user = auth()->user();
        if ($user && $user->branch_id !== null && ! $user->hasRole('Owner') && $user->email !== 'admin@urbanpos.com') {
            return (int) $user->branch_id;
        }

        $req = $request ?: request();
        $raw = $req ? $req->input('branch_id') : null;

        if ($raw !== null && $raw !== '') {
            if ($allowAll && $raw === 'all') {
                return 'all';
            }
            if (is_numeric($raw)) {
                return (int) $raw;
            }
        }

        $sessionBranch = session('active_branch_id');
        if ($sessionBranch && $sessionBranch !== 'all' && is_numeric($sessionBranch)) {
            return (int) $sessionBranch;
        }

        if ($user && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return (int) (\App\Models\Branch::where('status', true)->value('id') ?: (\App\Models\Branch::value('id') ?? 1));
    }

    /**
     * Resolve branch filter for listing/index queries.
     * Returns branch ID (int), 'all' (string), or active branch ID.
     */
    protected function resolveBranchFilter(?\Illuminate\Http\Request $request = null): int|string
    {
        return $this->resolveActiveBranchId($request, allowAll: true);
    }
}
