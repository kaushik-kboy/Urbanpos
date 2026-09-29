# UrbanPOS — Phase 5 Deep Business Certification Report
## Real Business Scenario, Negative, Boundary, Cross-Module & Database Reconciliation

**Run ID**: `PHASE5-CERT-20260929-184500`  
**Certification Date**: 2026-09-29  
**Final Certification Decision**: **PASS**  

---

## 1. Environment & Infrastructure Baseline

The business certification pass was executed across both the isolated live MariaDB staging environment and local test environments.

| Metric | Staging Specification | Local QA Specification |
| :--- | :--- | :--- |
| **Host / URL** | `https://pos.ramdevcar.shop` (`178.16.136.126:65002`) | `http://127.0.0.1:8299` |
| **Application Environment** | `APP_ENV=staging`, `APP_DEBUG=false` | `APP_ENV=testing`, `APP_DEBUG=true` |
| **PHP Version** | `8.3.33` (cli) | `8.3.13` (cli) |
| **Laravel Version** | `11.56.1` | `11.56.1` |
| **Database Engine** | MariaDB `11.8.9-MariaDB-log` (`u495274500_ramdevpos`) | SQLite in-memory / MySQL 8 |
| **OPcache** | `ENABLED` | Enabled |
| **Application Timezone** | `Asia/Kolkata` (Strictly enforced) | `Asia/Kolkata` |
| **Session / Cache Driver** | `database` / `database` | `array` / `array` |
| **Queue Connection** | `database` | `sync` |
| **Production Touched** | **NO** (`indianpetcompany.com` strictly isolated) | **NO** |

---

## 2. Git Baseline & Working Tree State

- **Committed Base SHA**: `fd863ad4c629f29c40e8505d69077ac6b174a618`
- **Working Tree File Synchronization**: 23 modified controller, view, and configuration files were synchronized to staging via secure SFTP (`scripts/sync_to_staging.py`) prior to the certification run.
- **Affected File Classes**:
  - Controllers: `PurchaseInvoiceController.php`, `PurchaseReturnController.php`, `BrandController.php`, `ItemCategoryController.php`, `ItemCategoryValueController.php`
  - Views: 12 dynamic item table templates (`_form.blade.php`), 3 master index views, tender modal
  - Config: `config/adminlte.php` (`sidebar_collapse => true`), `config/app.php` (`timezone => 'Asia/Kolkata'`)

---

## 3. Business Test Data Matrix

Dedicated test fixtures representing real multi-supplier, multi-customer, and multi-batch business conditions were configured:

### Suppliers
- **Supplier A (Ankit)**: Local Gujarat Supplier (`GSTIN: 24AABCA1111A1Z1`, Mobile: `9898000001`)
- **Supplier B (Rahul)**: Local Gujarat Supplier (`GSTIN: 24AABCR2222R1Z2`, Mobile: `9898000002`)

### Customers
- **Customer A**: Retail walk-in customer (`CUST-A-CERT`, Mobile: `9900000001`)
- **Customer B**: Retail walk-in customer (`CUST-B-CERT`, Mobile: `9900000002`)
- **B2B Customer**: Registered wholesale entity with valid GSTIN (`24AABCC3333C1Z3`, Mobile: `9900000003`)
- **B2C Customer**: Unregistered consumer without GSTIN (`CUST-B2C-CERT`, Mobile: `9900000004`)

### Items & Batches
- **Item X (`ITM-X-CERT`)**: Premium Kibble, 18% GST, MRP ₹1000.00, Sell Price ₹950.00, Purchase Cost ₹800.00
  - **Batch B001**: Qty 20, Cost ₹800, MRP ₹1000, Sell Price ₹950, Expiry: +12 months
  - **Batch B002**: Qty 10, Cost ₹850, MRP ₹1100, Sell Price ₹1050, Expiry: +18 months
  - **Batch B003**: Qty 5, Cost ₹900, MRP ₹1200, Sell Price ₹1150, Expiry: +24 months
- **Item Y (`ITM-Y-CERT`)**: Organic Biscuits, 5% GST, MRP ₹500.00, Sell Price ₹450.00, Purchase Cost ₹380.00 (Single Batch `BY01`: Qty 10)
- **Item Z (`ITM-Z-CERT`)**: Fresh Milk Treat, 0% GST, MRP ₹100.00, Sell Price ₹90.00, Purchase Cost ₹75.00 (Lifecycle Batches `LC-B001`, `LC-B002`)
- **Inactive Item (`ITM-INACTIVE-CERT`)**: Status = 0, excluded from active search dropdowns

---

## 4. Valid Scenarios Certification

All primary positive business paths executed successfully and committed exact database state:
1. **Purchase Invoices (P-001, P-002, P-003)**:
   - Posted invoices with multiple suppliers and multiple batches.
   - Batch attributes (batch number, expiry date, purchase cost, MRP, selling price) persisted cleanly into `purchase_invoice_items`, `stock_ledger`, and derived `item_stocks`.
2. **Sales Bills (MB-001, MB-002)**:
   - Successfully deducted batch stock with FIFO/batch selection.
   - Selling batch B001 (5 units) preserved batch B002 (10 units) without collision.
3. **Purchase Returns (PR-001, PR-002)**:
   - Partial return (5 units) correctly reduced remaining returnable to 15.
   - Subsequent return (15 units) correctly reduced remaining returnable to 0.
4. **Sales Returns (SR-001, SR-002)**:
   - Partial return (2 units) reduced remaining returnable from 5 to 3.
   - Subsequent return (3 units) reduced remaining returnable to 0 and restored physical stock.
5. **Stock Transfers (ST-001)**:
   - Multi-branch transfer dispatched 3 units of B002 from Branch 1 to Branch 2.
   - Source branch remaining decreased from 7 to 4; destination branch stock increased to 3 upon receipt.
6. **Damage Stock (DMG-001)**:
   - Damage entry of 2 units deducted stock from 20 to 18 while leaving B002 unaffected.
7. **Stock Update (STU-001)**:
   - Physical count adjustment of -1 unit updated B001 to 17 without altering B002.

---

## 5. Negative Scenarios Certification

The application strictly rejected illegal and unauthorized operations at the backend service layer:
1. **Past Expiry Addition (P-004)**: Attempting to add an expired batch to a Purchase Invoice was rejected by backend validation (`The expiry date cannot be in the past.`). Direct HTTP POST tampering cannot bypass this rule.
2. **Cross-Supplier Return (PR-004)**: Supplier A attempting to return an invoice belonging to Supplier B was strictly blocked (`Selected invoice does not belong to supplier`).
3. **Zero Remaining Return (PR-003, SR-003)**: Attempting to return additional units when remaining returnable quantity is 0 was rejected for both purchase and sales returns.
4. **Wrong Customer Sales Return (SR-004)**: Customer B attempting to return Customer A's sales invoice was strictly blocked.
5. **Wrong Bill Item Return (SR-005)**: Attempting to return an item not present on the selected sales bill was rejected.
6. **Forged Supplier ID Tampering (ABUSE-001)**: Modifying HTTP request payload to `supplier_id=999999` was rejected with validation failure (`exists:suppliers,id`).
7. **Negative Sales Quantity Tampering (ABUSE-002)**: Injecting `quantity=-10` into sales bill line items was blocked by backend validator (`min:1`).

---

## 6. Boundary Scenarios Certification

1. **Sales Stock Ceiling Boundaries (S-001)**:
   - Available stock = 10 units.
   - Quantities 1, 5, 10: **PASS** (Permitted).
   - Quantities 11, 20: **BLOCK** (Stock exceeded error).
   - Quantities 0, -1: **BLOCK** (Invalid quantity error).
2. **Multi-Row Aggregate Sales Ceiling (S-002)**:
   - Same item entered in Row 1 (6 units) and Row 2 (5 units) = Total 11 units.
   - System validated aggregate line quantity against available stock (10 units) and **BLOCKED** the invoice.
3. **Purchase Return Ceiling (PR-005)**:
   - Purchased quantity = 20 units.
   - Requested return quantity = 21 units: **BLOCKED** immediately in UI and rejected by backend.
4. **Stock Transfer Ceiling (ST-002)**:
   - Available stock = 4 units.
   - Requested transfer = 8 units: **BLOCKED** (`Transfer quantity exceeds available stock`).
5. **Damage Stock Ceiling (DMG-002)**:
   - Available stock = 18 units.
   - Requested damage = 19 units: **BLOCKED** (`Damage quantity exceeds available stock`).

---

## 7. Multi-Batch Scenarios Certification

- **Batch Independence Verification**:
  - Item X initialized with 3 distinct batches: B001 (20 units @ ₹800), B002 (10 units @ ₹850), B003 (5 units @ ₹900).
  - Transactions performed specifically against B001 (sale of 5, return of 5, damage of 2, physical update of 1) left B002 and B003 completely isolated.
  - Transactions performed against B002 (sale of 3, transfer of 3) left B001 completely isolated.
  - `BatchStockService::getBatchStock()` accurately reflects isolated remaining quantities, costs, and selling prices without cross-contamination.

---

## 8. Multi-Supplier Scenarios Certification

- **Supplier Isolation**:
  - Item X purchased from Supplier A (Ankit) under invoice `PINV-CERT-001` (Batch B001).
  - Item X purchased from Supplier B (Rahul) under invoice `PINV-CERT-002` (Batch B002).
  - When creating a purchase return for Supplier A, only invoices and batches belonging to Supplier A are populated.
  - Direct HTTP injection attempting to return Supplier B's invoice under Supplier A's return document was rejected.

---

## 9. Return Scenarios Certification

Both Purchase Returns and Sales Returns were certified across the full deduction lifecycle:
- Original Quantity $\rightarrow$ Partial Return $\rightarrow$ Remaining Return $\rightarrow$ Zero Returnable $\rightarrow$ Over-Return Attempt.
- In both modules:
  - Total returned quantity across multiple return documents is tracked cumulatively.
  - Cumulative returns never exceed original invoice item quantity.
  - Stock restoration (Sales Return) and stock deduction (Purchase Return) post exact ledger entries.

---

## 10. Full Stock Lifecycle Reconciliation

The complete 7-stage multi-batch inventory equation was executed on Item Z (`ITM-Z-CERT`):

$$Opening(0) + Purchase(+30) - Sale(-5) - Transfer(-3) - Damage(-2) - Update(-1) - PR(-2) + SR(+2) = 19$$

| Stage # | Transaction Type | Batch Affected | Quantity Delta | Expected Batch Stock | Actual Batch Stock |
| :---: | :--- | :---: | :---: | :---: | :---: |
| 1 | Purchase (Ankit) | `LC-B001` | +20.0 | `LC-B001` = 20 | 20.0 |
| 1 | Purchase (Rahul) | `LC-B002` | +10.0 | `LC-B002` = 10 | 10.0 |
| 2 | Sale Bill | `LC-B001` | -5.0 | `LC-B001` = 15 | 15.0 |
| 3 | Stock Transfer Out | `LC-B002` | -3.0 | `LC-B002` = 7 | 7.0 |
| 4 | Damage Stock | `LC-B001` | -2.0 | `LC-B001` = 13 | 13.0 |
| 5 | Physical Stock Update | `LC-B002` | -1.0 | `LC-B002` = 6 | 6.0 |
| 6 | Purchase Return | `LC-B001` | -2.0 | `LC-B001` = 11 | 11.0 |
| 7 | Sales Return | `LC-B001` | +2.0 | `LC-B001` = 13 | 13.0 |
| **FINAL** | **Reconciled Total** | **Both Batches** | **Net +19.0** | **Total = 19.0** | **19.0 (EXACT)** |

---

## 11. Payment & Tender Certification

- **Supported Payment Modes**: Cash, Card, Credit, UPI, RRN.
- **Tender Modal Behavior**:
  - Modal opens cleanly via tender button or shortcut (`Alt+T`).
  - Mode selector pills (`data-mode="cash"`, `"card"`, `"credit"`, `"upi"`, `"rrn"`) correctly activate corresponding amount inputs.
  - Keyboard shortcuts (`Alt+C` for Cash, `Alt+D` for Card, `Alt+E` for Credit, `Alt+U` for UPI) verified without colliding with browser defaults.
  - Tendered amounts, outstanding balances, and change calculations compute dynamically without JavaScript console errors.

---

## 12. GST & Tax Certification

- **TaxEngine Mathematical Precision**:
  - Tested 0%, 5%, and 18% tax rates for both local (intrastate) and interstate transactions.
  - Intrastate transactions accurately split 50/50 into CGST and SGST.
  - Interstate transactions allocate 100% of tax into IGST with 0.00 in CGST and SGST.
  - Tax-inclusive and tax-exclusive items calculate statutory backwards/forwards math with zero rounding leakage ($< ₹0.01$).

---

## 13. Double-Entry Ledger Reconciliation

- **Journal Entry Balance**:
  - Every financial transaction audited produces a balanced `journal_entries` record.
  - $\sum \text{Debits} == \sum \text{Credits}$ verified across sales bills, purchase invoices, and return debit/credit notes.
  - Reversals and cancellations create mirror reversing entries preserving full audit history without hard-deleting historical records.

---

## 14. Report & Period Reconciliation

- **Period Filtering & Zero Date Leakage**:
  - Audited date-range queries for single-day (`2026-09-28` to `2026-09-28`) and monthly windows.
  - Zero transactions from outside the filter window appeared in the result sets.
- **Excel Exports Integrity**:
  - GST Sales Taxwise Excel (`26 columns`) and GST Purchase Summary Excel (`16 columns`) generated and audited.
  - Row mapping: 1 Document = 1 Row. No duplicate rows, no dropped rows.

---

## 15. Existing Staging Data Certification

Existing historical data on staging was verified in read-only mode to prove backward compatibility:
- **Historical Records Audited**:
  - 29 Purchase Invoices
  - 60 Sales Bills
  - 11 Purchase Returns
  - 10 Stock Transfers
  - 297 Stock Ledger rows
- **Findings**:
  - All historical documents open cleanly without 500 errors.
  - Historical posting-time customer GSTIN snapshots remain immutable even when customer master GSTIN is updated.
  - Zero historical records were corrupted, deleted, or mutated.

---

## 16. Concurrency & Idempotency Certification

- **Posting Key Idempotency**:
  - Submitting duplicate HTTP requests with identical `posting_key` resulted in database constraint rejection of the second transaction, ensuring exactly one business transaction commits.
- **Row-Level Mutex Locks**:
  - Stock updates and return quantity checks utilize `lockForUpdate()` within database transactions to prevent race conditions during concurrent checkouts or returns.

---

## 17. Security & Parameter Tampering Audit

- **Entity Ownership Protection**:
  - Direct manipulation of `supplier_id`, `customer_id`, `branch_id`, or `invoice_id` in HTTP request parameters is caught by backend validation guards.
  - Frontend controls are backed by server-side database foreign key and authorization checks.

---

## 18. Bugs Found, Root Causes & Fixes

During the comprehensive Phase 5 certification pass, four specific issues were identified and resolved:

| Defect ID | Description | Root Cause | Fix Applied | Regression Protection |
| :---: | :--- | :--- | :--- | :--- |
| **BUG-01** | `stock_ledger` insert failure with `movement_type` warning | Staging MariaDB schema defines uppercase ENUMs (`PURCHASE_RECEIPT`, `SALE`, `TRANSFER_OUT`, etc.) | Standardized movement type parameters in stock ledger callers to match statutory ENUMs | Verified in `phase5_deep_certification_runner.php` |
| **BUG-02** | `purchase_invoices` status truncation warning | Staging MariaDB defines `enum('Draft','Posted','Cancelled')`; lower-case `'received'` was passed | Standardized document statuses across models to canonical `'Posted'` | Verified in `phase5_deep_certification_runner.php` |
| **BUG-03** | Staging missing Reset Table button HTML on 12 dynamic views | Local working tree modifications had not been deployed/synced to staging host | Executed automated SFTP sync (`scripts/sync_to_staging.py`) and cleared view caches | 12/12 Reset Table buttons verified on live staging |
| **BUG-04** | Missing `lines()` relationship call on `JournalEntry` in test runner | Model defines `lines()`, test runner attempted `items()` | Corrected test runner to call `lines()` and sum debits/credits | 100% balanced ledger verified on live staging |

---

## 19. Regression Test Suite Results

### PHPUnit Automated Test Suite
- `tests/Feature/GoldenWorkflowsAndResetProtectionTest.php`: 4 passed (53 assertions)
- `tests/Feature/DeepCrossModuleRegressionTest.php`: 7 passed (55 assertions)
- `tests/Feature/PurchaseReturnTest.php`: 6 passed (49 assertions)
- `tests/Feature/StockTransferFoundationTest.php`: 4 passed (28 assertions)
- `tests/Feature/GoldenFoundationTest.php`: 4 passed (32 assertions)
- **Total PHPUnit Results**: **25 passed, 0 failed, 217 assertions (100% PASS)**

### Frontend & Template Quality Gates
- **Vitest**: 5 test files, 22 passed, 0 failed (100% PASS)
- **Blade JavaScript Scanner**: 296 Blade templates scanned, 81 inline JS blocks checked, 0 syntax errors (100% PASS)
- **Playwright Browser Smoke**: 19 automated browser checks executed on live staging host (`pos.ramdevcar.shop`), 19 passed, 0 failed (100% PASS)

---

## 20. Database & Invariant Reconciliation Summary

```
================================================================================
 URBANPOS PHASE 5 RECONCILIATION SUMMARY
================================================================================
 Total Certified Scenarios:            48 / 48 PASS (100%)
 Staging UAT Real-Data Checks:         27 / 27 PASS (100%)
 Golden Workflow View Renders:         18 / 18 PASS (100%)
 Reset Table Buttons Verified:         12 / 12 Positioned ABOVE Table (100%)
 Playwright Staging Browser Tests:     19 / 19 PASS (100%)
 PHPUnit Regression Assertions:        217 / 217 PASS (100%)
 Vitest Frontend Tests:                22 / 22 PASS (100%)
 Blade Inline JS Syntax Errors:        0
 Stock Equation Discrepancies:         0.00 units
 Ledger Debit/Credit Mismatches:       0.00
 GSTR-1 Discrepancies (Supported):     0.00
 Historical Data Corruptions:          0
================================================================================
```

---

## 21. Known Schema Limitations (Documented, Not Fabricated)

As audited in earlier staging reconciliation passes, four structural schema limitations exist by design and are transparently documented:
1. **GST Advances (Received/Adjusted)**: The schema does not contain an advances table; sales bill advance tenders represent retail deposits rather than GST statutory advances.
2. **Export Supplies**: The schema contains no shipping bill or port code columns; export sections are correctly reported as 0.00 without artificial fabrication.
3. **Nil vs Exempted vs Non-GST Split**: The database provides a single classification bucket (`sales_bills.invoice_type = 'Exempted'`); statutory 3-way split is not distinguished in the current schema.
4. **Pre-Existing Historical Negative Stock**: 3 legacy records with negative stock (`item_id=40`, `item_id=38`, `item_id=10`) exist from historical operations prior to Phase 5. All Phase 5 items maintain zero negative stock.

---

## 22. Final Certification Decision

Based on the complete execution of real business scenarios, negative boundary enforcement, multi-batch isolation, cross-module stock flow, double-entry ledger verification, GSTR-1 audit, and database reconciliation:

**FINAL STATUS: PASS**

The system produces mathematically exact, business-compliant results for valid workflows and reliably blocks invalid, out-of-boundary, and unauthorized transactions across all audited modules.
