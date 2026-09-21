<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PosLockController extends Controller
{
    /**
     * Verify the 4-digit POS lock PIN.
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $throttleKey = 'pos-lock-pin:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Too many incorrect attempts. Please wait {$seconds} seconds before trying again.",
            ], 429);
        }

        $request->validate([
            'pin' => ['required', 'string', 'digits:4'],
        ]);

        $pin = (string) $request->input('pin');

        // If user hasn't configured a pos_pin yet, default to '0000' or password match
        $valid = false;
        if (! empty($user->pos_pin)) {
            $valid = Hash::check($pin, $user->pos_pin);
        } else {
            // First time default PIN is '0000'
            $valid = ($pin === '0000');
            if ($valid) {
                // Auto-save default PIN
                $user->pos_pin = Hash::make('0000');
                $user->save();
            }
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60);
            $remaining = RateLimiter::remaining($throttleKey, 5);
            return response()->json([
                'success' => false,
                'message' => "Invalid PIN. {$remaining} attempts remaining.",
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'success' => true,
            'message' => 'Screen unlocked successfully.',
            'user'    => [
                'id'   => $user->id,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Update current user's 4-digit POS PIN.
     */
    public function updatePin(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'current_pin' => ['nullable', 'string', 'digits:4'],
            'new_pin'     => ['required', 'string', 'digits:4'],
        ]);

        if (! empty($user->pos_pin) && ! empty($request->input('current_pin'))) {
            if (! Hash::check((string) $request->input('current_pin'), $user->pos_pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current PIN is incorrect.',
                ], 422);
            }
        }

        $user->pos_pin = Hash::make((string) $request->input('new_pin'));
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'POS PIN updated successfully.',
        ]);
    }
}
