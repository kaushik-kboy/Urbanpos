# PURCHASE DETAIL REPORT & EXCEL EXPORT AUDIT

## 1. Overview
The Purchase Detail report (`/reports/purchase-detail`) previously contained a structural defect where an invoice with multiple items appeared on multiple rows in both the web interface and CSV exports.

---

## 2. Issues Found & Root Causes

### 2.1 Multi-Row Duplication on Web Report
- **Previous Behavior**: A single purchase invoice containing 5 items rendered 5 separate rows in the HTML table.
- **Root Cause**: The Blade template `resources/views/reports/purchase-detail.blade.php` contained a nested loop:
  ```blade
  @forelse ($invoices as $invoice)
      @forelse ($invoice->items as $line)
          <tr>...</tr>
  ```
- **Fix**: Replaced nested item loop with a single `<tr>` per `$invoice`. Aggregated line item values (`taxable_amount`, `gst_percent` list, `cgst`, `sgst`, `igst`, `freight`, `tcs`, and `total`).

### 2.2 CSV & Excel Export Discrepancies
- **Previous Behavior**:
  - CSV export mapped line items:
    ```php
    return $invoice->items->map(fn ($line) => [...]);
    ```
    resulting in one CSV line per invoice item.
  - No Excel (`.xlsx`) export option was available for the Purchase Detail screen.
- **Fix**:
  - Updated CSV streaming callback to output exactly 1 row per invoice.
  - Implemented `App\Exports\PurchaseDetailExport` implementing `FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithEvents`.
  - Added dedicated route `/reports/purchase-detail/export` and supported `?export=excel` and `?export=xlsx` parameters.
  - Enforced the exact 16-column specification in exact sequence.

---

## 3. Specification Compliance: Exact 16 Columns

| Column # | Heading | Mapping Expression / Source | Formatting |
|---|---|---|---|
| 1 | `Inv No` | `pi.invoice_number` | Text |
| 2 | `Inv date` | `pi.invoice_date` | `dd-mm-yyyy` (Excel date) |
| 3 | `Supplier name` | `COALESCE(s.name, 'Unknown Supplier')` | Text |
| 4 | `GST No.` | `COALESCE(NULLIF(pi.supplier_gstin, ''), NULLIF(s.gst_no, ''))` | Text |
| 5 | `State Name` | `s.state` | Text |
| 6 | `Taxable amount` | `SUM(COALESCE(pii.net_amount, 0) - COALESCE(pii.gst_tax_amount, 0))` | Currency `0.00` |
| 7 | `Purchase tax %` | `GROUP_CONCAT(DISTINCT pii.gst_percent)` (sorted ascending) | Comma-separated list |
| 8 | `SGST Perc` | `GROUP_CONCAT(DISTINCT CASE WHEN pii.sgst_amount > 0 THEN pii.gst_percent / 2.0 END)` | Comma-separated list |
| 9 | `SGST TaxAmt` | `SUM(COALESCE(pii.sgst_amount, 0))` | Currency `0.00` |
| 10 | `CGST Perc` | `GROUP_CONCAT(DISTINCT CASE WHEN pii.cgst_amount > 0 THEN pii.gst_percent / 2.0 END)` | Comma-separated list |
| 11 | `CGST TaxAmt` | `SUM(COALESCE(pii.cgst_amount, 0))` | Currency `0.00` |
| 12 | `IGST Perc` | `GROUP_CONCAT(DISTINCT CASE WHEN pii.igst_amount > 0 THEN pii.gst_percent END)` | Comma-separated list |
| 13 | `IGST TaxAmt` | `SUM(COALESCE(pii.igst_amount, 0))` | Currency `0.00` |
| 14 | `Total amount` | `pi.total` | Currency `0.00` |
| 15 | `Freight charges` | `pi.freight` | Currency `0.00` |
| 16 | `TCS Amt` | `pi.tcs_amount` | Currency `0.00` |

---

## 4. Reconciliation & Verification
- **Automated Test**: `tests/Feature/PurchaseDetailOneRowPerInvoiceAndExcelTest.php`
- **Result**:
  - 1 invoice with 3 items = Exactly 1 row in HTML table.
  - Exactly 1 data line in CSV export.
  - Excel download triggers with all 16 headings verified in sequence.
  - Existing regression test `test_purchase_detail_paginates_invoices_and_exports_every_line` passes 100%.
