<?php

namespace App\Http\Controllers;

use App\Models\UserTablePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTablePreferenceController extends Controller
{
    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'table_key' => 'required|string|max:100',
        ]);

        $tableKey = $request->query('table_key');
        $preferences = UserTablePreference::getForUser(Auth::id(), $tableKey);

        return response()->json([
            'status' => 'success',
            'table_key' => $tableKey,
            'preferences' => $preferences,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_key' => 'required|string|max:100',
            'preferences' => 'required|array',
            'preferences.*.key' => 'required|string',
            'preferences.*.order' => 'required|integer|min:1',
            'preferences.*.visible' => 'required|boolean',
        ]);

        $record = UserTablePreference::setForUser(
            Auth::id(),
            $validated['table_key'],
            $validated['preferences']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Table column preferences saved successfully.',
            'preferences' => $record->preferences,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_key' => 'required|string|max:100',
        ]);

        UserTablePreference::resetForUser(Auth::id(), $validated['table_key']);

        return response()->json([
            'status' => 'success',
            'message' => 'Table preferences reset to default.',
        ]);
    }
}
