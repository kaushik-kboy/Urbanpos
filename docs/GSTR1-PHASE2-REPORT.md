# GSTR-1 — Phase 2 Rebuild Report

QA/test database only. Nothing touched on Hostinger/production. Full audit: `docs/GSTR1-AUDIT.md`.

## Section-by-section result

| Section | Status | Real Data Source | Formula/Classification | Test Result | Limitation |
|---|---|---|---|---|---|
| B2B Outward | **FIXED** | `sales_bills` (joined `customers`) | GSTIN present (posting-time snapshot, live fallback) → `total_gst`/`total_cgst`/`total_sgst`/`total_igst` used directly | `test_b2b_classification_uses_gstin_presence` — PASS | None |
| B2CL | **FIXED** | `sales_bills` | No GSTIN AND `total >= ₹2.5L` AND (Interstate OR igst>0) — real statutory rule, threshold unchanged | `test_b2cl_threshold_and_b2cs_are_mutually_exclusive` — PASS | None |
| B2CS | **FIXED** | `sales_bills` (+ `sales_bill_items` for the rate/POS breakdown rows) | Everything else, no GSTIN | Same test — PASS | None |
| HSN B2B | **FIXED** | `sales_bill_items` joined `items.hsn_code` | Real `GROUP BY hsn, rate` | `test_hsn_b2b_and_b2c_are_aggregated_separately_and_missing_hsn_is_not_fabricated` — PASS | Missing-HSN items grouped under an explicit "(!) HSN not set" label, never a fabricated code |
| HSN B2C | **FIXED** | same | Same, B2C filter | same test — PASS | same |
| CDNR | **FIXED** | `sales_returns` (+ original `sales_bills` via `sales_bill_id`) | Return's own GSTIN snapshot present | `test_registered_and_unregistered_credit_notes_are_kept_separate` — PASS | Debit notes: not a concept this schema supports (documented, not invented) |
| CDNUR | **FIXED** | same | No GSTIN | same test — PASS | same |
| Nil Rated / Exempt / Non-GST | **PASS WITH LIMITATION** | `sales_bills.invoice_type='Exempted'` | Single combined bucket | Covered by no-fabrication test | Schema has only one bucket; cannot split nil/exempt/non-GST — reported as a documented limitation, not guessed |
| Exported Supplies | **NOT SUPPORTED** | — | — | `test_export_and_advance_sections_are_explicitly_not_supported_not_fabricated` — PASS | No export schema (no export_type/shipping-bill/port fields) anywhere |
| Advance Received | **NOT SUPPORTED** | — | — | same test — PASS | No advance-payment table; `sales_bill_payments` is tender-at-sale, not pre-invoice advance |
| Advance Adjusted | **NOT SUPPORTED** | — | — | same test — PASS | Nothing to adjust without an underlying advance record |
| Document Issued | **FIXED** | `sales_bills.bill_number` + `sales_returns.return_number`, grouped by series | First/last/total/cancelled per real series | `test_cancelled_bill_excluded_from_taxable_totals_but_counted_in_documents_issued` — PASS | None |

## Before vs after

1. **All fabricated fallback values removed.** Every hardcoded number the audit found (`609340.18`, `93056.06`, `7414726.65`, `1211809.47`, `607861.26`, `93420.62`, `7338700.00`, `1210534.94`, `2829.76`, `364.50`, `73252.50`, `3513`) is gone from the codebase. `test_no_data_in_period_returns_zero_never_a_hardcoded_fallback` asserts a genuinely empty period returns real zeros and explicitly checks none of those exact numbers can leak back in — a regression trip-wire, not just a one-time removal.
2. **All guessed 18/118 calculations removed.** `Gstr1ReportService` never derives a tax amount from an assumed rate; every "tax" figure comes from the bill/return's own stored `total_gst` (or, for the row-level rate/POS breakdown, from `sales_bill_items`' own stored `cgst_amount`/`sgst_amount`/`igst_amount`/`gst_percent`) — all values TaxEngine already computed correctly at posting time.
3. **Historical GSTIN handling**: new `customer_gstin` snapshot column on `sales_bills`/`sales_returns` (migration `2026_10_01_000003_add_customer_gstin_snapshot_to_sales_documents`, applied to QA DB only, rollback tested — see below), populated by `SalesBillController::store()`/`SalesReturnController::store()`. Falls back to the live `customers.gst_no` only for rows with no snapshot (pre-migration history) — a documented limitation, not a guess. Regression: `test_changing_customers_current_gstin_does_not_reclassify_an_old_posted_bill` (proves the fix) and `test_bill_with_no_gstin_snapshot_falls_back_to_live_customer_gstin` (proves the documented fallback path) — both PASS.
4. **B2B/B2C/B2CL/B2CS classification**: unified on GSTIN presence + the real 2.5L/inter-state B2CL rule everywhere (the old `gstr1Details()` JSON endpoint had a SEPARATE, incomplete B2CL rule — threshold only, no inter-state check — now fixed to match the correct rule used elsewhere, which is why `tests/Feature/Cov/ReportsGstTest.php`'s JSON-endpoint test fixture needed a small update, see below).
5. **HSN reconciliation**: real `GROUP BY hsn, rate` from `sales_bill_items`; missing HSN is a labeled bucket, never `999721` or any other invented code.
6. **Credit/debit note reconciliation**: CDNR/CDNUR now carry the real original invoice reference (`sales_bill_id` → `bill_number`/`bill_date`), not just a total.
7. **Document-issued reconciliation**: now genuinely covers BOTH sales-bill numbers and sales-return numbers (the old version only ever counted bills) — this is the correct GSTR-1 rule, and it's why the pre-existing `ReportsGstTest` fixture's expected count changed from 6 to 8 (documented in that test's own updated comment).
8. **Unsupported Export/Advance/Nil limitations**: Export and both Advance sections report `supported: false` with an explicit reason string; Nil/Exempt/Non-GST reports one combined honest number with an explicit "cannot distinguish" note — none of the three is silently presented as more precise than the schema actually allows.
9. **Query performance**: every section is pure SQL aggregation (`GROUP BY`/`SUM`), no PHP loops hydrating full collections, no repeated TaxEngine recalculation. `EXPLAIN` on the core B2B/HSN queries against the 20.8-lakh-row QA dataset shows range scans on `sales_bills_bill_date_index` and `eq_ref`/FK-indexed joins throughout — no full table scans.

## Reconciliation

- `test_b2b_plus_b2cs_plus_b2cl_taxable_reconciles_to_total_posted_taxable_with_no_double_counting` proves, on a 3-bill controlled fixture spanning all three buckets, that `B2B.taxable + B2CS.taxable + B2CL.taxable` exactly equals the independently-computed sum of `(bill.total - bill.total_gst)` across all three bills — no gap, no overlap.
- `test_cancelled_bill_excluded_from_taxable_totals_but_counted_in_documents_issued` proves a cancelled bill contributes `0` to every taxable-value section while still counting once toward Documents Issued's total (and once toward its cancelled count) — matching the GSTR-1 rule that cancelled documents are reported, not silently dropped from the numbering sequence.
- Independent spot-check against real QA data (not just the controlled fixture): a raw SQL query for B2B bills in September 2026 on `urban_pos_qa` returned `count=741, taxable=₹1,298,985.05` — the live report page showed the identical numbers.

## Files changed (this pass)

- `app/Services/GST/Gstr1ReportService.php` — new; all 12 sections' real queries
- `app/Http/Controllers/GST/EInvoiceDashboardController.php` — `gstr1View`, `gstr1SectionView`, `gstr1Details` rewritten to use the service; sandbox-GSTIN detection added
- `app/Models/SalesBill.php`, `app/Models/SalesReturn.php` — `customer_gstin` added to `$fillable`
- `app/Http/Controllers/Sales/SalesBillController.php`, `app/Http/Controllers/Sales/SalesReturnController.php` — snapshot `customer_gstin` at posting time
- `database/migrations/2026_10_01_000003_add_customer_gstin_snapshot_to_sales_documents.php` — new; applied + rolled back + re-applied on `urban_pos_qa`, verified clean each time (a real bug was found and fixed in this migration's own rollback along the way — see below)
- `resources/views/gst/gstr-1.blade.php`, `gstr-1-section.blade.php` — sandbox-GSTIN banner; section view now branches by real per-section layout instead of one generic HSN table
- `tests/Feature/Gstr1ReportTest.php` — new, 11 tests, 63 assertions
- `tests/Feature/Cov/ReportsGstTest.php` — 2 fixtures updated to reflect corrected (not weakened) business rules — see next section
- `docs/GSTR1-AUDIT.md`, `docs/GSTR1-PHASE2-REPORT.md` — this pair of documents

## A real bug found and fixed along the way (migration rollback)

While verifying the new migration's `down()` actually works (not just assuming it), the first rollback attempt silently did nothing — the column stayed. Root cause: `SHOW COLUMNS FROM x LIKE ?` does not work with a bound PDO placeholder (MySQL error 1064), and the exception was being caught and swallowed by the helper's own `try/catch`, so `hasColumn()` always returned `false` — which happened to make `up()` behave correctly by accident (guard `if (!hasColumn())` was always true) but made `down()` a permanent no-op (guard `if (hasColumn())` was always false). Fixed by using a `WHERE Field = ?` clause instead of `LIKE ?`, which does support placeholders correctly — verified by re-running the full drop → rollback (column actually gone) → re-apply (column back) cycle successfully.

## Existing tests updated (not weakened)

Two assertions in the pre-existing `tests/Feature/Cov/ReportsGstTest.php` needed updating, both because they encoded the OLD, incomplete behavior:

1. `test_gstr1_details_json_buckets`'s B2CL fixture didn't set `sales_type=Interstate`/`total_igst`, because the OLD `gstr1Details()` JSON endpoint used a threshold-only B2CL rule (missing the inter-state check that `gstr1View()` already had correctly). Fixed the fixture to be genuinely inter-state, matching the one correct rule now used everywhere.
2. `test_gstr1_view_splits_...`'s `docIssuedTotal` expectation changed from 6 to 8, because Documents Issued now genuinely includes sales-return/credit-note numbers (2 in that fixture) in addition to sales-bill numbers, per the real GSTR-1 rule — the old code only ever counted bills.

Both changes are documented in-line in the test file itself with the reasoning, so a future reader doesn't mistake this for weakening the test.

## Test results

- `tests/Feature/Gstr1ReportTest.php`: **11/11 pass, 63 assertions.**
- `tests/Feature/Cov/ReportsGstTest.php` + `tests/Feature/GstSummaryAggregationTest.php` (existing GST-related regression): **43/43 pass, 344 assertions** (after the 2 documented fixture corrections above).
- Full PHPUnit suite (run because this pass changed PHP business logic in `SalesBillController`/`SalesReturnController`, not just views): **1098 tests, 7965 assertions — 1097 passed, 1 pre-existing test needed a one-line update, 1 skipped (unrelated, pre-existing).** The one failure, `SalesReturnFlowAndValidationTest::test_sales_return_create_page_renders_with_picker_and_inline_error_containers`, asserted the literal HTML id of the OLD single-select dropdown on the Sales Return page — a **separate, earlier fix in this same session** replaced that dropdown with a multi-check checkbox list (unrelated to GSTR-1). Updated the assertion to the new element id and re-ran that file plus the 3 other sales-return regression files: **47/47 pass.** Not re-running the full 1098 a second time for this one-line test-only fix — nothing else in that run touched sales-return HTML.

## Performance

`EXPLAIN` on the B2B classification query and the HSN aggregation query (against `urban_pos_qa`, ~20.8 lakh rows) both show `range` scans on `sales_bills_bill_date_index` for the date filter and `eq_ref`/FK-indexed lookups for the customer/item joins — no full table scans, no N+1s (every section is one query, not a query-per-bill).

**Measured timing found two real, worth-fixing problems, both fixed in this pass:**

1. **HSN sections were unbounded.** This QA dataset's seed data assigns a near-unique HSN code per item (34k+ distinct codes catalog-wide), so a month's HSN summary produced **8,326 distinct HSN+rate groups** — an unbounded HTML table that took 15+ seconds to render. Fixed: capped at the top 500 groups by taxable value (`Gstr1ReportService::HSN_ROW_CAP`), with an honest "showing the top 500 (there are more)" banner — detected by fetching one row past the cap, not a separate COUNT query (measured as costing as much as the main query itself, not worth doubling every HSN page's cost for an exact "of N" figure). Summary totals (taxable/tax/missing-HSN-qty) still reflect the FULL period, not just the capped rows.
2. **B2CS pulled every matching bill into PHP just to `sum()` three fields**, then re-joined `sales_bill_items` on a giant `whereIn($billIds)` list for the rate breakdown. At this app's real monthly volume (8,663 B2CS bills in the test period), that's real, measurable overhead. Fixed: headline count/taxable/tax now come from one flat SQL aggregate (`COUNT`/`SUM` — same "`total_gst` is authoritative" approach already used for B2B/B2CL), and the breakdown query re-expresses the same filter as a join instead of collecting IDs first. Measured: `b2cs()` alone dropped from ~3.1s to ~1.6s.

**Honest remaining numbers** (measured directly via `php artisan tinker`, isolating PHP+DB from HTTP/view overhead, on `urban_pos_qa`'s real September-2026 data): `b2b` 0.9s, `b2cl` 0.65s, `b2cs` 1.6s, `hsnB2b` 1.3s, `hsnB2c` 1.9s, `cdnr`/`cdnur`/`nilRated` well under 0.1s each, `documentsIssued` 0.65s — the 12-section summary page's ~7.4s is genuinely just the sum of these, not hidden overhead. The browser-measured page load adds a further ~5-6s of HTTP/view-rendering time consistent with this app's general per-page asset weight (seen on every page in this app all session, not specific to GSTR-1).

This is a monthly statutory report, not a page hit repeatedly under operational load, so single-digit seconds was judged an acceptable place to stop for this pass rather than chase further gains — but if faster is wanted later, the next real lever would be a composite index shaped for these specific joins (e.g. `sales_bill_items(sales_bill_id, gst_percent)` covering the GROUP BY), which deserves its own benchmark-before/after rather than being added speculatively here.

## Open / not done in this pass

- `gstr3bDetails`/`gstr9Sync`/`uploadGstr2` have similar guess/mock patterns (flagged in the Phase 1 audit) but are outside this task's 12-section GSTR-1 scope — not touched.
- The generic "SYNC NOW" button and its `setTimeout` fake-sync animation in `gstr-1-section.blade.php` is cosmetic-only and untouched — it was already understood to be decorative, not a data-correctness issue.
- Production `.env`/Hostinger: untouched, per explicit instruction.

**Do not treat GSTR-1 as filing-ready without a real GSTIN configured** — the sandbox-GSTIN banner now says so directly on both pages rather than silently showing a demo number as if it were real.
