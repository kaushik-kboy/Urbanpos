# GLOBAL UI CONSISTENCY MATRIX

## 1. Cross-Module UI & Component Alignment

| Module / Route | Global Active Branch | Quick Search | Reset Table Position | Reset Form Present & Position | Item Selection Shows | Alert-Free Validation | Status |
|---|---|---|---|---|---|---|---|
| **Purchase Receipt Notes (GIN)**<br>`/purchase/purchase-receipt-notes/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline feedback) | **ALIGNED** |
| **Purchase Indents**<br>`/purchase/purchase-indents/create` | Functional Branch selector | Authoritative Command Palette (`Ctrl+K`) | Single `#btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline feedback) | **ALIGNED** |
| **Delivery Notes**<br>`/sales/delivery-notes/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#sdn-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Table widths balanced, no clipping) | **ALIGNED** |
| **Stock Transfers**<br>`/inventory/stock-transfers/create` | Functional From/To Branch dropdowns (Mutual Exclusion) | Authoritative Command Palette (`Ctrl+K`) | Single `#btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Dynamic option exclusion, no alerts) | **ALIGNED** |
| **Damage Stock**<br>`/inventory/damage-stocks/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Toastr & inline validation) | **ALIGNED** |
| **Opening Stock**<br>`/inventory/opening-stocks/create` | Explicit Branch Selector required for stock init | Authoritative Command Palette (`Ctrl+K`) | Single `#btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Toastr & inline validation) | **ALIGNED** |
| **Stock Updates**<br>`/inventory/stock-updates/create` | Functional Branch selector | Authoritative Command Palette (`Ctrl+K`) | Single `#su-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Toastr & inline validation) | **ALIGNED** |
| **Purchase Returns**<br>`/purchase/purchase-returns/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#pr-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline max qty validation) | **ALIGNED** |
| **Purchase Orders**<br>`/purchase/purchase-orders/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#po-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline feedback) | **ALIGNED** |
| **Purchase Invoices**<br>`/purchase/purchase-invoices/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#pinv-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Natural supplier flow, date parsed) | **ALIGNED** |
| **Sales Bills**<br>`/sales/sales-bills/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#sb-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline feedback) | **ALIGNED** |
| **Sales Returns**<br>`/sales/sales-returns/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#sr-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Scoped customer/date validation) | **ALIGNED** |
| **Sales Quotations**<br>`/sales/sales-quotations/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#sq-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline error, Item -> Qty focus) | **ALIGNED** |
| **Sales Orders**<br>`/sales/sales-orders/create` | Hidden input (Global Header indicator only) | Authoritative Command Palette (`Ctrl+K`) | Single `#so-btn-reset-table` ABOVE table | Single `#btn-reset-form` in Card Footer | **Item Code** (not DB ID) | Yes (Inline error, Item -> Qty focus) | **ALIGNED** |

---

## 2. Item Flow Uniformity
Across all 12 transaction-entry modules, item selection (via Quick Scanner, Barcode Keydown, or F2 Search Modal) guarantees:
1. `.item-code-input` displays the **Item Code** (`ITM-...` or Barcode string), never internal auto-increment primary key ID.
2. `.item-id-hidden` / `.item-id-input` holds the database primary key ID safely behind the scenes.
3. Once an item is chosen, focus moves directly to the **Quantity** input.
4. If quantity is `<= 0`, Tab/Enter forward progression is blocked with an inline error message until corrected.
