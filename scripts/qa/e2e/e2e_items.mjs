import { chromium } from 'playwright-core';
const BASE = 'http://127.0.0.1:8299';
const results = [];
const b = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await b.newContext({ viewport: { width: 1600, height: 950 } });
const p = await ctx.newPage();
const dialogs = []; p.on('dialog', async d => { dialogs.push(d.message()); await d.dismiss(); });
async function login() {
  await p.goto(BASE + '/login');
  await p.fill('input[name=email]', 'qa-load-1@example.com'); await p.fill('input[name=password]', 'secret123');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
}
async function fresh() {
  await p.goto(BASE + '/sales/sales-bills/create'); await p.waitForLoadState('networkidle');
  await p.evaluate(() => { try { localStorage.clear(); sessionStorage.clear(); } catch {} });
  await p.reload(); await p.waitForLoadState('networkidle');
  dialogs.length = 0;
  const code = p.locator('#sb-items-body tr:first-child .sb-item-code'); await code.click(); return code;
}
const state = () => p.evaluate(() => {
  const a = document.activeElement; const row = document.querySelector('#sb-items-body tr');
  const modal = [...document.querySelectorAll('.modal.show')].map(m => m.id || m.className.slice(0, 40));
  const vis = (e) => e && e.offsetParent !== null;
  return {
    active: a ? (a.className || a.tagName).toString().slice(0, 60) : null,
    activeIsQty: !!a && a.classList.contains('sb-qty'),
    modalOpen: modal.length > 0, modals: modal,
    desc: row?.querySelector('.sb-item-desc')?.value || '',
    code: row?.querySelector('.sb-item-code')?.value || '',
    codeInvalid: row?.querySelector('.sb-item-code')?.classList.contains('is-invalid') || false,
    qty: row?.querySelector('.sb-qty')?.value || '',
    qtyErr: [...document.querySelectorAll('.sb-qty-error-msg')].filter(vis).map(e => e.textContent.trim()),
    toasts: [...document.querySelectorAll('#toast-container .toast-message, .toast-message')].map(e => e.textContent.trim()),
    inlineNearCode: [...(row?.querySelectorAll('.invalid-feedback, .text-danger, .sb-code-error-msg, .sb-item-error-msg, [class*=error]') || [])].filter(vis).map(e => e.textContent.trim()).filter(Boolean),
  };
});
const rec = (n, ok, note) => { results.push({ n, ok, note }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`); };
const settle = (ms = 700) => p.waitForTimeout(ms);
const shot = (name) => p.screenshot({ path: `shot_${name}.png` });

await login();

// 1
let code = await fresh(); await code.press('Enter'); await settle(); let s = await state();
rec('1 Empty item + Enter opens Item popup', s.modalOpen, JSON.stringify({ modals: s.modals })); await shot('1_enter_popup');
await p.keyboard.press('Escape'); await settle(400);
// 2
code = await fresh(); await code.press('Tab'); await settle(); s = await state();
rec('2 Empty item + Tab opens Item popup', s.modalOpen, JSON.stringify({ modals: s.modals })); await shot('2_tab_popup');
await p.keyboard.press('Escape'); await settle(400);
// 3
code = await fresh(); await code.fill('LOADHOT0001'); await code.press('Enter'); await settle(1200); s = await state();
rec('3 Valid item code + Enter -> direct load, NO popup', !s.modalOpen && s.desc.includes('Load Hot Item 1'), JSON.stringify({ modalOpen: s.modalOpen, desc: s.desc }));
rec('5a Qty field focused after code lookup', s.activeIsQty, 'active=' + s.active); await shot('3_valid_code');
// 4
code = await fresh(); await code.fill('8801000000002'); await code.press('Enter'); await settle(1200); s = await state();
rec('4 Valid barcode + Enter -> direct load, NO popup', !s.modalOpen && s.desc.includes('Load Hot Item 2'), JSON.stringify({ modalOpen: s.modalOpen, desc: s.desc }));
rec('5b Qty focused after barcode lookup', s.activeIsQty, 'active=' + s.active);
// 4b scanner-style: type fast + Enter (no Tab)
code = await fresh(); await code.pressSequentially('8801000000003', { delay: 5 }); await code.press('Enter'); await settle(1200); s = await state();
rec('4b Scanner-style burst typing + Enter -> direct load, NO popup', !s.modalOpen && s.desc.includes('Load Hot Item 3'), JSON.stringify({ modalOpen: s.modalOpen, desc: s.desc }));
// 6
code = await fresh(); await code.fill('ZZ-NOPE-999'); await code.press('Enter'); await settle(1200); s = await state();
rec('6a Invalid code: no wrong product selected', s.desc === '' , JSON.stringify({ desc: s.desc }));
rec('6b Invalid code: INLINE error message next to field (not toast/alert)', s.inlineNearCode.length > 0, JSON.stringify({ inline: s.inlineNearCode, toasts: s.toasts, alerts: dialogs, codeInvalidClass: s.codeInvalid }));
rec('6c Invalid code: user stays on the code field', s.active.includes('sb-item-code'), 'active=' + s.active); await shot('6_invalid_code');
// 7
code = await fresh(); await code.fill('E2ELIM01'); await code.press('Enter'); await settle(1200);
const qty = p.locator('#sb-items-body tr:first-child .sb-qty');
await qty.fill('11'); await qty.dispatchEvent('input'); await qty.dispatchEvent('change'); await settle(500); s = await state();
rec('7 Qty 11 > stock 10 -> inline error', s.qtyErr.length > 0, JSON.stringify({ qtyErr: s.qtyErr })); await shot('7_qty_over');
// 8
await qty.focus(); await qty.press('Tab'); await settle(500); s = await state();
rec('8 Invalid qty: Tab does NOT move to next field', s.activeIsQty, 'active=' + s.active);
// 9
await qty.fill('5'); await qty.dispatchEvent('input'); await qty.dispatchEvent('change'); await settle(500); s = await state();
rec('9a Correct qty 5 -> error disappears', s.qtyErr.length === 0, JSON.stringify({ qtyErr: s.qtyErr }));
await qty.focus(); await qty.press('Tab'); await settle(500); s = await state();
rec('9b Correct qty: Tab moves to next field', !s.activeIsQty, 'active=' + s.active); await shot('9_qty_ok');

console.log('\nSUMMARY', results.filter(r => r.ok).length + '/' + results.length, 'passed');
await b.close();
