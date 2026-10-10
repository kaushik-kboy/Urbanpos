# Urban Pets Stock & Expiry Verification — API Documentation

Base path: `/api/v1`. Auth: Laravel Sanctum personal access tokens (`Authorization: Bearer <token>`), no cookies/CSRF. All routes below (except login) require a valid bearer token.

Permission names referenced below come from `database/seeders/RolesAndPermissionsSeeder.php` and are checked via `$user->hasPermissionTo($permission, 'web')` **inline in the controller**, not via the `permission:` route middleware — see "Implementation notes" §4 in `URBANPETS_DATABASE_AND_WORKFLOW.md` for why.

Roles: `Staff` (branch-scoped, submit-only), `Manager`/`Owner` (review/approve/assign, `Owner` has all-branch access).

---

## Auth

### `POST /auth/login`
No auth required. Rate-limited `throttle:6,1`.

Request: `{ "email": string, "password": string }`

Response `200`: `{ "token": string, "user": { "id", "name", "email", "branch_id", "role" } }`
Errors: `422` invalid credentials or inactive account.

### `POST /auth/logout`
Revokes the current token. Response `200`: `{ "message": "Logged out." }`

### `GET /me`
Response `200`: `{ "id", "name", "email", "branch_id", "role", "roles": [...] }`

---

## Reference data (pre-existing)

- `GET /branches` — branches visible to the caller (all for Owner/null-branch users, own branch otherwise).
- `GET /expiry-buckets` — configured expiry buckets (`label`, `min_days`, `max_days`, `severity`, `sort_order`).
- `PUT /expiry-buckets/settings` *(new, 2026-10-10 business-rules session)* — Manager/Owner only (`stock-verification.review`). Full-replace: `{ "buckets": [ { "id": 1, "label": "Expiring Soon", "min_days": 0, "max_days": 30, "severity": "critical", "sort_order": 1 }, ... ] }` (`id` omitted = create new; an existing id not present in the list is deleted). Validation (`422` on failure): ≥1 bucket, `min_days <= max_days` and both `>=0`, no overlaps between buckets, the most-urgent bucket (lowest `min_days`) must start at `0` — gaps between buckets are allowed. Response `200`: the saved list, same shape as `GET /expiry-buckets`.
- `GET /items/search?q=&per_page=&category_value_id=&brand_id=` — paginated item search by code/name/barcode. *(2026-10-10 overnight-2)* Optional `category_value_id` (→ `item_category_values.id`) and `brand_id` (→ `brands.id`) filters — the same product filters the web item master uses (`Master\ItemController@index`); invalid ids → `422`. Rows now also include `brand_id` and `category_value_id` (no quantities). `q` may be empty when a category/brand filter is given (lists that scope page by page).
- `GET /categories` *(new, overnight-2)* — any authenticated user. Product categories exactly as the POS defines them: active `item_category_values` whose parent `item_categories` head is named `CATEGORY` (or the aliases `Category`/`category`/`Categories`/`CAT`, same list as the web item filter). Response `{ "data": [ { "id", "name" } ] }`. Read-only — unlike the web helper it never creates a missing head row.
- `GET /brands` *(new, overnight-2)* — any authenticated user. Active brands, `{ "data": [ { "id", "name" } ] }`.
- `GET /items/barcode/{code}` — item lookup by `ean_upc_code`. `404` if not found.
- `GET /items/{item}/batches?branch_id=` (middleware `api.branch.access`) — batch/expiry rows from `BatchStockService`. **Blind counting**: a Staff-only user (not also Manager/Owner) never receives the `remaining_qty` key at all.

---

## Tasks (`stock_count_tasks`)

### `POST /tasks`
Permission: `stock-verification.assign-task` (Manager/Owner).

Request:
```json
{
  "branch_id": 1,
  "title": "Count Rack 3",
  "section": "Rack 3",          // nullable
  "category_value_id": 41,       // nullable — PRODUCT category (item_category_values.id, = items.category_value_id)
  "brand_id": 7,                 // nullable — brands.id (= items.brand_id)
  "category_value_ids": [41, 52], // nullable, array (new, 2026-10-10) — MULTIPLE categories, only when explicitly selected; merged with category_value_id, not an alternative to it
  "brand_ids": [7, 12],           // nullable, array (new, 2026-10-10) — MULTIPLE brands, same rule
  "category_id": 2,              // nullable, LEGACY: item_categories.id (a category *head*) — kept for compatibility only
  "assigned_to": 5,               // required, users.id — must have access to branch_id
  "due_date": "2026-10-15",      // nullable
  "priority": "high",            // low|normal|high, default normal
  "items": [{ "item_id": 10, "batch_no": "B1" }]  // optional selected products (max 500)
}
```
**Scope consistency:** the UNION of every selected `category_value_id`(s)/`brand_id`(s) is computed; if that union is non-empty and `items` is also given, every selected item must match AT LEAST ONE of the selected categories/brands, else `422` `{ "message": "These selected products do not belong to the chosen category/brand: …", "errors": { "items": [...] } }` and nothing is created (single query, not per item). With no category/brand, selected items are unrestricted (unchanged behaviour). **Multi-value storage (new, 2026-10-10):** when more than one category and/or brand is selected, the singular `category_value_id`/`brand_id` columns on the task stay `null` and the full set lives in the `stock_count_task_category_values`/`stock_count_task_brands` pivot tables (`task.category_values[]`/`task.brands[]` in responses); when exactly one is selected, the singular column is populated too, so every pre-existing single-scope query/filter keeps working unchanged.
Response `201`: `{ "data": { ...task, "items": [ { ..., "item": {id,name,item_code,ean_upc_code} } ], "branch": {id,name}, "assignee": {id,name,branch_id}, "assigner": {id,name}, "category": {id,name}|null, "category_value": {id,name,item_category_id}|null, "brand": {id,name}|null } }`
Errors: `403` permission/branch; `422` assignee has no access to `branch_id`, out-of-scope items, or validation errors.

### `GET /tasks/assignees?branch_id=` *(new, overnight-2)*
Permission: `stock-verification.assign-task` (Manager/Owner) — `403` for Staff. Branch-scoped: a branch-restricted Manager asking for another branch → `403`; `branch_id` required (`422`). Returns active users holding Staff/Manager/Owner whose `branch_id` equals the branch or is null (the same rule `POST /tasks` enforces on `assigned_to`). Response `{ "data": [ { "id", "name", "branch_id", "role" } ] }` — no other user columns.

### `GET /tasks`
No extra permission (role-based visibility): Staff sees only tasks where `assigned_to = me`; Manager/Owner see all tasks in their accessible branch(es). Query: `status`, `category_value_id`, `brand_id`, `category_id` (legacy), `per_page`.
Response `200`: standard Laravel paginator JSON, `data[]` = tasks with the same nested relations as the `POST` response. Nested users are column-restricted (`assignee` = id/name/branch_id, `assigner` = id/name).

### `GET /tasks/{task}`
403 unless the caller is the assignee or has branch access to the task's branch.
Response `200`: `{ "data": { ...task, nested relations as above, "observations": [...] } }`.
**Blind-count fix (overnight-2):** `observations[]` previously serialized the raw model, which included `system_qty_snapshot` for a Staff assignee. It is now removed (key absent) unless the caller is Manager/Owner — verified by `TaskCategoryBrandScopeApiTest::test_task_detail_never_exposes_system_qty_snapshot_to_the_staff_assignee` and live.

### `PATCH /tasks/{task}/status`
Request: `{ "status": "in_progress" | "completed" | "cancelled" }`
Valid transitions (server-enforced): `pending→in_progress` and `in_progress→completed` (assignee only); `pending→cancelled` and `in_progress→cancelled` (Manager/Owner only). Anything else → `422`. A structurally valid transition performed by the wrong actor → `403`.
Response `200`: `{ "data": {...task} }`

---

## Observations (`inventory_observations`)

### `POST /observations`
Permission: `stock-verification.submit` (Staff/Manager/Owner).

Request:
```json
{
  "type": "stock_count",          // or "expiry_check"
  "task_id": null,
  "item_id": 10,
  "branch_id": 1,
  "batch_no": "B1",                // nullable
  "exp_date_observed": null,       // required if type=expiry_check and no batch_no
  "physical_qty": 17,               // required if type=stock_count
  "remarks": null,
  "photo": null,                    // multipart file, image, max 5MB
  "idempotency_key": "uuid",       // required, uuid
  "parent_observation_id": null,    // set on a recount resubmission
  "batch_correction": false,        // new, 2026-10-10 — only meaningful on a recount; see below
  "batch_correction_reason": null   // required string if batch_correction=true
}
```
**Blind counting**: `system_qty_snapshot` is captured server-side but is NEVER present in the response body (verified by test — key absence, not null).
**Idempotency**: a repeat POST with the same `idempotency_key` returns the *existing* row with `200` (not `201`), never creates a duplicate.
**Recount resubmission**: if `parent_observation_id` is given, that row must currently be `status = recount_requested` and share the same `item_id`/`branch_id`, else `422`. **Batch preservation (new, 2026-10-10 business rule):** omitting `batch_no`, or sending the same value as the parent's, keeps the parent's `batch_no` (not a correction). Sending a DIFFERENT `batch_no` is rejected with `422` unless `batch_correction: true` AND a non-empty `batch_correction_reason` are both given — in which case the new row is created with `batch_correction_applied: true`, `original_batch_no` = the parent's batch, and the reason stored (who/when are the row's own `submitted_by`/`created_at`).
This endpoint never calls `StockLedgerService` — zero inventory side effects, always.

Response `201` (new) / `200` (idempotent replay):
```json
{ "data": {
  "id", "type", "task_id", "item_id", "branch_id", "batch_no",
  "exp_date_observed", "physical_qty", "remarks", "photo_path",
  "idempotency_key", "status", "parent_observation_id",
  "batch_correction_applied", "original_batch_no", "batch_correction_reason",
  "approved_qty", "actual_adjustment_qty",
  "submitted_by", "reviewed_by", "review_remarks", "reviewed_at",
  "created_at", "updated_at"
} }
```
(No `system_qty_snapshot` key.)
Errors: `403` permission/branch; `422` validation, bad `parent_observation_id` state/scope, or missing `exp_date_observed` for an unscoped `expiry_check`.

### `GET /observations/mine`
The caller's own submissions (any status), paginated. Same response shape as above (never includes `system_qty_snapshot` or `variance`). **Hard-scoped to `submitted_by = caller`** — no parameter widens it (an injected `submitted_by` is ignored; tested).
*(overnight-2)* Optional filters identical to `GET /observations`: `branch_id`, `date_from`, `date_to` (≥ `date_from`), `type` (`stock_count|expiry_check`), `status` (`pending|approved|rejected|recount_requested`), `per_page` (1–100). Rows now also carry display names `item_name`, `item_code`, `branch_name`, `submitted_by_name`, `reviewed_by_name` (names only). Backs the **Staff** variant of the Flutter History & Audit screen.

### `GET /observations` — cross-staff History & Audit *(new, overnight-1/2)*
Permission: `stock-verification.review` (Manager/Owner) — **`403` for Staff** (they use `/mine`). Every observation (any status, any submitter) in the caller's permitted branch(es): a Manager is scoped to their own branch (an explicit other `branch_id` → `403`); an Owner / null-branch user sees all branches or can pick one.
Query (all optional): `branch_id`, `date_from`, `date_to` (on `created_at`, the submission time; `date_to` must be ≥ `date_from`, else `422`), `type` (Stock Count vs Expiry Check), `status`, `submitted_by` (one staff member — still bounded by branch scope), `per_page` (1–100, default 20), `page`.
Response `200`: Laravel paginator (`current_page`, `last_page`, `per_page`, `total`, `data[]`). Each row = reviewer shape (incl. `system_qty_snapshot`, `variance`) + `item_name`, `item_code`, `branch_name`, `submitted_by_name`, `reviewed_by_name`. Eager-loaded with column-restricted relations (no N+1, no user secrets).

### `GET /observations/pending`
Permission: `stock-verification.review` (Manager/Owner). Branch-scoped automatically; optional `branch_id`, `type`, `task_id`, `sort` (`id`, `-id`, `variance`, `-variance`), `per_page` filters.
Response rows include everything `mine` does **plus** `system_qty_snapshot` and a computed `variance = physical_qty - system_qty_snapshot`. *(overnight-2)* Rows now also include the display names listed above (so the queue no longer shows "Item #id").

### `GET /observations/{observation}`
403 unless caller is the submitter or has branch access. Reviewers get the `system_qty_snapshot`/`variance` shape; submitters get the blind shape. Response includes `parent_observation` and `recounts[]` for the full audit chain.

### `GET /observations/{observation}/photo` *(new, gap-closure follow-up)*
Permission: `stock-verification.review` (Manager/Owner) + branch access — same guard as `approve`/`reject`. Streams the raw uploaded evidence photo file (`image/*`) from the `public` disk. `404` if the observation has no `photo_path` or the file is missing; `403` if the caller isn't an authorised reviewer for that observation's branch; `401` unauthenticated. Not exposed to the submitting Staff member and not reachable via a public `storage:link` URL.

### `POST /observations/{observation}/approve`
Permission: `stock-verification.review` + branch access. **Branches on `observation.type` (business rule, 2026-10-10):**

**`type = 'stock_count'`** — Request: `{ "approved_qty": 17, "remarks": "ok" }`
1. Row-locks the observation; if not `pending`, returns `409` with **no** ledger call.
2. Row-locks `item_stocks` for the item+branch and re-reads current stock fresh (batch-aware via `BatchStockService` when `batch_no` is set).
3. `actual_adjustment_qty = approved_qty − current_stock_at_approval_time` (NOT the stale `system_qty_snapshot`).
4. If the delta is non-zero, calls `StockLedgerService::post()` exactly once (`movementType = 'CORRECTION'`, `referenceType = 'InventoryObservation'`, `referenceId = observation.id`). If it throws (e.g. would go negative without `allow_negative_stock`), the whole transaction rolls back — observation stays `pending`, response `422`.
5. On success: `status='approved'`, `approved_qty`, `actual_adjustment_qty`, `stock_ledger_id`, `reviewed_by`, `reviewed_at`.

**`type = 'expiry_check'`** — Request: `{ "remarks": "ok" }` (no `approved_qty` field — if sent, it is silently ignored, never read). **Never calls `StockLedgerService`; never touches `item_stocks`.** Only sets `status='approved'`, `reviewed_by`, `reviewed_at`, `review_remarks`. `approved_qty`/`actual_adjustment_qty`/`stock_ledger_id` stay `null`. Quantity corrections are exclusively a `stock_count`-approval concern.

Response `200`: reviewer shape (includes `system_qty_snapshot`/`variance`). Errors: `403`, `409` (not pending), `422` (ledger post failed / rolled back — `stock_count` only).

### `POST /observations/{observation}/request-recount`
Permission: `stock-verification.review`. Request: `{ "remarks": "required string" }`. Sets `status='recount_requested'` on the *original* row — never touches inventory, never modifies the row's `physical_qty`. The actual resubmission is a normal `POST /observations` with `parent_observation_id` set.
`409` if the row isn't currently `pending`.

### `POST /observations/{observation}/reject`
Permission: `stock-verification.review`. Request: `{ "remarks": "required string" }`. Sets `status='rejected'` — terminal, no inventory change. `409` if the row isn't currently `pending` (including on a second reject/approve/recount call against an already-terminal row).

---

## Barcode mapping (`barcode_mapping_requests`)

### `POST /barcode-mapping-requests`
Permission: `barcode-mapping.request` (all roles). Request: `{ "scanned_barcode", "suggested_item_id": nullable, "branch_id", "remarks": nullable }`. Creates a `pending` row only — never touches `items.ean_upc_code`.

### `GET /barcode-mapping-requests`
Permission: `barcode-mapping.review` (Manager/Owner). Branch-scoped; filters `status`, `branch_id`, `per_page`.

### `POST /barcode-mapping-requests/{mappingRequest}/approve`
Permission: `barcode-mapping.review`. Request: `{ "item_id": nullable (defaults to suggested_item_id), "force": nullable bool, "remarks": nullable }`.
If the target item already has a **different**, non-empty `ean_upc_code`, this is rejected with `422` unless `force: true` is passed — the existing mapping is never silently overwritten. On success, sets `items.ean_upc_code = scanned_barcode` and the request `status='approved'`. `409` if the request isn't `pending`.

### `POST /barcode-mapping-requests/{mappingRequest}/reject`
Permission: `barcode-mapping.review`. Request: `{ "remarks": "required string" }`. `status='rejected'`, no item change. `409` if not `pending`.

---

## Dashboard

### `GET /dashboard/summary`
Middleware: `api.branch.access` (only restricts when a `branch_id` query param is given, same as `/items/{item}/batches`). No extra permission for the Staff path; the Manager/Owner path is gated inline on `stock-verification.review` (`403` if missing — in practice Manager/Owner always have it per the seeder). Role-aware: the shape of `data` differs by caller role.

Query: `branch_id` (optional, `integer`, `exists:branches,id`). For Manager (branch-restricted), omitting it defaults to their own branch; passing a branch they don't have access to is `403`. For Owner (all-branch access), omitting it aggregates across **all** branches; passing one scopes to just that branch.

**Staff / caller without Manager/Owner role** — personal summary only. **Never contains any system-quantity or variance field anywhere in the payload** (master prompt §6: blind counting applies to dashboards too, not just the count-entry screen — verified by test, including a raw-body `assertDontSee`).

Response `200`:
```json
{ "data": {
  "role": "staff",
  "branch_id": 1,
  "tasks": { "pending": 2, "in_progress": 1, "completed": 5 },
  "observations": {
    "period_days": 30,
    "pending": 1, "approved": 3, "rejected": 0, "recount_requested": 0
  }
} }
```

**Manager/Owner** — branch-wide operational summary for the caller's accessible branch(es).

Response `200`:
```json
{ "data": {
  "role": "manager",
  "branch_id": 1,
  "branch_ids": [1],
  "pending_review_count": 4,
  "reviewed_today": { "approved": 2, "rejected": 0 },
  "reviewed_this_week": { "approved": 9, "rejected": 1 },
  "tasks": { "pending": 2, "in_progress": 1, "completed": 5, "cancelled": 0 },
  "pending_barcode_mapping_requests": 1,
  "expiry_buckets": [
    {
      "label": "Expiring Soon", "min_days": 0, "max_days": 30, "severity": "critical", "sort_order": 1,
      "count": 1, "has_more": false,
      "items": [
        { "item_id": 10, "item_name": "Cat Food 500g", "branch_id": 1, "batch_no": "BATCH-SOON",
          "exp_date": "2026-10-20", "days_until_expiry": 10, "is_expired": false, "remaining_qty": 20.0 }
      ]
    }
  ]
} }
```
`branch_id` is `null` when an Owner aggregates across all branches (`branch_ids` then lists every branch id included). `pending_barcode_mapping_requests` is `null` if the caller lacks `barcode-mapping.review`. `expiry_buckets` items are capped at 10 per bucket (`has_more: true` if the bucket's real `count` exceeds that); items already past their expiry date are clamped into the bucket whose `min_days` is 0 and flagged `is_expired: true` rather than dropped. "Today"/"this week" use the observation's `reviewed_at` timestamp (the moment of the approve/reject decision), with "this week" starting Monday 00:00 (Carbon's default week start).

Errors: `403` — no `stock-verification.review` permission, or requested a `branch_id` the caller doesn't have access to; `422` — invalid `branch_id`.

---

## AdminLTE web routes (desktop, session-guarded — not part of `/api/v1`)

Added by the business-rules session under the existing Inventory menu (`config/adminlte.php`), gated by the web `auth`/`verified` session guard + the same `stock-verification.review` permission (checked via `$user->hasPermissionTo($perm, 'web')`, same as the API). `StockVerificationController` (`app/Http/Controllers/Inventory/StockVerificationController.php`) is a thin delegate — it calls the exact same `Api\V1\ObservationController` methods for every action, so there is one authorization/branch-scoping/ledger-posting code path, not two (proven by `StockVerificationAdminLteTest::test_mobile_submission_approved_via_web_screen_posts_the_same_ledger_correction_as_the_api`).

- `GET /inventory/stock-verification/review-queue` — Manager Review Queue. Same filters as `GET /v1/observations/pending` (branch, type). Forbidden (`403`) for Staff.
- `GET /inventory/stock-verification/history` — History & Audit (desktop). Same filters as `GET /v1/observations` (branch, date range, type, status).
- `GET /inventory/stock-verification/{observation}` — observation detail: full data incl. recount chain, batch-correction audit fields, and an inline evidence-photo viewer.
- `GET /inventory/stock-verification/{observation}/photo` — streams the evidence photo (same authorization as the API's `/photo` endpoint).
- `POST /inventory/stock-verification/{observation}/approve` — same type-branching rule as the API (`stock_count` posts a ledger correction; `expiry_check` never touches stock).
- `POST /inventory/stock-verification/{observation}/request-recount`
- `POST /inventory/stock-verification/{observation}/reject`

Menu path: **Inventory → Stock & Expiry Verification → Manager Review Queue / History & Audit**.

---

## Common error shapes

- `401` — no/invalid bearer token.
- `403` — `{ "message": "..." }` — missing permission or no branch access.
- `409` — `{ "message": "This observation/mapping request is no longer pending." }` — duplicate-action protection.
- `422` — Laravel's standard validation error shape (`{ "message", "errors": {...} }`) for both field validation and the business-rule rejections described above (invalid status transition, bad recount scope, ledger-post rollback, unforced barcode remap).
