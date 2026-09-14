<?php

namespace App\Services\Accounting;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the racy `Model::max('id') + 1` numbering pattern that used to be duplicated
 * in VoucherController and LedgerPostingService — a locked counter row per series instead
 * of scanning the whole table, so two concurrent requests can't compute the same number.
 */
class DocumentNumberingService
{
    public function next(string $series): int
    {
        return DB::transaction(function () use ($series) {
            $sequence = DocumentSequence::firstOrCreate(['series' => $series], ['last_number' => 0]);
            $sequence = DocumentSequence::whereKey($sequence->id)->lockForUpdate()->first();
            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });
    }
}
