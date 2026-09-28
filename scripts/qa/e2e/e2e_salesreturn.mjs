import { chromium } from 'playwright-core';
const BASE='http://127.0.0.1:8299'; const results=[];
const b = await chromium.launch({ channel: 'chrome', headless: true }); const p = await b.newPage({ viewport:{width:1600,height:1000}});
const dialogs=[]; p.on('dialog', async d=>{dialogs.push(d.message()); await d.dismiss();});
const rec=(n,ok,note)=>{results.push(ok); console.log(`${ok?'PASS':'FAIL'}  ${n}  -- ${note}`);};
const settle=(ms=800)=>p.waitForTimeout(ms);
await p.goto(BASE+'/login'); await p.fill('input[name=email]','qa-load-owner@example.com'); await p.fill('input[name=password]','secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

async function openForm() {
  await p.goto(BASE+'/sales/sales-returns/create'); await p.waitForLoadState('networkidle'); await settle(600);
  // customer via select2 (search by exact customer code)
  await p.click('#select2-customer_id-container'); await p.keyboard.type('C0017670', {delay:30}); await p.waitForSelector('.select2-results__option:not(.loading-results)', {timeout:8000}); await settle(500);
  await p.keyboard.press('Enter'); await settle(1500);
  // bill via select2
  await p.click('#select2-sales_bill_id-container'); await settle(600);
  const opts = await p.$$eval('.select2-results__option', l=>l.map(e=>e.textContent.trim()));
  return opts;
}
const rows = () => p.evaluate(()=>[...document.querySelectorAll('#sr-items-body .sr-item-row')].filter(r=>r.querySelector('.sr-item-select')?.value).map(r=>({item:r.querySelector('.sr-item-select').value, desc:(r.querySelector('.sr-item-desc')?.value||'').slice(0,40), qty:r.querySelector('.sr-qty')?.value, max:r.querySelector('.sr-qty')?.getAttribute('max')})));

let opts = await openForm();
console.log('bill options:', JSON.stringify(opts.slice(0,6)));
const target = opts.find(o=>o.includes('QB-00250004'));
rec('A0 customer\'s bills are listed after picking the customer', !!target, 'options='+opts.length);
await p.locator('.select2-results__option', {hasText:'QB-00250004'}).first().click(); await settle(2000);
let r = await rows();
rec('A1 Selecting the bill does NOT auto-add items to Return Items', r.length===0, 'rows='+JSON.stringify(r));
const picker = await p.$$eval('#sr-bill-item-checklist .sr-bill-item-checkbox', l=>l.map(cb=>({idx:cb.getAttribute('data-idx'),t:cb.closest('.custom-control').querySelector('label').textContent.trim().replace(/\s+/g,' ').slice(0,90),dis:cb.disabled})));
rec('A2 "Select item(s) from bill" checklist lists all 3 bill items with Remaining/Sold', picker.length===3 && picker.every(o=>/Remaining:/.test(o.t)), JSON.stringify(picker));
await p.screenshot({path:'shot_sr_1_bill_selected.png'});
// Read item 0's CURRENT remaining qty dynamically rather than assuming a fresh
// "2/2" state — this fixture gets consumed a little further by every run.
const farminaBefore = parseFloat(picker[0].t.match(/Remaining: (\d+)/)?.[1] ?? 'NaN');
rec('A3 Item 0 (Farmina) has a positive remaining qty to return', farminaBefore > 0, 'remaining=' + farminaBefore);

// explicit check of ONE item's checkbox (idx 0), via its LABEL — Bootstrap's
// custom-checkbox visually hides the raw <input>, so a real user (and a real
// click) always lands on the label, not the input itself.
await p.locator('label[for="sr-bic-0"]').click(); await settle(1200);
r = await rows();
rec('B1 Only the explicitly selected item is in Return Items', r.length===1 && r[0].desc.includes('Farmina'), JSON.stringify(r));
rec('B2 Quantity defaults to the current remaining qty', parseFloat(r[0]?.qty)===farminaBefore && parseFloat(r[0]?.max)===farminaBefore, `qty=${r[0]?.qty} max=${r[0]?.max} expected=${farminaBefore}`);
const qty = p.locator('#sr-items-body .sr-item-row .sr-qty').first();
const overQty = farminaBefore + 1;
// Return everything but 1 when there's more than 1 remaining, so the "partial
// return" and its "remaining decreases by exactly 1" check below still make
// sense regardless of what this fixture's current remaining qty happens to be.
const submitQty = farminaBefore > 1 ? farminaBefore - 1 : farminaBefore;
const expectedRemainingAfter = farminaBefore - submitQty;

await qty.fill(String(overQty)); await qty.dispatchEvent('input'); await qty.dispatchEvent('change'); await settle(700);
const errs = await p.evaluate(()=>[...document.querySelectorAll('#sr-items-body .invalid-feedback, #sr-items-body .text-danger, #sr-items-body [class*=error]')].filter(e=>e.offsetParent!==null).map(e=>e.textContent.trim()).filter(Boolean));
rec(`C1 Qty ${overQty} > remaining ${farminaBefore} -> inline error`, errs.length>0, JSON.stringify(errs)+' alerts='+JSON.stringify(dialogs));
await qty.focus(); await qty.press('Tab'); await settle(500);
const stay = await p.evaluate(()=>document.activeElement.classList.contains('sr-qty'));
rec('C2 Invalid return qty: Tab does not move on', stay, '');
await p.screenshot({path:'shot_sr_2_over_qty.png'});
await qty.fill(String(submitQty)); await qty.dispatchEvent('input'); await qty.dispatchEvent('change'); await settle(600);
const errs2 = await p.evaluate(()=>[...document.querySelectorAll('#sr-items-body .invalid-feedback, #sr-items-body .text-danger, #sr-items-body [class*=error]')].filter(e=>e.offsetParent!==null).map(e=>e.textContent.trim()).filter(Boolean));
rec(`C3 Valid qty ${submitQty} -> error disappears`, errs2.length===0, JSON.stringify(errs2));

// submit the return
p.on('response', async r => { if (r.request().method()==='POST') console.log('   POST', r.url().slice(-40), r.status(), (r.headers()['location']||'')); });
await p.locator('#sr-form button[type=submit]').click(); await p.waitForLoadState('networkidle'); await settle(1000);
console.log('   PAGE:', (await p.evaluate(()=>document.body.innerText)).replace(/\s+/g,' ').slice(0,400)); const url = p.url(); const flash = await p.evaluate(()=>document.body.innerText.match(/Sales Return [^\s]+ created successfully/)?.[0]||'');
rec('D1 Return saved', /sales-returns/.test(url) && !!flash, url+' '+flash);

// reopen same bill: remaining must now have decreased by exactly submitQty, others unchanged
opts = await openForm();
await p.locator('.select2-results__option', {hasText:'QB-00250004'}).first().click(); await settle(2000);
const picker2 = await p.$$eval('#sr-bill-item-checklist label', l=>l.map(o=>o.textContent.trim().replace(/\s+/g,' ')));
rec(`E1 Remaining decreased by exactly ${submitQty} (Farmina -> Remaining: ${expectedRemainingAfter}/2)`, picker2.some(t=>/Farmina/.test(t)&&new RegExp('Remaining: '+expectedRemainingAfter+'/').test(t)), JSON.stringify(picker2));
rec('E2 Other items untouched (Remaining 3/3 and 4/4)', picker2.some(t=>/Remaining: 3\/3/.test(t)) && picker2.some(t=>/Remaining: 4\/4/.test(t)), '');
await p.screenshot({path:'shot_sr_3_after_partial.png'});
console.log('\nSUMMARY', results.filter(Boolean).length+'/'+results.length,'passed'); await b.close();
