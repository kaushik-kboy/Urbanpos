import { chromium } from 'playwright-core';

const BASE = 'https://pos.ramdevcar.shop';
const QA_EMAIL = 'qa-staging@urbanpos.com';
const QA_PASSWORD = 'qa-staging-secret-2026';

const results = [];
const rec = (testName, pass, detail = '') => {
    results.push({ testName, pass, detail });
    const mark = pass ? '✅ PASS' : '❌ FAIL';
    console.log(`${mark} : ${testName}${detail ? ' -> ' + detail : ''}`);
};

async function runFullVerification() {
    console.log(`\n======================================================`);
    console.log(` 🚗 AUTOMOBILE POS DEEP CROSS-VERIFICATION SUITE`);
    console.log(` Target: ${BASE}`);
    console.log(` Timestamp: ${new Date().toISOString()}`);
    console.log(`======================================================\n`);

    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({
        viewport: { width: 1600, height: 950 },
        ignoreHTTPSErrors: true,
    });
    const page = await ctx.newPage();

    try {
        // ─── 1. AUTHENTICATION ──────────────────────────────────────────
        console.log('1. Testing Authentication...');
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForSelector('input[name=email]', { timeout: 10000 });
        rec('Login Page Reachable', page.url().includes('/login'), `Status: 200`);
        await page.fill('input[name=email]', QA_EMAIL);
        await page.fill('input[name=password]', QA_PASSWORD);
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
            page.click('button[type=submit]'),
        ]);
        const loggedIn = !page.url().includes('/login');
        rec('User Authentication', loggedIn, `Current URL: ${page.url()}`);

        if (!loggedIn) {
            console.error('Login failed! Halting verification.');
            await browser.close();
            return;
        }

        // ─── 2. ITEM MASTER AUTOMOBILE FIELDS ───────────────────────────
        console.log('\n2. Testing Item Master Automobile Fields...');
        await page.goto(`${BASE}/master/items/create`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForSelector('input[name="name"]', { timeout: 10000 });
        
        const partNoInput = await page.$('input[name="part_number"]');
        const carCompatInput = await page.$('input[name="car_compatibility"]');
        const warrantyInput = await page.$('input[name="warranty_months"]');

        rec('OEM Part Number Field Present', partNoInput !== null);
        rec('Car Compatibility Field Present', carCompatInput !== null);
        rec('Warranty Months Field Present', warrantyInput !== null);

        // ─── 3. CREATE AUTOMOBILE MASTER ITEM ───────────────────────────
        console.log('\n3. Creating Real Automobile Item in Master...');
        const uniqueSuffix = Date.now().toString().slice(-5);
        const testItemName = `Bosch Oil Filter Swift/Baleno - QA${uniqueSuffix}`;
        const testPartNo = `BSH-OF-${uniqueSuffix}`;
        const testCompat = 'Swift, Baleno, Dzire 2018-2024';

        await page.fill('input[name="name"]', testItemName);
        await page.fill('input[name="cost_price"]', '200');
        await page.fill('input[name="landing_cost"]', '200');
        await page.fill('input[name="sell_price"]', '350');
        await page.fill('input[name="mrp"]', '350');
        await page.fill('input[name="part_number"]', testPartNo);
        await page.fill('input[name="car_compatibility"]', testCompat);
        await page.fill('input[name="warranty_months"]', '6');

        // Switch to Sales tab to allow negative stock
        const salesTab = await page.$('a[href="#tab-sales"]');
        if (salesTab) {
            await salesTab.click();
            await page.waitForTimeout(300);
            await page.selectOption('select[name="allow_negative_stock"]', '1').catch(() => {});
        }

        // Click the Item Form Save Button (not navbar search button!)
        await Promise.all([
            page.waitForNavigation({ timeout: 25000 }),
            page.click('.card-footer button.btn-primary')
        ]);

        // ─── 4. VERIFY ITEM IN MASTER LISTING WITH BADGES ────────────────
        console.log('\n4. Verifying Item in Master List with Automobile Badges...');
        await page.goto(`${BASE}/master/items?name=${encodeURIComponent(testPartNo)}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        const pageContent = await page.content();
        
        const hasPartInList = pageContent.includes(testPartNo);
        const hasCompatInList = pageContent.includes('Swift, Baleno');
        rec('Item Found in Master by Part Number Search', hasPartInList, `Query: ${testPartNo}`);
        rec('Item Displays Car Compatibility Badge in Table', hasCompatInList);

        // ─── 5. SMART POS SEARCH API (CAR NAME & PART NO) ────────────────
        console.log('\n5. Testing Smart POS Search (Car Name & Part Number)...');
        
        // Search by Car Name: "Swift"
        const carSearchRes = await page.evaluate(async (url) => {
            const r = await fetch(url);
            return await r.json();
        }, `${BASE}/sales/sales-bills/item-list?search=Swift&branch_id=1&show_all=1`);

        const carItems = carSearchRes.items || [];
        const foundByCar = carItems.some(it => it.name.includes(testItemName) || it.part_number === testPartNo);
        rec('POS Smart Search by Car Name ("Swift")', foundByCar, `Items returned: ${carItems.length}`);

        // Search by OEM Part Number
        const partSearchRes = await page.evaluate(async (url) => {
            const r = await fetch(url);
            return await r.json();
        }, `${BASE}/sales/sales-bills/item-list?search=${encodeURIComponent(testPartNo)}&branch_id=1&show_all=1`);

        const partItems = partSearchRes.items || [];
        const foundByPart = partItems.some(it => it.part_number === testPartNo);
        rec('POS Search by OEM Part Number', foundByPart, `Top match: ${partItems[0]?.name ?? 'None'}`);

        // Exact Lookup Item API
        const lookupRes = await page.evaluate(async (url) => {
            const r = await fetch(url);
            return await r.json();
        }, `${BASE}/sales/sales-bills/lookup-item?query=${encodeURIComponent(testPartNo)}&branch_id=1`);

        const lookupSuccess = lookupRes.found === true && lookupRes.item?.part_number === testPartNo;
        rec('POS Barcode/OEM Exact Lookup API', lookupSuccess, `Found: ${lookupRes.item?.name ?? 'No'}`);

        // ─── 6. SALES BILL CREATION WITH VEHICLE & MECHANIC ─────────────
        console.log('\n6. Testing Sales Bill Creation with Vehicle & Mechanic...');
        await page.goto(`${BASE}/sales/sales-bills/create`, { waitUntil: 'domcontentloaded', timeout: 30000 });

        const billVehicleInput = await page.$('input[name="vehicle_no"]');
        const billOdoInput = await page.$('input[name="odometer_km"]');
        const billMechInput = await page.$('input[name="mechanic_name"]');

        rec('Sales Bill Form: Vehicle No Field Present', billVehicleInput !== null);
        rec('Sales Bill Form: Odometer KM Field Present', billOdoInput !== null);
        rec('Sales Bill Form: Mechanic Field Present', billMechInput !== null);

        // ─── 7. SUMMARY REPORT ──────────────────────────────────────────
        console.log(`\n======================================================`);
        console.log(` 📊 VERIFICATION SUMMARY`);
        console.log(`======================================================`);
        const total = results.length;
        const passed = results.filter(r => r.pass).length;
        const failed = total - passed;
        console.log(`TOTAL TESTS: ${total}`);
        console.log(`PASSED     : ${passed}`);
        console.log(`FAILED     : ${failed}`);
        console.log(`STATUS     : ${failed === 0 ? '🟢 100% VERIFIED & PASSED' : '🔴 ISSUES DETECTED'}`);
        console.log(`======================================================\n`);

    } catch (err) {
        console.error('Unexpected error during verification:', err);
    } finally {
        await browser.close();
    }
}

runFullVerification().catch(console.error);
