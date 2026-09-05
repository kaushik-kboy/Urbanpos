# UrbanPets TruePOS – System Documentation

**Application:** GOFRUGAL RetailEasy OnCloud (TruePOS) + AccountsEasy OnCloud (TrueBooks)
**URL:** https://urbanpets.true-pos.com/TruePOS/ (build version 030926)
**Company:** URBANPETS SERVICES PRIVATE LIMITED — locations *URBANPETS SERVICES PRIVATE LIMITED* (HO, code 225) and *URBAN PETS / MOTERA* (code 32772)
**Documented on:** 04–05 Sep 2026, logged in as `admin1`, counter CO-225.

## Documents
| File | Module | What it covers |
|---|---|---|
| [00-menu-tree.md](00-menu-tree.md) | Menu inventory | Every menu and sub-menu item of TruePOS (≈290 screens/reports) |
| [01-master.md](01-master.md) | Master | Item, Category, Brand, Customer (+Pet details), Supplier, Tax, Branch, Register, Promotion, Tender types, hidden masters |
| [02-sales.md](02-sales.md) | Sales | Sales Bill / Quotation / Order / Delivery Note / Return / Transfer Out – every header field, grid column, function key |
| [03-purchase.md](03-purchase.md) | Purchase | PO, Receipt Note, Purchase Invoice, Returns, PO Cancel, Transfer In, Indent, Auto Indent, cut-off |
| [04-inventory.md](04-inventory.md) | Inventory | Opening/Damage/Stock-update entries, Barcode printing, Price fixing & levels, Repack, Kits, Shelf talker |
| [05-reports.md](05-reports.md) | Reports | SmartReport viewer (filters, toolbar) + columns of the key reports |
| [06-tools.md](06-tools.md) | Tools | Roles & users, **Business Configuration (all ~250 switches with current values)**, function keys, ledger map, mail, migration, subscription, utilities |
| [07-finance-accounts.md](07-finance-accounts.md) | Finance & Accounts | AccountsEasy: ledgers, vouchers, bill-wise receipts/payments, banking, financial reports |
| [08-module-relationships.md](08-module-relationships.md) | Cross-reference | For every master, transaction and setting: where it is used, what it changes, and which reports/ledgers it feeds; end-to-end chains |

## How the system fits together

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

## UI conventions (apply everywhere)
* **Top bar**: Master · Sales · Purchase · Inventory · Reports · Tools · Finance And Accounts; *Search Menu* box jumps to any screen; icons = theme, notifications, print, profile/logout.
* **Second bar**: sub-menu of the active module; overflow under *More ▾*.
* **Angular masters** (`/masters/`, `/angular-modules/`): list grid → `Add …` → tabbed form → Save/Cancel; row edit icon; paging 10–50/page; `View Active/Inactive` chip.
* **GWT transaction screens** (`com.gofrugal.raymedi.webpos.*`): header on top, item grid in the middle, totals on the right, Remarks/Message and function-key bar at the bottom. Standard keys: **F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close** (re-mappable in Tools → Function Key Mapping). Lookups open with ENTER/TAB. Leaving a screen with typed data asks for confirmation.
* **Location selector** at the top-right of transaction screens chooses which branch the document belongs to.
* **Report viewer**: funnel icon → Date / Standard / Advanced filters → Apply; export, mail, schedule, pivot, chart from the left toolbar.

## Key business facts discovered
* Two GST-registered locations; inter-branch stock moves through Transfer Out (HO) → Transfer In / Transfer In Touch (branch). A transfer (TO 3181) was pending receipt on 04-09-2026.
* Items are classified by three category heads: **Brands, DEPARTMENT, CATEGORY**; batch/expiry tracking is *Mandatory* by default (pet food).
* Customer master is extended with a **Pet Details** tab (Pet Type, Breed, Colour masters) and Sales Bill has **F1 Pet Details**.
* Tenders enabled: Cash, Credit Card, Wallet, RRN (return credit); Due bill, Cheque, Coupon, Finance are switched off.
* Running document numbers (FY start): Sales 57,844 · Purchase 2,388 · Sales Return 1,297 · Transfer Out 3,181 · Transfer In 3,180.
* Sales Order approval, Session (shift) management, Promotions, E-way bill and Transfer-Out approval are currently **disabled** in Business Configuration.
* Report Scheduler, Barcode Config, Category-wise sequences and GoFrugal Alerts/GST e-filing/GoSure integrations are not yet set up.
