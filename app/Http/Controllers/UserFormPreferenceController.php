<?php

namespace App\Http\Controllers;

use App\Models\RoleFormPreference;
use App\Models\User;
use App\Models\UserFormPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserFormPreferenceController extends Controller
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
            'form_key' => 'required|string|max:100',
        ]);

        $formKey = $request->query('form_key');
        $user = Auth::user();
        $preferences = UserFormPreference::getResolvedForUser($user, $formKey);

        return response()->json([
            'status' => 'success',
            'form_key' => $formKey,
            'preferences' => $preferences,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Normalize boolean and integer fields if sent via standard form-encoding (strings 'true'/'false')
        if ($request->has('preferences') && is_array($request->input('preferences'))) {
            $prefs = $request->input('preferences');
            foreach ($prefs as &$p) {
                if (isset($p['visible'])) {
                    $p['visible'] = filter_var($p['visible'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($p['order'])) {
                    $p['order'] = (int) $p['order'];
                }
            }
            unset($p);
            $request->merge(['preferences' => $prefs]);
        }

        $validated = $request->validate([
            'form_key' => 'required|string|max:100',
            'preferences' => 'required|array',
            'preferences.*.field' => 'required|string',
            'preferences.*.order' => 'required|integer|min:1',
            'preferences.*.grid_col' => 'nullable|string',
            'preferences.*.visible' => 'required|boolean',
            'target_type' => 'nullable|string|in:user,role,all_roles',
            'target_role_id' => 'nullable|integer',
            'sync_users' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $targetType = $validated['target_type'] ?? 'user';
        $syncUsers = !empty($validated['sync_users']);

        if (! $this->canCustomize()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to modify form layouts. Layout is locked by Administrator.',
            ], 403);
        }

        if ($targetType === 'role' && !empty($validated['target_role_id'])) {
            $roleId = (int) $validated['target_role_id'];
            $record = RoleFormPreference::setForRole(
                $roleId,
                $validated['form_key'],
                $validated['preferences']
            );

            if ($syncUsers) {
                $role = Role::find($roleId);
                if ($role) {
                    $userIds = User::role($role->name)->pluck('id');
                    if ($userIds->isNotEmpty()) {
                        UserFormPreference::whereIn('user_id', $userIds)
                            ->where('form_key', $validated['form_key'])
                            ->delete();
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Role form layout preferences saved and synced successfully.',
                'preferences' => $record->preferences,
            ]);
        }

        if ($targetType === 'all_roles') {
            $allRoles = Role::all();
            foreach ($allRoles as $role) {
                RoleFormPreference::setForRole(
                    $role->id,
                    $validated['form_key'],
                    $validated['preferences']
                );
            }
            RoleFormPreference::setForRole(
                null,
                $validated['form_key'],
                $validated['preferences']
            );

            if ($syncUsers) {
                UserFormPreference::where('form_key', $validated['form_key'])->delete();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Global layout applied to all system roles successfully.',
                'preferences' => $validated['preferences'],
            ]);
        }

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
            RoleFormPreference::resetForRole((int) $validated['target_role_id'], $validated['form_key']);
        } elseif ($targetType === 'all_roles') {
            RoleFormPreference::where('form_key', $validated['form_key'])->delete();
        } else {
            UserFormPreference::resetForUser(Auth::id(), $validated['form_key']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Form layout reset to system default.',
        ]);
    }
}
