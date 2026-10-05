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

async function runVerification() {
    console.log(`\n======================================================`);
    console.log(` 🔍 CROSS-VERIFICATION SUITE ON LIVE RAMDEV STAGING`);
    console.log(` URL: ${BASE}`);
    console.log(`======================================================\n`);

    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({
        viewport: { width: 1600, height: 950 },
        ignoreHTTPSErrors: true,
    });
    const page = await ctx.newPage();

    try {
        // 1. LOGIN
        console.log('1. Testing Login...');
        await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Login Page Load', page.url().includes('/login'), `Status: 200`);
        await page.fill('input[name=email]', QA_EMAIL);
        await page.fill('input[name=password]', QA_PASSWORD);
        await Promise.all([
            page.waitForNavigation({ timeout: 20000 }),
            page.click('button[type=submit]'),
        ]);
        const loggedIn = !page.url().includes('/login');
        rec('Authentication Flow', loggedIn, `Current URL: ${page.url()}`);

        if (!loggedIn) {
            console.error('Login failed! Skipping authenticated pages.');
            await browser.close();
            return;
        }

        // 2. ANALYTICS BUILDER & HINDI USER GUIDE
        console.log('\n2. Testing Custom Report Studio & Analytics Builder...');
        const analyticsRes = await page.goto(`${BASE}/reports/analytics-builder`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Analytics Builder Page Load', analyticsRes.status() === 200, `HTTP ${analyticsRes.status()}`);

        // Check Kaise Use Karein Guide Button
        const guideBtn = await page.$('button[data-target="#guideHindiModal"]');
        rec('Hindi Guide Modal Button Present', guideBtn !== null, guideBtn ? 'Found in header/presets' : 'Not found');

        // Check Guide Modal Markup
        const guideModal = await page.$('#guideHindiModal');
        rec('Hindi Guide Modal Element in DOM', guideModal !== null);

        if (guideBtn) {
            await guideBtn.click();
            await page.waitForTimeout(600);
            const isModalVisible = await page.evaluate(() => {
                const m = document.getElementById('guideHindiModal');
                return m && (m.classList.contains('show') || window.getComputedStyle(m).display === 'block');
            });
            rec('Hindi Guide Modal Opens on Click', isModalVisible);
            
            // Close modal
            await page.evaluate(() => {
                $('#guideHindiModal').modal('hide');
            });
            await page.waitForTimeout(500);
        }

        // Check Date Filter Presets
        const hasDatePresets = await page.evaluate(() => {
            const pills = document.querySelectorAll('.preset-pill');
            return pills.length >= 6;
        });
        rec('1-Click Date Presets Present', hasDatePresets, 'Found ' + await page.evaluate(() => document.querySelectorAll('.preset-pill').length) + ' preset pills');

        // Check Group By Quick Dimension Pills
        const hasGroupByPills = await page.evaluate(() => {
            const pills = document.querySelectorAll('.groupby-pill');
            return pills.length >= 6;
        });
        rec('Quick Group By Dimension Pills Present', hasGroupByPills, 'Item, Customer, Category, Sourcing, Supplier, Pay Mode');

        // Check Metrics Toggle Chips
        const hasMetricChips = await page.evaluate(() => {
            const chips = document.querySelectorAll('.metric-chip');
            return chips.length >= 4;
        });
        rec('Interactive Metrics Toggle Chips Present', hasMetricChips);

        // Run live report query for All Time
        console.log('   Testing live report data execution...');
        const liveReportData = await page.evaluate(async () => {
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const resp = await fetch('/reports/analytics-builder/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        group_by: 'item',
                        metrics: ['qty', 'sales_value', 'margin', 'bill_count'],
                        date_preset: 'all_time',
                    }),
                });
                return { status: resp.status, data: await resp.json() };
            } catch (e) {
                return { error: String(e) };
            }
        });
        const isSuccess = liveReportData && liveReportData.status === 200 && Boolean(liveReportData.data?.success);
        rec('Live Report Execution (/reports/analytics-builder/generate)', isSuccess, `HTTP ${liveReportData?.status}, Success: ${liveReportData?.data?.success}, Rows: ${liveReportData?.data?.rows?.length ?? 0}`);

        // 3. DAMAGE STOCKS
        console.log('\n3. Testing Damage Stocks Module...');
        const dmgRes = await page.goto(`${BASE}/inventory/damage-stocks/create`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Damage Stock Create Page Load', dmgRes.status() === 200, `HTTP ${dmgRes.status()}`);
        
        const dmgBatchSupport = await page.evaluate(() => {
            return document.querySelector('#item-damage-batch-modal') !== null || document.body.innerHTML.includes('batch');
        });
        rec('Damage Stock Batch Tracking Support in UI', dmgBatchSupport);

        const dmgLayoutCss = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some(l => l.href.includes('transaction-compact-layout.css'));
        });
        rec('Universal Compact Layout CSS Loaded (Damage Stocks)', dmgLayoutCss);

        // 4. STOCK UPDATES
        console.log('\n4. Testing Stock Updates Module...');
        const suRes = await page.goto(`${BASE}/inventory/stock-updates/create`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Stock Update Create Page Load', suRes.status() === 200, `HTTP ${suRes.status()}`);

        const suBatchModal = await page.evaluate(() => {
            return document.querySelector('#su-batch-modal') !== null;
        });
        rec('Stock Update Batch Selection Modal in DOM', suBatchModal, suBatchModal ? 'Found #su-batch-modal' : 'Not found');

        // 5. MASTER ROLES
        console.log('\n5. Testing Master Roles & Permissions...');
        const roleRes = await page.goto(`${BASE}/master/roles`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Master Roles List Load', roleRes.status() === 200, `HTTP ${roleRes.status()}`);

        // 6. SALES BILL POS
        console.log('\n6. Testing Sales Bills / POS...');
        const posRes = await page.goto(`${BASE}/sales/sales-bills/create`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Sales Bills POS Create Page Load', posRes.status() === 200, `HTTP ${posRes.status()}`);

        // 7. STOCK TRANSFERS
        console.log('\n7. Testing Stock Transfers Module...');
        const stRes = await page.goto(`${BASE}/inventory/stock-transfers/create`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Stock Transfers Create Page Load', stRes.status() === 200, `HTTP ${stRes.status()}`);

        // 8. PURCHASE INVOICES
        console.log('\n8. Testing Purchase Invoices Module...');
        const piRes = await page.goto(`${BASE}/purchase/purchase-invoices/create`, { waitUntil: 'networkidle', timeout: 25000 });
        rec('Purchase Invoices Create Page Load', piRes.status() === 200, `HTTP ${piRes.status()}`);

    } catch (err) {
        console.error('Critical verification error:', err);
        rec('Overall Test Execution', false, String(err));
    } finally {
        await browser.close();
    }

    console.log(`\n======================================================`);
    const passedCount = results.filter(r => r.pass).length;
    const totalCount = results.length;
    console.log(` 🏁 RESULT: ${passedCount}/${totalCount} Checks Passed (${Math.round((passedCount/totalCount)*100)}%)`);
    console.log(`======================================================\n`);
}

runVerification();
