<?php

namespace App\Support;

/**
 * Pure "what HTTP status does this submission get" rule used by
 * ObservationController::store(): a brand-new row is 201, a replay of an
 * already-processed idempotency_key (found up front, or raced into via the
 * unique-constraint catch) is 200 — same row either way, never a duplicate.
 * Extracted purely so the status-code rule itself is unit-testable without a
 * database; the actual lookup/insert/race-retry is still exercised by the
 * feature tests (that part is inherently DB I/O, not isolable logic).
 */
class IdempotentSubmissionOutcome
{
    public static function statusFor(bool $rowAlreadyExisted): int
    {
        return $rowAlreadyExisted ? 200 : 201;
    }
}
