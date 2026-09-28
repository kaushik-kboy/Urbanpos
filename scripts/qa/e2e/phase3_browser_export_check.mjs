import { chromium } from 'playwright-core';
import fs from 'fs';
import path from 'path';

const BASE_URL = 'http://127.0.0.1:8299';

async function main() {
    console.log('================================================================');
    console.log('UrbanPOS Phase 3: Browser & Real Excel Export Download Check');
    console.log(`Target: ${BASE_URL}`);
    console.log('================================================================\n');

    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ acceptDownloads: true });
    const page = await context.newPage();

    // 1. Login
    console.log('Authenticating as Admin/Owner...');
    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name=email]', 'admin@urbanpets.test');
    await page.fill('input[name=password]', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"]')
    ]);
    const loggedIn = !page.url().includes('/login');
    console.log(`[${loggedIn ? 'PASS' : 'FAIL'}] Logged in successfully as Admin/Owner`);

    // 2. GST Sales Taxwise Page & Export
    console.log('\nTesting /reports/view/gst-sales-taxwise...');
    await page.goto(`${BASE_URL}/reports/view/gst-sales-taxwise?from=2026-09-01&to=2026-09-30`, { waitUntil: 'networkidle' });
    const salesExportBtn = page.locator('a:has-text("Export Excel (GST Taxwise)")');
    await salesExportBtn.waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
    const hasSalesExportBtn = (await salesExportBtn.count()) > 0;
    console.log(`[${hasSalesExportBtn ? 'PASS' : 'FAIL'}] GST Sales Taxwise page has "Export Excel (GST Taxwise)" button`);

    console.log('Triggering GST Sales Taxwise Excel download via button click...');
    const [salesDownload] = await Promise.all([
        page.waitForEvent('download', { timeout: 15000 }),
        salesExportBtn.click()
    ]);
    const salesSuggestedName = salesDownload.suggestedFilename();
    const salesTempPath = path.join(process.cwd(), 'scratch', salesSuggestedName);
    fs.mkdirSync(path.join(process.cwd(), 'scratch'), { recursive: true });
    await salesDownload.saveAs(salesTempPath);
    const salesStats = fs.statSync(salesTempPath);
    const isSalesXlsxValid = salesStats.size > 2000 && salesSuggestedName.endsWith('.xlsx');
    console.log(`[${isSalesXlsxValid ? 'PASS' : 'FAIL'}] GST Sales Taxwise .xlsx downloaded: ${salesSuggestedName} (${salesStats.size} bytes)`);

    // 3. GST Purchase Summary Page & Export
    console.log('\nTesting /reports/gst-purchase-summary...');
    await page.goto(`${BASE_URL}/reports/gst-purchase-summary?from=2026-09-01&to=2026-09-30`, { waitUntil: 'networkidle' });
    const purExportBtn = page.locator('a:has-text("Export Excel (Invoice-wise)")');
    await purExportBtn.waitFor({ state: 'attached', timeout: 10000 }).catch(() => {});
    const hasPurExportBtn = (await purExportBtn.count()) > 0;
    console.log(`[${hasPurExportBtn ? 'PASS' : 'FAIL'}] GST Purchase Summary page has "Export Excel (Invoice-wise)" button`);

    console.log('Triggering GST Purchase Summary Excel download via button click...');
    const [purDownload] = await Promise.all([
        page.waitForEvent('download', { timeout: 15000 }),
        purExportBtn.click()
    ]);
    const purSuggestedName = purDownload.suggestedFilename();
    const purTempPath = path.join(process.cwd(), 'scratch', purSuggestedName);
    await purDownload.saveAs(purTempPath);
    const purStats = fs.statSync(purTempPath);
    const isPurXlsxValid = purStats.size > 2000 && purSuggestedName.endsWith('.xlsx');
    console.log(`[${isPurXlsxValid ? 'PASS' : 'FAIL'}] GST Purchase Summary .xlsx downloaded: ${purSuggestedName} (${purStats.size} bytes)`);

    // 4. GSTR-1 Overview Page & All 12 Sections
    console.log('\nTesting /tools/gst/gstr-1 and all 12 section detail pages...');
    await page.goto(`${BASE_URL}/tools/gst/gstr-1?from=2026-09-01&to=2026-09-30`, { waitUntil: 'networkidle' });
    const gstr1Heading = await page.locator('h1').textContent().catch(() => '');
    console.log(`[${gstr1Heading.includes('GSTR-1') ? 'PASS' : 'FAIL'}] GSTR-1 Dashboard loaded: "${gstr1Heading.trim()}"`);

    const sections = [
        { slug: 'b2b-hsn', name: 'HSN B2B Summary' },
        { slug: 'b2c-hsn', name: 'HSN B2C Summary' },
        { slug: 'b2b', name: 'B2B Outward Supplies' },
        { slug: 'b2cl', name: 'B2CL Outward Supplies' },
        { slug: 'exp', name: 'Exported Supplies' },
        { slug: 'b2cs', name: 'B2CS Outward Supplies' },
        { slug: 'cdnr', name: 'Credit/Debit Notes Registered' },
        { slug: 'cdnur', name: 'Credit/Debit Notes Unregistered' },
        { slug: 'nil', name: 'Nil Rated Supplies' },
        { slug: 'adv-rec', name: 'Advance Received' },
        { slug: 'adv-adj', name: 'Advance Adjusted' },
        { slug: 'doc-issued', name: 'Document Issued' }
    ];

    let allSectionsOk = true;
    for (const sec of sections) {
        const resp = await page.goto(`${BASE_URL}/tools/gst/gstr-1/${sec.slug}?from=2026-09-01&to=2026-09-30`, { waitUntil: 'networkidle' });
        const status = resp.status();
        const content = await page.content();
        const ok = status === 200 && !content.includes('Server Error') && !content.includes('Whoops!');
        console.log(`[${ok ? 'PASS' : 'FAIL'}] Section ${sec.slug} (${sec.name}): HTTP ${status}`);
        if (!ok) allSectionsOk = false;
    }

    await browser.close();

    console.log('\n================================================================');
    console.log(`Browser & Excel Export Verification: ${allSectionsOk && isSalesXlsxValid && isPurXlsxValid ? 'ALL PASSED' : 'SOME FAILED'}`);
    console.log('================================================================\n');
}

main().catch(err => {
    console.error('Test error:', err);
    process.exit(1);
});
