import { chromium } from 'playwright-core';
// Full keyboard Sales Bill flow: customer -> item(code) -> qty -> payment mode -> submit.
// Builds on e2e_items.mjs's item/qty conventions; this script's job is the part
// those didn't cover: payment mode selection and the actual end-to-end submit.
const BASE = 'http://127.0.0.1:8299';
const results = [];
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });
const dialogs = []; p.on('dialog', async d => { dialogs.push(d.message()); await d.dismiss(); });
const rec = (n, ok, note) => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`); };
const settle = (ms = 700) => p.waitForTimeout(ms);
const shot = (name) => p.screenshot({ path: `shot_sb_${name}.png` });

await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'qa-load-1@example.com'); await p.fill('input[name=password]', 'secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

await p.goto(BASE + '/sales/sales-bills/create'); await p.waitForLoadState('networkidle');
await p.evaluate(() => { try { localStorage.clear(); sessionStorage.clear(); } catch {} });
await p.reload(); await p.waitForLoadState('networkidle'); await settle(500);

// 1. Customer via select2 keyboard search (same convention as e2e_salesreturn.mjs)
await p.click('#select2-customer_id-container'); await p.keyboard.type('C0017670', { delay: 30 });
await p.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 }); await settle(400);
await p.keyboard.press('Enter'); await p.waitForLoadState('networkidle'); await settle(1500);
let custVal = await p.evaluate(() => document.querySelector('#customer_id')?.value || '');
rec('S1 Customer picked via keyboard select2', !!custVal, 'customer_id=' + custVal);

// 2. Item by code + Enter -> direct load, qty focused
// (waitForResponse instead of a fixed settle() — the customer-change AJAX just
// completed and can otherwise race with this lookup under headless load)
const code = p.locator('#sb-items-body tr:first-child .sb-item-code');
await code.click(); await code.fill('LOADHOT0001');
const lookupResp = p.waitForResponse(r => r.url().includes('lookup-item'), { timeout: 8000 });
await code.press('Enter');
await lookupResp; await settle(500);
let desc = await p.evaluate(() => document.querySelector('#sb-items-body tr:first-child .sb-item-desc')?.value || '');
let activeIsQty = await p.evaluate(() => document.activeElement?.classList?.contains('sb-qty') || false);
rec('S2 Item loaded via keyboard code lookup', desc.includes('Load Hot Item 1'), 'desc=' + desc);
rec('S3 Qty focused immediately after lookup', activeIsQty, '');

// 3. Qty entered via keyboard, Tab moves forward without error
const qty = p.locator('#sb-items-body tr:first-child .sb-qty');
await qty.fill('2'); await qty.dispatchEvent('input'); await qty.dispatchEvent('change'); await settle(500);
let qtyErr = await p.evaluate(() => [...document.querySelectorAll('.sb-qty-error-msg')].filter(e => e.offsetParent !== null).map(e => e.textContent.trim()));
rec('S4 Valid qty 2 -> no error', qtyErr.length === 0, JSON.stringify(qtyErr));
await qty.press('Tab'); await settle(400);

// 4. Payment mode via keyboard (select2) — switch from default Cash to UPI
await p.click('#select2-payment_type-container'); await settle(300);
await p.keyboard.type('UPI', { delay: 40 }); await p.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 5000 }); await settle(300);
await p.keyboard.press('Enter'); await settle(400);
let paymentVal = await p.evaluate(() => document.querySelector('#payment_type')?.value || '');
rec('S5 Payment mode changed to UPI via keyboard select2', paymentVal === 'UPI', 'payment_type=' + paymentVal);
await shot('1_before_submit');

// 5. Submit button reflects valid state (enabled) before we submit
let btnDisabled = await p.evaluate(() => document.querySelector('#sb-main-save-btn')?.disabled ?? true);
rec('S6 Save button enabled once header+item are valid', !btnDisabled, 'disabled=' + btnDisabled);

// 6. Save is a TWO-STEP keyboard flow, not a single submit: clicking/F6-ing the
// Save button opens the Tender/Payment modal first (#sb-tender-modal), which is
// the real "-> payment ->" step; the actual HTML-form submit only happens once
// that modal is confirmed (Enter on #tender-cash, or #tender-save-btn).
await p.locator('#sb-main-save-btn').click();
await p.waitForSelector('#sb-tender-modal.show', { timeout: 5000 });
await settle(600); // app focuses #tender-cash on its own 200ms timer after the modal opens
let tenderCash = await p.evaluate(() => document.querySelector('#tender-cash')?.value || '');
let tenderFocused = await p.evaluate(() => document.activeElement?.id === 'tender-cash');
rec('S7 Save opens Tender modal, cash pre-filled with bill total & focused', !!tenderCash && parseFloat(tenderCash) > 0 && tenderFocused, 'cash=' + tenderCash + ' focused=' + tenderFocused);
await shot('1b_tender_modal');

// 7. Confirm payment via keyboard: Enter on the focused #tender-cash field.
// (A prior pass here had to fall back to a click workaround — root-caused and
// fixed in public/js/select2-init.js since: its global "prevent accidental
// Enter-submit" guard was silently stopping the modal's own Enter-confirm
// handler via a jQuery handler-queue quirk. See e2e_tender_enter_confirm.mjs
// for the dedicated regression test and full root-cause writeup.)
p.on('response', async r => { if (r.request().method() === 'POST') console.log('   POST', r.url().slice(-40), r.status()); });
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
  p.keyboard.press('Enter'),
]);
await settle(500);
const url = p.url();
const bodyText = await p.evaluate(() => document.body.innerText);
const flash = bodyText.match(/Sales Bill ([^\s]+) created successfully/)?.[0] || '';
rec('S8 Full keyboard flow (customer->item->qty->payment->Enter confirm): bill created, redirected to index', /sales-bills/.test(url) && !/create/.test(url) && !!flash, url + ' | ' + flash);
await shot('2_after_submit');

console.log('\nSUMMARY', results.filter(Boolean).length + '/' + results.length, 'passed', 'alerts=' + JSON.stringify(dialogs));
await b.close();
