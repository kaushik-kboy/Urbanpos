import { chromium } from 'playwright-core';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({
        viewport: { width: 1400, height: 900 },
        ignoreHTTPSErrors: true,
    });
    const page = await ctx.newPage();

    console.log('1. Logging in...');
    await page.goto('http://127.0.0.1:8299/login');
    await page.fill('#email', 'admin@urbanpets.test');
    await page.fill('#password', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]')
    ]);

    console.log('2. Navigating to Purchase Invoices Create...');
    await page.goto('http://127.0.0.1:8299/purchase/purchase-invoices/create', { waitUntil: 'networkidle' });

    console.log('3. Selecting supplier Mars Petcare (ID: 3)...');
    await page.selectOption('#supplier_id', '3');
    await page.waitForTimeout(1000);

    // Check if PO items are NOT yet loaded (table should have only 1 empty row or 0 items)
    const rowCountAfterSupp = await page.locator('#pinv-items-body tr').count();
    const firstCodeValAfterSupp = await page.locator('#pinv-items-body tr:first-child .pinv-item-code').inputValue().catch(() => '');
    console.log(`   Rows after supplier select: ${rowCountAfterSupp}, First code: "${firstCodeValAfterSupp}"`);
    if (firstCodeValAfterSupp !== '') {
        console.error('ERROR: Items should NOT load until user selects PO!');
    } else {
        console.log('   SUCCESS: Items did NOT auto-load when supplier was selected.');
    }

    // Now select the PO explicitly
    console.log('4. Now explicitly selecting PO (ID: 2)...');
    await page.selectOption('#purchase_order_id', '2');
    await page.waitForTimeout(1500);

    const rowCountAfterPo = await page.locator('#pinv-items-body tr').count();
    const firstCodeValAfterPo = await page.locator('#pinv-items-body tr:first-child .pinv-item-code').inputValue();
    const firstExpValAfterPo = await page.locator('#pinv-items-body tr:first-child .pinv-exp-date').inputValue();
    console.log(`   Rows after PO select: ${rowCountAfterPo}`);
    console.log(`   First item code: "${firstCodeValAfterPo}" (Must be item ID without '#')`);
    console.log(`   First item expiry: "${firstExpValAfterPo}"`);

    if (firstCodeValAfterPo.startsWith('#')) {
        console.error('ERROR: Item code starts with #!');
    } else {
        console.log('   SUCCESS: Item code does NOT start with #, it is pure item ID.');
    }

    // Test Item Search / Lookup directly to confirm exp date is not set by default
    console.log('5. Testing direct item code entry on a new row...');
    await page.click('#pinv-add-row');
    await page.waitForTimeout(500);
    const lastRowCode = page.locator('#pinv-items-body tr:last-child .pinv-item-code');
    await lastRowCode.fill('RC-MAXI-15KG');
    await lastRowCode.press('Enter');
    await page.waitForTimeout(1000);

    const enteredCodeVal = await lastRowCode.inputValue();
    const enteredExpVal = await page.locator('#pinv-items-body tr:last-child .pinv-exp-date').inputValue();
    console.log(`   After entering code: Code="${enteredCodeVal}", ExpDate="${enteredExpVal}"`);

    if (enteredExpVal !== '') {
        console.error('ERROR: Expiry date should NOT be auto-filled by default!');
    } else {
        console.log('   SUCCESS: Expiry date is empty by default as requested.');
    }

    await page.screenshot({ path: 'pinv_verified_all_reqs.png' });
    console.log('Screenshot saved to pinv_verified_all_reqs.png');
    await browser.close();
})();
