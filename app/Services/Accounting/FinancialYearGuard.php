<?php

namespace App\Services\Accounting;

use App\Models\FinancialYear;
use Illuminate\Validation\ValidationException;

/**
 * Enforces spec §15.4 ("closed financial periods should be lockable... financial year
 * valid?"). Deliberately fails OPEN when no FinancialYear row exists ANYWHERE in the
 * system — Financial Year management is a new, opt-in Master screen, and every existing
 * document/branch in production predates it. The moment an Owner defines at least one
 * FinancialYear, this check activates for real: a document date must fall inside a
 * defined year, and that year must not be locked. There is no separate per-transaction
 * "backdated" permission — posting into an already-closed period is either blocked
 * outright or requires that period to be explicitly reopened first (an Owner-only,
 * audited action), which itself satisfies the spec's "requires permission" language.
 */
class FinancialYearGuard
{
    public function assertOpenForPosting(string $documentDate): void
    {
        if (! FinancialYear::query()->exists()) {
            return;
        }

        $financialYear = FinancialYear::containing($documentDate);

        if (! $financialYear) {
            throw ValidationException::withMessages([
                'financial_year' => "No financial year is defined for {$documentDate}. Define one under Master > Financial Years before posting this date.",
            ]);
        }

        if ($financialYear->is_locked) {
            throw ValidationException::withMessages([
                'financial_year' => "{$financialYear->name} is locked. An Owner must reopen it before posting into {$documentDate}.",
            ]);
        }
    }
}
