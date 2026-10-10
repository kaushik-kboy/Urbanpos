<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockCountTask;
use App\Models\User;
use App\Support\TaskStatusTransition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * Manager/Owner task assignment + Staff task list, per
 * URBANPETS_MASTER_IMPLEMENTATION_PROMPT.md §9 and URBANPETS_DATABASE_AND_WORKFLOW.md §3/§4.
 */
class TaskController extends Controller
{
    /**
     * Whether $user can see every branch (Owner-tier access), mirroring the bypass
     * logic already used by EnsureApiBranchAccess / BranchController.
     */
    private function hasAllBranchAccess(User $user): bool
    {
        return $user->branch_id === null || $user->hasRole('Owner') || $user->email === 'admin@urbanpos.com';
    }

    private function isReviewer(User $user): bool
    {
        return $user->hasAnyRole(['Manager', 'Owner']);
    }

    /**
     * Explicit guard 'web' — see routes/api.php header comment for why the
     * 'permission:' middleware alias cannot be used reliably on these sanctum-guarded
     * routes (Auth::shouldUse('sanctum') runs on every authenticated api request and
     * breaks the package's own default-guard permission resolution).
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
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.assign-task')) {
            return $guard;
        }

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'title' => ['required', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            // Legacy: item_categories (category HEAD) id — kept for backward compatibility.
            'category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            // Product category, same concept the POS filters items by (items.category_value_id).
            'category_value_id' => ['nullable', 'integer', 'exists:item_category_values,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            // Business rule (2026-10-10): multiple categories/brands are allowed only
            // when EXPLICITLY selected via these array fields — the singular fields
            // above remain the single-value path and are not silently widened.
            'category_value_ids' => ['nullable', 'array', 'max:50'],
            'category_value_ids.*' => ['integer', 'exists:item_category_values,id'],
            'brand_ids' => ['nullable', 'array', 'max:50'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'items' => ['nullable', 'array', 'max:500'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:255'],
        ]);

        // Union of every explicitly-selected category/brand: the singular field (if
        // given) and the array field (if given) are merged, not alternatives.
        $categoryValueIds = collect([$validated['category_value_id'] ?? null])
            ->merge($validated['category_value_ids'] ?? [])
            ->filter()->unique()->values()->all();
        $brandIds = collect([$validated['brand_id'] ?? null])
            ->merge($validated['brand_ids'] ?? [])
            ->filter()->unique()->values()->all();

        // Scope consistency: when a task is scoped by category and/or brand AND explicit
        // products are also selected, every selected product must belong to the UNION
        // of the selected categories/brands. Otherwise the task would carry a
        // contradictory scope (e.g. "Brand: Royal Canin" containing a Whiskas item).
        // One query, not one per item.
        $scopeError = $this->itemScopeError(
            collect($validated['items'] ?? [])->pluck('item_id')->filter()->unique()->values()->all(),
            $categoryValueIds,
            $brandIds,
        );
        if ($scopeError !== null) {
            return response()->json([
                'message' => $scopeError,
                'errors' => ['items' => [$scopeError]],
            ], 422);
        }

        $assignee = User::find($validated['assigned_to']);
        $branchId = (int) $validated['branch_id'];

        $assigneeHasAccess = $assignee->branch_id === null
            || (int) $assignee->branch_id === $branchId
            || $assignee->hasRole('Owner')
            || $assignee->email === 'admin@urbanpos.com';

        if (! $assigneeHasAccess) {
            return response()->json([
                'message' => 'The assigned user does not have access to this branch.',
            ], 422);
        }

        $requester = $request->user();
        if (! $this->hasAllBranchAccess($requester) && (int) $requester->branch_id !== $branchId) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        $task = DB::transaction(function () use ($validated, $requester, $branchId, $categoryValueIds, $brandIds) {
            $task = StockCountTask::create([
                'branch_id' => $branchId,
                'title' => $validated['title'],
                'section' => $validated['section'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                // Singular columns mirror the pivot tables ONLY when exactly one value
                // was selected, so every existing single-category/brand query (incl.
                // index()'s legacy filter) keeps working unchanged.
                'category_value_id' => count($categoryValueIds) === 1 ? $categoryValueIds[0] : null,
                'brand_id' => count($brandIds) === 1 ? $brandIds[0] : null,
                'assigned_to' => $validated['assigned_to'],
                'assigned_by' => $requester->id,
                'due_date' => $validated['due_date'] ?? null,
                'priority' => $validated['priority'] ?? 'normal',
                'status' => 'pending',
            ]);

            if (! empty($categoryValueIds)) {
                $task->categoryValues()->sync($categoryValueIds);
            }
            if (! empty($brandIds)) {
                $task->brands()->sync($brandIds);
            }

            foreach ($validated['items'] ?? [] as $row) {
                if (empty($row['item_id']) && empty($row['batch_no'])) {
                    continue;
                }
                $task->items()->create([
                    'item_id' => $row['item_id'] ?? null,
                    'batch_no' => $row['batch_no'] ?? null,
                ]);
            }

            return $task;
        });

        return response()->json([
            'data' => $task->load(self::DETAIL_RELATIONS)->toArray(),
        ], 201);
    }

    /**
     * Relations eager-loaded for list/detail responses (single query per relation —
     * no N+1). Only id/name columns of users are selected so no password hash,
     * remember token or PIN can ever leak through a nested relation.
     */
    private const DETAIL_RELATIONS = [
        'items.item:id,name,item_code,ean_upc_code',
        'branch:id,name',
        'assignee:id,name,branch_id',
        'assigner:id,name',
        'category:id,name',
        'categoryValue:id,name,item_category_id',
        'brand:id,name',
        'categoryValues:id,name,item_category_id',
        'brands:id,name',
    ];

    /**
     * Returns an error message if any of $itemIds does not match the UNION of the
     * given category_value_ids / brand_ids scope (i.e. an item is in-scope if it
     * matches AT LEAST ONE selected category or brand), else null. Empty arrays mean
     * "no scope restriction on that dimension".
     */
    private function itemScopeError(array $itemIds, array $categoryValueIds, array $brandIds): ?string
    {
        if (empty($itemIds) || (empty($categoryValueIds) && empty($brandIds))) {
            return null;
        }

        $mismatched = Item::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'name', 'category_value_id', 'brand_id'])
            ->filter(function (Item $item) use ($categoryValueIds, $brandIds) {
                $inCategory = ! empty($categoryValueIds) && $item->category_value_id !== null
                    && in_array((int) $item->category_value_id, $categoryValueIds, true);
                $inBrand = ! empty($brandIds) && $item->brand_id !== null
                    && in_array((int) $item->brand_id, $brandIds, true);

                return ! $inCategory && ! $inBrand;
            })
            ->pluck('name');

        if ($mismatched->isEmpty()) {
            return null;
        }

        return 'These selected products do not belong to the chosen category/brand: '
            .$mismatched->take(5)->implode(', ')
            .($mismatched->count() > 5 ? ' (and '.($mismatched->count() - 5).' more)' : '')
            .'.';
    }

    /**
     * GET /v1/tasks/assignees?branch_id= — users a Manager/Owner may assign a count task
     * to for that branch (same branch-access rule store() enforces on assigned_to:
     * the user's own branch matches, or the user has all-branch access). Only users
     * holding a module role (Staff/Manager/Owner) and active accounts are returned;
     * only id/name/role are exposed.
     */
    public function assignees(Request $request)
    {
        $user = $request->user();

        if ($guard = $this->requirePermission($user, 'stock-verification.assign-task')) {
            return $guard;
        }

        $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branchId = (int) $request->query('branch_id');

        if (! $this->hasAllBranchAccess($user) && (int) $user->branch_id !== $branchId) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        $users = User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Staff', 'Manager', 'Owner']))
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'branch_id' => $u->branch_id,
                'role' => $u->roles->pluck('name')->first(),
            ])
            ->values();

        return response()->json(['data' => $users]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'category_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            'category_value_id' => ['nullable', 'integer', 'exists:item_category_values,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $perPage = (int) $request->query('per_page', 20);

        $query = StockCountTask::query()->with(self::DETAIL_RELATIONS);

        if ($this->isReviewer($user)) {
            if (! $this->hasAllBranchAccess($user)) {
                $query->where('branch_id', $user->branch_id);
            }
        } else {
            $query->where('assigned_to', $user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Matches a task whose scope includes the given category/brand, whether it was
        // assigned via the legacy singular column or the multi-select pivot table.
        if ($categoryValueId = $request->query('category_value_id')) {
            $query->where(function ($q) use ($categoryValueId) {
                $q->where('category_value_id', $categoryValueId)
                    ->orWhereHas('categoryValues', fn ($q2) => $q2->where('item_category_values.id', $categoryValueId));
            });
        }

        if ($brandId = $request->query('brand_id')) {
            $query->where(function ($q) use ($brandId) {
                $q->where('brand_id', $brandId)
                    ->orWhereHas('brands', fn ($q2) => $q2->where('brands.id', $brandId));
            });
        }

        $tasks = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($tasks);
    }

    public function show(Request $request, StockCountTask $task)
    {
        $user = $request->user();

        $isAssignee = (int) $task->assigned_to === (int) $user->id;
        $hasBranchAccess = $this->hasAllBranchAccess($user) || (int) $user->branch_id === (int) $task->branch_id;

        if (! $isAssignee && ! $hasBranchAccess) {
            return response()->json(['message' => 'You do not have access to this task.'], 403);
        }

        $data = $task->load(self::DETAIL_RELATIONS)->toArray();

        // Observations on the task, in the shape appropriate to the caller. A blind Staff
        // caller must never receive system_qty_snapshot (master prompt §6) — the hidden
        // model attribute list below guarantees the key is absent, not just null.
        $observations = $task->observations()->orderByDesc('id')->get();
        if (! $this->isReviewer($user)) {
            $observations->each->makeHidden(['system_qty_snapshot']);
        }
        $data['observations'] = $observations->toArray();

        return response()->json(['data' => $data]);
    }

    /**
     * Valid transitions (server-enforced, per URBANPETS_DATABASE_AND_WORKFLOW.md):
     *   pending -> in_progress   (assignee only)
     *   in_progress -> completed (assignee only)
     *   pending -> cancelled     (Manager/Owner only)
     *   in_progress -> cancelled (Manager/Owner only)
     * Anything else is rejected with 422.
     */
    public function updateStatus(Request $request, StockCountTask $task)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed', 'cancelled'])],
        ]);

        $user = $request->user();
        $newStatus = $validated['status'];
        $currentStatus = $task->status;

        $hasBranchAccess = $this->hasAllBranchAccess($user) || (int) $user->branch_id === (int) $task->branch_id;

        $isAssignee = (int) $task->assigned_to === (int) $user->id;
        $isReviewer = $this->isReviewer($user) && $hasBranchAccess;

        $validTransition = TaskStatusTransition::isValid($currentStatus, $newStatus, $isAssignee, $isReviewer);

        if (! $validTransition) {
            // Distinguish "not allowed for anyone" (bad transition) from "not allowed for you"
            // (permission) only loosely — both are client errors; 422 for transition shape
            // issues, 403 reserved for an otherwise-valid transition the caller can't perform.
            if (TaskStatusTransition::isStructurallyValid($currentStatus, $newStatus)) {
                return response()->json(['message' => 'You are not allowed to perform this transition.'], 403);
            }

            return response()->json([
                'message' => "Invalid status transition from '{$currentStatus}' to '{$newStatus}'.",
            ], 422);
        }

        $task->update(['status' => $newStatus]);

        return response()->json(['data' => $task->fresh()]);
    }
}
