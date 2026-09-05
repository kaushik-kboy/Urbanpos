<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesReturn;

class LedgerPostingService
{
    public function postSalesBill(SalesBill $bill): void
    {
        $this->reverse(SalesBill::class, $bill->id);

        $customerLedger = $bill->customer->ledger ?? Ledger::findOrCreateSystemLedger($bill->customer->name, 'Sundry Debtors');
        $salesLedger = Ledger::findOrCreateSystemLedger('Sales Account', 'Sales Account');
        $gstLedger = Ledger::findOrCreateSystemLedger('GST Payable', 'Duties & Taxes');

        $taxable = round($bill->total - $bill->total_gst, 2);

        $lines = [
            ['ledger_id' => $customerLedger->id, 'debit' => $bill->total, 'credit' => 0],
            ['ledger_id' => $salesLedger->id, 'debit' => 0, 'credit' => $taxable],
        ];

        if ($bill->total_gst > 0) {
            $lines[] = ['ledger_id' => $gstLedger->id, 'debit' => 0, 'credit' => $bill->total_gst];
        }

        $this->createEntry('Sales', $bill->bill_date, $bill->branch_id, SalesBill::class, $bill->id,
            "Sales Bill {$bill->bill_number}", $lines);
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

    public function reverse(string $referenceType, int $referenceId): void
    {
        JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get()
            ->each->delete();
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

        $next = (JournalEntry::max('id') ?? 0) + 1;

        return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
