<?php

namespace App\Http\Controllers;

use App\Models\UserFormPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserFormPreferenceController extends Controller
{
    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'form_key' => 'required|string|max:100',
        ]);

        $formKey = $request->query('form_key');
        $preferences = UserFormPreference::getForUser(Auth::id(), $formKey);

        return response()->json([
            'status' => 'success',
            'form_key' => $formKey,
            'preferences' => $preferences,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_key' => 'required|string|max:100',
            'preferences' => 'required|array',
            'preferences.*.field' => 'required|string',
            'preferences.*.order' => 'required|integer|min:1',
            'preferences.*.grid_col' => 'nullable|string',
            'preferences.*.visible' => 'required|boolean',
        ]);

        $record = UserFormPreference::setForUser(
            Auth::id(),
            $validated['form_key'],
            $validated['preferences']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Form layout preferences saved successfully.',
            'preferences' => $record->preferences,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_key' => 'required|string|max:100',
        ]);

        UserFormPreference::resetForUser(Auth::id(), $validated['form_key']);

        return response()->json([
            'status' => 'success',
            'message' => 'Form layout reset to system default.',
        ]);
    }
}
