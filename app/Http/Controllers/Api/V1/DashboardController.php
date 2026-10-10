<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BarcodeMappingRequest;
use App\Models\Branch;
use App\Models\ExpiryBucketSetting;
use App\Models\InventoryObservation;
use App\Models\StockCountTask;
use App\Models\Item;
use App\Models\StockLedger;
use App\Models\User;
use App\Support\ExpiryBucketClassifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /v1/dashboard/summary — branch-scoped, role-aware mobile dashboard summary.
 * See URBANPETS_DATABASE_AND_WORKFLOW.md §8 (Phase 10 notes) and
 * URBANPETS_API_DOCUMENTATION.md for the full response shape documentation.
 *
 * Staff get a personal summary only (their own tasks/observations). Manager/Owner get
 * a branch-wide operational summary. Blind counting (master prompt §6) applies here too:
 * a Staff-only user's payload never contains any system-quantity or variance figure,
 * anywhere in this response — not just on the count-entry screen.
 */
class DashboardController extends Controller
{
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
    private function requirePermission(User $user, string $permission): ?JsonResponse
    {
        if (! $user->hasPermissionTo($permission, 'web')) {
            return response()->json(['message' => 'User does not have the right permissions.'], 403);
        }

        return null;
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = $request->user();

        return $this->isReviewer($user)
            ? $this->managerSummary($request, $user)
            : $this->staffSummary($user);
    }

    /**
     * Staff: personal counts only. NEVER includes system_qty_snapshot, variance, or any
     * other current-system-quantity figure — blind counting applies to dashboards too,
     * per the master prompt §6 (the rule hides system quantity from staff anywhere in
     * the API, not only on the count-entry screen).
     */
    private function staffSummary(User $user): JsonResponse
    {
        $taskCounts = StockCountTask::where('assigned_to', $user->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $periodDays = 30;
        $since = Carbon::now()->subDays($periodDays);

        $observationCounts = InventoryObservation::where('submitted_by', $user->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return response()->json(['data' => [
            'role' => 'staff',
            'branch_id' => $user->branch_id,
            'tasks' => [
                'pending' => (int) ($taskCounts['pending'] ?? 0),
                'in_progress' => (int) ($taskCounts['in_progress'] ?? 0),
                'completed' => (int) ($taskCounts['completed'] ?? 0),
            ],
            'observations' => [
                'period_days' => $periodDays,
                'pending' => (int) ($observationCounts['pending'] ?? 0),
                'approved' => (int) ($observationCounts['approved'] ?? 0),
                'rejected' => (int) ($observationCounts['rejected'] ?? 0),
                'recount_requested' => (int) ($observationCounts['recount_requested'] ?? 0),
            ],
        ]]);
    }

    /**
     * Manager/Owner: branch-wide operational summary for the caller's accessible
     * branch(es). Gated by 'stock-verification.review' (same permission as the review
     * queue itself) since this surfaces the same review-queue data in aggregate form.
     */
    private function managerSummary(Request $request, User $user): JsonResponse
    {
        if ($guard = $this->requirePermission($user, 'stock-verification.review')) {
            return $guard;
        }

        $hasAllBranches = $this->hasAllBranchAccess($user);
        $requestedBranchId = $request->query('branch_id');

        if ($requestedBranchId !== null) {
            if (! $hasAllBranches && (int) $requestedBranchId !== (int) $user->branch_id) {
                return response()->json(['message' => 'You do not have access to this branch.'], 403);
            }
            $branchIds = [(int) $requestedBranchId];
            $scopedBranchId = (int) $requestedBranchId;
        } elseif (! $hasAllBranches) {
            $branchIds = [(int) $user->branch_id];
            $scopedBranchId = (int) $user->branch_id;
        } else {
            $branchIds = Branch::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $scopedBranchId = null; // null = aggregated across all accessible branches
        }

        // --- pending review queue count (single aggregate query) ---
        $pendingReviewCount = InventoryObservation::where('status', 'pending')
            ->whereIn('branch_id', $branchIds)
            ->count();

        // --- today's / this week's approved vs rejected counts (single aggregate query) ---
        // "Today" = local calendar day start (Carbon::today()). "This week" = Carbon's
        // default week start (Monday 00:00) through now. Both use reviewed_at (the
        // moment the review decision was made), not created_at. Documented as an
        // implementation decision in URBANPETS_DATABASE_AND_WORKFLOW.md §8.
        $todayStart = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();

        $reviewed = InventoryObservation::whereIn('branch_id', $branchIds)
            ->whereNotNull('reviewed_at')
            ->selectRaw(
                "SUM(CASE WHEN status = 'approved' AND reviewed_at >= ? THEN 1 ELSE 0 END) as today_approved,
                 SUM(CASE WHEN status = 'rejected' AND reviewed_at >= ? THEN 1 ELSE 0 END) as today_rejected,
                 SUM(CASE WHEN status = 'approved' AND reviewed_at >= ? THEN 1 ELSE 0 END) as week_approved,
                 SUM(CASE WHEN status = 'rejected' AND reviewed_at >= ? THEN 1 ELSE 0 END) as week_rejected",
                [$todayStart, $todayStart, $weekStart, $weekStart]
            )
            ->first();

        // --- open task counts by status (single aggregate query) ---
        $taskCounts = StockCountTask::whereIn('branch_id', $branchIds)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // --- pending barcode-mapping-request count (own permission; single query) ---
        $pendingBarcodeMappingRequests = null;
        if ($user->hasPermissionTo('barcode-mapping.review', 'web')) {
            $pendingBarcodeMappingRequests = BarcodeMappingRequest::where('status', 'pending')
                ->whereIn('branch_id', $branchIds)
                ->count();
        }

        $expiryBuckets = $this->buildExpiryBuckets($branchIds);

        return response()->json(['data' => [
            'role' => $user->hasRole('Owner') ? 'owner' : 'manager',
            'branch_id' => $scopedBranchId,
            'branch_ids' => $branchIds,
            'pending_review_count' => $pendingReviewCount,
            'reviewed_today' => [
                'approved' => (int) ($reviewed->today_approved ?? 0),
                'rejected' => (int) ($reviewed->today_rejected ?? 0),
            ],
            'reviewed_this_week' => [
                'approved' => (int) ($reviewed->week_approved ?? 0),
                'rejected' => (int) ($reviewed->week_rejected ?? 0),
            ],
            'tasks' => [
                'pending' => (int) ($taskCounts['pending'] ?? 0),
                'in_progress' => (int) ($taskCounts['in_progress'] ?? 0),
                'completed' => (int) ($taskCounts['completed'] ?? 0),
                'cancelled' => (int) ($taskCounts['cancelled'] ?? 0),
            ],
            'pending_barcode_mapping_requests' => $pendingBarcodeMappingRequests,
            'expiry_buckets' => $expiryBuckets,
        ]]);
    }

    /**
     * Groups current batch stock (via BatchStockService, read-only, reused as-is) into
     * the configured expiry_bucket_settings ranges. Scope is bounded to items that
     * actually have a stock-ledger batch/expiry history in the given branch(es) — not
     * the full item catalog — to keep this bounded and avoid an unbounded per-item-in-
     * catalog N+1. Each per-item lookup still costs a few queries internally (inherited
     * from BatchStockService, not reimplemented here); this is a documented, accepted
     * tradeoff for reusing that read-only service rather than duplicating its batch-
     * resolution logic. See URBANPETS_DATABASE_AND_WORKFLOW.md §8 for this decision.
     *
     * Items already past their expiry date (negative days-until-expiry) are clamped
     * into the bucket whose min_days is 0 (the most urgent configured bucket) and
     * flagged with is_expired=true, rather than being silently dropped.
     */
    private function buildExpiryBuckets(array $branchIds): array
    {
        $bucketSettings = ExpiryBucketSetting::orderBy('sort_order')->get();
        if ($bucketSettings->isEmpty()) {
            return [];
        }

        $buckets = [];
        foreach ($bucketSettings as $setting) {
            $buckets[$setting->id] = [
                'label' => $setting->label,
                'min_days' => $setting->min_days,
                'max_days' => $setting->max_days,
                'severity' => $setting->severity,
                'sort_order' => $setting->sort_order,
                'count' => 0,
                'items' => [],
            ];
        }

        $itemLimitPerBucket = 10;
        $today = Carbon::today();

        foreach ($branchIds as $branchId) {
            // Single aggregated query per branch instead of one getItemBatches() call
            // (~5 queries each) per distinct item — avoids an N+1 that was issuing
            // thousands of queries on a single dashboard load for branches with a
            // realistic number of items with ledger/batch history.
            $batchRows = StockLedger::where('branch_id', $branchId)
                ->whereNotNull('exp_date')
                ->selectRaw('item_id, batch_no, DATE(exp_date) as exp_date_val, SUM(qty_in) - SUM(qty_out) as remaining_qty')
                ->groupBy('item_id', 'batch_no', DB::raw('DATE(exp_date)'))
                ->havingRaw('SUM(qty_in) - SUM(qty_out) > 0')
                ->get();

            if ($batchRows->isEmpty()) {
                continue;
            }

            $itemNames = Item::whereIn('id', $batchRows->pluck('item_id')->unique())->pluck('name', 'id');

            foreach ($batchRows as $row) {
                $expDate = Carbon::parse($row->exp_date_val)->startOfDay();
                $daysUntilExpiry = $today->diffInDays($expDate, false);
                $isExpired = $daysUntilExpiry < 0;
                $bucketDays = max(0, $daysUntilExpiry);

                $bucketId = ExpiryBucketClassifier::classify($bucketDays, $bucketSettings);

                if ($bucketId !== null) {
                    $buckets[$bucketId]['count']++;

                    if (count($buckets[$bucketId]['items']) < $itemLimitPerBucket) {
                        $buckets[$bucketId]['items'][] = [
                            'item_id' => (int) $row->item_id,
                            'item_name' => $itemNames[$row->item_id] ?? null,
                            'branch_id' => (int) $branchId,
                            'batch_no' => $row->batch_no ? trim($row->batch_no) : '',
                            'exp_date' => $expDate->toDateString(),
                            'days_until_expiry' => $daysUntilExpiry,
                            'is_expired' => $isExpired,
                            'remaining_qty' => (float) $row->remaining_qty,
                        ];
                    }
                }
            }
        }

        return array_values(array_map(function ($bucket) use ($itemLimitPerBucket) {
            $bucket['has_more'] = $bucket['count'] > $itemLimitPerBucket;

            return $bucket;
        }, $buckets));
    }
}
