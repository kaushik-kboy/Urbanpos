<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $firstActiveBranch = Branch::where('status', true)->orderBy('id')->first();
            $firstActiveBranchId = $firstActiveBranch ? (int) $firstActiveBranch->id : 1;

            $isGlobalAdmin = $user->branch_id === null
                || $user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator'])
                || $user->email === 'admin@urbanpos.com';

            if (! $isGlobalAdmin && $user->branch_id !== null) {
                // If user is locked to a specific branch, force it if valid, else first active
                $userBranchValid = Branch::where('id', $user->branch_id)->where('status', true)->exists();
                $activeId = $userBranchValid ? (int) $user->branch_id : $firstActiveBranchId;
                session(['active_branch_id' => $activeId]);
            } else {
                // User has access to switch branches (Owner/Admin)
                $currentActive = session('active_branch_id');
                $isValid = $currentActive && Branch::where('id', $currentActive)->where('status', true)->exists();
                if (! $isValid) {
                    session(['active_branch_id' => $firstActiveBranchId]);
                }
            }

            // Auto-heal incoming query/filter branch_id if present but invalid/stale/empty on GET
            if ($request->isMethod('GET') && $request->has('branch_id')) {
                $rawBranch = $request->input('branch_id');
                if (empty($rawBranch) || $rawBranch === 'all' || $rawBranch === '0' || ! Branch::where('id', $rawBranch)->where('status', true)->exists()) {
                    $healedBranchId = session('active_branch_id') ?: $firstActiveBranchId;
                    $request->merge(['branch_id' => $healedBranchId]);
                }
            }
        }

        return $next($request);
    }
}
