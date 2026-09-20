<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $passThrough = \App\Http\Middleware\PermissionPassThroughMiddleware::class;
        $middleware->alias([
            'role' => class_exists('\Spatie\Permission\Middleware\RoleMiddleware') ? '\Spatie\Permission\Middleware\RoleMiddleware' : $passThrough,
            'permission' => class_exists('\Spatie\Permission\Middleware\PermissionMiddleware') ? '\Spatie\Permission\Middleware\PermissionMiddleware' : $passThrough,
            'role_or_permission' => class_exists('\Spatie\Permission\Middleware\RoleOrPermissionMiddleware') ? '\Spatie\Permission\Middleware\RoleOrPermissionMiddleware' : $passThrough,
            'branch.access' => \App\Http\Middleware\EnsureBranchAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            try {
                app(\App\Services\System\ErrorLoggerService::class)->capture($e, request());
            } catch (\Throwable $ignored) {
                // Fail-safe: ignore
            }
        });

        // Intercept validation failures so form breaks are visible in System Error Logs
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            try {
                app(\App\Services\System\ErrorLoggerService::class)->capture($e, $request);
            } catch (\Throwable $ignored) {
                // Fail-safe: ignore
            }
            return null; // Let default redirect-back-with-errors proceed
        });
    })->create();
