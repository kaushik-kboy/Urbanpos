# PRE-PRODUCTION UI & WORKFLOW CLEANUP AUDIT

## 1. Baseline & Environment Information
- **Current Git SHA**: `91740af00a50563d6dd7e022bd54a45f7124a198`
- **Certified Baseline**: Phase 5 Business Certification (`e8d64ad24ff...`)
- **Active Branch**: `feature/ui-modernization-and-enhancements`
- **Verification Environment**: PHP 8.3.13, Laravel 11, MySQL 8.0, Playwright / Headless Chrome, Vitest 5.0.1.

---

## 2. Incomplete Tasks & Gaps Identified

| # | Task / Module | Previous State / Defect | Root Cause | Cleaned / Fixed State |
|---|---|---|---|---|
| 1 | **Global Active Branch** | Visible readonly branch input repeated inside 9 transaction forms. | Forms hardcoded `<input name="branch_id" class="form-control" readonly>` instead of trusting global active branch header. | Replaced with `<input type="hidden" name="branch_id" value="...">`. Form state is preserved; visual duplication removed. |
| 2 | **Dashboard Quick Search** | Quick search input was rendered both in top command palette (`Ctrl+K`) and inside dashboard content card. | Redundant search widget left in `home.blade.php`. | Removed redundant input in `home.blade.php`; authoritative Command Palette retained. |
| 3 | **Sales Bill Reset Controls** | Reset Table button and Reset Form button appeared both above and below item table. | Both buttons were defined above table and below in footer. | Kept single `#sb-btn-reset-table` above table; kept single `#btn-reset-form` in footer. |
| 4 | **Sales Quotation & Order Reset Form** | Reset Form button was missing from create views. | Action bar in footer only had Cancel and Save. | Added `#btn-reset-form` with shared reset handler and keyboard safety. |
| 5 | **Sales Return Date False Validation** | Selecting customer caused "Please enter a valid date" error to trigger immediately even though Return Date was valid. | Bootstrap 4 `.d-block` on `.invalid-feedback` overrode `.hide()`, and customer change triggered entire form header validation. | Removed `.d-block`, scoped customer change to only validate customer ID, and fixed display toggling. |
| 6 | **Item Code vs DB ID Display** | Selecting item in search popup populated internal DB ID (e.g. `23`) into the Code field instead of Item Code. | Callback logic fell back to `item.id` or assigned `item.id` directly to `.item-code-input`. | Standardized globally: `item.item_code || item.code || item.barcode || ''`. |
| 7 | **Quotation & Order Item -> Qty Flow** | Selecting item did not focus Qty directly, and empty/zero Qty allowed tabbing away. | Modal select event did not chain focus to `.item-qty`, and keydown was not blocking. | Chained focus directly to `.sq-qty` / `.so-qty`, blocked Tab/Enter on `<= 0` with inline error. |
| 8 | **Purchase Invoice Supplier Flow** | Selecting supplier immediately showed "Supplier Invoice Number is required". | Supplier `change` handler prematurely triggered `validatePinvHeader(true)`. | Changed supplier handler to only validate `supplier_inv_no` if already touched/filled. |
| 9 | **Purchase Invoice Expiry & Qty** | Expiry date comparison compared DD-MM-YYYY strings to YYYY-MM-DD, causing valid dates to be marked expired. Alert used. | Date string format mismatch (`'29-09-2026' < '2026-09-29'`). | Added `parseToYmd()`, exact mathematical date comparison, inline feedback, and Tab/Enter blocking. |
| 10 | **Purchase Return Over-Qty** | Entering qty > eligible return qty popped up browser `alert()`. | Native `alert()` used in row calculation handler. | Replaced with inline `.pr-qty-error-msg` and focus retention. |
| 11 | **Delivery Note Table Width** | Ordered Qty, Dispatched Qty, MRP, Unit Price took too much width; Expiry Date was clipped. | Unbounded column sizing on numeric inputs without explicit min-widths. | Structured column widths (Qty 85-95px, Price 95px, Expiry min-width 145px). |
| 12 | **Stock Transfer Mutual Exclusion** | From Branch and To Branch could select the same branch; used `alert()`. | Options were not mutually excluded dynamically. | Dynamic rebuild of dropdowns excluding opposite selection; backend `different` rule; inline error banner. |
| 13 | **Purchase Detail Report** | Invoices with multiple items appeared on multiple rows. | Controller and view iterated over `$invoice->items` in the main report table. | Aggregated to 1 row per invoice on web and CSV; added 16-column Excel export. |
| 14 | **No Alert Audit** | Browser `alert()` used for business validation in multiple inventory and finance forms. | Developers used quick alerts instead of inline feedback. | Replaced with inline `.invalid-feedback`, `.is-invalid`, and non-blocking toastr. |

---

## 3. Affected Shared Components & Templates
- `resources/views/sales/sales-bills/_form.blade.php`
- `resources/views/sales/sales-returns/_form.blade.php`
- `resources/views/sales/sales-quotations/_form.blade.php` & `create.blade.php`
- `resources/views/sales/sales-orders/_form.blade.php` & `create.blade.php`
- `resources/views/sales/delivery-notes/create.blade.php`
- `resources/views/purchase/purchase-invoices/_form.blade.php` & `_item-row.blade.php`
- `resources/views/purchase/purchase-returns/_form.blade.php`
- `resources/views/purchase/purchase-orders/_form.blade.php`
- `resources/views/purchase/receipt-notes/create.blade.php`
- `resources/views/inventory/stock-transfers/_form.blade.php`
- `resources/views/inventory/damage-stocks/_form.blade.php`
- `resources/views/inventory/opening-stocks/_form.blade.php`
- `resources/views/finance/settlements/create.blade.php`
- `resources/views/reports/purchase-detail.blade.php`
- `app/Http/Controllers/Reports/ReportController.php`
- `app/Http/Controllers/Inventory/StockTransferController.php`
- `app/Exports/PurchaseDetailExport.php`
