# UrbanPOS Stock Batch & Purchase Source Audit Report

## 1. Current Data Flow

The inventory flow across UrbanPOS has been retrofitted with end-to-end batch tracking:

```
[Purchase Invoice]
       │ (persists batch_no, exp_date, cost_price, sell_price, mrp, qty)
       ▼
[Purchase Invoice Items]
       │
       ├──────────────────────────────────────────┐
       ▼                                          ▼
[Stock Ledger]                             [Item Master]
(batch_no, exp_date, movementType:          (maintains moving average & default prices)
PURCHASE, qty_in, unit_cost)
       │
       ▼
[BatchStockService]
(resolves per-batch identity: Item + Batch No + Expiry with batch cost, MRP, sell price, remaining qty)
       │
       ├─────────────────────────┬─────────────────────────┐
       ▼                         ▼                         ▼
[Sales Bill / POS]        [Stock Update (Physical)]  [Purchase / Sales Return]
(select batch, deduct     (pre-fills batch attributes(returns against original
 from exact batch)         from purchase, counts diff) batch & restores cost)
```

---

## 2. Source Table and Column for Each Field

| Field | Required Source | Actual Source | Status |
| :--- | :--- | :--- | :--- |
| **Batch** | Purchase | `purchase_invoice_items.batch_no` & `stock_ledger.batch_no` | **PASS** |
| **Expiry** | Purchase Batch | `purchase_invoice_items.exp_date` & `stock_ledger.exp_date` | **PASS** |
| **Purchase Price** | Purchase | `purchase_invoice_items.cost_price` & `stock_ledger.unit_cost` | **PASS** |
| **MRP** | Purchase Batch | `purchase_invoice_items.mrp` | **PASS** |
| **Sales Price** | Purchase Batch | `purchase_invoice_items.sell_price` | **PASS** |
| **Quantity** | Posted Stock | `stock_ledger.qty_in` - `stock_ledger.qty_out` (by item & batch) | **PASS** |

---

## 3. Current Bug(s) Identified

1. **Batch Collapsing / Missing Identity**: Previously, only `exp_date` was tracked in `stock_ledger` and `purchase_invoice_items`. There was no explicit `batch_no` stored across transactional lines (`sales_bill_items`, `purchase_return_items`, `sales_return_items`, `stock_update_items`), causing multiple batches of the same item to either merge or lose their distinct identities.
2. **Master-Data Overwriting Historical Batch Costs/Prices**: When a newer purchase arrived with a different cost, MRP, or sell price, previous batch prices could be lost or overridden by item-master lookups.
3. **Stock Update Desynchronization**: Stock Update only read aggregate `ItemStock.quantity` without batch separation, preventing store managers from counting and reconciling individual batches physically.
4. **UI Friction & Tender Navigation**:
   - PO Discount % / Discount Amount had back-navigation loops where cleared values rebounded.
   - Stock Transfer had search icon in barcode input and lacked hard ceiling stopping user from entering quantity $> \text{available stock}$.
   - POS Tender modal shortcuts (`Alt+C`, `Alt+U`, `Alt+D`, `Alt+E`) were intercepted by global page shortcut listeners.
   - Default quantity of `1` or `-1` was prepopulated on new rows across sales orders, quotations, transfers, damage, opening stocks, and delivery notes instead of remaining blank.

---

## 4. Root Cause

- Transaction tables (`purchase_invoice_items`, `sales_bill_items`, `stock_update_items`, `purchase_return_items`, `sales_return_items`, `stock_ledger`) lacked a dedicated `batch_no` string column.
- Stock deduction and returns resolved costs from moving averages or master records rather than indexing the exact `(item_id, batch_no)` composite ledger.
- In `pos-hotkeys.js`, keydown listeners were registered in the capturing phase (`addEventListener('keydown', ..., true)`) without checking if a modal or input was active, capturing `Alt+C` intended for Cash tender and navigating away to Customer master.

---

## 5. Fix Implemented

1. **Database Schema**:
   - Added `batch_no` (varchar 100, indexed) to `purchase_invoice_items`, `stock_ledger`, `stock_update_items`, `sales_bill_items`, `purchase_return_items`, `sales_return_items`, `opening_stock_items`.
   - Added `cost_price` to `stock_update_items`.
2. **StockLedgerService & BatchStockService**:
   - Updated `StockLedgerService::post()` with optional `?string $batchNo = null` parameter, persisting batch identity on every stock movement.
   - Implemented `BatchStockService` to resolve composite batch data `(batch_no, exp_date, cost_price, sell_price, mrp, purchased_qty, remaining_qty)` per item and branch.
3. **Transactional Flow Wiring**:
   - **Purchase Invoices**: Stores `batch_no` per line and posts to `StockLedgerService` with `batchNo`.
   - **Sales Bills**: Added batch picker support and automatic single-batch selection; records `batch_no` in `sales_bill_items` and deducts stock from that exact batch in `StockLedgerService`.
   - **Purchase Returns**: Stores `batch_no`, links original invoice item cost per batch, and deducts from the exact batch.
   - **Sales Returns**: Restores stock against the original sale's batch, retaining original cost and batch identity.
   - **Stock Updates**: Pre-populates all batches with read-only purchase attributes (`batch_no`, `exp_date`, `cost_price`, `sell_price`, `mrp`, `system_qty_at_entry`) and posts physical count adjustments strictly per batch upon manager approval.
4. **UI & Keyboard Fixes**:
   - Fixed PO Discount calculation recursion with source tagging (`percent` vs `amount`).
   - Globally eliminated default `1` / `-1` quantities; rows now start clean and blank.
   - Enforced hard stock barrier on Stock Transfers: user cannot input quantity $> \text{available stock}$ and navigation is blocked.
   - Guarded global hotkeys so modals receive `Alt+C` (Cash), `Alt+U` (UPI), `Alt+D` (Card), `Alt+E` (Credit).

---

## 6. Files Changed

- `database/migrations/2026_10_03_000001_add_batch_tracking_to_inventory_tables.php`
- `app/Models/PurchaseInvoiceItem.php`
- `app/Models/StockLedger.php`
- `app/Models/StockUpdateItem.php`
- `app/Models/SalesBillItem.php`
- `app/Models/PurchaseReturnItem.php`
- `app/Models/SalesReturnItem.php`
- `app/Models/OpeningStockItem.php`
- `app/Services/Inventory/StockLedgerService.php`
- `app/Services/Inventory/BatchStockService.php`
- `app/Http/Controllers/Purchase/PurchaseInvoiceController.php`
- `app/Http/Controllers/Purchase/PurchaseReturnController.php`
- `app/Http/Controllers/Sales/SalesBillController.php`
- `app/Http/Controllers/Sales/SalesReturnController.php`
- `app/Http/Controllers/Inventory/StockUpdateController.php`
- `app/Http/Controllers/Inventory/StockUpdateApprovalController.php`
- `resources/views/purchase/purchase-orders/_form.blade.php`
- `resources/views/purchase/purchase-invoices/_form.blade.php`
- `resources/views/purchase/purchase-invoices/_item-row.blade.php`
- `resources/views/sales/sales-bills/_form.blade.php`
- `resources/views/sales/sales-bills/_item-row.blade.php`
- `resources/views/inventory/stock-updates/_form.blade.php`
- `resources/views/inventory/stock-updates/_item-row.blade.php`
- `resources/views/inventory/stock-transfers/_form.blade.php`
- `resources/views/inventory/stock-transfers/_item-row.blade.php`
- `resources/views/sales/sales-orders/_form.blade.php`
- `resources/views/sales/sales-quotations/_form.blade.php`
- `resources/views/inventory/damage-stocks/_form.blade.php`
- `resources/views/inventory/opening-stocks/_form.blade.php`
- `resources/views/sales/delivery-notes/create.blade.php`
- `public/js/pos-hotkeys.js`
- `tests/Feature/Inventory/StockBatchPurchaseSourceTest.php`

---

## 7. Migration Changes

Migration file: `2026_10_03_000001_add_batch_tracking_to_inventory_tables.php`
Executed via: `php artisan migrate`

Schema alterations:
- Added nullable string column `batch_no` with index to:
  - `purchase_invoice_items`
  - `stock_ledger`
  - `stock_update_items`
  - `sales_bill_items`
  - `purchase_return_items`
  - `sales_return_items`
  - `opening_stock_items`
- Added decimal `cost_price` (15, 2) to `stock_update_items`.

---

## 8. Automated Tests

Created test suite `tests/Feature/Inventory/StockBatchPurchaseSourceTest.php` covering 11 comprehensive regression scenarios:

- **TEST 1**: `test_purchase_one_batch_stock_matches_exactly` — Verifies batch B001 preserves all purchase attributes.
- **TEST 2**: `test_purchase_second_batch_both_batches_remain_separate` — Verifies multiple batches of the same item remain distinct.
- **TEST 3**: `test_different_expiry_per_batch_remains_separate` — Verifies expiry date independence.
- **TEST 4**: `test_different_mrp_per_batch_remains_separate` — Verifies MRP independence.
- **TEST 5**: `test_different_sales_price_per_batch_remains_separate` — Verifies selling price independence.
- **TEST 6**: `test_different_purchase_cost_per_batch_remains_separate` — Verifies purchase cost independence.
- **TEST 7**: `test_sell_from_b001_only_b001_stock_decreases` — Verifies sales consumption targets the specified batch.
- **TEST 8**: `test_purchase_return_b001_only_b001_stock_decreases` — Verifies purchase return decrements only the targeted batch.
- **TEST 9**: `test_sales_return_preserves_original_batch_information` — Verifies sales return restores stock and cost to the original batch.
- **TEST 10**: `test_historical_batch_is_not_overwritten_by_newer_purchase` — Verifies historical batch integrity when newer purchases arrive.
- **TEST 11**: `test_stock_update_derives_batch_attributes_and_adjusts_batch_stock` — Verifies physical count derives batch attributes and adjusts exact batch.

**Test Run Result**:
```
PASS  Tests\Feature\Inventory\StockBatchPurchaseSourceTest
✓ purchase one batch stock matches exactly
✓ purchase second batch both batches remain separate
✓ different expiry per batch remains separate
✓ different mrp per batch remains separate
✓ different sales price per batch remains separate
✓ different purchase cost per batch remains separate
✓ sell from b001 only b001 stock decreases
✓ purchase return b001 only b001 stock decreases
✓ sales return preserves original batch information
✓ historical batch is not overwritten by newer purchase
✓ stock update derives batch attributes and adjusts batch stock

Tests: 11 passed (83 assertions)
Duration: 8.44s
```

---

## 9. Staging Verification

1. Local automated regression suite: 11/11 passed (83 assertions).
2. UI inspection:
   - Purchase Invoice form: Batch column present, populated, and stored.
   - Sales Bill create: Multi-batch modal lists batches with quantities, MRP, and prices; selection binds `batch_no`.
   - Tender shortcuts: `Alt+C` selects Cash, `Alt+U` selects UPI, `Alt+D` selects Card, `Alt+E` selects Credit.
   - Stock Transfer create: Search icon removed from barcode input; available stock enforced as strict cap.
   - Default quantities: Removed across all creation forms.
   - Stock Update create: Items with multiple batches automatically render rows per batch with exact purchase batch attributes.

---

## 10. Remaining Limitations

- For legacy inventory posted before migration `2026_10_03_000001`, batches without an explicit `batch_no` fall back to grouping by `exp_date` or 'DEFAULT'. All newly created purchase invoices, opening stocks, and physical updates maintain explicit batch numbers.
