# Module 2 – SALES (Outward transactions)

All Sales screens except *Sales Order Approval* and *Transfer Out Approval* are the legacy GWT **Outward** screen (`/TruePOS/com.gofrugal.raymedi.webpos.outward.Outward/Outward.html`) rendered in different modes. They share one layout, so the common layout is documented once, then each screen lists only what differs.

## 2.0 Common Outward screen layout

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

### Header fields

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

### Item grid columns

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

### Totals panel (right)
Bill No, Bill Date, Item Disc Amount (sum of line discounts), Disc% / Disc Amount (bill-level discount), Round off Amount, Advance (SO/DN), Total GST, Total Extra Cess, GST Calamity Cess, **Total**.

### Function keys

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

## 2.1 Sales Quotation
Title *Quotation*. Fields: Customer, Address, Invoice Type, Delivery Type, **Ship From**, Sales Type, Payment Type. Totals: Quote Date, Quote No, Item Disc Amount, Disc%, Disc Amount, Round off, Total GST, Extra Cess, Calamity Cess. No Exp Dt column (no stock is reserved). Keys: F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: price offer to a customer; can be converted to a Sales Order.

## 2.2 Sales Order
Title *Sales Order*. Adds **Account** (Advance account / Order Location / Delivery Location / To customer account) and **Advance** in totals; header has SO No, SO Date, Quote No (link back to quotation). Use: confirmed customer order, advance collection, stock reservation (see report *Sales Order Stock Status*).

## 2.3 Sales Order Approval (`angular-modules`)
Grid: S.No, Sales Order No, Customer, Amount, Sales Man, Status, Acknowledged By, Acknowledged Date; branch filter; paging. Approver acknowledges pending sales orders before they can be delivered/billed (used when Business Configuration enables SO approval).

## 2.4 Delivery Note
Title *Delivery Note*. Header adds **SDN No / SDN Date**; totals include **Advance Amount**. Grid has Exp Dt. Stock is issued on delivery note; the bill is raised later (Delivery Bill reports). Use: home-delivery / dispatch before invoicing.

## 2.5 Sales Bill (POS invoice)
Title *Sales Bill*. Full header (Customer, Address, Balance, Invoice Type, Coupon Balance, Delivery Type, Delivery Time, Sales Type, Payment Type). Totals: Bill No, Bill Date, Item Disc Amount, Disc%, Disc Amount, Round off Amount, Total GST, Total Extra Cess, GST Calamity Cess, Total. Extra keys **F1 Pet Details, F2 Hold, F5 Recall, F11 Buy XCare**.
Bill number prefix comes from the Register (counter CO-225). Saving (F6) opens tender entry and prints.

## 2.6 Sales Return
Title *Sales Return*. Header: Customer, Address, Coupon Balance, **Bill No** (original bill to return against), **Return Mode** (RRN / Credit Note / Cash / Wallet / Card), Sales Type (Local / Interstate). Totals: Return No, Return Date, Item Disc Amount, Disc%, Disc Amount, Round off, Total GST, Extra Cess, Calamity Cess.
Return Mode meaning: **RRN** = Return Receipt Note (value kept as credit to adjust in next bill), **Credit Note**, **Cash** refund, **Wallet** refund to customer wallet, **Card** refund.

## 2.7 Delivery Note Return
Title *Delivery Note Return*. Header: Customer, Address, Coupon Balance, **DN No** (delivery note being returned), Delivery Type (read-only), Delivery Time, Sales Type, Payment Type. Totals: DNR No, DNR Date … Extra keys F11 New Customer, F12 Select Cust. Use: goods delivered on a delivery note but returned before billing.

## 2.8 More → Transfer Out (Stock Transfer Out)
Title *Stock Transfer Out*. Header: **To Branch Name** (ENTER/TAB to select branch), Address. Totals: TO NO, TO Date, Total GST, Total Extra Cess, Total. Grid: S.No, Code, Description, Exp Dt, Qty, Sell Price, MRP, Amount. Keys F3 New, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: send stock from one branch (e.g. HO) to another (MOTERA). The receiving branch books it with *Purchase → Transfer In*.

## 2.9 More → Transfer Out Approval and Auto TI
Currently shows: *"Please Select Approval and Auto Transfer In from Configuration – Enable Transfer Out Approval in Menu Tools → Business Configuration."* i.e. the feature is switched off. When enabled, transfer-outs wait for approval here and a Transfer In is auto-created at the destination branch.

---

## 2.10 Typical sales flows

1. **Counter sale**: Sales Bill → scan items → F6 Save → tender → print.
2. **Order then deliver**: Sales Quotation → Sales Order (advance) → (Sales Order Approval) → Delivery Note → Sales Bill.
3. **Return**: Sales Return against Bill No, choose Return Mode.
4. **Inter-branch**: Transfer Out (HO) → Transfer In (branch) – see Purchase module.
