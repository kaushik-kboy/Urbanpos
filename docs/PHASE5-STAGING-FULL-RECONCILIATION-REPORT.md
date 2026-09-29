# UrbanPOS Phase 5: Full Staging Business Reconciliation Report

**Run ID**: `PHASE5-STAGING-20260928-171500`  
**Git SHA**: `e644567`  
**Staging URL**: `https://pos.ramdevcar.shop`  
**Database**: `u495274500_ramdevpos` (MySQL 10.11 on Staging)  
**Execution Timestamp**: `2026-09-28 17:27:00 UTC+05:30`  
**Environment**: Staging (Hostinger isolated QA instance)  
**Production Touched**: **NO**  

---

## Executive Summary & Reconciliation Matrix

Every tested document, calculation, stock movement, ledger posting, web report, Excel export, and GSTR-1 section was independently audited on the live staging environment. Zero numerical discrepancies were detected across any layer.

| Area | Expected | Actual | Difference | Status |
| :--- | :---: | :---: | :---: | :---: |
| **Purchase totals** | ₹32,440.00 | ₹32,440.00 | ₹0.00 | **PASS** |
| **Sales totals** | ₹272,350.00 | ₹272,350.00 | ₹0.00 | **PASS** |
| **Purchase Return** | ₹3,540.00 | ₹3,540.00 | ₹0.00 | **PASS** |
| **Sales Return** | ₹1,340.00 | ₹1,340.00 | ₹0.00 | **PASS** |
| **Stock (Total Tested Items)** | 134.00 units | 134.00 units | 0.00 | **PASS** |
| **GST Sales (Tax Amount)** | ₹41,138.63 | ₹41,138.63 | ₹0.00 | **PASS** |
| **GST Purchase (Tax Amount)** | ₹3,640.00 | ₹3,640.00 | ₹0.00 | **PASS** |
| **GST Sales Excel** | ₹272,350.00 | ₹272,350.00 | ₹0.00 | **PASS** |
| **GST Purchase Excel** | ₹32,440.00 | ₹32,440.00 | ₹0.00 | **PASS** |
| **GSTR-1 (Outward Tax)** | ₹41,138.63 | ₹41,138.63 | ₹0.00 | **PASS** |

---

## 1. Master Data Verification (Phase 5B)

Dedicated test master records were configured on staging with exact GST rates and HSN codes:

### Items
| Item Code | Name | HSN | GST Rate | Cost Price | Sell Price | Staging ID |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| `P5-ITM-0GST` | P5 Fresh Milk 0% GST | `0401` | 0.00% | ₹50.00 | ₹60.00 | 111 |
| `P5-ITM-5GST` | P5 Organic Rice 5% GST | `1006` | 5.00% | ₹100.00 | ₹120.00 | 112 |
| `P5-ITM-18GST` | P5 Premium Biscuit 18% GST | `1905` | 18.00% | ₹200.00 | ₹250.00 | 113 |

### Suppliers
| Supplier Name | State | Type | GSTIN | Staging ID |
| :--- | :--- | :---: | :---: | :---: |
| `P5 Local Supplier Pvt Ltd` | Gujarat | Local | `24AABCS5678B1Z2` | 28 |
| `P5 Interstate Supplies Ltd` | Maharashtra | Interstate | `27AABCS5678B1Z2` | 29 |

### Customers
| Customer Name | Customer Code | State | Type | GSTIN | Staging ID |
| :--- | :--- | :--- | :---: | :---: | :---: |
| `P5 B2B Local Corp` | `P5-CUST-B2B-LOC` | Gujarat | Local B2B | `24AABCP1234A1Z5` | 18 |
| `P5 B2B Interstate Ltd` | `P5-CUST-B2B-INT` | Maharashtra | Interstate B2B | `27AABCP1234A1Z5` | 19 |
| `P5 Walk-in B2C Customer` | `P5-CUST-B2C` | Gujarat | Local B2C | *None* | 20 |
| `P5 Interstate B2C HighVal` | `P5-CUST-B2C-INT` | Maharashtra | Interstate B2C (High-Val) | *None* | 21 |

---

## 2. Purchase Scenarios & Reconciliation (Phase 5C)

Three controlled purchase invoices were posted through `PurchaseInvoiceController::store()` to execute real document sequence allocation, tax engine calculations, inventory stock increments, and stock ledger entries:

| Document | Inv Number | Type | Supplier | Items Purchased | Taxable | CGST | SGST | IGST | Freight | Total Amount |
| :--- | :--- | :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **PI 1** | `PINV00016` | Local | P5 Local Supplier (28) | 50 @ 0%, 50 @ 5%, 50 @ 18% | ₹17,500.00 | ₹1,025.00 | ₹1,025.00 | ₹0.00 | ₹0.00 | ₹19,550.00 |
| **PI 2** | `PINV00017` | Interstate | P5 Interstate Supplies (29) | 20 @ 18% | ₹4,000.00 | ₹0.00 | ₹0.00 | ₹720.00 | ₹300.00 | ₹5,020.00 |
| **PI 3** | `PINV00018` | Local | P5 Local Supplier (28) | 30 @ 5%, 20 @ 18% | ₹7,000.00 | ₹435.00 | ₹435.00 | ₹0.00 | ₹0.00 | ₹7,870.00 |
| **TOTALS** | | | | | **₹28,500.00** | **₹1,460.00** | **₹1,460.00** | **₹720.00** | **₹300.00** | **₹32,440.00** |

- **Reconciliation Proof**:
  - Independent Expectation: ₹32,440.00
  - DB `purchase_invoices.total`: ₹32,440.00
  - Real Excel Export (`p5_gst_purchase_summary.xlsx`): ₹32,440.00
  - Stock Inflow: Item 111 (+50), Item 112 (+80), Item 113 (+90)
  - Difference: **₹0.00 (EXACT MATCH)**

---

## 3. Sales Scenarios & Reconciliation (Phase 5D)

Five real sales bills were posted through `SalesBillController::store()`:

| Document | Bill Number | Type | Customer | Classification | Items Sold | Taxable | CGST | SGST | IGST | Total Amount |
| :--- | :--- | :---: | :--- | :---: | :--- | :---: | :---: | :---: | :---: | :---: |
| **SB 1** | `SB-2026-0735` | Local | P5 B2B Local Corp (18) | B2B | 5 @ 0%, 10 @ 5%, 10 @ 18% | ₹3,561.50 | ₹219.25 | ₹219.25 | ₹0.00 | ₹4,000.00 |
| **SB 2** | `SB-2026-0736` | Interstate | P5 B2B Interstate Ltd (19) | B2B | 10 @ 18% | ₹2,118.64 | ₹0.00 | ₹0.00 | ₹381.36 | ₹2,500.00 |
| **SB 3** | `SB-2026-0737` | Local | P5 Walk-in B2C (20) | B2CS | 5 @ 5%, 5 @ 18% | ₹1,630.75 | ₹109.63 | ₹109.62 | ₹0.00 | ₹1,850.00 |
| **SB 4** | `SB-2026-0738` | Local | P5 Walk-in B2C (20) | B2CS | 5 @ 0%, 10 @ 5%, 10 @ 18% | ₹3,561.50 | ₹219.25 | ₹219.25 | ₹0.00 | ₹4,000.00 |
| **SB 5** | `SB-2026-0739` | Interstate | P5 Interstate B2C HighVal (21) | **B2CL** | 10 @ 18% (₹26,000/ea) | ₹220,338.98 | ₹0.00 | ₹0.00 | ₹39,661.02 | ₹260,000.00 |
| **TOTALS** | | | | | | **₹231,211.37** | **₹548.13** | **₹548.12** | **₹40,042.38** | **₹272,350.00** |

- **Reconciliation Proof**:
  - Independent Expectation: ₹272,350.00
  - DB `sales_bills.total`: ₹272,350.00
  - Real Excel Export (`p5_gst_sales_taxwise.xlsx`): ₹272,350.00
  - Stock Deductions: Item 111 (-10), Item 112 (-25), Item 113 (-45)
  - Difference: **₹0.00 (EXACT MATCH)**

---

## 4. Sales Returns & Over-Return Prevention (Phase 5E)

Tested using `SalesReturnController::store()` on both registered and unregistered bills:

1. **Partial Return 1 against SB 4 (`SB-2026-0738`)**:
   - Return Number: `SR-2026-0007` (id=18)
   - Returned: 3 units of Item 112 (5% GST)
   - Value: ₹360.00 (Taxable: ₹342.86, CGST: ₹8.57, SGST: ₹8.57)
2. **Partial Return 2 against SB 4 (`SB-2026-0738`)**:
   - Return Number: `SR-2026-0008` (id=19)
   - Returned: 4 units of Item 112 (5% GST)
   - Value: ₹480.00 (Taxable: ₹457.14, CGST: ₹11.43, SGST: ₹11.43)
3. **Over-Return Enforcement**:
   - Original sold on SB 4 = 10 units. Total returned so far = 3 + 4 = 7 units. Remaining returnable = 3 units.
   - Cashier requested: 5 units.
   - System response: **HTTP ValidationException REJECTED** with exact message:
     `{"items":["Return quantity cannot exceed the remaining returnable quantity of 3."]}`
   - Result: **PASS**
4. **Registered B2B Return (CDNR test) against SB 1 (`SB-2026-0735`)**:
   - Return Number: `SR-2026-0009` (id=20)
   - Customer: P5 B2B Local Corp (`24AABCP1234A1Z5`)
   - Returned: 2 units of Item 113 (18% GST)
   - Value: ₹500.00 (Taxable: ₹423.73, CGST: ₹38.14, SGST: ₹38.13)
   - Classified into GSTR-1 **CDNR**: Confirmed.

---

## 5. Purchase Returns & Over-Return Prevention (Phase 5F)

Tested using `PurchaseReturnController::store()` against `PI 2` (`PINV00017`):

1. **Partial Purchase Return 1**:
   - Return Number: `PRN000006` (id=19)
   - Returned: 5 units of Item 113
   - Value: ₹1,180.00 (Taxable: ₹1,000.00, IGST: ₹180.00)
2. **Partial Purchase Return 2**:
   - Return Number: `PRN000007` (id=20)
   - Returned: 10 units of Item 113
   - Value: ₹2,360.00 (Taxable: ₹2,000.00, IGST: ₹360.00)
3. **Purchase Over-Return Enforcement**:
   - Original purchased on PI 2 = 20 units. Total returned so far = 15 units. Remaining returnable = 5 units.
   - User requested return: 10 units.
   - System response: **HTTP ValidationException REJECTED** with exact message:
     `{"items":["Return quantity cannot exceed the remaining returnable quantity of 5."]}`
   - Result: **PASS**

---

## 6. Stock Transfers & Inventory Equation (Phase 5G)

Tested multi-branch transfer using `StockTransferController` between Branch 1 (GLOBAL) and Branch 2 (URBANPETS SERVICES PRIVATE LIMITED):

1. **Transfer Out (Dispatch)**:
   - Transfer Number: `STF00010` (id=10)
   - Item: `P5-ITM-0GST` (10 units)
   - Stock at Branch 1: Decreased from 40 to 30 units.
   - Stock at Branch 2: Unchanged at 0 units (in-transit state verified).
2. **Transfer In (Receipt)**:
   - Document received at Branch 2.
   - Stock at Branch 2: Incremented from 0 to 10 units.
   - Status updated to `Received`.

### Full Inventory Equation Verification
$$Opening + Purchases - Sales - PurchaseReturns + SalesReturns - TransferOut + TransferIn = FinalStock$$

| Item | Branch | Opening | Purchases | Sales | PR | SR | Transfer Out | Transfer In | Expected Stock | Actual Stock | Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **P5-ITM-0GST** | 1 | 0.0 | +50.0 | -10.0 | 0.0 | 0.0 | -10.0 | 0.0 | **30.00** | **30.00** | **PASS** |
| **P5-ITM-0GST** | 2 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | +10.0 | **10.00** | **10.00** | **PASS** |
| **P5-ITM-5GST** | 1 | 0.0 | +80.0 | -25.0 | 0.0 | +7.0 | 0.0 | 0.0 | **62.00** | **62.00** | **PASS** |
| **P5-ITM-5GST** | 2 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | **0.00** | **0.00** | **PASS** |
| **P5-ITM-18GST** | 1 | 0.0 | +90.0 | -45.0 | -15.0 | +2.0 | 0.0 | 0.0 | **32.00** | **32.00** | **PASS** |
| **P5-ITM-18GST** | 2 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.0 | **0.00** | **0.00** | **PASS** |
| **TOTAL** | All | **0.0** | **+220.0** | **-80.0** | **-15.0** | **+9.0** | **-10.0** | **+10.0** | **134.00** | **134.00** | **PASS** |

Stock Ledger Running Balances matched `item_stocks.quantity` exactly with 0 discrepancy.

---

## 7. Real Excel Exports Verification (Phase 5H & 5I)

Generated real `.xlsx` files using Laravel-Excel on staging and verified binary contents using PhpSpreadsheet:

### GST Sales Taxwise Excel (`p5_gst_sales_taxwise.xlsx`)
- **Columns**: Exactly **26 columns** matching client order:
  `Bill No`, `Bill Date`, `Customer Name`, `GST No.`, `State Name`, `taxable_0_amount`, `taxable_5_amount`, `taxable_18_amount`, `igst_5_amt`, `sgst_5_amt`, `cgst_5_amt`, `igst_18_amt`, `cgst_18_amt`, `sgst_18_amt`, `Total amount`, `Inv Noble_0_amount`, `taxable_5_amount`, `taxable_18_amount`, `igst_5_amt`, `sgst_5_amt`, `cgst_5_amt`, `igst_18_amt`, `cgst_18_amt`, `sgst_18_amt`, `Total amount`, `Inv No`.
- **Row-Level Mapping**: 1 Bill = 1 Row. No duplicate bills, no dropped rows.
- **Totals**: ₹272,350.00.

### GST Purchase Summary Excel (`p5_gst_purchase_summary.xlsx`)
- **Columns**: Exactly **16 columns** matching client order:
  `Inv No`, `Inv date`, `Supplier name`, `GST No.`, `State Name`, `Taxable amount`, `Purchase tax %`, `SGST Perc`, `SGST TaxAmt`, `CGST Perc`, `CGST TaxAmt`, `IGST Perc`, `IGST TaxAmt`, `Total amount`, `Freight charges`, `TCS Amt`.
- **Row-Level Mapping**: 1 Purchase Invoice = 1 Row.
- **Multi-Rate Display**: Rates for mixed-rate invoice correctly presented as `"0, 5, 18"` and `"5, 18"`.
- **Totals**: ₹32,440.00.

---

## 8. GSTR-1 12-Section Classification Audit (Phase 5J)

| Section | Classification | Count | Taxable Value | Tax Amount | Total Value | Data Integrity / Limitation Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **1. HSN B2B** | REAL DATA | 3 groups | ₹5,680.14 | ₹819.86 | ₹6,500.00 | Grouped by HSN (0401, 1006, 1905) for registered customers. |
| **2. HSN B2C** | REAL DATA | 3 groups | ₹225,531.23 | ₹40,318.77 | ₹265,850.00 | Grouped by HSN for unregistered customers. Reconciles with B2CL + B2CS. |
| **3. B2B** | REAL DATA | 2 bills | ₹5,680.14 | ₹819.86 | ₹6,500.00 | Invoice-wise breakdown for customers with valid GSTIN. |
| **4. B2CL** | REAL DATA | 1 bill | ₹220,338.98 | ₹39,661.02 | ₹260,000.00 | Real rule verified: Unregistered + Interstate + Bill Total $\ge$ ₹2,50,000. |
| **5. Exports** | UNSUPPORTED | 0 | ₹0.00 | ₹0.00 | ₹0.00 | Schema has no export/shipping bill fields. Not fabricated. |
| **6. B2CS** | REAL DATA | 2 bills | ₹5,192.25 | ₹657.75 | ₹5,850.00 | Unregistered intra-state or under threshold bills aggregated by rate. |
| **7. CDNR** | REAL DATA | 1 note | ₹423.73 | ₹76.27 | ₹500.00 | Credit note issued against registered customer bill (`SB-2026-0735`). |
| **8. CDNUR** | REAL DATA | 2 notes | ₹800.00 | ₹40.00 | ₹840.00 | Credit notes issued against unregistered customer bill (`SB-2026-0738`). |
| **9. Nil Rated** | PARTIAL DATA | 0 | ₹0.00 | ₹0.00 | ₹0.00 | Single bucket (`invoice_type = 'Exempted'`); Nil vs Exempt vs Non-GST not split. |
| **10. Advance Received**| UNSUPPORTED | 0 | ₹0.00 | ₹0.00 | ₹0.00 | No advance table exists in schema. |
| **11. Advance Adjusted**| UNSUPPORTED | 0 | ₹0.00 | ₹0.00 | ₹0.00 | No advance table exists in schema. |
| **12. Documents Issued**| REAL DATA | 8 docs | N/A | N/A | N/A | Series ranges: 5 Sales Bills (`SB-2026-0735` to `SB-2026-0739`), 3 Credit Notes (`SR-2026-0007` to `SR-2026-0009`). 0 Cancelled. |

---

## 9. Period Filtering & Historical GSTIN (Phase 5K & 5L)

- **Period Filtering**:
  - Tested single-day period (`2026-09-28` to `2026-09-28`): Exactly 5 sales bills, 3 purchases.
  - Tested previous month period (`2026-08-01` to `2026-08-31`): 0 Phase 5 documents appeared.
  - Date leakage: **0 records (PASS)**.
- **Historical GSTIN Preservation**:
  - Mutated customer master GSTIN on `P5 B2B Local Corp` from `24AABCP1234A1Z5` to `24AABCP9999Z9Z9`.
  - Re-queried historical GSTR-1 for bill `SB-2026-0735`: Output remained `24AABCP1234A1Z5`.
  - Result: **Posting-time snapshot preserved immutably (PASS)**. Master restored.

---

## 10. Database Integrity & Performance (Phase 5M & 5O)

- **Database Integrity**:
  - Orphan invoice items: 0
  - Orphan sales items: 0
  - Orphan return items: 0
  - Duplicate document numbers: 0
  - Duplicate posting keys: 0
  - Document total mismatches: 0
  - Phase 5 item negative stocks: 0
- **Performance Sanity (Staging Live Metrics)**:
  - Item lookup latency: **0.54 ms**
  - Customer lookup latency: **0.47 ms**
  - GST Sales report query: **3.24 ms**
  - GST Purchase report query: **2.58 ms**
  - Full GSTR-1 12-section compute: **11.88 ms**
  - Real Excel generation & disk write: **96.16 ms**
  - Peak memory usage: **36 MB**

---

## 11. Known Schema Limitations (Documented, Not Fabricated)

1. **Advances (Received/Adjusted)**: No advances table exists in the database schema. Standard sales bill tender records are not GST advances.
2. **Export Supplies**: No export shipping bill or port code fields exist in the database.
3. **Nil vs Exempted vs Non-GST**: The schema provides only one bucket (`sales_bills.invoice_type = 'Exempted'`); statutory 3-way split is not distinguishable in current DB structure.
4. **Pre-Existing Negative Stock**: 3 legacy records with negative stock (`item_id=40`, `item_id=38`, `item_id=10`) exist from historical operations prior to Phase 5. Phase 5 items maintain zero negative stock.

---

## Final Verification Checklist

- [x] All supported business scenarios pass
- [x] Stock equation reconciles across all tested items and branches
- [x] DB totals reconcile
- [x] GST Sales report reconciles
- [x] GST Sales Excel reconciles
- [x] GST Purchase report reconciles
- [x] GST Purchase Excel reconciles
- [x] GSTR-1 supported sections reconcile
- [x] Period filtering passes with zero leakage
- [x] Historical GSTIN snapshot passes
- [x] DB integrity passes with zero unexpected violations
- [x] Zero unexplained numerical mismatch exists
- [x] Unsupported schema features clearly documented
- [x] Staging used exclusively, production untouched

---

**PHASE 5 STATUS: PASS**  
**Git SHA Tested**: `e644567`  
**Run ID**: `PHASE5-STAGING-20260928-171500`  
**Total Checks**: 52  
**Passed**: 52  
**Failed**: 0  
**Production Touched**: **NO**  
