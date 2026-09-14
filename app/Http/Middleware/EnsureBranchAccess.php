<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes sensitive actions by outlet (spec §15.1 "scope permissions by company/outlet").
 * A user with a null branch_id has all-branch access (e.g. Owner) and bypasses this
 * entirely. A user with a branch_id is only allowed to act on documents belonging to
 * that branch — checked against the request body for create/dispatch actions, or
 * against the route-bound model's branch field(s) for approve/reject/cancel/receive
 * actions on an existing document. Stock Transfer documents have two branch fields
 * (from/to); a scoped user matching either leg is allowed, since both the dispatching
 * and receiving branch have a legitimate claim on their side of the transfer.
 */
class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->branch_id === null) {
            return $next($request);
        }

        $userBranchId = (int) $user->branch_id;

        $bodyBranchId = $request->input('branch_id') ?? $request->input('from_branch_id');
        if ($bodyBranchId !== null) {
            if ((int) $bodyBranchId !== $userBranchId) {
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
                $param->from_branch_id ?? null,
                $param->to_branch_id ?? null,
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
