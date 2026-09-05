# Module 6 – TOOLS (Configuration, security, integrations, utilities)

## 6.1 Configuration

### 6.1.1 Security Configurations → Role Master (`angular-modules`)
Grid: S.No, Role Name, Description, Owner Name, Owner's Role; Edit. **Create Role** form: *Role Details* (Role Name*, Description) and *Security Configuration* – a Menu tree (search box) with per-menu **Operations** checkboxes (view / add / edit / delete / print) that decide what each role can open. Save / Cancel.

### 6.1.2 Security Configurations → Employee Master
Grid: S.No, Emp Name, Emp Code, User Name, Role, Approval Manager, Status, System Access; Edit; ~2 pages of users. **Create Employee** sections:

| Section | Fields | Purpose |
|---|---|---|
| Employee Details | Full Name*, Job Title, Email*, Mobile*, Default Branch*, Approval Manager*, Reporting Manager*, Employee Reference ID, Registration Number, **Allowed Item Discount %**, **Sales Commission %** | Discount cap enforced at POS; commission used by *Enable Sales Man Commission* |
| Login Details | Username (min 3 char)*, Role*, Password*, Confirm Password* | POS login; role = Role Master |
| General Information | Address Line 1/2, Landmark, City, State, Postal Code | |
| Contact Information | Office Phone, Home Phone, Date of Joining, Short name | Short name printed on bill as salesman |

### 6.1.3 Userwise Configuration
Grid: S.No, Configuration Code, Configuration Name, No.Of.Users, View (100 rows, 10 pages). Each row is a Business-Configuration switch that can be overridden **per user** (e.g. 100001 Show Ship To Branch Name of Customer, 100014 Tax, 100015 Discount, 100037 Show tender details in Sales Bill Edit Mode, 100038 Enable Sales Man Commission, 100040 Show stock of other locations, 100046 Show Landing and NetCost in LOV, 100048 Auto load coupon amount based on balance, 100050 Dont allow to edit Qty load from weight, 100052 Show Claim id …). *View* lists which users have the override.

### 6.1.4 Business Configuration (`angular-modules`) – the master switchboard
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

### 6.1.5 Function Key Mapping (GWT)
Screen Name (dropdown of all ~137 screens) → grid Sno, Function Name (New, Edit, Clear, Save, Exit, View…), **Function Key** (F1–F12), **Special Key** (Normal / Shift / Ctrl / Ctrl+Shift / Alt / Alt+Shift / Alt+Ctrl / Alt+Ctrl+Shift). Defaults: New F3, Edit F4, Clear F9, Save F6, Exit F10, View F7. **F4 Update, F10 Exit**.

### 6.1.6 Ledger Map (GWT *Accounts Posting Configuration*)
Transaction dropdown: Cash In, Cash Out, ConsumableIssue, Damage Stock Entry, Delivery Note, Distributor Payout, Gin Approval, Opening Stock, Purchase Invoice, Purchase Return, Receipt Note, Receipts, Sales Bill, Sales Order, Sales Return, service Invoice, Stock Transfer In, Stock Update, Transfer Out, Stock Classification Out/In. Grid: SNo, Description, Under Group, **LedgerName** (e.g. Cash Account → Cash in Hand; Cash In Amount → Indirect Incomes → "Cash In – InCome"). Defines which accounting ledger each POS transaction posts to. Save / Close.

### 6.1.7 Asset LedgerMap (GWT Mapping)
Grid S.No, Item Code, Description, **Asset Ledger** – maps capital-asset items to asset ledgers. F6 Save, F9 Clear, F10 Close.

### 6.1.8 Mail Server Configuration
Server Name, Port No, User Name, Password, From Email Id, TLS Status, SSL Status, Email Status (Active/InActive); **Send Test Mail**, Save, Reset, Reset to Default. Currently set to GoFrugal's default ZeptoMail relay (port 465, TLS+SSL, Active). Used by auto-mail of PO/Sales and Report Scheduler.

### 6.1.9 Category Wise Sequence
Location + Tran Type (Sales …) → per-category bill-number sequences ("No configurations found … Click here to add").

## 6.2 Addon Provisioning (`addonProvisioning.do`)
Grid User Name, User Role – grants add-on app (GoBill, SellSmart, EarnSmart, GoSecure…) access to users. "No Users have been created yet."

## 6.3 Integrations
* **GoFrugal Alert** – SMS/WhatsApp alert service; page says the Alerts account is *not linked* (register / link links).
* **GST Efiling** – GSTR filing integration (same account-link landing page).
* **GOFRUGAL Gosure** – GoSure stock-audit app (same landing page).

## 6.4 Master Migration (GWT WPMigration)
Tabs **Sheet Generation / Upload Sheet / Upload Status**. Select a Master: Customer Master, Supplier Master, Item Master, Item Outlet Specific, Manufacturer Master, Opening Stocks [By Item Name], Opening Stocks [By Serial No], Price List, Stock Update, Item Category Mapping, Price Level Master, Price Level vs Item Mapping, Item Update, Locationwise Item Update, Customer Update, Supplier Update, Shipping Master, Bulk Repack Mapping, **Pet Details for Customers**, Item Batchwise update, Stock Update Using Alias. Buttons Load Config, Save Config, **Download CSV** (template), Clear Config, Close. Bulk import/export via CSV.

## 6.5 Document Upload Transactionwise
Filters Type, Location, Date range, search by Tran No. Grid S.No, Date, Tran No, Location, **Attach / View / Remove** – attach scanned supplier invoices etc. to transactions.

## 6.6 Add On Service(s) (`truelayout.do` – Organization Settings)
Company Details, **Print Upload** (upload custom print layouts), Manage Subscription link.

## 6.7 Manage Subscription (`managesubscription.do`)
Buy, Outstanding, Order History, Invoice History, Receipt History for the GoFrugal licence (Customer Id 1608135).

## 6.8 More
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
