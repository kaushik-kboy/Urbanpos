# Urban Pets Stock & Expiry Verification — Local Setup & Testing

## Project structure (updated 2026-10-10)
Two sibling folders under `C:\laragon\www\`:
- **Laravel backend**: `C:\laragon\www\Urbanpos` (branch `mobileapp`) — all APIs, auth, DB access, inventory business logic live here.
- **Flutter app**: `C:\laragon\www\urbanpos-mobile-app` — moved out of the Laravel repo (was previously `C:\laragon\www\Urbanpos\mobile-app`; that copy is kept only until this external copy is fully verified, then removed — see `OVERNIGHT_PROGRESS_REPORT.md` for current status of that cleanup).

The Flutter app is a fully independent project with no path dependency on the Laravel repo's location on disk — it only needs network access to wherever the Laravel API is served (`API_BASE_URL`), same as before the move.

## Environment confirmed on this machine
- PHP 8.3.13, Composer 2.5.4, Laravel 11.56.1
- MySQL/MariaDB, DB name `urban_pos` (local, `127.0.0.1`), `APP_ENV=local`
- Flutter 3.35.4 (stable), Dart 3.9.2, Android SDK 36 / build-tools 36.0.0 present at `C:\Users\Prem\AppData\Local\Android\Sdk`
- No macOS/Xcode on this machine — iOS build/test is not possible here (documented as BLOCKED, not attempted).

## Laravel backend

### Install the new dependency (additive)
```
cd C:\laragon\www\Urbanpos
composer install
```
`laravel/sanctum` was added to `composer.json`/`composer.lock` by this work — `composer install` picks it up.

### Run the new migrations (additive — new tables only)
These are safe against your real `urban_pos` DB: every migration in this module only creates new tables (`personal_access_tokens`, `expiry_bucket_settings`, `stock_count_tasks`, `stock_count_task_items`, `inventory_observations`, `barcode_mapping_requests`) or adds nullable columns. **Still, take your DB backup before running this**, since none existed at the time of this build:
```
php artisan migrate
```
Review `php artisan migrate --pretend` first if you want to see the exact SQL without applying it.

*(2026-10-10, overnight-2)* Two more additive migrations were added for task assignment by Brand/Category — `2026_10_10_010006_add_brand_id_to_stock_count_tasks_table` and `2026_10_10_010007_add_category_value_id_to_stock_count_tasks_table` (nullable FK columns on the module's own `stock_count_tasks` table only). They **have already been applied** to the local `urban_pos` DB in that session (after a `mysqldump` safety backup). On any other environment, run `php artisan migrate` — without them `POST /api/v1/tasks` fails with an unknown-column SQL error.

### Seed the new Staff role / permissions (idempotent, safe to re-run)
```
php artisan db:seed --class=RolesAndPermissionsSeeder
```
This uses `firstOrCreate`, so re-running it does not duplicate existing Owner/Manager/Cashier roles or permissions — it only adds the new `Staff` role and `stock-verification.*`/`barcode-mapping.*` permissions.

### Run the automated tests (safe — runs against in-memory SQLite, never touches `urban_pos`)
```
vendor/bin/phpunit tests/Feature/Api
```
for just the new module's tests, or
```
vendor/bin/phpunit
```
for the full existing suite (expect pre-existing unrelated failures — see `OVERNIGHT_PROGRESS_REPORT.md` for the exact count and which files they're in; none overlap files this module touched).

### Serve the API for the Flutter app to hit
```
php artisan serve
```
Default: `http://127.0.0.1:8000`. The Flutter app's default API base URL (`http://10.0.2.2:8000/api/v1`) assumes an Android emulator talking to this same machine's `127.0.0.1:8000` — `10.0.2.2` is the emulator's alias for the host machine. For a physical device on the same Wi-Fi, use the host machine's LAN IP instead and pass it via `--dart-define=API_BASE_URL=http://<your-lan-ip>:8000/api/v1`.

### Create a test Staff user for manual testing
Via `php artisan tinker`:
```php
$u = App\Models\User::factory()->create(['email' => 'staff-test@urbanpos.local', 'branch_id' => 1]);
$u->assignRole('Staff');
```
(Adjust `branch_id` to a real branch id in your DB.)

## Flutter mobile app

```
cd C:\laragon\www\urbanpos-mobile-app
flutter pub get
flutter analyze
flutter test
```

### Run against your local Laravel server (Android emulator)
```
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

### Build the debug APK
```
flutter build apk --debug
```
Output: `C:\laragon\www\urbanpos-mobile-app\build\app\outputs\flutter-apk\app-debug.apk` — install on a device/emulator with `adb install -r build\app\outputs\flutter-apk\app-debug.apk` (run from `urbanpos-mobile-app`) or drag onto a running emulator.

## Manual test scenarios for morning review

1. **Login** as the Staff test user → should land on Dashboard, token stored securely.
2. **Blind count**: scan/search a product with existing batch stock → enter a physical quantity → submit. Confirm no system-quantity value is ever visible on this screen or in the network response (`GET /v1/items/{item}/batches` while logged in as Staff).
3. **Duplicate-tap protection**: on a flaky connection, retry the same submission — confirm only one `inventory_observations` row is created (check `my submissions` history, or the DB table directly).
4. **Manager review**: log in as a Manager/Owner user, open the pending-review queue — confirm the system quantity and variance ARE visible here, submitted by the Staff user above.
5. **Approve**: approve the observation with a target quantity — confirm `item_stocks`/`stock_ledger` reflect the correct delta (recompute manually: approved_qty minus whatever the current stock was at approval time, not the original snapshot).
6. **Duplicate approval**: try approving the same observation a second time (e.g. double-click, or call the API twice) — confirm the second call is rejected (409) and no second ledger row is created.
7. **Recount**: request a recount on a different observation — confirm the original row is untouched/preserved and a new linked submission is required before it can be approved.
8. **Reject**: reject an observation — confirm zero inventory change.
9. **Barcode mapping**: submit a mapping request for an unrecognized barcode, then review/approve it as Manager — confirm the item's barcode only changes after approval, never on submission.
11. **History & Audit (overnight-2)**: as Owner open the History tab → Branch "All Branches", pick a date range, switch All / Stock Count / Expiry Check, use the top-right filter icon for status, scroll to load more. Every row shows submitter + signed variance. Log in as the Staff user → the same screen shows only that user's own submissions, with no variance column.
12. **Assign task by Category/Brand (overnight-2)**: as Manager/Owner, Dashboard → "Assign Task" (or Tasks tab → Assign). Pick Brand (and/or Category — note the dev DB has no category values yet), optionally "Pick specific products" (only in-scope products are offered), assignee, priority → Assign. On the Tasks tab use the Category/Brand filter chips. Open the task as the Staff user → "Products to count" lists the scope.
13. **Barcode mapping review (overnight-2)**: as Staff, report an unknown barcode; as Manager/Owner, Dashboard → "Barcode Requests" → Approve (confirm) or Reject (reason required). If the product already has a different barcode a second "Replace existing barcode?" confirmation appears.
10. **Existing POS regression spot-check**: run a normal sale/purchase/stock-transfer flow in the existing AdminLTE app to confirm nothing in the new module broke it (per master prompt §14 item 15).

## Manual test scenarios — business rules + AdminLTE (2026-10-10 final session)

14. **Expiry-check approval never changes stock**: as Staff, submit an `expiry_check` observation (no quantity needed). As Manager/Owner, approve it (only a remarks field is shown/accepted — no quantity field at all). Confirm `item_stocks`/`stock_ledger` are unchanged before/after. Then submit a `stock_count` observation and confirm approving it still posts the ledger correction as before — the split only affects `expiry_check`.
15. **Multi-category / multi-brand task assignment**: Assign Task screen → Category field and Brand field are now multi-select (tap to open a checkbox dialog, pick more than one). Submit with 2+ categories and 1 brand selected — confirm the task is created and an item matching ANY of the selected categories/brands is accepted, while an item matching none of them is rejected (422) at submit time.
16. **Recount batch preservation**: request a recount on an observation that has a `batch_no`. Resubmit without touching the batch field — confirm the new row keeps the same `batch_no`. Then, as a separate check (API/Postman, since the app has no UI for this), POST a recount resubmission with a *different* `batch_no` and no `batch_correction` flag — confirm `422`. Repeat with `batch_correction: true` + a `batch_correction_reason` — confirm it succeeds and the observation row records `batch_correction_applied: true`, `original_batch_no`, and the reason.
17. **Expiry-bucket settings**: as Manager/Owner, open "Expiry Bucket Settings" from the Dashboard tile. Confirm the 3 default ranges (0-30/31-60/61-120) are shown, edit one, save, and confirm the Dashboard's expiry-exposure widget reflects the new ranges. As Staff, confirm this screen/route is not reachable (403 if hit directly via API).
18. **Photo evidence in review**: as Staff, submit an observation with a photo attached. As Manager/Owner, open it in the Flutter review screen or the AdminLTE observation-detail page — confirm the photo displays. As Staff, confirm the photo section does not appear on your own submission view.
19. **Recount history display**: after at least one recount cycle on an observation, open its detail (Flutter or AdminLTE) as Manager/Owner — confirm a "Recount History" section lists the prior attempt(s) and their outcome.
20. **Batch selection when assigning a task**: Assign Task → pick specific products → for a product with existing batch stock, use the new "Specific batch (optional)" dropdown to pick one — confirm the created task's item shows that batch.
21. **AdminLTE desktop screens**: log into the existing AdminLTE panel as Manager/Owner → **Inventory → Stock & Expiry Verification → Manager Review Queue** (approve/recount/reject from the desktop, same rules as the app) and **→ History & Audit** (same branch/date/type/status filters as the Flutter screen). Confirm a mobile-submitted observation appears here, and that approving it from this desktop screen posts the same stock change you'd see approving it from the phone.
22. **Cross-branch access blocked**: as a branch-restricted Manager, attempt to view/approve an observation belonging to a different branch (e.g. by editing the URL/observation id) — confirm `403` both via the API and the AdminLTE screen.

## Known environment limitations
- iOS build/device testing: not possible on this Windows machine (no Xcode/macOS). The Flutter codebase is structured to support it later.
- No physical Android device/emulator was launched during this automated build — only `flutter build apk --debug` (compiles) and `flutter test` (unit/widget tests) were run. Manual on-device testing is left for your morning review per the steps above.
