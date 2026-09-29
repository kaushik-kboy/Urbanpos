import { chromium } from 'playwright-core';
import fs from 'fs';
import path from 'path';

const BASE = 'https://pos.ramdevcar.shop';
const QA_EMAIL = 'qa-staging@urbanpos.com';
const QA_PASSWORD = 'qa-staging-secret-2026';

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({
  viewport: { width: 1400, height: 900 },
  ignoreHTTPSErrors: true,
});
const page = await ctx.newPage();

console.log('1. Logging in to staging...');
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 25000 });
await page.fill('input[name=email]', QA_EMAIL);
await page.fill('input[name=password]', QA_PASSWORD);
await Promise.all([
  page.waitForNavigation({ timeout: 20000 }),
  page.click('button[type=submit]'),
]);
console.log('   Logged in! Current URL:', page.url());

// ─── 2. PURCHASE INVOICE CREATE ──────────────────────────────────────────────
console.log('2. Visiting purchase invoice create page...');
await page.goto(`${BASE}/purchase/purchase-invoices/create`, { waitUntil: 'networkidle', timeout: 25000 });

const pinvSidebarCollapsed = await page.evaluate(() => document.body.classList.contains('sidebar-collapse'));
console.log('   Pinv sidebar collapsed by default:', pinvSidebarCollapsed);

const tableInfo = await page.evaluate(() => {
  const table = document.getElementById('pinv-items-table');
  const wrapper = document.querySelector('.pinv-table-wrapper');
  if (!table) return null;
  const ths = Array.from(table.querySelectorAll('thead th')).map(th => ({
    text: th.innerText.trim(),
    width: th.getBoundingClientRect().width,
  }));
  return {
    tableWidth: table.getBoundingClientRect().width,
    tableScrollWidth: table.scrollWidth,
    wrapperScrollWidth: wrapper ? wrapper.scrollWidth : 0,
    wrapperClientWidth: wrapper ? wrapper.clientWidth : 0,
    isScrollable: wrapper ? wrapper.scrollWidth > wrapper.clientWidth : false,
    columns: ths,
  };
});
console.log('   Table info:', JSON.stringify(tableInfo, null, 2));

const pinvScreenshotPath = path.resolve('docs/pinv_create_verified.png');
await page.screenshot({ path: pinvScreenshotPath, fullPage: false });
console.log('   Saved screenshot to:', pinvScreenshotPath);

// ─── 3. PURCHASE ORDER CREATE ────────────────────────────────────────────────
console.log('3. Visiting purchase order create page...');
await page.goto(`${BASE}/purchase/purchase-orders/create`, { waitUntil: 'networkidle', timeout: 25000 });

const poSidebarCollapsed = await page.evaluate(() => document.body.classList.contains('sidebar-collapse'));
console.log('   PO sidebar collapsed by default:', poSidebarCollapsed);

// Select Supplier
console.log('   Selecting supplier...');
await page.evaluate(() => {
  const $sup = $('#supplier_id');
  if ($sup.length && $sup.find('option').length > 1) {
    const val = $sup.find('option').eq(1).val();
    $sup.val(val).trigger('change');
  }
});
await page.waitForTimeout(500);

// Focus Code / Barcode field and press Enter to open modal
console.log('   Opening item search modal...');
await page.click('.po-item-code');
await page.keyboard.press('Enter');
await page.waitForSelector('#po-item-search-modal.show', { timeout: 8000 });
console.log('   Item search modal is open!');

// Type a character to trigger search
await page.fill('#po-isl-filter-name', 'a');
await page.waitForTimeout(600);

// Wait for items to load in modal and select first item
await page.waitForSelector('#po-isl-items-body tr.po-isl-item-row', { timeout: 8000 });
const firstItemName = await page.evaluate(() => {
  const $row = $('#po-isl-items-body tr.po-isl-item-row').first();
  return { id: $row.data('id'), name: $row.data('name'), code: $row.data('code') };
});
console.log('   Selecting first item:', firstItemName);
await page.click('#po-isl-items-body tr.po-isl-item-row:first-child');
await page.waitForTimeout(600);

// Verify code field has Item ID
const codeFieldVal = await page.evaluate(() => $('.po-item-code').first().val());
console.log('   Code/Barcode field value is:', codeFieldVal, '(Expected Item ID:', firstItemName.id, ')');

// Enter quantity and prices
await page.fill('.po-qty', '5');
await page.fill('.po-cost', '100');
await page.fill('.po-sell', '150');
await page.fill('.po-mrp', '200');

// Test Tab flow: Focus MRP, press Tab -> should focus Disc %
console.log('   Testing Tab from MRP...');
await page.focus('.po-mrp');
await page.keyboard.press('Tab');
await page.waitForTimeout(100);

const focusAfterMrp = await page.evaluate(() => document.activeElement ? document.activeElement.className : '');
console.log('   Active element after Tab from MRP:', focusAfterMrp);
const isDiscPercentFocused = focusAfterMrp.includes('po-disc-percent');
console.log('   Disc % focused:', isDiscPercentFocused);

// Now press Tab from Disc % -> should focus Disc Amount
console.log('   Testing Tab from Disc %...');
await page.keyboard.press('Tab');
await page.waitForTimeout(100);

const focusAfterDiscPct = await page.evaluate(() => document.activeElement ? document.activeElement.className : '');
console.log('   Active element after Tab from Disc %:', focusAfterDiscPct);
const isDiscAmountFocused = focusAfterDiscPct.includes('po-disc-amount');
console.log('   Disc Amount focused:', isDiscAmountFocused);

// Now press Tab from Disc Amount -> should trigger addPoRowAndOpenSearchModal!
console.log('   Testing Tab from Disc Amount (should open item search modal)...');
await page.keyboard.press('Tab');
await page.waitForTimeout(600);

const isModalOpenAfterDiscAmount = await page.evaluate(() => $('#po-item-search-modal').hasClass('show'));
console.log('   Modal opened after Tab on Disc Amount:', isModalOpenAfterDiscAmount);

const totalRows = await page.evaluate(() => $('#po-items-body tr').length);
console.log('   Total PO table rows:', totalRows);

const poScreenshotPath = path.resolve('docs/po_create_verified.png');
await page.screenshot({ path: poScreenshotPath, fullPage: false });
console.log('   Saved screenshot to:', poScreenshotPath);

await browser.close();
console.log('All verifications complete!');
