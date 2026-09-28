# Priority 8 — Coverage Gap Classification

Source: PCOV full-suite run (2026-09-27, 1081 tests, 98.51% line coverage). 15 methods across the 13
`phpunit.branch.xml`-scoped business-critical files were not fully covered. Each was read, its real call
sites traced, and classified below before writing (or deliberately not writing) a test.

## Genuine gaps — closed with new tests

| Method | Gap | Test |
|---|---|---|
| `TaxEngine::calculate` | Percent-based discount branch (every prior test passed a pre-computed `disc_amount`) | `Priority8CoverageGapsTest::test_tax_engine_derives_discount_amount_from_percent_when_only_percent_is_given` |
| `SalesReturnController::validateData` | "Customer hasn't purchased this item" anti-fraud rule — no test anywhere exercised it | `test_sales_return_rejects_item_the_customer_never_purchased` |
| `StockTransferController::getItemByCode` (via `resolveItemExpiry`) | Malformed/unparseable `exp_date` string (legacy/bad-import data) must not crash the barcode-scan lookup | `test_stock_transfer_item_by_code_tolerates_unparseable_expiry_date` |
| `StockTransferController::itemList` | Same malformed-expiry tolerance, in the raw-SQL search path | `test_stock_transfer_item_list_tolerates_unparseable_expiry_date_in_search` |
| `EnsureBranchAccess::handle` | Route parameters that are (a) not objects, (b) objects with none of `branch_id`/`from_branch_id`/`to_branch_id` — both "keep scanning" branches in a security-boundary middleware | `test_ensure_branch_access_skips_non_object_and_branch_less_route_parameters` + `..._allows_when_branch_bearing_parameter_matches` |
| `PurchaseReturnController::store` | Same-`posting_key` race, **with** an invoice link (line 105: found mid-transaction, return the existing document) | `scripts/qa/concurrency.php` scenario `duplicate_purchase_return` (genuine multi-process) |
| `PurchaseReturnController::store` | Same-`posting_key` race, **without** an invoice link (unique-constraint catch) | `scripts/qa/concurrency.php` scenario `duplicate_purchase_return_no_invoice` |
| `SalesReturnController::store` | Same-`posting_key` race, **without** a bill link (unique-constraint catch) — `nobill_return_race` already existed but uses a *different* `posting_key` per worker to test the quantity ceiling, not this idempotency path | `scripts/qa/concurrency.php` scenario `duplicate_return_no_bill` |

The three race-recovery scenarios are real multi-process, barrier-started concurrency tests (not PHPUnit),
because that's what this code exists for. An earlier attempt to simulate the race inside a single PHPUnit
process (via `Illuminate\Database\Events\TransactionBeginning`) was tried and abandoned: MySQL's REPEATABLE
READ snapshot and `RefreshDatabase`'s test-wrapping transaction make single-process simulation unreliable in
subtle, hard-to-diagnose ways (a plain `SELECT` inside a nested transaction doesn't see another connection's
already-committed row if that connection's snapshot predates the write; and injecting the row on the *same*
connection gets rolled back along with the failed `INSERT` it's supposed to be caught by) — genuine
multi-process concurrency sidesteps both problems entirely, and this codebase already has that harness.
`SalesReturnController::store`'s with-bill race path (line 121) was **not** re-tested — it was already
verified passing by the pre-existing `duplicate_return` scenario.

## False / strict-metric gaps — not tested, left as-is

| Method | Line(s) | Why it's not a meaningful gap |
|---|---|---|
| `DamageStockController::assertStockAvailable` | `if (!$item) continue;` | Defensive: `item_id` is already `exists:items,id`-validated before this runs. |
| `StockTransferController::assertStockAvailable` | same pattern | Same reason. |
| `PurchaseReturnController::assertStockAvailable` | same pattern | Same reason. |
| `SalesReturnController::assertReturnableAgainstBill` | `if (!$bill) return;` | `sales_bill_id` has `'nullable', 'exists:sales_bills,id'` — always valid or absent by the time this runs. |
| `SalesReturnController::assertWithinCustomerPool` | `if ($customerId <= 0) return;` / `if (!$requested) return;` | `customer_id` is `required`, items are `required|min:1` at the form-validation layer — both are unreachable given upstream validation. |
| `PurchaseReturnController::assertReturnableAgainstInvoice` | `if ($invoiceId <= 0) return;` | `store()` only ever calls this method from *inside* `if (!empty(purchase_invoice_id))` — this early-return is reachable only from `update()`'s edit-time re-validation path, a narrow, low-value case. |
| `LoyaltyService::accruePointsForBill` | fallback re-fetch of `$customer` when the relation returns null | Redundant in every reachable scenario: the fallback re-runs the identical `find($salesBill->customer_id)` query, which would return the SAME null result the lazy relation already gave (same underlying row, same global scopes) — testing it wouldn't prove anything the already-covered "no customer" branch doesn't. |
| `LedgerPostingService::nextNumber` | `'Contra' => 'CTR'`, `default => 'JV'` match arms | **Dead code from this class's own call sites.** Traced every caller: `postSalesBill`/`postPurchaseInvoice`/`postSalesReturn`/`postPurchaseReturn` always pass their own fixed type; `postBillSettlement` passes `'Receipt'`/`'Payment'` (both *are* covered, via `BillSettlementTest`). Manual "Contra" vouchers are a distinct feature served by `VoucherController`'s own, separate `nextNumber()` method (tested by `FinVoucherTest`) — `LedgerPostingService` never receives `'Contra'` from anywhere in the real app. |

## Bugs found while doing this work (fixed, unrelated to coverage-chasing)

- **`DocumentNumberingService`** (`next()`, `nextPrefixed()`, `generate()`): a genuine deadlock under real
  concurrent load, found by `scripts/qa/concurrency.php`'s `purchase_race` scenario (0/8 → briefly 1/8
  succeeding before the fix). Root cause: three `DB::transaction()` calls with no retry-attempts argument,
  racing on the same hot `document_sequences` counter row. Fixed by adding `, 3` (retry attempts) to each —
  Laravel's built-in deadlock-retry (rollback to savepoint, re-run the closure). Verified: `purchase_race`
  now passes 8/8 consistently, full concurrency suite 34/34, full PHPUnit regression unaffected.
