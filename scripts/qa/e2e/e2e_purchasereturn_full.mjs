import { chromium } from 'playwright-core';
// Full keyboard Purchase Return flow: supplier -> original invoice -> qty
// (against the invoice's remaining-quantity ceiling) -> submit.
// Discovered while writing this script: picking an invoice here AUTO-LOADS its
// item rows (a fetch to /purchase-returns/invoice-items/{id}), unlike Sales
// Return's own convention where picking a bill deliberately does NOT auto-add
// items (see e2e_salesreturn.mjs's "A1" assertion) — a genuine, real difference
// between the two modules, not a bug, so there is no item-search-modal step to
// drive here for the with-invoice path; qty-vs-ceiling is the real keyboard
// surface. e2e_dates.mjs already covers this form's return_date validation.
// Fixture: PINV27551 / supplier id 941 / item LOADHOT0001, qty 5, 0 returned so
// far — a leftover, legitimate row from this same pass's Purchase Invoice
// script run, reused here rather than seeding anything new.
const BASE = 'http://127.0.0.1:8299';
const results = [];
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });
const dialogs = []; p.on('dialog', async d => { dialogs.push(d.message()); await d.dismiss(); });
const rec = (n, ok, note) => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`); };
const settle = (ms = 700) => p.waitForTimeout(ms);
const shot = (name) => p.screenshot({ path: `shot_pr_${name}.png` });

await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'qa-load-owner@example.com'); await p.fill('input[name=password]', 'secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

await p.goto(BASE + '/purchase/purchase-returns/create'); await p.waitForLoadState('networkidle'); await settle(500);

// 1. Supplier: the fixture supplier ("QA Supp 3474ab") is one of 11 identically-
// named rows left over from concurrency.php runs in urban_pos_qa (no supplier
// code field exists to disambiguate by typed text — a real gap in this dataset,
// not in the form), so pick it by its known id rather than an ambiguous keyboard
// search; the invoice select2 and qty-ceiling logic below are this script's point.
await p.selectOption('#supplier_id', '941'); await settle(600);
let suppVal = await p.evaluate(() => document.querySelector('#supplier_id')?.value || '');
rec('R1 Supplier selected (id, disambiguating same-named QA fixture rows)', suppVal === '941', 'supplier_id=' + suppVal);

// 2. Original Purchase Invoice via select2 keyboard search -> auto-loads its
// item(s) (fetch to invoice-items/{id}), pre-filling qty to the remaining amount
const invResp = p.waitForResponse(r => r.url().includes('invoice-items'), { timeout: 8000 });
await p.click('#select2-purchase_invoice_id-container'); await p.keyboard.type('PINV27551', { delay: 25 });
await p.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 }); await settle(400);
await p.keyboard.press('Enter');
await invResp; await settle(500);
let invVal = await p.evaluate(() => document.querySelector('#purchase_invoice_id')?.value || '');
rec('R2 Original Purchase Invoice picked via keyboard select2', !!invVal, 'purchase_invoice_id=' + invVal);

let codeVal = await p.evaluate(() => document.querySelector('#pr-items-body tr:first-child .pr-item-code')?.value || '');
rec('R3 Picking the invoice auto-loads its item row (no item-search-modal step needed for this path)', codeVal.includes('LOADHOT0001'), 'code=' + codeVal);

// 3. Qty ceiling: read the invoice's remaining qty from the row itself (don't
// hardcode it), try 1 more than the ceiling -> inline error + Tab blocked, then
// a valid partial qty -> error clears
const prQty = p.locator('#pr-items-body tr:first-child .pr-qty');
let maxQty = parseFloat(await p.evaluate(() => document.querySelector('#pr-items-body tr:first-child .pr-qty')?.getAttribute('data-remaining-qty') || 'NaN'));
rec('R4 Remaining-qty ceiling read from the row (from the invoice, not assumed)', !isNaN(maxQty) && maxQty > 0, 'remaining=' + maxQty);
if (isNaN(maxQty) || maxQty <= 0) maxQty = 1; // fallback so the rest of the script can still finish and report truthfully

// This form marks the invalid state on the field itself (.pr-qty.is-invalid,
// with the message in its title attribute) plus a blocking alert() — not a
// separate inline .invalid-feedback element like Sales Bill/Sales Return use.
await prQty.fill(String(maxQty + 1)); await prQty.dispatchEvent('input'); await prQty.dispatchEvent('change'); await settle(500);
let qtyInvalid = await p.evaluate(() => document.querySelector('#pr-items-body tr:first-child .pr-qty')?.classList.contains('is-invalid'));
let qtyTitle = await p.evaluate(() => document.querySelector('#pr-items-body tr:first-child .pr-qty')?.getAttribute('title') || '');
rec('R5 Qty over ceiling -> field marked invalid, with the reason in its title', qtyInvalid, qtyTitle);
rec('R6 Qty over ceiling also raised a blocking alert (this form\'s own convention)', dialogs.some(m => /cannot be greater than the available purchase quantity|cannot exceed the remaining returnable quantity/.test(m)), JSON.stringify(dialogs));

const validQty = Math.max(1, maxQty - 1);
await prQty.fill(String(validQty)); await prQty.dispatchEvent('input'); await prQty.dispatchEvent('change'); await settle(500);
let qtyStillInvalid = await p.evaluate(() => document.querySelector('#pr-items-body tr:first-child .pr-qty')?.classList.contains('is-invalid'));
rec('R7 Valid partial qty -> invalid state clears', !qtyStillInvalid, 'is-invalid=' + qtyStillInvalid);
await shot('1_before_submit');

// 4. Submit (scoped to this form's own button, avoiding the unrelated navbar
// search button that also matches a bare button[type=submit] selector)
p.on('response', async r => { if (r.request().method() === 'POST') console.log('   POST', r.url().slice(-40), r.status()); });
await Promise.all([
  p.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
  p.locator('#pr-form button[type="submit"]').click(),
]);
await settle(500);
const url = p.url();
const bodyText = await p.evaluate(() => document.body.innerText);
const flash = bodyText.match(/Purchase Return ([^\s]+) created successfully/)?.[0] || '';
const errors = await p.evaluate(() => [...document.querySelectorAll('.invalid-feedback, .alert-danger')].map(e => e.textContent.trim()).filter(Boolean));
rec('R8 Full keyboard flow (supplier->invoice(auto-load)->qty, submitted): return created, redirected to index', /purchase-returns/.test(url) && !/create/.test(url) && !!flash, url + ' | ' + flash + ' | errors=' + JSON.stringify(errors));
await shot('2_after_submit');

console.log('\nSUMMARY', results.filter(Boolean).length + '/' + results.length, 'passed', 'alerts=' + JSON.stringify(dialogs));
await b.close();
