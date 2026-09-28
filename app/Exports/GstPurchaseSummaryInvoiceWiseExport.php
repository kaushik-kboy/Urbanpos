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
 * GST Purchase Summary — invoice-wise Excel export. ONE POSTED PURCHASE
 * INVOICE = ONE ROW. This is a separate, additional export next to the
 * existing HSN-wise summary report (gst-purchase-summary.blade.php, whose
 * own aggregation was audited and confirmed mathematically correct — see
 * docs/GST-PURCHASE-SUMMARY-EXPORT.md §1); that HSN view is left untouched.
 *
 * Deliberately built on FromQuery (chunked iteration), same reasoning as
 * GstSalesTaxwiseExport.
 *
 * Column-mapping decision for MULTIPLE GST RATES ON ONE INVOICE (documented
 * in full in docs/GST-PURCHASE-SUMMARY-EXPORT.md §4 — the client's own spec
 * only has a single "Purchase tax %" / SGST / CGST / IGST column group, no
 * per-rate repeat block like the sales-taxwise export has):
 *   - The four *_TaxAmt columns (SGST/CGST/IGST TaxAmt) are unambiguous —
 *     they are SUMS of the real posted amounts across every line on the
 *     invoice, regardless of how many different rates are present.
 *   - The four *_Perc columns (Purchase tax %, SGST Perc, CGST Perc, IGST
 *     Perc) cannot be summed (a "sum of percentages" is not a real GST rate)
 *     and cannot be silently picked as "the first line's rate" (that would
 *     hide the other rates present). Per the combined-display option the
 *     user themselves offered, each is rendered as a comma-separated list of
 *     the DISTINCT non-zero rates actually present on that invoice's lines
 *     for that tax type (e.g. "5, 18" for a mixed-rate invoice), sorted
 *     ascending. An invoice with only one rate (the common case) simply
 *     shows that one number, matching the spec's plain expectation exactly.
 *     Nothing is invented or averaged — every number shown is a real,
 *     posted line-level rate.
 *   - SGST%/CGST% are derived as gst_percent/2 for intrastate lines
 *     (sgst_amount > 0 or cgst_amount > 0) — standard GST math (SGST+CGST
 *     together equal the item's GST rate for an intra-state purchase), not a
 *     fabricated value. IGST% is the line's own gst_percent for interstate
 *     lines (igst_amount > 0). A line only ever has one or the other set of
 *     amounts populated (never both), per how this app already posts GST.
 *
 * Invoices with ZERO line items: found while reconciling against real QA
 * data that 1,372 posted, non-cancelled invoices in this dataset are
 * header-only (no items, total = 0.00). The query is built FROM
 * purchase_invoices with a LEFT JOIN to items (not the reverse) specifically
 * so these still produce one row each, with real zeros, rather than silently
 * vanishing from the export — an inner join from the items table would have
 * dropped them, breaking "one purchase invoice = one row".
 */
class GstPurchaseSummaryInvoiceWiseExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithEvents
{
    use Exportable;

    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
        private readonly ?int $branchId = null,
    ) {
    }

    public function query()
    {
        $gstinExpr = "COALESCE(NULLIF(pi.supplier_gstin, ''), NULLIF(s.gst_no, ''))";

        // FROM purchase_invoices (not purchase_invoice_items) with a LEFT
        // JOIN to items — found while reconciling against real QA data that
        // 1,372 posted, non-cancelled invoices in this dataset have ZERO
        // line items (a header-only row, total = 0.00). Starting from the
        // items table (an inner join) would silently drop these from the
        // export entirely, which breaks "one purchase invoice = one row" —
        // an invoice that genuinely has nothing to tax should still appear,
        // with real zeros, not vanish.
        return DB::table('purchase_invoices as pi')
            ->leftJoin('purchase_invoice_items as pii', 'pii.purchase_invoice_id', '=', 'pi.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'pi.supplier_id')
            ->where('pi.invoice_date', '>=', $this->fromDate)
            ->where('pi.invoice_date', '<=', $this->toDate)
            // Same rule as the existing HSN-wise summary report and the
            // GST Sales Taxwise export: Cancelled invoices are not a taxable
            // purchase at all. Legacy rows with a NULL status are included
            // (pre-dates the posting-lifecycle column).
            ->where(fn ($q) => $q->whereNull('pi.status')->orWhere('pi.status', '!=', 'Cancelled'))
            ->when($this->branchId, fn ($q) => $q->where('pi.branch_id', $this->branchId))
            ->groupBy(
                'pi.id', 'pi.invoice_number', 'pi.invoice_date',
                's.name', 'pi.supplier_gstin', 's.gst_no', 's.state',
                'pi.total', 'pi.freight', 'pi.tcs_amount'
            )
            ->orderBy('pi.invoice_date')->orderBy('pi.id')
            ->selectRaw("
                pi.id,
                pi.invoice_number,
                pi.invoice_date,
                COALESCE(s.name, 'Unknown Supplier') as supplier_name,
                {$gstinExpr} as gst_no,
                s.state as state_name,
                SUM(COALESCE(pii.net_amount, 0) - COALESCE(pii.gst_tax_amount, 0)) as taxable_amount,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.gst_percent IS NOT NULL THEN pii.gst_percent END) as tax_percent_list,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.sgst_amount > 0 THEN pii.gst_percent / 2.0 END) as sgst_percent_list,
                SUM(COALESCE(pii.sgst_amount, 0)) as sgst_tax_amt,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.cgst_amount > 0 THEN pii.gst_percent / 2.0 END) as cgst_percent_list,
                SUM(COALESCE(pii.cgst_amount, 0)) as cgst_tax_amt,
                GROUP_CONCAT(DISTINCT CASE WHEN pii.igst_amount > 0 THEN pii.gst_percent END) as igst_percent_list,
                SUM(COALESCE(pii.igst_amount, 0)) as igst_tax_amt,
                pi.total as total_amount,
                pi.freight as freight_charges,
                pi.tcs_amount as tcs_amt
            ");
    }

    public function headings(): array
    {
        return [
            'Inv No', 'Inv date', 'Supplier name', 'GST No.', 'State Name',
            'Taxable amount', 'Purchase tax %',
            'SGST Perc', 'SGST TaxAmt',
            'CGST Perc', 'CGST TaxAmt',
            'IGST Perc', 'IGST TaxAmt',
            'Total amount', 'Freight charges', 'TCS Amt',
        ];
    }

    public function map($row): array
    {
        $fmt = fn (float $v) => number_format(round($v, 2), 2, '.', '');

        // GROUP_CONCAT(DISTINCT ...) has no portable ORDER BY across MySQL
        // and the sqlite driver this test suite runs on, so rates are sorted
        // ascending here in PHP instead. Also trims trailing ".00"/".0" off
        // each value so "18.00" reads as "18" while "2.50" (half of a 5%
        // rate) stays as-is — cosmetic only, the underlying value is
        // untouched.
        $trimPercent = function (?string $csv): string {
            if ($csv === null || $csv === '') {
                return '';
            }

            $values = array_map('floatval', explode(',', $csv));
            sort($values, SORT_NUMERIC);

            return implode(', ', array_map(
                fn ($p) => rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.'),
                $values
            ));
        };

        return [
            $row->invoice_number,
            ExcelDate::PHPToExcel(strtotime($row->invoice_date)),
            $row->supplier_name,
            $row->gst_no ?: '',
            $row->state_name ?: '',
            $fmt((float) $row->taxable_amount),
            $trimPercent($row->tax_percent_list),
            $trimPercent($row->sgst_percent_list),
            $fmt((float) $row->sgst_tax_amt),
            $trimPercent($row->cgst_percent_list),
            $fmt((float) $row->cgst_tax_amt),
            $trimPercent($row->igst_percent_list),
            $fmt((float) $row->igst_tax_amt),
            $fmt((float) $row->total_amount),
            $fmt((float) $row->freight_charges),
            $fmt((float) $row->tcs_amt),
        ];
    }

    public function columnFormats(): array
    {
        $money = '0.00';

        return [
            'B' => 'DD-MMM-YYYY',
            'F' => $money,
            'I' => $money,
            'K' => $money,
            'M' => $money,
            'N' => $money,
            'O' => $money,
            'P' => $money,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->getStyle('A1:P1')->getFont()->setBold(true);
                $sheet->getParent()->getActiveSheet()->setTitle('GST Purchase Summary');
            },
        ];
    }
}
