# Module 8 – HOW THE MODULES CONNECT (cross-reference)

This file answers one question for every master, transaction and setting: **where is it used, and what does it feed?**
Read it as: *Source → consumed by → shows up in*.

---

## 8.1 Master data → where each master is used

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

## 8.2 Transactions → what each one changes and where it flows next

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

## 8.3 Tools (settings) → what each one controls

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

## 8.4 End-to-end chains (one line each)

1. **Retail sale**: Item + Customer + Register + Tender masters → Sales Bill → stock down, Sales/Cash ledgers, loyalty points → Daily Sales Summary, GST Sales Summary → AccountsEasy Sales Register / P&L.
2. **Credit sale to collection**: Customer (Payment Mode = credit, Credit Limit) → Sales Bill (Credit tender) → Sundry Debtor ledger → Customer Outstanding / Aged Receivable → **Billwise Receipt** → Cheque Deposit → Bank Reconciliation.
3. **Order to delivery**: Quotation → Sales Order (advance) → Sales Order Approval → Delivery Note → Sales Bill → Delivery Bill reports.
4. **Buying**: Supplier + Item → Purchase Order → Receipt Note → Purchase Invoice (cost/price/expiry update) → Barcode Printing → Sundry Creditor → **Billwise Payment**; discrepancies → Purchase Return.
5. **Branch replenishment**: Item Property Setting (Min/Max) → Auto Indent / Indent (to DC) → Transfer Out at HO → Transfer In at branch → Stock Transfer Discrepancy / TO Vs TIN.
6. **Stock hygiene**: Stock Update Entry → Stock Update Approval; Damage Stock Entry; Repack / Kit; Item Age & Expiry reports → Change Selling / Mark Down for near-expiry stock.
7. **Period end**: Reports (GST summaries) → GST e-filing integration; Year Begin Sequence Change (POS) + Year End Process (Accounts).
