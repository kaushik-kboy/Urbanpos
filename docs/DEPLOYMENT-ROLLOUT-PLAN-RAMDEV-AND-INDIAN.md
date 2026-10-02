# UrbanPOS: Safe Rollout & Verification Plan (Ramdev & Indian)

> [!IMPORTANT]
> **Git Push Status:** Successfully pushed to GitHub (`origin/main` commit `aaf855c`).  
> All pre-push regression guards passed with 100% green status (Core POS regression tests, Vitest suite, and Blade JS syntax verification).

---

## Architecture & Code Changes Included in this Release (`aaf855c`)

1. **Commit `1586388` — Global Barcode Standardization (Task 6)**:
   - Direct scan lookup across all 11 modules without opening the popup modal.
   - Automatic jump to Quantity field (`.qty`).
   - Hardware debounce protection (`PosScanGuard` < 400ms duplicate filter).
   - Strict `exact_match_only` lookup avoiding unintended name search fallbacks.

2. **Commit `97fa888` — Purchase Free Qty + Margin & Landing Cost (Step 9)**:
   - Landing Cost formula: $\frac{\text{Billed Cost} - \text{Line Discount}}{\text{Billed Qty} + \text{Free Qty}}$
   - Real-time **Landing Cost Price**, **Margin %**, and **Profit %** calculation on Purchase Invoice form.
   - Physical inventory intake in `StockLedger` at effective unit cost (correct balance sheet stock valuation).
   - Syncs `landing_cost` to `items` and `item_stocks`.

3. **Commit `aaf855c` — Receipt Designer & Contextual Hotkeys**:
   - AJAX save handler with non-blocking feedback and toastr notifications.
   - Contextual `Alt+C` Cash tender mode selection inside billing modal.

---

## Phase 1: Deploy on "Ramdev" (Testing Environment)

### Step 1.1: Pull latest certified code on Ramdev Server

```bash
# 1. Check current branch and git status
git status

# 2. Fetch the latest certified branch from remote
git fetch origin main

# 3. Pull cleanly (ensures latest commit aaf855c is applied without merge conflicts)
git pull origin main

# 4. Run migrations (adds effective_cost column to purchase_invoice_items)
php artisan migrate --force

# 5. Clear application, route, and view caches
php artisan optimize:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear
```

> [!TIP]
> Agar Ramdev server par pehle koi local uncommitted edits thi to unhe pull se pehle check karein:
> `git status` -> agar modified files hain to `git stash` karein taaki purana code naye commits ko block na kare.

---

### Step 1.2: Verification Checklist on Ramdev

| Test Area | What to Test | Expected Behavior | Status |
| :--- | :--- | :--- | :---: |
| **1. Barcode Scanner (Sales Bill)** | Scan barcode into `Code / Barcode` field | Row auto-populates immediately; modal **does not open**; cursor jumps to **Qty**. | [ ] |
| **2. Scanner Bounce Guard** | Rapid scan / double click barcode | Duplicate line create nahi hogi (`PosScanGuard` suppresses bounce). | [ ] |
| **3. Empty Search Modal** | Press `Enter` or `F2` on empty code field | Search Modal opens and search input is focused. | [ ] |
| **4. Barcode in Purchase & Stock Updates** | Scan in Purchase Invoice & Stock Updates | Direct scan lookup works without popup interruption. | [ ] |
| **5. Purchase Free Qty** | Enter `Qty = 10`, `Free = 2`, `Cost = 100`, `Sell = 150` | `Landing Cost` shows **₹83.33**, `Margin %` shows **34.45%**, `Profit %` shows **52.54%**. | [ ] |
| **6. Purchase with Discount** | Enter `Qty = 10`, `Free = 2`, `Cost = 100`, `Disc = 10%` | `Landing Cost` shows **₹75.00**, `Margin %` shows **41.00%**, `Profit %` shows **69.49%**. | [ ] |
| **7. Purchase Invoice Save** | Save Purchase Invoice with Free Qty | Total physical qty = 12; stock ledger shows 12 units intake @ effective landing cost. | [ ] |
| **8. Receipt Designer** | Go to Tools $\rightarrow$ Receipt Designer & click Save | Settings save smoothly via AJAX with green success feedback. | [ ] |
| **9. Tender Hotkey** | In Sales Bill tender modal, press `Alt+C` | Cash mode selects immediately without opening customer page. | [ ] |

---

## Phase 2: Sign-Off & Production Rollout to "Indian"

Ramdev testing checklist pass hone ke baad **Indian** server par safe deployment karein:

### Step 2.1: Pre-Deployment Database Backup (Safety First)

```bash
# Database backup zaroor lein
mysqldump -u root -p urbanpos_db > backup_before_step9_$(date +%F_%T).sql
```

### Step 2.2: Apply Clean Update on Indian Server

```bash
# 1. Verify working directory is clean
git status

# 2. Pull the certified code
git pull origin main

# 3. Run migration
php artisan migrate --force

# 4. Production cache optimization
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 2.3: Quick 2-Minute Smoke Test on Indian
1. Open Sales Bill -> Scan 1 item (verify barcode lookup).
2. Open Purchase Invoice -> Create test purchase with Free Qty (verify Landing Cost calculation).
3. Check Thermal Print / Receipt view.
