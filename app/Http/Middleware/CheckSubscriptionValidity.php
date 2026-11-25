<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionValidity
{
    /**
     * Handle an incoming request and verify subscription validity against Chandak SaaS Hub.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Excluded routes from license check
        if ($request->is('subscription-expired*') || 
            $request->is('login*') || 
            $request->is('logout*') || 
            $request->is('up') || 
            $request->is('api/*')) {
            return $next($request);
        }

        // Toggleable check: by default enabled if SAAS_HUB_URL is present, or local fallback
        $hubUrl = config('services.saas_hub.url', env('SAAS_HUB_URL', 'http://127.0.0.1:8085'));
        $isEnabled = config('services.saas_hub.enabled', env('SAAS_CHECK_ENABLED', true));

        if (!$isEnabled || empty($hubUrl)) {
            return $next($request);
        }

        $domain = $request->getHost();
        $cacheKey = 'saas_license_check_' . md5($domain);

        // Allow manual refresh if client clicked "Check Again"
        if ($request->query('refresh_license') === '1') {
            Cache::forget($cacheKey);
        }

        $licenseData = Cache::remember($cacheKey, now()->addHours(6), function () use ($hubUrl, $domain) {
            try {
                // Non-blocking 2-second timeout call to SaaS Hub
                $response = Http::timeout(2)->get("{$hubUrl}/api/v1/license/verify", [
                    'domain' => $domain,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                // Fail-Open Graceful Architecture: If SaaS Hub is unreachable, ALLOW billing!
            }

            // Fallback: Default to active with grace period so store is NEVER interrupted
            return [
                'success' => true,
                'status' => 'active',
                'is_allowed_billing' => true,
                'is_fallback' => true,
            ];
        });

        // If license has been explicitly expired or suspended by Chandak Infotech
        if (isset($licenseData['is_allowed_billing']) && $licenseData['is_allowed_billing'] === false) {
            if ($request->expectsJson() || $request->is('pos/*') || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => 'SUBSCRIPTION_EXPIRED',
                    'message' => 'UrbanPOS subscription expired. Please contact Chandak Infotech to renew.',
                    'contact' => $licenseData['support_contact'] ?? [],
                ], 403);
            }

            return response()->view('errors.subscription-expired', [
                'licenseData' => $licenseData,
            ], 403);
        }

        return $next($request);
    }
}
