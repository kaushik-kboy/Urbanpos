# UrbanPOS: Global Listing Sorting Audit & Architecture Matrix

> **Date:** October 2026  
> **Baseline Git SHA:** `b1600258bf6764d68561e111376abb147312d930` (`checkpoint-before-global-sorting`)  
> **Status:** AUDIT COMPLETED — IMPLEMENTATION READY  
> **Objective:** Establish a unified, reusable, database-level sorting architecture across all UrbanPOS transaction and master listing pages without modifying business calculations, keyboard engine, or validation logic.

---

## 1. Executive Summary & Architecture Inspection

UrbanPOS is ~90% complete and highly stable. Our inspection of the existing codebase revealed:

1. **Pagination Architecture:**
   - Transaction tables utilize either Eloquent's standard `$query->paginate(20)->withQueryString()` or the high-performance `PaginatesDeep` concern (`App\Http\Controllers\Concerns\PaginatesDeep`) for deep pagination.
   - Master listings utilize `App\Http\Controllers\Concerns\HasPerPage` (`perPage()` helper supporting 10, 20, 50, 100 options).
   - Both mechanisms already preserve query strings via `withQueryString()`.

2. **Existing Shared Components:**
   - `<x-per-page-select>` (`resources/views/components/per-page-select.blade.php`): Already preserves all request parameters via `request()->except(['per_page', 'page'])`.
   - `<x-table-column-customizer>` (`resources/views/components/table-column-customizer.blade.php`): Dynamically reads `<th>` elements and relies on `data-col-key` or header text to manage column visibility and ordering.
   - **No existing sorting component was present.** Previous listings had static `<th>` elements and hardcoded backend ordering (e.g. `orderByDesc('id')` or `orderBy('name')`).

3. **Required Universal Design Pattern:**
   - **Backend Trait:** `App\Http\Controllers\Concerns\HasSorting` to provide safe, whitelisted, database-level `applySorting($query, $allowedSorts, $defaultSort)`.
   - **UI Component:** `<x-sortable-th>` (`resources/views/components/sortable-th.blade.php`) to render standard, accessible table headers with 3-state cycle (Ascending ↑ -> Descending ↓ -> Default ↕).
   - **Form State Preservation:** Hidden sort parameters in filter forms so sorting survives search/filter submissions and pagination page changes.

---

## 2. Listing Pages Audit Matrix

| Module | Route / Page | Existing Default Ordering | Whitelisted Sortable Columns | Primary Data Types | Excluded Non-Sortable Columns | Query Mechanism | Status |
|---|---|---|---|---|---|---|---|
| **Sales Bills** | `sales.sales-bills.index` | `id DESC` | `bill_number`, `bill_date`, `customer`, `total`, `invoice_type`, `payment_mode`, `created_at` | Text, DateTime, String, Decimal | Actions, Mobile, Branch | DB Query + `PaginatesDeep` | Audited |
| **Sales Orders** | `sales.sales-orders.index` | `order_date DESC, id DESC` | `order_number`, `order_date`, `customer`, `final_total`, `status` | Text, Date, String, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Sales Quotations** | `sales.sales-quotations.index` | `quotation_date DESC, id DESC` | `quotation_number`, `quotation_date`, `customer`, `final_total`, `status` | Text, Date, String, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Sales Returns** | `sales.sales-returns.index` | `return_date DESC, id DESC` | `return_number`, `return_date`, `customer`, `total_amount`, `bill_number` | Text, Date, String, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Sales Delivery Notes** | `sales.delivery-notes.index` | `delivery_date DESC, id DESC` | `delivery_number`, `delivery_date`, `customer`, `status`, `reference_no` | Text, Date, String | Actions, Items count | DB Query + `paginate(20)` | Audited |
| **Purchase Invoices** | `purchase.purchase-invoices.index` | `invoice_date DESC, id DESC` | `invoice_number`, `invoice_date`, `supplier`, `supplier_inv_no`, `final_amount`, `purchase_type` | Text, Date, String, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Purchase Orders** | `purchase.purchase-orders.index` | `po_date DESC, id DESC` | `po_number`, `po_date`, `supplier`, `total_amount`, `status` | Text, Date, String, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Purchase Returns** | `purchase.purchase-returns.index` | `return_date DESC, id DESC` | `return_number`, `return_date`, `supplier`, `total_amount`, `invoice_number` | Text, Date, String, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Purchase Indents** | `purchase.purchase-indents.index` | `indent_date DESC, id DESC` | `indent_number`, `indent_date`, `department`, `priority`, `status` | Text, Date, String | Actions, Remarks | DB Query + `paginate(20)` | Audited |
| **Purchase Receipt Notes** | `purchase.purchase-receipt-notes.index` | `receipt_date DESC, id DESC` | `receipt_number`, `receipt_date`, `supplier`, `status`, `supplier_challan_no` | Text, Date, String | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Stock Transfers** | `inventory.stock-transfers.index` | `transfer_date DESC, id DESC` | `transfer_number`, `transfer_date`, `status` | Text, Date, String | Actions, Source/Dest Branch | DB Query + `paginate(20)` | Audited |
| **Opening Stocks** | `inventory.opening-stocks.index` | `entry_date DESC, id DESC` | `entry_number`, `entry_date`, `total_cost`, `total_qty` | Text, Date, Decimal | Actions, Branch | DB Query + `paginate(20)` | Audited |
| **Damage Stocks** | `inventory.damage-stocks.index` | `entry_date DESC, id DESC` | `damage_number`, `entry_date`, `total_damage_cost`, `wastage_type` | Text, Date, Decimal, String | Actions, Remarks | DB Query + `paginate(20)` | Audited |
| **Stock Updates** | `inventory.stock-updates.index` | `entry_date DESC, id DESC` | `update_number`, `entry_date`, `physical_qty`, `system_qty`, `delta_qty` | Text, Date, Decimal | Actions, Live stock | DB Query + `paginate(perPage)` | Audited |
| **Customers** | `master.customers.index` | `name ASC` | `name`, `customer_code`, `mobile`, `credit_limit`, `status`, `created_at` | String, Text, Decimal, Boolean | Actions, Address, Category | DB Query + `paginate(perPage)` | Audited |
| **Suppliers** | `master.suppliers.index` | `name ASC` | `name`, `supplier_code`, `mobile`, `gst_no`, `status` | String, Text, Boolean | Actions, Address | DB Query + `paginate(perPage)` | Audited |
| **Items** | `master.items.index` | `name ASC` | `name`, `item_code`, `sell_price`, `cost_price`, `mrp`, `status` | String, Text, Decimal, Boolean | Actions, Brand, Image | DB Query + `paginate(perPage)` | Audited |
| **Brands** | `master.brands.index` | `name ASC` | `name`, `prefix`, `status` | String, Text, Boolean | Actions | DB Query + `paginate(perPage)` | Audited |
| **Branches** | `master.branches.index` | `name ASC` | `name`, `code`, `city`, `business_type`, `status` | String, Text, Boolean | Actions, Contact | DB Query + `paginate(perPage)` | Audited |
| **Finance Vouchers** | `finance.vouchers.index` | `voucher_date DESC, id DESC` | `voucher_number`, `voucher_date`, `voucher_type`, `total_debit`, `total_credit` | Text, Date, String, Decimal | Actions, Narration | DB Query + `paginate(20)` | Audited |
| **Ledgers** | `finance.ledgers.index` | `ledger_group ASC, name ASC` | `name`, `ledger_group`, `status` | String, String, Boolean | Actions, Balance calculation | DB Query + `paginate(30)` | Audited |

---

## 3. Security, Whitelisting & SQL Injection Protection

1. **Strict Whitelist Architecture:**
   - Every controller specifies an `$allowedSorts` associative array.
   - Only keys explicitly present in the whitelist can influence the SQL query.
   - User inputs for `sort` and `direction` are never concatenated directly into `orderByRaw`.
   - `direction` is strictly validated: only `'asc'` or `'desc'` is accepted; any other value defaults to `'asc'`.
2. **Relation Column Handling:**
   - When sorting by a foreign relation (e.g. `customer` or `supplier`), correlated subqueries (`Customer::select('name')->whereColumn('customers.id', 'sales_bills.customer_id')`) are used.
   - This avoids table JOIN pollution, prevents duplicate rows, and avoids breaking existing column aliases.
3. **Data Type Integrity:**
   - Numeric columns (`total`, `amount`, `sell_price`, `delta_qty`) sort as numeric values in SQL.
   - Dates (`bill_date`, `order_date`, `created_at`) sort chronologically.
   - Text strings sort alphabetically.

---

## 4. UI / UX Design & Interaction Standard

1. **Indicator Icons (FontAwesome 5 in AdminLTE):**
   - Inactive / Default: `<i class="fas fa-sort text-muted ml-1"></i>` (↕)
   - Ascending (Active): `<i class="fas fa-sort-up text-primary ml-1"></i>` (↑)
   - Descending (Active): `<i class="fas fa-sort-down text-primary ml-1"></i>` (↓)
2. **3-State Cycle Interaction:**
   - Click 1: Order by Column Ascending (`?sort=column&direction=asc`)
   - Click 2: Order by Column Descending (`?sort=column&direction=desc`)
   - Click 3: Revert to Default Listing Sort (parameters removed from query string)
3. **Coexistence with `<x-table-column-customizer>`:**
   - Headers will output clean text labels and optional `data-col-key` attributes so column customizer modals continue functioning seamlessly.
4. **Keyboard & Accessibility:**
   - Headers use standard non-intrusive anchor elements with accessible titles/tooltips (`aria-label` / `title`).
   - Does not interfere with POS keyboard shortcuts, modal dialogs, or scanner inputs.
