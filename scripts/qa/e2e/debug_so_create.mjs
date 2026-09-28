import { chromium } from 'playwright-core';

const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });

p.on('console', msg => console.log('BROWSER LOG:', msg.text()));
p.on('dialog', async d => {
    console.log('BROWSER DIALOG:', d.type(), d.message());
    await d.dismiss();
});

await p.goto('http://127.0.0.1:8299/login');
await p.fill('input[name=email]', 'admin@urbanpets.test');
await p.fill('input[name=password]', 'password');
await p.click('.login-card-body button[type=submit]');
await p.waitForURL(url => !url.pathname.includes('/login'));

await p.goto('http://127.0.0.1:8299/sales/sales-orders/create');
await p.waitForLoadState('networkidle');

// 1. Pick customer
await p.evaluate(() => {
    const sel = document.querySelector('select[name=customer_id]');
    sel.value = sel.options[1].value;
    $(sel).trigger('change');
});
console.log('Customer selected. Val =', await p.evaluate(() => $('select[name=customer_id]').val()));

// 2. Open item search modal
await p.locator('#so-items-body tr:first-child .so-item-code').focus();
await p.keyboard.press('Enter');
await p.waitForSelector('#so-item-search-modal.show');
console.log('Modal opened');

// 3. Search and select item
await p.fill('#so-isl-filter-name', 'Royal Canin');
await p.waitForSelector('#so-isl-items-body tr .so-isl-btn-select');
await p.waitForTimeout(300);
await p.click('#so-isl-items-body tr:first-child .so-isl-btn-select');
await p.waitForTimeout(500);

// 4. Fill Qty
await p.locator('#so-items-body tr:first-child .so-qty').fill('2');
await p.locator('#so-items-body tr:first-child .so-qty').dispatchEvent('change');
await p.waitForTimeout(300);

// Check form state before clicking Save
const state = await p.evaluate(() => {
    const btn = document.querySelector('#so-form button[type=submit]');
    const form = document.querySelector('#so-form');
    const inputs = Array.from(form.querySelectorAll('input, select')).map(el => ({
        name: el.name,
        value: el.value,
        type: el.type
    }));
    return {
        btnDisabled: btn?.disabled,
        btnClasses: btn?.className,
        btnTitle: btn?.title,
        inputs: inputs
    };
});
console.log('STATE BEFORE SUBMIT:', JSON.stringify(state, null, 2));

// 5. Click Save
console.log('Clicking save...');
await p.click('#so-form button[type=submit]');
await p.waitForTimeout(3000);
console.log('URL AFTER CLICK:', p.url());

// If still on create, inspect errors on page
const pageText = await p.evaluate(() => document.body.innerText);
console.log('PAGE TEXT SNIPPET:\n', pageText.substring(0, 1000));

await b.close();
