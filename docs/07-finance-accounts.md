# Module 7 – FINANCE AND ACCOUNTS (AccountsEasy OnCloud / TrueBooks)

Clicking **Finance And Accounts** in TruePOS opens a *separate* application in a new browser tab:
`https://urbanpets.true-pos.com/TrueBooks/com.gofrugal.truebooks.transmdi.TransMDI/TransMDI.html` – **GOFRUGAL AccountsEasy OnCloud**. It shares the company, locations and login (Admin1) with TruePOS. POS transactions post into it automatically according to *Tools → Ledger Map* in TruePOS.

Top menu: **Masters | Voucher Entry | Receivables / Payables | Banking | Reports | Tools** + Menu Search + TRUEPOS link (back to POS) + print + settings gear.
Screens are GWT (function-key driven). Every voucher screen has: Location selector, **Financial Year selector** (2026_2027 / 2025_2026 / 2024_2025 / 2023_2024) and keys F3 New, F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close, **F11 Narration**, **F12 Delete**.

## 7.1 Masters
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

## 7.2 Voucher Entry (`WebTrac/TracScreen.html`)
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

## 7.3 Receivables / Payables
| Screen | Fields | Purpose |
|---|---|---|
| **Billwise Receipt** | Group (All Groups/HQ), Location, FY, **Adjustment Mode** (Ledger/Group), Code/Account (customer), Date, **Adjustment** (Manual/Auto), Auto Amount, Amount, Discount (No/Yes), Others (No/Yes), RefNo, VoucherSlno, Cur.Amt, BaseCur.Amt; pending-bills grid **LocationName, RefNo, DocNo, BillDate, BillAmount, Receivable, Adjusted, Balance, Days, [Y/N], Amount, Attachment**; discount line (Account, Disc%, DiscAmt); receipt mode block (Mode, Name, Payment Mode, Chq.No, UTR, Chq.Date, Branch, Cheque Name, Balance); summary On Account, Pending, Adjusted, Balance | collect money from a credit customer and knock it off against specific bills |
| **Billwise Payment** | identical with **Payable** column and bank details (Inst.Date, Account Number, Bank Name, IFSC, Account Type, Holder) | pay a supplier against specific invoices |
| Centralized Billwise Receipts / Payments | same, across locations from HO |
| A/R Reports | Accounts Receivables Summary, Aged Receivable, Customer Due Bills Statement, Customer Summary/Detailed, Customer Pending Days, Customer Outstanding (+Detailed), Customer BillWise Settlement, Month Wise Ageing Receivable, Area wise / Rep wise Outstanding Detailed |
| A/P Reports | Accounts Payables Summary, Aged Payable, Accounts Payable Due Bills, Supplier Detailed, Supplier Pending Days, Supplier Detailed-Billwise Status, Supplier Due Bills, Supplier BillWise Settlement, Month Wise Ageing Payable |
| more → Billwise Import | upload bill-wise adjustments |

## 7.4 Banking
| Screen | Fields / purpose |
|---|---|
| Customer Bank Master | customer bank details for cheques |
| **Cheque Deposit** | Code, Depositing Bank, Deposit Date, From/To Date; grid Date, Particulars, Amount, ChqNo, ChqDate, BankName, Branch, Flag → mark cheques-in-hand as deposited |
| **Bank Reconciliation** | Group, Location, FY, Bank, Use ChequeDate, Cheque Status (Both/UnCleared/Cleared), TranType (Both/Received/Paid), From/To Date; grid Location, Date, Particulars, RefNo, ChqNo, ChqDate, **DepositOn, ClearOn**, Debit, Credit, BankName, Branch, Narration; summary Opening Balance, Balance as per our Book, (+) Cheque Issued but not realised, (−) Cheque Deposited but not reflected, **Balance as per Bank** |
| Cheque Bounce, Cheque Represent, Cheque Print | |
| more → Cheque Details, Bank Payment Advice, Cheque Collection, Bank Reconciliation Statement, Bank Reconcilation (New) | |

## 7.5 Reports (accounting)
* General Ledger, Grouped Ledgers
* Financial Statements → Parent & SubGroup Wise: **Trading – Profit & Loss**, **Balance Sheet**, **Trial Balance**, Trial Balance Location Wise
* Account Books: Day Book, Cash Book, Bank Book, Purchase/Sales Book with Opening Balance, Confirmation of Accounts, MFR Customer wise Ledger, Day Transaction Summary
* Registers: Sales, Purchase, Sales Return, Purchase Return, Debit Note, Credit Note, Payment, Receipt, Journal, Contra, Payment/Receipt (Bill wise)
* Register Reports With Column Wise Breakup (Sales, Purchase, SR, PR, Sales & SR summary, Service)
* more → Registers With Tax Breakup (Accounts Only); Others (Opening Balance Report, Billwise Payments/GRN Processed, Bill wise Payment/Receipt, Voucher Import Audit, Memorandum, Expenses, Manual Sales/Purchase Journal, Chq-in-hand journals); Receipt/Payment Reports (Salesman wise collection / summary); Audit Summary (Transactions, Masters, Opening & Closing Stock, Outlet Group Mapping); Master Reports (Group Master, Ledger Master, Customer Bank Master, Narration, RTGS/NEFT Beneficiary); TDS Reports (Calculation & Deduction Summary/Detailed, Deduction Details, for Advance); General Ledger Report [NEW]; Voucher Approval Report; Cost Center Breakup; BillWise Import Audit

## 7.6 Tools
* Configuration → **Admin Configuration** (new Angular UI `ngx-truebooks`) – accounting preferences
* New Group Location – create a location group (HQ) for consolidated books
* **Year End Process** – close FY, carry forward balances
* **Month End Process** – lock a month

## 7.7 How POS and Accounts connect
1. TruePOS *Ledger Map* decides ledgers per transaction type (Sales Bill → Sales ledger + tender ledgers; Purchase Invoice → Purchase + supplier; Cash In/Out → Cash in Hand…).
2. Every Sales Bill / Purchase Invoice / Return / Damage entry creates the corresponding voucher automatically.
3. Credit sales appear as receivables → settle with **Billwise Receipt**. Supplier invoices → **Billwise Payment**.
4. Cheques → Cheque Deposit → Bank Reconciliation.
5. Period end → Month End / Year End Process; Year Begin Sequence Change in TruePOS resets document numbers.
