<?php

namespace App\Support;

/**
 * Pure variance calculation used by the manager/owner review shape
 * (ObservationController::reviewerShape). Extracted so it can be unit-tested
 * without a database — see tests/Unit/ObservationVarianceTest.php.
 */
class ObservationVariance
{
    /**
     * physical_qty - system_qty_snapshot, rounded to 3dp. Null whenever
     * either side is missing (e.g. an expiry check with no counted
     * quantity) — callers must render that as "-", never as 0.
     */
    public static function compute(?float $physicalQty, ?float $systemQtySnapshot): ?float
    {
        if ($physicalQty === null || $systemQtySnapshot === null) {
            return null;
        }

        return round($physicalQty - $systemQtySnapshot, 3);
    }
}
