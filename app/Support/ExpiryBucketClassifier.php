<?php

namespace App\Support;

/**
 * Pure "which configured expiry bucket does this many days-until-expiry fall
 * into" lookup used by DashboardController::buildExpiryBuckets. Extracted so
 * the boundary logic is unit-testable without a database — see
 * tests/Unit/ExpiryBucketClassifierTest.php.
 *
 * @param  iterable<object{id:int,min_days:int,max_days:int}>  $settings  ordered by sort_order
 */
class ExpiryBucketClassifier
{
    /** Returns the matching setting's id, or null if no configured bucket covers it. */
    public static function classify(int $bucketDays, iterable $settings): ?int
    {
        foreach ($settings as $setting) {
            if ($bucketDays >= $setting->min_days && $bucketDays <= $setting->max_days) {
                return $setting->id;
            }
        }

        return null;
    }
}
