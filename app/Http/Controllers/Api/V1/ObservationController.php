<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryObservation;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockCountTask;
use App\Models\User;
use App\Services\Inventory\BatchStockService;
use App\Services\Inventory\StockLedgerService;
use App\Support\IdempotentSubmissionOutcome;
use App\Support\ObservationReviewEligibility;
use App\Support\ObservationVariance;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Blind stock-count + expiry observation submission, staff history, manager review
 * queue and approve/recount/reject actions. See URBANPETS_DATABASE_AND_WORKFLOW.md §4/§5
 * and URBANPETS_MASTER_IMPLEMENTATION_PROMPT.md §6/§9 for the rules this implements.
 */
class ObservationController extends Controller
{
    public function __construct(
        private BatchStockService $batchStockService,
        private StockLedgerService $stockLedgerService,
    ) {
    }

    private function hasAllBranchAccess(User $user): bool
    {
        return $user->branch_id === null || $user->hasRole('Owner') || $user->email === 'admin@urbanpos.com';
    }

    private function isReviewer(User $user): bool
    {
        return $user->hasAnyRole(['Manager', 'Owner']);
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

    /**
     * Current system quantity for the item/branch(/batch), read fresh (not cached) —
     * used both to snapshot at submission time and to re-validate at approval time.
     */
    private function currentStock(int $itemId, int $branchId, ?string $batchNo): float
    {
        if ($batchNo) {
            $batch = $this->batchStockService->getBatchStock($itemId, $branchId, $batchNo);

            return $batch ? (float) $batch['remaining_qty'] : 0.0;
        }

        $stock = ItemStock::where('item_id', $itemId)->where('branch_id', $branchId)->first();

        return $stock ? (float) $stock->quantity : 0.0;
    }

    /**
     * Shape returned to the submitter — NEVER includes system_qty_snapshot or any
     * current-system-quantity field (master prompt §6: blind counting must not expose
     * it even behind a "hidden" UI field).
     */
    private function submitterShape(InventoryObservation $o): array
    {
        return [
            'id' => $o->id,
            'type' => $o->type,
            'task_id' => $o->task_id,
            'item_id' => $o->item_id,
            'branch_id' => $o->branch_id,
            'batch_no' => $o->batch_no,
            'batch_correction_applied' => $o->batch_correction_applied,
            'original_batch_no' => $o->original_batch_no,
            'batch_correction_reason' => $o->batch_correction_reason,
            'exp_date_observed' => optional($o->exp_date_observed)->toDateString(),
            'physical_qty' => $o->physical_qty,
            'remarks' => $o->remarks,
            'photo_path' => $o->photo_path,
            'idempotency_key' => $o->idempotency_key,
            'status' => $o->status,
            'parent_observation_id' => $o->parent_observation_id,
            'approved_qty' => $o->approved_qty,
            'actual_adjustment_qty' => $o->actual_adjustment_qty,
            'submitted_by' => $o->submitted_by,
            'reviewed_by' => $o->reviewed_by,
            'review_remarks' => $o->review_remarks,
            'reviewed_at' => optional($o->reviewed_at)->toIso8601String(),
            'created_at' => optional($o->created_at)->toIso8601String(),
            'updated_at' => optional($o->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Shape returned to a reviewer — includes system_qty_snapshot and a computed variance.
     */
    private function reviewerShape(InventoryObservation $o): array
    {
        $data = $this->submitterShape($o);
        $data['system_qty_snapshot'] = $o->system_qty_snapshot;
        $data['variance'] = ObservationVariance::compute(
            $o->physical_qty !== null ? (float) $o->physical_qty : null,
            $o->system_qty_snapshot !== null ? (float) $o->system_qty_snapshot : null,
        );

        return $data;
    }

    public function store(Request $request)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.submit')) {
            return $guard;
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['stock_count', 'expiry_check'])],
            'task_id' => ['nullable', 'integer', 'exists:stock_count_tasks,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'batch_no' => ['nullable', 'string', 'max:255'],
            'exp_date_observed' => ['nullable', 'date'],
            'physical_qty' => ['nullable', 'numeric', 'required_if:type,stock_count'],
            'remarks' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'idempotency_key' => ['required', 'uuid'],
            'parent_observation_id' => ['nullable', 'integer', 'exists:inventory_observations,id'],
            // Business rule: a recount preserves the parent's batch_no by default.
            // Changing it is an explicit, authorized, audited action — see below.
            'batch_correction' => ['nullable', 'boolean'],
            'batch_correction_reason' => ['required_if:batch_correction,true', 'string', 'max:255'],
        ]);

        if ($validated['type'] === 'expiry_check' && empty($validated['batch_no']) && empty($validated['exp_date_observed'])) {
            throw ValidationException::withMessages([
                'exp_date_observed' => ['exp_date_observed is required for an expiry_check with no batch_no to resolve one from.'],
            ]);
        }

        $user = $request->user();
        $branchId = (int) $validated['branch_id'];

        if (! $this->hasAllBranchAccess($user) && (int) $user->branch_id !== $branchId) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        // Idempotency: a retried POST with the same key returns the existing row (200),
        // never creates a duplicate.
        $existing = InventoryObservation::where('idempotency_key', $validated['idempotency_key'])->first();
        if ($existing) {
            return response()->json(
                ['data' => $this->submitterShape($existing)],
                IdempotentSubmissionOutcome::statusFor(rowAlreadyExisted: true)
            );
        }

        $batchCorrectionApplied = false;
        $originalBatchNo = null;
        $batchCorrectionReason = null;

        if (! empty($validated['parent_observation_id'])) {
            $parent = InventoryObservation::find($validated['parent_observation_id']);
            if (
                ! $parent
                || $parent->status !== 'recount_requested'
                || (int) $parent->item_id !== (int) $validated['item_id']
                || (int) $parent->branch_id !== $branchId
            ) {
                return response()->json([
                    'message' => 'parent_observation_id must reference a recount_requested observation for the same item and branch.',
                ], 422);
            }

            // A caller who doesn't send batch_no at all is not "attempting a change" —
            // it just defaults to the parent's. Only an EXPLICITLY sent, DIFFERENT
            // batch_no counts as a divergence that needs the correction flag.
            $batchNoKeyProvided = $request->exists('batch_no');
            $submittedBatchNo = $validated['batch_no'] ?? null;
            $batchCorrectionRequested = ! empty($validated['batch_correction']);

            if ($batchNoKeyProvided && $submittedBatchNo !== $parent->batch_no) {
                if (! $batchCorrectionRequested) {
                    // Default behaviour: a recount preserves the original batch_no.
                    // Silently diverging from it without the explicit flag is rejected,
                    // not auto-corrected, so the discrepancy is visible to the submitter.
                    return response()->json([
                        'message' => 'A recount must keep the original batch_no unless an authorized batch correction is explicitly submitted.',
                        'errors' => ['batch_no' => ['Does not match the parent observation\'s batch_no. Set batch_correction=true with a reason to override.']],
                    ], 422);
                }

                $batchCorrectionApplied = true;
                $originalBatchNo = $parent->batch_no;
                $batchCorrectionReason = $validated['batch_correction_reason'];
            } else {
                // batch_no matches the parent (or both are null) — nothing to correct,
                // even if batch_correction=true was sent (no-op, not an error).
                $validated['batch_no'] = $parent->batch_no;
            }
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('observations', 'public');
        }

        $systemQtySnapshot = $this->currentStock($validated['item_id'], $branchId, $validated['batch_no'] ?? null);

        try {
            $observation = InventoryObservation::create([
                'type' => $validated['type'],
                'task_id' => $validated['task_id'] ?? null,
                'item_id' => $validated['item_id'],
                'branch_id' => $branchId,
                'batch_no' => $validated['batch_no'] ?? null,
                'batch_correction_applied' => $batchCorrectionApplied,
                'original_batch_no' => $originalBatchNo,
                'batch_correction_reason' => $batchCorrectionReason,
                'exp_date_observed' => $validated['exp_date_observed'] ?? null,
                'physical_qty' => $validated['physical_qty'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'photo_path' => $photoPath,
                'idempotency_key' => $validated['idempotency_key'],
                'system_qty_snapshot' => $systemQtySnapshot,
                'status' => 'pending',
                'parent_observation_id' => $validated['parent_observation_id'] ?? null,
                'submitted_by' => $user->id,
            ]);
        } catch (QueryException $e) {
            // Unique idempotency_key race: another request with the same key won the
            // insert first. Return that row instead of erroring.
            $existing = InventoryObservation::where('idempotency_key', $validated['idempotency_key'])->first();
            if ($existing) {
                return response()->json(
                    ['data' => $this->submitterShape($existing)],
                    IdempotentSubmissionOutcome::statusFor(rowAlreadyExisted: true)
                );
            }
            throw $e;
        }

        return response()->json(
            ['data' => $this->submitterShape($observation)],
            IdempotentSubmissionOutcome::statusFor(rowAlreadyExisted: false)
        );
    }

    /** Validation rules shared by the history-style list endpoints (mine / index). */
    private function historyFilterRules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(['stock_count', 'expiry_check'])],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'recount_requested'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Applies the optional date-range/type/status filters. date_from/date_to filter on
     * created_at (submission time) — see index() docblock for why not exp_date_observed.
     */
    private function applyHistoryFilters($query, Request $request): void
    {
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
    }

    /**
     * Adds display names (item/branch/submitter/reviewer) to a shape. Relations must
     * be eager-loaded by the caller (no N+1). Names only — never a quantity.
     */
    private function withNames(array $shape, InventoryObservation $o): array
    {
        $shape['item_name'] = $o->item?->name;
        $shape['item_code'] = $o->item?->item_code;
        $shape['branch_name'] = $o->branch?->name;
        $shape['submitted_by_name'] = $o->submitter?->name;
        $shape['reviewed_by_name'] = $o->reviewer?->name;

        return $shape;
    }

    /** Column-restricted eager loads for list endpoints (no user secrets ever loaded). */
    private const NAME_RELATIONS = [
        'item:id,name,item_code',
        'branch:id,name',
        'submitter:id,name',
        'reviewer:id,name',
    ];

    /**
     * The caller's OWN submissions, any status. Server-enforced to submitted_by = me —
     * there is no parameter that widens it to other users. Always the blind submitter
     * shape (no system_qty_snapshot / variance), even for a Manager calling it. Accepts
     * the same branch/date-range/type/status filters as index() so the Flutter
     * "History & Audit" screen can offer identical filters to Staff without ever
     * calling the cross-staff endpoint.
     */
    public function mine(Request $request)
    {
        $request->validate($this->historyFilterRules());

        $perPage = (int) $request->query('per_page', 20);

        $query = InventoryObservation::query()
            ->with(self::NAME_RELATIONS)
            ->where('submitted_by', $request->user()->id);

        if ($branchId = $request->query('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        $this->applyHistoryFilters($query, $request);

        $observations = $query->orderByDesc('id')->paginate($perPage);

        $observations->setCollection(
            $observations->getCollection()->map(
                fn (InventoryObservation $o) => $this->withNames($this->submitterShape($o), $o)
            )
        );

        return response()->json($observations);
    }

    /**
     * Manager/Owner cross-staff audit view: ALL observations in their accessible
     * branch(es), any status, filterable by branch/date-range/type/status. Distinct
     * from mine() (own-only, submitter shape) and pending() (pending-only). This is
     * the backing endpoint for the Flutter "History & Audit" screen (10_history_audit_3x.png).
     *
     * date_from/date_to filter on created_at (the submission timestamp), not
     * exp_date_observed: created_at is present and semantically meaningful for every
     * observation (both stock_count and expiry_check), whereas exp_date_observed is
     * only populated for expiry_check rows and describes the expiry date on the
     * product, not when the audit event happened — filtering an "audit history" view
     * by it would silently drop all stock_count rows and conflate two different dates.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($guard = $this->requirePermission($user, 'stock-verification.review')) {
            return $guard;
        }

        $request->validate($this->historyFilterRules() + [
            'submitted_by' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = InventoryObservation::query()->with(self::NAME_RELATIONS);

        if (! $this->hasAllBranchAccess($user)) {
            $query->where('branch_id', $user->branch_id);
        }

        if ($branchId = $request->query('branch_id')) {
            if (! $this->hasAllBranchAccess($user) && (int) $branchId !== (int) $user->branch_id) {
                return response()->json(['message' => 'You do not have access to this branch.'], 403);
            }
            $query->where('branch_id', $branchId);
        }

        // Optional "one staff member" filter; still bounded by the branch scope above.
        if ($submittedBy = $request->query('submitted_by')) {
            $query->where('submitted_by', $submittedBy);
        }

        $this->applyHistoryFilters($query, $request);

        $perPage = (int) $request->query('per_page', 20);
        $observations = $query->orderByDesc('id')->paginate($perPage);

        $observations->setCollection(
            $observations->getCollection()->map(
                fn (InventoryObservation $o) => $this->withNames($this->reviewerShape($o), $o)
            )
        );

        return response()->json($observations);
    }

    public function pending(Request $request)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.review')) {
            return $guard;
        }

        $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['nullable', Rule::in(['stock_count', 'expiry_check'])],
            'task_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:id,-id,variance,-variance'],
        ]);

        $user = $request->user();
        $query = InventoryObservation::with(self::NAME_RELATIONS)->where('status', 'pending');

        if (! $this->hasAllBranchAccess($user)) {
            $query->where('branch_id', $user->branch_id);
        }
        if ($branchId = $request->query('branch_id')) {
            if (! $this->hasAllBranchAccess($user) && (int) $branchId !== (int) $user->branch_id) {
                return response()->json(['message' => 'You do not have access to this branch.'], 403);
            }
            $query->where('branch_id', $branchId);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($taskId = $request->query('task_id')) {
            $query->where('task_id', $taskId);
        }

        $sort = $request->query('sort', '-id');
        if (in_array($sort, ['id', '-id'], true)) {
            $query->orderBy('id', $sort === '-id' ? 'desc' : 'asc');
        }

        $perPage = (int) $request->query('per_page', 20);
        $observations = $query->paginate($perPage);

        $collection = $observations->getCollection()->map(
            fn (InventoryObservation $o) => $this->withNames($this->reviewerShape($o), $o)
        );

        if (in_array($sort, ['variance', '-variance'], true)) {
            $collection = $sort === '-variance'
                ? $collection->sortByDesc('variance')->values()
                : $collection->sortBy('variance')->values();
        }

        $observations->setCollection($collection);

        return response()->json($observations);
    }

    public function show(Request $request, InventoryObservation $observation)
    {
        $user = $request->user();

        $isSubmitter = (int) $observation->submitted_by === (int) $user->id;
        $hasBranchAccess = $this->hasAllBranchAccess($user) || (int) $user->branch_id === (int) $observation->branch_id;

        if (! $isSubmitter && ! $hasBranchAccess) {
            return response()->json(['message' => 'You do not have access to this observation.'], 403);
        }

        $observation->load(self::NAME_RELATIONS);
        $shape = $this->withNames(
            $this->isReviewer($user) ? $this->reviewerShape($observation) : $this->submitterShape($observation),
            $observation
        );

        $shape['parent_observation'] = $observation->parentObservation
            ? ($this->isReviewer($user) ? $this->reviewerShape($observation->parentObservation) : $this->submitterShape($observation->parentObservation))
            : null;

        $shape['recounts'] = $observation->recounts->map(
            fn (InventoryObservation $r) => $this->isReviewer($user) ? $this->reviewerShape($r) : $this->submitterShape($r)
        )->values();

        return response()->json(['data' => $shape]);
    }

    /**
     * Streams the uploaded evidence photo for an observation. Manager/Owner only
     * (same 'stock-verification.review' + branch-scoping pattern as approve/reject) —
     * see §11 "Authorised image retrieval" gap in OVERNIGHT_PROGRESS_REPORT.md. Files
     * live on the 'public' disk but are served through this authenticated route
     * rather than a public storage:link URL.
     */
    public function photo(Request $request, InventoryObservation $observation)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.review')) {
            return $guard;
        }
        if ($guard = $this->branchGuardFor($request, $observation)) {
            return $guard;
        }

        if (! $observation->photo_path || ! Storage::disk('public')->exists($observation->photo_path)) {
            return response()->json(['message' => 'No photo on this observation.'], 404);
        }

        return response()->file(Storage::disk('public')->path($observation->photo_path));
    }

    private function branchGuardFor(Request $request, InventoryObservation $observation): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (! $this->hasAllBranchAccess($user) && (int) $user->branch_id !== (int) $observation->branch_id) {
            return response()->json(['message' => 'You do not have access to this branch.'], 403);
        }

        return null;
    }

    /**
     * Approve a pending observation: computes actual_adjustment_qty from CURRENT stock
     * (re-fetched inside this transaction, not the stale system_qty_snapshot) and posts
     * exactly one StockLedgerService::post() call. Fully rolled back (observation stays
     * pending) if the ledger post throws. See URBANPETS_DATABASE_AND_WORKFLOW.md §5.
     */
    public function approve(Request $request, InventoryObservation $observation)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.review')) {
            return $guard;
        }
        if ($guard = $this->branchGuardFor($request, $observation)) {
            return $guard;
        }

        // Business rule: an expiry_check approval must NEVER change stock quantity.
        // Quantity corrections are exclusively a stock_count-approval concern — this
        // branch just records the review decision, no StockLedgerService call, no
        // approved_qty/actual_adjustment_qty, no item_stocks touch at all.
        if ($observation->type === 'expiry_check') {
            $validated = $request->validate([
                'remarks' => ['nullable', 'string'],
            ]);

            $user = $request->user();

            $conflict = DB::transaction(function () use ($observation, $validated, $user) {
                $locked = InventoryObservation::whereKey($observation->id)->lockForUpdate()->first();

                if (! ObservationReviewEligibility::canReview($locked->status)) {
                    return true;
                }

                $locked->update([
                    'status' => 'approved',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_remarks' => $validated['remarks'] ?? null,
                ]);

                return false;
            });

            if ($conflict) {
                return response()->json(['message' => 'This observation is no longer pending.'], 409);
            }

            return response()->json(['data' => $this->reviewerShape($observation->fresh())]);
        }

        $validated = $request->validate([
            'approved_qty' => ['required', 'numeric'],
            'remarks' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($observation, $validated, $user) {
                /** @var InventoryObservation $locked */
                $locked = InventoryObservation::whereKey($observation->id)->lockForUpdate()->first();

                if (! ObservationReviewEligibility::canReview($locked->status)) {
                    return ['conflict' => true];
                }

                // Lock the item/branch stock row to serialize against concurrent sales
                // and other approvals on the same item+branch before reading current stock.
                $stockRow = ItemStock::firstOrCreate(
                    ['item_id' => $locked->item_id, 'branch_id' => $locked->branch_id],
                    ['quantity' => 0, 'cost_price' => 0]
                );
                $stockRow = ItemStock::whereKey($stockRow->id)->lockForUpdate()->first();

                $currentStock = $locked->batch_no
                    ? (
                        ($batch = $this->batchStockService->getBatchStock($locked->item_id, $locked->branch_id, $locked->batch_no))
                            ? (float) $batch['remaining_qty']
                            : 0.0
                    )
                    : (float) $stockRow->quantity;

                $approvedQty = (float) $validated['approved_qty'];
                $delta = round($approvedQty - $currentStock, 3);

                $ledgerId = null;
                if ($delta !== 0.0) {
                    $ledger = $this->stockLedgerService->post(
                        itemId: $locked->item_id,
                        branchId: $locked->branch_id,
                        movementType: 'CORRECTION',
                        qtyDelta: $delta,
                        unitCost: null,
                        referenceType: 'InventoryObservation',
                        referenceId: $locked->id,
                        documentDate: now()->toDateString(),
                        userId: $user->id,
                        reasonCode: 'STOCK_VERIFICATION_APPROVAL',
                        expDate: optional($locked->exp_date_observed)->toDateString(),
                        batchNo: $locked->batch_no,
                    );
                    $ledgerId = $ledger->id;
                }

                $locked->update([
                    'status' => 'approved',
                    'approved_qty' => $approvedQty,
                    'actual_adjustment_qty' => $delta,
                    'stock_ledger_id' => $ledgerId,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_remarks' => $validated['remarks'] ?? null,
                ]);

                return ['conflict' => false, 'observation' => $locked->fresh()];
            });
        } catch (ValidationException $e) {
            // StockLedgerService::post() threw (e.g. negative-stock block without
            // allow_negative_stock). The whole transaction rolled back — status stays
            // pending, no partial ledger/observation state.
            return response()->json([
                'message' => 'Approval failed: '.$e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        if ($result['conflict']) {
            return response()->json(['message' => 'This observation is no longer pending.'], 409);
        }

        return response()->json(['data' => $this->reviewerShape($result['observation'])]);
    }

    public function requestRecount(Request $request, InventoryObservation $observation)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.review')) {
            return $guard;
        }
        if ($guard = $this->branchGuardFor($request, $observation)) {
            return $guard;
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $user = $request->user();

        $conflict = DB::transaction(function () use ($observation, $validated, $user) {
            $locked = InventoryObservation::whereKey($observation->id)->lockForUpdate()->first();

            if (! ObservationReviewEligibility::canReview($locked->status)) {
                return true;
            }

            $locked->update([
                'status' => 'recount_requested',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_remarks' => $validated['remarks'],
            ]);

            return false;
        });

        if ($conflict) {
            return response()->json(['message' => 'This observation is no longer pending.'], 409);
        }

        return response()->json(['data' => $this->reviewerShape($observation->fresh())]);
    }

    public function reject(Request $request, InventoryObservation $observation)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.review')) {
            return $guard;
        }
        if ($guard = $this->branchGuardFor($request, $observation)) {
            return $guard;
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string'],
        ]);

        $user = $request->user();

        $conflict = DB::transaction(function () use ($observation, $validated, $user) {
            $locked = InventoryObservation::whereKey($observation->id)->lockForUpdate()->first();

            if (! ObservationReviewEligibility::canReview($locked->status)) {
                return true;
            }

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_remarks' => $validated['remarks'],
            ]);

            return false;
        });

        if ($conflict) {
            return response()->json(['message' => 'This observation is no longer pending.'], 409);
        }

        return response()->json(['data' => $this->reviewerShape($observation->fresh())]);
    }
}
