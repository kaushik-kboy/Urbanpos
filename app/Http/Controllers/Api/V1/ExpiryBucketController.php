<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpiryBucketSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configurable expiry-bucket ranges (business rule, 2026-10-10 client decision):
 * Manager/Owner can view AND edit the buckets used by the dashboard's expiry-exposure
 * widget (DashboardController::buildExpiryBuckets via App\Support\ExpiryBucketClassifier).
 * Defaults, seeded by the 2026_10_10_010001 migration and unchanged unless an Owner/
 * Manager edits them here: "Expiring Soon" 0-30 days (critical), "Expiring Later"
 * 31-60 days (warning), "Watch List" 61-120 days (info). See
 * URBANPETS_DATABASE_AND_WORKFLOW.md §8 item 18 for the validation rules below.
 */
class ExpiryBucketController extends Controller
{
    public function index()
    {
        $buckets = ExpiryBucketSetting::query()
            ->orderBy('sort_order')
            ->get(['id', 'label', 'min_days', 'max_days', 'severity', 'sort_order']);

        return response()->json(['data' => $buckets]);
    }

    private function requirePermission(User $user, string $permission): ?\Illuminate\Http\JsonResponse
    {
        if (! $user->hasPermissionTo($permission, 'web')) {
            return response()->json(['message' => 'User does not have the right permissions.'], 403);
        }

        return null;
    }

    /**
     * Full replace of the bucket list (Manager/Owner only — reuses the existing
     * 'stock-verification.review' permission; there is no separate settings
     * permission in the module's 5-permission set). Validation rules, deliberately
     * conservative since these ranges drive client-visible urgency colours:
     *  - at least one bucket;
     *  - every bucket has min_days <= max_days, both >= 0;
     *  - buckets must not overlap each other;
     *  - the first bucket (by min_days) must start at 0 — there is no "unclassified
     *    gap" at the most urgent end. Gaps between buckets (e.g. 0-30, 40-60) ARE
     *    allowed deliberately — some clients may want ranges that don't tile the
     *    whole timeline — matching ExpiryBucketClassifier's existing "no matching
     *    bucket = unclassified" behaviour rather than inventing a forced tiling rule
     *    that was never confirmed with the client.
     */
    public function update(Request $request)
    {
        if ($guard = $this->requirePermission($request->user(), 'stock-verification.review')) {
            return $guard;
        }

        $validated = $request->validate([
            'buckets' => ['required', 'array', 'min:1', 'max:20'],
            'buckets.*.id' => ['nullable', 'integer', 'exists:expiry_bucket_settings,id'],
            'buckets.*.label' => ['required', 'string', 'max:255'],
            'buckets.*.min_days' => ['required', 'integer', 'min:0'],
            'buckets.*.max_days' => ['required', 'integer'],
            'buckets.*.severity' => ['required', Rule::in(['critical', 'warning', 'info'])],
            'buckets.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $buckets = collect($validated['buckets'])->sortBy('min_days')->values();

        if ($buckets->first()['min_days'] !== 0) {
            throw ValidationException::withMessages([
                'buckets' => ['The bucket covering the most urgent range must start at min_days = 0.'],
            ]);
        }

        for ($i = 0; $i < $buckets->count(); $i++) {
            $row = $buckets[$i];
            if ($row['min_days'] > $row['max_days']) {
                throw ValidationException::withMessages([
                    'buckets' => ["Bucket '{$row['label']}' has min_days greater than max_days."],
                ]);
            }
            if ($i > 0) {
                $prev = $buckets[$i - 1];
                if ($row['min_days'] <= $prev['max_days']) {
                    throw ValidationException::withMessages([
                        'buckets' => ["Buckets '{$prev['label']}' and '{$row['label']}' overlap."],
                    ]);
                }
            }
        }

        $saved = DB::transaction(function () use ($validated) {
            $keepIds = [];
            foreach ($validated['buckets'] as $row) {
                $bucket = isset($row['id'])
                    ? ExpiryBucketSetting::find($row['id'])
                    : new ExpiryBucketSetting();

                $bucket->fill([
                    'label' => $row['label'],
                    'min_days' => $row['min_days'],
                    'max_days' => $row['max_days'],
                    'severity' => $row['severity'],
                    'sort_order' => $row['sort_order'],
                ])->save();

                $keepIds[] = $bucket->id;
            }

            // Buckets removed from the submitted list are deleted — this is an
            // application-level settings edit, not the "no destructive DB operation"
            // migration/data-loss constraint, which is about schema/backup safety.
            ExpiryBucketSetting::whereNotIn('id', $keepIds)->delete();

            return ExpiryBucketSetting::orderBy('sort_order')->get(['id', 'label', 'min_days', 'max_days', 'severity', 'sort_order']);
        });

        return response()->json(['data' => $saved]);
    }
}
