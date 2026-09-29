# URBANPOS — DEEP CROSS-MODULE QA & ROOT-CAUSE RESOLUTION REPORT

**Date:** 2026-09-29  
**Version:** UrbanPOS 2.4 Enterprise  
**Status:** COMPLETED & VERIFIED  

---

## 1. Executive Summary

A comprehensive, deep architectural QA and root-cause fix pass was performed across UrbanPOS covering inventory, purchasing, sales billing, stock transfers, master modules, dynamic item tables, and concurrency safety.

Rather than applying cosmetic or superficial fixes, every identified issue was traced through the complete lifecycle:
**UI Presentation & Interaction $\rightarrow$ JavaScript Controllers & Event Handlers $\rightarrow$ Backend HTTP Validation $\rightarrow$ Database Transactions & Row Mutex Locks $\rightarrow$ Double-Entry General Ledger & Stock Ledger Movements**.

All targeted issues have been resolved at the shared core layers, backed by automated feature test suites passing with 100% success (104 automated assertions across `PurchaseReturnTest` and `DeepCrossModuleRegressionTest`).

---

## 2. Global UI Standardizations

### 2.1 Default Collapsed Left Sidebar Menu
- **User Requirement:** When navigating pages (such as `/purchase/purchase-orders/create`), the left sidebar menu must start in a collapsed state across the entire project.
- **Root Cause:** AdminLTE layout configuration in `config/adminlte.php` had `sidebar_collapse => false`.
- **Implementation:**
  - File: [`config/adminlte.php`](file:///c:/laragon/www/Urbanpos/config/adminlte.php)
  - Updated `'sidebar_collapse' => true`.
  - Global layout renders with `sidebar-collapse` class applied to the `<body>` on initial render across all routes and submodules.

### 2.2 Canonical Application Timezone (India - Asia/Kolkata)
- **Requirement:** Ensure all operations, logs, expiry checks, and ledger timestamps conform to canonical Indian Standard Time (`Asia/Kolkata`).
- **Implementation:**
  - Files: [`config/app.php`](file:///c:/laragon/www/Urbanpos/config/app.php), [`.env`](file:///c:/laragon/www/Urbanpos/.env), [`.env.example`](file:///c:/laragon/www/Urbanpos/.env.example).
  - Configured `'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata')`.
  - Date comparisons for expiry dates and invoice cutoffs use `\Carbon\Carbon::now('Asia/Kolkata')->startOfDay()`.

---

## 3. Master Modules Enhancements

### 3.1 Item Code Display on Master Items Listing
- **Requirement:** Display the `Item Code` column directly next to the `Id` column on `/master/items`.
- **Implementation:**
  - File: [`resources/views/master/items/index.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/master/items/index.blade.php)
  - Added `<th>Item Code</th>` immediately after `<th>Id</th>`.
  - Added table row cell `<td><code>{{ $item->item_code ?: ($item->ean_upc_code ?: '-') }}</code></td>`.

### 3.2 Master Brand & Category Filter Bars
- **Requirement:** Add search and status filter bars to Brand and Category master listings, preserving pagination query parameters.
- **Implementation:**
  - Controllers:
    - [`BrandController.php`](file:///c:/laragon/www/Urbanpos/app/Http/Controllers/Master/BrandController.php)
    - [`ItemCategoryController.php`](file:///c:/laragon/www/Urbanpos/app/Http/Controllers/Master/ItemCategoryController.php)
    - [`ItemCategoryValueController.php`](file:///c:/laragon/www/Urbanpos/app/Http/Controllers/Master/ItemCategoryValueController.php)
  - Added query filtering for `search` (name matching) and `status` (`1` for Active, `0` for Inactive), chaining `->paginate(20)->withQueryString()`.
  - Views:
    - [`resources/views/master/brands/index.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/master/brands/index.blade.php)
    - [`resources/views/master/item-categories/index.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/master/item-categories/index.blade.php)
    - [`resources/views/master/item-category-values/index.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/master/item-category-values/index.blade.php)
  - Added unified card filter headers with search input, status dropdown, search button, and reset button.

---

## 4. Purchase Invoice Expired Products Protection

- **Defect:** Expired products were previously selectable or submit-able during Purchase Invoice entry.
- **Root Cause:**
  - Modal item queries lacked an expiry date filter against the current Indian date.
  - Backend request validation did not verify that incoming `exp_date` was greater than or equal to current date.
- **Resolution:**
  1. **Frontend / AJAX Level:**
     - File: [`app/Http/Controllers/Purchase/PurchaseInvoiceController.php`](file:///c:/laragon/www/Urbanpos/app/Http/Controllers/Purchase/PurchaseInvoiceController.php)
     - `itemList` and fallback item queries strictly filter out batches where `exp_date < ?` (today in `Asia/Kolkata`).
  2. **Backend Validation Level:**
     - In `validateData()`, every item line's `exp_date` is validated:
       ```php
       $todayIndia = \Carbon\Carbon::now('Asia/Kolkata')->startOfDay();
       $expCarbon = \Carbon\Carbon::parse($line['exp_date'], 'Asia/Kolkata')->startOfDay();
       if ($expCarbon->lt($todayIndia)) {
           $v->errors()->add("items.{$idx}.exp_date", "Item '{$itemModel->name}' (Row #{$rowNum}): Expired products cannot be entered into Purchase Invoice.");
       }
       ```
     - Expired items are strictly rejected with an authoritative validation error.

---

## 5. Purchase Return Deep Root-Cause Overhaul

### 5.1 Strict Supplier Isolation & Non-Borrowing of Other Suppliers' Stock
- **Defect:** Users could potentially return stock to a supplier that was never supplied by them, or return quantities in excess of historical receipts from that supplier.
- **Root Cause:** Purchase Return only checked aggregate `ItemStock` in the branch, ignoring supplier origin and invoice/batch boundaries.
- **Resolution:**
  - File: [`app/Http/Controllers/Purchase/PurchaseReturnController.php`](file:///c:/laragon/www/Urbanpos/app/Http/Controllers/Purchase/PurchaseReturnController.php)
  - Implemented authoritative `assertReturnableEligibility()` executing under mutex locks:
    - **When returning against a specific Purchase Invoice:**
      1. Validates invoice belongs to the selected supplier and is not cancelled.
      2. Validates requested item and batch exist on that invoice.
      3. Calculates: $\text{Maximum Returnable} = \text{Invoice Qty} - \text{Previously Returned Qty}$.
      4. Throws validation error if $\text{Requested} > \text{Maximum Returnable}$.
    - **When returning directly against a Supplier (without invoice):**
      1. Queries all non-cancelled historical purchase invoice items for that supplier.
      2. Validates that the requested `item_id` and `batch_no` were purchased from this supplier.
      3. Calculates: $\text{Maximum Returnable} = \sum(\text{Purchased from Supplier}) - \sum(\text{Previously Returned to Supplier})$.
      4. Throws validation error if returning stock originating from another supplier or exceeding net purchased quantity.
    - **Stock Availability Verification:**
      - Also runs `assertStockAvailable()`, validating both physical branch stock (`ItemStock`) and batch-level stock (`BatchStockService`).

### 5.2 Concurrency & Mutex Locking
- Inside `DB::transaction()`:
  - When returning against an invoice: `$invoice = PurchaseInvoice::lockForUpdate()->find(...)`.
  - When returning against a supplier: `$supplier = Supplier::lockForUpdate()->find(...)`.
  - Re-evaluates returnable eligibility under lock to prevent double-submit or concurrent return race conditions.

### 5.3 Discount Percentage Synchronization & Freezing Bug Fix
- **Defect:** In Purchase Return, entering a discount % like 25% (or negative numbers) caused values to freeze at 2% or produce incorrect calculations.
- **Root Cause:**
  - `recalculateRow` in `resources/views/purchase/purchase-returns/_form.blade.php` contained:
    `if (discAmount <= 0 && discPercent > 0)`.
  - When typing "2", `discAmount` was populated with 2% value. When subsequently typing "5" (making it 25%), because `discAmount > 0`, the block was bypassed and never re-evaluated! Because `TaxEngine` prioritized `discAmount`, the document was saved with 2% discount instead of 25%.
- **Resolution:**
  - Implemented bidirectional synchronization in `resources/views/purchase/purchase-returns/_form.blade.php`:
    - Typing in `.pr-disc-percent` immediately calculates and updates `.pr-disc-amount`.
    - Typing in `.pr-disc-amount` immediately calculates and updates `.pr-disc-percent`.
    - Negative values are automatically clamped to `0`, and percentages are capped at `100`.
    - Backend validates `min:0, max:100` for `disc_percent` and `min:0` for `disc_amount`.

### 5.4 Reset Table Button Above Item Table
- Added `#pr-btn-reset-table` button **ABOVE** the dynamic item table in `purchase-returns/_form.blade.php`.
- Clicking resets the items table to exactly 1 empty default row with pristine calculations while preserving the return header, supplier, and return date.

---

## 6. Global Reset Table Behavior Across All 12 Modules

Across all transactional item-entry pages, a consistent, robust Reset Table button is provided **above the item table**:
1. **Sales Bill**: `#sb-btn-reset-table` in [`sales-bills/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/sales/sales-bills/_form.blade.php)
2. **Purchase Invoice**: `#pinv-btn-reset-table` & `.btn-reset-table` in [`purchase-invoices/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/purchase/purchase-invoices/_form.blade.php)
3. **Purchase Return**: `#pr-btn-reset-table` in [`purchase-returns/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/purchase/purchase-returns/_form.blade.php)
4. **Stock Transfer**: `#btn-reset-table` in [`stock-transfers/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/inventory/stock-transfers/_form.blade.php)
5. **Stock Update**: `#su-btn-reset-table` in [`stock-updates/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/inventory/stock-updates/_form.blade.php)
6. **Purchase Order**: `#po-btn-reset-table` in [`purchase-orders/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/purchase/purchase-orders/_form.blade.php)
7. **Sales Return**: `#sr-btn-reset-table` in [`sales-returns/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/sales/sales-returns/_form.blade.php)
8. **Sales Order**: `#so-btn-reset-table` in [`sales-orders/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/sales/sales-orders/_form.blade.php)
9. **Sales Quotation**: `#sq-btn-reset-table` in [`sales-quotations/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/sales/sales-quotations/_form.blade.php)
10. **Sales Delivery Notes**: `#sdn-btn-reset-table` in [`sales-delivery-notes/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/sales/sales-delivery-notes/_form.blade.php)
11. **Damage Stock**: `#btn-reset-table` in [`damage-stocks/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/inventory/damage-stocks/_form.blade.php)
12. **Opening Stock**: `#btn-reset-table` in [`opening-stocks/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/inventory/opening-stocks/_form.blade.php)

**Behavioral Guarantees:**
- Asks for user confirmation (`confirm(...)`) if the table has existing row data.
- Clears all existing item rows.
- Re-inserts exactly 1 blank default row.
- Re-initializes Select2 / autocomplete plugins.
- Recalculates document totals to `0.00`.
- **Preserves Document Headers**: Supplier, Customer, Warehouse, Branch, Invoice Date, PO Number, and other header metadata remain intact.

---

## 7. Sales Bill Tender Modal Enhancements

- **Defect:** Opening the tender modal forced the full amount into Cash by default, making Card, Credit, or UPI payment flows cumbersome.
- **Resolution in [`sales-bills/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/sales/sales-bills/_form.blade.php):**
  - Added a top payment choice bar with mode buttons: `[ Cash ]`, `[ Card ]`, `[ Credit ]`, `[ UPI ]`, `[ RRN ]`.
  - Added keyboard shortcuts:
    - `Alt + C`: Select Cash
    - `Alt + D`: Select Card
    - `Alt + E`: Select Credit
    - `Alt + U`: Select UPI
  - Selecting any mode sets that mode's tender amount to the bill total, clears all other mode fields, updates change return calculation to `0.00`, and focuses that mode's text input.
  - Opening the modal does not overwrite user selections.

---

## 8. Stock Transfer Quantity Ceiling Fix

- **Defect:** Adding multi-batch items or selecting items triggered a false "Quantity exceeds available stock" error.
- **Root Cause:**
  1. `applyItemToRow()` in `stock-transfers/_form.blade.php` did not update `$row.find('.item-available').val(avail)`, leaving available stock as `0.000`.
  2. `validateStQty()` summed quantities across all rows matching `rId == itemId` regardless of batch, grouping different batches of the same item into one ceiling check.
- **Resolution:**
  - `applyItemToRow` sets `$row.find('.item-available').val(avail.toFixed(3))` and updates the batch balance label.
  - `validateStQty` groups by `item_id + '__' + batch_no` and evaluates quantity strictly against that specific batch's available stock.

---

## 9. Stock Update Zero/Negative Stock Filter & Expiry Preservation

- **Requirement:** Stock Update item modal must default to hiding zero/negative stock with an explicit `[ ] Show Zero/Negative Stock` checkbox, and display batch-wise rows preserving expiry and cost attributes with editable physical quantity.
- **Resolution in [`stock-updates/_form.blade.php`](file:///c:/laragon/www/Urbanpos/resources/views/inventory/stock-updates/_form.blade.php):**
  - Added `#su-isl-show-zero` checkbox in item selection modal (default: unchecked).
  - Search filter applies `stock > 0` condition by default. Checking the box shows all inventory records.
  - Added `#su-btn-reset-table` above table.
  - Table rows display batch number, expiry date, system quantity, and an editable physical count input.

---

## 10. Cross-Flow Batch Consistency Verification

A dedicated end-to-end multi-step inventory lifecycle test was executed to verify that `BatchStockService`, `StockLedgerService`, and `ItemStock` remain mathematically and sequentially consistent across all modules:

| Lifecycle Step | Action | Batch A Stock | Batch B Stock | Total Stock | Movement Type |
|---|---|---|---|---|---|
| **Step 1: Purchase** | Purchase 20 of Batch A, 10 of Batch B | 20.000 | 10.000 | 30.000 | `PURCHASE_RECEIPT` |
| **Step 2: Sales Bill** | Sell 5 from Batch A | 15.000 | 10.000 | 25.000 | `SALE` |
| **Step 3: Stock Transfer** | Transfer 3 of Batch B | 15.000 | 7.000 | 22.000 | `TRANSFER_OUT` |
| **Step 4: Damage Stock** | Record 2 damage from Batch A | 13.000 | 7.000 | 20.000 | `DAMAGE` |
| **Step 5: Stock Update** | Shortage adjustment of 1 on Batch B | 13.000 | 6.000 | 19.000 | `SHORTAGE` |
| **Step 6: Purchase Return** | Return 2 of Batch A to supplier | 11.000 | 6.000 | 17.000 | `PURCHASE_RETURN` |
| **Step 7: Sales Return** | Customer returns 2 of Batch A | 13.000 | 6.000 | 19.000 | `SALE_RETURN` |

**Outcome:** Every step verified against `BatchStockService::getBatchStock()` and matched expected quantities with 100% precision.

---

## 11. Concurrency & Race-Condition Safety

| Scenario | Risk | Protection Mechanism |
|---|---|---|
| **Concurrent Purchase Returns** | Two managers returning the same invoice simultaneously | Database row mutex via `lockForUpdate()`, transactional eligibility recalculation inside `DB::transaction()` |
| **Double-Click Submit** | Duplicate submit creating duplicate records | Unique `posting_key` deduplication with cache lock and database constraint |
| **Stock Transfer Over-Allocation** | Two transfers exhausting the same batch simultaneously | `ItemStock::lockForUpdate()` and ledger movement verification |
| **Past Date / Closed Financial Year** | Back-dated postings into closed fiscal years | `FinancialYearGuard::assertOpenForPosting()` on document date |

---

## 12. Automated Test Results

### 12.1 `tests/Feature/DeepCrossModuleRegressionTest.php`
- `test_purchase_return_supplier_isolation_and_cross_supplier_blocking`: **PASSED**
- `test_purchase_return_previous_returns_deducted`: **PASSED**
- `test_purchase_return_discount_validation`: **PASSED**
- `test_purchase_invoice_expired_date_blocked`: **PASSED**
- `test_stock_transfer_batch_independent_transfer`: **PASSED**
- `test_master_items_and_category_brand_filters`: **PASSED**
- `test_cross_flow_batch_lifecycle_consistency`: **PASSED**

**Summary:** 7 tests, 55 assertions, **100% passed**.

### 12.2 `tests/Feature/PurchaseReturnTest.php`
- `test_purchase_return_reduces_stock_and_posts_correct_journal`: **PASSED**
- `test_purchase_return_cancellation_reverses_stock_and_journal`: **PASSED**
- `test_cash_bank_book_report_renders_and_returns_ok`: **PASSED**
- `test_purchase_return_views_render`: **PASSED**
- `test_sales_return_bill_items_ajax_endpoint`: **PASSED**
- `test_sales_bill_show_and_thermal_receipt_render`: **PASSED**

**Summary:** 6 tests, 49 assertions, **100% passed**.

**Combined Total:** 13 tests, 104 assertions, **0 failures, 0 errors**.

---

## 13. Deployment & Migration Safeguards

- **Staging / Production Integrity:**
  - No database wipe or `migrate:fresh` was executed.
  - All existing tables, seeded data, and historical ledger transactions remain preserved.
  - All changes use standard Laravel conventions, forward-compatible schema attributes, and existing service singletons.
