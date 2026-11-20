<?php

namespace App\Http\Controllers;

use App\Models\RoleTablePreference;
use App\Models\User;
use App\Models\UserTablePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserTablePreferenceController extends Controller
{
    protected function canCustomize(): bool
    {
        $user = Auth::user();
        return $user && (
            $user->hasRole(['Owner', 'Super Admin', 'Admin', 'Administrator']) ||
            $user->can('layout-customizer.customize') ||
            $user->can('layout.customize')
        );
    }

    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'table_key' => 'required|string|max:100',
        ]);

        $tableKey = $request->query('table_key');
        $user = Auth::user();
        $preferences = UserTablePreference::getResolvedForUser($user, $tableKey);

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
            'target_type' => 'nullable|string|in:user,role,all_roles',
            'target_role_id' => 'nullable|integer',
            'sync_users' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $targetType = $validated['target_type'] ?? 'user';
        $syncUsers = !empty($validated['sync_users']);

        // Layout customization requires permission
        if (! $this->canCustomize()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to modify table layouts. Layout is locked by Administrator.',
            ], 403);
        }

        if ($targetType === 'role' && !empty($validated['target_role_id'])) {
            $roleId = (int) $validated['target_role_id'];
            $record = RoleTablePreference::setForRole(
                $roleId,
                $validated['table_key'],
                $validated['preferences']
            );

            if ($syncUsers) {
                $role = Role::find($roleId);
                if ($role) {
                    $userIds = User::role($role->name)->pluck('id');
                    if ($userIds->isNotEmpty()) {
                        UserTablePreference::whereIn('user_id', $userIds)
                            ->where('table_key', $validated['table_key'])
                            ->delete();
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Role table layout preferences saved and synced successfully.',
                'preferences' => $record->preferences,
            ]);
        }

        if ($targetType === 'all_roles') {
            $allRoles = Role::all();
            foreach ($allRoles as $role) {
                RoleTablePreference::setForRole(
                    $role->id,
                    $validated['table_key'],
                    $validated['preferences']
                );
            }
            RoleTablePreference::setForRole(
                null,
                $validated['table_key'],
                $validated['preferences']
            );

            if ($syncUsers) {
                UserTablePreference::where('table_key', $validated['table_key'])->delete();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Global layout applied to all system roles successfully.',
                'preferences' => $validated['preferences'],
            ]);
        }

        // Standard user-specific save
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
            'target_type' => 'nullable|string|in:user,role,all_roles',
            'target_role_id' => 'nullable|integer',
        ]);

        if (! $this->canCustomize()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to reset layouts.',
            ], 403);
        }

        $targetType = $validated['target_type'] ?? 'user';

        if ($targetType === 'role' && !empty($validated['target_role_id'])) {
            RoleTablePreference::resetForRole((int) $validated['target_role_id'], $validated['table_key']);
        } elseif ($targetType === 'all_roles') {
            RoleTablePreference::where('table_key', $validated['table_key'])->delete();
        } else {
            UserTablePreference::resetForUser(Auth::id(), $validated['table_key']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Table preferences reset to default.',
        ]);
    }
}
