# Module 3 – PURCHASE (Inward transactions)

Purchase Order, Receipt Note, Purchase Invoice and Transfer In are the legacy GWT **Inward** screen (`/TruePOS/com.gofrugal.raymedi.webpos.inward.Inward/Inward.html`). Purchase Returns uses the Outward screen. PO Cancel, Indent, Auto Indent, Transfer In Touch and Indent CutOff are Angular screens.

## 3.0 Common Inward screen layout

| Area | Fields |
|---|---|
| Branch selector (top right) | Location receiving the goods |
| Supplier | text + lookup (ENTER/TAB opens supplier search). Address and **Balance** (amount owed) fill automatically |
| Purchase Type | Local / Interstate – CGST+SGST vs IGST on purchase |
| C-Form | Against C-Form / No Forms – legacy CST form declaration (kept for compatibility) |
| Remarks / Message | internal note / printed note |
| Totals panel | document numbers and dates, Item Disc Amount, Disc%, Disc Amount, **Freight**, Round off Amount, **Scheme ItemDiscAmt**, **OtherDiscAmt**, Total GST, Total Extra Cess, (TCS Amt on invoice), Total |
| Function keys | F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close |

### Item grid columns (Inward)

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

## 3.1 Purchase Order
Header: Supplier, Address, Balance, Purchase Type, C-Form. Totals: **PO No, PO Date**, Item Disc Amount, Disc%, Disc Amount, Freight, Round off, Scheme ItemDiscAmt, OtherDiscAmt, Total GST, Total Extra Cess. Grid: S.No, Code, Description, Qty, Free, Cost Price, Sell Price, MRP, Disc %, Disc Amount, GST%, GST TaxAmt, Margin%, Profit%, Net Amount (no Exp Dt – nothing received yet).
Use: order to supplier. Can be mailed (Supplier → Mail Type). Pending POs feed *Purchase Order Summary / Purchase Transit* reports and *PO Replenishment*.

## 3.2 Receipt Note (GRN without invoice)
Header adds **PO No / PO Date** (pull lines from a PO) and **RN No / RN Date**, plus **Inv No, Inv Date, Inv Amount** if the supplier invoice is already known. Grid includes Exp Dt. Stock is received into inventory; the financial invoice is booked later in Purchase Invoice. Use when goods arrive before the bill or need checking first.

## 3.3 Purchase Invoice
Header: Supplier, Address, Balance, PO No/Date, Purchase Type, C-Form. Totals: **GRN No / GRN Date**, **Inv No / Inv Date / Inv Amount** (supplier invoice – Inv Amount is used to cross-check the computed total), Item Disc Amount, Disc%, Disc Amount, Freight, Round off, Scheme ItemDiscAmt, OtherDiscAmt, Total GST, Total Extra Cess, **TCS Amt**, Total. Grid same as Receipt Note.
Use: the main stock-in + supplier-liability document. Updates item Cost Price, Landing Cost, Sell Price/MRP, batch expiry, and supplier ledger.

## 3.4 Purchase Returns (Outward screen, title *Purchase Return*)
Header: Supplier, Address, **Return Mode** (Debit Note / Cash), **Reference No, Ref.Date** (supplier invoice being returned against), **Return Reason** (dropdown from Reason master – currently empty). Totals: PRN No, PRN Date, Item Disc Amount, Disc%, Disc Amount, Freight, Round off, Scheme ItemDiscAmt, OtherDiscAmt, Total GST, Total Extra Cess. Grid: S.No, Code, Description, Exp Dt, Qty, Free, Cost Price, Sell Price, MRP, Disc %, Disc Amount, GST%, GST TaxAmt, Net Amount.
Use: send damaged / expired / excess goods back to supplier; reduces stock and supplier balance.

## 3.5 PO Cancel (`angular-modules`)
Filter **Location**. Grid: S.No, Po No, Prefix, Amount (₹), Supplier, Status, Date. Select a pending PO and cancel it (fully or remaining qty). Feeds report *Purchase Order Cancel*.

## 3.6 Transfer In (Stock Transfer In – Inward screen)
Header: **From Branch** (ENTER/TAB to select), Address; totals **TO No, TO Date** (the originating Transfer Out), **TI No**, Total GST, Total Extra Cess, Total. Grid: S.No, Code, Description, Qty, Sell Price, MRP, **Received** (qty actually received – shortages create *Stock Transfer Discrepancy*), Amount. Keys F6 Save, F7 View, F8 Print, F9 Clear, F10 Close (no F3 – lines always come from a TO).

## 3.7 Indent (`angular-modules`, title *Indents*)
Filter Location. Grid: S.No, Indent No, From Branch, To Branch, Created At, Status. **Add Indent** opens *Indent Entry*:

| Field | Purpose |
|---|---|
| To Location | branch / distribution centre being asked to supply |
| Item search → Add | adds line |
| Grid: S.No, Item Code, Item Name, Indent Quantity (editable), Stock (current stock at requesting branch, read-only), MBQ (min stock level, read-only), Action | |
| Save Indent / Cancel | |

Use: a branch requests stock from HO/DC. HO fulfils via Transfer Out.

## 3.8 More → Auto Indent (title *Indent Request*)
Filters: Branch (All Locations), **Advance Filter** (Brand, Item Type, Category, List Value; Apply / Reset / Cancel), **Search Indent**. Generates indent lines automatically for items below MBQ (Min/Max set in Item Property Setting). Related report: *Indent Based On Replenishment*.

## 3.9 More → Indent Cancellation (GWT Master screen)
Fields: Indent No, Indent Date [DD-MM-YYYY], Branch Name; grid S.No, Item Code, Description, Qty. Keys F6 Cancel, F9 Clear, F10 Close.

## 3.10 More → Transfer In Touch (`angular-modules`, touch-friendly Transfer In)
Filters: Branch (default current), Status (Pending). **Load** lists pending transfer-outs addressed to this branch: S.No, To No, SAP Invoice No, Source Location, To Date, Amount, Action. Example pending row: TO 3181 from URBAN PETS / MOTERA dated 04-09-2026, amount 2499. Action opens a receive screen to confirm quantities with a tablet.

## 3.11 More → Indent CutOff Time Configuration
Grid per location: S.No, Location Code (225 = HO, 32772 = MOTERA), Location Name, **Cut Off Time** (HH / MM 00-15-30-45 / AM-PM). Indents raised after the cut-off are treated as next-day. Save / Cancel.

---

## 3.12 Typical purchase flows

1. **Direct purchase**: Purchase Invoice (F3 New → supplier → items → F6 Save).
2. **PO based**: Purchase Order → Receipt Note (optional) → Purchase Invoice (pull PO/GRN) → PO Cancel for any unfulfilled remainder.
3. **Return**: Purchase Returns with Reference No of the invoice.
4. **Branch replenishment**: Branch raises Indent (or Auto Indent) → HO Transfer Out → branch Transfer In / Transfer In Touch.
