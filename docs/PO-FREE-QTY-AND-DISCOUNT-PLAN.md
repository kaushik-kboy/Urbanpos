# Purchase Orders: Free Qty, Line Discounts, Scheme & Other Discount Allocation Plan

## 1. Background & Objective
In **Purchase Invoices**, we implemented Option 1:
- Line-level: `Free Qty`, `Disc %`, `Disc Amt`, `Landing Cost`, `Margin %`, `Profit %`.
- Header-level (Totals & Charges): `Scheme ItemDiscAmt`, `Scheme ItemDisc%`, `OtherDiscAmt`.
- Proportional allocation: Total header discounts (`Scheme ItemDiscAmt + OtherDiscAmt`) are distributed across line items by base value, reducing the effective landing cost and adjusting margins in real-time.

Now, we will bring the exact same calculation model to **Purchase Orders** (`/purchase/purchase-orders/create` & `/edit`):
URL: `https://pos.ramdevcar.shop/purchase/purchase-orders/create`

---

## 2. Mathematical Calculation Model (Option 1 Consistency)

### A. Line Base Calculation
For each row $i$:
$$\text{Base}_i = \text{qty}_i \times \text{cost\_price}_i$$
$$\text{Line Discount}_i = \text{disc\_amount}_i \quad (\text{or } \text{Base}_i \times \text{disc\_percent}_i / 100)$$
$$\text{Net Base}_i = \max(0, \text{Base}_i - \text{Line Discount}_i)$$

### B. Header Discount Allocation
$$\text{Total Header Discount} = \text{Scheme ItemDiscAmt} + \text{OtherDiscAmt}$$
$$\text{Allocated Deduction}_i = \begin{cases} 
\text{round}\left(\frac{\text{Net Base}_i}{\sum \text{Net Base}} \times \text{Total Header Discount}, 2\right) & \text{for lines } 1 \dots N-1 \\
\text{Remaining Header Discount} & \text{for line } N 
\end{cases}$$

### C. Landing Cost & Margins
$$\text{Total Units}_i = \text{qty}_i + \text{free\_qty}_i$$
$$\text{Landing Cost}_i = \begin{cases} 
\text{round}\left(\frac{\text{Net Base}_i - \text{Allocated Deduction}_i}{\text{Total Units}_i}, 4\right) & \text{if } \text{Total Units}_i > 0 \\
\text{cost\_price}_i & \text{otherwise}
\end{cases}$$

$$\text{Selling Price (Excl. GST)}_i = \frac{\max(\text{sell\_price}_i, \text{mrp}_i)}{1 + (\text{gst\_percent}_i / 100)}$$
$$\text{Margin } \%_i = \frac{\text{Selling Price (Excl. GST)}_i - \text{Landing Cost}_i}{\text{Selling Price (Excl. GST)}_i} \times 100$$
$$\text{Profit } \%_i = \frac{\text{Selling Price (Excl. GST)}_i - \text{Landing Cost}_i}{\text{Landing Cost}_i} \times 100$$

### D. Grand Total
$$\text{Items Net Total} = \sum \left( \text{Net Base}_i + \text{GST Amount}_i \right)$$
$$\text{Grand Total} = \text{Items Net Total} + \text{Freight} + \text{Round Off} - \text{Scheme ItemDiscAmt} - \text{OtherDiscAmt} + \text{Total Extra Cess}$$

---

## 3. Scope of Implementation

### Phase 1: Database Migration (Non-destructive)
- Add `effective_cost` (`decimal(12, 4)->default(0)`) to `purchase_order_items` table if missing.
- Note: `free_qty`, `cost_price`, `sell_price`, `mrp`, `disc_percent`, `disc_amount`, `gst_percent`, `gst_tax_amount`, `net_amount` are **already present** in `purchase_order_items`.
- Note: `scheme_item_disc_amt`, `other_disc_amt`, `freight`, `round_off`, `total_extra_cess` are **already present** in `purchase_orders`.

### Phase 2: Backend Controller & Models
1. **`app/Models/PurchaseOrderItem.php`**:
   - Add `effective_cost` to `$fillable`.
2. **`app/Http/Controllers/Purchase/PurchaseOrderController.php`**:
   - Update `validateData()`:
     - Allow `scheme_item_disc_percent` in header rules.
     - Validate item rows with `free_qty`, `cost_price`, `disc_percent`, `disc_amount`, `mrp`, `sell_price`.
   - Update `computeLines()`:
     - Accept `$header` / `$data`.
     - Calculate proportional allocation of Scheme + Other discounts.
     - Compute `effective_cost = round(($base - $disc - $extraDeduction) / ($qty + $freeQty), 4)`.
   - Update `computeTotals()`:
     - Deduct `scheme_item_disc_amt` and `other_disc_amt` from total:
       `'total' => round($collection->sum('net_amount') + $freight + $roundOff + $totalExtCess - $otherDiscAmt - $schemeDiscAmt, 2)`.
   - Update `items(PurchaseOrder $purchaseOrder)`:
     - Return `effective_cost`, `free_qty`, `scheme_item_disc_amt`, `other_disc_amt` in JSON response for PO-to-Invoice auto-fill.

### Phase 3: Frontend Blade Views
1. **`resources/views/purchase/purchase-orders/_item-row.blade.php`**:
   - Add read-only `Landing Cost` (`po-landing-cost`), `Margin %` (`po-margin`), and `Profit %` (`po-profit`) input cells.
   - Format `free_qty`, `disc_percent`, `disc_amount` with proper formatting and accessibility classes matching `purchase-invoices`.
2. **`resources/views/purchase/purchase-orders/_form.blade.php`**:
   - Table header (`#po-items-table`): Add `Landing Cost`, `Margin %`, `Profit %` columns with `data-col-key` for column customizer.
   - Table footer: Display Total Free Qty, Total Discount, and Net Amount.
   - Totals & Charges: Ensure `scheme_item_disc_amt`, `scheme_item_disc_percent`, `other_disc_amt` are present with 2-way sync (`Scheme ItemDisc%` $\leftrightarrow$ `Scheme ItemDiscAmt`).
   - JavaScript engine:
     - Real-time row recalculation with discount allocation across all rows.
     - Add `scheduleCalculateTotals()` with `requestAnimationFrame` batching to ensure fast typing.
     - Listen to input changes on `po-qty`, `po-free-qty`, `po-cost`, `po-sell`, `po-mrp`, `po-disc-percent`, `po-disc-amount`, `scheme_item_disc_amt`, `scheme_item_disc_percent`, `other_disc_amt`, `freight`, `round_off`.
     - Subtract header discounts in `Grand Total` display.
3. **`resources/views/purchase/purchase-orders/show.blade.php` & `print.blade.php`**:
   - Show `Landing Cost`, `Scheme ItemDiscAmt`, `OtherDiscAmt` in show card and printable invoice.

### Phase 4: Downstream Conversion Bridge (PO -> Purchase Invoice)
- Verify `PurchaseInvoiceController::create` and AJAX item prefilling when converting a PO with Free Qty and discounts into a Purchase Invoice: all line discounts, free qty, and allocated header discounts match 1:1.

---

## 4. Verification & Testing Steps
1. **Unit & Feature Tests**:
   - Create `tests/Feature/PurchaseOrderDiscountAndFreeQtyTest.php`.
   - Test 1: PO created with Free Qty (10 + 2 Free @ ₹100 = Landing Cost ₹83.33).
   - Test 2: PO created with Line Discount + Scheme ItemDiscAmt + OtherDiscAmt; verify allocated effective cost and grand total.
   - Test 3: Edit PO, verify totals and line items remain accurate.
   - Test 4: Convert PO into Purchase Invoice via API / form prefill.
2. **Static & Syntax Checks**:
   - `npm run test:blade-js` (ensure 0 syntax errors across all Blade templates).
   - `php artisan test` (ensure existing regression tests pass).
3. **Staging Deployment**:
   - Push to `origin/main`.
   - Deploy to Ramdev staging server via SSH: `git pull`, `php artisan migrate --force`, `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
   - Live smoke test on `https://pos.ramdevcar.shop/purchase/purchase-orders/create`.
