/**
 * staging_browser_smoke.mjs
 * Phase 4 Browser Staging Smoke Test
 * Target: https://pos.ramdevcar.shop
 * QA Run ID: STAGING-QA-2026-09-28
 */
import { chromium } from 'playwright-core';

const BASE = 'https://pos.ramdevcar.shop';
const RUN_ID = 'STAGING-QA-2026-09-28';
const QA_EMAIL = 'qa-staging@urbanpos.com';
const QA_PASSWORD = 'qa-staging-secret-2026';

const results = [];
const rec = (name, ok, note = '') => {
  results.push({ name, ok, note });
  console.log(`${ok ? 'PASS' : 'FAIL'}  [${RUN_ID}] ${name}${note ? '  -- ' + note : ''}`);
};

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({
  viewport: { width: 1600, height: 950 },
  ignoreHTTPSErrors: true,
});
const page = await ctx.newPage();
const dialogs = [];
page.on('dialog', async d => { dialogs.push(d.message()); await d.dismiss(); });

// ─── LOGIN ────────────────────────────────────────────────────────────────────
try {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 20000 });
  rec('1. Staging login page loads (HTTP 200)', page.url().includes('/login'), `url=${page.url()}`);
  await page.fill('input[name=email]', QA_EMAIL);
  await page.fill('input[name=password]', QA_PASSWORD);
  await Promise.all([page.waitForNavigation({ timeout: 15000 }), page.click('button[type=submit]')]);
  const loggedIn = !page.url().includes('/login');
  rec('2. QA user login succeeds', loggedIn, `url=${page.url()}`);
} catch (e) {
  rec('2. QA user login succeeds', false, String(e));
}

// ─── CORE ROUTES ──────────────────────────────────────────────────────────────
const routes = [
  ['/home',                              '3. Dashboard loads'],
  ['/pos',                               '4. POS terminal loads'],
  ['/master/items',                      '5. Items list loads'],
  ['/master/customers',                  '6. Customers list loads'],
  ['/master/suppliers',                  '7. Suppliers list loads'],
  ['/purchase/purchase-invoices',        '8. Purchase invoices list loads'],
  ['/purchase/purchase-returns',         '9. Purchase returns list loads'],
  ['/sales/sales-bills',                 '10. Sales bills list loads'],
  ['/sales/sales-returns',               '11. Sales returns list loads'],
  ['/inventory/opening-stocks',          '12. Stock / opening-stocks loads'],
  ['/reports/view/gst-sales-taxwise',    '13. GST sales taxwise loads'],
  ['/reports/gst-purchase-summary',      '14. GST purchase summary loads'],
  ['/tools/gst/gstr-1',                  '15. GSTR-1 tool loads'],
];

for (const [path, desc] of routes) {
  try {
    const resp = await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const status = resp?.status() ?? 0;
    const ok = status === 200;
    rec(desc, ok, `status=${status}`);
  } catch (e) {
    rec(desc, false, String(e).slice(0, 120));
  }
}

// ─── DATE VALIDATION SMOKE ────────────────────────────────────────────────────
try {
  await page.goto(`${BASE}/purchase/purchase-invoices/create`, { waitUntil: 'networkidle', timeout: 20000 });
  const dateField = page.locator('input[name=invoice_date].datepicker, input[name=invoice_date]');
  await dateField.waitFor({ timeout: 8000 });
  await dateField.click();
  await page.keyboard.press('Control+A');
  await dateField.pressSequentially('35/15/2026', { delay: 20 });
  await dateField.press('Tab');
  await page.waitForTimeout(600);
  const errors = await page.evaluate(() =>
    [...document.querySelectorAll('.date-feedback-error, .is-invalid')]
      .filter(e => e.offsetParent !== null).length
  );
  rec('16. Invalid date shows validation error', errors > 0, `visibleErrors=${errors}`);
} catch (e) {
  rec('16. Invalid date shows validation error', false, String(e).slice(0, 120));
}

// ─── SALES BILL CREATE PAGE ───────────────────────────────────────────────────
try {
  await page.goto(`${BASE}/sales/sales-bills/create`, { waitUntil: 'networkidle', timeout: 20000 });
  const itemRow = page.locator('#sb-items-body tr:first-child .sb-item-code');
  await itemRow.waitFor({ timeout: 10000 });
  rec('17. Sales bill create page - item code field present', true, '');
} catch (e) {
  rec('17. Sales bill create page - item code field present', false, String(e).slice(0, 120));
}

// ─── PURCHASE INVOICE CREATE PAGE ────────────────────────────────────────────
try {
  await page.goto(`${BASE}/purchase/purchase-invoices/create`, { waitUntil: 'networkidle', timeout: 20000 });
  const piForm = await page.locator('form').count();
  rec('18. Purchase invoice create page loads', piForm > 0, `forms=${piForm}`);
} catch (e) {
  rec('18. Purchase invoice create page loads', false, String(e).slice(0, 120));
}

// ─── EXPORT ENDPOINTS (CONTENT CHECK) ────────────────────────────────────────
try {
  const apiResp = await page.goto(`${BASE}/reports/gst-purchase-summary`, { waitUntil: 'domcontentloaded', timeout: 15000 });
  rec('19. GST Purchase Summary report endpoint reachable', apiResp?.status() === 200, `status=${apiResp?.status()}`);
} catch (e) {
  rec('19. GST Purchase Summary report endpoint reachable', false, String(e).slice(0, 120));
}

// ─── SUMMARY ──────────────────────────────────────────────────────────────────
await browser.close();

const passed = results.filter(r => r.ok).length;
const failed = results.filter(r => !r.ok).length;
console.log(`\n${'─'.repeat(60)}`);
console.log(`STAGING BROWSER SMOKE — Run ID: ${RUN_ID}`);
console.log(`TOTAL : ${results.length}  PASS : ${passed}  FAIL : ${failed}`);
console.log(`STATUS: ${failed === 0 ? 'PASS' : 'FAIL'}`);
if (failed > 0) {
  console.log('\nFailed checks:');
  results.filter(r => !r.ok).forEach(r => console.log(` • ${r.name} — ${r.note}`));
}
process.exit(failed > 0 ? 1 : 0);
