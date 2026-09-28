import { chromium } from 'playwright-core';
const BASE='http://127.0.0.1:8299'; const res=[];
const b = await chromium.launch({ channel: 'chrome', headless: true }); const p = await b.newPage({ viewport:{width:1600,height:1000}});
const dialogs=[]; p.on('dialog', async d=>{dialogs.push(d.message()); await d.dismiss();});
const rec=(n,ok,note)=>{res.push(ok); console.log(`${ok?'PASS':'FAIL'}  ${n}  -- ${note}`);}; const settle=(ms=800)=>p.waitForTimeout(ms);
await p.goto(BASE+'/login'); await p.fill('input[name=email]','qa-load-owner@example.com'); await p.fill('input[name=password]','secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
const QTY = 'input[name$="[qty]"]';
async function startNoBill() {
  await p.goto(BASE+'/sales/sales-returns/create'); await p.waitForLoadState('networkidle'); await settle(600);
  await p.click('#select2-customer_id-container'); await p.keyboard.type('C0058472',{delay:30}); await p.waitForSelector('.select2-results__option:not(.loading-results)'); await settle(500); await p.keyboard.press('Enter'); await settle(1500);
  if (!(await p.locator('#sr-item-search-modal.show').count())) await p.click('#sr-add-row');
  await settle(800);
  await p.fill('#sr-isl-filter-code','QAI0004364'); await settle(1800);
  await p.locator('#sr-item-search-modal tbody tr').first().click(); await settle(1800);
}
const rowState = () => p.evaluate((Q)=>{const r=[...document.querySelectorAll('#sr-items-body tr')].filter(t=>t.querySelector(Q)).pop();
  return {desc:r?.querySelector('.sr-item-desc')?.value, qty:r?.querySelector(Q)?.value, invalid:r?.querySelector(Q)?.classList.contains('is-invalid'), label:r?.querySelector('.sr-max-qty-label')?.textContent.trim(), red:r?.querySelector('.sr-max-qty-label')?.classList.contains('text-danger')};}, QTY);
const setQty = async (v) => { const q=p.locator('#sr-items-body tr '+QTY).last(); await q.fill(String(v)); await q.dispatchEvent('input'); await q.dispatchEvent('change'); await settle(1500); };

await startNoBill();
let st = await rowState();
rec('F1 No-bill: customer\'s purchased item can be picked (even if out of stock here)', /Farmina|Toy|Chew|Trixie|Sheba|Royal|Pedigree|Whiskas|Drools|Purina|Me-O|Himalaya|Kong|Sheba|Orijen/.test(st.desc||'') || !!st.desc, JSON.stringify(st));
await setQty(4);                                    // customer bought only 3
st = await rowState();
rec('F2 No-bill: qty 4 > pool 3 -> inline error, value NOT silently changed', st.red && /Maximum returnable quantity is 3/.test(st.label||'') && st.qty==='4', JSON.stringify(st)+' dialogs='+JSON.stringify(dialogs));
await p.screenshot({path:'shot_sr_5_nobill_over.png'});
await p.locator('#sr-form button[type=submit]').click(); await p.waitForLoadState('networkidle'); await settle(1000);
let txt = await p.evaluate(()=>[...document.querySelectorAll('.alert, .invalid-feedback, .text-danger')].filter(e=>e.offsetParent!==null).map(e=>e.textContent.trim()).join(' | '));
rec('F3 Over-pool no-bill submit is NOT saved (server rule)', !/created successfully/.test(await p.evaluate(()=>document.body.innerText)), txt.slice(0,220));
await startNoBill(); await setQty(3);
st = await rowState();
rec('F4 qty 3 (= pool) -> error cleared', !st.invalid, JSON.stringify(st));
await p.locator('#sr-form button[type=submit]').click(); await p.waitForLoadState('networkidle'); await settle(1000);
const okTxt = await p.evaluate(()=>document.body.innerText.match(/Sales Return [^\s]+ created successfully/)?.[0]||'');
rec('F5 No-bill return of exactly the pool (3) is saved', !!okTxt, okTxt);
await startNoBill(); await setQty(1);
st = await rowState();
rec('F6 Pool now exhausted: qty 1 -> inline error "Maximum returnable quantity is 0"', st.red && /Maximum returnable quantity is 0/.test(st.label||''), JSON.stringify(st));
console.log('\nSUMMARY', res.filter(Boolean).length+'/'+res.length); await b.close();
