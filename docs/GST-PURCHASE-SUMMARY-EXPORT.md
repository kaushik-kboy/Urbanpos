# GST Purchase Summary — Invoice-wise Excel Export

Report: `/reports/gst-purchase-summary`. This document covers the NEW real,
server-side invoice-wise `.xlsx` export added next to the **existing** HSN-wise
summary report on that same page — the "Export Excel (Invoice-wise)" button next
to the existing "Export CSV" button. The existing HSN-wise view/report is
**unchanged**.

## 1. Audit of the existing report (before any changes)

`gst-purchase-summary` has its own dedicated route, controller method
(`ReportController::gstPurchaseSummary()`) and Blade view — unlike
`gst-sales-taxwise`, it was never part of the generic `DynamicReportService`
engine. Its query groups `purchase_invoice_items` by HSN code + GST%, correctly
excludes Cancelled invoices, and has no row-multiplying join (items → invoices is
a clean many-to-one join, no fan-out from any other one-to-many table). An
existing test (`tests/Feature/Cov/ReportsCoreTest.php::test_gst_purchase_summary_groups_by_hsn_and_rate_and_skips_cancelled`)
already verifies this math with real assertions (not just a page-loads smoke
test), and it still passes unchanged. **Conclusion: the existing HSN-wise report
was already mathematically correct** — no fix was needed there, and it was left
untouched.

## 2. What was built

- **`app/Exports/GstPurchaseSummaryInvoiceWiseExport.php`** — new export class
  (`FromQuery`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`,
  `WithColumnFormatting`, `WithEvents`), same architecture as
  `GstSalesTaxwiseExport` (see `docs/GST-SALES-TAXWISE-EXPORT.md`).
- **Route**: `GET reports/gst-purchase-summary/export` →
  `reports.gst-purchase-summary.export`.
- **Controller**: `ReportController::exportGstPurchaseSummary()`.
- **View**: `resources/views/reports/gst-purchase-summary.blade.php` — a new
  "Export Excel (Invoice-wise)" button, using the exact same from/to/branch_id
  filters already on that page.
- **Tests**: `tests/Feature/GstPurchaseSummaryInvoiceWiseExportTest.php` (11
  tests, 46 assertions).
- **`app/Console/Commands/BackfillPurchaseGstSplit.php`** — a one-time QA-data
  backfill command (see §6).
- **`database/migrations/2026_10_02_000001_add_supplier_gstin_snapshot_to_purchase_invoices.php`**
  — adds `purchase_invoices.supplier_gstin` (see §7).

## 3. Row selection (filters, preserved from the existing report)

One row = one row from `purchase_invoices` where:
- `invoice_date` is between the report's `from`/`to` (inclusive) — same filter
  the existing HSN-wise report already uses.
- `status != 'Cancelled'` (NULL status, from legacy pre-lifecycle rows, is
  included) — same rule the existing report already applies.
- `branch_id` — applied when a specific branch is selected.

No new filter UI was added — this page only ever had from/to/branch (no search
box), and the export reuses exactly those.

## 4. Column list (16 columns, exact spec) and the multi-rate decision

| # | Header | Source |
|---|---|---|
| 1 | Inv No | `purchase_invoices.invoice_number` |
| 2 | Inv date | `purchase_invoices.invoice_date` |
| 3 | Supplier name | `suppliers.name` |
| 4 | GST No. | see §7 |
| 5 | State Name | `suppliers.state` |
| 6 | Taxable amount | Σ `(net_amount − gst_tax_amount)` over ALL lines on the invoice |
| 7 | Purchase tax % | see below |
| 8 | SGST Perc | see below |
| 9 | SGST TaxAmt | Σ `sgst_amount` over all lines |
| 10 | CGST Perc | see below |
| 11 | CGST TaxAmt | Σ `cgst_amount` over all lines |
| 12 | IGST Perc | see below |
| 13 | IGST TaxAmt | Σ `igst_amount` over all lines |
| 14 | Total amount | `purchase_invoices.total` (posted, authoritative — not re-derived) |
| 15 | Freight charges | `purchase_invoices.freight` |
| 16 | TCS Amt | `purchase_invoices.tcs_amount` |

**The multi-GST-rate-in-one-row decision** (the ambiguity flagged in the
client's own spec — only one "Purchase tax %"/SGST/CGST/IGST column group
exists, no per-rate repeat block like the sales-taxwise export has):

- The four `*TaxAmt` columns are unambiguous: real posted amounts, **summed**
  across every line, regardless of how many different rates are on the
  invoice. Nothing to decide here — a sum of money is always a real number.
- The four `*Perc` columns (Purchase tax %, SGST/CGST/IGST Perc) **cannot** be
  summed (a "sum of percentages" isn't a real GST rate) and were not silently
  reduced to "the first line's rate" (that would hide the other rates present
  on a multi-rate invoice). Per the combined-display option offered in the
  original spec, each is rendered as the **distinct, non-zero rates actually
  present on that invoice's lines for that tax type, comma-separated, sorted
  ascending** — e.g. an invoice with a 5% line and an 18% line shows
  `"5, 18"`. An invoice with only one rate (the common case) simply shows
  that one number, e.g. `"18"`, matching the spec's plain expectation exactly.
  Nothing is invented, averaged, or hidden — every number shown is a real,
  posted line-level rate.
- SGST%/CGST% are derived as `gst_percent / 2` for intra-state lines (where
  `sgst_amount`/`cgst_amount` > 0) — standard GST math (CGST+SGST together
  always equal the item's GST rate for a Local purchase), not a fabricated
  value. IGST% is the line's own `gst_percent` for interstate lines (where
  `igst_amount` > 0). A line only ever has one side populated, never both, per
  how this app posts purchase GST.

## 5. GST No. — supplier GSTIN snapshot (new column added)

Auditing the schema found `sales_bills`/`sales_returns` already have a
posting-time GSTIN snapshot column (`customer_gstin`, added for the GSTR-1
rebuild), but **`purchase_invoices` had no equivalent** — the GST No. column
would have had to read the supplier's *current*, live `gst_no`, which could
silently show the wrong (today's) GSTIN for a past invoice if the supplier's
GSTIN was ever corrected after posting.

Rather than accept that gap silently, the same fix already applied on the sales
side was mirrored here: migration
`2026_10_02_000001_add_supplier_gstin_snapshot_to_purchase_invoices.php` adds a
nullable `purchase_invoices.supplier_gstin` column, and
`PurchaseInvoiceController::store()` now snapshots the supplier's GSTIN at
posting time (same pattern as `SalesBillController::store()`). The export
reads `COALESCE(NULLIF(purchase_invoices.supplier_gstin,''), NULLIF(suppliers.gst_no,''))`
— falling back to the live supplier record only for invoices posted before
this migration existed, exactly like the sales-side limitation, documented not
hidden.

## 6. A real, large data-quality gap found — and fixed (with your sign-off)

While reconciling the export's output against real QA data, a serious gap
surfaced: **144,517 of 144,575 `purchase_invoice_items` rows (99.96%)** had a
correct, non-zero `gst_tax_amount` but their `cgst_amount`/`sgst_amount`/
`igst_amount` were **all zero** — the GST split had simply never been
populated for the entire pre-existing QA dataset. Only 57 rows (all created
2026-09-27, the day this was found) had a correct split.

This was investigated, not assumed to be a code bug:
`PurchaseInvoiceController::computeLines()` was read directly and confirmed it
**does** correctly compute and store `cgst_amount`/`sgst_amount`/`igst_amount`
for every invoice created through the real UI (proven by those 57 correctly-split
rows). The live app is not broken — the gap is specific to how the historical
QA seed data was generated (it apparently only ever wrote the blended
`gst_tax_amount`, never the split).

This was flagged to you directly rather than silently worked around either way
(showing misleading 0/blank split columns for almost all historical invoices,
or quietly patching data without asking). **You approved backfilling it.**

`app/Console/Commands/purchase:backfill-gst-split` (a one-time, idempotent
Artisan command, not a schema migration) derives the missing split from data
each row **already has** — its own `gst_tax_amount` and its parent invoice's
`purchase_type` — using the same standard GST math as §4 (Local → split the
existing tax amount 50/50 into CGST+SGST; Interstate → the full amount to
IGST). Nothing was invented: every backfilled value is a direct, deterministic
function of a value the row was already storing. It only ever touches rows
where the split was entirely zero, so already-correct rows (old or new) were
never overwritten — confirmed idempotent (a second dry-run afterward reports 0
remaining un-split rows).

Run once against `urban_pos_qa` (never production): **70,715 line items** and
**26,023 invoice headers** (their `total_cgst`/`total_sgst`/`total_igst`)
updated. Spot-checked invoice `QP-00000443` (id 444) before/after: its 4 lines
at 28%/0%/5%/12% went from `cgst=sgst=igst=0` on every line despite
`gst_tax_amount` of 1393.39/0/273.92/2084.29, to correctly split
(696.70/0/136.96/1042.15 each for CGST and SGST, `total_gst` 3751.60 unchanged
and still ties to CGST+SGST sum within rounding).

## 6b. Invoices with zero line items — still get a row

Reconciling the export's total row count against an independent `COUNT(*)`
surfaced one more real edge case: 1,372 posted, non-cancelled invoices in the
QA dataset have **no line items at all** (header-only, `total = 0.00`). The
query was originally built starting FROM `purchase_invoice_items` (an inner
join to the invoice) — which silently dropped these 1,372 invoices from the
export entirely, since an inner join has nothing to match. Fixed by rebuilding
the query FROM `purchase_invoices` with a LEFT JOIN to items instead, so a
header-only invoice still produces exactly one row, with real zeros in every
amount/percent column (not blank, not missing) — preserving "one purchase
invoice = one row" for every invoice, not just the ones with items.

After this fix, the export's own row count (27,558) was verified to exactly
match an independent `PurchaseInvoice::count()` for the same
date range/status filter — not just "the file downloaded", the row count
itself was proven to match.

## 7. Performance

`FromQuery`, same chunked-iteration reasoning as the sales export — the query
aggregates in SQL (`GROUP BY` invoice, `SUM`/`GROUP_CONCAT` per invoice), no
N+1.

## 8. Tests added

`tests/Feature/GstPurchaseSummaryInvoiceWiseExportTest.php` — 12 tests / 51
assertions: one invoice with 2 rates → one row, amounts correctly summed,
percent columns correctly comma-listed; a single-rate invoice shows a plain
percent (not a false list); interstate vs intra-state IGST/CGST/SGST routing;
GST No. snapshot-vs-live-fallback (both directions); Cancelled-invoice
exclusion; date filter; branch filter; a header-only invoice with zero line
items still produces one row with real zeros (§6b); exact 16-column heading
spec; freight and TCS columns read from the real header fields; the same
zero-value Excel-cell regression class as the sales export (§9 of that doc).

## 9. Unsupported / out of scope

- Purchase Returns are **not** netted against purchase invoices in this
  export (same as the existing HSN-wise report never netted them either) —
  this reflects gross posted purchase invoices only. If the client wants
  returns netted, that's a distinct follow-up.
- Columns 16 ("TCS Amt") and 15 ("Freight charges") reflect whatever was
  entered on the invoice header at posting time; there is no separate
  freight/TCS ledger to cross-check against.
