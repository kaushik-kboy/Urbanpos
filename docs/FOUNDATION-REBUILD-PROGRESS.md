# Urban Pets POS — Foundation Rebuild: Progress Report (Phases 1–7)

**Source specs**: `Urban_Pets_POS_Foundation_Signoff_Report.pdf` (formal 32-page foundation spec) and `POS.docx` (supporting rationale with worked examples and phased build order). Both documents make the same core claim: every posted transaction must flow through five engines — **Stock Ledger, Cost/Valuation, Tax/GST, Accounting, Document/Posting** — atomically, before any more screens/CRM/AI get built. This report tracks what has actually been delivered against that requirement, phase by phase, plus what remains open.

**Status as of 2026-09-18**: Phases 1–7 complete and verified, plus two follow-up passes (User & Role Management, Permission Gating). 53 automated tests passing. All work verified against the real dev database (`urban_pos`) with zero data loss — every test run inside `DatabaseTransactions`, confirmed via direct DB inspection before and after each phase.

---

## Starting Point — What the Codebase Audit Found

Before Phase 1, a full audit of the existing Laravel 11 app found it violated nearly every foundation principle in the spec:

1. **No stock ledger.** `ItemStock::adjust()` was a bare `increment()` called from ~10 controllers directly — `item_stocks` was a mutable running-total cache, not an append-only log.
2. **Cost lived in 3 unsynced places** (`Item.cost_price`, `ItemStock.cost_price`, transaction-line snapshots), each with a different overwrite rule. No weighted-average was ever computed.
3. **No COGS was ever stored** — gross profit could not be reconstructed from stored data.
4. **GST calculation was copy-pasted 5×** across controllers, trusted the client-submitted tax rate instead of the server-side master, and had no CGST/SGST/IGST split despite Local/Interstate fields existing.
5. **Accounting posting was duplicated two ways** (manual vouchers vs. auto-posted documents), with a racy `MAX(id)+1` numbering scheme and a delete-and-recreate reversal pattern that destroyed journal history on every edit.
6. **A real shipping bug**: `StockUpdateController` posted stock changes unconditionally, regardless of approval status — the "no stock effect until approved" workflow didn't actually exist.
7. **Roles/permissions were 100% absent** — no model, table, or package; only two cosmetic label arrays in a placeholder page. No `users.branch_id` either.
8. **Stock Transfer — a mandatory V1 module — didn't exist**, only a dead-end placeholder page.

---

## Phase 1 — Foundation Engines

**Goal**: Build the five engines as new, additive services and retrofit the six real (non-stub) transaction controllers onto them, without breaking anything already working.

### Built
- **`stock_ledger` table** — append-only movement log (item, branch, movement type, qty in/out, unit cost, running balance, reference to source document, reversal linkage).
- **`StockLedgerService`** — the single writer for all stock movement. Computes moving-weighted-average cost on every incoming movement; outgoing movements always release at the current average. `reverse()` posts an equal-and-opposite row instead of deleting history.
- **`TaxEngine`** — replaces 5 duplicated tax calculations. Re-derives the GST rate **server-side** from the item's master record (closes the client-trusted-rate gap) and correctly handles tax-inclusive pricing (a bug that existed silently before).
- **`DocumentNumberingService`** — replaces the racy `MAX(id)+1` pattern with a locked per-series counter.
- **`HasPostingLifecycle` trait** — Draft/Posted/Cancelled state machine with an `assertEditable()` guard, applied to all six document models.
- **Retrofitted**: Purchase Invoice, Sales Bill, Sales Return, Damage Stock, Opening Stock, Stock Update — all now post through the ledger/tax engines. `sales_bill_items`/`sales_return_items` gained a `cost_at_sale` column, finally making gross-profit reporting possible.
- **Fixed the real approval-bypass bug** in `StockUpdateController`/`StockUpdateApprovalController` — stock now only posts once approved.
- **Golden Test** — an automated test encoding the spec's own worked dataset (opening → purchase → sale → return → damage → purchase return → MRP change), asserting every number reconciles exactly.

### Bugs found and fixed along the way
- `$line['exp_date'] ?: null` would fatal if the key was ever fully absent from a request — fixed to `??` in 4 controllers.

---

## Phase 2 — Repack/Kit Operations + CGST/SGST/IGST Split

**Goal**: Finish migrating every remaining stock-mutating code path off the legacy `ItemStock::adjust()`, and add the tax split the spec requires.

### Built
- Added `REPACK`, `KIT_ASSEMBLY`, `KIT_DISASSEMBLY` movement types.
- Retrofitted `InventoryMoreController`'s repack/kit-assembly/kit-disassembly actions onto `StockLedgerService`, with **value-conservation cost logic**: the outgoing side releases at its current average, and the incoming side(s) are valued from the *actual value that left* — never their own (possibly zero) average. Verified explicitly with a never-before-stocked item to prove it doesn't default to cost ₹0.
- **`ItemStock::adjust()` now has zero live callers left in the app.**
- `TaxEngine` gained an `isInterstate` parameter: Local supplies split 50/50 into CGST+SGST (odd-cent remainder assigned to SGST so the two always sum exactly); Interstate supplies go 100% to IGST. Wired into Purchase Invoice, Sales Bill, and Sales Return (the only three documents with a Local/Interstate concept).

### Flagged, not fixed (scope boundary)
- Five bulk-import commands (`ImportAllMasters`, `ImportClosingStock`, `ImportOpeningStock`, `ImportStockUpdates`, `ImportWastageReport`) write stock/documents directly, bypassing the ledger entirely — worse than the old generic-tag problem, since there's no ledger row at all. This is GoFrugal-migration tooling with a different risk profile (destructive delete-and-reimport on real data files) and needs a product decision before it can be fixed.

---

## Phase 3 — Stock Transfer Module

**Goal**: Replace the dead placeholder with a real dispatch → receive workflow — a mandatory V1 module per the spec.

### Built
- **`stock_transfers` + `stock_transfer_items`** tables and a full `StockTransferController` (dispatch, pending-receipt queue, receive, cancel) following the same architectural shape as the other real controllers.
- **Dispatch** posts `TRANSFER_OUT` immediately and checks source-branch stock availability (a genuinely new validation — no prior document checked "can this branch afford to send this").
- **Receive** posts `TRANSFER_IN` at the **dispatch-time cost snapshot**, not the destination's own average — verified with a destination that's never stocked the item before.
- **Short receipt** supported: dispatched vs. received quantities both stored transparently; no invented ledger entries for the shortfall ("lost in transit" is visible, not hidden).
- **Cancel** (only before receipt) fully reverses the dispatch via the existing `reverseByReference()`.
- Same-state transfers get zero tax (pure stock movement, matching spec exactly); different-state transfers compute and store a full CGST/SGST-vs-IGST split — but **no accounting journal entry is auto-posted** for the "distinct-person supply" question, since the spec itself says this needs CA validation and inventing a treatment would be worse than leaving it flagged.
- Real AdminLTE UI (index/create/show/pending-receipt/receive), reusing the existing item-picker JS pattern. Menu entries "Transfer Out"/"Transfer In" repointed to the real pages; the two genuinely-unbuilt GoFrugal features ("Transfer Out Approval & Auto TI", "Transfer In Touch") were left as stubs rather than silently repointed to look finished.

---

## Phase 4 — Control Foundation (Roles, Permissions, Audit, Branch Identity)

**Goal**: Close the security/accountability gap — who can do what, from which branch, and what's on record when something sensitive happens.

### Built
- **`spatie/laravel-permission` v6.25** installed (a well-tested package, not hand-rolled, for security-sensitive code).
- **`users.branch_id`** (nullable — null means all-branch access, e.g. Owner; non-null restricts to one branch).
- **Three seeded roles** matching the spec's own vocabulary:
  | Role | Permissions | Scope |
  |---|---|---|
  | **Owner** | All 28 | Everything, including approval authority and financial-master edits |
  | **Manager** | 21 | Every day-to-day operational module (purchase, sales, damage, opening stock, transfers) |
  | **Cashier** | 2 | Sales Bill + Sales Return creation only |
- **Route-level enforcement**: `permission:` + `branch.access` middleware on the store/update/destroy/approve/cancel/receive actions of 11 sensitive modules (index/create-form/show/edit-form stay open to any logged-in user). A new `EnsureBranchAccess` middleware blocks a branch-scoped user from acting on another branch's documents; null-branch users bypass it.
- **Audit trail**: new `audit_logs` table + `AuditLogger` service, wired into 8 specific spec-named sensitive actions (invoice/bill/return cancellation, stock-update approve/reject, transfer cancellation, GST rate edits, item price changes, voucher deletion).
- **Branch-identity UI**: session set on login; a real navbar branch-switcher for Owner-type users (built via AdminLTE's `BuildingMenu` event — a proper dropdown, not a fake widget); a static branch-name display for scoped users; existing "default branch" fallback logic across 3 controllers now prefers the session value.

### Bug found and fixed along the way
- **A Phase 1 bug, discovered only now**: `destroy()` on 5 controllers called the same `assertEditable()` guard as `update()`, which blocks anything with status `Posted` — but every document defaults to `Posted` immediately on creation. This meant **cancellation had been unreachable since Phase 1** for any normally-created document (an audit-log test writing to `destroy()` is what surfaced it). Fixed by removing the guard from `destroy()` (which already correctly implements reversal-based cancellation) while keeping it on `update()`, where it correctly belongs.

---

## Post-Phase-4 — User & Role Management Screen

**Goal**: Close the gap flagged above — an Owner could not onboard staff or assign roles/branches without `tinker`.

### Built
- **`users` permission module** (`create`/`edit`/`cancel`) — seeded **Owner-only**, deliberately excluded from Manager's permission set. A Manager granting roles could otherwise assign themselves Owner and self-escalate privileges.
- **`Master\UserController`** — standard CRUD matching the house pattern (`BranchController`), with a single-role picker (this app's three roles are exclusive tiers by design, not a multi-select) and a branch picker (nullable — blank means all-branch/Owner-level access).
- **Password handling**: relies on the existing `'hashed'` Eloquent cast, never calls `Hash::make()` directly (would double-hash); a blank password on edit means "keep current," not "clear it."
- **Two safety guards on delete**: an Owner cannot delete their own account, and the system will not allow the last remaining Owner to be deleted (defense-in-depth — unreachable through the current permission model itself, since deleting requires being an Owner and any Owner deleting another always leaves themselves behind, but kept cheap insurance against future permission changes).
- **Menu**: "Role Master" now points at the real screen instead of a fake stub page.

### Bug found and fixed along the way
- The first draft of the "last remaining Owner" test revealed the guard's condition needs the *target's* role count, not the acting user's — an easy mix-up in this kind of self-referential guard. Fixed before landing, verified with a controller-level test since the scenario is unreachable via a live route.

---

## Post-Phase-4 — Permission Gating for the Remaining ~35 Controllers

**Goal**: Close the security gap flagged above — every Master CRUD screen, Purchase Orders, Finance Ledgers, and several Inventory "More Operations" actions were open to *any* authenticated user, including a Cashier.

### Built
- 19 new permission modules added to the existing `RolesAndPermissionsSeeder` pattern, tiered exactly like the original 11: **operational** (Manager+Owner — all reference-data CRUD, Purchase Orders, repack/kit) vs. **Owner-only** (Branches, Ledgers, GST Taxes widened to full CRUD, Price Fixing, Change Selling).
- **`items` split by action, not a single tier**: creating a new product is Manager-level (routine setup), but editing/cancelling an existing one is Owner-only — because editing was found to also change price fields.
- **Route wiring**: the Master-resource loop now runs every resource through the existing `$gatedResource` helper instead of a plain open `Route::resource`, so the pattern didn't need reinventing.

### Bug found and fixed along the way
- **`ItemController::update()` let *any* authenticated user — including Cashier — directly edit `cost_price`, `sell_price`, `mrp`, and `gst_tax_id`**, completely bypassing the Owner-only `ItemPriceChangeController` built specifically to gate price changes. `PriceFixingController::apply()` and `ChangeSellingController::update()` had the identical gap. All three closed in this pass.

---

## Phase 5 — Quick Fixes + Credit Limit Enforcement

**Goal**: Clear three small, independent items flagged during the permission-gating pass.

### Built
- **`ItemPriceChangeController`**'s multi-branch price-update loop wrapped in `DB::transaction()` — a failure partway through no longer leaves some branches repriced and others not.
- **`LedgerController::destroy()`** now checks for existing journal history before deleting, returning a clean redirect instead of a raw uncaught `QueryException`.
- **`CreditLimitGuard`** — blocks a new credit sale/purchase that would push a Customer/Supplier's outstanding balance past their `credit_limit`. Uses the party's **live** `Ledger::balance()`, not the `credit_balance` column — that column was found to be pure dead data, validated on the form but never updated by any sale or purchase anywhere in the app.

---

## Phase 6 — Financial Year Lock

**Goal**: Build spec §15.4's "closed financial periods should be lockable" control, which had never been started.

### Built
- **`financial_years` table** + Owner-only Master screen with Lock/Reopen actions (reopening is audit-logged).
- **`FinancialYearGuard`**, wired into all 7 document controllers (Purchase Invoice, Sales Bill, Sales Return, Damage Stock, Opening Stock, Stock Update, Stock Transfer) — blocks posting a document dated outside any defined year, or into a locked one.
- **Deliberately fails open when zero Financial Years are defined anywhere** — since this is a brand-new, opt-in screen and every pre-existing branch/document predates it, the guard doesn't retroactively restrict anything until an Owner actually defines a year. There's no separate "backdated posting" permission either — posting into an already-closed period requires that period to be explicitly reopened first (an Owner-only, audited action), which itself satisfies the spec's "requires permission" language without a second control to build.

---

## Phase 7 — Settlement / Till / EOD Engine

**Goal**: Build the core V1 module the spec (§13 Settlement/Payment Engine, §14 Till/EOD Foundation) names but that had never been started — comparable in scope to Phase 1.

### Built
- **Till sessions**: open (register + opening cash, blocks a second open session on the same register), cash in/out tracking, close (computes expected cash from opening + cash-tender sales + cash-in − cash-out, accepts actual cash, stores variance).
- **Split-tender payments on Sales Bills**: a bill can now optionally carry a `payments[]` array (tender type + amount) instead of a single free-text `payment_type` string.
- **`LedgerPostingService` now actually answers "which ledger does a cash sale hit?"** — previously, *every* sale (even a walk-in cash sale) debited the customer's ledger regardless of how it was paid. A bill with recorded payments now splits the debit across each tender's resolved ledger (Cash/Bank); a "Credit" tender still debits the customer, same as before.
- **EOD report**: sales/returns/discounts/GST/payment-totals-by-tender/till-variance for a branch+date range or a single till session.
- **Fully additive**: a request that doesn't send `payments[]` — every one of the 46 pre-Phase-7 tests, and any bill created the old way — posts byte-for-byte identically to before this phase. Verified by the full suite staying green *unchanged* and a live end-to-end smoke run (open till → split-payment sale → close till → EOD report) via `tinker`, then rolled back.
- **Till access reaches Cashier** — the one new module a Cashier gets beyond `sales-bills.create`/`sales-returns.create`, since a cashier opens/closes their own shift in real retail.

---

## What's Still Pending

### Flagged, deliberately not fixed
- The 5 bulk-import commands bypassing the ledger (Phase 2) — needs a product decision on whether they may run again post-go-live.
- Emergency-override workflow (supervisor PIN capture) — neither source spec defines exactly what it should override; building a security-relevant bypass without a defined target was judged worse than leaving it flagged.
- Stock Transfer's distinct-person GST is computed and stored, but no accounting entry is auto-posted — still needs CA validation per the original Phase 3 flag.
- `SupplierController`/`CustomerController`'s `credit_limit`/`credit_balance`/`credit_days` fields remain editable by Manager alongside the rest of that master data (tightening this specifically would need a field-level split, a different task from the credit-limit *enforcement* built in Phase 5).

### Never started
- The 8 pure-stub modules confirmed to have zero backing table or model: Sales Quotation/Order/Delivery Note, Purchase Receipt Note/PO Cancel/Indent, Kit Mapping/Loyalty/Promotion. Deliberately excluded from the Phase 5–7 plan — building these is 7–8 entirely new modules, not "finishing the foundation," and was scoped out as a separate future initiative rather than silently folded in.
- The entire Tools module (still the original static stub, unchanged since before Phase 1).

---

## Verification Summary

| Phase | New tests | What they prove |
|---|---|---|
| 1 | 7 | Golden dataset reconciles exactly; reversal restores stock without deleting history; tax engine matches spec's worked examples; document numbering is race-free |
| 2 | 3 | Repack/kit operations conserve total inventory value exactly (including a never-stocked item); GST split is correct including odd-cent rounding |
| 3 | 6 | Dispatch/receive/cancel behave correctly including cost conservation and short receipt; views render without errors |
| 4 | 6 | Permission gates actually block (not just hide) unauthorized actions; branch scoping works both ways (blocked and Owner-bypass); audit logs capture the right old/new values |
| User Mgmt | 6 | Permission gate blocks Manager from creating users; password is correctly hashed (not double); blank password on edit preserves the original; self-delete and last-Owner guards both hold |
| Permission Gating | 5 | Cashier blocked from a master resource and from editing item prices; Manager can create an item but not re-price one; Manager can manage non-price master data; Owner-only actions correctly blocked for Manager |
| 5 | 7 | Credit limit blocks/allows correctly using the live ledger balance, not the dead column; price-change loop rolls back fully on failure; ledger delete no longer 500s |
| 6 | 6 | Posting unrestricted with zero Financial Years defined; blocked outside any defined year; backdated-but-open posting unaffected; locked year blocks then succeeds after an audited reopen; Manager blocked from managing years |
| 7 | 7 | Till variance computes correctly; double-open and double-close are blocked; old-style sales post identically to before; split-payment sale debits the correct ledgers per tender; payment mismatch blocks the sale; EOD aggregates match a hand-built scenario |
| **Total** | **53** | All passing, run against the real dev DB inside transactions, zero data loss confirmed by direct inspection after every phase |
