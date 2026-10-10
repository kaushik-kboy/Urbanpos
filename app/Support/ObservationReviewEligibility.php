<?php

namespace App\Support;

/**
 * Pure "is this observation still reviewable" check used by
 * ObservationController::approve/requestRecount/reject (all three require
 * the locked row to still be 'pending', else 409). Extracted so this rule is
 * unit-testable without a database — see tests/Unit/ObservationReviewEligibilityTest.php.
 */
class ObservationReviewEligibility
{
    public static function canReview(string $status): bool
    {
        return $status === 'pending';
    }
}
