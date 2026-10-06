<?php

namespace App\Services\GST;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use Illuminate\Support\Facades\DB;

/**
 * Real statutory GSTR-3B Computation Service.
 *
 * Derives all figures strictly from real posted database transactions:
 * - Table 3.1: Outward taxable supplies from posted SalesBill documents
 * - Table 3.2: Inter-state unregistered supplies
 * - Table 4(A)(5): Eligible ITC from posted PurchaseInvoice documents
 * - Table 4(B)(2): ITC reversals from posted PurchaseReturn documents
 * - Table 4(C): Net ITC Available
 * - Table 6.1: Tax payment offset (Output Tax vs Input Credit = Cash payable)
 */
class Gstr3bReportService
{
    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
    ) {
    }

    /**
     * Compute full statutory GSTR-3B dataset.
     */
    public function compute(): array
    {
        // -------------------------------------------------------------
        // TABLE 3.1: OUTWARD SUPPLIES & RCM
        // -------------------------------------------------------------
        $salesQuery = SalesBill::query()
            ->whereDate('bill_date', '>=', $this->fromDate)
            ->whereDate('bill_date', '<=', $this->toDate)
            ->where('status', '!=', 'Cancelled');

        $salesCount = (clone $salesQuery)->count();
        $salesTotal = (float) (clone $salesQuery)->sum('total');
        $salesGst   = (float) (clone $salesQuery)->sum('total_gst');
        $salesIgst  = (float) (clone $salesQuery)->sum('total_igst');
        $salesCgst  = (float) (clone $salesQuery)->sum('total_cgst');
        $salesSgst  = (float) (clone $salesQuery)->sum('total_sgst');

        // Handle bills where individual split wasn't stored (e.g. intra-state 50/50 fallback)
        if (($salesCgst + $salesSgst + $salesIgst) == 0 && $salesGst > 0) {
            $salesCgst = round($salesGst / 2, 2);
            $salesSgst = round($salesGst / 2, 2);
        }

        $salesTaxable = round(max(0, $salesTotal - $salesGst), 2);

        // Exempt / Nil rated sales (where total_gst == 0)
        $exemptSalesTotal = (float) (clone $salesQuery)->where('total_gst', '<=', 0)->sum('total');

        $table3_1 = [
            'a' => [
                'desc'    => '(a) Outward taxable supplies (other than zero rated, nil rated and exempted)',
                'taxable' => $salesTaxable,
                'igst'    => $salesIgst,
                'cgst'    => $salesCgst,
                'sgst'    => $salesSgst,
                'cess'    => 0.0,
                'total_tax' => $salesGst,
            ],
            'b' => [
                'desc'    => '(b) Outward taxable supplies (zero rated)',
                'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0, 'total_tax' => 0.0,
            ],
            'c' => [
                'desc'    => '(c) Other outward supplies (Nil rated, exempted)',
                'taxable' => $exemptSalesTotal, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0, 'total_tax' => 0.0,
            ],
            'd' => [
                'desc'    => '(d) Inward supplies liable to reverse charge (RCM)',
                'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0, 'total_tax' => 0.0,
            ],
            'e' => [
                'desc'    => '(e) Non-GST outward supplies',
                'taxable' => 0.0, 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0, 'total_tax' => 0.0,
            ],
        ];

        // -------------------------------------------------------------
        // TABLE 3.2: INTER-STATE UNREGISTERED SUPPLIES
        // -------------------------------------------------------------
        $interStateRows = SalesBill::query()
            ->leftJoin('customers', 'customers.id', '=', 'sales_bills.customer_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->where('sales_bills.sales_type', 'inter_state')
            ->where(function ($q) {
                $q->whereNull('sales_bills.customer_gstin')->orWhere('sales_bills.customer_gstin', '');
            })
            ->where(function ($q) {
                $q->whereNull('customers.gst_no')->orWhere('customers.gst_no', '');
            })
            ->selectRaw("
                COALESCE(NULLIF(customers.state, ''), '24') as pos,
                SUM(sales_bills.total - sales_bills.total_gst) as taxable_value,
                SUM(sales_bills.total_igst) as igst
            ")
            ->groupBy('pos')
            ->get()
            ->map(fn ($r) => [
                'pos'           => $r->pos,
                'taxable_value' => round((float) $r->taxable_value, 2),
                'igst'          => round((float) $r->igst, 2),
            ])
            ->toArray();

        // -------------------------------------------------------------
        // TABLE 4: ELIGIBLE INPUT TAX CREDIT (ITC)
        // -------------------------------------------------------------
        $purQuery = PurchaseInvoice::query()
            ->whereDate('invoice_date', '>=', $this->fromDate)
            ->whereDate('invoice_date', '<=', $this->toDate)
            ->where('status', '!=', 'Cancelled');

        $purCount   = (clone $purQuery)->count();
        $purTotal   = (float) (clone $purQuery)->sum('total');
        $purGst     = (float) (clone $purQuery)->sum('total_gst');
        $purIgst    = (float) (clone $purQuery)->sum('total_igst');
        $purCgst    = (float) (clone $purQuery)->sum('total_cgst');
        $purSgst    = (float) (clone $purQuery)->sum('total_sgst');

        if (($purCgst + $purSgst + $purIgst) == 0 && $purGst > 0) {
            $purCgst = round($purGst / 2, 2);
            $purSgst = round($purGst / 2, 2);
        }

        $purTaxable = round(max(0, $purTotal - $purGst), 2);

        // ITC Reversals from Purchase Returns (Debit Notes)
        $prQuery = PurchaseReturn::query()
            ->whereDate('return_date', '>=', $this->fromDate)
            ->whereDate('return_date', '<=', $this->toDate)
            ->where('status', '!=', 'Cancelled');

        $prCount = (clone $prQuery)->count();
        $revIgst = (float) (clone $prQuery)->sum('total_igst');
        $revCgst = (float) (clone $prQuery)->sum('total_cgst');
        $revSgst = (float) (clone $prQuery)->sum('total_sgst');
        $revGst  = (float) (clone $prQuery)->sum('total_gst');

        if (($revCgst + $revSgst + $revIgst) == 0 && $revGst > 0) {
            $revCgst = round($revGst / 2, 2);
            $revSgst = round($revGst / 2, 2);
        }

        // Net ITC Available = (A) - (B)
        $netIgst = max(0, round($purIgst - $revIgst, 2));
        $netCgst = max(0, round($purCgst - $revCgst, 2));
        $netSgst = max(0, round($purSgst - $revSgst, 2));
        $netTotalItc = round($netIgst + $netCgst + $netSgst, 2);

        $table4 = [
            'available' => [
                'import_goods' => ['desc' => '(1) Import of goods', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0],
                'import_serv'  => ['desc' => '(2) Import of services', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0],
                'rcm'          => ['desc' => '(3) Inward supplies liable to reverse charge', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0],
                'isd'          => ['desc' => '(4) Inward supplies from ISD', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'cess' => 0.0],
                'all_other'    => [
                    'desc'      => '(5) All other ITC (From Purchase Invoices)',
                    'taxable'   => $purTaxable,
                    'igst'      => $purIgst,
                    'cgst'      => $purCgst,
                    'sgst'      => $purSgst,
                    'cess'      => 0.0,
                    'total_itc' => $purGst,
                    'inv_count' => $purCount,
                ],
            ],
            'reversed' => [
                'rule_42_43' => ['desc' => '(1) As per rules 42 & 43 of CGST Rules', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
                'others'     => [
                    'desc'      => '(2) Others (Purchase Returns / Debit Notes)',
                    'igst'      => $revIgst,
                    'cgst'      => $revCgst,
                    'sgst'      => $revSgst,
                    'total'     => $revGst,
                    'ret_count' => $prCount,
                ],
            ],
            'net_itc' => [
                'desc'  => '(C) Net ITC Available (A) - (B)',
                'igst'  => $netIgst,
                'cgst'  => $netCgst,
                'sgst'  => $netSgst,
                'cess'  => 0.0,
                'total' => $netTotalItc,
            ],
            'ineligible' => [
                'sec_17_5' => ['desc' => '(1) As per section 17(5) (Blocked Credit)', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
                'others'   => ['desc' => '(2) Others', 'igst' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0],
            ],
        ];

        // -------------------------------------------------------------
        // TABLE 5: VALUES OF EXEMPT, NIL-RATED & NON-GST INWARD
        // -------------------------------------------------------------
        $exemptPurchases = (float) (clone $purQuery)->where('total_gst', '<=', 0)->sum('total');
        $table5 = [
            'inter_state' => 0.0,
            'intra_state' => $exemptPurchases,
        ];

        // -------------------------------------------------------------
        // TABLE 6.1: PAYMENT OF TAX & SET-OFF
        // -------------------------------------------------------------
        // Offset Rules:
        // Integrated Tax paid through: IGST ITC first, then CGST/SGST ITC
        // Central Tax paid through: CGST ITC first, then IGST ITC
        // State Tax paid through: SGST ITC first, then IGST ITC
        $itcIgstRem = $netIgst;
        $itcCgstRem = $netCgst;
        $itcSgstRem = $netSgst;

        // 1. Pay IGST
        $igstPaidByIgst = min($salesIgst, $itcIgstRem);
        $itcIgstRem    -= $igstPaidByIgst;
        $igstRemaining  = $salesIgst - $igstPaidByIgst;

        $igstPaidByCgst = min($igstRemaining, $itcCgstRem);
        $itcCgstRem    -= $igstPaidByCgst;
        $igstRemaining -= $igstPaidByCgst;

        $igstPaidBySgst = min($igstRemaining, $itcSgstRem);
        $itcSgstRem    -= $igstPaidBySgst;
        $igstRemaining -= $igstPaidBySgst;

        $igstCashPayable = max(0, $igstRemaining);

        // 2. Pay CGST
        $cgstPaidByCgst = min($salesCgst, $itcCgstRem);
        $itcCgstRem    -= $cgstPaidByCgst;
        $cgstRemaining  = $salesCgst - $cgstPaidByCgst;

        $cgstPaidByIgst = min($cgstRemaining, $itcIgstRem);
        $itcIgstRem    -= $cgstPaidByIgst;
        $cgstRemaining -= $cgstPaidByIgst;

        $cgstCashPayable = max(0, $cgstRemaining);

        // 3. Pay SGST
        $sgstPaidBySgst = min($salesSgst, $itcSgstRem);
        $itcSgstRem    -= $sgstPaidBySgst;
        $sgstRemaining  = $salesSgst - $sgstPaidBySgst;

        $sgstPaidByIgst = min($sgstRemaining, $itcIgstRem);
        $itcIgstRem    -= $sgstPaidByIgst;
        $sgstRemaining -= $sgstPaidByIgst;

        $sgstCashPayable = max(0, $sgstRemaining);

        $totalCashPayable = round($igstCashPayable + $cgstCashPayable + $sgstCashPayable, 2);
        $totalItcUtilized = round(($salesGst - $totalCashPayable), 2);
        $totalItcBalance  = round($itcIgstRem + $itcCgstRem + $itcSgstRem, 2);

        $table6_1 = [
            'igst' => [
                'payable'    => $salesIgst,
                'paid_itc'   => round($salesIgst - $igstCashPayable, 2),
                'paid_cash'  => $igstCashPayable,
            ],
            'cgst' => [
                'payable'    => $salesCgst,
                'paid_itc'   => round($salesCgst - $cgstCashPayable, 2),
                'paid_cash'  => $cgstCashPayable,
            ],
            'sgst' => [
                'payable'    => $salesSgst,
                'paid_itc'   => round($salesSgst - $sgstCashPayable, 2),
                'paid_cash'  => $sgstCashPayable,
            ],
            'total' => [
                'output_tax'       => $salesGst,
                'itc_utilized'     => max(0, $totalItcUtilized),
                'net_cash_payable' => $totalCashPayable,
                'itc_carry_forward'=> $totalItcBalance,
            ],
        ];

        return [
            'from_date'   => $this->fromDate,
            'to_date'     => $this->toDate,
            'sales_count' => $salesCount,
            'pur_count'   => $purCount,
            'table_3_1'   => $table3_1,
            'table_3_2'   => $interStateRows,
            'table_4'     => $table4,
            'table_5'     => $table5,
            'table_6_1'   => $table6_1,
        ];
    }
}
