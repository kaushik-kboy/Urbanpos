# Implementation Plan — 4 Advanced Enterprise Enhancements

This plan outlines the architecture, database schema, frontend components, and security guards for implementing 4 core retail features:
1. **POS Quick Lock Screen** (`Ctrl + L` / 3-minute idle lock with 4-digit Cashier PIN)
2. **Dynamic UPI QR Code Generator** (On-screen F8 modal + Thermal Receipt print)
3. **Direct Barcode Label / Sticker Printing Engine** (50x25mm, 38x25mm, A4 sheets from Item Master & Purchase Invoices)
4. **Automated Daily Database Backup & Admin Management Console**

---

## 1. Feature Breakdown & Architecture

### Feature 1: POS Quick Lock Screen (Counter Security)
- **Objective**: Prevent unauthorized counter tampering when cashier leaves the till unattended.
- **Components**:
  - **Cashier PIN**: Add nullable `pos_pin` (hashed 4-digit string) column to `users` table with validation and profile setup.
  - **Inactivity Timer**: JavaScript activity listener (`mousemove`, `keydown`, `touchstart`) resets 3-minute countdown.
  - **Manual Lock**: Hotkey `Ctrl + L` immediately blurs the viewport and brings up a full-screen lock overlay.
  - **PIN Pad Overlay**: On-screen numpad + physical keyboard digits support, error shaking animation on incorrect PIN.
  - **State Retention**: Carts, line items, selected customer, and tender inputs remain untouched in memory.

### Feature 2: Dynamic UPI QR Code on F8 Tender Screen & Receipts
- **Objective**: Zero manual entry UPI payments; customer scans exact bill amount via PhonePe, GPay, Paytm.
- **Components**:
  - **Store Configuration**: Add `upi_id` (VPA, e.g., `store@okhdfcbank`) and `upi_payee_name` in store/branch settings.
  - **UPI String Standard**: `upi://pay?pa={UPI_ID}&pn={STORE_NAME}&am={TOTAL}&tr={BILL_NO}&tn=Bill_{BILL_NO}&cu=INR`.
  - **F8 Tender Modal Display**: Generate QR code using lightweight SVG/Canvas QR library (zero external API dependency, works offline).
  - **Thermal Receipt Layout**: ESC/POS QR code command or base64 raster QR printed at the bottom of the 80mm thermal receipt.

### Feature 3: Direct Barcode Sticker Printing (50x25mm / 38x25mm / A4)
- **Objective**: High-speed sticker printing for newly received or existing stock.
- **Components**:
  - **Print Sources**:
    - **From Purchase Invoice**: Single click to print barcode stickers matching received quantities (`qty` = sticker count).
    - **From Item Master**: Custom quantity selector for ad-hoc label generation.
  - **Label Formats**:
    - **Standard 1-Up Roll**: 50mm x 25mm (Barcode thermal printers: TSC, TVS, Zebra, Xprinter).
    - **Compact 2-Up Roll**: 38mm x 25mm (2 labels per row).
    - **A4 Sheet Matrix**: 24 / 40 labels per A4 page for laser/inkjet printers.
  - **Label Content**: Store Name, Item Name, Barcode (Code128 SVG), MRP, Sell Price, Packed/Expiry Date.

### Feature 4: Automated Daily Database Backup & Admin Console
- **Objective**: Continuous protection against data loss with single-click manual download.
- **Components**:
  - **Storage**: `storage/app/backups/` directory with automated cleanup (retaining last 14 daily backups).
  - **Artisan Command**: `php artisan db:backup` generating gzip-compressed `.sql.gz` dump.
  - **Laravel Scheduler**: Scheduled in `routes/console.php` to execute nightly at `01:00 AM`.
  - **Admin UI** (`/tools/backups`):
    - Table of existing backups with File Name, Creation Time, and File Size.
    - **"Backup Now"** button (triggers instantaneous background backup).
    - **"Download"** button (secure streamed file download for Super Admin / Admin).

---

## 2. Database Changes (Migrations)

1. **`pos_pin` on `users`**:
   - `database/migrations/2026_10_01_000001_add_pos_pin_to_users_table.php`
   - Field: `string('pos_pin', 60)->nullable()` (bcrypt hashed 4-digit PIN).
2. **`upi_id` & `upi_name` on `branches`**:
   - `database/migrations/2026_10_01_000002_add_upi_details_to_branches_table.php`
   - Fields: `string('upi_id')->nullable()`, `string('upi_name')->nullable()`.

---

## 3. Proposed Files & Modules

### Feature 1: POS Quick Lock
- [MODIFY] `app/Models/User.php`: add `pos_pin` to fillable.
- [NEW] `app/Http/Controllers/Pos/PosLockController.php`: API endpoint `/pos/verify-pin` to validate PIN.
- [MODIFY] `resources/views/pos/terminal.blade.php`: add `#pos-lock-overlay` modal with PIN pad.
- [MODIFY] `public/js/pos-terminal.js`: add inactivity timer (3 mins) and `Ctrl + L` trigger.
- [MODIFY] `resources/views/master/users/_form.blade.php`: allow setting/resetting cashier 4-digit POS PIN.

### Feature 2: Dynamic UPI QR Code
- [MODIFY] `resources/views/pos/terminal.blade.php`: add Dynamic UPI QR canvas in `#tenderModal`.
- [MODIFY] `resources/views/sales/sales-bills/receipt.blade.php`: render QR code on thermal bill layout.
- [MODIFY] `resources/views/master/branches/_form.blade.php`: add UPI VPA and Merchant Name fields.

### Feature 3: Barcode Label Printing
- [NEW] `app/Http/Controllers/Master/BarcodePrintController.php`: handles sticker layout generation.
- [NEW] `resources/views/master/barcodes/print-labels.blade.php`: specialized CSS print layout for 50x25mm, 38x25mm, and A4.
- [MODIFY] `resources/views/purchase/purchase-invoices/index.blade.php` & `show.blade.php`: add "Print Labels" action button.
- [MODIFY] `resources/views/master/items/index.blade.php`: add "Print Label" bulk action.

### Feature 4: Database Backup System
- [NEW] `app/Console/Commands/DatabaseBackupCommand.php`: Artisan command running mysqldump and gzip compression.
- [NEW] `app/Http/Controllers/Tools/BackupController.php`: CRUD for backups (`index`, `create`, `download`, `delete`).
- [NEW] `resources/views/tools/backups/index.blade.php`: Admin console view for backup management.
- [MODIFY] `routes/web.php` & `routes/console.php`: register web routes and nightly cron schedule.
- [MODIFY] `config/adminlte.php`: add "Database Backups" menu link under Tools.

---

## 4. Verification & Testing Plan

1. **Automated Feature Tests**:
   - `tests/Feature/PosLockSecurityTest.php`: tests PIN verification, invalid PIN rejection, lockout rate-limiting.
   - `tests/Feature/DatabaseBackupTest.php`: tests backup command execution and secure download permissions.
   - `tests/Feature/BarcodeLabelPrintTest.php`: verifies sticker quantity matching purchase invoice items.
2. **Blade Route & Pre-Push Guard**:
   - Run `php artisan test --filter=BladeRouteIntegrityTest` to confirm 100% valid routes.
   - Run `php artisan test --filter=CorePosRegressionTest`.
3. **Vitest Frontend Tests**:
   - Run `npm run test:frontend` to ensure hotkeys (`Ctrl+L`, `F2`, `F8`) do not collide with browser defaults.
