<?php

namespace Tests\Unit;

use App\Support\ObservationReviewEligibility;
use PHPUnit\Framework\TestCase;

/**
 * Isolated unit coverage for "is this observation still reviewable" (the
 * check behind the approve/recount/reject 409 "no longer pending" response).
 * No database needed.
 */
class ObservationReviewEligibilityTest extends TestCase
{
    public function test_pending_can_be_reviewed(): void
    {
        $this->assertTrue(ObservationReviewEligibility::canReview('pending'));
    }

    public function test_approved_cannot_be_reviewed_again(): void
    {
        $this->assertFalse(ObservationReviewEligibility::canReview('approved'));
    }

    public function test_rejected_cannot_be_reviewed_again(): void
    {
        $this->assertFalse(ObservationReviewEligibility::canReview('rejected'));
    }

    public function test_recount_requested_cannot_be_reviewed_again(): void
    {
        // The original observation is frozen once a recount is requested —
        // only the NEW resubmission (its own, separately pending, row) can be
        // reviewed next.
        $this->assertFalse(ObservationReviewEligibility::canReview('recount_requested'));
    }
}
