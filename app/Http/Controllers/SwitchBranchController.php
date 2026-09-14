<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class SwitchBranchController extends Controller
{
    /**
     * Only a null-branch_id (all-access) user may switch — a scoped user's session
     * branch always matches their own users.branch_id and isn't user-selectable.
     */
    public function __invoke(Request $request, Branch $branch)
    {
        if ($request->user()->branch_id !== null) {
            abort(403);
        }

        $request->session()->put('active_branch_id', $branch->id);

        return redirect()->back()->with('status', "Now working in {$branch->name}.");
    }
}
