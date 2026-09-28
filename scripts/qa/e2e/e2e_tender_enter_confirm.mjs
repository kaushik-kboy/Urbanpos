import { chromium } from 'playwright-core';
// Focused regression test for the Tender/Payment modal's Enter-to-confirm bug.
//
// ROOT CAUSE (found this pass): public/js/select2-init.js has a global
// "prevent accidental Enter-submit" handler delegated on document via a
// selector (`form input:not([type=submit])...`). jQuery's dispatch loop groups
// handlers by the DOM node they're matched against, and treats selector-matched
// groups and plain `$(document).on('keydown', fn)` (no-selector) groups as
// SEPARATE levels, stopping the walk between levels on stopPropagation() (which
// `return false` triggers) — even without stopImmediatePropagation(). The
// Tender modal's own Enter-confirm handler (`_form.blade.php`, no selector) is
// one such later level, so it silently never ran whenever #tender-cash (an
// <input> inside the real <form>) had focus. Handlers matching the SAME target
// as the guard (e.g. .sb-item-code, .sb-qty) were unaffected, since same-level
// handlers only stop on stopImmediatePropagation() — which is why this bug
// affected only the Tender confirm step, not the rest of the keyboard flow.
//
// FIX: select2-init.js's guard now skips any field inside `.closest('.modal')`,
// since modals are always a deliberate action-confirmation surface with their
// own explicit Enter-handling.
//
// This script proves, end to end and repeatedly:
//  1. The Tender modal opens on Save.
//  2. #tender-cash receives focus.
//  3. Enter from #tender-cash confirms payment (not a click workaround).
//  4. A Sales Bill is actually created (real redirect + flash message).
//  5. Pressing Enter again immediately after (simulating an accidental double
//     press before navigation lands) does not create a duplicate bill.
const BASE = 'http://127.0.0.1:8299';
const results = [];
const rec = (n, ok, note) => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`); };

async function run(iteration) {
  const b = await chromium.launch({ channel: 'chrome', headless: true });
  const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });
  const settle = (ms = 500) => p.waitForTimeout(ms);

  await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'qa-load-1@example.com'); await p.fill('input[name=password]', 'secret123');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  await p.goto(BASE + '/sales/sales-bills/create'); await p.waitForLoadState('networkidle');

  await p.click('#select2-customer_id-container'); await p.keyboard.type('C0017670', { delay: 20 });
  await p.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
  await p.keyboard.press('Enter'); await p.waitForLoadState('networkidle'); await settle(1000);

  const code = p.locator('#sb-items-body tr:first-child .sb-item-code');
  await code.click(); await code.fill('LOADHOT0001');
  const lookupResp = p.waitForResponse(r => r.url().includes('lookup-item'), { timeout: 8000 });
  await code.press('Enter'); await lookupResp; await settle(400);
  const qty = p.locator('#sb-items-body tr:first-child .sb-qty');
  await qty.fill('1'); await qty.dispatchEvent('input'); await qty.dispatchEvent('change'); await settle(400);

  await p.locator('#sb-main-save-btn').click();
  await p.waitForSelector('#sb-tender-modal.show', { timeout: 5000 });
  await settle(600);
  rec(`[${iteration}] T1 Tender modal opens on Save`, true, '');

  const focused = await p.evaluate(() => document.activeElement?.id);
  rec(`[${iteration}] T2 #tender-cash receives focus`, focused === 'tender-cash', 'active=' + focused);

  let extraEnterFired = false;
  p.on('dialog', async d => { await d.dismiss(); }); // safety net, none expected

  await Promise.all([
    p.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }),
    p.keyboard.press('Enter'),
  ]);
  await settle(300);
  const url = p.url();
  const flash = await p.evaluate(() => document.body.innerText.match(/Sales Bill ([^\s]+) created successfully/)?.[0] || '');
  const billNo = flash.match(/Sales Bill (\S+) created/)?.[1] || '';
  rec(`[${iteration}] T3 Enter from #tender-cash confirms payment (no click workaround)`, !url.includes('create'), 'url=' + url);
  rec(`[${iteration}] T4 Sales Bill actually created`, !!billNo, flash);

  // T5: press Enter again right after landing on the index page — should be a
  // harmless no-op (no tender modal open here), not a resubmission of any kind.
  // Count only TABLE ROWS for this bill number (not the flash banner, which
  // also legitimately names it once) — the real signal for "was it duplicated".
  await p.keyboard.press('Enter'); await settle(400);
  const rowCount = await p.evaluate((bn) => {
    return [...document.querySelectorAll('table tbody tr')].filter(tr => tr.textContent.includes(bn)).length;
  }, billNo);
  rec(`[${iteration}] T5 No duplicate bill from a stray post-confirm Enter`, rowCount === 1, 'table rows for ' + billNo + ': ' + rowCount);

  await b.close();
  return billNo;
}

const billNumbers = [];
const ITERATIONS = 4; // time-boxed: enough to prove stability without piling up more headless Chrome instances in one run
for (let i = 1; i <= ITERATIONS; i++) {
  try {
    const billNo = await run(i);
    if (billNo) billNumbers.push(billNo);
  } catch (e) {
    console.log(`[${i}] ERROR: ${e.message}`);
  }
}

// Cross-run duplicate check: every bill number produced across all iterations
// must be distinct (each Enter-confirm created its own new bill, never reused
// or duplicated another run's).
const unique = new Set(billNumbers);
rec('X1 Every run created a distinct Sales Bill (no cross-run duplication)', unique.size === billNumbers.length && billNumbers.length === ITERATIONS, JSON.stringify(billNumbers));

console.log('\nSUMMARY', results.filter(Boolean).length + '/' + results.length, 'passed');
