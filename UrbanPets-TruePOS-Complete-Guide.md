# UrbanPets TruePOS – Complete System Guide

*GOFRUGAL RetailEasy OnCloud (TruePOS) + AccountsEasy OnCloud — how the system works, module by module.*

Prepared 05 Sep 2026 from a full walkthrough of https://urbanpets.true-pos.com/TruePOS/ (version 030926).

## Contents

- Part 0 – Overview
- Part 1 – Complete Menu Tree
- Part 2 – Master Module
- Part 3 – Sales Module
- Part 4 – Purchase Module
- Part 5 – Inventory Module
- Part 6 – Reports Module
- Part 7 – Tools Module
- Part 8 – Finance and Accounts (AccountsEasy)
- Part 9 – How the Modules Connect

---

# Part 0 – Overview

**Application:** GOFRUGAL RetailEasy OnCloud (TruePOS) + AccountsEasy OnCloud (TrueBooks)
**URL:** https://urbanpets.true-pos.com/TruePOS/ (build version 030926)
**Company:** URBANPETS SERVICES PRIVATE LIMITED — locations *URBANPETS SERVICES PRIVATE LIMITED* (HO, code 225) and *URBAN PETS / MOTERA* (code 32772)
**Documented on:** 04–05 Sep 2026, logged in as `admin1`, counter CO-225.

### How the system fits together

```
            MASTER (setup)                       TOOLS (rules)
  Item · Category · Brand · UOM         Business Configuration · Roles/Users
  Customer (+Pet) · Supplier · Tax      Function keys · Ledger Map · Register
  Branch · Register · Tender · Promo    Year-begin sequences · Mail · Migration
            │                                        │
            ▼                                        ▼
  PURCHASE ───────────► INVENTORY ◄──────────── SALES
  PO → Receipt Note →   Opening stock,          Quotation → Sales Order →
  Purchase Invoice      Damage, Stock update,   Delivery Note → Sales Bill
  Purchase Return       Repack, Kit, Price fix  Sales Return, Transfer Out
  Transfer In / Indent  Barcode / Shelf talker
            │                    │                    │
            └──────────► REPORTS (SmartReport) ◄──────┘
                                 │
                                 ▼
                  FINANCE & ACCOUNTS (AccountsEasy)
        auto-posted vouchers · bill-wise receipt/payment · bank recon · P&L / BS
```

### UI conventions (apply everywhere)
* **Top bar**: Master · Sales · Purchase · Inventory · Reports · Tools · Finance And Accounts; *Search Menu* box jumps to any screen; icons = theme, notifications, print, profile/logout.
* **Second bar**: sub-menu of the active module; overflow under *More ▾*.
* **Angular masters** (`/masters/`, `/angular-modules/`): list grid → `Add …` → tabbed form → Save/Cancel; row edit icon; paging 10–50/page; `View Active/Inactive` chip.
* **GWT transaction screens** (`com.gofrugal.raymedi.webpos.*`): header on top, item grid in the middle, totals on the right, Remarks/Message and function-key bar at the bottom. Standard keys: **F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close** (re-mappable in Tools → Function Key Mapping). Lookups open with ENTER/TAB. Leaving a screen with typed data asks for confirmation.
* **Location selector** at the top-right of transaction screens chooses which branch the document belongs to.
* **Report viewer**: funnel icon → Date / Standard / Advanced filters → Apply; export, mail, schedule, pivot, chart from the left toolbar.

### Key business facts discovered
* Two GST-registered locations; inter-branch stock moves through Transfer Out (HO) → Transfer In / Transfer In Touch (branch). A transfer (TO 3181) was pending receipt on 04-09-2026.
* Items are classified by three category heads: **Brands, DEPARTMENT, CATEGORY**; batch/expiry tracking is *Mandatory* by default (pet food).
* Customer master is extended with a **Pet Details** tab (Pet Type, Breed, Colour masters) and Sales Bill has **F1 Pet Details**.
* Tenders enabled: Cash, Credit Card, Wallet, RRN (return credit); Due bill, Cheque, Coupon, Finance are switched off.
* Running document numbers (FY start): Sales 57,844 · Purchase 2,388 · Sales Return 1,297 · Transfer Out 3,181 · Transfer In 3,180.
* Sales Order approval, Session (shift) management, Promotions, E-way bill and Transfer-Out approval are currently **disabled** in Business Configuration.
* Report Scheduler, Barcode Config, Category-wise sequences and GoFrugal Alerts/GST e-filing/GoSure integrations are not yet set up.

---

# Part 1 – Complete Menu Tree

Source: https://urbanpets.true-pos.com/TruePOS/ (version 030926)
Company: URBANPETS SERVICES PRIVATE LIMITED | Counter: CO-225 | Login user: admin1
Captured: 2026-09-04

Top bar (left to right): **Master | Sales | Purchase | Inventory | Reports | Tools | Finance And Accounts** + Search Menu box + Theme / Notifications / Print / Profile icons.
Each top menu shows a second-level horizontal bar; items that do not fit are under **More ▾**.

### 1. Master
- **Item**
  - Item Category
  - Item Category Value
  - Brand
  - Item
  - Item Property Setting
  - Item EAN/UPC Entry
  - Assembly
  - Kit Mapping
  - Item Price Change
  - UOM
  - UOM Vs Item Mapping
  - Tax Slab
- **Customer**
  - Customer Category
  - Customer
  - Area
  - Loyalty Program Info
  - Loyalty Points Update
  - Pet Masters → Pet Types, Breed Master, Color Master
- **Supplier**
  - Supplier
- **Tax**
  - GST Tax
  - GSTNo Restriction Master
- **Branch**
  - Branch
  - Distribution Centre Mapping
- **Register** (direct screen)
- **Promotion**
  - Promotion Management
- **More**
  - Tools → Master Configuration, Tender Type Values, Unicode Master, Tender Type, Master Attributes, Addon Devices Inactivation
  - Utility → Transporter, Freight Settings

### 2. Sales
- Sales Quotation
- Sales Order
- Sales Order Approval
- Delivery Note
- Sales Bill
- Sales Return
- Delivery Note Return
- **More** → Transfer Out, Transfer Out Approval and Auto TI

### 3. Purchase
- Purchase Order
- Receipt Note
- Purchase Invoice
- Purchase Returns
- PO Cancel
- Transfer In
- Indent
- **More** → Auto Indent, Indent Cancellation, Transfer In Touch, Indent CutOff Time Configuration

### 4. Inventory
- Opening Stock Entry
- Damage Stock Entry
- Stock Update Entry
- Stock Update Approval
- Barcode Printing
- Price Fixing → Price Fixing(markup/markdown), Price Level, Price Level Vs Items
- Change Selling
- **More** → Repack, Change Serial No, Price Drop, Kit Preparation, Kit Unpack, Shelf Talker

### 5. Reports
- **Masters**: Customer Master, UOM Vs Item Mapping, Kit Mapping, Customer Parent List, Customer Loyalty Details, Customer Pet Details, Supplier Master, Brand Master, Tax Master, Area, Branch Master, Item Master, Supplier Vs Items, Price List, Employee Master, RO Master
- **Purchase**
  - Transactions: Purchase Order Summary, Purchase Transit, Purchase Order Details, ReceiptNote Nowise Summary, ReceiptNote Nowise Detail, Purchase Summary, Purchase Detail, GIN Summary, Purchase Detail Serial, GIN Detail, GST Purchase Summary, Purchase Order vs Invoice Report, ReceiptNote Itemwise
  - Pending/Cancelled Transactions: Purchase Order Cancel
  - Returned Transactions: Purchase Return Supplierwise Detail, Purchase Return Summary
  - Purchase Analysis: Supplierwise Purchase Summary, PO/Purchase Discrepancy, Supplierwise Purchase Details, Datewise Itemwise Consolidated Purchase Report, Purchase Register Summary
- **Audit**: Foot Fall Details, Reprint Count Details, User Login Summary, Audit Viewer Report, Audit Detail Report, GST Tax Chaange Audit report, Service User Consent Summary, Email Audit Viewer Report, Audit Report (Cart entry Clear), Audit Detail (Cart entry Clear)
- **Sales**
  - Sales: Monthly Sales Summary [Storewise], Monthly Sales Summary [Till Wise], Serial Number Wise Price Details, Daily Sales Summary[Store Wise], Daily Sales Summary [Till Wise], Daily Sales [Bill No Wise], Billwise Itemwise Sales Detail, Daily Sales [Bill No Wise With Timefilter], Offline Sales Bill Details, Billwise Itemwise Sales Detail Serialwise, GST Sales Summary, GST Sales Taxwise, Billwise Itemwise Sales With Assembly Details, Tender Type Detail, Sales Register Summary, Offer Claim Report, Counterwise Sales Report, Sales Item Margin, Customerwise Itemwise Sales, Itemwise Customerwise Sales, Categorywise Sales, Categorywise Sales Detail, Customerwise Itemwise Points, Canceled Receipts
  - Orders And Quotation: Quotation Summary, Quotation Details, Sales Order Summary, Sales Order Detail, Sales Order Stock Status
  - Delivery Reports: Sales DeliveryNote Summary, Sales DeliveryNote Detail, Kitchen Preparation Report, Delivery Bill Summary Report, Delivery Bill Detail Report, Kitchen Preparation Report[Timewise]
  - Returned Transactions: Sales Return Advice Detail, Buy Back Details, Sale Return Customerwise Detail, Sales Return Itemwise, Sale Return Datewise, Sale Return Monthwise, Sales Return Summary
  - Cancelled Transactions: Bill Cancel Report
  - Sales Analysis: Margin Summary, Session Report, Supplier Sales Report, Supplierwise Sales Details, Date Timewise Sales, Consumption Sales Summary, Consumption Sales Detail, Areawise Sales Summary, Monthly Sales Detail, Non Purchase Customer List, Counterwise Datewise Sales Summary, Itemwise Monthly Sales Qty/Amt Details
  - Hold Transactions: Hold Bill Details, ItemWise Discount Approval Details
- **Inventory**
  - Stock Ledger: Itemwise Stock Statement, Itemwise Stock And Sales Detail, Itemwise Stock and Sales Detail Transit with Transit, Transactionwise Stock Register, Closing Stock, Current Stock Branchwise, Categorywise Datewise stock Report, Branchwise Stock Age Analysis, Categorywise Stock Age Analysis
  - Price Drop
  - Price Level Advance
  - Stock Movement: Transfer Out Approval Detail, Stock TransferOut Summary, Stock TransferOut Detail, Itemwise Storewise TransferOut Detail, Stock TransferIn Summary, Stock TransferIn Detail, Itemwise Storewise Transfer In Detail, Stock In Transit, Stock Transfer Discrepancy, TO Vs TIN, Stock Conversion Report
  - Stock Analysis: Stock Update Detail, Wastage/Damage Stock, Categorywise Storewise Current Stock Summary, Categorywise Storewise Current Stock Detail, Stock - Categorywise - FastMoving - Item, Stock - Categorywise - SlowMoving - Item, Wastage/Damage Stock Detail, Item Age Analysis, Item Expiry Update Details, Items Details In Cart, Stock Reserve Status
  - Stock Replenishment: MBQ Detail, Kit Preparation, Kit Unpack, Itemwise Stock Transfer Advice, Indent Based On Replenishment, Indent Summary, PO Replenishment, Picklist Detail, Picking List Discrepancy, Opening Stock Detail, Repack Summary, Eancode Detail, Repack Detail, Re-order Report, Issue Date Expiry Details
- **My Reports**: Counterwise Sales Summary, Sales MIS report, Off Take - Dealer Tracker Report
- **Production**: Production Plan, BOM Report, Production Costing Summary, Production Costing Detail, Sales/Production Variance
- **More**
  - Serialized Reports: Serial Number History, Serial No Wise Stock Detail
  - Offline Exported Reports
  - Dashboard
  - Monthly Transaction Summary

### 6. Tools
- **Configuration**
  - Security Configurations → Role Master, Employee Master
  - Userwise Configuration
  - Business Configuration
  - Function Key Mapping
  - Ledger Map
  - Asset LedgerMap
  - Mail Server Configuration
  - Category Wise Sequence
- Addon Provisioning
- **Integrations** → GoFrugal Alert, GST Efiling, GOFRUGAL Gosure
- Master Migration
- Document Upload Transactionwise
- Add On Service(s)
- Manage Subscription
- **More** → Year Begin Sequence Change, Session Management, Reprint, Multiple Dispatch, Report Scheduler, E-way Update, Barcode Config, Service User Consent

### 7. Finance And Accounts
- (no inline submenu – opens the separate Accounts module; see 07-finance-accounts.md)

---

# Part 2 – Master Module

The Master menu holds all reference data that transactions depend on: items, customers, suppliers, tax, branches, registers (billing counters), promotions and system lookups. Nothing is billed, purchased or moved here; these screens only *define* the data.

Two UI styles exist:
* **Angular list/form masters** (`/TruePOS/masters/index.html`) – list grid with `View Active/Inactive` chip, `Add <Master>` button, per-row edit icon and (for items) clone icon, search icon in header, paging (10/20/30/40/50 per page). The form opens as *Create <Master>* / *Edit "<name>"* with tabs and **Save / Cancel**.
* **Legacy GWT screens** (`/TruePOS/com.gofrugal.raymedi.*`) – keyboard-driven, buttons labelled with function keys (F4 Edit, F6 Save, F7 View, F9 Clear, F10 Close…).

---

### 1.1 Item group

#### 1.1.1 Item Category
Defines the **category heads** (dimensions) used to classify items. In this company three heads exist: **Brands, DEPARTMENT, CATEGORY**.

| Column | Meaning |
|---|---|
| Id | Internal id of the head |
| Name | Head name (e.g. DEPARTMENT) |
| Is Mandatory | Yes = every item must have a value for this head |
| Status | Active / Inactive |

#### 1.1.2 Item Category Value
The actual values under each head (e.g. DEPARTMENT = "Dog Food", CATEGORY = "Dry Food"). Grid: Image, Id, Name, Category Head, Status.

**Create Item Category Values form (tab General)**

| Field | Type | Purpose |
|---|---|---|
| Category Name | text | Value name shown on item, reports and filters |
| Category Head | dropdown | Which head this value belongs to (Brands / DEPARTMENT / CATEGORY) |
| Show in Webstore | Yes/No | Expose this category to the online store integration |
| Status | Yes/No | Active flag |
| SellQuick Applicable | Yes/No (read-only here) | Used by the SellQuick handheld app |
| Allowed Qty (in ml) | number (read-only) | Liquor-style quantity limit – not used for pets |

#### 1.1.3 Brand
Brand / manufacturer list. Grid: Name, Created Time, Updated Time.

| Field | Type | Purpose |
|---|---|---|
| Name | text | Brand or manufacturer name (Pedigree, Royal Canin…) |
| Status | Active/Inactive | |
| Prefix | text | Optional prefix used when auto-generating item codes/barcodes for this brand |
| Alias Code | text | Short code for imports/exports |

#### 1.1.4 Item  (core product master)
Grid: Image, Id, Name, Alias, Sell Price, Supplier, Created Time, Updated Time, search (edit), Clone Item. `View Active` chip toggles active/inactive items. `Add Item` opens the form.

**Create / Edit Item – tab General**

| Field | Type | Purpose |
|---|---|---|
| EAN/UPC Code | text | Primary barcode. Scanning this at POS pulls the item. Extra barcodes are added in *Item EAN/UPC Entry* |
| Item Name | text | Description printed on bill |
| Brand | dropdown + "Add new brand" | Links to Brand master |
| Supplier | dropdown + "Add new supplier" | Default supplier – used by PO replenishment and Supplier-wise reports |
| Product Type | Standard / Serialized / Service Component / Gift Voucher | Standard = normal stock item; Serialized = each unit has a serial no; Service Component = non-stock service (grooming); Gift Voucher = voucher sale |
| Cost Price | number | Purchase cost (read-only on edit – updated by purchase invoices) |
| Landing Cost | number | Cost + freight/other charges; margin reports use this |
| Sell Price | number | Default selling price at POS |
| MRP (Maximum Retail Price) | number | Printed MRP; Sell Price must not exceed it |
| Status | Active/Inactive | Inactive items cannot be billed |
| Store Pickup | No/Yes | Item available for "store pickup" in online orders |

**Tab Taxes** – `Tax Inclusive` (Yes/No): whether Sell Price already includes GST (Yes = price printed is final; No = GST added on top).

**Tab Sales**

| Field | Purpose |
|---|---|
| Batch/Expiry Details | Not Required / Optional / Mandatory / Days / Month – controls whether batch and expiry must be captured at purchase (pet-food expiry tracking). Default in this company = Mandatory |
| Shelf Life | number of days item stays saleable from mfg date |
| Minimum Shelf Life | minimum remaining shelf life accepted at receipt |
| Allow Negative Stock | Yes/No – permit billing when stock is zero |

**Tab Category** – one dropdown per category head: Brands, DEPARTMENT, CATEGORY (mandatory ones must be filled).

**Tab GST** – `GST Tax` (dropdown of GST tax master: GST 0%, Exempted, 5%, 12%, 18%, 28%, 28%+Cess…, 3%) and `HSN Code` (text).

#### 1.1.5 Item Property Setting (`angular-modules`)
Bulk grid editor for branch-wise item properties. Filters: **Based On** (Branch / Circle / Area / State / Item) and **Item Status** (All / Active / InActive). `Show Fields` opens *Select Fields to Display* with checkboxes: Minimum Quantity, Maximum Quantity, Sales, Purchase, Sales Return, Purchase Return, Allow Negative, Store Pickup, Tax Inclusive, Margin Perc, Shelf No, Rack No, Box No (+ Select All / Clear All).
Grid columns: S.no, Item Code, Item Name, Min Qty, Max Qty, Sales, Purchase, Sales Return, Purc Return, Allow Negative, Tax Incl, Store Pickup, Margin Perc, Shelf No, Rack No, Box No.
Use: set re-order Min/Max (MBQ) per branch, block sale/purchase of an item in a specific branch, set shelf/rack/box location for picking.

#### 1.1.6 Item EAN/UPC Entry
Lists items (S.no, Item Code, Item Name) with **Edit** → panel showing Item Code, Count, Item Name, Branch (default *All Locations*) and a sub-grid **S.No | EAN/UPC Code | Action** with `Add` to attach multiple barcodes to one item. Buttons Save / Cancel. Use when a product ships with several barcodes (different pack printings).

#### 1.1.7 Assembly
List of assembly (bundle) items: S.No, Item Code, Item Name, Alias, EAN Code. An assembly item is sold as one line but composed of component items (defined in *Kit Mapping*). Currently no records.

#### 1.1.8 Kit Mapping
Grid: S.no, Item Code, Kit Name, Quantity, Basic Cost, Selling Price. Maps component items and quantities to a kit/assembly item; Kit Preparation / Kit Unpack (Inventory module) convert stock between components and kit.

#### 1.1.9 Item Price Change
Grid Id, Name – pick an item to change its Cost / Sell / MRP outside the item form (price history is kept; reports *Price Drop* and *Price Level Advance* read from it).

#### 1.1.10 UOM (Conversion)
Unit-of-measure master. Grid: Name, Updated Time. Form: `Name` (e.g. Kg, Pcs, Box) and `Alias`.

#### 1.1.11 UOM Vs Item Mapping
Grid: Item Id, Item Name, Base Uom, Sub Uom, Uom Conversion – defines that 1 Box = N Pcs etc. so purchase can be in boxes and sale in pieces.

#### 1.1.12 Tax Slab
Amount-based tax slabs (used for items whose GST rate depends on price). Form: `Name` + rule rows **S.No | From Amount | To Amount | Tax (GST master dropdown)**.

---

### 1.2 Customer group

#### 1.2.1 Customer Category
Grid: Name, Created, Updated. Form:

| Field | Purpose |
|---|---|
| Name | e.g. WALK-IN, Regular, VIP, Breeder |
| App Access | Yes/No – allow customers of this category to use the customer app |
| Enable Loyalty | Yes/No – loyalty points accrue for this category |
| Discount Percent | default bill discount for this category |
| Status | Active/Inactive |
| Business Type | ALL / COCO / FRANCHISE / BRANCH / DISTRIBUTION CENTER / SERVICE UNIT / FOFO / ASP – which branch type this category applies to |

#### 1.2.2 Customer
Grid: Id, Name, Customer Id, Alias Code, Status, Created, Updated. Form has 4 tabs.

**Tab General**

| Field | Type / options | Purpose |
|---|---|---|
| Title | Mr / Ms / Mrs / M/s / Dr | |
| Name | text | Customer name |
| Category | dropdown (default WALK-IN) + "Add new category" | Drives discount and loyalty |
| Customer Id | text | Own code / phone-based id |
| Sales Type | Local / … | Local vs inter-state – decides CGST+SGST vs IGST |
| Payment Mode | Cash Only / No Credit / Credit Only / Both Cash and Credit / Cash on Delivery | What tenders POS allows |
| Credit Limit | number (default 1,000,000) | Max outstanding credit |
| Credit Balance | number | Current available credit (system-maintained) |
| Monthly Credit Balance | number, read-only | |
| Credit Days | number (default 1000) | Due days for credit bills |
| Branch | dropdown (GLOBAL) | Home branch; GLOBAL = visible in all branches |
| Status | Active/Inactive | |
| Sales Formula | dropdown | Price-level / formula assigned to customer (special pricing) |
| GST Type | Regular / Composite / Un Register | Customer GST registration – decides tax invoice vs retail invoice |
| I wish to receive SMS from URBANPETS… | Yes/No | SMS consent |

**Tab Contact Details** – Address1, Area, City, State (Indian states list), Country (list), Postal Code, Phone (STD Code + number), Email, Remarks, Status (read-only), GST No, Aadhar No, Pan No, Mobile.

**Tab Others** – Gender (Male/Female), Exempted Reason (Other exemption / SEZ-Exempt / SEZ-LUT / BOND / SEZ-Taxable), Customer Type (RETAIL INVOICE / TAX INVOICE / EXEMPTED / E-COMMERCE).

**Tab Pet Details** (custom for this business) – Pet Type (from Pet Types master), Breed (from Breed Master), Name, Gender (Male/Female), Age, Remarks, Birth Date (date). Reported through *Reports → Masters → Customer Pet Details*.

#### 1.2.3 Area
Delivery/marketing areas. Form: `Name`, `Branch` (GLOBAL or specific). Used in customer address and *Areawise Sales Summary*.

#### 1.2.4 Loyalty Program Info (`angular-modules` – "Loyalty Program Master")
Grid: S.no, Loyalty Name, Start Date, End Date, Based On, Status, Action; search box; `Add Loyalty`.
Form tabs **Loyalty Info** and **Bill Wise Loyalty**:

| Field | Purpose |
|---|---|
| Loyalty Name | program name |
| Start Date / End Date | DD-MM-YYYY validity |
| Status | checkbox active |
| Roundoff Loyalty | round points to integer |
| Min pts req for Redeem | threshold before redemption allowed |
| Amount Per Point | rupee value of 1 point at redemption |
| (Bill Wise tab) | slab rules: bill amount → points earned |

#### 1.2.5 Loyalty Points Update
Manual adjustment: `Customer` (search), `Points` (+ button to add / negative to deduct), `Remarks`, **Update / Clear**.

#### 1.2.6 Pet Masters
* **Pet Types** – Name, Status (Dog, Cat, Bird…).
* **Breed Master** – Breed Name, Pet Type (dropdown), Status.
* **Color Master** – Name, Status.
These feed the Customer → Pet Details tab.

---

### 1.3 Supplier
Grid: Id, Name, Status, Created, Updated. Form tabs **General** and **Contact details**.

| Field | Options | Purpose |
|---|---|---|
| Name | text | |
| Currency | dropdown | Purchase currency |
| Purchase Type | Local / Interstate / Import | Tax treatment (CGST+SGST vs IGST) |
| Purchase Mode | Credit / Cash / Consignment | Default payment terms |
| Credit Limit | number | Max amount you may owe |
| Credit Balance | number | Available credit |
| Credit Days | number | Payment due days |
| Status | Active/Inactive | |
| GST Type | Regular / Composite / Un Register | Supplier registration (affects input credit) |
| Mail Type | None / Inline HTML / CSV / SAP / EDI | Format used when e-mailing POs to this supplier |
| Contact tab | Address, City, Postal Code, State, Country, Phone, Email, Mobile, Aadhar No, Pan No, GST No | |

---

### 1.4 Tax
* **GST Tax** – list only (Description, Status, Created, Updated): GST 0% Tax, GST Exempted, GST 5%, 12%, 18%, 28%, 28%+Cess 12/15/36/60/160%, GST 3% … (31 rows). Maintained by GoFrugal; selected in Item → GST tab.
* **GSTNo Restriction Master** – Grid Id, Gst No, Status. Form: `GST No`, `Status`. Stores GSTINs that are *restricted/blocked* for B2B billing validation.

---

### 1.5 Branch
#### 1.5.1 Branch
Grid: Name, Address Line1, Address Line2, City… Form tabs **General** and **GST**.

| Field | Purpose |
|---|---|
| Branch Name | Store / location name |
| Address Line1/2, City, Postal Code, State, Country | Printed on invoices |
| Contact, Phone, Email, Mobile | |
| Language | ENGLISH – print language |
| Area Code / Circle Code | grouping codes used by "Based On Area/Circle" filters |
| Business Type | COCO / FRANCHISE / BRANCH / DISTRIBUTION CENTER … |
| Webstore | Yes/No – branch participates in online store |
| ERP Code | external ERP location code |
| Country Code | numeric phone country code (1 = default) |
| License ID, CST | statutory ids |
| Website Link, Social Media Link | printed on bill footer / QR |
| Enable Thirdparty Loyalty | Yes/No – external loyalty integration |
| GST tab | GST No, Pan No, GST Type (Regular/Composite/Un Register), GST Filing (Monthly/Quarterly) |

#### 1.5.2 Distribution Centre Mapping (`angular-modules`)
Grid S.No | Branch Name | Action with `+ ADD`, Save, Cancel. Marks which branches act as distribution centres (source for Indent / Transfer Out).

---

### 1.6 Register
A **Register** = a billing counter / till. Grid: Register Name, Branch Name, Product Type Name, Created Date, Status.

| Field | Purpose |
|---|---|
| Register Name | e.g. Counter 1 |
| Location | Branch |
| Status | Active / Inactive / Yet to Active |
| Product Type | TruePOS (web POS) etc. |
| Inv Seq No | starting invoice sequence for this register |
| Device Id | read-only, filled when a device activates |
| Online Sales Allowed | Yes/No |
| Register Prefix | prefix in bill number (e.g. CO-225) |

The current login shows *Counter CO-225*.

---

### 1.7 Promotion → Promotion Management (GWT "Offer Management")
Keyboard screen: **F4 Edit, F6 Save, F7 View, F9 Clear, F10 Close, F11 Map Offer**.

| Field | Purpose |
|---|---|
| Offer Name | |
| Allow Offer Span | offer may span multiple bills |
| Offer Type | Retail Offer / Reclaim Offer |
| Period | checkbox + from/to dates |
| Happy Days | restrict to weekdays |
| Happy Hours [24:00] | hour:minute selectors (00-23 / 00-59) |
| First N Customers | limit to first N bills |
| Customer Type / Customer Category / Customer | targeting filters |
| F11 Map Offer | attach items / categories / buy-X-get-Y rules to the offer |

---

### 1.8 More → Tools

#### Master Configuration
A launcher page (search box "Search master") listing **every** master in the system, including many not on the menu:
Transporter, Branch, Item Category, Tax, Gst taxes, Register, Item Category Values, Tax Area, Item, Customer Category, Customer, Relationship Officer, Area, Brand, Supplier, Tax Commodity, Holiday, Microfinance Branch, Department, Designation, Item Price Change, Loyalty, Tender Type, State name, Conversion, UomProduct, TaxSlab, Customer Credential, Customer Price Rules, UpdateLoyalty, Circle, Denomination, Pet Types, Breed Master, Color Master, Service Master, Claim Master, Center Master, Shipment, Reason, Expiry Rules, Channel, Customer Profile, Customer Source Master, Payment Type, Order Type Master, Item Type Master, Price Level Master, Area/Beat Master, REP Master, Customer Category Values, Gift Master, Supplier Group Master, Doctor, Item Classification, Tender Type Value, GST Master.

#### Tender Type
Payment methods. Grid Id, Name, Type, Status. Form:

| Field | Options | Purpose |
|---|---|---|
| Name | text | e.g. Paytm, HDFC Card |
| Status | Active/Inactive | |
| Type | Card / Coupon / Wallet / Credit / Finance | classification (Cash is built-in) |
| Mode | Manual / … | Manual entry vs device-integrated (EDC) |
| Service applicable | No/Yes | charge a service fee |
| Mandate refno | No/Yes | force cashier to enter reference / approval no |
| Service Charge Perc | number | % surcharge |
| Branch | GLOBAL / branch | availability |

#### Tender Type Values
Sub-values of a tender (e.g. Card → Visa, Master). Form: Name, Status, Tender Name (parent tender), Group Ledger (accounting ledger), Branch.

#### Unicode Master (`angular-modules`)
Grid Branch Code, Branch Name, Unicode, Address, City, State, Country – stores regional-language (Unicode) branch text for bill printing. Update / Cancel.

#### Master Attributes
Grid S.No, Id, Name + Edit – define extra custom attributes on masters.

#### Addon Devices Inactivation
Grid S.No, Model Name, Last Used By, Last Used Time, Status, Action – deactivate registered add-on devices (weighing scale, EDC, mobile app devices).

### 1.9 More → Utility
#### Transporter
Grid Name, Phone, Branch. Form tabs General (Name, Trans Mode Road/Rail/Air/Ship, Address Line 1, Area, State, Phone, Email, Status) and GST (GST No, Pan No). Used in e-Way bill / delivery note.

#### Freight Settings (GWT Mapping screen)
Filters Based on (Branch/State/Circle/Area), Item Status (Active/Inactive/All); grid S.No, Code, Delivery, Amount – freight charge per delivery type. F6 Update, F9 Clear, F10 Close.

---

# Part 3 – Sales Module

All Sales screens except *Sales Order Approval* and *Transfer Out Approval* are the legacy GWT **Outward** screen (`/TruePOS/com.gofrugal.raymedi.webpos.outward.Outward/Outward.html`) rendered in different modes. They share one layout, so the common layout is documented once, then each screen lists only what differs.

### 2.0 Common Outward screen layout

```
┌ Title (Sales Bill / Quotation / …)                     [Branch selector ▾] [?] ┐
│ Customer  [____________]  Delivery Type [Delivered ▾]     │ Bill No     ____   │
│ Address   [____________]  Delivery Time [hh:mm]           │ Bill Date   ____   │
│ Balance   [____________]  Sales Type    [Local ▾]         │ Item Disc Amount   │
│ Invoice Type [Retail ▾]   Payment Type  [None ▾]          │ Disc%              │
│ Coupon Balance                                            │ Disc Amount        │
├───────────────────────────────────────────────────────────│ Round off Amount   │
│ S.No Code Description Exp Dt Qty SellPrice MRP Disc% ...  │ Total GST          │
│ 1                                                          │ Total Extra Cess   │
│                                                            │ GST Calamity Cess  │
├ Remarks [        ]   Message [        ]                    │                    │
│ Total Qty [ ] Total Weight [ ]   "Press ENTER or TAB…"     │ Total : 0.00       │
│ F1 F2 F3 New F4 Edit F5 Recall F6 Save F7 View F8 Print F9 Clear F10 Close F11 │
└──────────────────────────────────────────────────────────────────────────────┘
```

#### Header fields

| Field | Type | Purpose |
|---|---|---|
| Branch selector (top-right, shows "URBANPETS SERVICES PRIVATE LIM…") | search box | Location for which the document is raised. Two locations exist: **URBANPETS SERVICES PRIVATE LIMITED** (HO) and **URBAN PETS / MOTERA** |
| Customer | text + lookup (ENTER/TAB opens customer search) | Customer master lookup; WALK-IN is default for retail |
| Address / Balance | read-only | Pulled from customer master; Balance = outstanding credit |
| Invoice Type | Retail Invoice / Tax Invoice / Exempted | Retail = B2C bill; Tax Invoice = B2B with customer GSTIN; Exempted = SEZ/exempt customer. Auto-set from customer GST Type |
| Coupon Balance | read-only | Loyalty / coupon balance of the customer |
| Delivery Type | Delivered / Home Delivery or Phone / Delivery / Pickup or visit | Fulfilment channel; drives Delivery Reports and Kitchen/Preparation reports |
| Delivery Time | time | Promised delivery time (defaults to now) |
| Ship From (Quotation / SO only) | branch list | Location that will ship the goods |
| Sales Type | Local / Interstate | Local → CGST+SGST, Interstate → IGST |
| Account (SO only) | Advance account / Order Location / Delivery Location / To customer account | Where the advance collected on the order is posted |
| Payment Type | None / Bajaj / Buyback In-House / Pinelabs / Pinelab-HDFC / Pinelab-ICICI / Pinelab-CITI / …Buyback variants / HDFC Finance / CITI Finance / Buyback Cashify / Capital (IDFC) / Bajaj Finance / Home Credit / TVS Finance / Axis Finance / SBI / Axis / IDFC / SCB / Kotak (+Buyback) | EMI-finance or exchange (buy-back) scheme attached to the bill; "None" for normal sale |
| Remarks | textarea | Internal note, printed on bill if configured |
| Message | textarea | Customer-facing message printed on bill |

#### Item grid columns

| Column | Meaning |
|---|---|
| S.No | line number |
| Code | item code / barcode scan (ENTER opens item search) |
| Description | item name |
| Exp Dt | expiry date of the batch being sold (items with Batch/Expiry = Mandatory) |
| Qty | quantity |
| Sell Price | unit price (from item master / price level / customer sales formula) |
| MRP | printed MRP |
| Disc % / Disc Amount | line discount |
| GST% / GST TaxAmt | tax rate and amount |
| Net Amount | line total |

Transfer Out grid instead shows: S.No, Code, Description, Exp Dt, Qty, Sell Price, MRP, Amount.

#### Totals panel (right)
Bill No, Bill Date, Item Disc Amount (sum of line discounts), Disc% / Disc Amount (bill-level discount), Round off Amount, Advance (SO/DN), Total GST, Total Extra Cess, GST Calamity Cess, **Total**.

#### Function keys

| Key | Action |
|---|---|
| F1 Pet Details (Sales Bill only) | Capture / view the customer's pet (type, breed, name…) on the bill |
| F2 Hold (Sales Bill only) | Park the current bill; recalled with F5 |
| F3 New | Start a new document |
| F4 Edit | Open an existing document by number for editing |
| F5 Recall (Sales Bill only) | Recall a held bill |
| F6 Save | Save. On Sales Bill this opens the **tender window** (Cash / Card / Wallet / Credit / Coupon / Advance / Due bill – as configured in Tender Type master) |
| F7 View | Search / list documents |
| F8 Print | Reprint |
| F9 Clear | Clear screen without saving |
| F10 Close | Close screen (a "Leave site / unsaved" confirm appears if data was typed) |
| F11 Buy XCare (Sales Bill) | Sell extended-warranty / care plan add-on |
| F11 New Customer, F12 Select Cust (Delivery Note Return) | quick customer create / pick |

---

### 2.1 Sales Quotation
Title *Quotation*. Fields: Customer, Address, Invoice Type, Delivery Type, **Ship From**, Sales Type, Payment Type. Totals: Quote Date, Quote No, Item Disc Amount, Disc%, Disc Amount, Round off, Total GST, Extra Cess, Calamity Cess. No Exp Dt column (no stock is reserved). Keys: F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: price offer to a customer; can be converted to a Sales Order.

### 2.2 Sales Order
Title *Sales Order*. Adds **Account** (Advance account / Order Location / Delivery Location / To customer account) and **Advance** in totals; header has SO No, SO Date, Quote No (link back to quotation). Use: confirmed customer order, advance collection, stock reservation (see report *Sales Order Stock Status*).

### 2.3 Sales Order Approval (`angular-modules`)
Grid: S.No, Sales Order No, Customer, Amount, Sales Man, Status, Acknowledged By, Acknowledged Date; branch filter; paging. Approver acknowledges pending sales orders before they can be delivered/billed (used when Business Configuration enables SO approval).

### 2.4 Delivery Note
Title *Delivery Note*. Header adds **SDN No / SDN Date**; totals include **Advance Amount**. Grid has Exp Dt. Stock is issued on delivery note; the bill is raised later (Delivery Bill reports). Use: home-delivery / dispatch before invoicing.

### 2.5 Sales Bill (POS invoice)
Title *Sales Bill*. Full header (Customer, Address, Balance, Invoice Type, Coupon Balance, Delivery Type, Delivery Time, Sales Type, Payment Type). Totals: Bill No, Bill Date, Item Disc Amount, Disc%, Disc Amount, Round off Amount, Total GST, Total Extra Cess, GST Calamity Cess, Total. Extra keys **F1 Pet Details, F2 Hold, F5 Recall, F11 Buy XCare**.
Bill number prefix comes from the Register (counter CO-225). Saving (F6) opens tender entry and prints.

### 2.6 Sales Return
Title *Sales Return*. Header: Customer, Address, Coupon Balance, **Bill No** (original bill to return against), **Return Mode** (RRN / Credit Note / Cash / Wallet / Card), Sales Type (Local / Interstate). Totals: Return No, Return Date, Item Disc Amount, Disc%, Disc Amount, Round off, Total GST, Extra Cess, Calamity Cess.
Return Mode meaning: **RRN** = Return Receipt Note (value kept as credit to adjust in next bill), **Credit Note**, **Cash** refund, **Wallet** refund to customer wallet, **Card** refund.

### 2.7 Delivery Note Return
Title *Delivery Note Return*. Header: Customer, Address, Coupon Balance, **DN No** (delivery note being returned), Delivery Type (read-only), Delivery Time, Sales Type, Payment Type. Totals: DNR No, DNR Date … Extra keys F11 New Customer, F12 Select Cust. Use: goods delivered on a delivery note but returned before billing.

### 2.8 More → Transfer Out (Stock Transfer Out)
Title *Stock Transfer Out*. Header: **To Branch Name** (ENTER/TAB to select branch), Address. Totals: TO NO, TO Date, Total GST, Total Extra Cess, Total. Grid: S.No, Code, Description, Exp Dt, Qty, Sell Price, MRP, Amount. Keys F3 New, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: send stock from one branch (e.g. HO) to another (MOTERA). The receiving branch books it with *Purchase → Transfer In*.

### 2.9 More → Transfer Out Approval and Auto TI
Currently shows: *"Please Select Approval and Auto Transfer In from Configuration – Enable Transfer Out Approval in Menu Tools → Business Configuration."* i.e. the feature is switched off. When enabled, transfer-outs wait for approval here and a Transfer In is auto-created at the destination branch.

---

### 2.10 Typical sales flows

1. **Counter sale**: Sales Bill → scan items → F6 Save → tender → print.
2. **Order then deliver**: Sales Quotation → Sales Order (advance) → (Sales Order Approval) → Delivery Note → Sales Bill.
3. **Return**: Sales Return against Bill No, choose Return Mode.
4. **Inter-branch**: Transfer Out (HO) → Transfer In (branch) – see Purchase module.

---

# Part 4 – Purchase Module

Purchase Order, Receipt Note, Purchase Invoice and Transfer In are the legacy GWT **Inward** screen (`/TruePOS/com.gofrugal.raymedi.webpos.inward.Inward/Inward.html`). Purchase Returns uses the Outward screen. PO Cancel, Indent, Auto Indent, Transfer In Touch and Indent CutOff are Angular screens.

### 3.0 Common Inward screen layout

| Area | Fields |
|---|---|
| Branch selector (top right) | Location receiving the goods |
| Supplier | text + lookup (ENTER/TAB opens supplier search). Address and **Balance** (amount owed) fill automatically |
| Purchase Type | Local / Interstate – CGST+SGST vs IGST on purchase |
| C-Form | Against C-Form / No Forms – legacy CST form declaration (kept for compatibility) |
| Remarks / Message | internal note / printed note |
| Totals panel | document numbers and dates, Item Disc Amount, Disc%, Disc Amount, **Freight**, Round off Amount, **Scheme ItemDiscAmt**, **OtherDiscAmt**, Total GST, Total Extra Cess, (TCS Amt on invoice), Total |
| Function keys | F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close |

#### Item grid columns (Inward)

| Column | Meaning |
|---|---|
| Code / Description | item (ENTER/TAB opens item search; new items can be created inline) |
| Exp Dt | expiry date of received batch (mandatory when item has Batch/Expiry = Mandatory) |
| Qty | ordered / received quantity |
| Free | free (scheme) quantity – increases stock, zero cost |
| Cost Price | supplier price per unit |
| Sell Price | selling price to set on the item (updates item master on save) |
| MRP | MRP to set |
| Disc % / Disc Amount | supplier line discount |
| GST% / GST TaxAmt | input tax |
| Margin% | (Sell − Cost) ÷ Sell |
| Profit% | (Sell − Cost) ÷ Cost |
| Net Amount | line value |

---

### 3.1 Purchase Order
Header: Supplier, Address, Balance, Purchase Type, C-Form. Totals: **PO No, PO Date**, Item Disc Amount, Disc%, Disc Amount, Freight, Round off, Scheme ItemDiscAmt, OtherDiscAmt, Total GST, Total Extra Cess. Grid: S.No, Code, Description, Qty, Free, Cost Price, Sell Price, MRP, Disc %, Disc Amount, GST%, GST TaxAmt, Margin%, Profit%, Net Amount (no Exp Dt – nothing received yet).
Use: order to supplier. Can be mailed (Supplier → Mail Type). Pending POs feed *Purchase Order Summary / Purchase Transit* reports and *PO Replenishment*.

### 3.2 Receipt Note (GRN without invoice)
Header adds **PO No / PO Date** (pull lines from a PO) and **RN No / RN Date**, plus **Inv No, Inv Date, Inv Amount** if the supplier invoice is already known. Grid includes Exp Dt. Stock is received into inventory; the financial invoice is booked later in Purchase Invoice. Use when goods arrive before the bill or need checking first.

### 3.3 Purchase Invoice
Header: Supplier, Address, Balance, PO No/Date, Purchase Type, C-Form. Totals: **GRN No / GRN Date**, **Inv No / Inv Date / Inv Amount** (supplier invoice – Inv Amount is used to cross-check the computed total), Item Disc Amount, Disc%, Disc Amount, Freight, Round off, Scheme ItemDiscAmt, OtherDiscAmt, Total GST, Total Extra Cess, **TCS Amt**, Total. Grid same as Receipt Note.
Use: the main stock-in + supplier-liability document. Updates item Cost Price, Landing Cost, Sell Price/MRP, batch expiry, and supplier ledger.

### 3.4 Purchase Returns (Outward screen, title *Purchase Return*)
Header: Supplier, Address, **Return Mode** (Debit Note / Cash), **Reference No, Ref.Date** (supplier invoice being returned against), **Return Reason** (dropdown from Reason master – currently empty). Totals: PRN No, PRN Date, Item Disc Amount, Disc%, Disc Amount, Freight, Round off, Scheme ItemDiscAmt, OtherDiscAmt, Total GST, Total Extra Cess. Grid: S.No, Code, Description, Exp Dt, Qty, Free, Cost Price, Sell Price, MRP, Disc %, Disc Amount, GST%, GST TaxAmt, Net Amount.
Use: send damaged / expired / excess goods back to supplier; reduces stock and supplier balance.

### 3.5 PO Cancel (`angular-modules`)
Filter **Location**. Grid: S.No, Po No, Prefix, Amount (₹), Supplier, Status, Date. Select a pending PO and cancel it (fully or remaining qty). Feeds report *Purchase Order Cancel*.

### 3.6 Transfer In (Stock Transfer In – Inward screen)
Header: **From Branch** (ENTER/TAB to select), Address; totals **TO No, TO Date** (the originating Transfer Out), **TI No**, Total GST, Total Extra Cess, Total. Grid: S.No, Code, Description, Qty, Sell Price, MRP, **Received** (qty actually received – shortages create *Stock Transfer Discrepancy*), Amount. Keys F6 Save, F7 View, F8 Print, F9 Clear, F10 Close (no F3 – lines always come from a TO).

### 3.7 Indent (`angular-modules`, title *Indents*)
Filter Location. Grid: S.No, Indent No, From Branch, To Branch, Created At, Status. **Add Indent** opens *Indent Entry*:

| Field | Purpose |
|---|---|
| To Location | branch / distribution centre being asked to supply |
| Item search → Add | adds line |
| Grid: S.No, Item Code, Item Name, Indent Quantity (editable), Stock (current stock at requesting branch, read-only), MBQ (min stock level, read-only), Action | |
| Save Indent / Cancel | |

Use: a branch requests stock from HO/DC. HO fulfils via Transfer Out.

### 3.8 More → Auto Indent (title *Indent Request*)
Filters: Branch (All Locations), **Advance Filter** (Brand, Item Type, Category, List Value; Apply / Reset / Cancel), **Search Indent**. Generates indent lines automatically for items below MBQ (Min/Max set in Item Property Setting). Related report: *Indent Based On Replenishment*.

### 3.9 More → Indent Cancellation (GWT Master screen)
Fields: Indent No, Indent Date [DD-MM-YYYY], Branch Name; grid S.No, Item Code, Description, Qty. Keys F6 Cancel, F9 Clear, F10 Close.

### 3.10 More → Transfer In Touch (`angular-modules`, touch-friendly Transfer In)
Filters: Branch (default current), Status (Pending). **Load** lists pending transfer-outs addressed to this branch: S.No, To No, SAP Invoice No, Source Location, To Date, Amount, Action. Example pending row: TO 3181 from URBAN PETS / MOTERA dated 04-09-2026, amount 2499. Action opens a receive screen to confirm quantities with a tablet.

### 3.11 More → Indent CutOff Time Configuration
Grid per location: S.No, Location Code (225 = HO, 32772 = MOTERA), Location Name, **Cut Off Time** (HH / MM 00-15-30-45 / AM-PM). Indents raised after the cut-off are treated as next-day. Save / Cancel.

---

### 3.12 Typical purchase flows

1. **Direct purchase**: Purchase Invoice (F3 New → supplier → items → F6 Save).
2. **PO based**: Purchase Order → Receipt Note (optional) → Purchase Invoice (pull PO/GRN) → PO Cancel for any unfulfilled remainder.
3. **Return**: Purchase Returns with Reference No of the invoice.
4. **Branch replenishment**: Branch raises Indent (or Auto Indent) → HO Transfer Out → branch Transfer In / Transfer In Touch.

---

# Part 5 – Inventory Module

Inventory screens never involve a customer or supplier; they adjust stock quantity, price or packaging inside a location. Every screen starts with a **Location** selector (URBANPETS SERVICES PRIVATE LIMITED = HO, URBAN PETS / MOTERA = branch).

### 4.1 Opening Stock Entry (GWT Inward screen)
Grid: S.No, Code, Description, Exp Dt, Qty, Cost Price, Sell Price, MRP, Disc %, Disc Amount, GST%, GST TaxAmt, **Supplier**, Scheme Disc%, Scheme Amt, Scheme Others, Net Amount. Remarks / Message. Keys **F5 New**, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: load initial stock when going live (or a new branch) with cost, price and batch expiry per item without a supplier invoice. Report: *Opening Stock Detail*.

### 4.2 Damage Stock Entry (`angular-modules`)
List: Location filter, **New Damage Stock** button, grid S.No, Damage No, Date, Total Qty, Total Cost [₹], **Wastage Type** (Wastage / Damage / Theft), View. 39 entries exist (e.g. No 52 on 31-01-2026, 152 qty, ₹42,010 Wastage).

**New Damage Stock form**

| Field | Purpose |
|---|---|
| Location | branch losing the stock |
| Date | read-only, today |
| Wastage Type | Wastage / Damage / Theft – classification for the *Wastage/Damage Stock* reports |
| Item search → Add | grid S.No, Item Code, Item Name, Exp Dt, Qty, Cost Price, Selling Price, MRP, GST %, GST taxAmt, Net Amt, Action |
| Message / Remarks | notes |
| Save / Cancel / Clear | |

Stock is written off at cost.

### 4.3 Stock Update Entry (GWT Inventory screen, title *Stock Update*)
Grid: S.No, Code, Description, Exp Dt, Qty (physical count), **Current Stock** (system qty), Sell Price, MRP. Keys F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: physical stock-take; entering counted Qty creates a +/- adjustment. If approval is enabled the entry waits in *Stock Update Approval*.

### 4.4 Stock Update Approval (`angular-modules`)
Select Location → grid S.no, Code, Item Name, Exp Dt, **Physical Qty**, **Current stock**, Sell Price, MRP, **Update Qty** (difference), Status, **Approve** (checkbox), Remarks. Search Item/Code. Save / Cancel. Manager approves or rejects each stock-take line before stock changes.

### 4.5 Barcode Printing (`angular-modules`)
Branch filter, **Load Transaction** (pull items from a purchase invoice / GRN to print labels for the received qty), or search item and **Add**. Grid: S.no, Item Code, Item Name, Exp Date, **Print Qty**, Cost Price, Sell Price, MRP, Action. **Print** / Cancel. Label layout comes from *Tools → Barcode Config*.

### 4.6 Price Fixing

#### 4.6.1 Price Fixing (markup/markdown) – "Mark Up/Down"
List: Type filter (All), grid S.No, Date, Mark Up/Down Id, Type, Status. **Add Mark Up/Down** form:

| Field | Options | Purpose |
|---|---|---|
| MarkUp/Down Type | Item | apply per item (category filters available) |
| RoundOff Type / RoundOff Value | number | rounding of resulting price |
| Applicable For | Other than Purchase return | scope |
| Category Filters | button | restrict by category |
| Grid → Mark Up/Down | MarkDown(cost) / MarkDown(selling and cost) / MarkDown(selling) / MarkUp(selling and MRP) / MarkUp(selling) | which prices move and direction |
| Selling Based On | MRP / Purchase Price / Landing Cost / Landing cost (Exc. Discount or Free) | base for computing selling price |
| Amount/Percentage + Selling Value | Amount / Percentage | markup value for selling price |
| MRP/Cost Based On + Amount/Percentage + Cost Value | same bases | value for MRP/cost |
| Add / Save / Cancel | | |

Use: bulk re-pricing, e.g. "Selling = Landing Cost + 25 %".

#### 4.6.2 Price Level (Price Level Master)
Grid Name, Type, By, On. **Create Price Level Master**: Price Level Name; Type (MarkUp / MarkDown / Price); Based On (Cost / Landing Cost / Selling); By (Percentage / Amount). A price level is a named pricing rule (e.g. "Wholesale = Cost + 10 %") that can be assigned to customers (Customer → Sales Formula) or branches.

#### 4.6.3 Price Level Vs Items (GWT *pricelevelmapping*)
Header: Pricelevel Name (lookup), Type, Based on, By (read from the master). Grid: S.No, Item Code, Description, **Round Off** (None / Lower / Near / Upper), **Round To** (0 / 0.5 / 1), **Mark Value** (item-specific % or amount overriding the level default). F6 Save, F9 Clear, F10 Close.

### 4.7 Change Selling (`angular-modules`)
Option / **Category Filter**; search item → Add. Grid: S.no, Item Code, Item Name, Exp Date, **New Sell Price**, **New MRP**, Cost Price, Sell Price, MRP, Action. Save / Cancel. Quick manual price change per item/batch; history visible in *Price Drop* and *Price Level Advance* reports.

### 4.8 More → Repack (`angular-modules`)
List: Location, **Add Repack**, grid S.No, Repack No, Bulk Item Name, Date, Packed [in %], Qty Taken [in KG].

**Repack Entry form** – Bulk Item Selection: Bulk Item, Current Stock [KG], Conversion, **Qty Taken**, Packed, Avail for Pack, **Conversion Loss [Kg]**, Batch No, Mfg Dt, Expiry Dt, Cost Price, Selling. Then packed items grid: S.No, Item Code, Item Name, **Repack Quantity**, Sell Price, MRP, **Conversion in (g)**, Expiry Date, Action. Add / Save / Cancel / Clear.
Use: break a bulk bag (e.g. 20 kg dog food) into loose 1 kg packs; bulk stock decreases, pack items increase, loss recorded.

### 4.9 More → Change Serial No (`angular-modules`)
Item Name, Part No (read-only); grid S.No, MFR Serial No → New MFR Serial No, Serial No 2 → New Serial No 2, Serial No 3 → New Serial No 3, Action. Add / Save / Cancel. For Serialized product type only.

### 4.10 More → Price Drop (`angular-modules`)
Header: Tran Type (Purchase), Location, Mode (Auto), Operation (Decrease), **Invoice No**. Grid: Item, Quantity, Rate, Amount, **Assessable Value (Amount − ?)**, Action; Add, Save, Reset.
Use: record a post-purchase price reduction (supplier price-drop credit) against an invoice so the assessable/cost value is corrected.

### 4.11 More → Kit Preparation (`/TruePOS/kitPreparation/`)
Location (dropdown); kit item row: Item Name (search), Expiry Date, Quantity, Selling Price, Cost Price, MRP, Action → **Add**; **Create Kit** / Reset. Consumes component stock per *Kit Mapping* and creates kit stock.

### 4.12 More → Kit Unpack (title *Kit Item – Unpack*)
Location, search item; grid Item Code, Item Name, Location, **Prepared, Sold, Available**, Sale Price, Action (unpack). Reverses a kit back into components.

### 4.13 More → Shelf Talker (`angular-modules`)
Location, **Add Shelf Talker**; grid S.No, Item Code, Item Name, **Price Format**, **Calculated Value**, **Print Profile**, Print Qty. Prints shelf-edge price labels (per-kg price etc.).

---

### 4.14 Where inventory data shows up
* Stock ledger reports (Itemwise Stock Statement, Transactionwise Stock Register, Closing Stock).
* Wastage/Damage Stock and Stock Update Detail reports (Stock Analysis group).
* Repack Summary / Detail, Kit Preparation / Unpack reports (Stock Replenishment group).

---

# Part 6 – Reports Module

All reports open in the **SmartReport viewer** (`/smartreport/index.html`). Every report has a numeric id shown in the title bar, e.g. *110158 : Daily Sales Summary (Store Wise)*. The complete menu of ~190 reports is listed in 00-menu-tree.md; this file explains how the viewer works and the columns of the most-used reports.

### 5.0 Report viewer – how it works

#### Filter panel (left slide-out, funnel icon with a badge = number of applied filters)
| Group | Contents |
|---|---|
| **Applied Filter List** | shows current filters (e.g. Date Range : Today, Location : All Location) with ⊗ to remove each |
| **Date** (mandatory for transaction reports) | *Date Range* picker: Start Date / End Date calendars + presets **Today, Yesterday, This month, Last Month, This year, Last year, Financial year, Custom Days, Custom Months** → Apply / Cancel |
| **Standard** (mandatory) | report-specific pick lists; for almost all reports **Location** (multi-select, default *All Location*); others add Supplier, Customer, Category, Item, Till/Counter, User etc. |
| **Advanced** | build your own conditions: *Select column* → *Select condition* (=, >, contains…) → *Enter Value* → **Add** |
| Save this filters | remember the filter set for this report |
| **Apply** | run the report |

The panel opens automatically the first time a report is opened. Reports without a date (masters, current stock) just need Apply.

#### Left toolbar icons
Filters · Settings · Related Reports · Favourite Reports · Export Reports (Excel/CSV/PDF) · Send Mail · Scheduler · Pivot · Print Preview · What's New · About Report.
Top-right of the grid: **Config Settings** (column chooser / Create-update Formula column), **Refresh**, **Favourite** (star), **Dynamic Chart** (bar/pie/line – charts can be pinned to the Dashboard, which is why the home page says "Start building your own dashboard").

#### Result grid
Sortable columns with ▾ menus (sort, hide, group), **NetTotal** footer row, pager (Go to page, rows per page 50/100/150/200/250), "Query Execution: N ms", "Total: N Record(s)". Values in ₹ with Indian grouping (1,55,674.00).

---

### 5.1 Masters reports
Data dumps of master tables – no date filter.

| Report | Key columns (what to use it for) |
|---|---|
| **Item Master** (110110) | S.No, BRANDS, DEPARTMENT, CATEGORY, Item, Item name, Unicode Item Name, Brand name, Item alias, Rack, Product type, Allow Negative Stock, Weighable, Discount, Status, Focus Product, Non MRP, Packing Charge, Selling Price Policy, Item Short Name, Landing cost, MRP, Pur_net, Packed price, Selling, ISBN, Yop, Author, Total pages, Expiry Date Format, Show online store, BEM %, GST Rate, HSN Code, Purchase/Sales Abatement, Purchase GST On, Sales GST On, Is Exempted, Tax Type, Image Upload, Item Open Type, Weight, Supplier, Store Pickup, Inclusive of tax, Batch/Expiry, Mfg Date, Digits after decimal, MFR Format, Item Preparation Status, Repack Conversion (g), Description, Base UOM (+Volume), Sub UOM (+Volume), Tax %, Addl. Tax %, Sell_Through, DMS, Apple_Care, Service Applicable, created_date, Material Type/Group, Shelf No, Flat Offer, Item type, Minimum Selling, Online Special Price, Shelf Life, Minimum Shelf Life, Unique Barcode Id, Sell By. → full item audit / export for price checks |
| **Customer Master** (110126) | Title, Code, Customer Name, Address, Place, Postal code, State, Phone, Mobile, Email, GST No, GST Type, Sales type, Aadhar, PAN, Credit Days, Credit Limit, Loyalty Allowed, Loyalty Point, Invoice Type, Gender, Loyalty Amount, Category, Business Type, Is Exempted, Exempted Reason, Customer Status, Mail Type, Family Customer Code, Birthday, Anniversary, Coupon Limit, Created Date, Customer credit balance, Delivery Beat, Delivery boy, Discount %, Rep Code, Branch… → CRM export, loyalty balances |
| **Customer Pet Details** | S.No, Code, Customer Name, Breed, Type, Pet Name, Gender, Age, Birthday, Color, Height, Weight, Length, Remarks → pet-birthday campaigns |
| Customer Loyalty Details, Customer Parent List, Supplier Master, Brand Master, Tax Master, Area, Branch Master, Supplier Vs Items, Price List, Employee Master, RO Master, UOM Vs Item Mapping, Kit Mapping | same idea for the other masters |

### 5.2 Sales reports
| Report | Columns / purpose |
|---|---|
| **Daily Sales Summary [Store Wise]** (110158) | Store, Date, Bill amount, Tax, Disc. Amount, Scheme amount, **Cash, Card, Cheque, Coupon, Wallet Amt, Credit, Due, Compliment, Approval, Advance adjusted**, Rounded off, **Profit**, Item discount, Bill discount, Freight, **Total bills**, GST/SGST/CGST/IGST TaxAmt, GST Cess, Redeemed Point → day-end cash-up per store |
| Daily Sales Summary [Till Wise] | same split per till/counter (CO-225 …) |
| **Monthly Sales Summary [Storewise]** (110161) | Store, Month, Bill amount, Tax, Discount, Scheme, tender split, Profit, GST split → month trend |
| Daily Sales [Bill No Wise] / with Timefilter | one row per bill |
| **Billwise Itemwise Sales Detail** (110116) | Bill date, Till, Customer Name, Mobile, Bill No, Item name, Qty, Brand, Bill amount, Message, Branch Area/Circle, GRN No, Delivery type, Item code, MRP, State, Sales executive → line-level sales audit |
| **GST Sales Summary** (116504) | Bill No, Till seq, Bill date, Item name, HSN Code, Free qty, Sales Qty, Customer, GST No, State, **Taxable amount, SGST/CGST/IGST Perc & Amt, Cess**, Total amount, Branch, Exempted Reason → GSTR-1 working |
| GST Sales Taxwise | tax-rate-wise totals |
| **Tender Type Detail** (116580) | bill-wise tender breakdown (card/wallet/UPI reference) → reconcile with bank/EDC |
| **Sales Item Margin** (116214) | Store, Customer, Item, Bill date, Bill seq, Till, Qty, Selling rate, Item amount, Purchase rate, **Gross margin** |
| **Customerwise Itemwise Sales** (110166) | BRANDS/DEPARTMENT/CATEGORY, Customer, Item, PCS, Bill amount, Net amount, Net Qty, Return amount/qty, address, Customer type, Free qty, Branch, Membership, Ro Name, HSN |
| Itemwise Customerwise Sales, Categorywise Sales (+Detail), Customerwise Itemwise Points, Counterwise Sales Report, Sales Register Summary, Offer Claim Report, Serial Number Wise Price Details, Offline Sales Bill Details, Canceled Receipts | variants of the above |
| **Orders & Quotation**: Quotation Summary/Details, Sales Order Summary/Detail, Sales Order Stock Status | pipeline of open quotes/orders and whether stock exists |
| **Delivery Reports**: Sales DeliveryNote Summary/Detail, Delivery Bill Summary/Detail, Kitchen Preparation Report (+Timewise) | home-delivery tracking |
| **Returned Transactions**: **Sales Return Summary** (110251: Branch, Return date, SR Prefix, Return No, Customer, Area, Counter, Log ID, Payment type, Return amount, Discount, Remarks, Profit, GST split, Status, Cancelled date), Sales Return Advice Detail, Buy Back Details, Sale Return Customerwise/Itemwise/Datewise/Monthwise | |
| Cancelled Transactions: Bill Cancel Report | who cancelled which bill |
| **Sales Analysis**: Margin Summary, Session Report, Supplier Sales Report, Supplierwise Sales Details, Date Timewise Sales, Consumption Sales Summary/Detail, Areawise Sales Summary, Monthly Sales Detail, Non Purchase Customer List, Counterwise Datewise Sales Summary, Itemwise Monthly Sales Qty/Amt Details | management analysis |
| Hold Transactions: Hold Bill Details, ItemWise Discount Approval Details | parked bills and discount overrides |

### 5.3 Purchase reports
| Report | Columns / purpose |
|---|---|
| **Purchase Detail** (110120) | BRANDS/DEPARTMENT/CATEGORY, GRN No/date, Supplier Bill No, Inv date, Supplier Name, Item code/alias/HSN/name, Received Qty, Purchase rate, Selling, MRP, Expiry date, GST Perc/Amt, Tax Goods Value, Discount %, Disc. Amount, Cash disc., Rebate, Purchase amount, Net margin %, CGST/SGST/IGST, Cess, Conversion unit, Status, Net cost, Profit %, Margin % → line-level purchase audit and margin check |
| **GST Purchase Summary** (116505) | Inv No/date, Purchase date, HSN, Item, Qty, Supplier, GST No, State, Taxable amount, tax split, Freight, Supplier branch GST → GSTR-2/ITC |
| **Purchase Order Summary** (110117) | Sequence No, PO date, Expiry date, Currency, PO amount, Credit days, Delivery period, Branch, Supplier, Discount, Status, Freight, tax split, Order reference, Expiry Status, Approved Date, Supplier Bill No/Date → open PO tracking |
| Purchase Order Details, Purchase Transit, ReceiptNote Nowise Summary/Detail, ReceiptNote Itemwise, Purchase Summary, GIN Summary/Detail, Purchase Detail Serial, Purchase Order vs Invoice Report | |
| Pending/Cancelled: Purchase Order Cancel | |
| Returned: Purchase Return Supplierwise Detail, Purchase Return Summary | |
| Purchase Analysis: Supplierwise Purchase Summary/Details, PO/Purchase Discrepancy, Datewise Itemwise Consolidated Purchase Report, Purchase Register Summary | |

### 5.4 Inventory reports
| Report | Columns / purpose |
|---|---|
| **Current Stock Branchwise** (110113) | BRANDS/DEPARTMENT/CATEGORY, Item code/alias, Store, Item name, **Batch no, Stock**, Cost, Selling, MRP, Landing, Net cost, **Value on landing / netcost / selling**, GST Perc, **Expiry date**, Supplier, Status, HSN, Received Qty, Asset/Consumable/Damage/Demo/Rental/Replacement stock, Reserve → live stock valuation per batch |
| **Closing Stock** (110204) | Store, Item, Cat1/2/3, Status, Net cost, **Closing stock, Closing stock amount**, Batch, Brand, Expiry date, HSN, MRP → stock as on a date |
| Itemwise Stock Statement (110146) | opening + in + out + closing per item for the period |
| Itemwise Stock And Sales Detail (+Transit), Transactionwise Stock Register, Categorywise Datewise stock, Branchwise / Categorywise Stock Age Analysis | |
| Price Drop, Price Level Advance | price change history |
| Stock Movement: Transfer Out Approval Detail, Stock TransferOut Summary/Detail, Itemwise Storewise TransferOut Detail, Stock TransferIn Summary/Detail, Itemwise Storewise Transfer In Detail, Stock In Transit, Stock Transfer Discrepancy, TO Vs TIN, Stock Conversion Report | inter-branch movement and shortages |
| Stock Analysis: Stock Update Detail, **Wastage/Damage Stock** (110283: Branch, Entry date, Wastage Qty, Current stock while wastage, Total cost) (+Detail), Categorywise Storewise Current Stock Summary/Detail, Fast/Slow Moving items, Item Age Analysis, Item Expiry Update Details, Items Details In Cart, Stock Reserve Status | |
| Stock Replenishment: MBQ Detail, Kit Preparation, Kit Unpack, Itemwise Stock Transfer Advice, Indent Based On Replenishment, Indent Summary, PO Replenishment, Picklist Detail, Picking List Discrepancy, Opening Stock Detail, Repack Summary/Detail, Eancode Detail, **Re-order Report** (116040), Issue Date Expiry Details | what to buy / transfer next |

### 5.5 Audit reports
Foot Fall Details, Reprint Count Details, **User Login Summary** (Login ID, Total No of logins, Days/Hours/Minutes/Seconds), Audit Viewer Report, Audit Detail Report, GST Tax Change Audit, Service User Consent Summary, Email Audit Viewer, Audit Report / Detail (Cart entry Clear).

### 5.6 My Reports
User-saved/custom reports: Counterwise Sales Summary, Sales MIS report, Off Take – Dealer Tracker Report.

### 5.7 Production
Production Plan, BOM Report, Production Costing Summary/Detail, Sales/Production Variance (for kit/assembly manufacturing – unused here).

### 5.8 More
Serialized Reports (Serial Number History, Serial No Wise Stock Detail), Offline Exported Reports, **Dashboard** (home-page tiles: Sales, Sales Return, Purchase, Purchase Return month-vs-month; Tender Wise Sales pie; Sales vs Margin line), Monthly Transaction Summary.

---

# Part 7 – Tools Module

### 6.1 Configuration

#### 6.1.1 Security Configurations → Role Master (`angular-modules`)
Grid: S.No, Role Name, Description, Owner Name, Owner's Role; Edit. **Create Role** form: *Role Details* (Role Name*, Description) and *Security Configuration* – a Menu tree (search box) with per-menu **Operations** checkboxes (view / add / edit / delete / print) that decide what each role can open. Save / Cancel.

#### 6.1.2 Security Configurations → Employee Master
Grid: S.No, Emp Name, Emp Code, User Name, Role, Approval Manager, Status, System Access; Edit; ~2 pages of users. **Create Employee** sections:

| Section | Fields | Purpose |
|---|---|---|
| Employee Details | Full Name*, Job Title, Email*, Mobile*, Default Branch*, Approval Manager*, Reporting Manager*, Employee Reference ID, Registration Number, **Allowed Item Discount %**, **Sales Commission %** | Discount cap enforced at POS; commission used by *Enable Sales Man Commission* |
| Login Details | Username (min 3 char)*, Role*, Password*, Confirm Password* | POS login; role = Role Master |
| General Information | Address Line 1/2, Landmark, City, State, Postal Code | |
| Contact Information | Office Phone, Home Phone, Date of Joining, Short name | Short name printed on bill as salesman |

#### 6.1.3 Userwise Configuration
Grid: S.No, Configuration Code, Configuration Name, No.Of.Users, View (100 rows, 10 pages). Each row is a Business-Configuration switch that can be overridden **per user** (e.g. 100001 Show Ship To Branch Name of Customer, 100014 Tax, 100015 Discount, 100037 Show tender details in Sales Bill Edit Mode, 100038 Enable Sales Man Commission, 100040 Show stock of other locations, 100046 Show Landing and NetCost in LOV, 100048 Auto load coupon amount based on balance, 100050 Dont allow to edit Qty load from weight, 100052 Show Claim id …). *View* lists which users have the override.

#### 6.1.4 Business Configuration (`angular-modules`) – the master switchboard
Tabs **Configurations** / **Report** (change history). Left sections; search box "Search configurations across all section". Toggle = on/off, dropdowns and numeric ranges. Current values captured on 2026-09-05 (ON = enabled):

**Outward (sales screens)**
| Setting | Value |
|---|---|
| Show Ship To Branch Name of Customer | off |
| Show Delivery Type | ON |
| Show Delivery Date | Not Required |
| Show Delivery Time | From Time |
| Show Salesman | off |
| Default Billing Qty (Delivery Note & Sales Bill) | 0 (range 0-9999) |
| Make Customer Name as mandatory in Sales | ON |
| Show Zero Stock Items also in LOV | ON |
| Show Sales Type | ON |
| Show Receipt Terms / Show PO no and date in sale bill | off |
| Tax | Not Allow to Edit |
| Discount | Allow to Edit |
| Hide Outstanding in Sales Order / Item wise Delivery Status / Item wise Order No | off |
| Allow Branch wise executive only | ON |
| Show Delivery Category / Stock Status in SO / Stock Quality in TO | off |
| Monthly cycle date for Credit Sales | 0 |
| Enable Session Management | off (hence Session Management screen disabled) |
| Enable Promotion | off |
| Show tender details in Sales Bill Edit Mode | ON |
| Enable Sales Man Commission / card swipe in customer selection / Show stock of other locations | off |
| Same Bill Sequence for Invoice | off |
| Validate selling rate should not be less than cost price | No |
| Load Multiple Sales Orders / Show Landing and NetCost in LOV | off |
| Monthly cycle date for Coupon Sales | 0 |
| Auto load coupon amount / Dont allow to edit Qty load from weight / Show Claim id / Enable expiry offer | off |
| Restrict multiple offers on single item | None |
| Show shipping information | ON |
| Dont print TO / Show Customer Profile / Show SO Type | off |
| Enable E-Invoice | Auto |
| Show Zero stock in Sales Order/Quotation | ON |
| Show SAP InvNo in Sales Return / Validate rate against MSP / Sales Return approval required | off |
| Allow to edit SalesMan/Delivery date when convert SO to Sales | ON |
| Save advance order bill type as AD / Show customer First & Last name / Search by Mobile in Outward | off |
| Enable Settlement / Dont allow billing after close settlement | off |
| Daywise cash tender limit for each customer | 0 |
| Load GRN as Sales Bill / Show Customer Payment Type in GoBill / Load available stock as SO qty / Show SO No in Sales/DN / Write off SO after convert | off |
| Allow expiry batches in TO/TI / Apply all offers on Esc / Show SO Preview | off |
| Sales Order advance goes to | To customer account |
| Credit days validation based on invoice outstanding / Enable Session Denomination | off |

**Inward (purchase screens)**
| Setting | Value |
|---|---|
| Show Supplier Branch | off |
| Confirm when difference between Inv Amount and Total exceeds | 1 |
| Enable Margin Validation | off |
| Auto Load PO Cost / Selling / MRP / Disc in Receipt Note & Purchase | ON (all four) |
| Show PO Delivery Type / Purchase Type / Payment Terms | off |
| Show Profit Percent | ON |
| Enable Free Qty in Purchase | ON |
| Allow Stock Update upto Inward Stock | off |
| Un Register Purchase Limit for GST | 0 |
| Show shipping name in Inward / Load purchase as Indent in TO / Do not validate Min-Max in inward | off |
| Allow diff b/w Invoice and Payable amount | ON |
| Rate Get in Price Policy / Lock Header-Footer on Purchase Advice import | off |
| Number of days Invoice date can be less than purchase date | 0 |
| Print Barcode along with Kit Preparation / Repack | off |
| Show volume fields / scheme disc fields / previous purchase rate | off |
| Allow Inward Expired Stock in Opening Stock Migration | off |
| Write off PO after convert as RN/PI | off |
| Indent cut off validation on SKU type / Self indent support in Auto Indent / Show supplier InvNo in PO | off |
| Apply marginal GST calculation in buy back | ON |
| Load latest Selling and MRP in Purchase | off |

**General**
| Setting | Value |
|---|---|
| Show Inactive Items in LOV | off |
| Confirm before Printing / Clearing / Closing | ON |
| Branch wise Sequence No (Outward) / in Inward / based on Source Branch in TO-TI | ON |
| Show Item Amount / Item Part No | off |
| Enable Multi Currency | off |
| Enable Contain Search for Items | ON |
| Show All Location as last in Reports | off |
| Confirm alert message | ON |
| Show stock in Stock update LOV | ON |
| Show Expiry Date | ON |
| Show Purchase Invoice No in barcode batch LOV | off |
| Show notifications in TruePOS | ON |
| Enable Packing / Kit ingredient qty 0 / Auto Sales Price in Kit | off |
| Hide offer columns / Enable auto mail (Sales, PO) / Show Mfg Date / Show BatchNo / alias code search | off |
| Allow duplicate Eancode | ON |
| Enable batch details in Change Selling | ON |
| Show OTP generation in TP loyalty | ON |
| GoBill – fetch transactions from local for reprint | ON |
| Fill Customer Address by PinCode / Address mandatory in Customer Master | off |
| Enable sales posting to government | off (port 8524) |
| Validate Customer / Loyalty Redeem / Rep Master against OTP | off |
| Show HSN Code | off |
| Allow Create Child Customers with HO approval / Map family members by mobile | off |
| Markup/Down order config | 1234 |
| Enable Parent Category Relation / Raise Auto Indent to DC | off |
| Show Brand Prefix / Posting CR-DR diff as Round Off | off |
| Show only Home Outlet Rep in Master and Sales | off |
| Show global Customer/Customer Category for franchise | ON |
| Hide Company name in footer for Franchisee users | off |
| Download Error Sheet with Reason in Migration | off |

**Peripheral** – Enable Weighing Scale off; Print Server Enabled off; Print Cr Balance incl. A/C off; default online print off; Remote print off; Scan Auto No. as barcode off; Printer Server URL `http://127.0.0.1:6969`; Mobile remote printer URL same; Bill Amount for tax invoice 0; Calculate reprint count in SellSmart off; Validate bill save on print off; Print HSN in Tax Summary off; print profiles "Profile 1", orientation "Horizontal 1".

**Tender** – Allow Complimentary Bill off; Allow Due Bill off; **Allow Credit Card Bill ON**; Cheque off; Coupon off; **Allow RRN in Sales Bill ON**; **Allow Wallet ON**; Mandate Cheque Detail off; Allow Finance off; Show EMI Calculation off.

**Process Inward** – multiple RN selection off; Selling based on Auto Price Fix off; only supplier-mapped items off; Cost=Sell=MRP off; Sell=MRP off; Auto discount from supplier mapping off; Restrict duplicate item off; Expiry in PO off; Show all suppliers in PO off; Print barcode in purchase off; **PO Expiry Days 3**; **Auto load Items in inward ON**; Manual discount amount in PI off; Load selling by receiving branch in TI off; PR based on Bill No off; **Show Received Quantity in TI ON**; Do not validate purchase mandatory off; **Allow Back Date Purchase/Return/Orders ON**; PR to other suppliers off.

**Process Outward** – **Enable multiple Delivery Notes in Sales Bill ON**; **Allow SR based on Bill No ON**; Round-off Near / 100 Paisa; price load = Latest Price; Save SO qty as Indent off; Allow Rate Edit in Sales / SO / Quotation off; Freight in TO/TI off; Credit card details mandatory off; **Show Ship From Branch in SO & Quotation ON**; Calculation on Transfer Price off; Restrict duplicate items off; Capture SR slip no off; Allow change selling below cost off; Revision in SO/Quotation off; Auto load discount/freight from master off; Auto load current stock off; Allow SR without sales history off; **Allow Multiple DN in DN Return ON**; Order status maintain off; Rate edit in DN/SR off; Hide batch LOV off; SR history window Last 6 Months; Print barcode in Sales/TO off; Allow maximum discount off; Print kit ingredients off; **Allow Back Date Sales/Orders/Returns ON**; SR based on Bill; **Show MRP in batch LOV ON**; **Enable Multiple Address for Customer & Supplier ON**; Online sale tag off; decimals 2.

**Approval** – Approve Sales Order before Billing: off.

**Quick Add** (quick customer create popup) – default categories Other Customers / WALK-IN; Same Address for Shipping ON; scope Global; Allow State selection ON.

**Tax** – Enable InterState Purchase ON; InterState Sales ON; Consignment Purchase/Sales off; Import/Export off; Additional VAT off; Service related Taxes ON.

**Mobile Addons (GoBill / SellSmart / EarnSmart apps)** – all off except **Allow conversion in order ON** and **Allow Price Level In GoBill ON**; Auto sync interval 0; Expiry warn days 0; Geofencing off.

#### 6.1.5 Function Key Mapping (GWT)
Screen Name (dropdown of all ~137 screens) → grid Sno, Function Name (New, Edit, Clear, Save, Exit, View…), **Function Key** (F1–F12), **Special Key** (Normal / Shift / Ctrl / Ctrl+Shift / Alt / Alt+Shift / Alt+Ctrl / Alt+Ctrl+Shift). Defaults: New F3, Edit F4, Clear F9, Save F6, Exit F10, View F7. **F4 Update, F10 Exit**.

#### 6.1.6 Ledger Map (GWT *Accounts Posting Configuration*)
Transaction dropdown: Cash In, Cash Out, ConsumableIssue, Damage Stock Entry, Delivery Note, Distributor Payout, Gin Approval, Opening Stock, Purchase Invoice, Purchase Return, Receipt Note, Receipts, Sales Bill, Sales Order, Sales Return, service Invoice, Stock Transfer In, Stock Update, Transfer Out, Stock Classification Out/In. Grid: SNo, Description, Under Group, **LedgerName** (e.g. Cash Account → Cash in Hand; Cash In Amount → Indirect Incomes → "Cash In – InCome"). Defines which accounting ledger each POS transaction posts to. Save / Close.

#### 6.1.7 Asset LedgerMap (GWT Mapping)
Grid S.No, Item Code, Description, **Asset Ledger** – maps capital-asset items to asset ledgers. F6 Save, F9 Clear, F10 Close.

#### 6.1.8 Mail Server Configuration
Server Name, Port No, User Name, Password, From Email Id, TLS Status, SSL Status, Email Status (Active/InActive); **Send Test Mail**, Save, Reset, Reset to Default. Currently set to GoFrugal's default ZeptoMail relay (port 465, TLS+SSL, Active). Used by auto-mail of PO/Sales and Report Scheduler.

#### 6.1.9 Category Wise Sequence
Location + Tran Type (Sales …) → per-category bill-number sequences ("No configurations found … Click here to add").

### 6.2 Addon Provisioning (`addonProvisioning.do`)
Grid User Name, User Role – grants add-on app (GoBill, SellSmart, EarnSmart, GoSecure…) access to users. "No Users have been created yet."

### 6.3 Integrations
* **GoFrugal Alert** – SMS/WhatsApp alert service; page says the Alerts account is *not linked* (register / link links).
* **GST Efiling** – GSTR filing integration (same account-link landing page).
* **GOFRUGAL Gosure** – GoSure stock-audit app (same landing page).

### 6.4 Master Migration (GWT WPMigration)
Tabs **Sheet Generation / Upload Sheet / Upload Status**. Select a Master: Customer Master, Supplier Master, Item Master, Item Outlet Specific, Manufacturer Master, Opening Stocks [By Item Name], Opening Stocks [By Serial No], Price List, Stock Update, Item Category Mapping, Price Level Master, Price Level vs Item Mapping, Item Update, Locationwise Item Update, Customer Update, Supplier Update, Shipping Master, Bulk Repack Mapping, **Pet Details for Customers**, Item Batchwise update, Stock Update Using Alias. Buttons Load Config, Save Config, **Download CSV** (template), Clear Config, Close. Bulk import/export via CSV.

### 6.5 Document Upload Transactionwise
Filters Type, Location, Date range, search by Tran No. Grid S.No, Date, Tran No, Location, **Attach / View / Remove** – attach scanned supplier invoices etc. to transactions.

### 6.6 Add On Service(s) (`truelayout.do` – Organization Settings)
Company Details, **Print Upload** (upload custom print layouts), Manage Subscription link.

### 6.7 Manage Subscription (`managesubscription.do`)
Buy, Outstanding, Order History, Invoice History, Receipt History for the GoFrugal licence (Customer Id 1608135).

### 6.8 More
| Screen | What it does |
|---|---|
| **Year Begin Sequence Change** | Business Type + Location filter; shows current running numbers: Purchase 2388, Purchase Return 55, Sales 57844, Sales Return 1297, Delivery Challan In 0 / Out 1, Transfer In 3180, Transfer Out 3181, Goods Inward Note 0, Purchase Order 2, Kit Pack 0, Kit Ingredient 0, DN Return 0, Consumption Entry 0. **Edit** resets them at financial-year start ("can be updated only once in a year… cannot be reverted"). |
| **Session Management** | Cashier shift open/close with denominations – disabled until *Enable Session Management* is ON in Business Configuration → Outward. |
| **Reprint** | Type (Sales / Price Drop / Sales Order), Location, Date range, Advance Search → Search → reprint documents. |
| **Multiple Dispatch** | Type (Sales / Delivery Note / Transfer Out / Purchase Return), Location, Date → mark several documents as dispatched together. |
| **Report Scheduler** | New Schedule: pick Report Ids + Scheduled Time; e-mails reports automatically (needs Mail Server). None configured. |
| **E-way Update** | Generates/updates e-Way bills; disabled until *Eway Generation Type* chosen in Business Configuration → General. |
| **Barcode Config** | Add Config: label templates (fields, size) used by Barcode Printing. None defined. |
| **Service User Consent** | "Allow support team to access TruePOS" toggle for GoFrugal support login. |

---

# Part 8 – Finance and Accounts (AccountsEasy)

Clicking **Finance And Accounts** in TruePOS opens a *separate* application in a new browser tab:
`https://urbanpets.true-pos.com/TrueBooks/com.gofrugal.truebooks.transmdi.TransMDI/TransMDI.html` – **GOFRUGAL AccountsEasy OnCloud**. It shares the company, locations and login (Admin1) with TruePOS. POS transactions post into it automatically according to *Tools → Ledger Map* in TruePOS.

Top menu: **Masters | Voucher Entry | Receivables / Payables | Banking | Reports | Tools** + Menu Search + TRUEPOS link (back to POS) + print + settings gear.
Screens are GWT (function-key driven). Every voucher screen has: Location selector, **Financial Year selector** (2026_2027 / 2025_2026 / 2024_2025 / 2023_2024) and keys F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close, **F11 Narration**, **F12 Delete**.

### 7.1 Masters
| Screen | Fields / purpose |
|---|---|
| **Customers** (Customer Master [New]; tabs MAIN / SPECIFIC / GST) | Location Name*, Account Type (Sundry Debtors / Customers / Other Customers / Third Party Finance), Customer Name*, Building, Street, LandMark, City, Pincode, Phone, Mobile, Credit Limit, Credit Days, Customer Account Status. Creates the debtor ledger (POS customers sync here automatically as "Other Customers" under Sundry Debtors) |
| **Suppliers** | same layout for creditor ledgers (Sundry Creditors) |
| **Bank** (Bank Master [New]) | Location Name*, Account Type (Bank Account / Bank OCC Account), Account Name*, Account Credit Limit, Account Number, Building, City, Phone, Tax Registration No, Location Type (LOCAL / INTER STATE / IMPORT / UNREGISTERED), Cost Centres Enable, Bank Account Status, ERP Ref Code |
| Tax, Income, Expenses | ledgers under Duties & Taxes, Indirect Incomes, Indirect Expenses |
| more → Assets, Liabilities, Account Types | other ledger groups |
| more → Currencies (Instrument Type Master, Exchange Rate Type Master, Instrument Master, Currency Master, Exchange Rate Master) | multi-currency setup |
| more → Narration | reusable narration texts |
| more → Merge Ledgers | merge duplicate ledgers |
| more → RTGS/NEFT Beneficiary Master, Bulk Beneficiary Import | bank-transfer payees |
| **more → Ledger Master (New)** (smartreport grid) | all ledgers: Location, Ledger Code, Ledger Name, Ledger Group, Sub Group, Address, Pincode, Phone, Mobile, Credit Limit/Days, CST/TIN/PAN, STATUS, Currency, Area, Parent Group, GST No, Email, ERP Ref Code, Action; **NEW LEDGER** button. ~24,000 ledgers exist (every POS customer becomes a ledger) |
| more → Cheque Book Master, Budget Master (New) | |

### 7.2 Voucher Entry (`WebTrac/TracScreen.html`)
Common layout: Transaction, Date, RefNo, RefDate, VoucherSlno; grid **Cr/Dr | Particulars (ledger) | Debit | Credit**; line details (Name, Payment Mode, Chq.No, Bank Ref No./UTR No., Chq.Date, Branch, Cheque Name, Balance, Inst.Date, Account Number, Bank Name, IFSC, Account Type, Account Holder Name); Total Debit / Total Credit; Narration.

| Voucher | Default first line | Use |
|---|---|---|
| **Payment Voucher** | Dr (expense/supplier) … Cr cash/bank | money going out |
| Payment Single Voucher | one-line quick payment | |
| **Receipt Voucher** | Cr (customer/income) … Dr cash/bank | money coming in |
| Receipt Single Voucher | | |
| **Journal Voucher** | Dr/Cr any ledgers | adjustments, provisions |
| **Contra Voucher** | cash ↔ bank | deposits / withdrawals |
| more → Debit Note / Credit Note | supplier / customer adjustments |
| more → Purchase Voucher / Sales Voucher | manual purchase or sale not from POS |
| more → Opening Balance Entry / Opening Balance Import | year-start balances |
| more → Centralised Voucher Entry, Voucher Approval, Voucher Bulk Uploader | HO entry for all locations, maker-checker, Excel upload |
| more → Expense Entry [Mobile], Contra Voucher [Mobile] | mobile-app entries |

### 7.3 Receivables / Payables
| Screen | Fields | Purpose |
|---|---|---|
| **Billwise Receipt** | Group (All Groups/HQ), Location, FY, **Adjustment Mode** (Ledger/Group), Code/Account (customer), Date, **Adjustment** (Manual/Auto), Auto Amount, Amount, Discount (No/Yes), Others (No/Yes), RefNo, VoucherSlno, Cur.Amt, BaseCur.Amt; pending-bills grid **LocationName, RefNo, DocNo, BillDate, BillAmount, Receivable, Adjusted, Balance, Days, [Y/N], Amount, Attachment**; discount line (Account, Disc%, DiscAmt); receipt mode block (Mode, Name, Payment Mode, Chq.No, UTR, Chq.Date, Branch, Cheque Name, Balance); summary On Account, Pending, Adjusted, Balance | collect money from a credit customer and knock it off against specific bills |
| **Billwise Payment** | identical with **Payable** column and bank details (Inst.Date, Account Number, Bank Name, IFSC, Account Type, Holder) | pay a supplier against specific invoices |
| Centralized Billwise Receipts / Payments | same, across locations from HO |
| A/R Reports | Accounts Receivables Summary, Aged Receivable, Customer Due Bills Statement, Customer Summary/Detailed, Customer Pending Days, Customer Outstanding (+Detailed), Customer BillWise Settlement, Month Wise Ageing Receivable, Area wise / Rep wise Outstanding Detailed |
| A/P Reports | Accounts Payables Summary, Aged Payable, Accounts Payable Due Bills, Supplier Detailed, Supplier Pending Days, Supplier Detailed-Billwise Status, Supplier Due Bills, Supplier BillWise Settlement, Month Wise Ageing Payable |
| more → Billwise Import | upload bill-wise adjustments |

### 7.4 Banking
| Screen | Fields / purpose |
|---|---|
| Customer Bank Master | customer bank details for cheques |
| **Cheque Deposit** | Code, Depositing Bank, Deposit Date, From/To Date; grid Date, Particulars, Amount, ChqNo, ChqDate, BankName, Branch, Flag → mark cheques-in-hand as deposited |
| **Bank Reconciliation** | Group, Location, FY, Bank, Use ChequeDate, Cheque Status (Both/UnCleared/Cleared), TranType (Both/Received/Paid), From/To Date; grid Location, Date, Particulars, RefNo, ChqNo, ChqDate, **DepositOn, ClearOn**, Debit, Credit, BankName, Branch, Narration; summary Opening Balance, Balance as per our Book, (+) Cheque Issued but not realised, (−) Cheque Deposited but not reflected, **Balance as per Bank** |
| Cheque Bounce, Cheque Represent, Cheque Print | |
| more → Cheque Details, Bank Payment Advice, Cheque Collection, Bank Reconciliation Statement, Bank Reconcilation (New) | |

### 7.5 Reports (accounting)
* General Ledger, Grouped Ledgers
* Financial Statements → Parent & SubGroup Wise: **Trading – Profit & Loss**, **Balance Sheet**, **Trial Balance**, Trial Balance Location Wise
* Account Books: Day Book, Cash Book, Bank Book, Purchase/Sales Book with Opening Balance, Confirmation of Accounts, MFR Customer wise Ledger, Day Transaction Summary
* Registers: Sales, Purchase, Sales Return, Purchase Return, Debit Note, Credit Note, Payment, Receipt, Journal, Contra, Payment/Receipt (Bill wise)
* Register Reports With Column Wise Breakup (Sales, Purchase, SR, PR, Sales & SR summary, Service)
* more → Registers With Tax Breakup (Accounts Only); Others (Opening Balance Report, Billwise Payments/GRN Processed, Bill wise Payment/Receipt, Voucher Import Audit, Memorandum, Expenses, Manual Sales/Purchase Journal, Chq-in-hand journals); Receipt/Payment Reports (Salesman wise collection / summary); Audit Summary (Transactions, Masters, Opening & Closing Stock, Outlet Group Mapping); Master Reports (Group Master, Ledger Master, Customer Bank Master, Narration, RTGS/NEFT Beneficiary); TDS Reports (Calculation & Deduction Summary/Detailed, Deduction Details, for Advance); General Ledger Report [NEW]; Voucher Approval Report; Cost Center Breakup; BillWise Import Audit

### 7.6 Tools
* Configuration → **Admin Configuration** (new Angular UI `ngx-truebooks`) – accounting preferences
* New Group Location – create a location group (HQ) for consolidated books
* **Year End Process** – close FY, carry forward balances
* **Month End Process** – lock a month

### 7.7 How POS and Accounts connect
1. TruePOS *Ledger Map* decides ledgers per transaction type (Sales Bill → Sales ledger + tender ledgers; Purchase Invoice → Purchase + supplier; Cash In/Out → Cash in Hand…).
2. Every Sales Bill / Purchase Invoice / Return / Damage entry creates the corresponding voucher automatically.
3. Credit sales appear as receivables → settle with **Billwise Receipt**. Supplier invoices → **Billwise Payment**.
4. Cheques → Cheque Deposit → Bank Reconciliation.
5. Period end → Month End / Year End Process; Year Begin Sequence Change in TruePOS resets document numbers.

---

# Part 9 – How the Modules Connect

This file answers one question for every master, transaction and setting: **where is it used, and what does it feed?**
Read it as: *Source → consumed by → shows up in*.

---

### 8.1 Master data → where each master is used

| Master (Master module) | Used by (transactions / screens) | Appears in (reports / accounts) |
|---|---|---|
| **Item** (code, EAN, name, cost, sell, MRP, product type, tax-inclusive, batch/expiry rule, category, GST, HSN) | Every item grid: Sales Bill / Quotation / Order / DN / Return, PO / Receipt Note / Purchase Invoice / Return, Transfer Out/In, Opening Stock, Damage, Stock Update, Barcode Printing, Change Selling, Price Fixing, Repack, Kit Preparation, Shelf Talker, Indent | Item Master report, all Itemwise / Categorywise sales, purchase and stock reports; HSN + GST rate drive GST Sales / Purchase Summary |
| **Item Category** heads (Brands, DEPARTMENT, CATEGORY) + **Item Category Value** | Item form → Category tab (mandatory heads must be filled); Category Filter in Change Selling, Mark Up/Down, Auto Indent | First three columns (BRANDS, DEPARTMENT, CATEGORY) of most reports; Categorywise Sales, Categorywise Stock, Fast/Slow moving |
| **Brand** (prefix, alias) | Item form → Brand; Auto Indent filter | Brand Master report, Brand columns in sales/purchase/stock reports |
| **UOM / UOM Vs Item Mapping** | Purchase in box, sale in piece (conversion at grid level); Repack conversion | Conversion unit column in Purchase Detail, UOM Vs Item Mapping report |
| **Tax Slab / GST Tax** | Item → GST tab; tax computed on every sales & purchase line (Local → CGST+SGST, Interstate → IGST) | Tax Master report, GST Sales Summary / Taxwise, GST Purchase Summary, Accounts → Duties & Taxes ledgers |
| **Item Property Setting** (branch-wise Min/Max, allow sale/purchase, negative, shelf/rack/box) | Blocks or allows the item in that branch's Sales / Purchase screens; Min/Max = MBQ used by Indent Entry (MBQ column), Auto Indent, PO Replenishment | MBQ Detail, Re-order Report, Indent Based On Replenishment; Rack/Shelf columns in Item Master |
| **Item EAN/UPC Entry** (extra barcodes) | Scanning at Sales Bill / Purchase resolves any mapped barcode to the item | Eancode Detail report |
| **Assembly / Kit Mapping** | Kit Preparation & Kit Unpack (Inventory) convert component stock ↔ kit stock; kit sells as one line at POS | Kit Mapping, Kit Preparation, Kit Unpack, Billwise Itemwise Sales With Assembly Details |
| **Item Price Change** | Alternative entry point to change prices; same effect as Change Selling | Price Drop, Price Level Advance reports |
| **Customer** (category, credit limit/days, payment mode, sales type, GST type, sales formula, pet details) | Sales Bill/Quotation/Order/DN/Return header (ENTER/TAB lookup). Category → default discount & loyalty; Payment Mode → which tenders allowed; Credit Limit/Days → credit-bill validation; GST Type → Invoice Type (Retail / Tax / Exempted); Sales Type → tax split; Sales Formula → price level applied; Pet Details → F1 Pet Details on bill | Customer Master, Customer Pet Details, Customerwise Itemwise Sales, Customer Outstanding (Accounts). Every customer becomes a **Sundry Debtors ledger** in AccountsEasy |
| **Customer Category** (discount %, loyalty on/off, app access, business type) | Auto-applied when the customer is picked on a bill | Customer Master report (Category column) |
| **Area** | Customer address; Branch area code | Areawise Sales Summary, Area wise Outstanding (Accounts) |
| **Loyalty Program Info / Loyalty Points Update** | Points earned on Sales Bill (per Bill Wise Loyalty slabs), redeemed as tender ("Redeemed Point"); manual +/- adjustment | Customer Loyalty Details, Customerwise Itemwise Points, Daily Sales Summary (Redeemed Point column) |
| **Pet Types / Breed / Color** | Customer → Pet Details tab; Sales Bill F1 | Customer Pet Details report |
| **Supplier** (purchase type, purchase mode, credit limit/days, GST type, mail type) | PO / Receipt Note / Purchase Invoice / Purchase Return header; Item → default supplier; Purchase Type → tax split; Mail Type → PO e-mail format | Supplier Master, Supplierwise Purchase, Supplier Sales Report (sales of that supplier's items), Supplier Vs Items. Every supplier becomes a **Sundry Creditors ledger** in AccountsEasy |
| **Branch** (address, GST, business type, area/circle code) | Location selector on every transaction; Ship From on Quotation/SO; To/From Branch on Transfer Out/In; Indent To Location; branch-wise sequence numbers; printed bill header | Branch Master report, every "Storewise / Branchwise" report, Trial Balance Location Wise (Accounts) |
| **Distribution Centre Mapping** | Which branch an Indent / Auto Indent is raised to | Indent Summary |
| **Register** (counter, prefix, invoice sequence) | Determines bill number prefix (CO-225) and sequence when a device bills; Online Sales Allowed flag | Daily Sales Summary [Till Wise], Counterwise Sales, Tender Type Detail (Till column) |
| **Promotion Management** | Offers auto-applied on Sales Bill when *Enable Promotion* is ON (currently OFF) | Offer Claim Report, Hold/Discount approval reports |
| **Tender Type / Tender Type Values** | Options in the tender window after F6 Save on Sales Bill (Cash built-in; Card, Wallet, Credit, Coupon, Finance per Business Configuration → Tender); Group Ledger → posting ledger | Tender Type Detail, Daily Sales Summary tender columns, Tender Wise Sales dashboard pie, Accounts → Cash/Bank ledgers |
| **Transporter / Freight Settings** | Delivery Note / Transfer Out shipping details, e-Way bill; freight amount on PO / Purchase Invoice | Freight column in Purchase Detail / Daily Sales Summary |
| **GSTNo Restriction Master** | Validation of customer GSTIN when Invoice Type = Tax Invoice | — |
| **Unicode Master** | Regional-language branch text on printed bills | — |

---

### 8.2 Transactions → what each one changes and where it flows next

| Transaction | Stock effect | Money / ledger effect (via Ledger Map) | Next step it enables | Reports that read it |
|---|---|---|---|---|
| **Sales Quotation** | none | none | convert to Sales Order (Quote No carried) | Quotation Summary / Details |
| **Sales Order** | reserves stock (Stock Reserve Status) | Advance posted per *Sales Order advance goes to* (To customer account) | Sales Order Approval (if enabled) → Delivery Note or Sales Bill (SO No carried); Auto-indent if *Save SO Qty as Indent* | Sales Order Summary/Detail, Sales Order Stock Status, Stock Reserve Status |
| **Delivery Note** | stock OUT | none until billed | Sales Bill (multiple DNs can be merged – config ON); DN Return reverses | Sales DeliveryNote Summary/Detail, Delivery Bill reports, Kitchen Preparation |
| **Sales Bill** | stock OUT (or already out via DN) | Sales voucher + tender ledgers (Cash in Hand, Card, Wallet, Sundry Debtor for Credit); loyalty points earned; customer credit balance reduced | Sales Return (against Bill No); Reprint; Multiple Dispatch; Billwise Receipt in Accounts for credit bills | All Sales reports, Daily/Monthly Sales Summary, GST Sales Summary, Sales Item Margin, Tender Type Detail, Dashboard tiles |
| **Sales Return** | stock IN | reverses sales; refund per Return Mode (RRN credit / Credit Note / Cash / Wallet / Card) | RRN balance usable as tender on next bill | Sales Return Summary / Itemwise / Customerwise, Sales Return Register (Accounts) |
| **Delivery Note Return** | stock IN | none | — | Sales DeliveryNote reports |
| **Transfer Out** (HO) | stock OUT at source, "in transit" | inter-branch ledger | **Transfer In / Transfer In Touch** at destination (TO No carried); optional Transfer Out Approval (disabled) | Stock TransferOut Summary/Detail, Stock In Transit, TO Vs TIN, Purchase Transit |
| **Transfer In** (branch) | stock IN at destination; *Received* column vs sent qty | inter-branch ledger | discrepancy handling | Stock TransferIn Summary/Detail, Stock Transfer Discrepancy |
| **Indent / Auto Indent** | none | none | HO raises Transfer Out (or PO) to fulfil; Indent Cancellation | Indent Summary, Indent Based On Replenishment |
| **Purchase Order** | none | none | Receipt Note or Purchase Invoice pulls PO lines (PO No/Date); PO Cancel for remainder; expires after *PO Expiry Days* (3) | Purchase Order Summary/Details, Purchase Transit, PO/Purchase Discrepancy, PO Replenishment |
| **Receipt Note** | stock IN (with batch/expiry) | none | Purchase Invoice pulls RN (GRN No) | ReceiptNote reports, GIN Summary/Detail |
| **Purchase Invoice** | stock IN (if no RN); updates item Cost, Landing Cost, Sell, MRP, expiry | Purchase voucher + Sundry Creditor liability + input GST | Barcode Printing (Load Transaction), Purchase Return (Reference No), Price Drop (Invoice No), Billwise Payment in Accounts | Purchase Detail/Summary, GST Purchase Summary, Supplierwise Purchase, Purchase Register (Accounts) |
| **Purchase Return** | stock OUT | Debit Note / Cash to supplier | — | Purchase Return Summary / Supplierwise, Purchase Return Register |
| **Opening Stock Entry** | initial stock IN with cost | Opening Stock ledger | — | Opening Stock Detail, Opening & Closing Stock Audit |
| **Damage Stock Entry** | stock OUT at cost (Wastage / Damage / Theft) | Damage Stock Entry ledger | — | Wastage/Damage Stock (+Detail) |
| **Stock Update Entry** → **Stock Update Approval** | ± adjustment to counted qty (after approval if enabled) | Stock Update ledger | — | Stock Update Detail, Transactionwise Stock Register |
| **Repack / Kit Preparation / Kit Unpack** | bulk → packs, components ↔ kit (with loss) | Stock Classification In/Out ledgers | packs/kits become saleable items | Repack Summary/Detail, Kit Preparation/Unpack, Stock Conversion Report |
| **Change Selling / Price Fixing / Price Level / Price Drop** | none (price only) | none | new prices used by next Sales Bill; price levels applied per customer Sales Formula | Price Drop, Price Level Advance, Price List |
| **Barcode Printing / Shelf Talker** | none | none | labels for shelves / items | Reprint Count Details |

---

### 8.3 Tools (settings) → what each one controls

| Setting screen | Controls |
|---|---|
| **Business Configuration → Outward** | which header fields appear on sales screens (Delivery Type, Salesman, Sales Type…), whether customer is mandatory, discount/tax editability, session management, promotions, settlement, SO approval, e-invoice, SO advance ledger |
| **→ Inward** | auto-loading PO cost/sell/MRP/discount into Receipt Note & Purchase, free qty, margin validation, invoice-vs-total tolerance, back-dated purchase |
| **→ General** | confirmations, branch-wise sequence numbers, expiry/batch columns, contain-search, duplicate EAN, OTP validations, notifications |
| **→ Peripheral** | weighing scale, print server URLs, print profiles |
| **→ Tender** | which tender buttons exist in the Sales Bill tender window |
| **→ Process Inward / Process Outward** | multi-RN / multi-DN merging, rate editing rights, rounding, back-dating, SR based on bill, MRP in batch LOV, multiple addresses |
| **→ Approval / Quick Add / Tax / Mobile Addons** | SO approval; quick-customer popup defaults; interstate/consignment/import/export enablement; GoBill/SellSmart app behaviour |
| **Userwise Configuration** | per-user override of the same switches |
| **Role Master / Employee Master** | which menus & operations a user can open; salesman discount cap and commission; default branch |
| **Function Key Mapping** | F-key layout of every GWT screen |
| **Ledger Map / Asset LedgerMap** | ledger used by each POS transaction type when posting to AccountsEasy |
| **Year Begin Sequence Change / Category Wise Sequence / Register** | document numbering |
| **Mail Server + Report Scheduler** | e-mailing POs, bills and scheduled reports |
| **Master Migration** | bulk CSV import of items, customers, suppliers, opening stock, prices, pet details |
| **Barcode Config** | label template used by Barcode Printing |
| **Indent CutOff Time Configuration / Distribution Centre Mapping** | indent routing and timing |

---

### 8.4 End-to-end chains (one line each)

1. **Retail sale**: Item + Customer + Register + Tender masters → Sales Bill → stock down, Sales/Cash ledgers, loyalty points → Daily Sales Summary, GST Sales Summary → AccountsEasy Sales Register / P&L.
2. **Credit sale to collection**: Customer (Payment Mode = credit, Credit Limit) → Sales Bill (Credit tender) → Sundry Debtor ledger → Customer Outstanding / Aged Receivable → **Billwise Receipt** → Cheque Deposit → Bank Reconciliation.
3. **Order to delivery**: Quotation → Sales Order (advance) → Sales Order Approval → Delivery Note → Sales Bill → Delivery Bill reports.
4. **Buying**: Supplier + Item → Purchase Order → Receipt Note → Purchase Invoice (cost/price/expiry update) → Barcode Printing → Sundry Creditor → **Billwise Payment**; discrepancies → Purchase Return.
5. **Branch replenishment**: Item Property Setting (Min/Max) → Auto Indent / Indent (to DC) → Transfer Out at HO → Transfer In at branch → Stock Transfer Discrepancy / TO Vs TIN.
6. **Stock hygiene**: Stock Update Entry → Stock Update Approval; Damage Stock Entry; Repack / Kit; Item Age & Expiry reports → Change Selling / Mark Down for near-expiry stock.
7. **Period end**: Reports (GST summaries) → GST e-filing integration; Year Begin Sequence Change (POS) + Year End Process (Accounts).

---

