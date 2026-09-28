<?php

namespace App\Services\GST;

use App\Models\SalesBill;
use App\Models\SalesReturn;
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
    ) {
    }

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
            ->selectRaw("
                sales_bills.bill_number as invoice_number,
                sales_bills.bill_date as invoice_date,
                sales_bills.sales_type as pos_type,
                sales_bills.total as invoice_value,
                (sales_bills.total - sales_bills.total_gst) as taxable_value,
                sales_bills.total_igst as igst
            ")
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
        $gstin = $this->gstinExpr();
        $condition = $isB2b ? "{$gstin} IS NOT NULL" : "{$gstin} IS NULL";

        $base = fn () => DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bills.id', '=', 'sales_bill_items.sales_bill_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_bills.customer_id')
            ->leftJoin('items', 'items.id', '=', 'sales_bill_items.item_id')
            ->whereDate('sales_bills.bill_date', '>=', $this->fromDate)
            ->whereDate('sales_bills.bill_date', '<=', $this->toDate)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereRaw($condition);

        // Flat aggregate over every matching line — these totals don't need the
        // HSN/rate grouping at all, so they're correct regardless of the row cap below.
        $aggregates = $base()->selectRaw('
                SUM(sales_bill_items.qty * (items.hsn_code IS NULL)) as missing_hsn_qty,
                SUM(sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) as taxable,
                SUM(sales_bill_items.igst_amount + sales_bill_items.cgst_amount + sales_bill_items.sgst_amount) as tax
            ')->first();

        // Fetch one row beyond the cap so we know whether more groups exist
        // without a separate COUNT query (which, measured, cost as much as the
        // main aggregate query itself — not worth doubling the cost of every
        // HSN page load just for an exact "of N" figure).
        $rows = $base()
            ->selectRaw("
                items.hsn_code as hsn,
                sales_bill_items.gst_percent as rate,
                SUM(sales_bill_items.qty) as qty,
                SUM(sales_bill_items.net_amount) as total,
                SUM(CASE WHEN sales_bill_items.gst_percent = 0 THEN sales_bill_items.net_amount ELSE 0 END) as nil,
                SUM(sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) as taxable,
                SUM(sales_bill_items.igst_amount) as igst,
                SUM(sales_bill_items.cgst_amount) as cgst,
                SUM(sales_bill_items.sgst_amount) as sgst
            ")
            ->groupBy('hsn', 'rate')
            ->orderByRaw('SUM(sales_bill_items.net_amount - sales_bill_items.gst_tax_amount) DESC')
            ->limit(self::HSN_ROW_CAP + 1)
            ->get();
        $hasMore = $rows->count() > self::HSN_ROW_CAP;
        $rows = $rows->take(self::HSN_ROW_CAP);

        return [
            'taxable' => round((float) $aggregates->taxable, 2),
            'tax' => round((float) $aggregates->tax, 2),
            'missing_hsn_qty' => (float) $aggregates->missing_hsn_qty,
            'has_more_groups' => $hasMore,
            'row_cap' => self::HSN_ROW_CAP,
            'rows' => $rows->map(fn ($r) => [
                'hsn' => $r->hsn ?? null,
                'name' => $r->hsn === null ? '(!) HSN not set on item master — not fabricated, see limitation note' : null,
                'uom' => 'UNT-UNITS',
                'qty' => (float) $r->qty,
                'total' => round((float) $r->total, 2),
                'rate' => (float) $r->rate,
                'nil' => round((float) $r->nil, 2),
                'taxable' => round((float) $r->taxable, 2),
                'igst' => round((float) $r->igst, 2),
                'cgst' => round((float) $r->cgst, 2),
                'sgst' => round((float) $r->sgst, 2),
                'cess' => 0.00, // Cess is genuinely unsupported: no cess column exists anywhere in this schema.
            ])->values()->all(),
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
                sales_returns.total_sgst as sgst
            ")
            ->orderBy('sales_returns.return_date')
            ->get();

        return [
            'count' => $rows->count(),
            'value' => round((float) $rows->sum('taxable_value'), 2),
            // total_gst is authoritative (same reasoning as b2b()/b2cl()); igst/cgst/sgst are for the row breakdown only.
            'tax' => round((float) $rows->sum('gst'), 2),
            'rows' => $rows->map(fn ($r) => [
                'ref' => $r->return_number,
                'date' => $r->return_date,
                'customer' => $r->customer_name,
                'gstin' => $r->gstin,
                'original_invoice' => $r->original_invoice_number,
                'original_invoice_date' => $r->original_invoice_date,
                'taxable' => round((float) $r->taxable_value, 2),
                'igst' => round((float) $r->igst, 2),
                'cgst' => round((float) $r->cgst, 2),
                'sgst' => round((float) $r->sgst, 2),
                'total' => round((float) $r->note_value, 2),
            ])->values()->all(),
            'note' => 'This schema only represents credit-note-like sales returns issued by this business. '
                . 'There is no debit-note concept (a document the customer would issue back to this business) '
                . 'in the current data model — reported as unsupported rather than invented.',
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
        $amount = round((float) $this->billsBaseQuery()
            ->where('sales_bills.invoice_type', 'Exempted')
            ->sum('sales_bills.total'), 2);

        return [
            'combined_amount' => $amount,
            'supported_breakdown' => false,
            'limitation' => 'sales_bills.invoice_type only has a single "Exempted" value — the schema cannot '
                . 'currently distinguish Nil-rated vs Exempt vs Non-GST supplies. This figure is the combined '
                . 'total for that one bucket, not a guess at any specific one of the three.',
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
                . 'anywhere). Reported as NOT SUPPORTED rather than treating any domestic sale as an export.',
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
                . 'time of an already-created invoice, which is not the same thing as GST advance received before '
                . 'a supply — a normal paid/partially-paid bill is never treated as an advance.',
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
    // Documents Issued — real document numbers, grouped by series prefix
    // -----------------------------------------------------------------
    public function documentsIssued(): array
    {
        $billSeries = $this->documentSeriesFor(
            SalesBill::query()
                ->whereDate('bill_date', '>=', $this->fromDate)
                ->whereDate('bill_date', '<=', $this->toDate),
            'bill_number',
            'status',
            'Sales Invoice'
        );

        $returnSeries = $this->documentSeriesFor(
            SalesReturn::query()
                ->whereDate('return_date', '>=', $this->fromDate)
                ->whereDate('return_date', '<=', $this->toDate),
            'return_number',
            'status',
            'Credit Note (Sales Return)'
        );

        $series = array_merge($billSeries, $returnSeries);

        return [
            'total_issued' => array_sum(array_column($series, 'total')),
            'total_cancelled' => array_sum(array_column($series, 'cancelled')),
            'rows' => $series,
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    private function documentSeriesFor($query, string $numberColumn, string $statusColumn, string $label): array
    {
        $all = $query->orderBy($numberColumn)->pluck($statusColumn, $numberColumn);
        if ($all->isEmpty()) {
            return [];
        }

        $numbers = $all->keys();
        $cancelled = $all->filter(fn ($status) => $status === 'Cancelled')->count();

        return [[
            'label' => $label,
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
}
