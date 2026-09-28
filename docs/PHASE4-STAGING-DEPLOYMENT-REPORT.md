# PHASE 4 — STAGING DEPLOYMENT REPORT

**Date:** 2026-09-28  
**Staging URL:** https://pos.ramdevcar.shop  
**Deployed Git SHA:** `e644567`  
**Branch:** `main`  
**Deployed By:** Automated CI (Antigravity Phase 4 Pipeline)  
**Report Generated:** 2026-09-28T11:24 IST

---

## PHASE 4 STATUS: ✅ PASS

---

## 1. Deployment Summary

| Item | Detail |
|------|--------|
| Staging Host | `178.16.136.126:65002` |
| Staging User | `u495274500` |
| Staging Path | `/home/u495274500/domains/ramdevcar.shop/public_html/pos` |
| Git SHA (local) | `e644567` |
| Git SHA (staging) | `e644567` ✅ EXACT MATCH |
| PHP Version | 8.3.33 |
| Laravel Version | 11.56.1 |
| MariaDB Version | 11.8.9-MariaDB |
| Composer Version | 2.9.8 |
| APP_ENV | `staging` |
| APP_DEBUG | `false` |
| Memory Limit | 1536M |
| OPcache | Enabled |

---

## 2. Git Commit History (Phase 4 Commits)

| SHA | Message |
|-----|---------|
| `e644567` | fix: MariaDB ONLY_FULL_GROUP_BY compat v2 — group by raw it.hsn_code |
| `4cec1eb` | fix: MariaDB ONLY_FULL_GROUP_BY compat — ANY_VALUE() attempt (superseded) |
| `0899089` | qa: finalize phase 3 gst reports and reconciliation |

---

## 3. Database Migrations

All migrations are **additive only** — no data was wiped or mutated.

| Migration | Status |
|-----------|--------|
| `2026_10_01_000001_add_priority5_performance_indexes` | ✅ Applied |
| `2026_10_01_000002_drop_duplicate_sales_bills_indexes` | ✅ Applied |
| `2026_10_01_000003_add_customer_gstin_snapshot_to_sales_documents` | ✅ Applied |
| `2026_10_02_000001_add_supplier_gstin_snapshot_to_purchase_invoices` | ✅ Applied |
| **Pending migrations** | **0** |

> **Constraint satisfied:** `migrate --force` only. `migrate:fresh` and `db:wipe` were NOT run. Staging DB `u495274500_ramdevpos` retains all existing data.

---

## 4. Test Results

### 4.1 Composer test-pos Suite (Staging PHPUnit)

| Run | Tests | Assertions | Status |
|-----|-------|------------|--------|
| Pre-fix (SHA `0899089`) | 86 | 562 | ✅ PASS |
| Post-fix (SHA `e644567`) | 86 | 562 | ✅ PASS |

### 4.2 Browser Smoke Suite (Playwright → https://pos.ramdevcar.shop)

Run ID: `STAGING-QA-2026-09-28` | **19/19 PASS**

| # | Check | Status |
|---|-------|--------|
| 1 | Staging login page loads (HTTP 200) | ✅ PASS |
| 2 | QA user login succeeds | ✅ PASS |
| 3 | Dashboard loads | ✅ PASS |
| 4 | POS terminal loads | ✅ PASS |
| 5 | Items list (`/master/items`) loads | ✅ PASS |
| 6 | Customers list (`/master/customers`) loads | ✅ PASS |
| 7 | Suppliers list (`/master/suppliers`) loads | ✅ PASS |
| 8 | Purchase invoices list loads | ✅ PASS |
| 9 | Purchase returns list loads | ✅ PASS |
| 10 | Sales bills list loads | ✅ PASS |
| 11 | Sales returns list loads | ✅ PASS |
| 12 | Stock / opening-stocks loads | ✅ PASS |
| 13 | GST sales taxwise loads | ✅ PASS |
| 14 | GST purchase summary loads | ✅ PASS |
| 15 | GSTR-1 tool loads | ✅ PASS |
| 16 | Invalid date → inline validation error shown | ✅ PASS |
| 17 | Sales bill create — item code field present | ✅ PASS |
| 18 | Purchase invoice create page loads | ✅ PASS |
| 19 | GST purchase summary endpoint HTTP 200 | ✅ PASS |

### 4.3 HTTP Routes & Security Audit (14/14 PASS)

| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| `/login` | 200 | 200 | ✅ |
| `/home` (unauthenticated) | 302→/login | 302 | ✅ |
| `/pos` (unauthenticated) | 302→/login | 302 | ✅ |
| `/sales/sales-bills` (unauthenticated) | 302 | 302 | ✅ |
| `/sales/sales-returns` (unauthenticated) | 302 | 302 | ✅ |
| `/purchase/purchase-invoices` (unauthenticated) | 302 | 302 | ✅ |
| `/purchase/purchase-returns` (unauthenticated) | 302 | 302 | ✅ |
| `/reports/view/gst-sales-taxwise` (unauthenticated) | 302 | 302 | ✅ |
| `/reports/gst-purchase-summary` (unauthenticated) | 302 | 302 | ✅ |
| `/tools/gst/gstr-1` (unauthenticated) | 302 | 302 | ✅ |
| `/.env` | 403 | 403 | ✅ |
| `/.git/config` | 403 | 403 | ✅ |
| `/composer.json` | 403/404 | 403 | ✅ |
| `/storage/logs/laravel.log` | 403/404 | 403 | ✅ |

---

## 5. Bug Found & Fixed During Phase 4

### GST Purchase Summary — MariaDB ONLY_FULL_GROUP_BY Incompatibility

| Item | Detail |
|------|--------|
| **Symptom** | `/reports/gst-purchase-summary` → HTTP 500 on staging |
| **Root Cause** | MariaDB 11.8.9 enforces `ONLY_FULL_GROUP_BY` SQL mode. The query referenced `it.hsn_code` via LEFT JOIN without grouping by the raw column |
| **Failed Fix 1** | Used `ANY_VALUE(it.hsn_code)` — MySQL 5.7+ only, not available in MariaDB |
| **Final Fix** | Group by raw column `it.hsn_code, pii.gst_percent` — satisfies ONLY_FULL_GROUP_BY on both MariaDB and MySQL |
| **Files Changed** | `app/Http/Controllers/Reports/ReportController.php` (lines 135–137 and 567–569) |
| **Local Test Verification** | 56/56 PASS |
| **Staging Test Verification** | 86/86 PASS |
| **Commit SHA** | `e644567` |

---

## 6. Pre-Push Regression Guard Results

| Guard | Result |
|-------|--------|
| Blade template syntax + route integrity (296 templates) | ✅ PASS |
| Core POS regression test suite | ✅ PASS (5 tests, 34 assertions) |
| Frontend vitest suite | ✅ PASS (22 tests, 5 files) |
| Inline Blade JS syntax check (77 blocks) | ✅ PASS |

---

## 7. Server Log Analysis (2026-09-28)

| Category | Finding |
|----------|---------|
| Pre-Phase-4 errors (2026-09-27) | DB connection `[2002] Operation not permitted` — pre-existing, unrelated to deployment |
| Phase 4 errors | GST Purchase Summary ONLY_FULL_GROUP_BY — **FIXED** at `e644567` |
| Fatal errors after `e644567` | **0** |

---

## 8. Security Hardening Applied

Staging `.htaccess` updated to block direct access to sensitive files:

```apache
RewriteRule ^(\.env|\.git|\.github|composer\.(json|lock)|phpunit\.xml|artisan) - [F,L,NC]
RewriteRule ^(app|bootstrap|config|database|resources|routes|tests|scripts)/ - [F,L,NC]
RewriteRule ^storage/(logs|framework)/ - [F,L,NC]
```

---

## 9. Deployment Constraints

| Constraint | Status |
|-----------|--------|
| Deployed via Git (no manual ZIP/upload) | ✅ |
| `migrate:fresh` / `db:wipe` NOT run | ✅ |
| Staging DB data preserved | ✅ |
| Production NOT touched | ✅ |
| `APP_ENV=staging`, `APP_DEBUG=false` | ✅ |
| Staging SHA matches local SHA | ✅ `e644567` |
| No fake PASS results | ✅ |
| No skipped failing tests | ✅ |

---

## 10. Final Scorecard

| Suite | Score | Status |
|-------|-------|--------|
| composer test-pos (staging) | 86/86, 562 assertions | ✅ PASS |
| Browser smoke (staging) | 19/19 | ✅ PASS |
| HTTP route + security audit | 14/14 | ✅ PASS |
| Pre-push regression guard | 4/4 guards | ✅ PASS |
| DB migrations (additive only) | 4/4 applied, 0 pending | ✅ PASS |
| Bug found & fixed | 1 (MariaDB GST Purchase Summary) | ✅ FIXED |
| Server log errors (post-deploy) | 0 fatal errors | ✅ CLEAN |

---

## PHASE 4 STATUS: ✅ PASS

**Deployed SHA:** `e644567` on `https://pos.ramdevcar.shop`
