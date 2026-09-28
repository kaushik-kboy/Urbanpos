# UrbanPOS — Local Production-Hardening Pass

Scope of this pass, per explicit instruction: **local repository + QA/test database only.**
Nothing on Hostinger or any real production server was touched, configured, or even connected to.

## PASS

### 1. Tender Enter-key bug — found, root-caused, fixed, regression-tested

**Reproduced first** (headless Playwright, `urban_pos_qa`): with `#tender-cash` focused inside the
Sales Bill's Tender/Payment modal, pressing Enter consistently did nothing — no confirm, no
submit, no error. A direct click on `#tender-save-btn` always worked.

**Root cause** (confirmed via jQuery internals, not guessed): `public/js/select2-init.js` has a
global "prevent accidental Enter-submit" guard, delegated on `document` with a selector
(`form input:not([type=submit])...`). jQuery's event-dispatch loop groups handlers by the DOM node
they're matched against and walks those groups in order, stopping the walk between groups on
`stopPropagation()` — which the guard's `return false` triggers — **even without**
`stopImmediatePropagation()`. The Tender modal's own Enter-confirm handler
(`resources/views/sales/sales-bills/_form.blade.php`, bound with `$(document).on('keydown', fn)`,
**no selector**) sits in a *later* group than any selector-matching handler, so it was silently
never reached whenever the guard's selector matched the focused field — which it does for
`#tender-cash` (an `<input>` inside the real `<form method="POST">`). Handlers matching the exact
same target as the guard (`.sb-item-code`, `.sb-qty`, etc.) were unaffected, because same-group
handlers only stop on `stopImmediatePropagation()` — which is exactly why only the Tender-confirm
step broke and not the rest of the keyboard flow.

**Fix** (`public/js/select2-init.js`): the guard now skips any field inside `.closest('.modal')`,
since a modal is always a deliberate action-confirmation surface with its own explicit
Enter-handling — a general fix, not a special case for this one modal. The existing
Tender/Payment modal behavior and the earlier cash-focus fix (`shown.bs.modal` re-focus) are both
preserved untouched.

**New focused regression test**: `scripts/qa/e2e/e2e_tender_enter_confirm.mjs` — proves, per run:
tender modal opens → `#tender-cash` is focused → Enter confirms (no click) → a Sales Bill is
actually created → no duplicate bill from a stray extra Enter afterward. Run 4 times in a row:
**21/21 assertions pass, 4/4 distinct bills created, 0 duplicates** (verified against the real
`sales_bills` table, not just page text). `scripts/qa/e2e/e2e_salesbill_full.mjs` was updated back
to a genuine `Enter` confirm (previously a click workaround) — **8/8 pass**.

### 2. Duplicate-index cleanup — QA database only

Migration `database/migrations/2026_10_01_000002_drop_duplicate_sales_bills_indexes.php` drops
exactly the 3 confirmed exact duplicates on `sales_bills`:

| Dropped | Duplicate of (kept) |
|---|---|
| `idx_sb_bill_date` (bill_date) | `sales_bills_bill_date_index` |
| `idx_sb_branch_date` (branch_id,bill_date) | `sales_bills_branch_id_bill_date_index` |
| `idx_sb_customer_date` (customer_id,bill_date) | `sales_bills_customer_id_bill_date_index` |

`sales_bill_items.idx_sbi_item_id` was **not** touched, per instruction.

Verified on `urban_pos_qa` (port 3307) only:
- Index names confirmed to exist before dropping.
- `migrate` ran clean (315-570ms).
- `migrate:rollback --step=1` correctly restored all 3 indexes; re-`migrate` cleanly re-dropped
  them — rollback is implemented and tested, not just written.
- `SHOW INDEX` confirms the required indexes (`idx_sb_status`, `idx_sb_branch_status`,
  `idx_sb_created_at`, all `sales_bills_*`-named ones) are all still present.
- `EXPLAIN` on bill_date-range, branch+date, and customer+date queries all still pick an index
  (no full-table scan): `sales_bills_bill_date_index`, `sales_bills_branch_id_bill_date_index`,
  `sales_bills_customer_id_bill_date_index` respectively — the plan is unaffected, only the
  duplicate copy is gone.
- **Not run against production** — the migration exists in the repo; applying it to the real
  server is a future deploy-time decision, not done here.

### 3. Relevant regression (after both changes above)

- Smallest relevant PHPUnit set (Sales Bill / idempotency / duplicate-guard / tax / control
  foundation, 53 tests): **53/53 pass, 241 assertions**, re-run after the `select2-init.js` fix.
- Vitest: **22/22 pass.**
- Full 1087-test suite intentionally **not** re-run: both changes this pass are JS (view/asset) and
  a QA-DB-only index migration — no PHP business-logic path was touched, so it doesn't meet the
  "material change" bar for a full rerun.

## DOCUMENTED FOR FUTURE HOSTINGER DEPLOYMENT (nothing applied)

| Item | What's documented | Where |
|---|---|---|
| Logging configuration | `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning`, with reasoning | `docs/PRODUCTION-LOGGING-DEPLOYMENT-NOTE.md` |
| Scheduler cron | 3 tasks defined (`pos:heartbeat` hourly, `pos:backup`/`db:backup` daily); confirmed firing on this dev box via real dated backup files; production still needs its own `* * * * * php artisan schedule:run` cron entry | `docs/QA-CLOSURE-FINAL-PASS.md` §3 |
| PHP/OPcache | Already ON server-side on the real Hostinger account (196M, JIT) — better than local dev's default | `urbanpos-qa-pending-cleanup.md` memory, Priority 6 findings |
| MySQL configuration | Real server is MariaDB 11.8.9, `max_user_connections` 75 (live)/50 (staging) — a real per-account cap already measured | same |
| Redis | `redis` PHP extension present on the real server, no Redis process running; `predis/predis` added to composer for a no-extension-needed path if wanted later | same |
| Backup/restore | Backups genuinely running on schedule (real dated files); a restore was **actually tested** this pass (gunzip+mysql import into a disposable DB, clean success, ~94 tables restored) — this is evidence, not a claim | `docs/QA-CLOSURE-FINAL-PASS.md` §3 |
| Health checks | `SystemHealthController` + `pos-health-check` routes exist with real diagnostics endpoints | same |
| Monitoring | No external error-monitoring service (Sentry/Bugsnag) configured; logs are local-file only even after the recommended `daily` change | `docs/PRODUCTION-LOGGING-DEPLOYMENT-NOTE.md` |

## OPEN (genuinely unresolved)

- 100/200/300-concurrent-user load re-test after Priority 4/5/6 fixes: still **NOT MEASURED** — no
  real production server spec exists yet (the new Hostinger plan isn't provisioned), and repeating
  it on the same local laptop was explicitly ruled out as not representative. This can only be
  measured meaningfully once the new hosting plan exists.
- Hostinger DNS/PHP version/MySQL/Redis/cron/`.env`/migrations/deploy: **deliberately untouched**,
  per this pass's explicit instructions — to be handled once the new plan is ready.
- Applying the duplicate-index migration and the recommended logging config to the *real*
  production database/`.env`: not done here (QA DB and documentation only, as scoped).

## Bottom line

UrbanPOS is **not** being called production-ready in this pass — that determination is intentionally
deferred until the new Hostinger infrastructure is configured and the still-open items above are
addressed. What this pass closes: a real, previously-unfixed keyboard bug (found, root-caused,
fixed, regression-tested — not worked around), a real QA-database index cleanup (applied, verified,
rollback-tested), and a ready-to-apply logging recommendation for whenever production is next
touched.
