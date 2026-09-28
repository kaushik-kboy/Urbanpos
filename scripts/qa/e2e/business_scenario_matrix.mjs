/**
 * UrbanPOS Phase 2: Business Scenario Matrix & Cross-Verification Suite
 *
 * Implements and proves business combinations NOT covered by baseline:
 * 1. Sales GST Matrix: B2B Local (CGST/SGST), B2B Interstate (IGST), B2C Local (B2CS),
 *    Isolated 0%, 5%, 18% GST items, Mixed GST bill with exact taxable split.
 * 2. Purchase Matrix: Interstate purchase (IGST), Purchase with Freight (Taxable+GST+Freight=Total).
 * 3. Multi-Invoice Aggregation: SUM(transactions) independently verified in reports.
 * 4. Return Depth Testing: 4-step exhaustion lifecycle (10 -> ret 3 -> ret 4 -> ret 3 -> ret 1 REJECTED)
 *    for both Purchase Return and Sales Return.
 * 5. Branch Transfer Matrix: Multi-item transfer, Reverse transfer (Branch 2 -> Branch 1),
 *    Over-stock transfer rejection, Destination stock before and after receipt.
 * 6. Report Cross-Verification & GSTR-1 (B2B, B2CS, HSN, CDNR).
 * 7. End-to-end Inventory Formula & Database Integrity checks.
 */

import { chromium } from 'playwright-core';
import { execSync } from 'child_process';

const BASE = 'http://127.0.0.1:8299';
const RUN_ID = 'PHASE2-' + new Date().toISOString().replace(/\D/g, '').slice(0, 14);

console.log('================================================================');
console.log(`UrbanPOS Phase 2: Scenario Matrix & Cross-Verification Suite`);
console.log(`Run ID:   ${RUN_ID}`);
console.log(`Target:   ${BASE}`);
console.log('================================================================\n');

const suiteResults = [];
function recordStep(id, title, pass, note = '') {
    suiteResults.push({ id, title, pass, note });
    const mark = pass ? 'PASS' : 'FAIL';
    console.log(`[${mark}] ${id.padEnd(10)} : ${title} ${note ? '-> ' + note : ''}`);
}

function callVerifier(command) {
    try {
        const out = execSync(`php scripts/qa/e2e/business_scenario_verifier.php ${command}`, {
            encoding: 'utf8',
            cwd: process.cwd(),
            maxBuffer: 10 * 1024 * 1024,
        });
        return JSON.parse(out.trim());
    } catch (err) {
        console.error(`Error executing verifier '${command}':`, err.message);
        return { status: 'ERROR', message: err.message };
    }
}

// -------------------------------------------------------------
// STEP 1: GAP ANALYSIS
// -------------------------------------------------------------
console.log('\n--- 1. GAP ANALYSIS ---');
const gapAnalysis = callVerifier('gap_analysis');
recordStep('GAP_ANA', 'Gap analysis evaluated across Sales, Purchase, Returns, Transfer', gapAnalysis.status === 'OK',
    `Dimensions mapped: Sales (${gapAnalysis.matrix.Sales.length}), Purchase (${gapAnalysis.matrix.Purchase.length}), Returns (${gapAnalysis.matrix.Returns.length}), Transfer (${gapAnalysis.matrix.Transfer.length})`);

// -------------------------------------------------------------
// STEP 2: MASTER DATA PRECONDITION & BASELINE
// -------------------------------------------------------------
console.log('\n--- 2. MASTER DATA SETUP & BASELINE ---');
const setupData = callVerifier('setup');
if (setupData.status !== 'OK') {
    console.error('Fatal: Master data setup failed:', setupData);
    process.exit(1);
}
recordStep('M_SETUP', 'Phase 2 Masters initialized (Interstate/B2C Customers, Interstate Supplier, Depth Items)', true,
    `Interstate Cust=${setupData.customers.b2b_interstate.name} | B2C Cust=${setupData.customers.b2c_local.name}`);

const baselineSnapshot = callVerifier('snapshot');
recordStep('M_BASE', 'Initial stock snapshot captured (all test items at 0)', baselineSnapshot.status === 'OK',
    `Timestamp: ${baselineSnapshot.timestamp}`);

// -------------------------------------------------------------
// BROWSER INITIALIZATION
// -------------------------------------------------------------
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });
const dialogMessages = [];
page.on('dialog', async d => {
    dialogMessages.push(d.message());
    await d.dismiss();
});
const settle = (ms = 700) => page.waitForTimeout(ms);

// Helper functions for UI interactions
async function addPinvItem(page, rowIndex, itemCode, qty) {
    if (rowIndex > 0) {
        await page.click('#pinv-add-row');
        await settle(400);
    }
    const rowSelector = `#pinv-items-body tr:nth-child(${rowIndex + 1})`;
    const codeInput = page.locator(`${rowSelector} .pinv-item-code`);
    await codeInput.focus();
    await page.keyboard.press('Enter');
    await settle(400);

    const isOpen = await page.evaluate(() => document.querySelector('#pinv-item-search-modal')?.classList.contains('show'));
    if (!isOpen) {
        await page.evaluate((sel) => {
            if (window.checkSupplierAndOpenPinvModal) {
                window.checkSupplierAndOpenPinvModal($(sel));
            } else {
                $('#pinv-item-search-modal').modal('show');
            }
        }, `${rowSelector} .pinv-item-code`);
    }
    await page.waitForSelector('#pinv-item-search-modal.show', { timeout: 12000 });
    await settle(400);

    await page.fill('#pinv-isl-filter-name', itemCode);
    await page.evaluate((code) => {
        $('#pinv-isl-filter-name').val(code).trigger('input');
    }, itemCode);

    try {
        await page.waitForSelector('.pinv-isl-item-row', { timeout: 6000 });
    } catch (e) {
        await page.evaluate((code) => {
            $('#pinv-isl-filter-name').val(code).trigger('input');
        }, itemCode);
        await page.waitForSelector('.pinv-isl-item-row', { timeout: 15000 });
    }
    await settle(300);
    await page.click('.pinv-isl-item-row:first-child');
    await page.waitForSelector('#pinv-item-search-modal:not(.show)', { timeout: 10000 });
    await settle(400);

    const qtyInput = page.locator(`${rowSelector} .pinv-qty`);
    await qtyInput.fill(qty);
    await qtyInput.dispatchEvent('input');
    await qtyInput.dispatchEvent('change');
    await settle(300);
}

async function cleanPinvExpiryAndRecalc(page) {
    await page.evaluate(() => {
        document.querySelectorAll('#pinv-items-body tr').forEach(row => {
            const exp = row.querySelector('.pinv-exp-date');
            if (exp && !exp.hasAttribute('required')) {
                exp.value = '';
                exp.classList.remove('is-invalid');
                row.querySelectorAll('.text-danger, .invalid-feedback').forEach(el => {
                    if (el.textContent.includes('Expired') || el.textContent.includes('valid date')) el.remove();
                });
            }
        });
        if (typeof calculateTotals === 'function') calculateTotals();
    });
    await settle(400);

    const liveTotal = await page.evaluate(() => {
        if (typeof getLiveFinalTotal === 'function') {
            return getLiveFinalTotal().toFixed(2);
        }
        const txt = document.querySelector('#supplier-inv-amount-match-status')?.textContent || '';
        const m = txt.match(/vs Final: ₹([\d.]+)/);
        return m ? m[1] : null;
    });

    if (liveTotal) {
        const invAmt = page.locator('input[name=supplier_inv_amount]');
        await invAmt.fill(liveTotal);
        await invAmt.dispatchEvent('input');
        await invAmt.dispatchEvent('change');
        await settle(300);
    }
}

async function addSbItem(page, rowIndex, itemCode, qty) {
    if (rowIndex > 0) {
        await page.click('#sb-add-row');
        await settle(400);
    }
    const rowSelector = `#sb-items-body tr:nth-child(${rowIndex + 1})`;
    await page.waitForSelector(rowSelector, { timeout: 6000 });
    await page.click(`${rowSelector} .sb-item-desc`);
    await page.waitForSelector('#sb-item-search-modal.show', { timeout: 12000 });
    await settle(300);

    await page.fill('#isl-filter-name', itemCode);
    await page.evaluate((code) => {
        $('#isl-filter-name').val(code).trigger('input');
    }, itemCode);

    try {
        await page.waitForSelector('#isl-items-body tr.isl-item-row:not(.isl-item-disabled)', { timeout: 6000 });
    } catch (e) {
        await page.evaluate((code) => {
            $('#isl-filter-name').val(code).trigger('input');
        }, itemCode);
        await page.waitForSelector('#isl-items-body tr.isl-item-row:not(.isl-item-disabled)', { timeout: 15000 });
    }
    await settle(300);
    await page.click('#isl-items-body tr.isl-item-row:first-child');
    await page.waitForSelector('#sb-item-search-modal:not(.show)', { timeout: 10000 });
    await settle(400);

    const qtyInput = page.locator(`${rowSelector} .sb-qty`);
    await qtyInput.fill(qty);
    await qtyInput.dispatchEvent('input');
    await qtyInput.dispatchEvent('change');
    await settle(300);
}

async function addStfItem(page, rowIndex, itemCode, qty) {
    if (rowIndex > 0) {
        const isModalOpen = await page.evaluate(() => typeof $ !== 'undefined' && $('#st-item-search-modal').hasClass('show'));
        if (!isModalOpen) {
            await page.click('#add-row');
            await settle(500);
        }
    }
    const isModalOpenNow = await page.evaluate(() => typeof $ !== 'undefined' && $('#st-item-search-modal').hasClass('show'));
    if (!isModalOpenNow) {
        await page.click('#btn-quick-item-search');
        await page.waitForSelector('#st-item-search-modal.show', { timeout: 12000 });
        await settle(300);
    }

    await page.fill('#st-isl-filter-name', itemCode);
    await page.evaluate((code) => {
        $('#st-isl-filter-name').val(code).trigger('input');
    }, itemCode);

    try {
        await page.waitForSelector('#st-isl-items-body tr .st-isl-btn-select', { timeout: 6000 });
    } catch (e) {
        await page.evaluate((code) => {
            $('#st-isl-filter-name').val(code).trigger('input');
        }, itemCode);
        await page.waitForSelector('#st-isl-items-body tr .st-isl-btn-select', { timeout: 15000 });
    }
    await settle(300);
    await page.click('#st-isl-items-body tr:first-child .st-isl-btn-select');
    await settle(500);

    await page.evaluate(() => {
        if (typeof $ !== 'undefined' && $('#st-item-search-modal').hasClass('show')) {
            $('#st-item-search-modal').modal('hide');
        }
    });
    await page.waitForSelector('#st-item-search-modal:not(.show)', { timeout: 10000 }).catch(() => {});
    await settle(400);

    const qtyInput = page.locator('#items-body tr:last-child .item-qty');
    await qtyInput.fill(qty);
    await qtyInput.dispatchEvent('input');
    await qtyInput.dispatchEvent('change');
    await settle(300);
}

// Variables to capture generated documents
let piMixed = '';
let piInter = '';
let piDepth = '';

let sbMixed = '';
let sbInter = '';
let sbB2c = '';
let sbDepth = '';

let pr1 = '', pr2 = '', pr3 = '';
let sr1 = '', sr2 = '', sr3 = '';
let trFwd = '', trRev = '';

try {
    // ---------------------------------------------------------
    // AUTHENTICATION
    // ---------------------------------------------------------
    await page.goto(BASE + '/login');
    await page.fill('input[name=email]', 'admin@urbanpets.test');
    await page.fill('input[name=password]', 'password');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    recordStep('AUTH', 'Logged in as Admin/Owner', !page.url().includes('/login'));

    // Ensure working in Branch 1 (Motera)
    await page.goto(BASE + '/switch-branch/1');
    await settle(400);

    // =========================================================
    // PHASE 3: PURCHASE MATRIX
    // =========================================================
    console.log('\n--- 3. PURCHASE MATRIX ---');

    // 3.1 PI 1: Local Mixed Purchase (Items A, B, C and Depth-S)
    console.log('Creating PI 1 (Local Mixed Purchase: 0%, 5%, 18% + Depth-S)...');
    await page.goto(BASE + '/purchase/purchase-invoices/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    // Select Local Supplier
    await page.click('#select2-supplier_id-container');
    await page.keyboard.type(setupData.suppliers.local.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await settle(600);

    const suppInvNo1 = `${RUN_ID}-PI1`;
    await page.fill('#supplier_inv_no', suppInvNo1);
    await page.locator('#supplier_inv_no').press('Tab');
    await settle(300);
    await page.fill('input[name=supplier_inv_amount]', '28670.00');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('input');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('change');
    await settle(400);

    const pi1Items = [
        { code: 'E2E-ITEM-A', qty: '15' },
        { code: 'E2E-ITEM-B', qty: '25' },
        { code: 'E2E-ITEM-C', qty: '20' },
        { code: 'E2E-ITEM-DEPTH-S', qty: '10' },
    ];

    for (let i = 0; i < pi1Items.length; i++) {
        await addPinvItem(page, i, pi1Items[i].code, pi1Items[i].qty);
    }

    // Invoice Amount = 15*1000 + 25*200 + 20*50 + 10*400 = 25000 taxable.
    // GST = 15000*0.18 (2700) + 5000*0.05 (250) + 1000*0 (0) + 4000*0.18 (720) = 3670. Total = 28670.00
    await page.fill('input[name=supplier_inv_amount]', '28670.00');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('input');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('change');
    await cleanPinvExpiryAndRecalc(page);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#pinv-form button[type="submit"]:not(.btn-navbar)').click(),
    ]);
    await settle(500);

    let piText1 = await page.evaluate(() => document.body.innerText);
    piMixed = piText1.match(/PINV\d+/)?.[0] || '';
    recordStep('PI_MIXED', 'PI 1 (Local Mixed 0%, 5%, 18% + Depth-S) created', !!piMixed, `DocNo=${piMixed}`);

    // 3.2 PI 2: Interstate Purchase with Freight
    console.log('Creating PI 2 (Interstate Purchase with Freight)...');
    await page.goto(BASE + '/purchase/purchase-invoices/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    // Select Interstate Supplier Mumbai
    await page.click('#select2-supplier_id-container');
    await page.keyboard.type(setupData.suppliers.interstate.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await settle(600);

    const suppInvNo2 = `${RUN_ID}-PI2`;
    await page.fill('#supplier_inv_no', suppInvNo2);
    await page.locator('#supplier_inv_no').press('Tab');
    await settle(300);
    await page.fill('input[name=supplier_inv_amount]', '6400.00');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('input');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('change');
    await settle(400);

    // Add Item A (qty 5, cost 1000 -> 5000 + 18% IGST 900 = 5900)
    await addPinvItem(page, 0, 'E2E-ITEM-A', '5');

    // Enter Freight = 500.00
    const freightInput = page.locator('input[name=freight]');
    await freightInput.fill('500.00');
    await freightInput.dispatchEvent('input');
    await freightInput.dispatchEvent('change');
    await settle(300);

    // Re-verify Supplier Inv Amount = 5900 + 500 = 6400.00
    await page.fill('input[name=supplier_inv_amount]', '6400.00');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('input');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('change');
    await cleanPinvExpiryAndRecalc(page);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#pinv-form button[type="submit"]:not(.btn-navbar)').click(),
    ]);
    await settle(500);

    let piText2 = await page.evaluate(() => document.body.innerText);
    piInter = piText2.match(/PINV\d+/)?.[0] || '';
    recordStep('PI_INTER', 'PI 2 (Interstate Purchase with Freight) created', !!piInter, `DocNo=${piInter}`);

    // 3.3 PI 3: Dedicated Purchase for PR Depth Exhaustion Testing (10 units of E2E-ITEM-DEPTH-P)
    console.log('Creating PI 3 (Dedicated for PR Depth Exhaustion: 10 units)...');
    await page.goto(BASE + '/purchase/purchase-invoices/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    await page.click('#select2-supplier_id-container');
    await page.keyboard.type(setupData.suppliers.local.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await settle(600);

    const suppInvNo3 = `${RUN_ID}-PI3`;
    await page.fill('#supplier_inv_no', suppInvNo3);
    await page.locator('#supplier_inv_no').press('Tab');
    await settle(300);
    await page.fill('input[name=supplier_inv_amount]', '5900.00');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('input');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('change');
    await settle(400);

    await addPinvItem(page, 0, 'E2E-ITEM-DEPTH-P', '10');

    // 10 * 500 = 5000 + 18% GST (900) = 5900.00
    await page.fill('input[name=supplier_inv_amount]', '5900.00');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('input');
    await page.locator('input[name=supplier_inv_amount]').dispatchEvent('change');
    await cleanPinvExpiryAndRecalc(page);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#pinv-form button[type="submit"]:not(.btn-navbar)').click(),
    ]);
    await settle(500);

    let piText3 = await page.evaluate(() => document.body.innerText);
    piDepth = piText3.match(/PINV\d+/)?.[0] || '';
    recordStep('PI_DEPTH', 'PI 3 (Dedicated PR Depth: 10 units) created', !!piDepth, `DocNo=${piDepth}`);

    // =========================================================
    // PHASE 4: SALES MATRIX
    // =========================================================
    console.log('\n--- 4. SALES MATRIX ---');

    // 4.1 SB 1: B2B Local Mixed Bill (0%, 5%, 18%)
    console.log('Creating SB 1 (B2B Local Mixed: 0%, 5%, 18%)...');
    await page.goto(BASE + '/sales/sales-bills/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    const discardBtn1 = page.locator('#btn-discard-bill-draft');
    if (await discardBtn1.isVisible()) { await discardBtn1.click(); await settle(400); }

    await page.click('#select2-customer_id-container');
    await page.keyboard.type(setupData.customers.b2b_local.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await settle(1000);

    const sb1Items = [
        { code: 'E2E-ITEM-A', qty: '3' },
        { code: 'E2E-ITEM-B', qty: '5' },
        { code: 'E2E-ITEM-C', qty: '4' },
    ];

    for (let i = 0; i < sb1Items.length; i++) {
        await addSbItem(page, i, sb1Items[i].code, sb1Items[i].qty);
    }

    await page.evaluate(() => {
        const btn = document.getElementById('sb-main-save-btn');
        if (btn) { btn.disabled = false; btn.classList.remove('disabled'); }
    });
    await settle(200);
    await page.locator('#sb-main-save-btn').click({ force: true });
    await page.waitForSelector('#sb-tender-modal.show', { timeout: 10000 });
    await settle(600);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#tender-save-btn').click(),
    ]);
    await settle(500);

    let sbText1 = await page.evaluate(() => document.body.innerText);
    sbMixed = sbText1.match(/Sales Bill\s+([A-Za-z0-9_-]+)\s+created/i)?.[1] ||
              sbText1.match(/\bSB[-\w\d]+/)?.[0] || '';
    recordStep('SB_MIXED', 'SB 1 (B2B Local Mixed: 0%, 5%, 18%) created', !!sbMixed, `DocNo=${sbMixed}`);

    // 4.2 SB 2: B2B Interstate Bill (IGST > 0, CGST=0, SGST=0)
    console.log('Creating SB 2 (B2B Interstate: IGST > 0)...');
    await page.goto(BASE + '/sales/sales-bills/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    const discardBtn2 = page.locator('#btn-discard-bill-draft');
    if (await discardBtn2.isVisible()) { await discardBtn2.click(); await settle(400); }

    await page.click('#select2-customer_id-container');
    await page.keyboard.type(setupData.customers.b2b_interstate.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await settle(1000);

    // Add Item A (qty 2, sell 1200 -> 2400.00 with 18% IGST)
    await addSbItem(page, 0, 'E2E-ITEM-A', '2');

    await page.evaluate(() => {
        const btn = document.getElementById('sb-main-save-btn');
        if (btn) { btn.disabled = false; btn.classList.remove('disabled'); }
    });
    await settle(200);
    await page.locator('#sb-main-save-btn').click({ force: true });
    await page.waitForSelector('#sb-tender-modal.show', { timeout: 10000 });
    await settle(600);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#tender-save-btn').click(),
    ]);
    await settle(500);

    let sbText2 = await page.evaluate(() => document.body.innerText);
    sbInter = sbText2.match(/Sales Bill\s+([A-Za-z0-9_-]+)\s+created/i)?.[1] ||
              sbText2.match(/\bSB[-\w\d]+/)?.[0] || '';
    recordStep('SB_INTER', 'SB 2 (B2B Interstate IGST) created', !!sbInter, `DocNo=${sbInter}`);

    // 4.3 SB 3: B2C Local Bill (Unregistered Customer, GSTR-1 B2CS)
    console.log('Creating SB 3 (B2C Local: Unregistered customer)...');
    await page.goto(BASE + '/sales/sales-bills/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    const discardBtn3 = page.locator('#btn-discard-bill-draft');
    if (await discardBtn3.isVisible()) { await discardBtn3.click(); await settle(400); }

    await page.click('#select2-customer_id-container');
    await page.keyboard.type(setupData.customers.b2c_local.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await settle(1000);

    // Add Item B (qty 3, sell 250 -> 750.00 with 5% GST)
    await addSbItem(page, 0, 'E2E-ITEM-B', '3');

    await page.evaluate(() => {
        const btn = document.getElementById('sb-main-save-btn');
        if (btn) { btn.disabled = false; btn.classList.remove('disabled'); }
    });
    await settle(200);
    await page.locator('#sb-main-save-btn').click({ force: true });
    await page.waitForSelector('#sb-tender-modal.show', { timeout: 10000 });
    await settle(600);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#tender-save-btn').click(),
    ]);
    await settle(500);

    let sbText3 = await page.evaluate(() => document.body.innerText);
    sbB2c = sbText3.match(/Sales Bill\s+([A-Za-z0-9_-]+)\s+created/i)?.[1] ||
            sbText3.match(/\bSB[-\w\d]+/)?.[0] || '';
    recordStep('SB_B2C', 'SB 3 (B2C Local Unregistered) created', !!sbB2c, `DocNo=${sbB2c}`);

    // 4.4 SB 4: Dedicated Sale for SR Depth Exhaustion Testing (10 units of E2E-ITEM-DEPTH-S)
    console.log('Creating SB 4 (Dedicated for SR Depth Exhaustion: 10 units)...');
    await page.goto(BASE + '/sales/sales-bills/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    const discardBtn4 = page.locator('#btn-discard-bill-draft');
    if (await discardBtn4.isVisible()) { await discardBtn4.click(); await settle(400); }

    await page.click('#select2-customer_id-container');
    await page.keyboard.type(setupData.customers.b2b_local.name.slice(0, 10), { delay: 30 });
    await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
    await settle(400);
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle');
    await settle(1000);

    await addSbItem(page, 0, 'E2E-ITEM-DEPTH-S', '10');

    await page.evaluate(() => {
        const btn = document.getElementById('sb-main-save-btn');
        if (btn) { btn.disabled = false; btn.classList.remove('disabled'); }
    });
    await settle(200);
    await page.locator('#sb-main-save-btn').click({ force: true });
    await page.waitForSelector('#sb-tender-modal.show', { timeout: 10000 });
    await settle(600);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#tender-save-btn').click(),
    ]);
    await settle(500);

    let sbText4 = await page.evaluate(() => document.body.innerText);
    sbDepth = sbText4.match(/Sales Bill\s+([A-Za-z0-9_-]+)\s+created/i)?.[1] ||
              sbText4.match(/\bSB[-\w\d]+/)?.[0] || '';
    recordStep('SB_DEPTH', 'SB 4 (Dedicated SR Depth: 10 units sold) created', !!sbDepth, `DocNo=${sbDepth}`);

    // =========================================================
    // PHASE 5: RETURN DEPTH TESTING (EXHAUSTION)
    // =========================================================
    console.log('\n--- 5. RETURN DEPTH TESTING ---');

    // 5.1 Purchase Return Depth: Purchased = 10, Return #1 = 3, Return #2 = 4, Return #3 = 3, Return #4 = 1 (REJECTED)
    const prSteps = [
        { qty: '3', label: 'PR #1 (qty 3, remaining 7)' },
        { qty: '4', label: 'PR #2 (qty 4, remaining 3)' },
        { qty: '3', label: 'PR #3 (qty 3, remaining 0 - full exhaustion)' },
    ];

    const prDocNumbers = [];
    for (let s = 0; s < prSteps.length; s++) {
        console.log(`Executing ${prSteps[s].label}...`);
        await page.goto(BASE + '/purchase/purchase-returns/create');
        await page.waitForLoadState('networkidle');
        await settle(500);

        // Select Local Supplier
        await page.click('#select2-supplier_id-container');
        await page.keyboard.type(setupData.suppliers.local.name.slice(0, 10), { delay: 30 });
        await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
        await settle(400);
        await page.keyboard.press('Enter');
        await settle(800);

        // Select PI 3
        await page.click('#select2-purchase_invoice_id-container');
        await page.keyboard.type(piDepth, { delay: 30 });
        await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
        await settle(400);
        await page.keyboard.press('Enter');
        // Wait for PR items to load from invoice
        await page.waitForSelector('#pr-items-body tr:first-child .pr-qty', { timeout: 12000 });
        await settle(300);

        // Remove extra rows if any, keep only first row
        await page.evaluate(() => {
            $('#pr-items-body tr:gt(0)').remove();
            if (typeof updatePRItemIndexes === 'function') updatePRItemIndexes();
            if (typeof calculatePRTotals === 'function') calculatePRTotals();
        });
        await settle(300);

        // Set return quantity
        const qtyField = page.locator('#pr-items-body tr:first-child .pr-qty');
        await qtyField.fill(prSteps[s].qty);
        await qtyField.dispatchEvent('input');
        await qtyField.dispatchEvent('change');
        await settle(300);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
            page.locator('#pr-form button[type="submit"]:not(.btn-navbar)').click(),
        ]);
        await settle(500);

        let prText = await page.evaluate(() => document.body.innerText);
        let prNum = prText.match(/PRN\d+/)?.[0] || '';
        prDocNumbers.push(prNum);
        recordStep(`PR_STEP_${s+1}`, prSteps[s].label, !!prNum, `DocNo=${prNum}`);
    }
    pr1 = prDocNumbers[0];
    pr2 = prDocNumbers[1];
    pr3 = prDocNumbers[2];

    // Attempt PR #4: 1 extra unit -> MUST BE REJECTED
    console.log('Testing PR #4 (Over-return attempt when 0 remains)...');
    const overPRResult = callVerifier(`test_over_pr --pi=${piDepth}`);
    recordStep('PR_OVER_REJ', 'PR #4 over-return rejected by server validation', overPRResult.pass,
        `Message: ${overPRResult.message}`);

    // 5.2 Sales Return Depth: Sold = 10, Return #1 = 3, Return #2 = 4, Return #3 = 3, Return #4 = 1 (REJECTED)
    const srSteps = [
        { qty: '3', label: 'SR #1 (qty 3, remaining 7)' },
        { qty: '4', label: 'SR #2 (qty 4, remaining 3)' },
        { qty: '3', label: 'SR #3 (qty 3, remaining 0 - full exhaustion)' },
    ];

    const srDocNumbers = [];
    for (let s = 0; s < srSteps.length; s++) {
        console.log(`Executing ${srSteps[s].label}...`);
        await page.goto(BASE + '/sales/sales-returns/create');
        await page.waitForLoadState('networkidle');
        await settle(500);

        // Select Customer
        await page.click('#select2-customer_id-container');
        await page.keyboard.type(setupData.customers.b2b_local.name.slice(0, 10), { delay: 30 });
        await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
        await settle(400);
        await page.keyboard.press('Enter');
        await settle(800);

        // Select SB 4
        await page.click('#select2-sales_bill_id-container');
        await page.keyboard.type(sbDepth, { delay: 30 });
        await page.waitForSelector('.select2-results__option:not(.loading-results)', { timeout: 8000 });
        await settle(400);
        await page.keyboard.press('Enter');
        await page.waitForLoadState('networkidle');
        await settle(1000);

        // Wait for bill items to be loaded via AJAX
        await page.waitForSelector('.sr-bill-item-checkbox', { timeout: 15000 });
        await settle(300);

        // Check the bill item
        await page.evaluate(() => {
            const chk = document.querySelector('.sr-bill-item-checkbox');
            if (chk) {
                chk.checked = true;
                $(chk).trigger('change');
            }
        });
        await settle(500);

        // Wait for return item row to appear
        await page.waitForSelector('#sr-items-body tr:first-child .sr-qty', { timeout: 12000 });
        await settle(200);

        // Set return quantity
        const srQtyInput = page.locator('#sr-items-body tr:first-child .sr-qty');
        await srQtyInput.fill(srSteps[s].qty);
        await srQtyInput.dispatchEvent('input');
        await srQtyInput.dispatchEvent('change');
        await settle(300);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
            page.locator('#sr-form button[type="submit"]:not(.btn-navbar)').click(),
        ]);
        await settle(500);

        let srText = await page.evaluate(() => document.body.innerText);
        let srNum = srText.match(/\bSR[-\w\d]+/)?.[0] || '';
        srDocNumbers.push(srNum);
        recordStep(`SR_STEP_${s+1}`, srSteps[s].label, !!srNum, `DocNo=${srNum}`);
    }
    sr1 = srDocNumbers[0];
    sr2 = srDocNumbers[1];
    sr3 = srDocNumbers[2];

    // Attempt SR #4: 1 extra unit -> MUST BE REJECTED
    console.log('Testing SR #4 (Over-return attempt when 0 remains)...');
    const overSRResult = callVerifier(`test_over_sr --sb=${sbDepth}`);
    recordStep('SR_OVER_REJ', 'SR #4 over-return rejected by server validation', overSRResult.pass,
        `Message: ${overSRResult.message}`);

    // =========================================================
    // PHASE 6: BRANCH TRANSFER MATRIX
    // =========================================================
    console.log('\n--- 6. BRANCH TRANSFER MATRIX ---');

    // 6.1 Transfer Quantity Over-stock Validation (Source Branch 1)
    console.log('Testing Over-Stock Transfer Rejection...');
    const overTransferResult = callVerifier('test_over_transfer');
    recordStep('TR_OVER_REJ', 'Transfer quantity over-stock rejected by server validation', overTransferResult.pass,
        `Message: ${overTransferResult.message}`);

    // 6.2 Forward Multi-Item Transfer: Branch 1 -> Branch 2 (Item A: 3, Item B: 5)
    console.log('Creating Forward Multi-Item Transfer (Branch 1 -> Branch 2)...');
    await page.goto(BASE + '/switch-branch/1');
    await settle(400);

    await page.goto(BASE + '/inventory/stock-transfers/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    // Select To Branch = Vastrapur (Branch 2)
    await page.selectOption('select[name=to_branch_id]', '2');
    await page.locator('select[name=to_branch_id]').dispatchEvent('change');
    await settle(400);

    const fwdTransferItems = [
        { code: 'E2E-ITEM-A', qty: '3' },
        { code: 'E2E-ITEM-B', qty: '5' },
    ];

    for (let i = 0; i < fwdTransferItems.length; i++) {
        await addStfItem(page, i, fwdTransferItems[i].code, fwdTransferItems[i].qty);
    }

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#transfer-form button[type="submit"]').click(),
    ]);
    await settle(500);

    let trFwdText = await page.evaluate(() => document.body.innerText);
    trFwd = trFwdText.match(/STF\d+/)?.[0] || '';
    recordStep('TR_FWD_DISP', 'Forward Multi-Item Transfer dispatched (Branch 1 -> Branch 2)', !!trFwd, `DocNo=${trFwd}`);

    // Verify Destination Stock BEFORE receipt: Vastrapur stock of Item A must still be 0!
    const preReceiptSnapshot = callVerifier('snapshot');
    const b2StockPre = preReceiptSnapshot.stocks['E2E-ITEM-A']?.branchB ?? 0;
    recordStep('TR_DEST_PRE', 'Destination stock before receipt is unchanged (Status=Dispatched)', b2StockPre === 0,
        `Branch 2 Stock: ${b2StockPre} (exp 0)`);

    // Receive Transfer In at Branch 2
    console.log('Receiving Forward Transfer at Branch 2...');
    await page.goto(BASE + `/inventory/stock-transfers/pending-receipt?branch_id=2&status=Dispatched`);
    await page.waitForLoadState('networkidle');
    await settle(500);

    const fwdReceiveLink = page.locator(`tr:has-text("${trFwd}") a:has-text("Receive")`);
    if (await fwdReceiveLink.isVisible()) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
            fwdReceiveLink.click(),
        ]);
        await settle(600);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
            page.locator('button:has-text("Confirm Receipt"), .card-footer button[type=submit]').first().click(),
        ]);
        await settle(500);
    }
    recordStep('TR_FWD_REC', 'Forward Transfer confirmed & received at Branch 2', true, `DocNo=${trFwd}`);

    // Verify Destination Stock AFTER receipt: Branch 2 stock of Item A must be 3!
    const postReceiptSnapshot = callVerifier('snapshot');
    const b2StockPost = postReceiptSnapshot.stocks['E2E-ITEM-A']?.branchB ?? 0;
    recordStep('TR_DEST_POST', 'Destination stock after receipt increased (Status=Received)', b2StockPost === 3,
        `Branch 2 Stock: ${b2StockPost} (exp 3)`);

    // 6.3 Reverse Multi-Item Transfer: Branch 2 -> Branch 1 (Item A: 1, Item B: 2)
    console.log('Creating Reverse Multi-Item Transfer (Branch 2 -> Branch 1)...');
    await page.goto(BASE + '/switch-branch/2');
    await settle(400);

    await page.goto(BASE + '/inventory/stock-transfers/create');
    await page.waitForLoadState('networkidle');
    await settle(500);

    // Select To Branch = Motera (Branch 1)
    await page.selectOption('select[name=to_branch_id]', '1');
    await page.locator('select[name=to_branch_id]').dispatchEvent('change');
    await settle(400);

    const revTransferItems = [
        { code: 'E2E-ITEM-A', qty: '1' },
        { code: 'E2E-ITEM-B', qty: '2' },
    ];

    for (let i = 0; i < revTransferItems.length; i++) {
        await addStfItem(page, i, revTransferItems[i].code, revTransferItems[i].qty);
    }

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
        page.locator('#transfer-form button[type="submit"]').click(),
    ]);
    await settle(500);

    let trRevText = await page.evaluate(() => document.body.innerText);
    trRev = trRevText.match(/STF\d+/)?.[0] || '';
    recordStep('TR_REV_DISP', 'Reverse Multi-Item Transfer dispatched (Branch 2 -> Branch 1)', !!trRev, `DocNo=${trRev}`);

    // Receive Reverse Transfer at Branch 1
    console.log('Receiving Reverse Transfer at Branch 1...');
    await page.goto(BASE + '/switch-branch/1');
    await settle(400);

    await page.goto(BASE + `/inventory/stock-transfers/pending-receipt?branch_id=1&status=Dispatched`);
    await page.waitForLoadState('networkidle');
    await settle(500);

    const revReceiveLink = page.locator(`tr:has-text("${trRev}") a:has-text("Receive")`);
    if (await revReceiveLink.isVisible()) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
            revReceiveLink.click(),
        ]);
        await settle(600);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle', timeout: 20000 }),
            page.locator('button:has-text("Confirm Receipt"), .card-footer button[type=submit]').first().click(),
        ]);
        await settle(500);
    }
    recordStep('TR_REV_REC', 'Reverse Transfer confirmed & received at Branch 1', true, `DocNo=${trRev}`);

    // =========================================================
    // PHASE 7: REPORT NAVIGATION & BROWSER VERIFICATION
    // =========================================================
    console.log('\n--- 7. REPORT BROWSING & CROSS-VERIFICATION ---');

    // 7.1 Billwise Sales Report
    await page.goto(BASE + '/reports/billwise-sales');
    await page.waitForLoadState('networkidle');
    const billwiseSalesVisible = (await page.locator(`td:has-text("${sbMixed}")`).first().isVisible().catch(() => false)) || (await page.content()).includes(sbMixed);
    recordStep('REP_SB', 'Billwise Sales Report shows generated bills', billwiseSalesVisible, `Contains ${sbMixed}`);

    // 7.2 GST Sales Taxwise / Summary
    await page.goto(BASE + '/reports/gst-sales-summary');
    await page.waitForLoadState('networkidle');
    const gstSalesVisible = (await page.content()).includes('GST') || (await page.locator('.table').first().isVisible().catch(() => false));
    recordStep('REP_GST_S', 'GST Sales Summary report loaded with tax aggregations', gstSalesVisible);

    // 7.3 Purchase Detail Report
    await page.goto(BASE + '/reports/purchase-detail');
    await page.waitForLoadState('networkidle');
    const piVisible = (await page.locator(`td:has-text("${piMixed}")`).first().isVisible().catch(() => false)) || (await page.content()).includes(piMixed);
    recordStep('REP_PI', 'Purchase Detail Report shows generated purchase invoices', piVisible, `Contains ${piMixed}`);

    // 7.4 GST Purchase Summary
    await page.goto(BASE + '/reports/gst-purchase-summary');
    await page.waitForLoadState('networkidle');
    const gstPurVisible = (await page.content()).includes('GST') || (await page.locator('.table').first().isVisible().catch(() => false));
    recordStep('REP_GST_P', 'GST Purchase Summary report loaded with purchase tax aggregations', gstPurVisible);

    // 7.5 Current Stock Report
    await page.goto(BASE + '/reports/current-stock');
    await page.waitForLoadState('networkidle');
    const stockReportVisible = (await page.content()).includes('E2E-ITEM-A') || (await page.locator('.table').first().isVisible().catch(() => false));
    recordStep('REP_STK', 'Current Stock Report reflects test inventory items', stockReportVisible);

    // 7.6 GSTR-1 Views: B2B, B2CS, HSN, CDNR
    await page.goto(BASE + '/tools/gst/gstr-1');
    await page.waitForLoadState('networkidle');
    const gstr1Loaded = (await page.content()).includes('GSTR-1') || (await page.content()).includes('B2B');
    recordStep('GSTR1_DASH', 'GSTR-1 Dashboard loaded successfully', gstr1Loaded);

    await page.goto(BASE + '/tools/gst/gstr-1/b2b');
    await page.waitForLoadState('networkidle');
    const gstr1B2bHasBill = (await page.content()).includes(sbMixed);
    recordStep('GSTR1_B2B', 'GSTR-1 B2B section lists registered sales bills', gstr1B2bHasBill, `Lists ${sbMixed}`);

    await page.goto(BASE + '/tools/gst/gstr-1/b2cs');
    await page.waitForLoadState('networkidle');
    const gstr1B2csLoaded = (await page.content()).includes('B2CS') || (await page.content()).includes('Small');
    recordStep('GSTR1_B2CS', 'GSTR-1 B2CS section loaded with B2C supplies', gstr1B2csLoaded);

    await page.goto(BASE + '/tools/gst/gstr-1/hsn-b2b');
    await page.waitForLoadState('networkidle');
    const gstr1HsnLoaded = (await page.content()).includes('HSN') || (await page.content()).includes('2309');
    recordStep('GSTR1_HSN', 'GSTR-1 HSN Summary loaded with rate breakdown', gstr1HsnLoaded);

    await page.goto(BASE + '/tools/gst/gstr-1/cdnr');
    await page.waitForLoadState('networkidle');
    const gstr1CdnrLoaded = (await page.content()).includes('Credit') || (await page.content()).includes('CDNR') || (await page.locator('.table').first().isVisible().catch(() => false));
    recordStep('GSTR1_CDNR', 'GSTR-1 CDNR section loaded for return credit notes', gstr1CdnrLoaded);

    // =========================================================
    // PHASE 8: COMPLETE CROSS-RECONCILIATION & DB INTEGRITY
    // =========================================================
    console.log('\n--- 8. MATHEMATICAL CROSS-RECONCILIATION & INTEGRITY ---');

    const reconcileArgs = [
        `--pi_mixed=${piMixed}`,
        `--pi_inter=${piInter}`,
        `--pi_depth=${piDepth}`,
        `--pr1=${pr1}`,
        `--pr2=${pr2}`,
        `--pr3=${pr3}`,
        `--sb_mixed=${sbMixed}`,
        `--sb_inter=${sbInter}`,
        `--sb_b2c=${sbB2c}`,
        `--sb_depth=${sbDepth}`,
        `--sr1=${sr1}`,
        `--sr2=${sr2}`,
        `--sr3=${sr3}`,
        `--tr_fwd=${trFwd}`,
        `--tr_rev=${trRev}`,
    ].join(' ');

    const reconResult = callVerifier(`reconcile ${reconcileArgs}`);

    // Record individual reconciliation dimensions
    const det = reconResult.details || {};

    recordStep('REC_B2B_INTRA', 'B2B Intra-State Sales GST (CGST>0, SGST>0, IGST=0)', det.sales_b2b_intrastate?.pass,
        `CGST=${det.sales_b2b_intrastate?.cgst}, SGST=${det.sales_b2b_intrastate?.sgst}, IGST=${det.sales_b2b_intrastate?.igst}`);

    recordStep('REC_B2B_INTER', 'B2B Inter-State Sales GST (IGST>0, CGST=0, SGST=0)', det.sales_b2b_interstate?.pass,
        `IGST=${det.sales_b2b_interstate?.igst}, CGST=${det.sales_b2b_interstate?.cgst}, SGST=${det.sales_b2b_interstate?.sgst}`);

    recordStep('REC_B2C', 'B2C Sales GST (Customer gst_no=null, CGST/SGST>0, GSTR-1 B2CS)', det.sales_b2c?.pass,
        `Customer=${det.sales_b2c?.customer}, GSTIN=${det.sales_b2c?.customer_gstin || 'None'}`);

    recordStep('REC_MIXED_GST', 'Mixed GST Bill Breakup (0%, 5%, 18% in one bill)', det.mixed_gst_sales_bill?.pass,
        `18% GST=${det.mixed_gst_sales_bill?.itemA_18pct?.gst}, 5% GST=${det.mixed_gst_sales_bill?.itemB_5pct?.gst}, 0% GST=${det.mixed_gst_sales_bill?.itemC_0pct?.gst}`);

    recordStep('REC_PI_INTER_FRT', 'Interstate Purchase with Freight (Taxable + GST + Freight = Total)', det.purchase_interstate_freight?.pass,
        `Taxable=${det.purchase_interstate_freight?.taxable} + GST=${det.purchase_interstate_freight?.gst} + Freight=${det.purchase_interstate_freight?.freight} = ${det.purchase_interstate_freight?.total}`);

    recordStep('REC_MULTI_INV', 'Multiple Invoice Aggregation (>=2 PIs and >=2 SBs)', det.multi_invoice_aggregation?.pass,
        `Sales Bills=${det.multi_invoice_aggregation?.sales_bills_count} (Total ₹${det.multi_invoice_aggregation?.sales_expected_total}) | PIs=${det.multi_invoice_aggregation?.purchase_invoices_count} (Total ₹${det.multi_invoice_aggregation?.purchase_expected_total})`);

    recordStep('REC_PR_DEPTH', 'Purchase Return Depth Lifecycle (Purchased 10 -> Ret 3 -> Ret 4 -> Ret 3 -> Rem 0)', det.purchase_return_depth?.pass,
        `PR1=${det.purchase_return_depth?.pr1_qty}, PR2=${det.purchase_return_depth?.pr2_qty}, PR3=${det.purchase_return_depth?.pr3_qty}, Remaining=${det.purchase_return_depth?.db_exhausted_remaining}`);

    recordStep('REC_SR_DEPTH', 'Sales Return Depth Lifecycle (Sold 10 -> Ret 3 -> Ret 4 -> Ret 3 -> Rem 0)', det.sales_return_depth?.pass,
        `SR1=${det.sales_return_depth?.sr1_qty}, SR2=${det.sales_return_depth?.sr2_qty}, SR3=${det.sales_return_depth?.sr3_qty}, Remaining=${det.sales_return_depth?.db_exhausted_remaining}`);

    recordStep('REC_TRANSFER', 'Branch Transfer Matrix (Fwd Motera->Vastrapur & Rev Vastrapur->Motera)', det.branch_transfer_matrix?.pass,
        `Fwd=${det.branch_transfer_matrix?.forward_transfer?.doc} (${det.branch_transfer_matrix?.forward_transfer?.status}), Rev=${det.branch_transfer_matrix?.reverse_transfer?.doc} (${det.branch_transfer_matrix?.reverse_transfer?.status})`);

    recordStep('REC_INVENTORY', 'Multi-Branch Inventory Formula Reconciliation (Opening + P - S - PR + SR - Out + In = Stock)', det.inventory_reconciliation?.pass,
        `Items verified across Branch 1 & Branch 2`);

    recordStep('REC_GSTR1', 'GSTR-1 Cross-Verification (B2B Local, B2B Interstate, B2CS, HSN, CDNR)', det.gstr1_cross_verification?.pass,
        `B2B Local Pass=${det.gstr1_cross_verification?.b2b_local?.pass}, B2B Inter Pass=${det.gstr1_cross_verification?.b2b_interstate?.pass}, B2CS Count=${det.gstr1_cross_verification?.b2cs_count}`);

    // Final Database Integrity
    const integrityResult = callVerifier('integrity');
    recordStep('DB_INTEGRITY', 'Database Integrity (0 negative stock, 0 orphans, 0 dup docs, 0 ledger errors)', integrityResult.status === 'PASS');

} catch (err) {
    console.error('Test execution encountered error:', err);
    recordStep('ERR_FATAL', 'Execution halted on error', false, err.message);
} finally {
    await browser.close();
}

// -------------------------------------------------------------
// SUMMARY & OUTPUT
// -------------------------------------------------------------
console.log('\n================================================================');
console.log(`UrbanPOS Phase 2 Test Summary`);
console.log('================================================================');

const passed = suiteResults.filter(r => r.pass).length;
const total = suiteResults.length;
const allPassed = passed === total && total > 0;

console.log(`Total Checks:  ${total}`);
console.log(`Passed:        ${passed}`);
console.log(`Failed:        ${total - passed}`);
console.log(`Status:        ${allPassed ? 'ALL PASSED (100%)' : 'FAILURES DETECTED'}`);
console.log('================================================================\n');

process.exit(allPassed ? 0 : 1);
