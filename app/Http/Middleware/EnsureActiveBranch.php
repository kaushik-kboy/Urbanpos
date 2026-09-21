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
            if ($user->branch_id !== null) {
                // If user is locked to a specific branch, force it
                session(['active_branch_id' => (int) $user->branch_id]);
            } else {
                // User has access to switch branches (Owner/Admin)
                $currentActive = session('active_branch_id');
                if (! $currentActive) {
                    $defaultBranchId = Branch::where('id', 3)->where('status', true)->value('id')
                        ?: (Branch::where('status', true)->orderBy('id')->value('id') ?: 3);
                    session(['active_branch_id' => (int) $defaultBranchId]);
                }
            }
        }

        return $next($request);
    }
}
