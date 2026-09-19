# Urban Pets UI 2.0 Design System — Redesign Roadmap & Implementation Checklist

> **Reference Specification**: *Urban Pets UI 2.0 Final POS / ERP Visual + UX Design System (26-Page PDF)*  
> **Git Branch**: `desgin`  
> **Centralized Common Stylesheet**: [`public/css/urbanpets-theme.css`](file:///c:/laragon/www/Urbanpos/public/css/urbanpets-theme.css)

---

## 🔒 Core Rules & Boundaries (User Directives)

1. **Zero Layout Shifts**: Never change the placement, order, or positioning of any section or component.
2. **Zero Content & Data Alteration**: Never add new content, delete existing content, or alter data queries, model bindings, or table columns.
3. **Pure Visual Redesign**: Only apply visual styling (color combinations, soft-card surfaces, border radiuses, typography, badges, and button states).
4. **Centralized Style Architecture**: All common styles must be maintained within `public/css/urbanpets-theme.css` rather than hardcoded inline in individual Blade templates.

---

## ✅ Completed Components & Shell

- [x] **Global Design System Tokens** (`public/css/urbanpets-theme.css`)
  - Primary color palette: `#0B1F52` (Deep Navy), `#1769E8` (Urban Blue), `#DCE6F5` (Borders), `#F4F7FD` (Canvas Background).
  - Semantic pastels: Success (`#E7F8F0` / `#168447`), Warning (`#FFF7EE` / `#D97706`), Danger (`#FEECEB` / `#D92D20`), Teal (`#E7F8F6` / `#078B87`), Purple (`#F3F0FF` / `#6C3BE8`).
  - Global typography: Google Font **Inter** (400, 500, 600, 700, 800) globally applied.
  - Global card surfaces: 12px radius, `#FFFFFF` clean surfaces, `#DCE6F5` borders.
- [x] **Sidebar** (PDF Page 5 & 23)
  - Clean white `#FFFFFF` surface with `#DCE6F5` right border.
  - Brand header: `URBAN PETS` in `#0B1F52` navy, 800 weight.
  - Active item indicator: Soft blue `#EDF4FF` pill with `#1769E8` blue text and icon.
  - AdminLTE collapse and hover: Smooth 0.3s expand on hover (250px) over content with soft shadow; 4.6rem compact icon-only collapsed state.
- [x] **Header / Topbar** (PDF Page 5)
  - 56px height, clean white `#FFFFFF` background, `#DCE6F5` bottom border.
  - Soft branch selector dropdown pill with `#0B1F52` text.
  - Latency / health monitor badge in soft mint (`#E7F8F0` / `#168447`).
- [x] **Main Footer** (PDF Page 5)
  - Clean white background, `#DCE6F5` top border, muted system metadata and UI 2.0 badge.
- [x] **POS Action Shortcut Footer Bar** (`x-pos-keyboard-bar`)
  - Elevated clean white `#FFFFFF` surface with `#DCE6F5` top border and upward shadow.
  - Responsive left sync with sidebar width (250px expanded / 4.6rem collapsed).
  - **No-Scroll Design**: Compact keycaps and spacing ensuring all shortcuts are visible without horizontal scrolling.
  - Semantic keyboard keycaps for `F2` through `F10`.
  - Emerald green hero highlight for `F6 Save & Tender`.
  - Quick jump links (`Alt+S`, `Alt+P`, `Alt+T`, `Alt+C`, `Alt+I`, `Config`) in soft white pills.

---

## 📋 Remaining Modules to Redesign

Below is the structured breakdown of every remaining module in the application, listing the specific visual elements to be redesigned without modifying data or placement.

---

### Phase 1: Dashboard Visual Refinement (`resources/views/home.blade.php`)
*PDF Reference: Page 6 (Executive Analytics & KPI Dashboard)*

- [x] **KPI Summary Metric Cards**:
  - Soft pastel card backgrounds matching PDF spec:
    - **Total Sales**: Soft blue (`#EDF4FF` fill, `#D4E4FC` border, `#1769E8` icon)
    - **MTD Sales**: Soft emerald (`#E7F8F0` fill, `#BDE4CF` border, `#168447` icon)
    - **Gross Margin**: Soft purple (`#F3F0FF` fill, `#E2DBFB` border, `#6C3BE8` icon)
    - **Stock Valuation**: Soft amber (`#FFF7EE` fill, `#FDE8D0` border, `#D97706` icon)
  - Metric values: Bold `#0B1F52` deep navy with tabular numerals.
  - Trend indicators: Soft green badge (`#E7F8F0` / `#168447`) for positive growth, soft red (`#FEECEB` / `#D92D20`) for negative.
- [x] **Chart.js Visual Palette**:
  - Chart.js datasets using UI 2.0 colors: Urban Blue (`#1769E8`), Emerald Green (`#168447`), Purple (`#6C3BE8`), Teal (`#078B87`).
  - Soft gridlines (`#EEF3FA`) and tooltips with `#0B1F52` background.
- [x] **Dashboard Data Tables (Active Shifts, Recent Bills, Open Quotations)**:
  - Table header: `#F1F5FA` background with `#536581` uppercase typography (12.5px, weight 600).
  - Row borders: Crisp `#EEF3FA` horizontal dividers with soft hover highlight.
  - Status badges: Soft green for "Open" and "Active", soft amber, soft blue links.
- [x] **Fast Action Command Center Buttons**:
  - Rounded 8px buttons with hover lift and coordinated icon accents.

---

### Phase 2: POS Terminal Screen (`resources/views/pos/terminal.blade.php`)
*PDF Reference: Pages 7, 8 & 9 (POS Screen, Cart Grid & Tender Dialog)*

- [x] **Barcode & Search Input Bar**:
  - Clean white `#FFFFFF` surface with `#DCE6F5` border and 8px border-radius.
  - Focused state: `#1769E8` focus ring with soft blue shadow (`rgba(23, 105, 232, 0.15)`).
- [x] **Cart Item Data Table**:
  - Header: `#F4F7FD` background, `#0B1F52` text, 12px font size.
  - Row lines: Delicate `#EEF3FA` borders, `#FFFFFF` row background with `#F8FAFE` active row highlight.
  - Quantity control buttons: Soft white pills with `#DCE6F5` border.
  - Delete row action: Soft red icon button (`#FEECEB` hover, `#D92D20` icon).
- [x] **Customer & Loyalty Panel (Right Column)**:
  - Customer search card: Soft white card with `#DCE6F5` border.
  - Loyalty points counter: Soft purple pill badge (`#F3F0FF` / `#6C3BE8`).
- [x] **Financial Totals Breakdown**:
  - Subtotal, Discounts, GST rows: Muted slate text `#5A6A85`.
  - Grand Total: Hero card with Deep Navy-to-Blue gradient (`linear-gradient(135deg, #0B1F52 0%, #1769E8 100%)`) and large white typography.
- [x] **Quick Tender Mode Buttons**:
  - Large tactile payment buttons: `[Cash]`, `[UPI]`, `[Card]`.
  - Cash button: Soft emerald fill (`#E7F8F0` / `#168447`).
  - Digital / UPI: Soft blue fill (`#EDF4FF` / `#1769E8`).
  - Card: Soft teal fill (`#E7F8F6` / `#078B87`).
- [x] **Tender / Payment Modal Dialog** (PDF Page 9):
  - Clean white modal body, `#0B1F52` navy modal header.
  - Cash Received input: Large bold typography.
  - Change to Return: Large bold emerald green typography (`#168447`).
- [x] **Hero Action Pay Button**:
  - Emerald green gradient (`linear-gradient(135deg, #10B981 0%, #059669 100%)`) with lift on hover and tactile press.

---

### Phase 3: Master Data Screens (`resources/views/master/*`)
*PDF Reference: Pages 13, 14, 15 & 17 (Master Data, Catalog & Datatables)*

*Targets: `items`, `customers`, `suppliers`, `brands`, `item-categories`, `uoms`, `gst-taxes`*

- [x] **Data Table Visual Layout**:
  - Clean table headers: `#F4F7FD` background, `#0B1F52` text, bold 11.5px uppercase, tracking 0.5px.
  - Row alternating background: `#FFFFFF` and subtle `#FAFCFF`.
  - Row hover effect: Smooth transition to `#F4F8FE` soft blue tint.
  - Row divider: 1px solid `#EEF3FA`.
- [x] **Action Buttons in Table Rows**:
  - Standardized soft action icon buttons:
    - Edit: Soft blue (`#EDF4FF` / `#1769E8` / `#CBE0FC`)
    - View: Soft teal (`#E7F8F6` / `#078B87` / `#C8ECE7`)
    - Delete: Soft red (`#FEECEB` / `#D92D20` / `#FCD4CF`)
- [x] **Status Badges**:
  - Active: Soft green (`#E7F8F0` / `#168447`)
  - Inactive: Soft slate (`#EEF3FA` / `#74839B`)
  - Out of Stock: Soft red (`#FEECEB` / `#D92D20`)
- [x] **Toolbar & Filter Header**:
  - Filter toolbar background: Soft `#F8FAFC` surface with delicate `#DCE6F5` bottom border.
  - Form labels: Uppercase bold 11px slate `#536581`.
  - Search & filter inputs: 6px border-radius, 34px height, `#FFFFFF` background, `#DCE6F5` border.
  - "Add New" button: Primary Urban Blue (`#1769E8`) with 8px radius.
  - Select2 filters: Styled to match UI 2.0 input height and borders with `#1769E8` focus ring.
- [x] **Create / Edit Form Modal / Card**:
  - Form field inputs: 8px radius, `#DCE6F5` borders, Inter font.
  - Organized tab panels: Pill navigation tabs with active blue state (`#EDF4FF` / `#1769E8`).
  - Alert messages: Soft pastels with rounded 8px container.
- [x] **Price Change Management**:
  - Removed inline CSS and centralized styles into `urbanpets-theme.css`.

---

### Phase 4: Sales & Purchase Management (`resources/views/sales/*`, `resources/views/purchase/*`)
*PDF Reference: Pages 16 & 18 (Sales Invoices, Quotations, Purchase Orders & Invoices)*

*Targets: `sales-bills`, `sales-quotations`, `sales-orders`, `sales-returns`, `purchase-invoices`, `purchase-orders`, `receipt-notes`, `delivery-notes`*

- [x] **Document List Data Tables**:
  - Bill / Invoice number column: Monospace font, `#1769E8` bold link.
  - Total amount column: Tabular numerals, right-aligned, bold `#168447` or `#0B1F52`.
  - Payment & document status pills:
    - Paid / Posted: Soft green (`#E7F8F0` / `#168447`)
    - Pending / Draft: Soft amber (`#FFF7EE` / `#D97706`)
    - Overdue / Cancelled: Soft red (`#FEECEB` / `#D92D20`)
    - Partially Paid / Converted: Soft blue (`#EDF4FF` / `#1769E8`)
  - Thermal Receipt action button: Soft green pill button (`#E7F8F0` / `#168447` / `#BDE4CF`).
- [x] **Document Detail & Print Slip Layout**:
  - Clean card surface with clear summary header (`.card-outline.card-secondary`).
  - Document information fields: Uppercase bold 11.5px slate labels (`#536581`) with bold `#0B1F52` values.
- [x] **ERP Sales Bill F6 Tender Dialog Theming**:
  - Modernized `.tender-table`, `.tender-label`, `.tender-summary-label`, and `.tender-btn` with UI 2.0 pastels and emerald hero pay button.
  - Removed inline CSS and centralized styles into `urbanpets-theme.css`.

---

### Phase 5: Inventory & Stock Management (`resources/views/inventory/*`)
*PDF Reference: Page 19 (Stock Transfers, Physical Stock, Stock Updates)*

*Targets: `stock-transfers`, `stock-updates`, `damage-stocks`, `opening-stocks`*

- [x] **Summary Statistics & KPI Cards (`.info-box`)**:
  - Converted harsh AdminLTE info-boxes to UI 2.0 soft pastels with `#DCE6F5` border and 12px radius.
  - Soft icon backgrounds with semantic pastel tints (Blue, Amber, Coral Red, Mint Green).
  - Bold `#0B1F52` numbers with tabular numerals.
- [x] **Stock Transfer & Movement Grids**:
  - Transfer status badges: "Received" (soft green), "Dispatched" (soft amber), "Cancelled" (soft red/slate).
  - Clean table layout with `#F4F7FD` sticky headers and `#EEF3FA` dividers.
- [x] **Expiry Tracking & Damage Stock**:
  - Clickable damage row hover highlight (`#FFF5F5`).
  - Standardized compact line items table (`#items-table`) and modal item results.
  - Cleaned up inline `<style>` blocks across all inventory views into `urbanpets-theme.css`.

---

### Phase 6: Reports & Till Closing (`resources/views/reports/*`, `resources/views/till/*`)
*PDF Reference: Page 20 (Reports, Analytics & Cashier Day-End Closing)*

- [x] **Cashier Day-End Closing / Till Screen (`till/sessions`)**:
  - Denomination counting table: Clean inputs with tabular total calculations and `#DCE6F5` border.
  - Cash reconciliation card: Expected vs Actual discrepancy badge (Green for matched, Red for variance).
  - Clean definition lists (`dl.row`): Uppercase 11.5px slate labels (`#536581`) and bold `#0B1F52` values.
- [x] **Report Viewers (`reports/*`)**:
  - Filter bar: Clean unified date range & branch filter card with standardized inputs.
  - Reports center catalog: Categorized report cards, smooth row hover, and soft arrow pill badges.
  - Centralized report print media styling in `urbanpets-theme.css`.

---

### Phase 7: Authentication Screen (`resources/views/auth/login.blade.php`)
*PDF Reference: Pages 10 & 23 (Brand Shell & Login Card)*

- [x] **Login Box**:
  - Soft elevated card with 14px radius and `#DCE6F5` border.
  - `URBAN PETS` logo with `#0B1F52` header.
  - Clean 8px input fields with left-aligned icons.
  - Primary `#1769E8` submit button with hover lift.
  - Centralized styling in `urbanpets-theme.css`, eliminated inline `<style>`.

---

## 🛠️ Implementation Strategy

All updates will follow this safe workflow:
1. **Centralized CSS First**: Add component styling rules directly into `public/css/urbanpets-theme.css`.
2. **Minimal View Edits**: In blade templates, only replace old dark Bootstrap classes (`bg-dark`, `table-dark`, etc.) with semantic classes (`card`, `table`, `badge`, `btn`) without touching any HTML hierarchy, data variables, or onclick handlers.
3. **Verification**: Run `php artisan view:clear` and verify view compilation to guarantee 0 regressions.
