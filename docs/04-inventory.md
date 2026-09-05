# Module 4 – INVENTORY (Stock adjustments, pricing, packing)

Inventory screens never involve a customer or supplier; they adjust stock quantity, price or packaging inside a location. Every screen starts with a **Location** selector (URBANPETS SERVICES PRIVATE LIMITED = HO, URBAN PETS / MOTERA = branch).

## 4.1 Opening Stock Entry (GWT Inward screen)
Grid: S.No, Code, Description, Exp Dt, Qty, Cost Price, Sell Price, MRP, Disc %, Disc Amount, GST%, GST TaxAmt, **Supplier**, Scheme Disc%, Scheme Amt, Scheme Others, Net Amount. Remarks / Message. Keys **F5 New**, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: load initial stock when going live (or a new branch) with cost, price and batch expiry per item without a supplier invoice. Report: *Opening Stock Detail*.

## 4.2 Damage Stock Entry (`angular-modules`)
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

## 4.3 Stock Update Entry (GWT Inventory screen, title *Stock Update*)
Grid: S.No, Code, Description, Exp Dt, Qty (physical count), **Current Stock** (system qty), Sell Price, MRP. Keys F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close.
Use: physical stock-take; entering counted Qty creates a +/- adjustment. If approval is enabled the entry waits in *Stock Update Approval*.

## 4.4 Stock Update Approval (`angular-modules`)
Select Location → grid S.no, Code, Item Name, Exp Dt, **Physical Qty**, **Current stock**, Sell Price, MRP, **Update Qty** (difference), Status, **Approve** (checkbox), Remarks. Search Item/Code. Save / Cancel. Manager approves or rejects each stock-take line before stock changes.

## 4.5 Barcode Printing (`angular-modules`)
Branch filter, **Load Transaction** (pull items from a purchase invoice / GRN to print labels for the received qty), or search item and **Add**. Grid: S.no, Item Code, Item Name, Exp Date, **Print Qty**, Cost Price, Sell Price, MRP, Action. **Print** / Cancel. Label layout comes from *Tools → Barcode Config*.

## 4.6 Price Fixing

### 4.6.1 Price Fixing (markup/markdown) – "Mark Up/Down"
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

### 4.6.2 Price Level (Price Level Master)
Grid Name, Type, By, On. **Create Price Level Master**: Price Level Name; Type (MarkUp / MarkDown / Price); Based On (Cost / Landing Cost / Selling); By (Percentage / Amount). A price level is a named pricing rule (e.g. "Wholesale = Cost + 10 %") that can be assigned to customers (Customer → Sales Formula) or branches.

### 4.6.3 Price Level Vs Items (GWT *pricelevelmapping*)
Header: Pricelevel Name (lookup), Type, Based on, By (read from the master). Grid: S.No, Item Code, Description, **Round Off** (None / Lower / Near / Upper), **Round To** (0 / 0.5 / 1), **Mark Value** (item-specific % or amount overriding the level default). F6 Save, F9 Clear, F10 Close.

## 4.7 Change Selling (`angular-modules`)
Option / **Category Filter**; search item → Add. Grid: S.no, Item Code, Item Name, Exp Date, **New Sell Price**, **New MRP**, Cost Price, Sell Price, MRP, Action. Save / Cancel. Quick manual price change per item/batch; history visible in *Price Drop* and *Price Level Advance* reports.

## 4.8 More → Repack (`angular-modules`)
List: Location, **Add Repack**, grid S.No, Repack No, Bulk Item Name, Date, Packed [in %], Qty Taken [in KG].

**Repack Entry form** – Bulk Item Selection: Bulk Item, Current Stock [KG], Conversion, **Qty Taken**, Packed, Avail for Pack, **Conversion Loss [Kg]**, Batch No, Mfg Dt, Expiry Dt, Cost Price, Selling. Then packed items grid: S.No, Item Code, Item Name, **Repack Quantity**, Sell Price, MRP, **Conversion in (g)**, Expiry Date, Action. Add / Save / Cancel / Clear.
Use: break a bulk bag (e.g. 20 kg dog food) into loose 1 kg packs; bulk stock decreases, pack items increase, loss recorded.

## 4.9 More → Change Serial No (`angular-modules`)
Item Name, Part No (read-only); grid S.No, MFR Serial No → New MFR Serial No, Serial No 2 → New Serial No 2, Serial No 3 → New Serial No 3, Action. Add / Save / Cancel. For Serialized product type only.

## 4.10 More → Price Drop (`angular-modules`)
Header: Tran Type (Purchase), Location, Mode (Auto), Operation (Decrease), **Invoice No**. Grid: Item, Quantity, Rate, Amount, **Assessable Value (Amount − ?)**, Action; Add, Save, Reset.
Use: record a post-purchase price reduction (supplier price-drop credit) against an invoice so the assessable/cost value is corrected.

## 4.11 More → Kit Preparation (`/TruePOS/kitPreparation/`)
Location (dropdown); kit item row: Item Name (search), Expiry Date, Quantity, Selling Price, Cost Price, MRP, Action → **Add**; **Create Kit** / Reset. Consumes component stock per *Kit Mapping* and creates kit stock.

## 4.12 More → Kit Unpack (title *Kit Item – Unpack*)
Location, search item; grid Item Code, Item Name, Location, **Prepared, Sold, Available**, Sale Price, Action (unpack). Reverses a kit back into components.

## 4.13 More → Shelf Talker (`angular-modules`)
Location, **Add Shelf Talker**; grid S.No, Item Code, Item Name, **Price Format**, **Calculated Value**, **Print Profile**, Print Qty. Prints shelf-edge price labels (per-kg price etc.).

---

## 4.14 Where inventory data shows up
* Stock ledger reports (Itemwise Stock Statement, Transactionwise Stock Register, Closing Stock).
* Wastage/Damage Stock and Stock Update Detail reports (Stock Analysis group).
* Repack Summary / Detail, Kit Preparation / Unpack reports (Stock Replenishment group).
