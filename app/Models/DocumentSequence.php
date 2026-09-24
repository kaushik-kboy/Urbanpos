<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    use HasFactory;

    protected $table = 'document_sequences';

    protected $fillable = [
        'document_type',
        'document_title',
        'prefix',
        'suffix',
        'padding_zeros',
        'starting_number',
        'reset_frequency',
        'last_reset_period',
        'branch_id',
        'is_active',
        'series',
        'last_number',
        'financial_year',
    ];

    protected $casts = [
        'padding_zeros'   => 'integer',
        'starting_number' => 'integer',
        'last_number'     => 'integer',
        'is_active'       => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Resolve all dynamic tokens ({YEAR}, {YY}, {FY}, {FY_LONG}, {FY_NUM}, {BRANCH}, {MONTH}, {DAY})
     * in the prefix or suffix string.
     *
     * Token Reference:
     *   {YEAR}    → Full calendar year, e.g. 2026
     *   {YY}      → 2-digit calendar year, e.g. 26
     *   {FY}      → Short financial year, e.g. 26-27
     *   {FY_LONG} → Long financial year, e.g. 2026-2027
     *   {FY_NUM}  → FY digits concatenated, e.g. 2627  (for 2026-27)
     *   {BRANCH}  → Branch code, e.g. HQ
     *   {MONTH}   → Zero-padded month, e.g. 04
     *   {M}       → Month without padding, e.g. 4
     *   {DAY}     → Zero-padded day, e.g. 01
     */
    public function resolveTokens(string $template, ?Branch $branch = null, ?Carbon $date = null): string
    {
        $d = $date ?: now();
        $targetBranch = $branch ?: $this->branch;

        // Financial year calculation (India: 1 April - 31 March)
        $year = (int) $d->format('Y');
        $month = (int) $d->format('m');
        if ($month >= 4) {
            $fyLong = $year . '-' . ($year + 1);
            $fyShort = substr((string)$year, -2) . '-' . substr((string)($year + 1), -2);
        } else {
            $fyLong = ($year - 1) . '-' . $year;
            $fyShort = substr((string)($year - 1), -2) . '-' . substr((string)$year, -2);
        }

        // Branch code fallback
        $branchCode = $targetBranch?->code 
            ?: ($targetBranch?->name ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $targetBranch->name), 0, 3)) : 'MAIN');

        $replacements = [
            '{YEAR}'    => $d->format('Y'),
            '{YY}'      => $d->format('y'),
            '{FY}'      => $fyShort,        // e.g. 26-27
            '{FY_LONG}' => $fyLong,         // e.g. 2026-2027
            '{FY_NUM}'  => str_replace('-', '', $fyShort), // e.g. 2627
            '{BRANCH}'  => $branchCode,
            '{MONTH}'   => $d->format('m'),
            '{M}'       => $d->format('n'),
            '{DAY}'     => $d->format('d'),
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Generate an illustrative preview of the next document number.
     */
    public function formatPreview(?Branch $branch = null): string
    {
        $prefix = $this->resolveTokens($this->prefix ?: '', $branch);
        $suffix = $this->resolveTokens($this->suffix ?: '', $branch);
        $nextNum = max((int)$this->starting_number, (int)$this->last_number + 1);
        $padding = max(1, (int)($this->padding_zeros ?: 4));

        return $prefix . str_pad((string)$nextNum, $padding, '0', STR_PAD_LEFT) . $suffix;
    }

    /**
     * Get the current period identifier based on reset frequency.
     */
    public static function getCurrentPeriod(string $frequency, ?Carbon $date = null): string
    {
        $d = $date ?: now();
        $year = (int) $d->format('Y');
        $month = (int) $d->format('m');

        return match ($frequency) {
            'yearly'  => $d->format('Y'),
            'monthly' => $d->format('Y-m'),
            'financial_year' => ($month >= 4) ? "{$year}-" . ($year + 1) : ($year - 1) . "-{$year}",
            default   => 'continuous',
        };
    }
}
