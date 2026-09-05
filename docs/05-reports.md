# Module 5 – REPORTS

All reports open in the **SmartReport viewer** (`/smartreport/index.html`). Every report has a numeric id shown in the title bar, e.g. *110158 : Daily Sales Summary (Store Wise)*. The complete menu of ~190 reports is listed in [00-menu-tree.md](00-menu-tree.md); this file explains how the viewer works and the columns of the most-used reports.

## 5.0 Report viewer – how it works

### Filter panel (left slide-out, funnel icon with a badge = number of applied filters)
| Group | Contents |
|---|---|
| **Applied Filter List** | shows current filters (e.g. Date Range : Today, Location : All Location) with ⊗ to remove each |
| **Date** (mandatory for transaction reports) | *Date Range* picker: Start Date / End Date calendars + presets **Today, Yesterday, This month, Last Month, This year, Last year, Financial year, Custom Days, Custom Months** → Apply / Cancel |
| **Standard** (mandatory) | report-specific pick lists; for almost all reports **Location** (multi-select, default *All Location*); others add Supplier, Customer, Category, Item, Till/Counter, User etc. |
| **Advanced** | build your own conditions: *Select column* → *Select condition* (=, >, contains…) → *Enter Value* → **Add** |
| Save this filters | remember the filter set for this report |
| **Apply** | run the report |

The panel opens automatically the first time a report is opened. Reports without a date (masters, current stock) just need Apply.

### Left toolbar icons
Filters · Settings · Related Reports · Favourite Reports · Export Reports (Excel/CSV/PDF) · Send Mail · Scheduler · Pivot · Print Preview · What's New · About Report.
Top-right of the grid: **Config Settings** (column chooser / Create-update Formula column), **Refresh**, **Favourite** (star), **Dynamic Chart** (bar/pie/line – charts can be pinned to the Dashboard, which is why the home page says "Start building your own dashboard").

### Result grid
Sortable columns with ▾ menus (sort, hide, group), **NetTotal** footer row, pager (Go to page, rows per page 50/100/150/200/250), "Query Execution: N ms", "Total: N Record(s)". Values in ₹ with Indian grouping (1,55,674.00).

---

## 5.1 Masters reports
Data dumps of master tables – no date filter.

| Report | Key columns (what to use it for) |
|---|---|
| **Item Master** (110110) | S.No, BRANDS, DEPARTMENT, CATEGORY, Item, Item name, Unicode Item Name, Brand name, Item alias, Rack, Product type, Allow Negative Stock, Weighable, Discount, Status, Focus Product, Non MRP, Packing Charge, Selling Price Policy, Item Short Name, Landing cost, MRP, Pur_net, Packed price, Selling, ISBN, Yop, Author, Total pages, Expiry Date Format, Show online store, BEM %, GST Rate, HSN Code, Purchase/Sales Abatement, Purchase GST On, Sales GST On, Is Exempted, Tax Type, Image Upload, Item Open Type, Weight, Supplier, Store Pickup, Inclusive of tax, Batch/Expiry, Mfg Date, Digits after decimal, MFR Format, Item Preparation Status, Repack Conversion (g), Description, Base UOM (+Volume), Sub UOM (+Volume), Tax %, Addl. Tax %, Sell_Through, DMS, Apple_Care, Service Applicable, created_date, Material Type/Group, Shelf No, Flat Offer, Item type, Minimum Selling, Online Special Price, Shelf Life, Minimum Shelf Life, Unique Barcode Id, Sell By. → full item audit / export for price checks |
| **Customer Master** (110126) | Title, Code, Customer Name, Address, Place, Postal code, State, Phone, Mobile, Email, GST No, GST Type, Sales type, Aadhar, PAN, Credit Days, Credit Limit, Loyalty Allowed, Loyalty Point, Invoice Type, Gender, Loyalty Amount, Category, Business Type, Is Exempted, Exempted Reason, Customer Status, Mail Type, Family Customer Code, Birthday, Anniversary, Coupon Limit, Created Date, Customer credit balance, Delivery Beat, Delivery boy, Discount %, Rep Code, Branch… → CRM export, loyalty balances |
| **Customer Pet Details** | S.No, Code, Customer Name, Breed, Type, Pet Name, Gender, Age, Birthday, Color, Height, Weight, Length, Remarks → pet-birthday campaigns |
| Customer Loyalty Details, Customer Parent List, Supplier Master, Brand Master, Tax Master, Area, Branch Master, Supplier Vs Items, Price List, Employee Master, RO Master, UOM Vs Item Mapping, Kit Mapping | same idea for the other masters |

## 5.2 Sales reports
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

## 5.3 Purchase reports
| Report | Columns / purpose |
|---|---|
| **Purchase Detail** (110120) | BRANDS/DEPARTMENT/CATEGORY, GRN No/date, Supplier Bill No, Inv date, Supplier Name, Item code/alias/HSN/name, Received Qty, Purchase rate, Selling, MRP, Expiry date, GST Perc/Amt, Tax Goods Value, Discount %, Disc. Amount, Cash disc., Rebate, Purchase amount, Net margin %, CGST/SGST/IGST, Cess, Conversion unit, Status, Net cost, Profit %, Margin % → line-level purchase audit and margin check |
| **GST Purchase Summary** (116505) | Inv No/date, Purchase date, HSN, Item, Qty, Supplier, GST No, State, Taxable amount, tax split, Freight, Supplier branch GST → GSTR-2/ITC |
| **Purchase Order Summary** (110117) | Sequence No, PO date, Expiry date, Currency, PO amount, Credit days, Delivery period, Branch, Supplier, Discount, Status, Freight, tax split, Order reference, Expiry Status, Approved Date, Supplier Bill No/Date → open PO tracking |
| Purchase Order Details, Purchase Transit, ReceiptNote Nowise Summary/Detail, ReceiptNote Itemwise, Purchase Summary, GIN Summary/Detail, Purchase Detail Serial, Purchase Order vs Invoice Report | |
| Pending/Cancelled: Purchase Order Cancel | |
| Returned: Purchase Return Supplierwise Detail, Purchase Return Summary | |
| Purchase Analysis: Supplierwise Purchase Summary/Details, PO/Purchase Discrepancy, Datewise Itemwise Consolidated Purchase Report, Purchase Register Summary | |

## 5.4 Inventory reports
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

## 5.5 Audit reports
Foot Fall Details, Reprint Count Details, **User Login Summary** (Login ID, Total No of logins, Days/Hours/Minutes/Seconds), Audit Viewer Report, Audit Detail Report, GST Tax Change Audit, Service User Consent Summary, Email Audit Viewer, Audit Report / Detail (Cart entry Clear).

## 5.6 My Reports
User-saved/custom reports: Counterwise Sales Summary, Sales MIS report, Off Take – Dealer Tracker Report.

## 5.7 Production
Production Plan, BOM Report, Production Costing Summary/Detail, Sales/Production Variance (for kit/assembly manufacturing – unused here).

## 5.8 More
Serialized Reports (Serial Number History, Serial No Wise Stock Detail), Offline Exported Reports, **Dashboard** (home-page tiles: Sales, Sales Return, Purchase, Purchase Return month-vs-month; Tender Wise Sales pie; Sales vs Margin line), Monthly Transaction Summary.
