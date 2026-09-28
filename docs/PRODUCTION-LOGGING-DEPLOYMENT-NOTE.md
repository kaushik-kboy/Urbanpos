# Production Logging — Deployment Note (documentation only, not applied)

**No changes were made to any real `.env`, Hostinger, or production configuration.** This is a
recommendation for whenever the new hosting plan is configured; local `.env` was not touched
either (nothing here required a local test change).

## Current state (found during the QA index/deployment audit)

- `LOG_CHANNEL=stack` → `LOG_STACK=single`: every log line goes into **one never-rotated file**,
  `storage/logs/laravel.log`. There is no automatic pruning or size cap.
- `LOG_LEVEL=debug`: the most verbose level — every debug-level message the framework and app
  code emit gets written, not just warnings/errors.

## Why this matters before go-live

- A single-file, unrotated log on a long-running production site grows without bound. On a
  shared-hosting plan (Hostinger) with a disk-quota, this is a slow-burn risk: nothing fails
  immediately, but the file keeps growing until it becomes a real problem (quota exhaustion, slow
  tail/grep, awkward to download for debugging).
- `debug` level in production also means noisy, larger log files for no operational benefit —
  debug-level messages are a development aid, not something a live store needs captured forever.

## Recommended production values

```
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=warning
```

- **`LOG_STACK=daily`**: Laravel's built-in `daily` channel writes to a new dated file each day
  (`laravel-2026-09-27.log`, etc.) and prunes files older than `LOG_DAILY_DAYS` automatically — no
  cron or manual cleanup needed.
- **`LOG_DAILY_DAYS=14`**: two weeks of history is enough to investigate a recently-reported issue
  without keeping indefinite logs on a disk-quota-limited shared host. Raise it if the business
  wants a longer audit trail and disk allows; there's no code-level reason it must be exactly 14.
- **`LOG_LEVEL=warning`**: still captures everything that matters operationally (warnings, errors,
  critical/emergency) without the debug-level noise. Use `error` instead if even warnings turn out
  to be too chatty in practice — that's a judgment call to make after watching it run for a few
  days, not something to guess correctly on day one.

## What this note does NOT do

- It does not change the real Hostinger `.env` — that happens only when the new hosting plan is
  ready and the user asks for it.
- It does not change the local dev `.env` — the local values were left as they are; this is a
  target for the *production* environment specifically, not a claim that local dev is wrong to run
  verbosely.
- It does not add log-rotation infrastructure beyond what Laravel's own `daily` driver already
  provides — that's suffient for this scale and needs no extra package or server-level cron.
