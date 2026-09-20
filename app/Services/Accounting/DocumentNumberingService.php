<?php

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\DocumentSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enterprise Document Numbering & Sequence Engine.
 * Supports dynamic prefixes ({YEAR}, {YY}, {FY}, {BRANCH}, {MONTH}),
 * atomic concurrency row-locking, auto-reset by financial year / calendar year / monthly,
 * and zero collision fallback.
 */
class DocumentNumberingService
{
    /**
     * Legacy series counter for ledger posting lines and legacy vouchers.
     */
    public function next(string $series): int
    {
        return DB::transaction(function () use ($series) {
            $sequence = DocumentSequence::firstOrCreate(['series' => $series], ['last_number' => 0]);
            $sequence = DocumentSequence::whereKey($sequence->id)->lockForUpdate()->first();
            $sequence->increment('last_number');

            return (int) $sequence->last_number;
        });
    }

    /**
     * Generate the next dynamic document number for any voucher type.
     */
    public function generate(string $documentType, ?int $branchId = null, ?string $date = null): string
    {
        $parsedDate = $date ? Carbon::parse($date) : now();

        return DB::transaction(function () use ($documentType, $branchId, $parsedDate) {
            $branch = $branchId ? Branch::find($branchId) : null;

            // 1. Locate branch-specific sequence, otherwise fallback to global sequence
            $sequence = DocumentSequence::where('document_type', $documentType)
                ->where('is_active', true)
                ->where(function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                    } else {
                        $q->whereNull('branch_id');
                    }
                })
                ->orderByRaw('branch_id IS NULL ASC') // prefer branch-specific over global
                ->lockForUpdate()
                ->first();

            // If not created in DB yet, initialize from defaults
            if (!$sequence) {
                $defaults = static::defaultDefinitions()[$documentType] ?? [
                    'title'     => ucwords(str_replace('_', ' ', $documentType)),
                    'prefix'    => strtoupper(substr($documentType, 0, 2)) . '-{YEAR}-',
                    'padding'   => 4,
                    'reset'     => 'financial_year',
                    'start'     => 1,
                ];

                $sequence = DocumentSequence::create([
                    'document_type'     => $documentType,
                    'document_title'    => $defaults['title'],
                    'prefix'            => $defaults['prefix'],
                    'suffix'            => null,
                    'padding_zeros'     => $defaults['padding'],
                    'starting_number'   => $defaults['start'],
                    'last_number'       => 0,
                    'reset_frequency'   => $defaults['reset'],
                    'last_reset_period' => DocumentSequence::getCurrentPeriod($defaults['reset'], $parsedDate),
                    'branch_id'         => $branchId,
                    'is_active'         => true,
                    'series'            => 'doc:' . $documentType . ($branchId ? ":{$branchId}" : ''),
                ]);

                // Re-lock
                $sequence = DocumentSequence::whereKey($sequence->id)->lockForUpdate()->first();
            }

            // 2. Evaluate Reset Counter Rule
            $currentPeriod = DocumentSequence::getCurrentPeriod($sequence->reset_frequency, $parsedDate);
            if ($sequence->reset_frequency !== 'never' && $sequence->last_reset_period && $sequence->last_reset_period !== $currentPeriod) {
                $sequence->last_number = max(0, (int)$sequence->starting_number - 1);
                $sequence->last_reset_period = $currentPeriod;
            }

            // 3. Resolve dynamic prefix & suffix tokens
            $prefix = $sequence->resolveTokens($sequence->prefix ?: '', $branch, $parsedDate);
            $suffix = $sequence->resolveTokens($sequence->suffix ?: '', $branch, $parsedDate);
            $padding = max(1, (int)($sequence->padding_zeros ?: 4));

            // If counter is below starting number, align it
            if ($sequence->last_number < (int)$sequence->starting_number) {
                $sequence->last_number = max(0, (int)$sequence->starting_number - 1);
            }

            // 4. Generate next candidate & check collision against target table
            $targetMeta = static::defaultDefinitions()[$documentType] ?? null;
            $modelClass = $targetMeta['model'] ?? null;
            $column = $targetMeta['column'] ?? null;

            // If counter is 0 or initial, check if existing records in DB already match this prefix to pre-align
            if ($sequence->last_number == 0 && $modelClass && class_exists($modelClass) && $column) {
                $latestMatching = $modelClass::where($column, 'like', $prefix . '%')->latest('id')->first();
                if ($latestMatching && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/', (string)$latestMatching->{$column}, $m)) {
                    $sequence->last_number = max((int)$m[1], (int)$sequence->starting_number - 1);
                }
            }

            do {
                $sequence->last_number++;
                $candidate = $prefix . str_pad((string)$sequence->last_number, $padding, '0', STR_PAD_LEFT) . $suffix;
                
                $exists = false;
                if ($modelClass && class_exists($modelClass) && $column) {
                    $exists = $modelClass::where($column, $candidate)->exists();
                }
            } while ($exists);

            $sequence->save();

            return $candidate;
        });
    }

    /**
     * Preview the next document number without incrementing or saving.
     */
    public function preview(string $documentType, ?int $branchId = null): string
    {
        $branch = $branchId ? Branch::find($branchId) : null;
        $sequence = DocumentSequence::where('document_type', $documentType)
            ->where(function ($q) use ($branchId) {
                if ($branchId) {
                    $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->orderByRaw('branch_id IS NULL ASC')
            ->first();

        if ($sequence) {
            return $sequence->formatPreview($branch);
        }

        $defaults = static::defaultDefinitions()[$documentType] ?? null;
        if (!$defaults) {
            return 'DOC-2026-0001';
        }

        $dummy = new DocumentSequence([
            'prefix'          => $defaults['prefix'],
            'padding_zeros'   => $defaults['padding'],
            'starting_number' => $defaults['start'],
            'last_number'     => 0,
        ]);

        return $dummy->formatPreview($branch);
    }

    /**
     * Master registry of all voucher types with default prefixes, models, and columns.
     */
    public static function defaultDefinitions(): array
    {
        return [
            'sales_bill' => [
                'title'    => 'Sales Bill / POS Invoice',
                'module'   => 'Sales',
                'prefix'   => 'SB-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\SalesBill::class,
                'column'   => 'bill_number',
            ],
            'sales_quotation' => [
                'title'    => 'Sales Quotation',
                'module'   => 'Sales',
                'prefix'   => 'SQ-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\SalesQuotation::class,
                'column'   => 'quotation_number',
            ],
            'sales_order' => [
                'title'    => 'Sales Order',
                'module'   => 'Sales',
                'prefix'   => 'SO-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\SalesOrder::class,
                'column'   => 'order_number',
            ],
            'delivery_note' => [
                'title'    => 'Sales Delivery Note',
                'module'   => 'Sales',
                'prefix'   => 'DN-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\SalesDeliveryNote::class,
                'column'   => 'delivery_number',
            ],
            'sales_return' => [
                'title'    => 'Sales Return / Credit Note',
                'module'   => 'Sales',
                'prefix'   => 'SR-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\SalesReturn::class,
                'column'   => 'return_number',
            ],
            'purchase_order' => [
                'title'    => 'Purchase Order (PO)',
                'module'   => 'Purchase',
                'prefix'   => 'PO-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\PurchaseOrder::class,
                'column'   => 'po_number',
            ],
            'purchase_invoice' => [
                'title'    => 'Purchase Invoice (PI)',
                'module'   => 'Purchase',
                'prefix'   => 'PI-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\PurchaseInvoice::class,
                'column'   => 'invoice_number',
            ],
            'purchase_return' => [
                'title'    => 'Purchase Return / Debit Note',
                'module'   => 'Purchase',
                'prefix'   => 'PR-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\PurchaseReturn::class,
                'column'   => 'return_number',
            ],
            'purchase_receipt_note' => [
                'title'    => 'Goods Receipt Note (GRN)',
                'module'   => 'Purchase',
                'prefix'   => 'GRN-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\PurchaseReceiptNote::class,
                'column'   => 'receipt_number',
            ],
            'purchase_indent' => [
                'title'    => 'Purchase Indent',
                'module'   => 'Purchase',
                'prefix'   => 'IND-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\PurchaseIndent::class,
                'column'   => 'indent_number',
            ],
            'stock_transfer' => [
                'title'    => 'Stock Transfer Note',
                'module'   => 'Inventory',
                'prefix'   => 'ST-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\StockTransfer::class,
                'column'   => 'transfer_number',
            ],
            'damage_stock' => [
                'title'    => 'Damage Stock Entry',
                'module'   => 'Inventory',
                'prefix'   => 'DMG-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\DamageStock::class,
                'column'   => 'damage_number',
            ],
            'stock_update' => [
                'title'    => 'Stock Adjustment / Update',
                'module'   => 'Inventory',
                'prefix'   => 'STK-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\StockUpdate::class,
                'column'   => 'update_number',
            ],
            'voucher_payment' => [
                'title'    => 'Payment Voucher',
                'module'   => 'Finance',
                'prefix'   => 'PMT-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\Voucher::class,
                'column'   => 'voucher_number',
            ],
            'voucher_receipt' => [
                'title'    => 'Receipt Voucher',
                'module'   => 'Finance',
                'prefix'   => 'RCT-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\Voucher::class,
                'column'   => 'voucher_number',
            ],
            'voucher_journal' => [
                'title'    => 'Journal Voucher (JV)',
                'module'   => 'Finance',
                'prefix'   => 'JV-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\Voucher::class,
                'column'   => 'voucher_number',
            ],
            'voucher_contra' => [
                'title'    => 'Contra Voucher',
                'module'   => 'Finance',
                'prefix'   => 'CTR-{YEAR}-',
                'padding'  => 4,
                'reset'    => 'financial_year',
                'start'    => 1,
                'model'    => \App\Models\Voucher::class,
                'column'   => 'voucher_number',
            ],
        ];
    }
}
