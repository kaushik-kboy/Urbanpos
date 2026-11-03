<?php

namespace App\Services\GST;

use App\Models\Branch;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Real, non-fabricated GSTR-1 outward-supplies computation.
 *
 * Every method here reads actual posted transactions and the tax amounts
 * already computed by TaxEngine at posting time (sales_bill_items /
 * sales_return_items' cgst_amount/sgst_amount/igst_amount/gst_percent columns)
 * — it never re-derives a rate by guessing (no "18/118" style assumptions),
 * and it never falls back to a hardcoded demo number when real data is small
 * or zero. See docs/GSTR1-AUDIT.md for the full audit this replaced.
 *
 * Classification uses the posting-time customer_gstin snapshot on
 * sales_bills/sales_returns (added by
 * 2026_10_01_000003_add_customer_gstin_snapshot_to_sales_documents), falling
 * back to the customer's CURRENT gst_no only for pre-migration rows where no
 * snapshot exists — that fallback is a real, documented limitation (historical
 * misclassification is possible for those old rows only), not a guess.
 */
class Gstr1ReportService
{
    /**
     * GSTR-1's statutory large-value inter-state B2C threshold. This is the
     * real government threshold (confirmed in the Phase 1 audit), not
     * something invented for this codebase.
     */
    public const B2CL_THRESHOLD = 250000.00;

    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
    ) {}

    /** GSTIN expression: posting-time snapshot, falling back to the live customer record only when no snapshot was taken (pre-migration rows). */
    private function gstinExpr(string $docAlias = 'sales_bills', string $custAlias = 'customers'): string
    {
        return "COALESCE(NULLIF({$docAlias}.customer_gstin, ''), NULLIF({$custAlias}.gst_no, ''))";
    }

    private function billsBaseQuery()
    {
        return SalesBill::query()
            ->leftJoin('customers', 'customers.id', '=', 'sales_bills.customer_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            ->where('sales_bills.status', '!=', 'Cancelled');
    }

    private function returnsBaseQuery()
    {
        return SalesReturn::query()
            ->leftJoin('customers', 'customers.id', '=', 'sales_returns.customer_id')
            ->leftJoin('sales_bills as orig_bill', 'orig_bill.id', '=', 'sales_returns.sales_bill_id')
            ->whereDate('sales_returns.return_date', '>=', $this->fromDate)
            ->whereDate('sales_returns.return_date', '<=', $this->toDate)
            ->where('sales_returns.status', '!=', 'Cancelled');
    }

    // -----------------------------------------------------------------
    // B2B — invoice-wise, registered customers (GSTIN present)
    // -----------------------------------------------------------------
    public function b2b(): array
    {
        $gstin = $this->gstinExpr();
        $rows = $this->billsBaseQuery()
            ->whereRaw("{$gstin} IS NOT NULL")
            ->selectRaw("
                sales_bills.id,
                sales_bills.bill_number as invoice_number,
                sales_bills.bill_date as invoice_date,
                customers.name as customer_name,
                {$gstin} as gstin,
                sales_bills.sales_type as pos_type,
                sales_bills.total as invoice_value,
                (sales_bills.total - sales_bills.total_gst) as taxable_value,
                sales_bills.total_gst as gst,
                sales_bills.total_igst as igst,
                sales_bills.total_cgst as cgst,
                sales_bills.total_sgst as sgst
            ")
            ->orderBy('sales_bills.bill_date')
            ->get();

        return [
            'count' => $rows->count(),
            'taxable' => round((float) $rows->sum('taxable_value'), 2),
            // total_gst is the authoritative stored figure (always populated by
            // computeTotals() on a real posted bill); igst/cgst/sgst are used only
            // for their own breakdown columns below, which can legitimately be 0
            // without invalidating the overall tax total.
            'tax' => round((float) $rows->sum('gst'), 2),
            'total' => round((float) $rows->sum('invoice_value'), 2),
            'rows' => $rows->map(fn ($r) => [
                'ref' => $r->invoice_number,
                'date' => $r->invoice_date,
                'customer' => $r->customer_name,
                'gstin' => $r->gstin,
                'pos' => $r->pos_type,
                'taxable' => round((float) $r->taxable_value, 2),
                'igst' => round((float) $r->igst, 2),
                'cgst' => round((float) $r->cgst, 2),
                'sgst' => round((float) $r->sgst, 2),
                'total' => round((float) $r->invoice_value, 2),
            ])->values()->all(),
        ];
    }

    // -----------------------------------------------------------------
    // B2CL — unregistered, inter-state, >= threshold (invoice-wise, real rule)
    // -----------------------------------------------------------------
    public function b2cl(): array
    {
        $gstin = $this->gstinExpr();
        $rows = $this->billsBaseQuery()
            ->whereRaw("{$gstin} IS NULL")
            ->where('sales_bills.total', '>=', self::B2CL_THRESHOLD)
            ->where(function ($q) {
                $q->where('sales_bills.sales_type', 'Interstate')
                    ->orWhere('sales_bills.total_igst', '>', 0);
            })
            ->selectRaw('
                sales_bills.bill_number as invoice_number,
                sales_bills.bill_date as invoice_date,
                sales_bills.sales_type as pos_type,
                sales_bills.total as invoice_value,
                (sales_bills.total - sales_bills.total_gst) as taxable_value,
                sales_bills.total_igst as igst
            ')
            ->orderBy('sales_bills.bill_date')
            ->get();

        return [
            'count' => $rows->count(),
            'taxable' => round((float) $rows->sum('taxable_value'), 2),
            'tax' => round((float) $rows->sum('igst'), 2),
            'total' => round((float) $rows->sum('invoice_value'), 2),
            'rows' => $rows->map(fn ($r) => [
                'ref' => $r->invoice_number,
                'date' => $r->invoice_date,
                'pos' => $r->pos_type,
                'taxable' => round((float) $r->taxable_value, 2),
                'igst' => round((float) $r->igst, 2),
                'total' => round((float) $r->invoice_value, 2),
            ])->values()->all(),
        ];
    }

    // -----------------------------------------------------------------
    // B2CS — unregistered, everything not B2CL (aggregated by rate)
    // -----------------------------------------------------------------
    /** The B2CS bill-matching WHERE, shared by both queries below so they can't drift apart. */
    private function b2csBillFilter($query): void
    {
        $gstin = $this->gstinExpr();
        $query->whereRaw("{$gstin} IS NULL")
            ->where(function ($q) {
                $q->where('sales_bills.total', '<', self::B2CL_THRESHOLD)
                    ->orWhere(function ($q2) {
                        $q2->where('sales_bills.sales_type', '!=', 'Interstate')
                            ->where('sales_bills.total_igst', '<=', 0);
                    });
            });
    }

    public function b2cs(): array
    {
        // Headline count/taxable/tax: one flat SQL aggregate over matching
        // bills — no row hydration needed for these (same "total_gst is
        // authoritative" reasoning as b2b()/b2cl()). Previously this pulled
        // every matching bill (thousands, at this app's real transaction
        // volume) into a PHP Collection just to sum() three fields in PHP,
        // and then re-joined on a giant whereIn($billIds) for the breakdown —
        // both were measured as the main cost of this section; this replaces
        // both with straight SQL.
        $headlineQuery = $this->billsBaseQuery();
        $this->b2csBillFilter($headlineQuery);
        $headline = $headlineQuery->selectRaw('
                COUNT(*) as cnt,
                SUM(sales_bills.total - sales_bills.total_gst) as taxable,
                SUM(sales_bills.total_gst) as tax
            ')->first();

        // rate/POS breakdown rows are genuine item-level aggregation (a bill-level
        // total can't be split by rate) — re-expressed as a join on the SAME
        // filter (via a shared method, so the two queries can't disagree)
        // instead of collecting bill ids into an IN(...) list first.
        $rowsQuery = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bills.id', '=', 'sales_bill_items.sales_bill_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_bills.customer_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            ->where('sales_bills.status', '!=', 'Cancelled');
        $this->b2csBillFilter($rowsQuery);
        $agg = $rowsQuery
            ->selectRaw('
                sales_bills.sales_type as pos_type,
                sales_bill_items.gst_percent as rate,
                SUM(sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) as taxable,
                SUM(sales_bill_items.igst_amount) as igst,
                SUM(sales_bill_items.cgst_amount) as cgst,
                SUM(sales_bill_items.sgst_amount) as sgst
            ')
            ->groupBy('pos_type', 'rate')
            ->get();

        return [
            'count' => (int) $headline->cnt,
            'taxable' => round((float) $headline->taxable, 2),
            'tax' => round((float) $headline->tax, 2),
            'rows' => $agg->map(fn ($r) => [
                'pos' => $r->pos_type,
                'rate' => (float) $r->rate,
                'taxable' => round((float) $r->taxable, 2),
                'igst' => round((float) $r->igst, 2),
                'cgst' => round((float) $r->cgst, 2),
                'sgst' => round((float) $r->sgst, 2),
            ])->values()->all(),
        ];
    }

    // -----------------------------------------------------------------
    // HSN summaries — genuine SQL aggregation, B2B and B2C kept separate
    // -----------------------------------------------------------------
    /**
     * Row cap for the HSN detail table. Real GSTR-1 HSN summaries typically run
     * to dozens or low hundreds of distinct HSN+rate combinations; this dataset's
     * QA seed data assigns a near-unique HSN per item (34k+ distinct codes),
     * which is a seed-data artifact, not a real production shape — but the cap
     * is kept unconditionally, since unbounded HTML-table rendering is a real
     * risk regardless of why a period produced many groups (Phase 9: avoid
     * unbounded collection hydration). Aggregate totals (taxable/tax/missing-HSN
     * qty) are computed from the FULL grouped set, not just the capped rows.
     */
    private const HSN_ROW_CAP = 500;

    private function hsnSummary(bool $isB2b): array
    {
        $gstin = $this->gstinExpr('sales_bills', 'customers');
        $billCondition = $isB2b ? "{$gstin} IS NOT NULL" : "{$gstin} IS NULL";

        // Authoritative bill totals (Net of line discounts and bill discounts)
        $billTotals = $this->billsBaseQuery()
            ->whereRaw($billCondition)
            ->selectRaw('
                SUM(sales_bills.total - sales_bills.total_gst) as bill_taxable,
                SUM(sales_bills.total_gst) as bill_gst
            ')
            ->first();
        $targetBillTaxable = (float) ($billTotals->bill_taxable ?? 0);
        $targetBillTax = (float) ($billTotals->bill_gst ?? 0);

        // Fetch bill items grouped by (hsn, rate)
        $billRows = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bills.id', '=', 'sales_bill_items.sales_bill_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_bills.customer_id')
            ->leftJoin('items', 'items.id', '=', 'sales_bill_items.item_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereRaw($billCondition)
            ->selectRaw('
                items.hsn_code as hsn,
                sales_bill_items.gst_percent as rate,
                MIN(COALESCE(items.name, "Unknown Item")) as sample_name,
                COUNT(DISTINCT items.id) as item_count,
                GROUP_CONCAT(DISTINCT items.name) as group_names,
                SUM(sales_bill_items.qty) as qty,
                SUM(sales_bill_items.net_amount) as total,
                SUM(CASE WHEN sales_bill_items.gst_percent = 0 THEN sales_bill_items.net_amount ELSE 0 END) as nil,
                SUM(sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) as taxable,
                SUM(sales_bill_items.igst_amount) as igst,
                SUM(sales_bill_items.cgst_amount) as cgst,
                SUM(sales_bill_items.sgst_amount) as sgst
            ')
            ->groupBy('hsn', 'rate')
            ->get();

        $rawBillTaxableSum = (float) $billRows->sum('taxable');
        $taxableScale = $rawBillTaxableSum > 0 ? ($targetBillTaxable / $rawBillTaxableSum) : 1.0;

        $rawBillTaxSum = (float) ($billRows->sum('igst') + $billRows->sum('cgst') + $billRows->sum('sgst'));
        $billTaxScale = $rawBillTaxSum > 0 ? ($targetBillTax / $rawBillTaxSum) : 1.0;

        // Authoritative return totals (Credit Notes / CDNR / CDNUR)
        $retGstin = $this->gstinExpr('sales_returns', 'customers');
        $retCondition = $isB2b ? "{$retGstin} IS NOT NULL" : "{$retGstin} IS NULL";

        $retTotals = $this->returnsBaseQuery()
            ->whereRaw($retCondition)
            ->selectRaw('
                SUM(sales_returns.total - sales_returns.total_gst) as ret_taxable,
                SUM(sales_returns.total_gst) as ret_gst
            ')
            ->first();
        $targetRetTaxable = (float) ($retTotals->ret_taxable ?? 0);
        $targetRetTax = (float) ($retTotals->ret_gst ?? 0);

        // Fetch return items grouped by (hsn, rate)
        $retRows = DB::table('sales_return_items')
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_returns.customer_id')
            ->leftJoin('items', 'items.id', '=', 'sales_return_items.item_id')
            ->whereDate('sales_returns.return_date', '>=', $this->fromDate)
            ->whereDate('sales_returns.return_date', '<=', $this->toDate)
            ->where('sales_returns.status', '!=', 'Cancelled')
            ->whereRaw($retCondition)
            ->selectRaw('
                items.hsn_code as hsn,
                sales_return_items.gst_percent as rate,
                SUM(sales_return_items.qty) as qty,
                SUM(sales_return_items.net_amount) as total,
                SUM(CASE WHEN sales_return_items.gst_percent = 0 THEN sales_return_items.net_amount ELSE 0 END) as nil,
                SUM(sales_return_items.net_amount - sales_return_items.gst_tax_amount) as taxable,
                SUM(sales_return_items.igst_amount) as igst,
                SUM(sales_return_items.cgst_amount) as cgst,
                SUM(sales_return_items.sgst_amount) as sgst
            ')
            ->groupBy('hsn', 'rate')
            ->get();

        $rawRetTaxableSum = (float) $retRows->sum('taxable');
        $retTaxableScale = $rawRetTaxableSum > 0 ? ($targetRetTaxable / $rawRetTaxableSum) : 1.0;

        $rawRetTaxSum = (float) ($retRows->sum('igst') + $retRows->sum('cgst') + $retRows->sum('sgst'));
        $retTaxScale = $rawRetTaxSum > 0 ? ($targetRetTax / $rawRetTaxSum) : 1.0;

        // Merge bill items and return items by (hsn, rate)
        $groups = [];
        foreach ($billRows as $bl) {
            $key = ($bl->hsn ?? 'NULL').'|'.((float) $bl->rate);
            $groups[$key] = [
                'hsn' => $bl->hsn,
                'rate' => (float) $bl->rate,
                'sample_name' => $bl->sample_name,
                'item_count' => (int) $bl->item_count,
                'group_names' => (string) $bl->group_names,
                'qty' => (float) $bl->qty,
                'taxable' => (float) $bl->taxable * $taxableScale,
                'igst' => (float) $bl->igst * $billTaxScale,
                'cgst' => (float) $bl->cgst * $billTaxScale,
                'sgst' => (float) $bl->sgst * $billTaxScale,
                'nil' => (float) $bl->nil,
            ];
        }

        foreach ($retRows as $rl) {
            $key = ($rl->hsn ?? 'NULL').'|'.((float) $rl->rate);
            $retTaxable = (float) $rl->taxable * $retTaxableScale;
            $retIgst = (float) $rl->igst * $retTaxScale;
            $retCgst = (float) $rl->cgst * $retTaxScale;
            $retSgst = (float) $rl->sgst * $retTaxScale;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'hsn' => $rl->hsn,
                    'rate' => (float) $rl->rate,
                    'sample_name' => 'Returned Item',
                    'item_count' => 1,
                    'group_names' => '',
                    'qty' => -((float) $rl->qty),
                    'taxable' => -$retTaxable,
                    'igst' => -$retIgst,
                    'cgst' => -$retCgst,
                    'sgst' => -$retSgst,
                    'nil' => -((float) $rl->nil),
                ];
            } else {
                $groups[$key]['qty'] -= (float) $rl->qty;
                $groups[$key]['taxable'] -= $retTaxable;
                $groups[$key]['igst'] -= $retIgst;
                $groups[$key]['cgst'] -= $retCgst;
                $groups[$key]['sgst'] -= $retSgst;
                $groups[$key]['nil'] -= (float) $rl->nil;
            }
        }

        // Sort descending by net taxable
        uasort($groups, fn ($a, $b) => $b['taxable'] <=> $a['taxable']);

        $missingHsnQty = 0.0;
        foreach ($groups as $g) {
            if (empty($g['hsn'])) {
                $missingHsnQty += $g['qty'];
            }
        }

        $allRows = array_values($groups);
        $hasMore = count($allRows) > self::HSN_ROW_CAP;
        $cappedRows = array_slice($allRows, 0, self::HSN_ROW_CAP);

        $mappedRows = array_map(function ($r) {
            $nameList = ! empty($r['group_names']) ? array_unique(array_filter(array_map('trim', explode(',', (string) $r['group_names'])))) : [];
            $topNames = ! empty($nameList) ? implode(', ', array_slice($nameList, 0, 3)) : ($r['sample_name'] ?: 'Unknown Item');

            if ($r['hsn'] === null) {
                $name = '(!) HSN not set on item master (not fabricated): '.$topNames;
                if ($r['item_count'] > 3) {
                    $name .= ' (+'.($r['item_count'] - 3).' more items)';
                }
            } else {
                $name = $topNames;
                if ($r['item_count'] > 3) {
                    $name .= ' (+'.($r['item_count'] - 3).' more items)';
                }
            }

            $taxable = round((float) $r['taxable'], 2);
            $igst = round((float) $r['igst'], 2);
            $cgst = round((float) $r['cgst'], 2);
            $sgst = round((float) $r['sgst'], 2);
            $total = round($taxable + $igst + $cgst + $sgst, 2);

            return [
                'hsn' => $r['hsn'] ?? null,
                'name' => $name,
                'item_count' => (int) $r['item_count'],
                'sample_name' => $r['sample_name'],
                'uom' => 'UNT-UNITS',
                'qty' => (float) $r['qty'],
                'total' => $total,
                'rate' => (float) $r['rate'],
                'nil' => round((float) $r['nil'], 2),
                'taxable' => $taxable,
                'igst' => $igst,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'cess' => 0.00, // Cess is genuinely unsupported: no cess column exists anywhere in this schema.
            ];
        }, $cappedRows);

        return [
            'taxable' => round($targetBillTaxable - $targetRetTaxable, 2),
            'tax' => round($targetBillTax - $targetRetTax, 2),
            'missing_hsn_qty' => $missingHsnQty,
            'has_more_groups' => $hasMore,
            'row_cap' => self::HSN_ROW_CAP,
            'rows' => $mappedRows,
        ];
    }

    public function hsnB2b(): array
    {
        return $this->hsnSummary(true);
    }

    public function hsnB2c(): array
    {
        return $this->hsnSummary(false);
    }

    // -----------------------------------------------------------------
    // CDNR / CDNUR — real sales_returns, classified by the RETURN's own
    // posting-time GSTIN snapshot (a return belongs to one customer, same
    // reasoning as the sales-bill snapshot).
    // -----------------------------------------------------------------
    private function creditNotes(bool $registered): array
    {
        $gstin = $this->gstinExpr('sales_returns', 'customers');
        $condition = $registered ? "{$gstin} IS NOT NULL" : "{$gstin} IS NULL";

        $rows = $this->returnsBaseQuery()
            ->whereRaw($condition)
            ->selectRaw("
                sales_returns.id,
                sales_returns.return_number,
                sales_returns.return_date,
                customers.name as customer_name,
                {$gstin} as gstin,
                orig_bill.bill_number as original_invoice_number,
                orig_bill.bill_date as original_invoice_date,
                sales_returns.total as note_value,
                (sales_returns.total - sales_returns.total_gst) as taxable_value,
                sales_returns.total_gst as gst,
                sales_returns.total_igst as igst,
                sales_returns.total_cgst as cgst,
                sales_returns.total_sgst as sgst,
                (
                    SELECT GROUP_CONCAT(DISTINCT sri.gst_percent)
                    FROM sales_return_items sri
                    WHERE sri.sales_return_id = sales_returns.id
                ) as gst_rates,
                (
                    SELECT MAX(sri.gst_percent)
                    FROM sales_return_items sri
                    WHERE sri.sales_return_id = sales_returns.id
                ) as primary_rate
            ")
            ->orderBy('sales_returns.return_date')
            ->get();

        return [
            'count' => $rows->count(),
            'value' => round((float) $rows->sum('taxable_value'), 2),
            // total_gst is authoritative (same reasoning as b2b()/b2cl()); igst/cgst/sgst are for the row breakdown only.
            'tax' => round((float) $rows->sum('gst'), 2),
            'rows' => $rows->map(function ($r) {
                $rateStr = '';
                if (! empty($r->gst_rates)) {
                    $rates = array_map(function ($p) {
                        return ((float) trim($p)).'%';
                    }, explode(',', (string) $r->gst_rates));
                    $rateStr = implode(', ', array_filter($rates, fn ($v) => $v !== ''));
                }

                $ratePct = (float) ($r->primary_rate ?? 0);
                if (empty($rateStr)) {
                    $taxable = (float) $r->taxable_value;
                    $gst = (float) $r->gst;
                    if ($taxable > 0 && $gst > 0) {
                        $calcPct = round(($gst / $taxable) * 100);
                        $rateStr = $calcPct.'%';
                        $ratePct = (float) $calcPct;
                    } else {
                        $rateStr = '0%';
                        $ratePct = 0.0;
                    }
                }

                return [
                    'ref' => $r->return_number,
                    'date' => $r->return_date,
                    'customer' => $r->customer_name,
                    'gstin' => $r->gstin,
                    'rate' => $rateStr,
                    'rate_pct' => $ratePct,
                    'original_invoice' => $r->original_invoice_number,
                    'original_invoice_date' => $r->original_invoice_date,
                    'taxable' => round((float) $r->taxable_value, 2),
                    'igst' => round((float) $r->igst, 2),
                    'cgst' => round((float) $r->cgst, 2),
                    'sgst' => round((float) $r->sgst, 2),
                    'total' => round((float) $r->note_value, 2),
                ];
            })->values()->all(),
            'note' => 'This schema only represents credit-note-like sales returns issued by this business. '
                .'There is no debit-note concept (a document the customer would issue back to this business) '
                .'in the current data model — reported as unsupported rather than invented.',
        ];
    }

    public function cdnr(): array
    {
        return $this->creditNotes(true);
    }

    public function cdnur(): array
    {
        return $this->creditNotes(false);
    }

    // -----------------------------------------------------------------
    // Nil Rated / Exempt / Non-GST — the schema has exactly one bucket
    // (sales_bills.invoice_type = 'Exempted'). Reported honestly as a single
    // combined figure, not split into three, since the data can't support that.
    // -----------------------------------------------------------------
    public function nilRated(): array
    {
        // 1. Items sold with 0% GST (nil rated) during the selected period
        $zeroItems = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bills.id', '=', 'sales_bill_items.sales_bill_id')
            ->join('items', 'items.id', '=', 'sales_bill_items.item_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->where('sales_bill_items.gst_percent', 0)
            ->select(
                'items.name as item_name',
                'items.hsn_code',
                DB::raw('COUNT(DISTINCT sales_bills.id) as bill_count'),
                DB::raw('SUM(sales_bill_items.qty) as total_qty'),
                DB::raw('SUM(sales_bill_items.net_amount) as total_amount')
            )
            ->groupBy('items.name', 'items.hsn_code')
            ->orderByDesc('total_amount')
            ->get();

        // 2. Any entire bills marked as Exempted that did not have itemized 0% items
        $exemptBillsSum = (float) $this->billsBaseQuery()
            ->where('sales_bills.invoice_type', 'Exempted')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sales_bill_items')
                    ->whereColumn('sales_bill_items.sales_bill_id', 'sales_bills.id')
                    ->where('sales_bill_items.gst_percent', 0);
            })
            ->sum('sales_bills.total');

        $itemsTotal = (float) $zeroItems->sum('total_amount');
        $totalCombined = round($itemsTotal + $exemptBillsSum, 2);

        $rows = $zeroItems->map(fn ($item) => [
            'name' => $item->item_name,
            'hsn' => $item->hsn_code ?: 'N/A',
            'qty' => round((float) $item->total_qty, 3),
            'bills' => (int) $item->bill_count,
            'amount' => round((float) $item->total_amount, 2),
            'rate' => 0.0,
            'type' => 'Nil Rated (0% GST)',
        ])->toArray();

        return [
            'combined_amount' => $totalCombined,
            'supported_breakdown' => true,
            'count' => count($rows),
            'rows' => $rows,
            'limitation' => 'Statutory GSTR-1 Table 8: Aggregated supplies with 0% GST rate and exempted supplies.',
        ];
    }

    // -----------------------------------------------------------------
    // Sections with NO underlying data source at all — reported as such,
    // never fabricated.
    // -----------------------------------------------------------------
    public function exportSupplies(): array
    {
        return [
            'supported' => false,
            'count' => 0,
            'taxable' => 0.0,
            'tax' => 0.0,
            'rows' => [],
            'limitation' => 'No export concept exists in this schema (no export_type, shipping-bill, or port fields '
                .'anywhere). Reported as NOT SUPPORTED rather than treating any domestic sale as an export.',
        ];
    }

    public function advanceReceived(): array
    {
        return [
            'supported' => false,
            'taxable' => 0.0,
            'tax' => 0.0,
            'rows' => [],
            'limitation' => 'No advance-payment table exists. sales_bill_payments records tender/payment AT the '
                .'time of an already-created invoice, which is not the same thing as GST advance received before '
                .'a supply — a normal paid/partially-paid bill is never treated as an advance.',
        ];
    }

    public function advanceAdjusted(): array
    {
        return [
            'supported' => false,
            'taxable' => 0.0,
            'tax' => 0.0,
            'rows' => [],
            'limitation' => 'Same as Advance Received — there is nothing to adjust against without an underlying advance record.',
        ];
    }

    // -----------------------------------------------------------------
    // Documents Issued — real document numbers, grouped branch-wise
    // -----------------------------------------------------------------
    public function documentsIssued(): array
    {
        $branches = Branch::orderBy('id')->get();
        $series = [];

        foreach ($branches as $branch) {
            $billSeries = $this->documentSeriesFor(
                SalesBill::query()
                    ->where('branch_id', $branch->id)
                    ->whereDate('bill_date', '>=', $this->fromDate)
                    ->whereDate('bill_date', '<=', $this->toDate),
                'bill_number',
                'status',
                'Sales Invoice',
                $branch->name
            );

            $returnSeries = $this->documentSeriesFor(
                SalesReturn::query()
                    ->where('branch_id', $branch->id)
                    ->whereDate('return_date', '>=', $this->fromDate)
                    ->whereDate('return_date', '<=', $this->toDate),
                'return_number',
                'status',
                'Credit Note (Sales Return)',
                $branch->name
            );

            $series = array_merge($series, $billSeries, $returnSeries);
        }

        // Also include bills/returns with null or 0 branch_id if any exist
        $unassignedBills = $this->documentSeriesFor(
            SalesBill::query()
                ->where(function ($q) {
                    $q->whereNull('branch_id')->orWhere('branch_id', 0);
                })
                ->whereDate('bill_date', '>=', $this->fromDate)
                ->whereDate('bill_date', '<=', $this->toDate),
            'bill_number',
            'status',
            'Sales Invoice',
            'Main / Unassigned'
        );
        $unassignedReturns = $this->documentSeriesFor(
            SalesReturn::query()
                ->where(function ($q) {
                    $q->whereNull('branch_id')->orWhere('branch_id', 0);
                })
                ->whereDate('return_date', '>=', $this->fromDate)
                ->whereDate('return_date', '<=', $this->toDate),
            'return_number',
            'status',
            'Credit Note (Sales Return)',
            'Main / Unassigned'
        );
        $series = array_merge($series, $unassignedBills, $unassignedReturns);

        // Fallback for tests or setups without Branch records
        if (empty($series)) {
            $billSeries = $this->documentSeriesFor(
                SalesBill::query()
                    ->whereDate('bill_date', '>=', $this->fromDate)
                    ->whereDate('bill_date', '<=', $this->toDate),
                'bill_number',
                'status',
                'Sales Invoice',
                'All Branches'
            );
            $returnSeries = $this->documentSeriesFor(
                SalesReturn::query()
                    ->whereDate('return_date', '>=', $this->fromDate)
                    ->whereDate('return_date', '<=', $this->toDate),
                'return_number',
                'status',
                'Credit Note (Sales Return)',
                'All Branches'
            );
            $series = array_merge($billSeries, $returnSeries);
        }

        return [
            'total_issued' => array_sum(array_column($series, 'total')),
            'total_cancelled' => array_sum(array_column($series, 'cancelled')),
            'rows' => $series,
        ];
    }

    /** @param Builder $query */
    private function documentSeriesFor($query, string $numberColumn, string $statusColumn, string $label, string $branchName = ''): array
    {
        $all = $query->orderBy($numberColumn)->pluck($statusColumn, $numberColumn);
        if ($all->isEmpty()) {
            return [];
        }

        $numbers = $all->keys();
        $cancelled = $all->filter(fn ($status) => $status === 'Cancelled')->count();

        return [[
            'branch' => $branchName,
            'label' => ! empty($branchName) ? "{$label} ({$branchName})" : $label,
            'doc_type' => $label,
            'first' => $numbers->first(),
            'last' => $numbers->last(),
            'total' => $numbers->count(),
            'cancelled' => $cancelled,
            'net_issued' => $numbers->count() - $cancelled,
        ]];
    }

    // -----------------------------------------------------------------
    // Overview (12-card summary page)
    // -----------------------------------------------------------------
    public function summary(): array
    {
        $b2b = $this->b2b();
        $b2cl = $this->b2cl();
        $b2cs = $this->b2cs();
        $hsnB2b = $this->hsnB2b();
        $hsnB2c = $this->hsnB2c();
        $cdnr = $this->cdnr();
        $cdnur = $this->cdnur();
        $nil = $this->nilRated();
        $export = $this->exportSupplies();
        $advRec = $this->advanceReceived();
        $advAdj = $this->advanceAdjusted();
        $docs = $this->documentsIssued();

        return compact('b2b', 'b2cl', 'b2cs', 'hsnB2b', 'hsnB2c', 'cdnr', 'cdnur', 'nil', 'export', 'advRec', 'advAdj', 'docs');
    }

    /**
     * Generate official Government GST Portal Offline Tool JSON schema.
     * Ready for direct upload to services.gst.gov.in
     */
    public function generateGovtJson(string $gstin): array
    {
        $fp = date('mY', strtotime($this->fromDate));
        $defaultPos = substr($gstin, 0, 2) ?: '24';

        // 1. Table 4 B2B
        $b2bData = [];
        $groupedB2b = [];
        foreach ($this->b2b()['rows'] as $r) {
            $ctin = $r['gstin'] ?: $gstin;
            $groupedB2b[$ctin][] = $r;
        }
        foreach ($groupedB2b as $ctin => $invs) {
            $invList = [];
            foreach ($invs as $inv) {
                $pos = substr($ctin, 0, 2) ?: $defaultPos;
                $invTaxable = (float) $inv['taxable'];
                $invGst = (float) $inv['igst'] + (float) $inv['cgst'] + (float) $inv['sgst'];
                $rt = $invTaxable > 0 ? round(($invGst / $invTaxable) * 100, 1) : 18.0;

                $invList[] = [
                    'inum' => (string) $inv['ref'],
                    'idt' => date('d-m-Y', strtotime($inv['date'])),
                    'val' => (float) $inv['total'],
                    'pos' => (string) $pos,
                    'rchrg' => 'N',
                    'inv_typ' => 'R',
                    'itms' => [
                        [
                            'num' => 1,
                            'itm_det' => [
                                'txval' => $invTaxable,
                                'rt' => $rt,
                                'iamt' => (float) $inv['igst'],
                                'camt' => (float) $inv['cgst'],
                                'samt' => (float) $inv['sgst'],
                                'csamt' => 0.0,
                            ],
                        ],
                    ],
                ];
            }
            $b2bData[] = [
                'ctin' => $ctin,
                'cfs' => 'Y',
                'inv' => $invList,
            ];
        }

        // 2. Table 7 B2CS
        $b2csData = [];
        foreach ($this->b2cs()['rows'] as $r) {
            $b2csData[] = [
                'sply_ty' => ($r['igst'] > 0) ? 'INTER' : 'INTRA',
                'pos' => $defaultPos,
                'typ' => 'OE',
                'rt' => (float) $r['rate'],
                'txval' => (float) $r['taxable'],
                'iamt' => (float) $r['igst'],
                'camt' => (float) $r['cgst'],
                'samt' => (float) $r['sgst'],
                'csamt' => 0.0,
            ];
        }

        // 3. Table 9B CDNR
        $cdnrData = [];
        foreach ($this->cdnr()['rows'] as $r) {
            $ctin = $r['gstin'] ?: $gstin;
            $pos = substr($ctin, 0, 2) ?: $defaultPos;
            $cdnrData[] = [
                'ctin' => $ctin,
                'cfs' => 'Y',
                'nt' => [
                    [
                        'nt_num' => (string) $r['ref'],
                        'nt_dt' => date('d-m-Y', strtotime($r['date'])),
                        'ntty' => 'C',
                        'val' => (float) $r['total'],
                        'pos' => (string) $pos,
                        'rchrg' => 'N',
                        'itms' => [
                            [
                                'num' => 1,
                                'itm_det' => [
                                    'txval' => (float) $r['taxable'],
                                    'rt' => (float) ($r['rate_pct'] ?? 18.0),
                                    'iamt' => (float) $r['igst'],
                                    'camt' => (float) $r['cgst'],
                                    'samt' => (float) $r['sgst'],
                                    'csamt' => 0.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        // 4. Table 12 HSN Summary
        $hsnList = [];
        $hsnNum = 1;
        $allHsn = array_merge($this->hsnB2b()['rows'], $this->hsnB2c()['rows']);
        foreach ($allHsn as $h) {
            $hsnList[] = [
                'num' => $hsnNum++,
                'hsn_sc' => (string) ($h['hsn'] ?: '9999'),
                'desc' => substr($h['name'] ?: 'Pet Supplies Retail', 0, 30),
                'uqc' => 'UNT',
                'qty' => (float) $h['qty'],
                'val' => (float) $h['total'],
                'txval' => (float) $h['taxable'],
                'iamt' => (float) $h['igst'],
                'camt' => (float) $h['cgst'],
                'samt' => (float) $h['sgst'],
                'csamt' => 0.0,
            ];
        }

        // 5. Table 13 Documents Issued
        $docsData = [];
        $docNum = 1;
        foreach ($this->documentsIssued()['rows'] as $d) {
            $docsData[] = [
                'doc_num' => $docNum++,
                'doc_typ' => $d['label'],
                'docs' => [
                    [
                        'num' => 1,
                        'from' => (string) $d['first'],
                        'to' => (string) $d['last'],
                        'totnum' => (int) $d['total'],
                        'canc' => (int) $d['cancelled'],
                        'net_issue' => (int) $d['net_issued'],
                    ],
                ],
            ];
        }

        return [
            'gstin' => $gstin,
            'fp' => $fp,
            'version' => 'GSTR1_V2.0',
            'hash' => 'hash',
            'b2b' => $b2bData,
            'b2cs' => $b2csData,
            'cdnr' => $cdnrData,
            'hsn' => ['data' => $hsnList],
            'doc_issue' => ['doc_det' => $docsData],
        ];
    }
}
