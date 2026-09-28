import { chromium } from 'playwright-core';
const BASE = 'http://127.0.0.1:8299';
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 1600, height: 1000 }, acceptDownloads: true });
await p.goto(BASE + '/login'); await p.fill('input[name=email]', 'qa-load-owner@example.com'); await p.fill('input[name=password]', 'secret123');
await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
const downloadPromise = p.waitForEvent('download');
try { await p.goto(BASE + '/reports/gst-sales-taxwise/export?from=2025-10-29&to=2025-10-29&branch_id=all'); } catch (e) {}
const download = await downloadPromise;
await download.saveAs('/tmp/gst_export_test3.xlsx');
console.log('saved');
await b.close();
