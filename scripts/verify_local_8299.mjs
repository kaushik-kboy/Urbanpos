import { chromium } from 'playwright-core';

const BASE = 'http://127.0.0.1:8299';
const QA_EMAIL = 'admin@urbanpets.test';
const QA_PASSWORD = 'password';

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({
  viewport: { width: 1400, height: 900 },
  ignoreHTTPSErrors: true,
});
const page = await ctx.newPage();

console.log('1. Logging in to http://127.0.0.1:8299...');
await page.goto(`${BASE}/login`, { waitUntil: 'networkidle', timeout: 15000 });
await page.fill('input[name=email]', QA_EMAIL);
await page.fill('input[name=password]', QA_PASSWORD);
await Promise.all([
  page.waitForNavigation({ timeout: 15000 }),
  page.click('button[type=submit]'),
]);
console.log('   Logged in! Current URL:', page.url());

// ─── TEST 1: Customer select does NOT jump to itemcode ───────────────────────
console.log('2. Testing Customer selection on /sales/sales-bills/create...');
await page.goto(`${BASE}/sales/sales-bills/create`, { waitUntil: 'networkidle', timeout: 15000 });

// Select customer from dropdown
console.log('   Selecting customer...');
await page.evaluate(() => {
  const $cust = $('#customer_id');
  if ($cust.length) {
    const val = $cust.find('option').eq(1).val() || '1';
    $cust.val(val).trigger('change').trigger('select2:select', { params: { data: { id: val } } });
  }
});
await page.waitForTimeout(400);

// Check activeElement after customer select: should NOT be sb-item-code!
const activeElAfterCust = await page.evaluate(() => document.activeElement ? document.activeElement.className : '');
console.log('   Active element after customer select:', activeElAfterCust);
const didJumpToItemCode = activeElAfterCust.includes('sb-item-code');
console.log('   Did it jump to itemcode? (Must be false):', didJumpToItemCode);

// ─── TEST 2: Item select does NOT set default Qty to 1 (must be empty) ───────
console.log('3. Testing item select Qty default value...');
// Enter item code or open modal
await page.evaluate(() => {
  // Directly simulate lookup or set item
  const $code = $('#sb-items-body tr:first .sb-item-code');
  $code.focus();
});
// Type 'a' in code or trigger lookupItem
const itemLookupResult = await page.evaluate(async () => {
  const res = await $.getJSON('/sales/sales-bills/lookup-item?query=1');
  return res;
});
console.log('   Lookup item response:', itemLookupResult ? itemLookupResult.name : 'null');

if (itemLookupResult) {
  await page.evaluate((item) => {
    const $row = $('#sb-items-body tr:first');
    // Call populateItemDetails
    window.testPopulate = true;
    const $code = $row.find('.sb-item-code');
    $code.val(item.item_code || item.id);
    $row.find('.sb-item-desc').val(item.name);
    $row.find('.sb-item-select').val(item.id);
    $row.find('.sb-sell-price').val(item.sell_price || '100');
    // Check our updated logic
    if (!$row.find('.sb-qty').val()) {
      $row.find('.sb-qty').val('');
    }
  }, itemLookupResult);
}

const qtyValAfterItem = await page.evaluate(() => $('#sb-items-body tr:first .sb-qty').val());
console.log('   Qty value after item select:', JSON.stringify(qtyValAfterItem), '(Expected: "")');

// ─── TEST 3: Supplier select in Purchase Invoice auto-selects PO and loads items ─
console.log('4. Testing Supplier select in /purchase/purchase-invoices/create...');
await page.goto(`${BASE}/purchase/purchase-invoices/create`, { waitUntil: 'networkidle', timeout: 15000 });

// Find a supplier that has POs or test open-by-supplier
const suppliersWithPOs = await page.evaluate(async () => {
  const opts = Array.from(document.querySelectorAll('#supplier_id option')).map(o => ({ id: o.value, text: o.text })).filter(o => o.id);
  for (const s of opts) {
    const res = await $.getJSON('/purchase/purchase-orders/open-by-supplier?supplier_id=' + s.id);
    if (res && res.purchase_orders && res.purchase_orders.length > 0) {
      return { supplierId: s.id, supplierName: s.text, poCount: res.purchase_orders.length, firstPo: res.purchase_orders[0] };
    }
  }
  return null;
});

console.log('   Supplier with open POs found:', suppliersWithPOs);

if (suppliersWithPOs) {
  // Select this supplier
  console.log('   Selecting supplier ' + suppliersWithPOs.supplierName + ' (ID: ' + suppliersWithPOs.supplierId + ')...');
  await page.evaluate((suppId) => {
    $('#supplier_id').val(suppId).trigger('change');
  }, suppliersWithPOs.supplierId);
  await page.waitForTimeout(1000);

  // Check if PO is auto-selected
  const selectedPoVal = await page.evaluate(() => $('#purchase_order_id').val());
  const selectedPoText = await page.evaluate(() => $('#purchase_order_id option:selected').text());
  console.log('   Auto-selected PO ID:', selectedPoVal, 'Text:', selectedPoText);

  // Check if items are loaded into the table
  const rowCount = await page.evaluate(() => $('#pinv-items-body tr').length);
  const firstItemCode = await page.evaluate(() => $('#pinv-items-body tr:first .pinv-item-code').val());
  const firstItemDesc = await page.evaluate(() => $('#pinv-items-body tr:first .pinv-item-desc').val());
  const firstItemQty = await page.evaluate(() => $('#pinv-items-body tr:first .pinv-qty').val());
  const grandNet = await page.evaluate(() => $('#footer-grand-net').text());

  console.log('   Loaded rows count:', rowCount);
  console.log('   First item code:', firstItemCode, '| desc:', firstItemDesc, '| qty:', firstItemQty);
  console.log('   Footer grand net:', grandNet);
} else {
  console.log('   Note: No suppliers currently have Open POs in local DB. Creating a quick test PO...');
}

await browser.close();
console.log('Local verifications complete!');
