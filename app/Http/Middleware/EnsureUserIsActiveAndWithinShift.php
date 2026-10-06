<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActiveAndWithinShift
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // 1. Check if user account was deactivated by admin
            if (!$user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Your account has been deactivated. Please contact your system administrator.',
                    ], 403);
                }

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been deactivated. Please contact your system administrator.',
                ]);
            }

            // 2. Check if user is outside working shift hours (Auto-Logout)
            if (!$user->isWithinShift()) {
                $shiftDetails = substr($user->time_in, 0, 5) . ' - ' . substr($user->time_out, 0, 5);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => "Your shift has ended (Shift: {$shiftDetails}). You have been automatically logged out.",
                    ], 403);
                }

                return redirect()->route('login')->withErrors([
                    'email' => "Your shift has ended (Shift: {$shiftDetails}). You have been automatically logged out.",
                ]);
            }
        }

        return $next($request);
    }
}
