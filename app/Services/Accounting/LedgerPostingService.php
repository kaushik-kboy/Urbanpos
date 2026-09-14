<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Models\TenderType;
use App\Models\TenderTypeValue;

class LedgerPostingService
{
    public function __construct(private DocumentNumberingService $numbering)
    {
    }

    public function postSalesBill(SalesBill $bill): void
    {
        $this->reverse(SalesBill::class, $bill->id);

        $salesLedger = Ledger::findOrCreateSystemLedger('Sales Account', 'Sales Account');
        $gstLedger = Ledger::findOrCreateSystemLedger('GST Payable', 'Duties & Taxes');

        $taxable = round($bill->total - $bill->total_gst, 2);

        $lines = $this->debitLinesForSale($bill);
        $lines[] = ['ledger_id' => $salesLedger->id, 'debit' => 0, 'credit' => $taxable];

        if ($bill->total_gst > 0) {
            $lines[] = ['ledger_id' => $gstLedger->id, 'debit' => 0, 'credit' => $bill->total_gst];
        }

        $this->createEntry('Sales', $bill->bill_date, $bill->branch_id, SalesBill::class, $bill->id,
            "Sales Bill {$bill->bill_number}", $lines);
    }

    /**
     * Additive: a bill with no sales_bill_payments rows (every bill before Phase 7, and
     * any bill today that doesn't opt into split-tender) debits the customer ledger for
     * the full total exactly as it always has. A bill WITH payment rows splits the debit
     * across each tender's resolved ledger instead — a "Credit" tender still debits the
     * customer ledger, same as the old single-line behavior, just for that slice only.
     */
    private function debitLinesForSale(SalesBill $bill): array
    {
        $payments = $bill->payments()->with(['tenderType', 'tenderTypeValue'])->get();

        if ($payments->isEmpty()) {
            $customerLedger = $bill->customer->ledger ?? Ledger::findOrCreateSystemLedger($bill->customer->name, 'Sundry Debtors');

            return [['ledger_id' => $customerLedger->id, 'debit' => (float) $bill->total, 'credit' => 0]];
        }

        return $payments->map(function ($payment) use ($bill) {
            $ledger = $payment->tenderType->type === 'Credit'
                ? ($bill->customer->ledger ?? Ledger::findOrCreateSystemLedger($bill->customer->name, 'Sundry Debtors'))
                : $this->resolvePaymentLedger($payment->tenderType, $payment->tenderTypeValue);

            return ['ledger_id' => $ledger->id, 'debit' => (float) $payment->amount, 'credit' => 0];
        })->all();
    }

    /**
     * TenderTypeValue.group_ledger is a free-text hint, not a strict FK to `ledgers` —
     * resolved by name+group via the same findOrCreateSystemLedger() every other system
     * ledger uses. Falls back to a shared Cash-in-Hand/Bank-Account ledger when a tender
     * value isn't configured with one.
     */
    private function resolvePaymentLedger(TenderType $tenderType, ?TenderTypeValue $tenderTypeValue): Ledger
    {
        $isCash = $tenderType->type === 'Cash';
        $group = $isCash ? 'Cash in Hand' : 'Bank Account';
        $name = $tenderTypeValue?->group_ledger ?: ($isCash ? 'Cash in Hand' : $tenderType->name);

        return Ledger::findOrCreateSystemLedger($name, $group);
    }

    public function postPurchaseInvoice(PurchaseInvoice $invoice): void
    {
        $this->reverse(PurchaseInvoice::class, $invoice->id);

        $supplierLedger = $invoice->supplier->ledger ?? Ledger::findOrCreateSystemLedger($invoice->supplier->name, 'Sundry Creditors');
        $purchaseLedger = Ledger::findOrCreateSystemLedger('Purchase Account', 'Purchase Account');
        $gstLedger = Ledger::findOrCreateSystemLedger('GST Input', 'Duties & Taxes');

        $taxable = round($invoice->total - $invoice->total_gst, 2);

        $lines = [
            ['ledger_id' => $purchaseLedger->id, 'debit' => $taxable, 'credit' => 0],
            ['ledger_id' => $supplierLedger->id, 'debit' => 0, 'credit' => $invoice->total],
        ];

        if ($invoice->total_gst > 0) {
            $lines[] = ['ledger_id' => $gstLedger->id, 'debit' => $invoice->total_gst, 'credit' => 0];
        }

        $this->createEntry('Purchase', $invoice->invoice_date, $invoice->branch_id, PurchaseInvoice::class, $invoice->id,
            "Purchase Invoice {$invoice->invoice_number}", $lines);
    }

    public function postSalesReturn(SalesReturn $return): void
    {
        $this->reverse(SalesReturn::class, $return->id);

        $customerLedger = $return->customer->ledger ?? Ledger::findOrCreateSystemLedger($return->customer->name, 'Sundry Debtors');
        $salesLedger = Ledger::findOrCreateSystemLedger('Sales Account', 'Sales Account');
        $gstLedger = Ledger::findOrCreateSystemLedger('GST Payable', 'Duties & Taxes');

        $taxable = round($return->total - $return->total_gst, 2);

        $lines = [
            ['ledger_id' => $salesLedger->id, 'debit' => $taxable, 'credit' => 0],
            ['ledger_id' => $customerLedger->id, 'debit' => 0, 'credit' => $return->total],
        ];

        if ($return->total_gst > 0) {
            $lines[] = ['ledger_id' => $gstLedger->id, 'debit' => $return->total_gst, 'credit' => 0];
        }

        $this->createEntry('Sales Return', $return->return_date, $return->branch_id, SalesReturn::class, $return->id,
            "Sales Return {$return->return_number}", $lines);
    }

    /**
     * Posts an offsetting (debit/credit swapped) entry for every not-yet-reversed
     * journal entry tied to a source document, instead of deleting it — preserves
     * journal history across edits per the foundation spec's reversal rule. Safe to
     * call more than once; entries that already have a reversal are skipped.
     */
    public function reverse(string $referenceType, int $referenceId): void
    {
        $originals = JournalEntry::with('lines')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversal_of')
            ->get();

        foreach ($originals as $original) {
            $alreadyReversed = JournalEntry::where('reversal_of', $original->id)->exists();
            if ($alreadyReversed) {
                continue;
            }

            $reversalLines = $original->lines->map(fn ($line) => [
                'ledger_id' => $line->ledger_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
            ])->all();

            $reversal = JournalEntry::create([
                'voucher_number' => $this->nextNumber($original->voucher_type),
                'voucher_type' => $original->voucher_type,
                'voucher_date' => now()->toDateString(),
                'branch_id' => $original->branch_id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'narration' => "Reversal of {$original->voucher_number}",
                'total_debit' => collect($reversalLines)->sum('debit'),
                'total_credit' => collect($reversalLines)->sum('credit'),
                'reversal_of' => $original->id,
            ]);

            $reversal->lines()->createMany($reversalLines);
        }
    }

    private function createEntry(string $voucherType, $date, int $branchId, string $referenceType, int $referenceId, string $narration, array $lines): void
    {
        $entry = JournalEntry::create([
            'voucher_number' => $this->nextNumber($voucherType),
            'voucher_type' => $voucherType,
            'voucher_date' => $date,
            'branch_id' => $branchId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'narration' => $narration,
            'total_debit' => collect($lines)->sum('debit'),
            'total_credit' => collect($lines)->sum('credit'),
        ]);

        $entry->lines()->createMany($lines);
    }

    private function nextNumber(string $voucherType): string
    {
        $prefix = match ($voucherType) {
            'Sales' => 'JV-S',
            'Purchase' => 'JV-P',
            'Sales Return' => 'JV-SR',
            'Purchase Return' => 'JV-PR',
            'Payment' => 'PMT',
            'Receipt' => 'RCT',
            'Contra' => 'CTR',
            default => 'JV',
        };

        $next = $this->numbering->next('voucher:'.$voucherType);

        return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
