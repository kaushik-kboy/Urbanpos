<?php

namespace Tests\Unit;

use App\Support\ObservationVariance;
use PHPUnit\Framework\TestCase;

/**
 * Isolated unit coverage for the variance calculation (physical_qty minus
 * system_qty_snapshot) used by ObservationController::reviewerShape. No
 * database needed — see OVERNIGHT_PROGRESS_REPORT.md §13 "no isolated unit
 * tests for the module" gap.
 */
class ObservationVarianceTest extends TestCase
{
    public function test_positive_variance(): void
    {
        $this->assertSame(6.0, ObservationVariance::compute(26.0, 20.0));
    }

    public function test_negative_variance(): void
    {
        $this->assertSame(-3.0, ObservationVariance::compute(17.0, 20.0));
    }

    public function test_zero_variance(): void
    {
        $this->assertSame(0.0, ObservationVariance::compute(20.0, 20.0));
    }

    public function test_null_when_physical_qty_missing(): void
    {
        $this->assertNull(ObservationVariance::compute(null, 20.0));
    }

    public function test_null_when_system_qty_missing(): void
    {
        $this->assertNull(ObservationVariance::compute(17.0, null));
    }

    public function test_rounds_to_three_decimal_places(): void
    {
        $this->assertSame(0.001, ObservationVariance::compute(10.0015, 10.0005));
    }

    public function test_worked_example_from_workflow_doc(): void
    {
        // snapshot 20 -> current stock drops to 18 -> manager approves target
        // 17 -> posted delta is -1 (18->17), not -3 (stale 20->17). The
        // variance shown to the reviewer is still against the SNAPSHOT (20),
        // which is a distinct number from the ledger delta — this just
        // confirms the snapshot-based figure a manager sees on the queue.
        $this->assertSame(-3.0, ObservationVariance::compute(17.0, 20.0));
    }
}
