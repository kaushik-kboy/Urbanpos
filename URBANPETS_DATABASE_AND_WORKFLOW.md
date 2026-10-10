# Urban Pets — Stock & Expiry Verification: Database & Workflow

Status: DESIGN (Phase 3). Implementation proceeds against this doc; any deviation found necessary during build will be noted inline here, not silently applied.

## 1. Design decisions locked with client (2026-10-10)

- New **`Staff`** Spatie role is added (does not replace Cashier). Staff gets only the new module's permissions (`stock-verification.*`, `barcode-mapping.*`). Owner/Manager get review/approval permissions in addition.
- Expiry buckets are **configurable**, stored in a new `expiry_bucket_settings` table, seeded with defaults **0–30 / 31–60 / 61–120 days** (editable later without a code change).
- No DB backup exists for `urban_pos` today (confirmed by audit). Mitigation: every new migration is **additive only** (new tables/columns, nullable where added to existing tables) and fully reversible via `down()`. No existing table is altered destructively. No existing stock-affecting code path is modified.

## 2. Integration points (reused, not reimplemented)

- `App\Services\Inventory\StockLedgerService::post()` — the only place inventory quantity is ever changed. Approved verification corrections call this exactly once per approval, inside a DB transaction with row locking.
- `App\Services\Inventory\BatchStockService::getItemBatches()/getBatchStock()` — read-only batch/expiry reconstruction, reused for both the manager's batch view and expiry classification.
- `App\Http\Middleware\EnsureBranchAccess` pattern — mirrored (not reused directly, since it's a `web`-guard middleware) in a new `EnsureApiBranchAccess` for the `api` guard.
- `spatie/laravel-permission` — new permissions added via the existing `MODULE_ACTIONS`/`MANAGER_MODULES` pattern in `RolesAndPermissionsSeeder`, not a parallel permission system.

## 3. New tables

### `expiry_bucket_settings`
Configurable dashboard buckets.
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| label | string | e.g. "Expiring Soon" |
| min_days | int | inclusive |
| max_days | int | inclusive |
| severity | string | info / warning / critical |
| sort_order | int | |

Seeded rows: (0,30,"critical"), (31,60,"warning"), (61,120,"info").

### `stock_count_tasks`
Manager-assigned counting/verification tasks.
| column | type | notes |
|---|---|---|
| id | bigint pk | |
| branch_id | FK branches | |
| title | string | |
| section | string nullable | rack/section free text |
| category_id | FK item_categories nullable | LEGACY — points at a category *head*; kept, see §8 item 10 |
| category_value_id | FK item_category_values nullable | *(added 2026-10-10, migration 010007)* product category the task is scoped to (= `items.category_value_id`) |
| brand_id | FK brands nullable | *(added 2026-10-10, migration 010006)* brand the task is scoped to (= `items.brand_id`) |
| assigned_to | FK users | staff |
| assigned_by | FK users | manager/owner |
| due_date | date nullable | |
| priority | enum(low,normal,high) default normal | |
| status | enum(pending,in_progress,completed,cancelled) default pending | |
| timestamps | | |

### `stock_count_task_items` (optional scoping; task may be open-ended)
| task_id | FK | |
| item_id | FK items nullable | |
| batch_no | string nullable | |

### `inventory_observations`
Unified table for both blind stock counts and expiry observations (identical review lifecycle; splitting into two tables would duplicate the approve/recount/reject logic for no benefit).

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| type | enum(stock_count, expiry_check) | |
| task_id | FK stock_count_tasks nullable | ad-hoc observations allowed |
| item_id | FK items | real PK, never item name |
| branch_id | FK branches | |
| batch_no | string nullable | |
| exp_date_observed | date nullable | staff-reported expiry (expiry_check only) |
| physical_qty | decimal nullable | required for stock_count, optional for expiry_check |
| remarks | text nullable | |
| photo_path | string nullable | |
| idempotency_key | uuid unique | client-generated, prevents duplicate-tap/retry submissions |
| system_qty_snapshot | decimal | captured server-side at submission time; **never returned to staff in API responses** |
| status | enum(pending,approved,rejected,recount_requested) default pending | |
| parent_observation_id | FK inventory_observations nullable | set when this row is a recount re-submission; preserves prior row untouched (audit trail, not overwritten) |
| approved_qty | decimal nullable | manager's approved target quantity |
| actual_adjustment_qty | decimal nullable | delta actually posted to ledger (computed from re-validated current stock, not the stale snapshot) |
| stock_ledger_id | FK stock_ledger nullable | set on approval; links to the ledger row it produced |
| submitted_by | FK users | |
| reviewed_by | FK users nullable | |
| review_remarks | text nullable | |
| reviewed_at | timestamp nullable | |
| timestamps | | |

Unique index: `(idempotency_key)`.
Index: `(branch_id, status)`, `(item_id, branch_id, batch_no)`.

### `barcode_mapping_requests`
| id | bigint pk | |
| scanned_barcode | string | |
| suggested_item_id | FK items nullable | |
| branch_id | FK branches | |
| status | enum(pending,approved,rejected) default pending | |
| submitted_by | FK users | |
| reviewed_by | FK users nullable | |
| remarks | text nullable | |
| timestamps | | |

### Sanctum
`personal_access_tokens` table via `laravel/sanctum` package migration (vendor:publish). Standard, unmodified.

## 4. Status transitions (enforced server-side only; client cannot set status directly)

```
inventory_observations.status:
  pending --approve--> approved        (StockLedgerService::post() called exactly once, inside transaction)
  pending --reject-->  rejected        (no inventory change)
  pending --recount--> recount_requested  (original row frozen; staff resubmits a NEW row with parent_observation_id = this row's id, status starts at pending)
```

No other transitions are valid. `approved`/`rejected` are terminal for that row. A second approve/reject/recount call on a non-pending row is rejected with 409 and does not touch the ledger (duplicate-approval protection).

## 5. Concurrency & idempotency rules

1. **Submission**: unique `idempotency_key` — a retried POST with the same key returns the existing record (200), never creates a second row.
2. **Approval**: `DB::transaction()`; `inventory_observations` row is locked (`lockForUpdate`) and re-checked for `status === 'pending'` before proceeding — a concurrent second approval attempt fails fast with 409. Current system stock is **re-fetched inside the transaction** (not the `system_qty_snapshot` taken at submission time) via `ItemStock` row lock inside `StockLedgerService::post()`. `actual_adjustment_qty = approved_qty - current_stock_at_approval_time`. If the ledger post throws, the whole transaction rolls back — observation stays `pending`, no partial state.
3. Worked example from the master prompt (system 20 → staff counts 17 → a sale drops stock to 18 → manager approves target 17): adjustment posted = `17 - 18 = -1`, not `17 - 20`. This is why the re-fetch-at-approval-time step exists.

## 6. Permissions added (via existing seeder pattern)

- `stock-verification.submit` (Staff, Manager, Owner)
- `stock-verification.review` (Manager, Owner) — approve/reject/recount
- `stock-verification.assign-task` (Manager, Owner)
- `barcode-mapping.request` (Staff, Manager, Owner)
- `barcode-mapping.review` (Manager, Owner)

`Staff` role: branch-scoped like Cashier (uses the same `branch_id` column on `users`).

## 7. Open items / unresolved (per master prompt §8 — documented, not silently decided)

- ~~Whether a recount keeps the same batch_no strictly~~ — **resolved by client decision, 2026-10-10**: see §8 item 16. A recount preserves the parent's `batch_no` by default; changing it requires an explicit, authorized, audited "batch correction".
- Photo storage: using local `storage/app/public/observations` for now (consistent with any existing image upload pattern found in the app); not yet confirmed against production disk config — flagged for Phase 11 review.

## 8. Implementation notes (Phases 6–9 build, 2026-10-10)

Everything in §1–§7 above was designed before this build and matched the schema exactly — no migration changes were needed. The following are implementation decisions made while wiring the actual controllers/routes/tests on top of that schema:

1. **Ledger movement type for approved corrections.** `StockLedgerService::post()` is called with `movementType = 'CORRECTION'` (already an allowed enum value on `stock_ledger.movement_type` — see `2026_09_14_000001_add_repack_kit_movement_types_to_stock_ledger.php`). No new enum value was added; `CORRECTION` is the existing generic adjustment type and fits a verified-count correction exactly. `reason_code` is set to `'STOCK_VERIFICATION_APPROVAL'` to distinguish these rows from other `CORRECTION` sources (e.g. the legacy `ItemStock::adjust()` forwarder) in reporting.

2. **Zero-delta approvals don't call the ledger at all.** If `approved_qty` exactly equals the freshly re-fetched current stock (`actual_adjustment_qty === 0.0`), `StockLedgerService::post()` is skipped entirely — it throws `InvalidArgumentException` on a zero quantity delta by design. The observation is still marked `approved` with `actual_adjustment_qty = 0` and `stock_ledger_id = null`. This is a reasonable, undocumented-until-now edge case: the master prompt's worked example never hits a zero delta, but a manager approving a count that happens to match current stock exactly must not get a 500 error.

3. **Current-stock revalidation at approval time, by scope.** When the observation has no `batch_no`, current stock is the locked `item_stocks.quantity` row (same row `StockLedgerService::post()` itself locks). When a `batch_no` is present, current stock is read via `BatchStockService::getBatchStock()` (aggregated from `stock_ledger`) — this table has no per-batch row to lock directly, so correctness instead relies on first acquiring the `item_stocks` row lock for that item+branch (which every other writer, including sales, must also acquire before posting to the ledger) before reading the batch aggregate. This serializes the batch-level read against concurrent ledger writers without a literal row lock on `stock_ledger`.

5. **(Phase 10) Dashboard "today"/"this week" boundaries.** `GET /v1/dashboard/summary`'s `reviewed_today`/`reviewed_this_week` counts use `inventory_observations.reviewed_at` (the moment the approve/reject decision was made), not `created_at`. "Today" = `Carbon::today()` (local-timezone calendar day start) through now. "This week" = `Carbon::now()->startOfWeek()` (Carbon's default week start, Monday 00:00) through now. Not specified in the master prompt; documented here per the task's requirement to log any undocumented decision.

6. **(Phase 10) Expiry-bucket item scope for the dashboard is bounded to items with stock-ledger batch history, not the full item catalog.** `buildExpiryBuckets()` first collects `DISTINCT item_id` from `stock_ledger` (per branch) `WHERE exp_date IS NOT NULL`, then calls `BatchStockService::getItemBatches()` only for those item ids — not for every item in the catalog. This keeps the endpoint's query count bounded by "items that actually have expiry-tracked stock movement" rather than catalog size, while still reusing `BatchStockService` as-is (no new stock-reading logic, per scope). The tradeoff: each matched item still costs `BatchStockService`'s own ~3 queries (unavoidable without rewriting that service, which is out of scope for Phase 10), so this is a bounded-N+1, not a zero-N+1 — acceptable given stock-ledger-tracked items are typically a small fraction of a branch's full catalog at any one time. Covered by a query-count sanity-ceiling test (`DashboardApiTest::test_dashboard_query_count_stays_within_a_reasonable_bound`), not a strict single-query proof.

7. **(Phase 10) Already-expired batches are not dropped from the expiry buckets.** A batch with a negative days-until-expiry (already past its expiry date) is clamped to the bucket whose `min_days` is `0` (the most urgent configured bucket, "critical" by default) and flagged `is_expired: true` in its item row, rather than being silently excluded from the dashboard. Not specified in the master prompt; this is the safer default for an expiry-exposure dashboard (an already-expired batch is at least as urgent as a soon-to-expire one).

8. **(Phase 10) "Representative list" is a flat cap, not full pagination.** The master prompt phrasing ("paginate the expiry-bucket item lists rather than returning everything") is satisfied by capping each bucket's `items` array at 10 rows plus a `has_more` boolean on the bucket, rather than full cursor/page-based pagination of a nested array (which Laravel's paginator doesn't support cleanly for a grouped-and-nested response like this). If deeper drill-down into a specific bucket's full item list is needed later, that should be a separate paginated endpoint (e.g. `GET /dashboard/expiry-buckets/{bucket}/items`), not retrofitted into this summary response.

9. **Permission checks are inline in the controllers (guard `'web'`), not via the `'permission:'` middleware alias.** Discovered during testing: `Illuminate\Auth\Middleware\Authenticate::authenticate()` calls `Auth::shouldUse($guard)` (here `'sanctum'`) on **every** authenticated api request. That mutates `config('auth.defaults.guard')` to `'sanctum'` for the rest of the request. `spatie/laravel-permission`'s `PermissionRegistrar::registerPermissions()` resolves the permission's guard via `Spatie\Permission\Guard::getDefaultName()`, which prefers `config('auth.defaults.guard')` whenever it's one of the model's possible guards — and `'sanctum'` is, since both the `web` and `sanctum` auth guards share the `users` provider. Since every `Permission`/`Role` row in this app is seeded with `guard_name = 'web'`, the middleware alias's own permission check (`$user->canAny($permissions)`, called with no explicit guard) always resolves against `'sanctum'` after the api request is authenticated and always returns false — i.e. `'permission:x,sanctum'` middleware denies every legitimate request on a sanctum-guarded route in this app's current auth config, not just in tests. The fix: every mobile-app controller action that needs a permission check calls `$user->hasPermissionTo($permission, 'web')` directly with an explicit guard, bypassing the mutated default entirely. Branch access and role checks (`hasRole`/`hasAnyRole`) are unaffected by this bug since they don't resolve a guard the same way. This is flagged here in case a future `web`-guard-only feature needs the `'permission:'` middleware on an api route again — it needs this same workaround or a guard-aware fix to the route registration.

10. **(Overnight-2) Task scope "by Category" uses `item_category_values`, not `item_categories`.** In this POS, `item_categories` rows are category *heads* (`DEPARTMENT`, `CATEGORY`, `Brands` — see `ImportItemMaster`), and a product's category is `items.category_value_id → item_category_values`. Every existing POS screen that filters products by category (`Master\ItemController@index`, `BarcodeController`, `PriceFixingController`, `ReportController`, …) filters on `items.category_value_id`. The original `stock_count_tasks.category_id → item_categories` therefore could not resolve a product list. Fix: additive migration `2026_10_10_010007_add_category_value_id_to_stock_count_tasks_table` (nullable FK, `nullOnDelete`); `category_id` is kept untouched and still accepted for backward compatibility. Brand uses the existing `items.brand_id → brands` (migration 010006, written by the interrupted earlier attempt and applied in this session). `GET /v1/categories` lists active values under the `CATEGORY` head using the same alias list as the web item filter. Note: the dev DB `urban_pos` currently has **0** `item_category_values` (the `CATEGORY` head exists, empty) and 5/11 items with a brand — the category path is proven by automated tests, the brand path also live.

11. **(Overnight-2) Selected products must match the task's category/brand.** When `category_value_id` and/or `brand_id` is set and explicit `items` are selected, `POST /tasks` rejects (422, nothing created) any item outside that scope. This is a consistency guard derived from the master prompt's "assign tasks using … Category or brand / Selected products", not a new inventory rule; without a category/brand, item selection is unrestricted exactly as before. Flag for client confirmation if mixed scopes are ever desired.

12. **(Overnight-2) History & Audit visibility split.** Manager/Owner use `GET /v1/observations` (cross-staff, permitted branches, reviewer shape). Staff use `GET /v1/observations/mine` with the same filters (own rows only, blind shape). The split is enforced server-side (403 / hard `submitted_by = me` scope); the Flutter screen merely picks the endpoint by role. Date filters apply to `created_at` (submission time).

13. **(Overnight-2) Approve target quantity is now explicit in the app.** The Flutter review screen used to send `approved_qty = physical_qty ?? 0`; approving an expiry check that had no counted quantity would therefore have asked the server to set stock to 0 (a full write-off via `StockLedgerService`). The app now shows an editable "Approved target quantity" field, pre-filled with the staff count and **empty (Approve disabled) when there is no count**. The server contract is unchanged (`approved_qty` required) for `stock_count` observations. **Resolved, 2026-10-10: an `expiry_check` approval never accepts or applies `approved_qty` at all** — see §8 item 18.

14. **(Overnight-2, gap-closure follow-up) Authorised photo retrieval.** `GET /v1/observations/{observation}/photo` (Manager/Owner only, same branch-scoping as approve/reject) streams the uploaded evidence file from the `public` disk — previously there was no way to fetch it at all (flagged as a FAIL in the §11 checklist). Deliberately still not exposed to the submitting Staff member or via a public `storage:link` URL — a reviewer-only authenticated route, consistent with the rest of this module.

15. **(Overnight-2, gap-closure follow-up) Pure review/task logic extracted to `App\Support\*`.** `ObservationVariance`, `TaskStatusTransition`, `ObservationReviewEligibility`, `ExpiryBucketClassifier`, `IdempotentSubmissionOutcome` — small static helpers pulled out of `ObservationController`/`TaskController`/`DashboardController` purely so the module's core decision rules (variance math, who-can-transition-what, pending-only review, expiry bucket boundaries, replay-vs-new status code) have isolated `tests/Unit` coverage that doesn't need a database, addressing the §13 "no isolated unit tests for the module" gap. No behaviour changed — each controller call site was a direct substitution, re-verified by the full `tests/Feature/Api` suite (unchanged pass count) after the refactor.

16. **(Business-rules session, 2026-10-10) Recount batch_no preservation + audited correction.** `inventory_observations` gained `batch_correction_applied` (bool), `original_batch_no`, `batch_correction_reason` (migration `2026_10_10_020002_...`). On a recount resubmission (`POST /observations` with `parent_observation_id`): omitting `batch_no` entirely defaults to the parent's `batch_no` (not a correction). Sending the SAME `batch_no` as the parent is also not a correction. Sending a DIFFERENT `batch_no` is rejected (422) unless `batch_correction: true` + a non-empty `batch_correction_reason` are both present, in which case the new row is created with `batch_correction_applied = true`, `original_batch_no` = the parent's batch, and the reason stored — who (`submitted_by`) and when (`created_at`) are already columns on the same row, so the audit trail needs no separate table. The Flutter app does not yet expose a UI to perform an authorized batch correction (no client request for one) — only the API/backend enforces and records it; flagged as the one NOT VERIFIED (UI) item for this rule.

17. **(Business-rules session, 2026-10-10) Multi-category / multi-brand task scope.** New pivot tables `stock_count_task_category_values` and `stock_count_task_brands` (migration `2026_10_10_020001_...`, backfilled from the pre-existing singular columns) are the authoritative scope; `stock_count_tasks.category_value_id`/`brand_id` are kept and populated ONLY when exactly one category/brand was selected (backward compatible with every existing single-scope query/test). `POST /tasks` accepts `category_value_ids[]`/`brand_ids[]` alongside the existing singular fields (merged into one set, not alternatives) — multiple values are stored only when explicitly sent as more than one id. The item-scope-consistency check now validates against the UNION of every selected category/brand (an item is in-scope if it matches at least one of them, not all) — e.g. selecting "Dog Food" category + "Royal Canin" brand allows both RC cat food AND any dog food brand, not just RC dog food. The Flutter Assign Task screen's Category/Brand fields are now multi-select (checkbox dialog); the product-picker preview query still only filters by the FIRST selected category/brand (the item-search API filter is single-valued), but submission-time validation is always fully server-side regardless of what the picker previews.

18. **(Business-rules session, 2026-10-10) Expiry-check approval never changes stock quantity.** `ObservationController::approve()` now branches on `observation->type` before validating input: for `expiry_check`, `approved_qty` is not accepted/read at all, `StockLedgerService::post()` is never called, and the observation is marked `approved` from only an optional `remarks` field — `approved_qty`/`actual_adjustment_qty`/`stock_ledger_id` stay `null`. Quantity corrections remain exclusively the `stock_count` approval path (unchanged). This closes the "approval-side risk" flagged as open in §8 item 13 and in the master-prompt checklist's §8 row.

19. **(Business-rules session, 2026-10-10) Configurable expiry-bucket settings.** `GET /v1/expiry-buckets` (unchanged, public to any authenticated user) now has a sibling `PUT /v1/expiry-buckets/settings` (Manager/Owner only, via the existing `stock-verification.review` permission — there is no separate "settings" permission in the module's 5-permission set). Full-replace semantics: the submitted list becomes the new bucket set (existing ids updated, new rows without an id created, ids absent from the list deleted). Validation: at least 1 bucket, every `min_days <= max_days` and both `>= 0`, buckets must not overlap each other, and the most-urgent bucket (lowest `min_days`) must start at exactly `0` — gaps between buckets ARE allowed (e.g. 0-10 then 20-30) since `ExpiryBucketClassifier` already treats "no matching bucket" as unclassified rather than erroring, and no tiling requirement was ever confirmed with the client. Defaults remain 0-30 "Expiring Soon" (critical) / 31-60 "Expiring Later" (warning) / 61-120 "Watch List" (info) unless a Manager/Owner edits them. A new Flutter screen (`ExpiryBucketSettingsScreen`, reachable from the Manager/Owner Dashboard tile "Expiry Bucket Settings") lets Manager/Owner add/edit/remove buckets and save; validation errors from the server are shown, not swallowed.

20. **(Business-rules session, 2026-10-10) AdminLTE desktop screens added for this module.** Per explicit client instruction (overriding the prior session's "no AdminLTE requirement found" conclusion), two desktop screens were added under the existing Inventory menu: a Manager/Owner **Review Queue** (`/inventory/stock-verification/review-queue`, approve/recount/reject) and a cross-staff **History & Audit** (`/inventory/stock-verification/history`, same branch/date/status/type filters as the Flutter screen), plus an observation detail page with recount history and the evidence photo. `StockVerificationController` is a deliberately thin wrapper: every authorization check, branch-scoping rule and the ledger-posting approval logic already live in `ObservationController` (the Sanctum API controller) and are called directly from the web controller — a web-session request satisfies the exact same `hasPermissionTo($perm, 'web')`/branch checks the API controller performs, so there is exactly one code path that can approve/reject/recount an observation, proven by `StockVerificationAdminLteTest::test_mobile_submission_approved_via_web_screen_posts_the_same_ledger_correction_as_the_api` (a mobile-submitted observation, approved through the web screen, posts the identical `StockLedger` correction the API-side test already proves). The existing "Stock & Expiry Verification (Mobile)" permission-group label (`Master\RoleController`) was renamed to drop "(Mobile)" since it now also gates these desktop screens — the 5 underlying permissions are unchanged.
