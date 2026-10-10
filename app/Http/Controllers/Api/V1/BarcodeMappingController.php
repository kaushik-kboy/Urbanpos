<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BarcodeMappingRequest;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Unknown-barcode mapping requests + authorized review. Master prompt §9: unknown
 * barcodes must never auto-modify master product data; only an explicit approve()
 * call updates items.ean_upc_code, and never silently overwrites an existing mapping.
 */
class BarcodeMappingController extends Controller
{
    private function hasAllBranchAccess(User $user): bool
    {
        return $user->branch_id === null || $user->hasRole('Owner') || $user->email === 'admin@urbanpos.com';
    }

    /**
     * Explicit guard 'web' — see routes/api.php header comment: the 'permission:'
     * middleware alias cannot be used reliably on sanctum-guarded routes because
     * Auth::shouldUse('sanctum') runs on every authenticated api request and breaks
     * spatie/laravel-permission's default-guard permission resolution.
     */
    private function requirePermission(User $user, string $permission): ?\Illuminate\Http\JsonResponse
    {
        if (! $user->hasPermissionTo($permission, 'web')) {
            return response()->json(['message' => 'User does not have the right permissions.'], 403);
        }

        return null;
    }

    public function store(Request $request)
    {
        if ($guard = $this->requirePermission($request->user(), 'barcode-mapping.request')) {
            return $guard;
        }

        $validated = $request->validate([
            'scanned_barcode' => ['required', 'string', 'max:255'],
            'suggested_item_id' => ['nullable', 'integer', 'exists:items,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $branchId = (int) $validated['branch_id'];

        if (! $this->hasAllBranchAccess($user) && (int) $user->branch_id !== $branchId) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        $mappingRequest = BarcodeMappingRequest::create([
            'scanned_barcode' => $validated['scanned_barcode'],
            'suggested_item_id' => $validated['suggested_item_id'] ?? null,
            'branch_id' => $branchId,
            'status' => 'pending',
            'submitted_by' => $user->id,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return response()->json(['data' => $mappingRequest], 201);
    }

    public function index(Request $request)
    {
        if ($guard = $this->requirePermission($request->user(), 'barcode-mapping.review')) {
            return $guard;
        }

        $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $query = BarcodeMappingRequest::query()->with(['suggestedItem', 'branch', 'submitter', 'reviewer']);

        if (! $this->hasAllBranchAccess($user)) {
            $query->where('branch_id', $user->branch_id);
        }
        if ($branchId = $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 20);

        return response()->json($query->orderByDesc('id')->paginate($perPage));
    }

    /**
     * Only approve() ever writes to items.ean_upc_code. If the target item already has
     * a different non-null ean_upc_code, this is rejected unless the caller explicitly
     * passes force=true — preventing an accidental/unauthorized remap onto an item that
     * is already correctly mapped to a different barcode.
     */
    public function approve(Request $request, BarcodeMappingRequest $mappingRequest)
    {
        if ($guard = $this->requirePermission($request->user(), 'barcode-mapping.review')) {
            return $guard;
        }

        $user = $request->user();
        if (! $this->hasAllBranchAccess($user) && (int) $user->branch_id !== (int) $mappingRequest->branch_id) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        $validated = $request->validate([
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'force' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        $itemId = $validated['item_id'] ?? $mappingRequest->suggested_item_id;
        if (! $itemId) {
            return response()->json(['message' => 'No item_id provided and no suggested_item_id on the request.'], 422);
        }

        $item = Item::find($itemId);
        if (! $item) {
            return response()->json(['message' => 'Target item not found.'], 422);
        }

        $force = (bool) ($validated['force'] ?? false);
        $alreadyMapped = $item->ean_upc_code !== null && trim((string) $item->ean_upc_code) !== '';
        $mapsToDifferentBarcode = $alreadyMapped && trim((string) $item->ean_upc_code) !== trim($mappingRequest->scanned_barcode);

        if ($mapsToDifferentBarcode && ! $force) {
            return response()->json([
                'message' => "Item \"{$item->name}\" is already mapped to barcode \"{$item->ean_upc_code}\". Pass force=true to overwrite.",
            ], 422);
        }

        $conflict = DB::transaction(function () use ($mappingRequest, $item, $user, $validated) {
            $locked = BarcodeMappingRequest::whereKey($mappingRequest->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending') {
                return true;
            }

            $item->ean_upc_code = $mappingRequest->scanned_barcode;
            $item->save();

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'remarks' => $validated['remarks'] ?? $locked->remarks,
            ]);

            return false;
        });

        if ($conflict) {
            return response()->json(['message' => 'This mapping request is no longer pending.'], 409);
        }

        return response()->json(['data' => $mappingRequest->fresh()]);
    }

    public function reject(Request $request, BarcodeMappingRequest $mappingRequest)
    {
        if ($guard = $this->requirePermission($request->user(), 'barcode-mapping.review')) {
            return $guard;
        }

        $user = $request->user();
        if (! $this->hasAllBranchAccess($user) && (int) $user->branch_id !== (int) $mappingRequest->branch_id) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $conflict = DB::transaction(function () use ($mappingRequest, $user, $validated) {
            $locked = BarcodeMappingRequest::whereKey($mappingRequest->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending') {
                return true;
            }

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $user->id,
                'remarks' => $validated['remarks'],
            ]);

            return false;
        });

        if ($conflict) {
            return response()->json(['message' => 'This mapping request is no longer pending.'], 409);
        }

        return response()->json(['data' => $mappingRequest->fresh()]);
    }
}
