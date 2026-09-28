// Realistic POS workflow load test.
//   node scripts/qa/load_test.mjs --users 100 --duration 90 --servers 16 [--manifest file.json] [--redis]
//
// --redis switches session/cache to the dedicated QA Redis (:6390, started separately — see
// scripts/qa/prod_config_test.php's header) via predis, and turns opcache.validate_timestamps off, matching
// Priority 6's recommended production config. Without it, this reproduces the original DB-session baseline.
//
// Starts N php built-in-server workers (opcache on) against the dedicated QA database, logs in `users`
// cashier accounts (real login + CSRF + session), then each virtual user loops a mixed POS workload with
// think-time until the duration ends. Reports throughput, p50/p95/p99, error rate per action, and the
// MySQL-side counters (deadlocks, lock waits, peak connections).
import { spawn, execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';
import path from 'node:path';

const arg = (k, d) => { const i = process.argv.indexOf('--' + k); return i > -1 ? process.argv[i + 1] : d; };
const USERS = +arg('users', 100), DURATION = +arg('duration', 90), SERVERS = +arg('servers', 16), BASE_PORT = 8200;
const USE_REDIS = process.argv.includes('--redis');
const ROOT = path.resolve(import.meta.dirname, '../..');
const PHP = 'php', MYSQL = 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe';
const manifest = JSON.parse(execFileSync(PHP, ['scripts/qa/load_prep.php', String(Math.max(USERS, 300))], { cwd: ROOT, maxBuffer: 1 << 26 }).toString());

const sql = (q) => execFileSync(MYSQL, ['-uroot', '-P3307', '-h127.0.0.1', '-N', '-B', 'urban_pos_qa', '-e', q]).toString().trim();
const status = (v) => +sql(`show global status like '${v}'`).split('\t')[1];
const before = { dead: +sql("select count from information_schema.innodb_metrics where name='lock_deadlocks'"), waits: status('Innodb_row_lock_waits'), waitMs: status('Innodb_row_lock_time') };

// ---- server pool -------------------------------------------------------------------------------------------
const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: '3307', DB_DATABASE: 'urban_pos_qa',
  ...(USE_REDIS
    ? { CACHE_STORE: 'redis', SESSION_DRIVER: 'redis', REDIS_CLIENT: 'predis', REDIS_HOST: '127.0.0.1', REDIS_PORT: '6390', REDIS_PASSWORD: 'null', REDIS_DB: '5', REDIS_CACHE_DB: '6' }
    : { CACHE_STORE: 'database', SESSION_DRIVER: 'database' }),
  QUEUE_CONNECTION: 'sync', LOG_CHANNEL: 'single', LOG_LEVEL: 'error', APP_URL: 'http://127.0.0.1',
  // production-style bootstrap caches (built by `artisan config:cache/route:cache/event:cache`). Config caching
  // bakes env() values in at BUILD time, so the database-cache and redis-cache directories are two separately
  // pre-built caches, not one cache read with different env vars (a runtime override would be silently ignored).
  ...(USE_REDIS
    ? { APP_CONFIG_CACHE: '../../qa-redis/appcache/config.php', APP_ROUTES_CACHE: '../../qa-redis/appcache/routes.php', APP_EVENTS_CACHE: '../../qa-redis/appcache/events.php', APP_SERVICES_CACHE: '../../qa-redis/appcache/services.php', APP_PACKAGES_CACHE: '../../qa-redis/appcache/packages.php' }
    : { APP_CONFIG_CACHE: '../../qa-mysql/appcache/config.php', APP_ROUTES_CACHE: '../../qa-mysql/appcache/routes.php', APP_EVENTS_CACHE: '../../qa-mysql/appcache/events.php', APP_SERVICES_CACHE: '../../qa-mysql/appcache/services.php', APP_PACKAGES_CACHE: '../../qa-mysql/appcache/packages.php' }) };
const router = path.join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
const procs = [];
for (let i = 0; i < SERVERS; i++) {
  procs.push(spawn(PHP, ['-d', 'zend_extension=opcache', '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit=off', '-d', 'opcache.memory_consumption=128',
    ...(USE_REDIS ? ['-d', 'opcache.validate_timestamps=0'] : []),
    '-S', `127.0.0.1:${BASE_PORT + i}`, '-t', '.', router], { cwd: path.join(ROOT, 'public'), env, stdio: 'ignore' }));
}
const stopAll = () => procs.forEach((p) => { try { p.kill(); } catch {} });
process.on('exit', stopAll);
await sleep(2500);
// warm every worker (opcache compile, route/config load) so the run measures steady state
for (let r = 0; r < 3; r++) await Promise.all(procs.map((_, i) => fetch(`http://127.0.0.1:${BASE_PORT + i}/login`).then((x) => x.text()).catch(() => {})));

// ---- tiny cookie-jar http client --------------------------------------------------------------------------
const lat = {}; const errs = {}; const errBody = {}; let total = 0;
function record(name, ms, ok, code, text = '') {
  (lat[name] ??= []).push(ms); total++;
  if (!ok) { const k = `${name} -> ${code}`; errs[k] = (errs[k] ?? 0) + 1; errBody[k] ??= text.replace(/\s+/g, ' ').slice(0, 300); }
}
class VU {
  constructor(i, u) { this.i = i; this.u = u; this.base = `http://127.0.0.1:${BASE_PORT + (i % SERVERS)}`; this.jar = new Map(); this.csrf = null; }
  cookies() { return [...this.jar].map(([k, v]) => `${k}=${v}`).join('; '); }
  eat(res) { for (const c of res.headers.getSetCookie?.() ?? []) { const [kv] = c.split(';'); const eq = kv.indexOf('='); this.jar.set(kv.slice(0, eq), kv.slice(eq + 1)); } }
  async req(name, method, url, { json, form, okCodes = [200, 302] } = {}) {
    const headers = { Cookie: this.cookies(), Accept: 'application/json, text/html', 'X-Requested-With': 'XMLHttpRequest' };
    if (this.csrf) headers['X-CSRF-TOKEN'] = this.csrf;
    let body;
    if (json) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
    if (form) { headers['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(form).toString(); }
    const t = performance.now();
    let res, code = 0, text = '';
    try { res = await fetch(this.base + url, { method, headers, body, redirect: 'manual', signal: AbortSignal.timeout(60000) }); code = res.status; this.eat(res); text = await res.text(); }
    catch (e) { code = 'NETERR:' + (e.cause?.code ?? e.name); }
    const ms = performance.now() - t;
    record(name, ms, okCodes.includes(code), code, text);
    return { code, text };
  }
  async login() {
    let r = await this.req('login: GET form', 'GET', '/login');
    this.csrf = /name="_token" value="([^"]+)"/.exec(r.text)?.[1];
    r = await this.req('login: POST', 'POST', '/login', { form: { _token: this.csrf, email: this.u.email, password: manifest.password }, okCodes: [200, 204, 302] });
    if (![200, 204, 302].includes(r.code)) return false;
    r = await this.req('page: POS billing screen', 'GET', '/sales/sales-bills/create');
    this.csrf = /name="csrf-token" content="([^"]+)"/.exec(r.text)?.[1] ?? this.csrf;
    return r.code === 200;
  }
  pick(a) { return a[Math.floor(Math.random() * a.length)]; }
  async billOnce() {
    const branch = this.u.branch_id, n = 1 + Math.floor(Math.random() * 3);
    const items = Array.from({ length: n }, () => this.pick(manifest.hot_items));
    const cust = this.pick(manifest.customer_ids);
    await this.req('customer search', 'GET', `/sales/sales-bills/customer-search?q=${encodeURIComponent('C' + String(cust - manifest.customer_min + 1).padStart(7, '0'))}`);
    const lines = [];
    for (const it of items) {
      await this.req('barcode lookup', 'GET', `/sales/sales-bills/lookup-item?query=${it.ean}&exact_match_only=1&branch_id=${branch}`);
      lines.push({ item_id: it.id, qty: 1 + Math.floor(Math.random() * 3), sell_price: 100, mrp: 120, disc_percent: 0, disc_amount: 0, gst_percent: 18 });
      await sleep(150 + Math.random() * 350);
    }
    if (Math.random() < 0.25) await this.req('item popup search', 'GET', `/sales/sales-bills/item-list?search=Load%20Hot&branch_id=${branch}`);
    const total = lines.reduce((s, l) => s + l.qty * 100, 0);
    const key = crypto.randomUUID();
    const payload = { posting_key: key, bill_date: new Date().toISOString().slice(0, 19).replace('T', ' '), customer_id: cust, branch_id: branch,
      invoice_type: 'Retail Invoice', delivery_type: 'Delivered', sales_type: 'Local', items: lines, payments: [{ tender_type_id: manifest.cash_tender_id, amount: total }] };
    const r = await this.req('SALE: POST bill', 'POST', '/sales/sales-bills', { json: payload, okCodes: [200, 201, 302] });
    // double-click / retry on ~3% of bills: must be idempotent
    if (Math.random() < 0.03) await this.req('SALE: duplicate POST (retry)', 'POST', '/sales/sales-bills', { json: payload, okCodes: [200, 201, 302] });
    return r;
  }
  async returnFlow() {
    const cust = this.pick(manifest.customer_ids);
    const r = await this.req('return: customer bills', 'GET', `/sales/sales-returns/customer-bills/${cust}`);
    try { const j = JSON.parse(r.text); const bills = Array.isArray(j) ? j : (j.bills ?? []); if (bills[0]) await this.req('return: bill items', 'GET', `/sales/sales-returns/bill-items/${bills[0].id}`); } catch {}
  }
  async report() {
    const pick = this.pick([
      ['report: invoice list', '/sales/sales-bills?branch_id=' + this.u.branch_id],
      ['report: sales summary 30d', `/reports/sales-summary?branch_id=${this.u.branch_id}&from=${new Date(Date.now() - 30 * 864e5).toISOString().slice(0, 10)}&to=${new Date().toISOString().slice(0, 10)}`],
      ['report: current stock', '/reports/current-stock?branch_id=' + this.u.branch_id],
    ]);
    await this.req(pick[0], 'GET', pick[1]);
  }
  async run(endAt) {
    if (!(await this.login())) return;
    let k = 0;
    while (Date.now() < endAt) {
      k++;
      await this.billOnce();
      if (k % 12 === 0) await this.returnFlow();
      if (k % 25 === 0) await this.report();
      await sleep(300 + Math.random() * 900); // customer hand-over / scanning time
    }
  }
}

// ---- run ---------------------------------------------------------------------------------------------------
console.log(`Load test: ${USERS} concurrent users, ${DURATION}s, ${SERVERS} PHP workers (opcache on), DB tuned instance :3307`);
// authenticated warm-up on every worker: compile controllers/Eloquent/Blade once per process (not measured)
await Promise.all(Array.from({ length: SERVERS }, async (_, i) => {
  const w = new VU(i, manifest.users[i % manifest.users.length]);
  if (await w.login()) { await w.billOnce(); await w.returnFlow(); await w.report(); }
}));
for (const k of Object.keys(lat)) delete lat[k];
for (const k of Object.keys(errs)) delete errs[k];
total = 0;
const samples = [];
// Async on purpose: a sync child_process call would freeze this event loop and inflate every in-flight latency.
import { execFile } from 'node:child_process';
const run = (f, a) => new Promise((res) => execFile(f, a, { maxBuffer: 1 << 20 }, (e, out) => res(e ? '' : out.toString())));
let sampling = false;
const sampler = setInterval(async () => {
  if (sampling) return; sampling = true;
  try {
    const o = (await run('powershell', ['-NoProfile', '-Command', "(Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples[0].CookedValue; (Get-Process mysqld -ErrorAction SilentlyContinue | Where-Object {$_.Id -ne 0} | Measure-Object WorkingSet64 -Maximum).Maximum/1MB; (Get-Process php -ErrorAction SilentlyContinue | Measure-Object WorkingSet64 -Sum).Sum/1MB"])).trim().split(/\s+/).map(Number);
    const c = (await run(MYSQL, ['-uroot', '-P3307', '-h127.0.0.1', '-N', '-B', '-e', "show global status like 'Threads_connected'"])).split('	')[1];
    if (o.length >= 3) samples.push({ cpu: o[0], mysqlMb: o[1], phpMb: o[2], conns: +c });
  } finally { sampling = false; }
}, 4000);

const start = Date.now(); const endAt = start + DURATION * 1000;
const users = Array.from({ length: USERS }, (_, i) => new VU(i, manifest.users[i % manifest.users.length]));
// stagger logins over 10 s (a shop opening), then all users work until endAt
await Promise.all(users.map(async (vu, i) => { await sleep((i / USERS) * 10000); await vu.run(endAt); }));
clearInterval(sampler);
const wall = (Date.now() - start) / 1000;
stopAll();

const pct = (a, p) => { const s = [...a].sort((x, y) => x - y); return s[Math.min(s.length - 1, Math.max(0, Math.ceil(p / 100 * s.length) - 1))]; };
const rows = Object.entries(lat).map(([k, v]) => ({ action: k, n: v.length, p50: pct(v, 50), p95: pct(v, 95), p99: pct(v, 99), max: Math.max(...v) }));
console.log(`\n${'action'.padEnd(32)} ${'n'.padStart(7)} ${'p50'.padStart(8)} ${'p95'.padStart(8)} ${'p99'.padStart(8)} ${'max'.padStart(8)}   (ms)`);
for (const r of rows.sort((a, b) => a.action.localeCompare(b.action))) console.log(`${r.action.padEnd(32)} ${String(r.n).padStart(7)} ${r.p50.toFixed(0).padStart(8)} ${r.p95.toFixed(0).padStart(8)} ${r.p99.toFixed(0).padStart(8)} ${r.max.toFixed(0).padStart(8)}`);
const all = Object.values(lat).flat(); const errN = Object.values(errs).reduce((a, b) => a + b, 0);
// errs keys look like "action -> 500" / "action -> 422" / "action -> NETERR:..."; split by the code after " -> ".
let count4xx = 0, count5xx = 0, countNetErr = 0;
for (const [k, n] of Object.entries(errs)) {
  const code = k.split(' -> ').at(-1);
  if (/^5\d\d$/.test(code)) count5xx += n;
  else if (/^4\d\d$/.test(code)) count4xx += n;
  else countNetErr += n;
}
const dead = +sql("select count from information_schema.innodb_metrics where name='lock_deadlocks'") - before.dead;
const out = { users: USERS, workers: SERVERS, duration_s: +wall.toFixed(1), requests: total, rps: +(total / wall).toFixed(1), p50: pct(all, 50), p95: pct(all, 95), p99: pct(all, 99),
  error_pct: +(100 * errN / total).toFixed(2), count_4xx: count4xx, count_5xx: count5xx, count_network_error: countNetErr,
  errors: errs, error_samples: errBody, deadlocks: dead, row_lock_waits: status('Innodb_row_lock_waits') - before.waits,
  row_lock_wait_ms: status('Innodb_row_lock_time') - before.waitMs, peak_db_connections: Math.max(0, ...samples.map((s) => s.conns)),
  cpu_avg_pct: +(samples.reduce((a, s) => a + s.cpu, 0) / Math.max(1, samples.length)).toFixed(0), cpu_max_pct: +Math.max(0, ...samples.map((s) => s.cpu)).toFixed(0),
  mysqld_mb_max: +Math.max(0, ...samples.map((s) => s.mysqlMb)).toFixed(0), php_total_mb_max: +Math.max(0, ...samples.map((s) => s.phpMb)).toFixed(0) };
console.log('\nSUMMARY', JSON.stringify(out, null, 2));
mkdirSync(path.join(ROOT, 'scripts/qa/results'), { recursive: true });
writeFileSync(path.join(ROOT, `scripts/qa/results/load_${USERS}u.json`), JSON.stringify({ ...out, rows }, null, 2));
process.exit(0);
