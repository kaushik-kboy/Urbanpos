import { chromium } from 'playwright-core';

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({
        viewport: { width: 1400, height: 900 },
        ignoreHTTPSErrors: true,
    });
    const page = await ctx.newPage();

    await page.goto('http://127.0.0.1:8299/login');
    await page.fill('#email', 'admin@urbanpets.test');
    await page.fill('#password', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]')
    ]);

    await page.goto('http://127.0.0.1:8299/purchase/purchase-invoices/create', { waitUntil: 'networkidle' });
    
    // Select supplier 3 to auto-load PO items
    await page.selectOption('#supplier_id', '3');
    await page.waitForTimeout(1500);

    await page.screenshot({ path: 'pinv_compact_verified.png' });
    console.log('Saved screenshot to pinv_compact_verified.png');
    await browser.close();
})();
