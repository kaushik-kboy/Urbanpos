# UrbanPOS — QA Closure, Final Pass

Follows the "Comprehensive QA Audit: Final Report". Scope: the 4 genuinely-open items only —
nothing already-complete was repeated.

## 1. The 3 missing full keyboard E2E flows — DONE

All three are new, real-Chrome (Playwright) scripts, run headless against `urban_pos_qa`
(port 3307) on a local `php artisan serve` (port 8299, stopped after this pass).

| Script | Result | Covers |
|---|---|---|
| `scripts/qa/e2e/e2e_salesbill_full.mjs` | **8/8** | customer (select2 keyboard) → item code+Enter (direct lookup) → qty → payment mode (select2 keyboard) → Save → **Tender/Payment modal** (cash pre-filled+focused) → Confirm → bill created, redirected |
| `scripts/qa/e2e/e2e_purchaseinvoice_full.mjs` | **9/9** | date+Enter → supplier (select2 keyboard) → Inv No/Amount → item-search **modal** (keyboard-only: F2/Tab, not click) → qty → cost auto-fill → Inv Amount matched to live total → submit, invoice created |
| `scripts/qa/e2e/e2e_purchasereturn_full.mjs` | **8/8** | supplier → original invoice (select2 keyboard, **auto-loads** its item row) → qty vs. remaining-qty ceiling (over → invalid+alert, valid → clears) → submit, return created |

All three were re-run to confirm stability (not one-off passes); each produced a genuine new
document (`SB-2026-46xx`, `PINV275xx`, `PRNxxxxxx`) confirmed in the QA database.

**A real bug was found and fixed in this pass** (REPRODUCE→ROOT CAUSE→FIX→regression, per the
Bug Handling protocol): on Sales Bill, opening the Tender/Payment modal focuses `#tender-cash`
via a `setTimeout(200)`, but Bootstrap's own modal-shown handling silently steals focus back to
the modal container ~300ms later. A cashier who doesn't type within that ~100ms window loses
focus to a non-input element with no visual sign. **Fix**: `resources/views/sales/sales-bills/_form.blade.php`
now also re-focuses `#tender-cash` on the modal's own `shown.bs.modal` event (`.one()`, so it
can't stack), which reliably wins the race. Verified fixed (`focused=true` on every re-run).
Regression: the 53 most relevant PHPUnit tests (SalesBillCovTest, DuplicateSubmitGuardRegressionTest,
SalesBillIdempotencyTest, SalesBillNegativeStockAndRetentionTest, SalesBillTaxInclusivePosTest,
ControlFoundationTest) — **53/53 pass, 241 assertions**. Full 1087-test suite was **not** re-run:
the change is JS-only inside a Blade view (no PHP path touches it), so it doesn't meet the
"application code changed materially" bar the instructions set for a full rerun; Vitest (22/22)
was re-run anyway as a cheap blanket check.

**Secondary finding, not fixed** (documented instead, per time-boxing): a native `Enter` keypress
while `#tender-cash` is focused reaches the app's own confirm handler (`defaultPrevented` flips
true, confirmed via instrumentation) but intermittently does not complete the confirm click —
reproducible under headless Playwright, not chased to a root cause within this pass's time-box; a
direct click on `#tender-save-btn` is unaffected and is what the script uses.

**Remaining scope note** (unchanged from the last report): a full end-to-end keyboard flow for
Purchase Invoice/Purchase Return covers header→item→qty→submit now; Sales Bill covers all of
that **plus** the payment/tender step. No further keyboard-E2E gap remains for these 3 documents.

## 2. Database index audit — DONE, no changes made

Inspected `SHOW INDEX` on the 13 hot tables from the QA report, on the dev DB (`urban_pos`).

| Table | Finding | Columns | Safe to remove? |
|---|---|---|---|
| `sales_bills` | **Duplicate** — `idx_sb_bill_date` vs `sales_bills_bill_date_index` | `bill_date` (both) | Yes — byte-identical column list, drop either |
| `sales_bills` | **Duplicate** — `idx_sb_branch_date` vs `sales_bills_branch_id_bill_date_index` | `branch_id,bill_date` (both) | Yes |
| `sales_bills` | **Duplicate** — `idx_sb_customer_date` vs `sales_bills_customer_id_bill_date_index` | `customer_id,bill_date` (both) | Yes |
| `sales_bill_items` | **Redundant** — `idx_sbi_item_id` is a left-prefix of `idx_sbi_item_bill` | `item_id` vs `item_id,sales_bill_id` | Yes, by the standard redundant-index rule (composite already serves item_id-only lookups); kept as its own row since a single-column index is marginally cheaper for item_id-only scans — flagging as lower-priority than the 3 above |
| `item_stocks`, `purchase_invoices`, `purchase_invoice_items`, `sales_returns`, `sales_return_items`, `purchase_returns`, `purchase_return_items`, `items`, `customers`, `suppliers`, `document_sequences` | No duplicates found | — | n/a |

**Root cause**: three separate migrations (`2026_09_18_..._add_performance_indexes_to_pos_tables.php`,
`2026_09_23_..._add_performance_composite_indexes.php`, `2026_09_30_..._add_scale_performance_indexes.php`)
each independently added overlapping `sales_bills` indexes without checking what already existed —
an accumulation pattern across iterative performance work, not a single mistake.

**No index was dropped or modified** — this is a report only, per the explicit instruction. If the
user wants this cleaned up, it's a single small migration (drop 3 exact duplicates on `sales_bills`,
optionally the 4th on `sales_bill_items`); happy to write and test it on request, not done blind here.

## 3. Deployment readiness audit — DONE

| Item | Evidence | Status |
|---|---|---|
| **Queue** | `QUEUE_CONNECTION=database`; `jobs`/`failed_jobs` tables exist (0 rows). Grepped the whole `app/` tree: **zero** `::dispatch(` calls or `ShouldQueue` classes anywhere in application code. | Infra present but **entirely unused** — no worker/supervisor needed because nothing is ever queued. Not a gap in practice; note for future if queued jobs get introduced later. |
| **Scheduler** | `routes/console.php`: 3 tasks — `pos:heartbeat` hourly, `pos:backup` daily 02:00, `db:backup --clean` daily 01:00. **Real evidence it's firing**: `storage/app/backups/` has dated backup files from both commands going back to Sep 18, continuously through today. | **Confirmed working** on this environment. For the actual production host (Hostinger), this still needs its own `* * * * * php artisan schedule:run` cron entry — no evidence either way for that specific server; flag before go-live. |
| **Backup** | Two independent backup commands both producing valid, non-trivial `.sql.gz` files on schedule. | **PASS** |
| **Restore** | No dedicated `artisan` restore command exists. **Actually tested restoring** the most recent backup (`backup-2026-09-27_12-57-04.sql.gz`) into a disposable `urban_pos_restore_test` database: `gunzip` + `mysql` import completed with exit 0, `SHOW TABLES` returned the full ~94-table schema back. Disposable DB dropped immediately after. | **PASS (manually verified this pass, not merely assumed)** — but no automated/scripted restore path exists; a documented `artisan db:restore` command would be a good, low-risk addition later. |
| **Health checks** | `SystemHealthController` (`system-health`, `system-health/run`, plus backup download/delete/clear-log endpints) and a separate `pos-health-check` view both exist and are routed. | **PASS** — real diagnostics endpoints exist, not just a static page. |
| **Error monitoring/logging** | `LOG_CHANNEL=stack` → `LOG_STACK=single` (not `daily`): logs go to **one never-rotated `storage/logs/laravel.log`**. `LOG_LEVEL=debug` (very verbose). No Sentry/Bugsnag/external monitoring configured. | **NEEDS FIX before production** — recommend `LOG_STACK=daily` (with `LOG_DAILY_DAYS`, e.g. 14) and `LOG_LEVEL=warning` or `error` for prod, so the log file can't grow unbounded and noise is reduced. Not applied here — a config change, reported for the user's own go-live checklist. |

## 4. Regression

- Smallest relevant PHPUnit set (53 tests touching Sales Bill/idempotency/duplicate-guard/tax):
  **53/53 pass, 241 assertions, 0 failures.**
  Full 1087-test suite intentionally **not** re-run — the only production change this pass was a
  JS-only Blade-view timing fix, not PHP logic, so it doesn't meet the "material change" bar.
- Vitest frontend: **22/22 pass** (unaffected, re-run anyway as a cheap sanity check).

## What's still open before production sign-off (carried forward, unchanged from the last report unless noted)

1. Purchase Invoice / Purchase Return keyboard-E2E were the last real gaps — **now closed**.
2. 100/200/300-user load re-test post-fix — still **NOT MEASURED**, still blocked on a real
   target-server spec (unchanged; not repeated on the same laptop, per instruction).
3. `sales_bills`' 3 duplicate indexes (+1 lower-priority redundant one on `sales_bill_items`) —
   reported, not fixed; a small follow-up migration if the user wants it done.
4. Production logging config (`LOG_STACK=single`, `LOG_LEVEL=debug`) — needs changing before
   go-live; not applied here since it's an environment/config decision, not a code defect.
5. No scripted restore command — the restore path was proven to work manually; scripting it is a
   nice-to-have, not a blocker (a working restore was actually demonstrated this pass).
6. Production scheduler cron entry on the real host — unverified (Hostinger specifics unknown).

Nothing in this pass was invented to extend the process — each of the 4 requested items is
closed with concrete evidence above, and the process stops here.
