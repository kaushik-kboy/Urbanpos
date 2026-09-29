# URBANPOS — STAGING UAT CHECKLIST

**Target Environment:** Staging (`https://pos.ramdevcar.shop`)  
**Server IP:** `178.16.136.126:65002`  
**Git Baseline Commit:** `fd863ad4c629f29c40e8505d69077ac6b174a618`  
**Execution Date:** 2026-09-29  
**Result Status:** **PASS WITH LIMITATION (Deployment Packaging Gate Pending)**  

---

## Evaluation Standard
A workflow is marked **PASS** only if:
$$\text{UI Layer} + \text{Business Rule Validation} + \text{Database Integrity} + \text{Stock Ledger} + \text{Accounting Double-Entry} = \text{CORRECT}$$

---

## Phase-by-Phase Checklist

### Phase 1: Freeze & Deploy Exact Baseline
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Git SHA Match** | Match `fd863ad4c629f29c40e8505d69077ac6b174a618` | `fd863ad4c629f29c40e8505d69077ac6b174a618` | **PASS** | `git rev-parse HEAD` matched identically |
| **PHP Version** | PHP 8.2+ supported | PHP 8.3.33 | **PASS** | CLI & FPM confirmed |
| **Laravel Version** | Laravel 11.x | 11.56.1 | **PASS** | Framework version verified |
| **Database Engine** | MariaDB / MySQL 8.0+ | 11.8.9-MariaDB-log | **PASS** | MySQL driver active |
| **APP_ENV** | Set to staging | `staging` | **PASS** | Verified via configuration |
| **APP_DEBUG** | Disabled (`false`) | `false` | **PASS** | Stack traces hidden |
| **APP_TIMEZONE** | Canonical `Asia/Kolkata` | `Asia/Kolkata` | **PASS** | Verified in `.env` and `app.timezone` |
| **OPcache** | Enabled | `ENABLED` | **PASS** | `opcache.enable = 1` |
| **Production Untouched** | Production domain untouched | `indianpetcompany.com` not accessed | **PASS** | Strict host boundary enforced |

---

### Phase 2: Staging Health Check
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Login Page** | HTTP 200, renders cleanly | HTTP 200 OK | **PASS** | `curl -k -s -o /dev/null -w '%{http_code}' https://pos.ramdevcar.shop/login` returned 200 |
| **Security (.env)** | Block external access (403) | HTTP 403 Forbidden | **PASS** | Verified via public curl |
| **Security (.git)** | Block external access (403) | HTTP 403 Forbidden | **PASS** | Verified via public curl |
| **Storage Permissions**| Writable storage & cache | WRITABLE | **PASS** | `storage` and `bootstrap/cache` verified |
| **Database Connection**| Active MySQL connection | MariaDB connected | **PASS** | PDO connection active |
| **Assets & Bundles** | CSS/JS loaded properly | Loaded | **PASS** | Asset bundles verified |

---

### Phase 3: Real Staging Data Audit
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **No Database Wipe** | Preserve all historical staging records | Zero records deleted | **PASS** | All 20 users, 17 suppliers, 18 customers intact |
| **Historical Purchase Invoices** | Existing PIs open with line items | 29 records intact | **PASS** | Tested latest PI #45 (`PINV00031`) with items |
| **Historical Sales Bills** | Existing Bills open with line items | 60 records intact | **PASS** | Tested latest Bill #67 (`SB-2026-0896`) with items |
| **Historical Purchase Returns** | Existing PRs open cleanly | 11 records intact | **PASS** | Tested latest PR #24 (`PRN000011`) |
| **Historical Stock Transfers** | Existing STs open cleanly | 10 records intact | **PASS** | Tested latest ST #10 (`STF00010`) |
| **Stock Ledger Volume** | Historical ledger movements intact | 297 rows intact | **PASS** | Covers 10 distinct movement types |

---

### Phase 4: Purchase Flow UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Expired Product Invariant** | Past expiry rejected on Purchase Invoice | Yesterday date blocked | **PASS** | `lt($todayIndia)` triggered validation error |
| **Future Expiry Invariant** | Future expiry permitted | Allowed | **PASS** | 6-month future date passes |
| **Today Expiry Invariant** | Today's expiry permitted | Allowed | **PASS** | Current date passes |
| **Supplier Isolation (Ankit/Rahul)** | Ankit cannot return Rahul's stock | Strictly blocked | **PASS** | Ankit returning Batch B rejected |
| **Invoice Return Ceiling** | Cannot return more than invoice qty | Blocked at 21 and 30 | **PASS** | Requested 21/30 against purchased 20 rejected |
| **Normal Supplier Return** | Exact purchased quantity permitted | Allowed | **PASS** | Ankit returning 20 of Batch A passes |

---

### Phase 5: Sales Flow UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Customer Selection** | Loads existing customer metadata | Functional | **PASS** | Tested with customer #13 (Ankit) |
| **Item Lookup** | Barcode / Code / Name lookup | Sub-1ms | **PASS** | Real item lookup executed |
| **Stock Deduction** | Deducts stock via StockLedgerService | Append-only ledger | **PASS** | Posts `SALE` movement |
| **Tax Engine** | Correct CGST / SGST calculation | Matches spec | **PASS** | TaxEngine calculation verified |

---

### Phase 6: Tender Modal UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Payment Modes Present** | Cash, Card, Credit, UPI, RRN | Mode inputs present | **PASS** | All modes stored in DB |
| **Keyboard Shortcuts** | Alt+C, Alt+D, Alt+E, Alt+U | Active | **PASS** | Event listeners verified |
| **Top Mode Choice Bar** | Top clickable pills for quick selection | In Local Tree | **PASS WITH LIMITATION** | Local working copy verified; pending commit push to staging |

---

### Phase 7: Multi-Batch Inventory Lifecycle UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Initial Purchase** | Batch A (20), Batch B (10) | A=20, B=10 | **PASS** | `BatchStockService` reconciled |
| **Sale Movement** | Sell 5 of Batch A | A=15, B=10 | **PASS** | `SALE` movement posted |
| **Stock Transfer** | Transfer 3 of Batch B | A=15, B=7 | **PASS** | `TRANSFER_OUT` posted |
| **Damage Stock** | Damage 2 of Batch A | A=13, B=7 | **PASS** | `DAMAGE` posted |
| **Stock Update** | Shortage 1 on Batch B | A=13, B=6 | **PASS** | `SHORTAGE` posted |
| **Purchase Return** | Return 2 of Batch A | A=11, B=6 | **PASS** | `PURCHASE_RETURN` posted |
| **Sales Return** | Customer returns 2 of Batch A | A=13, B=6 | **PASS** | `SALE_RETURN` posted |
| **Final Stock Agreement** | Total ItemStock matches 19 | Matched (19.0) | **PASS** | `ItemStock` == Sum(Batches) |

---

### Phase 8: Stock Transfer UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Multi-Batch Independence** | Batches transfer independently | Verified | **PASS** | Transferring Batch B does not deplete Batch A |
| **Batch Stock Ceiling** | Over-quantity transfer blocked | Blocked | **PASS** | Checked available batch stock |

---

### Phase 9: Damage Stock UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Normal Damage Entry** | Deducts target batch stock | Verified | **PASS** | Deducts intended batch |
| **Over-Quantity Damage** | Cannot damage more than batch stock | Blocked | **PASS** | Rejection enforced |

---

### Phase 10: Stock Update UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Batch Row Separation** | Different batches render separately | Functional | **PASS** | System qty per batch preserved |
| **Zero/Negative Stock Checkbox** | Filter modal by stock $\le 0$ | In Local Tree | **PASS WITH LIMITATION** | Feature verified locally; pending commit push |

---

### Phase 11: Global Reset Table UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Sales Bill Reset** | `#sb-btn-reset-table` | Functional | **PASS** | Button present and operational on staging |
| **Other 11 Modules Reset** | Buttons above table across 11 modules | In Local Tree | **PASS WITH LIMITATION** | Implemented & verified locally; pending commit push |

---

### Phase 12: Item Lookup UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Item Search Response** | Sub-250ms lookup | 0.68 ms | **PASS** | Sub-millisecond performance |
| **Indexed Code Lookup** | Sub-150ms lookup | 0.63 ms | **PASS** | Sub-millisecond performance |

---

### Phase 13: Master Modules UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Master Items Listing** | Loads 64 items cleanly | Operational | **PASS** | HTTP 200 OK |
| **Item Code Column** | Displayed next to ID | In Local Tree | **PASS WITH LIMITATION** | Blade template updated in local tree; pending commit push |
| **Brand & Category Filters** | Search and status filter bar | In Local Tree | **PASS WITH LIMITATION** | Controllers and views updated locally; pending commit push |

---

### Phase 14: Table / UI Layout UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **AdminLTE Sidebar Collapse** | Default collapsed globally | In Local Tree | **PASS WITH LIMITATION** | `config/adminlte.php` updated locally; pending commit push |
| **Table Colspan & Footers** | Dynamic colspans and alignments | Operational | **PASS** | Evaluated cleanly across views |

---

### Phase 15: Reports & GST UAT
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **GST Purchase Summary** | Real data aggregated correctly | Operational | **PASS** | Route loads and exports properly |
| **GSTR-1 Report** | Real data aggregated correctly | 3.17 ms | **PASS** | 50-bill retrieval under 4ms |

---

### Phase 16: Date & Time Configuration
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **Canonical Timezone** | `Asia/Kolkata` active | Active | **PASS** | Verified in `.env` and `app.timezone` |
| **No UTC Boundary Drift** | Date boundary checks aligned with IST | Aligned | **PASS** | `Asia/Kolkata` start of day used |

---

### Phase 17: Security & Production Safety
| Check Item | Requirement | Staging Reality | Status | Evidence / Notes |
|---|---|---|---|---|
| **APP_DEBUG Disabled** | `APP_DEBUG=false` | Confirmed | **PASS** | No sensitive exception dumps |
| **Hidden Files Inaccessible** | `.env` and `.git` return 403 | Confirmed | **PASS** | Public HTTP 403 verified |
| **Production Isolated** | `indianpetcompany.com` untouched | Untouched | **PASS** | Only staging host accessed |

---

### Phase 18: Performance Sanity
| Check Item | Threshold | Staging Measured Time | Status |
|---|---|---|---|
| **Item Name Search** | $< 250\text{ ms}$ | **0.68 ms** | **PASS** |
| **Item Code Lookup** | $< 150\text{ ms}$ | **0.63 ms** | **PASS** |
| **Customer Name Search** | $< 150\text{ ms}$ | **1.40 ms** | **PASS** |
| **Supplier Name Search** | $< 150\text{ ms}$ | **0.56 ms** | **PASS** |
| **Stock Ledger Aggregation** | $< 250\text{ ms}$ | **0.93 ms** | **PASS** |
| **GSTR-1 50-Bill Query** | $< 400\text{ ms}$ | **3.17 ms** | **PASS** |
