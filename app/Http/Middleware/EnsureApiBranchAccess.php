<?php

namespace App\Http\Middleware;

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

        if (! $user || $user->branch_id === null || $user->hasRole('Owner') || $user->email === 'admin@urbanpos.com') {
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
