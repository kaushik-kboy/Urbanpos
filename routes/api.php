<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BarcodeMappingController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpiryBucketController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ObservationController;
use App\Http\Controllers\Api\V1\ReferenceDataController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Urban Pets mobile app — Sanctum bearer tokens, no cookies)
|--------------------------------------------------------------------------
|
| Everything here is new and additive: it does not touch any existing web
| route. See URBANPETS_DATABASE_AND_WORKFLOW.md and
| URBANPETS_MASTER_IMPLEMENTATION_PROMPT.md for the design this implements.
|
| Permission checks for the routes below are done INLINE in the controllers
| (explicit guard 'web') rather than via the 'permission:' middleware alias.
| See "Implementation notes" in URBANPETS_DATABASE_AND_WORKFLOW.md for why:
| Illuminate\Auth\Middleware\Authenticate::authenticate() calls
| Auth::shouldUse('sanctum') on every authenticated api request, which
| changes config('auth.defaults.guard') to 'sanctum' for the rest of that
| request — and spatie/laravel-permission's Gate::before() hook resolves
| the permission's guard from that same now-mutated default, not from the
| 'permission:x,sanctum' middleware argument. Since every seeded Permission/
| Role row uses guard_name 'web', that mismatch makes the standard
| 'permission:' middleware alias always deny authenticated api requests.
*/

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/branches', [BranchController::class, 'index']);

        Route::get('/expiry-buckets', [ExpiryBucketController::class, 'index']);
        Route::put('/expiry-buckets/settings', [ExpiryBucketController::class, 'update']);

        // Product taxonomy for task assignment by Category or Brand (read-only).
        Route::get('/categories', [ReferenceDataController::class, 'categories']);
        Route::get('/brands', [ReferenceDataController::class, 'brands']);

        Route::get('/items/search', [ItemController::class, 'search']);
        Route::get('/items/barcode/{code}', [ItemController::class, 'byBarcode']);

        Route::middleware('api.branch.access')->group(function () {
            Route::get('/items/{item}/batches', [ItemController::class, 'batches']);
        });

        // --- Phase 8: task assignment (Manager/Owner assign, Staff see their own) ---
        // Permission checked inline in TaskController@store ('stock-verification.assign-task').
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/tasks', [TaskController::class, 'index']);
        // Declared before /tasks/{task} so "assignees" is never bound as a task id.
        Route::get('/tasks/assignees', [TaskController::class, 'assignees']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);

        // --- Phase 6/7/9: observation submission, staff history, manager review queue ---
        // Permission checked inline in ObservationController (submit / review).
        Route::post('/observations', [ObservationController::class, 'store']);
        Route::get('/observations', [ObservationController::class, 'index']);
        Route::get('/observations/mine', [ObservationController::class, 'mine']);
        Route::get('/observations/pending', [ObservationController::class, 'pending']);
        Route::get('/observations/{observation}', [ObservationController::class, 'show']);
        Route::get('/observations/{observation}/photo', [ObservationController::class, 'photo']);
        Route::post('/observations/{observation}/approve', [ObservationController::class, 'approve']);
        Route::post('/observations/{observation}/request-recount', [ObservationController::class, 'requestRecount']);
        Route::post('/observations/{observation}/reject', [ObservationController::class, 'reject']);

        // --- Phase 7/9: unknown-barcode mapping requests + authorized review ---
        // Permission checked inline in BarcodeMappingController (request / review).
        Route::post('/barcode-mapping-requests', [BarcodeMappingController::class, 'store']);
        Route::get('/barcode-mapping-requests', [BarcodeMappingController::class, 'index']);
        Route::post('/barcode-mapping-requests/{mappingRequest}/approve', [BarcodeMappingController::class, 'approve']);
        Route::post('/barcode-mapping-requests/{mappingRequest}/reject', [BarcodeMappingController::class, 'reject']);

        // --- Phase 10: role-aware dashboard summary (Staff: personal; Manager/Owner: branch-wide) ---
        // Permission checked inline in DashboardController@summary for the Manager/Owner path
        // ('stock-verification.review' + optional 'barcode-mapping.review'); Staff path is
        // open to any authenticated Staff/Manager/Owner user, mirroring /tasks and
        // /observations/mine (role-based visibility, not permission-gated).
        Route::middleware('api.branch.access')->group(function () {
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        });
    });
});
