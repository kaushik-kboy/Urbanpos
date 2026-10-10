<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $canSeeAll = $user->branch_id === null
            || $user->hasRole('Owner')
            || $user->email === 'admin@urbanpos.com';

        $branches = $canSeeAll
            ? Branch::query()->where('status', true)->orderBy('name')->get(['id', 'name', 'city', 'status'])
            : Branch::query()->where('id', $user->branch_id)->get(['id', 'name', 'city', 'status']);

        return response()->json(['data' => $branches]);
    }
}
