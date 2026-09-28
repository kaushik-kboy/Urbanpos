<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * GST Sales Taxwise export — ONE POSTED SALES BILL = ONE ROW, with 0%/5%/18%
 * taxable + IGST/CGST/SGST breakup as horizontal columns. See
 * docs/GST-SALES-TAXWISE-EXPORT.md for the full audit, column-mapping
 * decisions (columns 16 and 26 in particular), and reconciliation tests.
 *
 * Deliberately built on FromQuery (Laravel Excel chunks through the query in
 * batches, e.g. 1000 rows at a time) rather than loading every bill into a
 * Collection up front — this app's real posted-bill volume is large enough
 * (tens of thousands per month) that an unchunked export would be exactly the
 * "unbounded collection hydration" this task explicitly asked to avoid.
 *
 * Scope decision (documented, not silent): this export covers SALES BILLS
 * only, not sales returns/credit notes — "gst-sales-taxwise" is one of many
 * report slugs under isSalesReport() in DynamicReportService, and returns
 * already have their own separate 'sale-return-*' report family. If the
 * client wants returns included (net or separate), that's a distinct,
 * explicit follow-up request, not something to fold in silently here.
 */
class GstSalesTaxwiseExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
        private readonly ?int $branchId = null,
        private readonly ?string $search = null,
    ) {
    }

    public function query()
    {
        $gstinExpr = "COALESCE(NULLIF(sales_bills.customer_gstin, ''), NULLIF(customers.gst_no, ''))";

        $q = DB::table('sales_bills')
            ->join('sales_bill_items', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_bills.customer_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            // Only real, posted sales count as taxable supply for a GST report —
            // Draft bills aren't finalized and Cancelled ones aren't a supply at
            // all (same rule already established for GSTR-1 in this codebase).
            ->where('sales_bills.status', 'Posted')
            ->when($this->branchId, fn ($qq) => $qq->where('sales_bills.branch_id', $this->branchId))
            ->when($this->search, function ($qq) {
                $term = '%' . $this->search . '%';
                $qq->where(function ($q2) use ($term) {
                    $q2->where('sales_bills.bill_number', 'like', $term)
                        ->orWhere('customers.name', 'like', $term);
                });
            })
            ->groupBy(
                'sales_bills.id',
                'sales_bills.bill_number',
                'sales_bills.bill_date',
                'customers.name',
                'sales_bills.customer_gstin',
                'customers.gst_no',
                'customers.state',
                'sales_bills.total'
            )
            ->orderBy('sales_bills.bill_date')
            ->orderBy('sales_bills.id')
            ->selectRaw("
                sales_bills.id,
                sales_bills.bill_number,
                sales_bills.bill_date,
                COALESCE(customers.name, 'Walk-in Customer') as customer_name,
                {$gstinExpr} as gst_no,
                customers.state as state_name,
                SUM(CASE WHEN sales_bill_items.gst_percent = 0 THEN (sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) ELSE 0 END) as taxable_0_amount,
                SUM(CASE WHEN sales_bill_items.gst_percent = 5 THEN (sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) ELSE 0 END) as taxable_5_amount,
                SUM(CASE WHEN sales_bill_items.gst_percent = 18 THEN (sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) ELSE 0 END) as taxable_18_amount,
                SUM(CASE WHEN sales_bill_items.gst_percent = 5 THEN sales_bill_items.igst_amount ELSE 0 END) as igst_5_amt,
                SUM(CASE WHEN sales_bill_items.gst_percent = 5 THEN sales_bill_items.sgst_amount ELSE 0 END) as sgst_5_amt,
                SUM(CASE WHEN sales_bill_items.gst_percent = 5 THEN sales_bill_items.cgst_amount ELSE 0 END) as cgst_5_amt,
                SUM(CASE WHEN sales_bill_items.gst_percent = 18 THEN sales_bill_items.igst_amount ELSE 0 END) as igst_18_amt,
                SUM(CASE WHEN sales_bill_items.gst_percent = 18 THEN sales_bill_items.cgst_amount ELSE 0 END) as cgst_18_amt,
                SUM(CASE WHEN sales_bill_items.gst_percent = 18 THEN sales_bill_items.sgst_amount ELSE 0 END) as sgst_18_amt,
                sales_bills.total as total_amount
            ");

        return $q;
    }

    /**
     * Column order and headings exactly as specified by the client, including
     * the two literal headers preserved verbatim rather than silently renamed:
     *
     * - Col 16 "Inv Noble_0_amount": columns 16-25 clearly mirror columns 6-15
     *   (taxable_5/18, igst/cgst/sgst_5/18, Total amount all repeat verbatim),
     *   so position 16 — the "0% slot" of that repeated block — was almost
     *   certainly meant to be another "taxable_0_amount", with "Inv No"
     *   bleeding in from an adjacent cell during whatever copy/extraction
     *   produced this list. Mapped to the SAME real taxable_0_amount value,
     *   header kept exactly as supplied (not renamed).
     * - Col 26 "Inv No": appears once, at the very end, with no other new
     *   columns around it — read as a second copy of the bill/invoice number
     *   for reference at the far right of a wide sheet (a common practice so
     *   the reader doesn't need to scroll back to column 1), not a separate
     *   document-number field (no such second number exists in this schema).
     *   Mapped to the SAME bill_number as "Bill No".
     *
     * See docs/GST-SALES-TAXWISE-EXPORT.md for the full reasoning.
     */
    public function headings(): array
    {
        return [
            'Bill No', 'Bill Date', 'Customer Name', 'GST No.', 'State Name',
            'taxable_0_amount', 'taxable_5_amount', 'taxable_18_amount',
            'igst_5_amt', 'sgst_5_amt', 'cgst_5_amt',
            'igst_18_amt', 'cgst_18_amt', 'sgst_18_amt',
            'Total amount',
            'Inv Noble_0_amount', 'taxable_5_amount', 'taxable_18_amount',
            'igst_5_amt', 'sgst_5_amt', 'cgst_5_amt',
            'igst_18_amt', 'cgst_18_amt', 'sgst_18_amt',
            'Total amount', 'Inv No',
        ];
    }

    public function map($row): array
    {
        $taxable0 = round((float) $row->taxable_0_amount, 2);
        $taxable5 = round((float) $row->taxable_5_amount, 2);
        $taxable18 = round((float) $row->taxable_18_amount, 2);
        $igst5 = round((float) $row->igst_5_amt, 2);
        $sgst5 = round((float) $row->sgst_5_amt, 2);
        $cgst5 = round((float) $row->cgst_5_amt, 2);
        $igst18 = round((float) $row->igst_18_amt, 2);
        $cgst18 = round((float) $row->cgst_18_amt, 2);
        $sgst18 = round((float) $row->sgst_18_amt, 2);
        $total = round((float) $row->total_amount, 2);

        // Found while verifying the real export output (not just assuming a
        // successful download meant it was right): Laravel Excel/PhpSpreadsheet
        // silently writes a raw PHP int(0) or float(0.0) cell value as an EMPTY
        // cell, not a numeric zero — confirmed in isolation with a minimal
        // FromArray export outside this class entirely. A numeric STRING like
        // "0.00" does not trigger it (PhpSpreadsheet's own value binder detects
        // numeric strings and stores them as real numeric cells anyway — same
        // outcome, sidesteps the bug), so every amount is formatted via
        // number_format() rather than left as a bare float.
        $fmt = fn (float $v) => number_format($v, 2, '.', '');
        [$taxable0, $taxable5, $taxable18, $igst5, $sgst5, $cgst5, $igst18, $cgst18, $sgst18, $total] =
            array_map($fmt, [$taxable0, $taxable5, $taxable18, $igst5, $sgst5, $cgst5, $igst18, $cgst18, $sgst18, $total]);

        return [
            $row->bill_number,
            ExcelDate::PHPToExcel(strtotime($row->bill_date)),
            $row->customer_name,
            $row->gst_no ?: '',
            $row->state_name ?: '',
            $taxable0, $taxable5, $taxable18,
            $igst5, $sgst5, $cgst5,
            $igst18, $cgst18, $sgst18,
            $total,
            // Columns 16-25: see the headings() docblock for the mapping decision.
            $taxable0, $taxable5, $taxable18,
            $igst5, $sgst5, $cgst5,
            $igst18, $cgst18, $sgst18,
            $total,
            $row->bill_number,
        ];
    }

    public function columnFormats(): array
    {
        $money = '0.00';
        return [
            'B' => 'DD-MMM-YYYY',
            'F' => $money, 'G' => $money, 'H' => $money,
            'I' => $money, 'J' => $money, 'K' => $money,
            'L' => $money, 'M' => $money, 'N' => $money,
            'O' => $money,
            'P' => $money, 'Q' => $money, 'R' => $money,
            'S' => $money, 'T' => $money, 'U' => $money,
            'V' => $money, 'W' => $money, 'X' => $money,
            'Y' => $money,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->getStyle('A1:Z1')->getFont()->setBold(true);
                $sheet->getParent()->getActiveSheet()->setTitle('GST Sales Taxwise');
            },
        ];
    }
}
