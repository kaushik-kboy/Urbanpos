<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class SwitchBranchController extends Controller
{
    public function __invoke(Request $request, Branch $branch)
    {
        return $this->switch($request, $branch);
    }

    public function switch(Request $request, Branch $branch)
    {
        if ($request->user()->branch_id !== null && (int)$request->user()->branch_id !== (int)$branch->id) {
            abort(403, 'Unauthorized branch switch.');
        }

        $request->session()->put('active_branch_id', $branch->id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
            ]);
        }

        return redirect()->back()->with('status', "Now working in {$branch->name}.");
    }

    public function setActiveBranch(Request $request)
    {
        $branchId = $request->input('branch_id');
        $user = $request->user();

        if ($user && $user->branch_id !== null) {
            $branchId = $user->branch_id;
        }

        $branch = Branch::find($branchId);
        if (! $branch) {
            $branch = Branch::where('status', true)->first();
        }

        if ($branch) {
            $request->session()->put('active_branch_id', $branch->id);

            return response()->json([
                'success' => true,
                'status' => 'ok',
                'branch_id' => $branch->id,
                'active_branch_id' => $branch->id,
                'branch_name' => $branch->name,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Branch not found.'], 404);
    }
}
