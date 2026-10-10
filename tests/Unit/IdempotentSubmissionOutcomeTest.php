<?php

namespace Tests\Unit;

use App\Support\IdempotentSubmissionOutcome;
use PHPUnit\Framework\TestCase;

/**
 * Isolated unit coverage for the idempotent-submission status-code rule used
 * by ObservationController::store() (200 on replay, 201 on a genuinely new
 * row — see tests/Feature/Api/ObservationApiTest.php for the DB-backed
 * lookup/race-retry behaviour this rule sits on top of).
 */
class IdempotentSubmissionOutcomeTest extends TestCase
{
    public function test_a_replayed_submission_is_200(): void
    {
        $this->assertSame(200, IdempotentSubmissionOutcome::statusFor(rowAlreadyExisted: true));
    }

    public function test_a_new_submission_is_201(): void
    {
        $this->assertSame(201, IdempotentSubmissionOutcome::statusFor(rowAlreadyExisted: false));
    }
}
