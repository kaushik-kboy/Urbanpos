<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API (auth:sanctum) counterpart of App\Http\Middleware\EnsureBranchAccess (which is
 * web-guard and session based). Mirrors the same bypass/check logic for bearer-token
 * mobile app requests: a user with a null branch_id has all-branch access (Owner), the
 * Owner role bypasses outright, and the hard-coded admin email bypasses for parity with
 * the web middleware. Otherwise the request's branch_id (body or query string) or the
 * route-bound model's branch_id must match the user's own branch_id, else 403.
 */
class EnsureApiBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $firstActiveBranchId = (int) (Branch::where('status', true)->orderBy('id')->value('id') ?? 1);

        // Auto-heal branch_id if requested value does not exist in branches table
        $requestedBranchId = $request->input('branch_id') ?? $request->query('branch_id');
        if ($requestedBranchId !== null) {
            $exists = Branch::where('id', $requestedBranchId)->exists();
            if (! $exists) {
                $healedId = ($user->branch_id !== null && Branch::where('id', $user->branch_id)->exists())
                    ? (int) $user->branch_id
                    : $firstActiveBranchId;

                $request->merge(['branch_id' => $healedId]);
                $requestedBranchId = $healedId;
            }
        }

        if ($user->branch_id === null || $user->hasRole('Owner') || $user->email === 'admin@urbanpos.com') {
            return $next($request);
        }

        $userBranchId = (int) $user->branch_id;

        $requestedBranchId = $request->input('branch_id') ?? $request->query('branch_id');
        if ($requestedBranchId !== null) {
            if ((int) $requestedBranchId !== $userBranchId) {
                abort(403, 'You do not have access to this branch.');
            }

            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $param) {
            if (! is_object($param)) {
                continue;
            }

            $candidates = array_filter([
                $param->branch_id ?? null,
            ], fn ($v) => $v !== null);

            if (empty($candidates)) {
                continue;
            }

            if (! in_array($userBranchId, array_map('intval', $candidates), true)) {
                abort(403, 'You do not have access to this branch.');
            }

            return $next($request);
        }

        return $next($request);
    }
}
