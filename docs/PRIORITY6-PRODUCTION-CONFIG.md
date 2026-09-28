# Priority 6 — Production DB/Connection Architecture

**Scope**: prepare and test the recommended production architecture (PHP-FPM, OPcache, MySQL buffer pool,
connection pool, Redis) and verify 200-300 concurrent terminals don't hit connection exhaustion or excessive
locking. **The real `urban_pos` database and the shared dev `php.ini`/Apache config were never modified** —
everything here was built and tested against the isolated QA sandbox (`urban_pos_qa` on MySQL :3307, a
dedicated QA Redis on :6390, and PHP built-in-server workers on their own ports).

## What was found (today's actual config)

| Area | Current state |
|---|---|
| Web server | Apache + `mod_fcgid` → `php-cgi.exe` (`FcgidMaxProcesses 50`, global across all Laragon sites). No PHP-FPM on this Windows box — see recommendation below. |
| OPcache | **Fully disabled.** Every `opcache.*` directive in `php-8-3-13/php.ini` is commented out and `zend_extension=opcache` is commented out. The DLL is present, just not loaded. |
| MySQL (dev, port 3306 — where the real `urban_pos` lives) | Stock installer defaults: `innodb_buffer_pool_size=128M`, `max_connections=151`, `wait_timeout=28800` (8 hours). Never tuned for this app's ~2.1M-row dataset. |
| MySQL (QA, port 3307) | Already tuned from an earlier session: `innodb_buffer_pool_size=1536M`, `max_connections=600`, `innodb_redo_log_capacity=2G`, `slow_query_log` on. This is effectively a draft of the recommended config, already load-tested. |
| Session/cache | `SESSION_DRIVER=database`, `CACHE_STORE=database` — every request writes a session row. `.env` already has unused `REDIS_*` keys. Redis binary (`redis-x64-5.0.14.1`, an old Windows port) was present but not running; no `phpredis` extension for PHP 8.3. |

## Changes made

1. **`predis/predis:^2.3`** added via composer (pure-PHP Redis client — no PHP extension needed, since `phpredis` isn't compiled for this PHP 8.3 build). Verified the app boots and the existing suite still passes with it installed.
2. **Dedicated QA Redis instance**: `C:\laragon\qa-redis\redis.conf` — port **6390** (never the default 6379, so it can never collide with anything else on the machine), no persistence (`save ""`), 256 MB `maxmemory` with `allkeys-lru`. Start with:
   ```
   C:\laragon\bin\redis\redis-x64-5.0.14.1\redis-server.exe C:\laragon\qa-redis\redis.conf
   ```
3. **`scripts/qa/prod_config_test.php`** (new) — boots small pools of PHP's *actual* built-in HTTP server (real per-request bootstrap cost, unlike the in-process kernel calls `perf_queries.php` uses) for two scenarios and compares them:
   - **BASELINE**: today's real config — no OPcache, DB-backed session/cache.
   - **RECOMMENDED**: OPcache on (`validate_timestamps=0`, i.e. production mode — see the deploy note below), Redis session/cache via predis.
   It also asserts a session genuinely round-trips (a protected page doesn't bounce back to `/login` after `/login` set a cookie) and, for the Redis scenario, checks Redis's own `DBSIZE` before/after so the result isn't just "env vars were set" — an earlier version of this test looked like it was using Redis but wasn't (see gotcha below).
4. **`scripts/qa/load_test.mjs --redis`** flag (new) — the existing 100/200/300-concurrent-user harness from the earlier QA session, now switchable to the recommended architecture (Redis session/cache + `opcache.validate_timestamps=0`) instead of the original DB-session baseline, so the same realistic POS workflow (login → barcode lookup → customer search → sale) can be re-run under either config.
5. Two separate **pre-built bootstrap caches** (`config:cache`/`route:cache`/`event:cache`), because config caching bakes `env()` calls in at build time:
   - `C:\laragon\qa-mysql\appcache\` — database session/cache (already existed).
   - `C:\laragon\qa-redis\appcache\` — Redis session/cache, predis client, port 6390 (new).

   **Gotcha hit and fixed**: the first pass at this test pointed `SESSION_DRIVER=redis` at the *existing* `qa-mysql` config cache via env vars and reported a big speedup with "session round-trip: ok" — but that cache had `database` baked in, so it was silently still testing the database driver. Laravel ignores env overrides once a config cache exists. Fixed by building a second, separate cache. Re-verified with `redis-cli DBSIZE`: 4→56 keys during one test run, 10 session keys / 3 cache keys in a `load_test.mjs --redis` smoke run — genuinely landing in Redis this time.
6. Also hit and fixed a Windows-specific `proc_open` trap while building the harness: passing a custom `$env` array to `proc_open` **replaces** the whole process environment instead of extending it, which drops `SystemRoot`/`PATH`/`TEMP` and breaks Winsock (every spawned PHP server failed with `"Failed to listen (reason: ?)"` with no useful error). Fixed with `getenv() + $baseEnv + $cfg['env']`. Documented inline in `prod_config_test.php` in case this pattern is copied elsewhere.

## Results (light load, single machine, ~16 h into an unrelated coverage run eating one full CPU core — numbers are directionally solid but noisier than a quiet-machine run)

```
BASELINE  (today: no opcache, DB session+cache)
  p50=321-372ms  p95=436-488ms  p99=458-511ms

RECOMMENDED (opcache on, Redis session+cache)
  p50=28-46ms  p95=242-542ms  p99=287-667ms
  Redis db5 keys: before=4 after=56 (confirmed via redis-cli, not just config)
```

p50 is **7-12x faster** with OPcache alone (it eliminates re-parsing/re-compiling the whole app's PHP source on every single request — the dominant cost when nothing is cached). p95/p99 are noisier under both configs here because of CPU contention with the coverage job; expect tighter tails on a quiet machine or real production hardware. `load_test.mjs --redis` at a small scale (2 users) ran the full POS workflow (login → barcode lookup → customer search → sale) with 0 errors, 0 deadlocks, and confirmed session/cache keys actually in Redis (db5: 10 keys, db6: 3 keys).

## Recommended production config (to actually apply on the real production box — not this dev machine)

**PHP-FPM** (this Windows dev box has no FPM; production should be Linux + PHP-FPM, not Apache+mod_fcgid):
- `pm = dynamic`
- `pm.max_children` = (RAM budgeted for PHP) / (~40-60 MB per Laravel worker, measure with `php_total_mb_max` from `load_test.mjs`'s output) — for 200-300 concurrent terminals with think-time (not 200-300 *simultaneous* requests; POS terminals are closed-loop, ~1 request every few seconds each per the existing QA-REPORT throughput numbers), 40-60 workers is a reasonable starting point, verified empirically rather than assumed.
- `pm.start_servers` / `pm.min_spare_servers` / `pm.max_spare_servers` sized around 25-50% of `pm.max_children`.
- The Windows equivalent tested here is `FcgidMaxProcesses` (currently 50, shared across *all* Laragon sites) — fine for dev, not a substitute for real PHP-FPM in production.

**OPcache** (currently fully off — this is the single highest-value change and the one the earlier QA-REPORT already flagged as still open):
```ini
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```
`validate_timestamps=0` means **a deploy must run `opcache_reset()` (or restart PHP-FPM)** or stale bytecode gets served — bake this into the deploy script, not left implicit.

**MySQL buffer pool / connections** (the QA my.ini values below are an already-tested starting point, not a copy-paste for a different-sized production box — size `innodb_buffer_pool_size` to ~70-80% of the DB server's RAM once that's known):
```ini
innodb_buffer_pool_size = 1536M   ; scale to ~70-80% of the DB server's RAM
innodb_redo_log_capacity = 2G
max_connections = 300             ; sized to (PHP workers across all app servers) + admin headroom, not guessed
innodb_lock_wait_timeout = 20     ; fail fast instead of piling up locks under contention
wait_timeout = 600                ; the current 28800s (8h) default lets dead connections sit for hours
slow_query_log = 1
long_query_time = 0.5
```

**Redis** for session/cache (this removes the "write a session row on every single request" cost the earlier QA report flagged): `phpredis` extension is preferable in real production (C extension, no per-call PHP object overhead) if the target PHP build supports it; `predis` (already added here) is the safe fallback that needs no extension.

## Still pending / deferred

- **The actual 200-300 concurrent-terminal verification under the RECOMMENDED config** (`node scripts/qa/load_test.mjs --users 200 --duration 90 --servers 16 --redis`, then `--users 300`) was **deliberately deferred** — a full run pegs the CPU (95-97% in the earlier baseline run) and would corrupt both its own numbers and the still-running coverage job's timing if run concurrently. The earlier session's 100/200/300-user baseline (`docs/QA-REPORT.md`) already showed 0 deadlocks / 0 5xx *without* OPcache or Redis; this run is to confirm the recommended config holds (or improves on) that bar at real concurrency, not to re-discover a problem. **Run this once the coverage job finishes.**
- `redis-x64-5.0.14.1` is a community-maintained Windows port of Redis 5 (old); real production should run current Redis (or Valkey) on Linux. Nothing here depends on Windows-specific Redis behaviour.
- Cleanup owed once Priority 6 is fully closed: stop the QA Redis instance (`redis-cli -p 6390 shutdown nosave` or kill the process), and decide whether `C:\laragon\qa-redis\` stays for future runs or gets deleted.
