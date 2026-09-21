# UrbanPOS — Master System Requirements Specification (SRS) & Architecture Plan

**Document Version:** 2.0  
**Target System:** UrbanPOS Retail & ERP System  
**Document Purpose:** Complete functional, non-functional, database, UI/UX, and architectural blueprint for drafting technical documentation, user manuals, and client SRS reports.  
**Deployment URL:** `https://pos.ramdevcar.shop`  
**Repository Branch:** `main`  

---

## 1. Executive Summary & System Overview

UrbanPOS is a modern, high-speed, enterprise-grade Point of Sale (POS) and Retail Enterprise Resource Planning (ERP) platform. Engineered specifically for dynamic retail environments (supermarkets, pet shops, department stores, and multi-branch retail chains), the system unifies fast barcode checkout, inventory tracking, multi-batch expiry handling, multi-tender payments, Indian GST compliance, customer loyalty, WhatsApp billing, double-entry accounting, and real-time business intelligence.

---

## 2. Technical Stack & Architecture

| Layer | Technology / Framework | Description & Key Responsibilities |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 11.x (PHP 8.3+) | REST APIs, business validation, transaction handling, role-based security. |
| **Database** | MySQL 8.0 / MariaDB (SQLite for Unit Tests) | Relational engine with foreign key constraints, indexes on barcodes, items, and transactions. |
| **Frontend UI/UX** | AdminLTE 3 / Bootstrap 4 & Vanilla JS | Clean responsive layout, zero bloat, high-performance DOM manipulation. |
| **POS Terminal Engine** | Vanilla JavaScript + Hotkeys Engine | Zero-dependency event-driven POS engine designed for sub-50ms barcode processing. |
| **Testing & CI Guards** | PHPUnit 11 + Vitest 5.0 + Custom Pre-Push Hook | Strict automated guards ensuring 100% regression safety before git push. |
| **Hardware Support** | USB HID Scanners, Thermal Printers (ESC/POS 80mm/58mm), Cash Drawers | Hardware agnostic keyboard emulation and thermal printing via raw browser drivers. |

---

## 3. Core Module: POS Terminal Engine (`/pos`)

The POS screen is optimized for zero-mouse operations, enabling cashiers to complete high-volume billing in seconds using keyboard shortcuts and high-speed barcode scanning.

### 3.1. Keyboard Shortcuts & Hotkeys Matrix

| Hotkey | Action | Description |
| :--- | :--- | :--- |
| **F1** | Help Guide Modal | Opens interactive popup displaying keyboard shortcut cheat sheet. |
| **F2** | Item Lookup Popup Modal | Fast searchable popup by Item Name, Code, Barcode, Expiry with branch stock and pricing. |
| **F3** | Quick Customer Add Modal | Fast popup to register new customers without leaving the billing screen. |
| **F4** | Item Discount / Bill Discount | Quick focus to discount input for line-item or bill-level discounts. |
| **F6** | Hold Current Bill | Saves the active cart in memory/session so another customer can be served. |
| **F7** | Recall Held Bill | Opens modal of held carts with customer name, item count, and time to restore. |
| **F8** | Multi-Tender Payment Modal | Opens split payment modal (Cash, UPI, Card, Credit/Wallet). |
| **F9** | Quick Cash Checkout | Instantly pays full amount via Cash, creates sales bill, prints receipt. |
| **F10** | Reprint Last Receipt | Quick reprint of the most recent finalized sales invoice. |
| **Arrow Up / Down** | Table / Modal Navigation | Smooth keyboard scrolling across cart rows and search modal rows. |
| **Delete / Backspace**| Remove Selected Row | Removes active empty or highlighted line item. |
| **ESC** | Close Modal / Cancel | Closes any open modal (F2, F8, F1) and returns focus to barcode input. |

### 3.2. Barcode Scanning & Item Lookup Engine
- **Hardware Scanner Guard**: Sub-100ms rapid keystroke interceptor distinguishes hardware barcode guns from manual cashier typing.
- **Auto-Quantity Increment**: Re-scanning the same barcode increments quantity by 1 without creating duplicate rows.
- **F2 Item Search Modal**:
  - Real-time search across: Name, Item Code, Barcode, Expiry Date.
  - Displays: Item Name, Barcode, Available Branch Stock, Sell Price, MRP, Expiry Date.
  - Keyboard Navigation: `ArrowDown` / `ArrowUp` to highlight item, `Enter` or double click to immediately add to POS cart.

### 3.3. Multi-Tender Payment & Settlement
- **Supported Payment Modes**: Cash, UPI (QR code scan), Credit/Debit Card, Customer Credit Ledger, Store Loyalty Points.
- **Split Payment Calculations**:
  - Cashier can split a single bill across multiple tenders (e.g. ₹500 Cash + ₹1200 UPI).
  - Live calculations for Tendered Amount, Paid Amount, Balance / Change to Return.
  - Strict validation preventing bill closure if Paid Amount is less than Total Bill Value.

### 3.4. Bill Hold & Recall System
- Multi-cart session holding allows cashiers to hold up to 10 active bills simultaneously.
- Restores item quantities, batch selections, customer details, and applied discounts upon recall.

### 3.5. Thermal Receipt Printing & WhatsApp Billing
- **ESC/POS Thermal Printing**: Optimized 80mm & 58mm receipt layout with store header, GST breakdown, itemized table, savings summary, and QR code.
- **WhatsApp Integration (ChatOnClick)**: Single-click or automated dispatch of digital PDF bill and greeting directly to customer's WhatsApp mobile number.

---

## 4. Sales & Distribution Management

### 4.1. Sales Bill (`/sales/sales-bills`)
- Full backend sales invoice engine with GST computation (IGST for interstate, CGST + SGST for intrastate).
- **Batch Expiry Selection**:
  - Single batch available: Automatically populates expiry date and selling price.
  - Multiple batches: Opens batch selection modal with pricing, expiry date, and current batch stock.
- **Strict Negative Stock Prevention**: Configurable per item (`allow_negative_stock = 0`). Cashier is blocked from billing out-of-stock items unless explicitly enabled by management.
- **Customer Loyalty Points**: Automatic points accrual based on invoice total; real-time point deduction against billing amount.

### 4.2. Sales Returns (`/sales/sales-returns`)
- Bill-referenced return or direct walk-in return.
- Automatic restocking of returned goods to branch inventory.
- Credit Note generation or cash refund settlement.

### 4.3. Quotations, Sales Orders & Delivery Notes
- **Sales Quotations**: Quotation generation with validity periods.
- **Sales Orders**: Advance booking and order status tracking.
- **Delivery Notes (Challans)**: Goods dispatch notes linked to parent sales orders or independent deliveries.

---

## 5. Purchase & Vendor Supply Chain

### 5.1. Purchase Invoices (`/purchase/purchase-invoices`)
- Complete vendor billing with automated calculation of:
  - Basic Cost Price, Freight, Insurance, Packaging, Landing Cost.
  - Input GST Credit (CGST/SGST/IGST).
  - Batch number, Manufacturing date, Expiry date assignment.
- **Strict Supplier Invoice Matching Guard**:
  - Cashier/Purchaser enters `Supplier Invoice Amount`.
  - System validates live invoice total against vendor amount.
  - Saving is blocked if discrepancy exists, highlighting exact difference amount.

### 5.2. Purchase Indents, Orders & Receipt Notes (GRN)
- **Purchase Indent**: Branch replenishment request based on reorder levels.
- **Purchase Order (PO)**: Formal order to suppliers with agreed pricing.
- **Goods Receipt Note (GRN)**: Physical verification and gate entry of received goods before invoice posting.
- **Purchase Returns (Debit Note)**: Return of defective or expired items to suppliers with debit balance adjustment.

---

## 6. Master Data Architecture

### 6.1. Product & Item Master (`/master/items`)
- **Core Attributes**: Name, Item Code, Barcode (EAN/UPC), Brand, Category, Department, Sub-Category, UOM.
- **Dynamic Product Type Master (`product_types`)**:
  - Dedicated CRUD master allowing custom types (Standard, Serialized, Service Component, Gift Voucher, etc.).
- **Expiry & Batch Policy**: Configurable (`Not Required`, `Optional`, `Mandatory`, `Days`, `Month`).
- **Pricing & Margins**: Cost Price, Landing Cost, Min Sell Price, Max Sell Price, MRP, Tax Inclusion Flag.

### 6.2. Tax & GST Master Architecture
- **GST Tax Slabs (`gst_taxes`)**: Standard Indian GST rates (0%, 5%, 12%, 18%, 28%).
- **GST Type Master (`gst_types`)**:
  - Dedicated CRUD master for taxpayer types (`Regular`, `Composite`, `Unregistered`, `SEZ`, etc.).
  - Dynamically populates across Supplier, Customer, Branch, and POS quick forms.

### 6.3. Geographical Masters: Indian States & Dynamic Cities
- **State Priority**: Gujarat, Rajasthan, and Maharashtra pinned to the top of state dropdowns, followed alphabetically by all remaining 33 Indian States and Union Territories.
- **Cascading City Engine**: Dynamic JavaScript engine (`indian-states-cities.js`) instantly populates official cities based on selected state across all master and transaction forms.

### 6.4. Customer & Pet Profiles Master
- Comprehensive customer directory with contact details, GSTIN, credit limits, and credit days.
- Specialized Pet Profiles (Pet Type, Breed, Color, DOB, Vaccination details) for pet retail stores.

### 6.5. Supplier Master
- Vendor profile, GSTIN, Pan No, State, City, Bank Details, Payment Terms, Credit Limit, and historical ledger.

---

## 7. Inventory & Warehouse Management

### 7.1. Branch Stock Control
- Multi-branch stock segregation (`item_stocks` table indexed by `item_id` and `branch_id`).
- Real-time stock decrement on sales bill approval and increment on purchase invoice / return.

### 7.2. Stock Transfer & Inter-Branch Logistics
- Transfer Out (Dispatch from Warehouse/Branch) -> Transfer In (Receipt at Destination Branch).
- In-transit stock tracking to prevent inventory shrinkage.

### 7.3. Physical Stock Audit & Damage Adjustments
- **Stock Updates**: Reconciliation between physical shelf audit and system stock with supervisor approval workflow.
- **Damage Stocks**: Logging damaged, expired, or spoiled goods with reason codes and cost write-off.

---

## 8. Finance, Ledgers & Double-Entry Accounting

### 8.1. Chart of Accounts & General Ledger
- Automatic double-entry journal vouchers for Sales, Purchases, Receipts, and Payments.
- Dedicated ledgers for Debtors (Customers), Creditors (Suppliers), Cash, Bank, and Tax Accounts.

### 8.2. Bill Settlements & Outstanding Aging
- Outstanding invoice tracking for B2B wholesale customers and credit retail sales.
- Aging reports: 0-30 days, 31-60 days, 61-90 days, 90+ days.

### 8.3. Till Management & Cash Drawer Reconciliation
- Cashier shift opening float balance recording.
- Day-end cash counting, petty cash movements, and cash discrepancy logging.

---

## 9. Reports & Business Intelligence Engine

| Report Module | Endpoint | Key Insights Provided |
| :--- | :--- | :--- |
| **Sales Summary** | `/reports/sales-summary` | Daily, weekly, monthly sales totals, gross profit, margin %. |
| **Billwise Sales** | `/reports/billwise-sales` | Itemized bill logs, cashier ID, customer name, payment breakdown. |
| **GST Sales/Purchase** | `/reports/gst-sales-summary` | GSTR-1 & GSTR-3B ready tax summaries (Taxable value, CGST, SGST, IGST). |
| **Current Stock Report** | `/reports/current-stock` | Branch-wise available quantity, valuation (cost & retail), reorder alerts. |
| **Smart 360° Analytics** | `/reports/smart-analytics` | Top-selling items, high-value customers, slow-moving inventory analysis. |
| **Analytics Builder** | `/reports/analytics-builder` | Dynamic custom report designer with multi-column filtering and CSV exports. |

---

## 10. Quality Assurance, Security & Regression Safeguards

### 10.1. Automated Pre-Push Regression Guard (`.githooks/pre-push`)
Before any code can be pushed to GitHub or deployed to production, the automated git pre-push hook runs 4 levels of validation:
1. **Blade Syntax & Route Integrity Check**:
   - Executes `php artisan view:cache`.
   - Runs `BladeRouteIntegrityTest`: Scans all `.blade.php` files across the codebase to verify every `route('...')` call matches an active route in `routes/web.php`. Any broken route immediately blocks push.
2. **Core POS Regression Test Suite**:
   - Runs `composer test-pos` (verifies pricing, negative stock rules, discount calculations).
3. **Frontend & Hotkey Unit Tests**:
   - Runs `npm run test:frontend` via Vitest (tests keyboard shortcuts, scan guard, modal lifecycle).

### 10.2. Role-Based Access Control (RBAC)
- Fine-grained permissions (Spatie Laravel-Permission) for Super Admin, Admin, Store Manager, Cashier, and Accountant.
- Restricts critical actions (editing bills, manual discounts, negative stock overrides, viewing profit margins).

---

## 11. Hardware & Deployment Specifications

### 11.1. Recommended Hardware Environment
- **Client Terminals**: Any Windows / POSReady PC, All-in-One POS Machine, or Laptop (Core i3+, 4GB+ RAM).
- **Barcode Scanners**: 1D/2D Laser/CCD Scanners (Honeywell, Zebra, TVS, Datalogic) operating in USB HID Keyboard Emulation mode.
- **Printers**: 3-inch (80mm) or 2-inch (58mm) Thermal POS Printers (Epson TM-T82, TVS RP-3200, Posiflex, Xprinter).
- **Cash Drawer**: Standard RJ11/RJ12 drawer connected to printer kick-out port.

### 11.2. Production Infrastructure
- **Server**: Cloud VPS running Ubuntu 22.04 LTS.
- **Web Server**: Nginx / Apache with HTTP/2 and Let's Encrypt SSL.
- **PHP**: PHP 8.3 with OPcache enabled.
- **Database**: MySQL 8.0 with InnoDB buffer optimization.
- **Domain**: `https://pos.ramdevcar.shop`

---

## 12. Advanced Enterprise & Counter Security Modules (v2.1 Additions)

### 12.1. POS Quick Lock Screen (Counter Security)
- **Problem Addressed**: Counter abandonment vulnerabilities (cashier stepping away for physical stock retrieval or breaks, leaving open bills exposed to customer tampering or unauthorized discounts).
- **Triggers**:
  - **Manual Hotkey**: `Ctrl + L` triggers instant screen lock from anywhere on the POS terminal.
  - **Header Action**: One-click padlock icon button in the POS terminal navigation header (`#btnPosLockScreen`).
  - **Automatic Inactivity Lockout**: 3-minute idle watchdog auto-locks the screen if no mouse movement, touch, or keystrokes are registered.
- **State Integrity**:
  - Entire background DOM, active cart lines, selected customer, and held orders remain strictly preserved in memory.
- **4-Digit Quick PIN Security**:
  - Cashier enters their fast 4-digit numeric PIN (default auto-provisioned: `0000`).
  - Supports physical keyboard numbers, numpad, or responsive on-screen touch keypad.
  - **Rate Limiting**: Throttled to 5 consecutive attempts per minute with auto-lockout to prevent brute-force attacks.
  - Self-service "Change PIN" modal allowing cashiers to update their security PIN on-the-fly.

### 12.2. Dynamic UPI QR Code Generation (Counter & Thermal Receipts)
- **Problem Addressed**: Manual cashier typing of bill amounts on static UPI QR stands often causes transaction delays, typos, and revenue under-collection during peak rush hours.
- **Real-Time Dynamic QR Engine**:
  - Branch-specific VPA configuration (`upi_id`, `upi_payee_name`) stored on the active branch profile.
  - **POS Tender Modal (`F8`)**: As bill items are scanned or payment splits are adjusted, the system generates an NPCI-compliant URI:
    ```
    upi://pay?pa={VPA}&pn={PAYEE_NAME}&am={BILL_AMOUNT}&cu=INR&tn=Bill-{INVOICE_NO}
    ```
  - Instantly rendered on screen as a high-contrast QR code via Google Charts / native SVG QR generator.
  - **80mm Thermal Receipt Layout**: Embedded dynamic QR printed at the foot of each thermal receipt for fast post-checkout scan-and-pay reconciliation.

### 12.3. Direct Barcode Label & Sticker Printing System
- **Problem Addressed**: Complex third-party software setups historically required to print barcode stickers for newly received purchase consignments.
- **Direct Multi-Source Workflows**:
  - **Purchase Invoice Inflow**: Single-click "Print Stickers" directly from Purchase Invoice list and show views (`/purchase/purchase-invoices/{id}`). Replicates the exact received quantities for every line item in the shipment.
  - **Item Master On-Demand**: Custom quantity barcode printing directly from the Item Master index (`/master/items`).
- **Standard Industry Thermal Formats**:
  1. **50mm x 25mm (1-up Thermal Roll)**: Standard retail sticker with Store Name, Item Name, Barcode SVG, MRP, Selling Price, and Expiry Date.
  2. **38mm x 25mm (2-up Thermal Roll)**: Compact dual-column roll for small packaging, pet accessories, and cosmetics.
  3. **A4 Sheet (40 Labels / 4x10 Matrix)**: Standard Avery / sticker sheets designed for regular office laser and inkjet printers.
- **Vector Barcode Rendering**: Rendered via client-side `JsBarcode` emitting crisp SVG vectors to ensure 100% first-pass read rates on all 1D laser scanners.

### 12.4. Automated Database Backup & Disaster Recovery System
- **Problem Addressed**: Hardware failure, ransomware, or server-side data corruption without reliable zero-maintenance backups.
- **Dual-Engine CLI Command**: `php artisan db:backup {--clean}`
  - **Engine 1 (`mysqldump`)**: Automatically locates and invokes `mysqldump` on Windows Laragon (`C:\laragon\bin\mysql\*\bin\mysqldump.exe`) and standard Linux systems.
  - **Engine 2 (Streaming Fallback)**: High-speed PHP streaming exporter using `gzopen` and 500-row chunked database reads. Executes flawlessly in restricted shared hosting environments where `exec()` or `mysqldump` is disabled.
- **Retention & Automated Scheduling**:
  - Automated cron scheduled daily at `01:00 AM` (`routes/console.php`).
  - Automatic pruning of database backup archives older than 14 days.
- **Web Admin Console (`/tools/backups`)**:
  - Dedicated administrative dashboard under `Tools > Database Backups`.
  - Displays backup history, file sizes, creation timestamps, and download status.
  - Single-click **"Generate Backup Now"** action for immediate on-demand snapshots.
  - Authenticated and secure direct download and purge controls.
