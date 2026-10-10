<?php

namespace Tests\Unit;

use App\Support\ExpiryBucketClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Isolated unit coverage for the expiry-bucket boundary lookup used by
 * DashboardController::buildExpiryBuckets. No database needed — settings are
 * plain stdClass rows (the real ExpiryBucketSetting model only needs to
 * expose id/min_days/max_days, which this test fakes directly).
 */
class ExpiryBucketClassifierTest extends TestCase
{
    private function setting(int $id, int $min, int $max): object
    {
        return (object) ['id' => $id, 'min_days' => $min, 'max_days' => $max];
    }

    /** Mirrors the module's seeded default buckets (0-30/31-60/61-120 days). */
    private function defaultBuckets(): array
    {
        return [
            $this->setting(1, 0, 30),
            $this->setting(2, 31, 60),
            $this->setting(3, 61, 120),
        ];
    }

    public function test_classifies_into_the_first_bucket(): void
    {
        $this->assertSame(1, ExpiryBucketClassifier::classify(5, $this->defaultBuckets()));
    }

    public function test_classifies_a_boundary_day_into_the_lower_bucket(): void
    {
        $this->assertSame(1, ExpiryBucketClassifier::classify(30, $this->defaultBuckets()));
        $this->assertSame(2, ExpiryBucketClassifier::classify(31, $this->defaultBuckets()));
    }

    public function test_classifies_into_the_last_bucket(): void
    {
        $this->assertSame(3, ExpiryBucketClassifier::classify(120, $this->defaultBuckets()));
    }

    public function test_returns_null_when_nothing_covers_it(): void
    {
        // 121 days out falls past every configured bucket in this fixture —
        // the caller (DashboardController) simply omits it, never guesses.
        $this->assertNull(ExpiryBucketClassifier::classify(121, $this->defaultBuckets()));
    }

    public function test_returns_null_for_an_empty_settings_list(): void
    {
        $this->assertNull(ExpiryBucketClassifier::classify(10, []));
    }

    public function test_already_expired_items_are_clamped_to_zero_before_classifying(): void
    {
        // DashboardController clamps a negative days-until-expiry to 0
        // before calling this — confirm 0 still lands in the first bucket.
        $this->assertSame(1, ExpiryBucketClassifier::classify(0, $this->defaultBuckets()));
    }

    public function test_first_matching_bucket_wins_when_ranges_overlap(): void
    {
        $overlapping = [$this->setting(10, 0, 50), $this->setting(20, 20, 60)];
        $this->assertSame(10, ExpiryBucketClassifier::classify(25, $overlapping));
    }
}
