# UrbanPOS QA / Performance Report (2026-09-26)

Environments: tests run on `urban_pos_test` (MySQL) or in-memory sqlite. Concurrency/load/perf ran on a **dedicated MySQL instance
(port 3307, datadir `C:\laragon\qa-mysql`, DB `urban_pos_qa`)**. Production/dev DB `urban_pos` was never written by tests
(`tests/TestCase.php` refuses any DB not named `*_test`; QA scripts refuse anything not `*_qa` on :3307).

Run: `php vendor/bin/phpunit -c phpunit.mysql.xml` · `npx vitest run --pool=forks` · `php scripts/qa/concurrency.php run all 8`
Load: `node scripts/qa/load_test.mjs --users 100 --duration 90 --servers 8` (needs the QA MySQL running + `seed_scale.php`).

## Results
- PHPUnit: 360+ tests, 0 failures (was 340 tests / 115 failing). Vitest: 22/22.
- Concurrency harness (real multi-process, barrier-started): 15/15 checks pass (see bugs 2,3,5).
- Data: 20.8 lakh records (275K bills, 665K lines, 734K stock movements, 92K customers, 34K items).
- Load (8 PHP workers, opcache + config/route cache, single 8-core box shared with client + DB, closed-loop ~2.4 req/s per user):

| users | req/s | p50 | p95 | p99 | 5xx | deadlocks | error % |
|---|---|---|---|---|---|---|---|
| 100 | 23.4 | 3.0 s | 7.7 s | 10.6 s | 0 | 0 | 0.64 (dup-guard 422) |
| 200 | 18.8 | 7.9 s | 19.2 s | 22.8 s | 0 | 0 | 0.46 |
| 300 | 15.6 | 11.6 s | 36.7 s | 40.5 s | 0 | 0 | 0.31 |

  CPU was 95-97% throughout: this measures one saturated laptop, not a production sizing. Integrity after load: 0 duplicate bill
  numbers, 0 duplicate posting keys, 0 negative stock, 0 bills without lines/payments, stock == opening - sold for 1500 rows.

## Bugs fixed (each has a regression test)
1. CRITICAL Gate::before gave Manager / any name-or-email containing admin|owner full access.
2. CRITICAL Sales/Purchase Return over-return race (40 returned vs 5 sold) - qty check now under row lock in the txn.
3. HIGH duplicate document numbers (max(id)+1 in 8 controllers; cross-branch counters) -> HTTP 500s. Atomic `nextPrefixed()`; one
   shared counter per document type; gate row; sequence creation via INSERT IGNORE (was gap-lock deadlock).
4. HIGH return could reference another customer's/supplier's or a cancelled document.
5. MEDIUM duplicate submit of a return -> 500/422; now returns the original document.
6. HIGH backups used LOCK TABLES (would freeze billing).
7. HIGH invoice search: uncorrelated OR matched every bill; >30 s at 100K bills. Same precedence bug in 2 report queries.
8. HIGH billwise-sales + sales-return-summary pages crashed for every user with >=1 customer.
9. Reports: current-stock 13 s->0.6 s (paginated + SQL totals); GST sales 1y 10.3 s->0.85 s and purchase 2.5 s->0.47 s (SQL group-by;
   cancelled bills no longer counted in GST totals); POS item popup no longer aggregates all purchase history per keystroke.
10. Exact customer code / mobile / bill number fast-paths (580 ms->9 ms; 600 ms->10 ms DB).

## Open items / recommendations
- Sales throughput: the bill-number lock is held ~75 ms per sale (fine to ~13 bills/s); moving allocation to the end of the txn
  would raise it. Legacy duplicate-content guard rejects a repeat customer's same-first-item bill within 10 s (UX risk).
- `%term%` substring search (items/customers) scales linearly (mobile fragment: 2.2 s at 92K customers). Use prefix search or a
  FULLTEXT ngram index. Deep invoice pagination (OFFSET) 1.3 s at page 500 - use keyset pagination.
- Production MUST run `artisan optimize` + opcache (halves per-request cost) and tune MySQL (dev: 128 MB buffer pool, 151 connections).
- Sales return without a selected bill has no quantity ceiling. Sessions/cache are DB-backed (write per request) - consider Redis.
- Not done: coverage % (no pcov/xdebug installed), real-browser keyboard E2E (Chrome extension unavailable), 2M+ bills tier,
  journal/ledger tables at scale.
- The dev `admin@urbanpos.test` user has no role; assign Owner after bug 1's fix.
