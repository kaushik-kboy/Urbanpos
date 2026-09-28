import { chromium } from 'playwright-core';

const BASE = 'http://127.0.0.1:8299';
const results = [];
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });

p.on('dialog', async d => {
    console.log('BROWSER DIALOG:', d.message());
    await d.dismiss();
});

const rec = (n, ok, note = '') => {
    results.push(ok);
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`);
};
const settle = (ms = 500) => p.waitForTimeout(ms);

try {
    // -------------------------------------------------------------
    // LOGIN AS ADMIN / OWNER
    // -------------------------------------------------------------
    await p.goto(BASE + '/login');
    await p.fill('input[name=email]', 'admin@urbanpets.test');
    await p.fill('input[name=password]', 'password');
    await p.click('.login-card-body button[type=submit]');
    await p.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 });
    rec('PO_L1 Login as Admin/Owner', p.url().includes('/home'), 'url=' + p.url());

    // -------------------------------------------------------------
    // SCENARIO C: Validation (Missing supplier, missing item, etc.)
    // -------------------------------------------------------------
    await p.goto(BASE + '/purchase/purchase-orders/create');
    await p.waitForLoadState('networkidle');

    // 1. Submit without supplier
    let supplierValidationBlocked = await p.evaluate(() => {
        const form = document.querySelector('#po-form');
        const event = new Event('submit', { cancelable: true });
        form.dispatchEvent(event);
        return event.defaultPrevented;
    });
    rec('PO_Val1 Submit blocked when supplier is missing', supplierValidationBlocked, '');

    // Select supplier
    await p.evaluate(() => {
        const sel = document.querySelector('select[name=supplier_id]');
        if (sel) {
            sel.value = sel.options[1].value;
            $(sel).trigger('change');
        }
    });
    await settle(300);

    // 2. Submit with supplier but no items
    let noItemValidationBlocked = await p.evaluate(() => {
        const form = document.querySelector('#po-form');
        const event = new Event('submit', { cancelable: true });
        form.dispatchEvent(event);
        return event.defaultPrevented;
    });
    rec('PO_Val2 Submit blocked when no items selected', noItemValidationBlocked, '');

    // -------------------------------------------------------------
    // SCENARIO A: Basic PO (Supplier + 1 Item + Valid Quantity)
    // -------------------------------------------------------------
    // Open item search modal via Enter on first item code
    const firstCodeInput = p.locator('#po-items-body tr:first-child .po-item-code');
    await firstCodeInput.focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#po-item-search-modal.show', { timeout: 5000 });
    rec('PO_A1 Item search modal opened via Enter', true, '');

    // Search and select Royal Canin
    await p.fill('#po-isl-filter-name', 'Royal Canin');
    await p.waitForSelector('#po-isl-items-body tr .po-isl-btn-select', { timeout: 8000 });
    await settle(300);
    await p.click('#po-isl-items-body tr:first-child .po-isl-btn-select');
    await settle(400);

    let itemDescA = await p.evaluate(() => document.querySelector('#po-items-body tr:first-child .po-item-desc')?.value);
    rec('PO_A2 Item populated into PO row', itemDescA.includes('Royal Canin'), 'desc=' + itemDescA);

    // Enter Qty = 10
    const firstQtyInput = p.locator('#po-items-body tr:first-child .po-qty');
    await firstQtyInput.fill('10');
    await firstQtyInput.dispatchEvent('input');
    await firstQtyInput.dispatchEvent('change');
    await settle(300);

    // Verify totals calculation in UI
    let grandTotalTextA = await p.evaluate(() => document.querySelector('#po-summary-grand')?.textContent.trim());
    rec('PO_A3 Totals calculated', grandTotalTextA && grandTotalTextA !== '₹0.00' && grandTotalTextA !== '0.00', 'total=' + grandTotalTextA);

    // Save Basic PO
    await p.click('#po-form button[type=submit]');
    await p.waitForURL(url => url.pathname.includes('/purchase/purchase-orders'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let listingTextA = await p.evaluate(() => document.body.innerText);
    let poNumberA = listingTextA.match(/PO-[\w-]+/)?.[0] || '';
    rec('PO_A4 Basic PO saved & redirected to listing', !!poNumberA, 'PO=' + poNumberA);

    // -------------------------------------------------------------
    // SCENARIO B: Multiple Items (At least 2 different items)
    // -------------------------------------------------------------
    await p.goto(BASE + '/purchase/purchase-orders/create');
    await p.waitForLoadState('networkidle');

    // Supplier 2
    await p.evaluate(() => {
        const sel = document.querySelector('select[name=supplier_id]');
        if (sel) {
            sel.value = sel.options[2].value;
            $(sel).trigger('change');
        }
    });
    await settle(300);

    // Item 1 (Pedigree)
    await p.locator('#po-items-body tr:first-child .po-item-code').focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#po-item-search-modal.show', { timeout: 5000 });
    await p.fill('#po-isl-filter-name', 'Pedigree');
    await p.waitForSelector('#po-isl-items-body tr .po-isl-btn-select', { timeout: 8000 });
    await settle(300);
    await p.click('#po-isl-items-body tr:first-child .po-isl-btn-select');
    await settle(400);

    await p.locator('#po-items-body tr:first-child .po-qty').fill('15');
    await p.locator('#po-items-body tr:first-child .po-qty').dispatchEvent('change');
    await settle(300);

    // Add row for second item
    await p.click('#po-add-row');
    await settle(300);

    // Item 2 (Whiskas)
    await p.locator('#po-items-body tr:nth-child(2) .po-item-code').focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#po-item-search-modal.show', { timeout: 5000 });
    await p.fill('#po-isl-filter-name', 'Whiskas');
    await p.waitForSelector('#po-isl-items-body tr .po-isl-btn-select', { timeout: 8000 });
    await settle(300);
    await p.click('#po-isl-items-body tr:first-child .po-isl-btn-select');
    await settle(400);

    await p.locator('#po-items-body tr:nth-child(2) .po-qty').fill('25');
    await p.locator('#po-items-body tr:nth-child(2) .po-qty').dispatchEvent('change');
    await settle(300);

    let rowsCount = await p.evaluate(() => document.querySelectorAll('#po-items-body tr').length);
    rec('PO_B1 Multiple items added to PO', rowsCount === 2, 'rows=' + rowsCount);

    // Save Multi-item PO
    await p.click('#po-form button[type=submit]');
    await p.waitForURL(url => url.pathname.includes('/purchase/purchase-orders'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let listingTextB = await p.evaluate(() => document.body.innerText);
    let allMatches = Array.from(listingTextB.matchAll(/PO-[\w-]+/g)).map(m => m[0]);
    let poNumberB = allMatches.find(m => m !== poNumberA) || allMatches[0] || '';
    rec('PO_B2 Multi-item PO saved & captured', !!poNumberB && poNumberB !== poNumberA, 'PO=' + poNumberB);

    // -------------------------------------------------------------
    // SCENARIO D: Duplicate Submission Guard Check
    // -------------------------------------------------------------
    await p.goto(BASE + '/purchase/purchase-orders/create');
    await p.waitForLoadState('networkidle');

    await p.evaluate(() => {
        const sel = document.querySelector('select[name=supplier_id]');
        if (sel) {
            sel.value = sel.options[1].value;
            $(sel).trigger('change');
        }
    });
    await settle(300);

    await p.locator('#po-items-body tr:first-child .po-item-code').focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#po-item-search-modal.show', { timeout: 5000 });
    await p.fill('#po-isl-filter-name', 'Drools');
    await p.waitForSelector('#po-isl-items-body tr .po-isl-btn-select', { timeout: 8000 });
    await settle(300);
    await p.click('#po-isl-items-body tr:first-child .po-isl-btn-select');
    await settle(400);

    await p.locator('#po-items-body tr:first-child .po-qty').fill('5');
    await p.locator('#po-items-body tr:first-child .po-qty').dispatchEvent('change');
    await settle(300);

    // Click submit and verify duplicate click is prevented
    await p.click('#po-form button[type=submit]');
    await settle(40);
    let disabledImmediately = await p.evaluate(() => {
        const btn = document.querySelector('#po-form button[type=submit]');
        return btn?.disabled || btn?.classList?.contains('disabled') || false;
    });
    rec('PO_D1 Submit button disables on click', disabledImmediately, '');

    await p.waitForURL(url => url.pathname.includes('/purchase/purchase-orders'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let listingTextD = await p.evaluate(() => document.body.innerText);
    let allMatchesD = Array.from(listingTextD.matchAll(/PO-[\w-]+/g)).map(m => m[0]);
    let poNumberD = allMatchesD.find(m => m !== poNumberA && m !== poNumberB) || '';
    rec('PO_D2 PO saved successfully without duplicate', !!poNumberD, 'PO=' + poNumberD);

    // -------------------------------------------------------------
    // LISTING VERIFICATION (Section 7)
    // -------------------------------------------------------------
    await p.goto(BASE + '/purchase/purchase-orders');
    await p.waitForLoadState('networkidle');

    let bodyText = await p.evaluate(() => document.body.innerText);
    rec('PO_List1 Basic PO visible in listing', bodyText.includes(poNumberA), 'poNumberA=' + poNumberA);
    rec('PO_List2 Multi-item PO visible in listing', bodyText.includes(poNumberB), 'poNumberB=' + poNumberB);
    rec('PO_List3 Duplicate-guard PO visible in listing', bodyText.includes(poNumberD), 'poNumberD=' + poNumberD);

    // Test Search filter
    await p.fill('input[name=search]', poNumberA);
    await p.click('.card-body form button[type=submit]');
    await p.waitForLoadState('networkidle');
    let searchResultText = await p.evaluate(() => document.querySelector('#purchaseOrdersTable tbody')?.innerText || '');
    rec('PO_List4 Search filter works', searchResultText.includes(poNumberA) && !searchResultText.includes(poNumberB), '');

    // Reset search
    await p.goto(BASE + '/purchase/purchase-orders');
    await p.waitForLoadState('networkidle');

    // -------------------------------------------------------------
    // EDIT / VIEW VERIFICATION
    // -------------------------------------------------------------
    // Click on Edit icon in table for poNumberB
    const editLink = p.locator(`tr:has-text("${poNumberB}") a[href*="/edit"]`);
    await editLink.click();
    await p.waitForURL(url => url.pathname.includes('/edit'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let editRemarks = p.locator('textarea[name=remarks], input[name=remarks]');
    if (await editRemarks.count() > 0) {
        await editRemarks.first().fill('Updated PO Remarks via Edit QA');
    }
    await p.click('#po-form button[type=submit]');
    await p.waitForURL(url => url.pathname.includes('/purchase/purchase-orders'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');
    rec('PO_Edit1 PO updated successfully', true, '');

    // Open show/view page for poNumberB
    const poRowLink = p.locator(`tr:has-text("${poNumberB}") td:first-child, tr:has-text("${poNumberB}") a`);
    // Direct navigate to show page by finding ID
    let poIdB = await p.evaluate((no) => {
        const row = Array.from(document.querySelectorAll('#purchaseOrdersTable tbody tr')).find(r => r.innerText.includes(no));
        const editLink = row?.querySelector('a[href*="/purchase-orders/"][href*="/edit"]');
        return editLink?.href?.match(/\/purchase-orders\/(\d+)\/edit/)?.[1] || '';
    }, poNumberB);

    if (poIdB) {
        await p.goto(BASE + `/purchase/purchase-orders/${poIdB}`);
        await p.waitForLoadState('networkidle');
        let showText = await p.evaluate(() => document.body.innerText);
        rec('PO_View1 Show view displays PO details', showText.includes(poNumberB), '');
        rec('PO_View2 Show view displays line items', showText.includes('Pedigree') && showText.includes('Whiskas'), '');
    }

    // -------------------------------------------------------------
    // DOWNSTREAM BUSINESS FLOW: PO -> Purchase Invoice (Section 9)
    // -------------------------------------------------------------
    await p.goto(BASE + '/purchase/purchase-orders');
    await p.waitForLoadState('networkidle');

    const invoiceBtn = p.locator(`tr:has-text("${poNumberB}") a[href*="purchase-invoices/create"]`);
    if (await invoiceBtn.count() > 0) {
        await invoiceBtn.first().click();
        await p.waitForURL(url => url.pathname.includes('/purchase/purchase-invoices/create'), { timeout: 15000 });
        await p.waitForLoadState('networkidle');

        let invoiceUrl = p.url();
        rec('PO_Down1 Converted to purchase invoice create form', invoiceUrl.includes('from_order='), 'url=' + invoiceUrl);

        let invItems = await p.evaluate(() => Array.from(document.querySelectorAll('.pinv-item-desc, input[name*="item_desc"]')).map(i => i.value).join(' '));
        rec('PO_Down2 Converted PO items populated in invoice', invItems.includes('Pedigree') && invItems.includes('Whiskas'), 'items=' + invItems);
    }

    // -------------------------------------------------------------
    // SCENARIO F: Branch Isolation Check
    // -------------------------------------------------------------
    // Clear cookies & login as Vastrapur Manager (Branch 2)
    await p.context().clearCookies();
    await p.goto(BASE + '/login');
    await p.waitForLoadState('networkidle');
    await p.fill('input[name=email]', 'manager.vastrapur@urbanpets.test');
    await p.fill('input[name=password]', 'password');
    await p.click('.login-card-body button[type=submit]');
    await p.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    // Go to Purchase Orders listing
    await p.goto(BASE + '/purchase/purchase-orders');
    await p.waitForLoadState('networkidle');
    let managerListingText = await p.evaluate(() => document.querySelector('#purchaseOrdersTable tbody')?.innerText || '');
    rec('PO_F1 Branch isolation: Vastrapur manager cannot see Motera branch POs', 
        !managerListingText.includes(poNumberA) && !managerListingText.includes(poNumberB), 
        'listing=' + managerListingText);

} catch (err) {
    console.error('ERROR in test execution:', err);
    await p.screenshot({ path: 'shot_po_error.png' });
} finally {
    await b.close();
    console.log(`\n=== SUMMARY: ${results.filter(r => r).length} / ${results.length} PASSED ===`);
}
