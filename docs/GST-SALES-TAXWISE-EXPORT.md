# GST Sales Taxwise — Excel Export

Report: `/reports/view/gst-sales-taxwise` (report slug `gst-sales-taxwise`, one of the
`isSalesReport()` slugs in `DynamicReportService`). This document covers the real,
server-side `.xlsx` export added for that report — the "Export Excel (GST Taxwise)"
button next to the existing generic "Export CSV" button.

## 1. Before this change

The `gst-sales-taxwise` slug had **no dedicated `case` at all** in
`DynamicReportService::isSalesReport()`. It silently fell through to the generic
`default:` branch, which renders a basic paginated `SalesBill` list (Bill Reference /
Date / Customer / Branch / Quantity / GST Amount / Total Revenue / Status) — no GST
rate breakup whatsoever. The page's "Export CSV" button is a client-side JS function
(`exportTableToCSV()`) that scrapes whatever HTML rows are currently rendered — only
the current 50-row page, only the generic columns above. Neither of these produced
anything close to the client's requested one-bill-one-row, rate-wise-breakup format.

## 2. What was built

- **`app/Exports/GstSalesTaxwiseExport.php`** — a real `maatwebsite/excel` export
  class (`FromQuery`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`,
  `WithColumnFormatting`, `WithEvents`). First real Excel export class in this
  codebase (the package was previously only used for CSV/XLSX *imports*, see
  `app/Http/Controllers/Concerns/Importable.php`).
- **Route**: `GET reports/gst-sales-taxwise/export` → `reports.gst-sales-taxwise.export`
  (`routes/web.php`, inside the existing `reports.` group).
- **Controller**: `ReportController::exportGstSalesTaxwise()` — reuses the report's
  existing `dateAndBranchFilter($request)` helper (same from/to/branch resolution as
  the on-screen report) plus `search`, and streams the download.
- **View**: `resources/views/reports/generic-report.blade.php` — a conditional
  "Export Excel (GST Taxwise)" button, shown only when `$module === 'gst-sales-taxwise'`,
  linking to the export route with the *same* filter values currently applied on
  screen (from/to/branch_id/search) so what you export matches what you're looking at.
- **Tests**: `tests/Feature/GstSalesTaxwiseExportTest.php` (13 tests, 53 assertions).

## 3. Scope decision — bills only, not returns

This export covers **posted Sales Bills only**. Sales Returns / credit notes are a
separate, existing report family (`sale-return-*` slugs) and are not folded into this
sheet. If the client wants returns reflected here too (net of returns, or as a
separate section), that is a distinct follow-up request — not something assumed or
silently added.

## 4. Row selection (filters, preserved from the existing report)

One row = one row from `sales_bills` where:
- `bill_date` is between the report's `from`/`to` (inclusive) — same filter the
  on-screen report already uses.
- `status = 'Posted'` — Draft bills aren't a finalized transaction yet, Cancelled
  bills aren't a taxable supply at all (same rule already established for this app's
  GSTR-1 rebuild).
- `branch_id` — applied when a specific branch is selected (defaults to "All
  Branches" server-side unless the request explicitly asks for one branch, exactly
  like the existing generic report filter).
- `search` — matches `bill_number` OR `customers.name` (the existing report's only
  free-text field), so exporting after a search only exports the filtered set.

No new filter UI was added — the export reuses the exact 4 filters the generic report
page already exposes (from/to/branch/search); there is no separate customer/status
filter dropdown in this report, so none was invented.

## 5. Column list (26 columns, exact order/headings as given by the client)

| # | Header | Source |
|---|---|---|
| 1 | Bill No | `sales_bills.bill_number` |
| 2 | Bill Date | `sales_bills.bill_date` |
| 3 | Customer Name | `customers.name` (or "Walk-in Customer" if null) |
| 4 | GST No. | see §6 |
| 5 | State Name | `customers.state` |
| 6 | taxable_0_amount | Σ `(net_amount − gst_tax_amount)` over items where `gst_percent = 0` |
| 7 | taxable_5_amount | same, `gst_percent = 5` |
| 8 | taxable_18_amount | same, `gst_percent = 18` |
| 9 | igst_5_amt | Σ `igst_amount` where `gst_percent = 5` |
| 10 | sgst_5_amt | Σ `sgst_amount` where `gst_percent = 5` |
| 11 | cgst_5_amt | Σ `cgst_amount` where `gst_percent = 5` |
| 12 | igst_18_amt | Σ `igst_amount` where `gst_percent = 18` |
| 13 | cgst_18_amt | Σ `cgst_amount` where `gst_percent = 18` |
| 14 | sgst_18_amt | Σ `sgst_amount` where `gst_percent = 18` |
| 15 | Total amount | `sales_bills.total` (posted, authoritative bill total — **not** re-derived from columns 6-14) |
| 16 | Inv Noble_0_amount | see §7 — same value as column 6 |
| 17-25 | (repeat of 7-15) | same values as columns 7-15 |
| 26 | Inv No | see §7 — same value as column 1 |

All amount columns are formatted `0.00`, stored as genuine numeric Excel cells (see
§8 for why this needed a specific fix). Bill Date is a real Excel date cell
(`DD-MMM-YYYY` format), not a text string.

## 6. GST No. column

Uses `sales_bills.customer_gstin` — the GSTIN **snapshotted at bill-posting time**
(added via migration `2026_10_01_000003_add_customer_gstin_snapshot_to_sales_documents`,
as part of this app's GSTR-1 rebuild), falling back to the *live* `customers.gst_no`
only when the snapshot is null (i.e. bills posted before that migration existed).
This is deliberate, not a shortcut: if a customer's GSTIN is edited after a bill is
posted, this column must keep showing what was true when the bill was issued, since
that's the legally-relevant value for a GST report. Bills posted before the snapshot
column existed don't have this history, so they fall back to the live value — this
edge case is called out here rather than hidden.

## 7. The "Inv Noble_0_amount" / "Inv No" mapping decision

The client's own 26-column list has two column headers that don't obviously match any
real field: column 16 "Inv Noble_0_amount" and column 26 "Inv No" (appearing again,
alone, at the very end).

Investigated rather than guessed at, by pattern-matching the rest of the list:
columns 17-25 mirror columns 7-15 **verbatim** (taxable_5/18, all six GST-rate
amounts, Total amount — same headers, same order, repeated once). That makes column
16 the "0%-slot" of that second, repeated block — i.e. it lines up exactly where a
second `taxable_0_amount` would sit. Read together with "Noble" not being a real word
that means anything in this domain, the most likely explanation is that "Inv No"
(from column 26, further down the same source list) got merged/mis-pasted into this
cell during whatever spreadsheet copy produced the client's spec, corrupting
"taxable_0_amount" into "Inv Noble_0_amount".

**Decision**: column 16 is mapped to the same real `taxable_0_amount` value as column
6 — completing the mirrored block exactly like columns 17-25 do — while the header
text itself is kept **exactly as the client wrote it** ("Inv Noble_0_amount"), not
silently renamed, per the explicit instruction not to guess/rename columns without
flagging it. This is flagged here, in the open, rather than fixed silently.

Column 26, "Inv No", appears once at the very end with nothing else new around it.
Read as a deliberate second copy of the bill/invoice number at the far right of a wide
26-column sheet — a common spreadsheet convention so a reader scrolled right doesn't
need to scroll all the way back to column 1 to know which bill a row belongs to.
Mapped to the same `bill_number` as column 1. There is no second document-number field
anywhere in the sales-bill schema, so this is not read as a distinct value.

**If either reading is wrong, this needs a corrected client-side column list** — the
code and this doc both flag the decision explicitly so it can be corrected quickly
rather than silently shipping a guess.

## 8. The 0/5/18-only rate gap (documented per user decision)

Real posted `sales_bill_items.gst_percent` values in this app's data are 0%, 5%,
12%, 18%, and 28% (confirmed via direct SQL — each of the 5 rates is roughly
equally represented in the QA dataset, tens of thousands of item lines each). The
client's own column spec only has breakup columns for 0/5/18%.

This was raised with the user directly rather than assumed either way (add
undocumented extra columns, or silently drop 12%/28% data). **User's explicit
decision: implement exactly the requested columns (0/5/18 only)** — do not add
12%/28% columns that weren't asked for.

Practical effect: for a bill that contains 12% or 28% items, the taxable_0/5/18 and
igst/cgst/sgst_5/18 columns will **not** sum to column 15 ("Total amount") — the
12%/28% taxable value and its tax are not lost (they're still inside the authoritative
`Total amount`, which is the posted bill total, never recalculated from the 0/5/18
buckets alone), they're just not broken out into their own columns in this sheet. If
the client later wants 12%/28% broken out too, that's a follow-up column-spec change.

## 9. A real bug found and fixed: zero values written as blank cells

While reconciling actual export output against independently-computed expected
values (not just checking the file downloaded), a genuine bug was found in the
`maatwebsite/excel` / PhpSpreadsheet cell-writing pipeline: a raw PHP `int(0)` or
`float(0.0)` value returned from `WithMapping::map()` is silently written as a
**blank cell**, not a numeric zero.

Confirmed in isolation (a minimal standalone `FromArray` export, outside this class
entirely): `[0, 0.0]` wrote as blank cells; `['0.00', 5.5]` (a numeric *string* and a
non-zero float) wrote correctly. `PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder`
was read directly and correctly classifies `0`/`0.0` as `TYPE_NUMERIC` in isolation —
so the bug sits somewhere in Laravel Excel's own row-processing layer above
PhpSpreadsheet's binder, not in PhpSpreadsheet itself.

**Fix**: every amount value is passed through `number_format($v, 2, '.', '')` (a
numeric *string* like `"0.00"`) before being returned from `map()`, instead of a raw
float. PhpSpreadsheet's value binder auto-detects numeric strings and still stores
them as genuine numeric cells (`getDataType() === 'n'`, `getValue() === 0.0`,
`getFormattedValue() === '0.00'`) — this sidesteps the bug while satisfying both
"must be a real numeric cell" and "zero must display as 0.00, not blank"
simultaneously. Regression-tested by
`test_zero_tax_buckets_are_exported_as_a_real_zero_not_a_blank_cell`.

## 10. Performance

`FromQuery` (not `FromCollection`) — Laravel Excel chunks through the query in
batches rather than hydrating every matching bill into memory up front, since this
app's real posted-bill volume is large enough that an unbounded collection would be
a real problem, not a theoretical one. The query itself does the SUM/CASE aggregation
in SQL (one query, `GROUP BY` on the bill's own identifying columns) — no N+1 (no
per-bill follow-up query for its items).

## 11. Tests added

`tests/Feature/GstSalesTaxwiseExportTest.php` — 13 tests / 53 assertions, covering:
- One bill with 3 different GST rates on its line items → exactly one row, every
  rate-wise bucket correct (the task's own required acceptance test).
- Multiple items at the *same* rate on one bill → correctly aggregated into one
  bucket, not one row per item.
- A bill with only 18% items → 18% columns populated, 0%/5% columns are real zeros
  (not blank — see §9).
- A bill with only 5% items → same, for the 5% bucket.
- Interstate bill → IGST populated, CGST/SGST are zero (not the reverse).
- GST No. reflects the bill-posting-time snapshot, not the customer's *current*
  GSTIN if it was changed after the bill was posted.
- Draft and Cancelled bills are excluded (status filter).
- Bills outside the from/to range are excluded (date filter).
- Branch filter excludes bills from other branches.
- Search filter matches by bill number OR customer name.
- Exact 26-column heading order and text (including the two preserved-verbatim
  headers from §7).
- Columns 16-25 exactly mirror columns 6-15's values; column 26 exactly repeats
  column 1 — verified index-by-index against `map()`'s own output.
- The zero-value regression test from §9.

## 12. Manual reconciliation (not just "the file downloaded")

A real QA bill with mixed 0%/5%/18% line items (`QB-00100543`, bill id 100544, date
2025-10-29, customer "QA Cust 0029043 Desai", no GSTIN on file, state Gujarat) was
found via direct SQL. Expected values were independently computed by hand from the
same raw item rows (taxable_0 = 681.25, taxable_5 = 1476.69, taxable_18 = 194.49,
igst_5 = igst_18 = 0.00, cgst_5 = sgst_5 = 36.92, cgst_18 = sgst_18 = 17.50, Total =
2461.27). The real `.xlsx` was then downloaded via an authenticated request and
parsed directly with PhpSpreadsheet — every value matched exactly, and the row count
for that date (250 bills) matched an independent `COUNT(*)` exactly.

## 13. Unsupported / out of scope

- Sales Returns are not included in this sheet (§3).
- 12% and 28% GST rates are not broken out into their own columns, by explicit user
  decision (§8) — their value is still inside `Total amount`.
- Columns 16 and 26 are a best-effort interpretation of an apparently-corrupted
  client column list, not a confirmed requirement (§7) — flag if wrong.
