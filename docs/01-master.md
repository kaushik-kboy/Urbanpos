# Module 1 – MASTER (Setup data)

The Master menu holds all reference data that transactions depend on: items, customers, suppliers, tax, branches, registers (billing counters), promotions and system lookups. Nothing is billed, purchased or moved here; these screens only *define* the data.

Two UI styles exist:
* **Angular list/form masters** (`/TruePOS/masters/index.html`) – list grid with `View Active/Inactive` chip, `Add <Master>` button, per-row edit icon and (for items) clone icon, search icon in header, paging (10/20/30/40/50 per page). The form opens as *Create <Master>* / *Edit "<name>"* with tabs and **Save / Cancel**.
* **Legacy GWT screens** (`/TruePOS/com.gofrugal.raymedi.*`) – keyboard-driven, buttons labelled with function keys (F4 Edit, F6 Save, F7 View, F9 Clear, F10 Close…).

---

## 1.1 Item group

### 1.1.1 Item Category
Defines the **category heads** (dimensions) used to classify items. In this company three heads exist: **Brands, DEPARTMENT, CATEGORY**.

| Column | Meaning |
|---|---|
| Id | Internal id of the head |
| Name | Head name (e.g. DEPARTMENT) |
| Is Mandatory | Yes = every item must have a value for this head |
| Status | Active / Inactive |

### 1.1.2 Item Category Value
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

### 1.1.3 Brand
Brand / manufacturer list. Grid: Name, Created Time, Updated Time.

| Field | Type | Purpose |
|---|---|---|
| Name | text | Brand or manufacturer name (Pedigree, Royal Canin…) |
| Status | Active/Inactive | |
| Prefix | text | Optional prefix used when auto-generating item codes/barcodes for this brand |
| Alias Code | text | Short code for imports/exports |

### 1.1.4 Item  (core product master)
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

### 1.1.5 Item Property Setting (`angular-modules`)
Bulk grid editor for branch-wise item properties. Filters: **Based On** (Branch / Circle / Area / State / Item) and **Item Status** (All / Active / InActive). `Show Fields` opens *Select Fields to Display* with checkboxes: Minimum Quantity, Maximum Quantity, Sales, Purchase, Sales Return, Purchase Return, Allow Negative, Store Pickup, Tax Inclusive, Margin Perc, Shelf No, Rack No, Box No (+ Select All / Clear All).
Grid columns: S.no, Item Code, Item Name, Min Qty, Max Qty, Sales, Purchase, Sales Return, Purc Return, Allow Negative, Tax Incl, Store Pickup, Margin Perc, Shelf No, Rack No, Box No.
Use: set re-order Min/Max (MBQ) per branch, block sale/purchase of an item in a specific branch, set shelf/rack/box location for picking.

### 1.1.6 Item EAN/UPC Entry
Lists items (S.no, Item Code, Item Name) with **Edit** → panel showing Item Code, Count, Item Name, Branch (default *All Locations*) and a sub-grid **S.No | EAN/UPC Code | Action** with `Add` to attach multiple barcodes to one item. Buttons Save / Cancel. Use when a product ships with several barcodes (different pack printings).

### 1.1.7 Assembly
List of assembly (bundle) items: S.No, Item Code, Item Name, Alias, EAN Code. An assembly item is sold as one line but composed of component items (defined in *Kit Mapping*). Currently no records.

### 1.1.8 Kit Mapping
Grid: S.no, Item Code, Kit Name, Quantity, Basic Cost, Selling Price. Maps component items and quantities to a kit/assembly item; Kit Preparation / Kit Unpack (Inventory module) convert stock between components and kit.

### 1.1.9 Item Price Change
Grid Id, Name – pick an item to change its Cost / Sell / MRP outside the item form (price history is kept; reports *Price Drop* and *Price Level Advance* read from it).

### 1.1.10 UOM (Conversion)
Unit-of-measure master. Grid: Name, Updated Time. Form: `Name` (e.g. Kg, Pcs, Box) and `Alias`.

### 1.1.11 UOM Vs Item Mapping
Grid: Item Id, Item Name, Base Uom, Sub Uom, Uom Conversion – defines that 1 Box = N Pcs etc. so purchase can be in boxes and sale in pieces.

### 1.1.12 Tax Slab
Amount-based tax slabs (used for items whose GST rate depends on price). Form: `Name` + rule rows **S.No | From Amount | To Amount | Tax (GST master dropdown)**.

---

## 1.2 Customer group

### 1.2.1 Customer Category
Grid: Name, Created, Updated. Form:

| Field | Purpose |
|---|---|
| Name | e.g. WALK-IN, Regular, VIP, Breeder |
| App Access | Yes/No – allow customers of this category to use the customer app |
| Enable Loyalty | Yes/No – loyalty points accrue for this category |
| Discount Percent | default bill discount for this category |
| Status | Active/Inactive |
| Business Type | ALL / COCO / FRANCHISE / BRANCH / DISTRIBUTION CENTER / SERVICE UNIT / FOFO / ASP – which branch type this category applies to |

### 1.2.2 Customer
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

### 1.2.3 Area
Delivery/marketing areas. Form: `Name`, `Branch` (GLOBAL or specific). Used in customer address and *Areawise Sales Summary*.

### 1.2.4 Loyalty Program Info (`angular-modules` – "Loyalty Program Master")
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

### 1.2.5 Loyalty Points Update
Manual adjustment: `Customer` (search), `Points` (+ button to add / negative to deduct), `Remarks`, **Update / Clear**.

### 1.2.6 Pet Masters
* **Pet Types** – Name, Status (Dog, Cat, Bird…).
* **Breed Master** – Breed Name, Pet Type (dropdown), Status.
* **Color Master** – Name, Status.
These feed the Customer → Pet Details tab.

---

## 1.3 Supplier
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

## 1.4 Tax
* **GST Tax** – list only (Description, Status, Created, Updated): GST 0% Tax, GST Exempted, GST 5%, 12%, 18%, 28%, 28%+Cess 12/15/36/60/160%, GST 3% … (31 rows). Maintained by GoFrugal; selected in Item → GST tab.
* **GSTNo Restriction Master** – Grid Id, Gst No, Status. Form: `GST No`, `Status`. Stores GSTINs that are *restricted/blocked* for B2B billing validation.

---

## 1.5 Branch
### 1.5.1 Branch
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

### 1.5.2 Distribution Centre Mapping (`angular-modules`)
Grid S.No | Branch Name | Action with `+ ADD`, Save, Cancel. Marks which branches act as distribution centres (source for Indent / Transfer Out).

---

## 1.6 Register
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

## 1.7 Promotion → Promotion Management (GWT "Offer Management")
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

## 1.8 More → Tools

### Master Configuration
A launcher page (search box "Search master") listing **every** master in the system, including many not on the menu:
Transporter, Branch, Item Category, Tax, Gst taxes, Register, Item Category Values, Tax Area, Item, Customer Category, Customer, Relationship Officer, Area, Brand, Supplier, Tax Commodity, Holiday, Microfinance Branch, Department, Designation, Item Price Change, Loyalty, Tender Type, State name, Conversion, UomProduct, TaxSlab, Customer Credential, Customer Price Rules, UpdateLoyalty, Circle, Denomination, Pet Types, Breed Master, Color Master, Service Master, Claim Master, Center Master, Shipment, Reason, Expiry Rules, Channel, Customer Profile, Customer Source Master, Payment Type, Order Type Master, Item Type Master, Price Level Master, Area/Beat Master, REP Master, Customer Category Values, Gift Master, Supplier Group Master, Doctor, Item Classification, Tender Type Value, GST Master.

### Tender Type
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

### Tender Type Values
Sub-values of a tender (e.g. Card → Visa, Master). Form: Name, Status, Tender Name (parent tender), Group Ledger (accounting ledger), Branch.

### Unicode Master (`angular-modules`)
Grid Branch Code, Branch Name, Unicode, Address, City, State, Country – stores regional-language (Unicode) branch text for bill printing. Update / Cancel.

### Master Attributes
Grid S.No, Id, Name + Edit – define extra custom attributes on masters.

### Addon Devices Inactivation
Grid S.No, Model Name, Last Used By, Last Used Time, Status, Action – deactivate registered add-on devices (weighing scale, EDC, mobile app devices).

## 1.9 More → Utility
### Transporter
Grid Name, Phone, Branch. Form tabs General (Name, Trans Mode Road/Rail/Air/Ship, Address Line 1, Area, State, Phone, Email, Status) and GST (GST No, Pan No). Used in e-Way bill / delivery note.

### Freight Settings (GWT Mapping screen)
Filters Based on (Branch/State/Circle/Area), Item Status (Active/Inactive/All); grid S.No, Code, Delivery, Amount – freight charge per delivery type. F6 Update, F9 Clear, F10 Close.
