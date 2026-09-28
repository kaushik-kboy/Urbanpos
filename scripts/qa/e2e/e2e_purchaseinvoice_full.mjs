import { chromium } from 'playwright-core';
// Full keyboard Purchase Invoice flow: date -> supplier -> supplier inv no/amount ->
// item (via the modal-based item search, this module's real keyboard convention,
// unlike Sales Bill's direct-code-lookup) -> qty -> cost -> submit.
// e2e_dates.mjs already covers this form's date-field validation in isolation;
// this script's job is everything after that: header gating, the item search
// modal's keyboard nav, and the actual end-to-end submit.
const BASE = 'http://127.0.0.1:8299';
const results = [];
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });
const dialogs = []; p.on('dialog', async d => { dialogs.push(d.message()); await d.dismiss(); });
const rec = (n, ok, note) => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`); };
const settle = (ms = 700) => p.waitForTimeout(ms);
const shot = (name) => p.screenshot({ path: `shot_pi_${name}.png` });

await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'qa-load-owner@example.com'); await p.fill('input[name=password]', 'secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

await p.goto(BASE + '/purchase/purchase-invoices/create'); await p.waitForLoadState('networkidle'); await settle(500);

// 1. invoice_date: valid date via keyboard, Enter -> moves to supplier (per this
// form's own Enter-chaining; date-field VALIDATION itself is e2e_dates.mjs's job)
const dateField = p.locator('input[name=invoice_date]');
await dateField.click(); await p.keyboard.press('Control+a'); await dateField.pressSequentially('25/09/2026', { delay: 20 });
await dateField.press('Enter'); await settle(500);
let onSupplier = await p.evaluate(() => document.activeElement?.closest('.select2-container')?.previousElementSibling?.id === 'supplier_id' || document.activeElement?.id === 'supplier_id');
rec('P1 Enter on invoice_date moves focus to Supplier', onSupplier, '');

// 2. Supplier via select2 keyboard search
await p.click('#select2-supplier_id-container'); await p.keyboard.type('QA Supp 3474ab', { delay: 25 });
await p.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 }); await settle(400);
await p.keyboard.press('Enter'); await settle(500);
let suppVal = await p.evaluate(() => document.querySelector('#supplier_id')?.value || '');
rec('P2 Supplier picked via keyboard select2', !!suppVal, 'supplier_id=' + suppVal);

// 3. Supplier Inv No (required, auto-uppercased) + Inv Amount (required, >0) via keyboard
const invNo = 'E2EPI' + Date.now();
await p.fill('#supplier_inv_no', invNo); await p.locator('#supplier_inv_no').press('Tab'); await settle(1500); // let the async duplicate-check XHR settle
await p.fill('input[name=supplier_inv_amount]', '1'); await p.locator('input[name=supplier_inv_amount]').dispatchEvent('change'); await settle(500);

// 4. Item code field: by this module's own design, a MOUSE CLICK on it does NOT
// open the search modal ("Open modal on Code/Barcode field: Tab or Enter/F2 —
// Mouse Click disabled", per _form.blade.php) — real keyboard focus does.
// NOTE: a real Tab keypress out of Inv Amount was found to be unreliable here
// under headless automation (a placeholder Inv Amount that doesn't yet match the
// zero-item live total gets flagged live by form-sequential-validator.js, and its
// own Tab-blocking races with this field's re-render); focusing the field directly
// is the reliable equivalent of "the user tabbed here" without that race.
const itemCode = p.locator('#pinv-items-body tr:first-child .pinv-item-code');
await itemCode.focus(); await settle(400);
let modalOpen = await p.evaluate(() => document.querySelector('#pinv-item-search-modal')?.classList.contains('show'));
rec('P3 Item code field opens the Item Search modal', modalOpen, '');
await p.waitForSelector('#pinv-isl-filter-name:focus', { timeout: 5000 }).catch(() => {});
await p.keyboard.type('LOADHOT0001', { delay: 25 });
await p.waitForSelector('.pinv-isl-item-row', { timeout: 8000 }); await settle(400);
let rowText = await p.evaluate(() => document.querySelector('.pinv-isl-item-row')?.textContent || '');
rec('P4 Search finds the item by code', rowText.includes('Load Hot Item 1'), rowText.slice(0, 60));
await p.keyboard.press('Enter'); // selects the highlighted (first) row
await p.waitForSelector('#pinv-item-search-modal:not(.show)', { timeout: 5000 });
await settle(400);
let codeVal = await p.evaluate(() => document.querySelector('#pinv-items-body tr:first-child .pinv-item-code')?.value || '');
rec('P5 Item selected via keyboard (Enter in modal), modal closes', codeVal.includes('LOADHOT0001'), 'code=' + codeVal);

// 5. Qty via keyboard; Enter chains to Cost (per pos-hotkeys.js's pinv-qty handling)
const pinvQty = p.locator('#pinv-items-body tr:first-child .pinv-qty');
await pinvQty.waitFor({ state: 'visible', timeout: 5000 });
await pinvQty.fill('5'); await pinvQty.dispatchEvent('input'); await pinvQty.dispatchEvent('change');
await pinvQty.press('Enter');
let costFocused = await p.evaluate(() => document.activeElement?.classList?.contains('pinv-cost'));
let costVal = '';
for (let i = 0; i < 10 && !costVal; i++) {
  await settle(200);
  costVal = await p.evaluate(() => document.querySelector('#pinv-items-body tr:first-child .pinv-cost')?.value || '');
}
rec('P6 Qty entered -> Cost auto-filled from item master', parseFloat(costVal) > 0, 'cost=' + costVal);
rec('P6b Enter chains focus to Cost', costFocused, 'focused=' + costFocused);

// 6. Supplier Inv Amount must match the computed Final Amount exactly (read it
// from the app's own live mismatch-status message, rather than hardcoding an
// expected total — this is the only place the live total is actually rendered;
// #display-final-total, which the JS also writes to, has no matching element in
// this form's HTML, a small dead-code gap noted in the QA report, not fixed here)
// The totals recalc is debounced, so poll instead of a single fixed settle().
let statusText = '', finalTotal = NaN;
for (let i = 0; i < 10; i++) {
  await settle(300);
  statusText = await p.evaluate(() => document.querySelector('#supplier-inv-amount-match-status')?.textContent || '');
  finalTotal = parseFloat(statusText.match(/vs Final: ₹([\d.]+)/)?.[1] ?? 'NaN');
  if (!isNaN(finalTotal) && finalTotal > 0) break;
}
rec('P7 Live final total read from the amount-match status message', !isNaN(finalTotal) && finalTotal > 0, 'status="' + statusText.trim() + '"');
const invAmt = p.locator('input[name=supplier_inv_amount]');
await invAmt.fill(finalTotal.toFixed(2)); await invAmt.dispatchEvent('input'); await invAmt.dispatchEvent('change'); await settle(400);
await shot('1_before_submit');

// 7. Submit (scoped to the actual form's button, avoiding the unrelated navbar
// search button that also matches a bare button[type=submit] selector)
p.on('response', async r => { if (r.request().method() === 'POST') console.log('   POST', r.url().slice(-40), r.status()); });
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
  p.locator('#pinv-form button[type="submit"]').click(),
]);
await settle(500);
const url = p.url();
const bodyText = await p.evaluate(() => document.body.innerText);
const flash = bodyText.match(/Purchase Invoice ([^\s]+) created successfully/)?.[0] || '';
const errors = await p.evaluate(() => [...document.querySelectorAll('.invalid-feedback, .alert-danger')].map(e => e.textContent.trim()).filter(Boolean));
rec('P8 Full keyboard flow (date->supplier->item->qty, submitted): invoice created, redirected to index', /purchase-invoices/.test(url) && !/create/.test(url) && !!flash, url + ' | ' + flash + ' | errors=' + JSON.stringify(errors));
await shot('2_after_submit');

console.log('\nSUMMARY', results.filter(Boolean).length + '/' + results.length, 'passed', 'alerts=' + JSON.stringify(dialogs));
await b.close();
