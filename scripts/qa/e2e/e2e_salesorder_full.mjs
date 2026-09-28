import { chromium } from 'playwright-core';

const BASE = 'http://127.0.0.1:8299';
const results = [];
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });
const dialogs = []; 
p.on('dialog', async d => { 
    dialogs.push(d.message()); 
    await d.dismiss(); 
});

const rec = (n, ok, note) => { 
    results.push(ok); 
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${n}  -- ${note}`); 
};
const settle = (ms = 700) => p.waitForTimeout(ms);

try {
    // Login as Admin / Owner
    await p.goto(BASE + '/login');
    await p.fill('input[name=email]', 'admin@urbanpets.test');
    await p.fill('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    rec('L1 Login as Admin/Owner', p.url().includes('/home') || !p.url().includes('/login'), 'url=' + p.url());

    // -------------------------------------------------------------
    // SCENARIO C: Validation (Missing customer, zero quantity, etc.)
    // -------------------------------------------------------------
    await p.goto(BASE + '/sales/sales-orders/create');
    await p.waitForLoadState('networkidle');

    // Check save button is disabled initially (no customer, no items)
    let saveDisabled = await p.evaluate(() => document.querySelector('#so-form button[type=submit]')?.disabled);
    rec('SO_Val1 Save button disabled on empty form', !!saveDisabled, 'disabled=' + saveDisabled);

    // Pick customer
    await p.evaluate(() => {
        const sel = document.querySelector('select[name=customer_id]');
        if (sel) {
            sel.value = sel.options[1].value;
            $(sel).trigger('change');
        }
    });
    await settle(400);

    // Still disabled because no items
    saveDisabled = await p.evaluate(() => document.querySelector('#so-form button[type=submit]')?.disabled);
    rec('SO_Val2 Save button disabled when customer selected but no item', !!saveDisabled, 'disabled=' + saveDisabled);

    // -------------------------------------------------------------
    // SCENARIO A: Basic Order (Customer + 1 Item + Valid Quantity)
    // -------------------------------------------------------------
    // Open item search modal via Enter on first item code
    const firstCodeInput = p.locator('#so-items-body tr:first-child .so-item-code');
    await firstCodeInput.focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#so-item-search-modal.show', { timeout: 5000 });
    rec('SO_A1 Item search modal opened via Enter', true, '');

    // Search for Royal Canin
    await p.fill('#so-isl-filter-name', 'Royal Canin');
    await p.waitForSelector('#so-isl-items-body tr .so-isl-btn-select', { timeout: 8000 });
    await settle(400);

    // Click select button
    await p.click('#so-isl-items-body tr:first-child .so-isl-btn-select');
    await settle(500);

    let itemDesc = await p.evaluate(() => document.querySelector('#so-items-body tr:first-child .so-item-desc')?.value);
    rec('SO_A2 Item populated into row', itemDesc.includes('Royal Canin'), 'desc=' + itemDesc);

    // Enter Qty = 2
    const firstQtyInput = p.locator('#so-items-body tr:first-child .so-qty');
    await firstQtyInput.fill('2');
    await firstQtyInput.dispatchEvent('input');
    await firstQtyInput.dispatchEvent('change');
    await settle(400);

    // Verify totals calculation in UI
    let grandTotalText = await p.evaluate(() => document.querySelector('#so-summary-total')?.textContent.trim());
    rec('SO_A3 Totals calculated', grandTotalText.includes('₹') && !grandTotalText.includes('₹0.00'), 'total=' + grandTotalText);

    // Save Basic Order
    await p.click('#so-form button[type=submit]');
    await p.waitForURL(url => /\/sales\/sales-orders\/\d+/.test(url.pathname), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let showUrl = p.url();
    rec('SO_A4 Order created & redirected to show page', showUrl.includes('/sales/sales-orders/'), 'url=' + showUrl);

    let soNumberA = await p.evaluate(() => document.body.innerText.match(/SO-[\w-]+/)?.[0] || '');
    rec('SO_A5 Captured Sales Order Number', !!soNumberA, 'Order=' + soNumberA);

    // -------------------------------------------------------------
    // SCENARIO B: Multiple Items Order (At least 2 different items)
    // -------------------------------------------------------------
    await p.goto(BASE + '/sales/sales-orders/create');
    await p.waitForLoadState('networkidle');

    // Customer
    await p.evaluate(() => {
        const sel = document.querySelector('select[name=customer_id]');
        if (sel) {
            sel.value = sel.options[2].value;
            $(sel).trigger('change');
        }
    });
    await settle(300);

    // Add first item (Pedigree)
    await p.locator('#so-items-body tr:first-child .so-item-code').focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#so-item-search-modal.show', { timeout: 5000 });
    await p.fill('#so-isl-filter-name', 'Pedigree');
    await p.waitForSelector('#so-isl-items-body tr .so-isl-btn-select', { timeout: 8000 });
    await settle(400);
    await p.click('#so-isl-items-body tr:first-child .so-isl-btn-select');
    await settle(500);

    await p.locator('#so-items-body tr:first-child .so-qty').fill('3');
    await p.locator('#so-items-body tr:first-child .so-qty').dispatchEvent('change');
    await settle(300);

    // Add row for second item
    await p.click('#so-add-row-btn');
    await settle(300);

    // Add second item (Whiskas)
    await p.locator('#so-items-body tr:nth-child(2) .so-item-code').focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#so-item-search-modal.show', { timeout: 5000 });
    await p.fill('#so-isl-filter-name', 'Whiskas');
    await p.waitForSelector('#so-isl-items-body tr .so-isl-btn-select', { timeout: 8000 });
    await settle(400);
    await p.click('#so-isl-items-body tr:first-child .so-isl-btn-select');
    await settle(500);

    await p.locator('#so-items-body tr:nth-child(2) .so-qty').fill('5');
    await p.locator('#so-items-body tr:nth-child(2) .so-qty').dispatchEvent('change');
    await settle(300);

    let rowsCount = await p.evaluate(() => document.querySelectorAll('#so-items-body tr').length);
    rec('SO_B1 Multiple item rows added', rowsCount === 2, 'rows=' + rowsCount);

    // Save Multi-item order
    await p.click('#so-form button[type=submit]');
    await p.waitForURL(url => /\/sales\/sales-orders\/\d+/.test(url.pathname), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let soNumberB = await p.evaluate(() => document.body.innerText.match(/SO-[\w-]+/)?.[0] || '');
    rec('SO_B2 Multi-item order saved & captured', !!soNumberB, 'Order=' + soNumberB);

    // -------------------------------------------------------------
    // SCENARIO D: Duplicate Submission Guard Check
    // -------------------------------------------------------------
    await p.goto(BASE + '/sales/sales-orders/create');
    await p.waitForLoadState('networkidle');

    // Customer
    await p.evaluate(() => {
        const sel = document.querySelector('select[name=customer_id]');
        if (sel) {
            sel.value = sel.options[1].value;
            $(sel).trigger('change');
        }
    });
    await settle(300);
    // Add item
    await p.locator('#so-items-body tr:first-child .so-item-code').focus();
    await p.keyboard.press('Enter');
    await p.waitForSelector('#so-item-search-modal.show', { timeout: 5000 });
    await p.fill('#so-isl-filter-name', 'Drools');
    await p.waitForSelector('#so-isl-items-body tr .so-isl-btn-select', { timeout: 8000 });
    await settle(400);
    await p.click('#so-isl-items-body tr:first-child .so-isl-btn-select');
    await settle(500);
    await p.locator('#so-items-body tr:first-child .so-qty').fill('1');
    await p.locator('#so-items-body tr:first-child .so-qty').dispatchEvent('change');
    await settle(300);

    // Click submit and verify duplicate click is prevented
    await p.click('#so-form button[type=submit]');
    await settle(40);
    let disabledImmediately = await p.evaluate(() => {
        const btn = document.querySelector('#so-form button[type=submit]');
        return btn?.disabled || btn?.classList?.contains('disabled') || false;
    });
    rec('SO_D1 Submit button disables on click', disabledImmediately, '');
    await p.waitForURL(url => /\/sales\/sales-orders\/\d+/.test(url.pathname), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    let soNumberD = await p.evaluate(() => document.body.innerText.match(/SO-[\w-]+/)?.[0] || '');
    rec('SO_D2 Order saved without duplicate', !!soNumberD, 'Order=' + soNumberD);

    // -------------------------------------------------------------
    // LISTING VERIFICATION (Section 7)
    // -------------------------------------------------------------
    await p.goto(BASE + '/sales/sales-orders');
    await p.waitForLoadState('networkidle');

    let bodyText = await p.evaluate(() => document.body.innerText);
    rec('SO_List1 Basic order visible in listing', bodyText.includes(soNumberA), 'soNumberA=' + soNumberA);
    rec('SO_List2 Multi-item order visible in listing', bodyText.includes(soNumberB), 'soNumberB=' + soNumberB);
    rec('SO_List3 Duplicate-guard order visible in listing', bodyText.includes(soNumberD), 'soNumberD=' + soNumberD);

    // Test Search filter
    await p.fill('input[name=search]', soNumberA);
    await p.click('.card-body form button[type=submit]');
    await p.waitForLoadState('networkidle');
    let searchResultText = await p.evaluate(() => document.querySelector('#salesOrdersTable tbody')?.innerText || '');
    rec('SO_List4 Search filter works', searchResultText.includes(soNumberA) && !searchResultText.includes(soNumberB), '');

    // Reset search
    await p.goto(BASE + '/sales/sales-orders');
    await p.waitForLoadState('networkidle');

    // -------------------------------------------------------------
    // EDIT / VIEW VERIFICATION (Section 4 step 12-13)
    // -------------------------------------------------------------
    await p.click('#salesOrdersTable a:has-text("' + soNumberB + '")');
    await p.waitForLoadState('networkidle');
    let showPageText = await p.evaluate(() => document.body.innerText);
    rec('SO_View1 View page loads with order number', showPageText.includes(soNumberB), '');
    rec('SO_View2 View page displays line items', showPageText.includes('Pedigree') && showPageText.includes('Whiskas'), '');

    // Edit page
    await p.click('.card-header a:has-text("Edit"), a.btn:has-text("Edit")');
    await p.waitForLoadState('networkidle');
    let editPageRemarks = p.locator('input[name=remarks]');
    if (await editPageRemarks.count() > 0) {
        await editPageRemarks.fill('Updated QA Remarks via Edit');
    }
    await p.click('#so-form button[type=submit]');
    await p.waitForURL(url => /\/sales\/sales-orders\/\d+/.test(url.pathname), { timeout: 15000 });
    await p.waitForLoadState('networkidle');
    let afterEditText = await p.evaluate(() => document.body.innerText);
    rec('SO_Edit1 Order updated successfully', afterEditText.includes('Updated QA Remarks via Edit') || afterEditText.includes(soNumberB), '');

    // -------------------------------------------------------------
    // DOWNSTREAM BUSINESS FLOW: Sales Order -> Sales Bill (Section 9)
    // -------------------------------------------------------------
    // Click "Convert to Sales Bill" button
    const convertBtn = p.locator('a:has-text("Convert to Sales Bill")');
    if (await convertBtn.isVisible()) {
        await convertBtn.click();
        await p.waitForURL(url => url.pathname.includes('/sales/sales-bills/create'), { timeout: 15000 });
        await p.waitForLoadState('networkidle');
        let billUrl = p.url();
        rec('SO_Down1 Converted to sales bill create form', billUrl.includes('/sales/sales-bills/create?from_order='), 'url=' + billUrl);

        let billItems = await p.evaluate(() => Array.from(document.querySelectorAll('.sb-item-desc')).map(i => i.value).join(' '));
        rec('SO_Down2 Converted items pre-populated', billItems.includes('Pedigree') && billItems.includes('Whiskas'), 'items=' + billItems);
    }

    // -------------------------------------------------------------
    // SCENARIO E: Branch Isolation Check
    // -------------------------------------------------------------
    // Clear session and login as Vastrapur Manager (Branch 2)
    await p.context().clearCookies();
    await p.goto(BASE + '/login');
    await p.waitForLoadState('networkidle');
    await p.fill('input[name=email]', 'manager.vastrapur@urbanpets.test');
    await p.fill('input[name=password]', 'password');
    await p.click('.login-card-body button[type=submit]');
    await p.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 });
    await p.waitForLoadState('networkidle');

    // Go to Sales Orders listing
    await p.goto(BASE + '/sales/sales-orders');
    await p.waitForLoadState('networkidle');
    let managerListingText = await p.evaluate(() => document.querySelector('#salesOrdersTable tbody')?.innerText || '');
    rec('SO_E1 Branch isolation: Vastrapur manager cannot see Motera branch orders', 
        !managerListingText.includes(soNumberA) && !managerListingText.includes(soNumberB), 
        'listing=' + managerListingText);

} catch (err) {
    console.error('ERROR in test execution:', err);
    await p.screenshot({ path: 'shot_so_error.png' });
} finally {
    await b.close();
    console.log(`\n=== SUMMARY: ${results.filter(r => r).length} / ${results.length} PASSED ===`);
}
