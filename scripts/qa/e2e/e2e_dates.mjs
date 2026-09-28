import { chromium } from 'playwright-core';
const BASE='http://127.0.0.1:8299'; const results=[];
const b = await chromium.launch({ channel: 'chrome', headless: true }); const p = await b.newPage({ viewport:{width:1500,height:900}});
const dialogs=[]; p.on('dialog', async d=>{dialogs.push(d.message()); await d.dismiss();});
await p.goto(BASE+'/login'); await p.fill('input[name=email]','qa-load-1@example.com'); await p.fill('input[name=password]','secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
const rec=(n,ok,note)=>{results.push(ok); console.log(`${ok?'PASS':'FAIL'}  ${n}  -- ${note}`);};
const st = (sel)=>p.evaluate((sel)=>{const a=document.activeElement, el=document.querySelector(sel);
  const err=[...document.querySelectorAll('.date-feedback-error')].filter(e=>e.offsetParent!==null).map(e=>e.textContent.trim());
  return {activeName:a?(a.name||a.id||a.tagName):null, onField: a===el, invalid: el.classList.contains('is-invalid'), err, val: el.value};},sel);
for (const [url, field] of [['/sales/sales-returns/create','return_date'],['/purchase/purchase-invoices/create','invoice_date'],['/purchase/purchase-returns/create','return_date'],['/sales/sales-quotations/create','quotation_date']]) {
  await p.goto(BASE+url); await p.waitForLoadState('networkidle'); dialogs.length=0; await p.waitForSelector(`input[name=${field}].datepicker`,{timeout:8000}); await p.waitForTimeout(800);
  const sel=`input[name=${field}]`; const el=p.locator(sel);
  await el.click(); await p.keyboard.press('Control+A'); await el.pressSequentially('35/15/2026',{delay:20}); await el.press('Tab'); await p.waitForTimeout(500);
  let s=await st(sel);
  rec(`10a ${url} ${field}: invalid 35/15/2026 -> inline error shown`, s.err.length>0, JSON.stringify({err:s.err, val:s.val}));
  rec(`10b ${url} ${field}: Tab blocked (focus stays on date)`, s.onField, 'active='+s.activeName+' alerts='+JSON.stringify(dialogs));
  await p.screenshot({path:`shot_10_${field}_${url.split('/')[2]}.png`});
  await p.keyboard.press('Control+A'); await el.pressSequentially('25/09/2026',{delay:20}); await p.waitForTimeout(200);
  await el.press('Tab'); await p.waitForTimeout(500); s=await st(sel);
  rec(`11a ${url} ${field}: valid 25/09/2026 -> error gone`, s.err.length===0, JSON.stringify({err:s.err,val:s.val}));
  rec(`11b ${url} ${field}: Tab moves to next field`, !s.onField, 'active='+s.activeName);
}
console.log('\nSUMMARY', results.filter(Boolean).length+'/'+results.length,'passed'); await b.close();
