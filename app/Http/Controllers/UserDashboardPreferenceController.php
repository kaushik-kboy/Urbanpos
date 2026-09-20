<?php

namespace App\Http\Controllers;

use App\Models\UserDashboardPreference;
use App\Services\Dashboard\DashboardRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserDashboardPreferenceController extends Controller
{
    public function __construct(
        private readonly DashboardRegistryService $registryService
    ) {
        $this->middleware('auth');
    }

    /**
     * Save user customized dashboard shortcuts and widgets.
     */
    public function save(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        $accessible = $this->registryService->getAccessibleShortcuts($user);
        $accessibleKeys = $accessible->keys()->all();

        $validated = $request->validate([
            'shortcuts' => ['nullable', 'array'],
            'shortcuts.*' => ['string'],
            'widgets' => ['nullable', 'array'],
            'widgets.*' => ['string'],
        ]);

        $submittedShortcuts = $validated['shortcuts'] ?? [];
        $submittedWidgets = $validated['widgets'] ?? [];

        // Security Guard: Filter out any shortcut the user does NOT have permission for
        $cleanShortcuts = array_values(array_intersect($submittedShortcuts, $accessibleKeys));

        // Filter valid widget keys
        $allWidgetKeys = array_keys(config('dashboard.widgets', []));
        $cleanWidgets = array_values(array_intersect($submittedWidgets, $allWidgetKeys));

        UserDashboardPreference::setForUser($user->id, $cleanShortcuts, $cleanWidgets);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard preferences saved successfully!',
                'shortcuts_count' => count($cleanShortcuts),
            ]);
        }

        return redirect()->route('home')->with('status', 'Dashboard preferences customized successfully!');
    }

    /**
     * Reset user dashboard to their role default.
     */
    public function reset(Request $request): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        UserDashboardPreference::resetForUser($user->id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard reset to role defaults successfully!',
            ]);
        }

        return redirect()->route('home')->with('status', 'Dashboard reset to role defaults.');
    }
}
