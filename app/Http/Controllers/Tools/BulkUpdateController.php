<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\CustomerCategory;
use App\Models\GstTax;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\PetType;
use App\Services\Tools\UniversalBulkUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkUpdateController extends Controller
{
    public function __construct(private UniversalBulkUpdateService $bulkService)
    {
    }

    /**
     * Enforce management authorization.
     */
    protected function checkAuthorization(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        if ((int) $user->id === 1) {
            return;
        }

        if ($user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator', 'Manager'])) {
            return;
        }

        abort(403, 'Unauthorized access. Only management can execute universal bulk modifications.');
    }

    /**
     * Display the universal bulk update console.
     */
    public function index(Request $request): View
    {
        $this->checkAuthorization();

        $modules = $this->bulkService->getModuleDefinitions();
        $selectedModule = $request->input('module', 'items');
        $isDynamicTableMode = ($selectedModule === 'dynamic_table');
        $allTables = $this->bulkService->getAvailableDatabaseTables();
        $selectedTable = $request->input('table', $allTables[0] ?? 'items');

        if (! isset($modules[$selectedModule]) && ! $isDynamicTableMode) {
            $selectedModule = 'items';
        }

        // Preload common relational lookup tables for UI dropdowns
        $lookups = [
            'gst_taxes' => GstTax::where('status', true)->orderBy('percentage')->get(['id', 'description', 'percentage']),
            'item_categories' => ItemCategory::where('status', true)->orderBy('name')->get(['id', 'name']),
            'item_category_values' => ItemCategoryValue::where('status', true)->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::where('status', true)->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('status', true)->orderBy('name')->get(['id', 'name']),
            'customer_categories' => CustomerCategory::where('status', true)->orderBy('name')->get(['id', 'name']),
            'areas' => Area::where('status', true)->orderBy('name')->get(['id', 'name']),
            'pet_types' => PetType::where('status', true)->orderBy('name')->get(['id', 'name']),
        ];

        // Group modules by category for streamlined tabs
        $groupedModules = [];
        foreach ($modules as $mKey => $mDef) {
            $group = $mDef['group'] ?? 'General';
            $groupedModules[$group][$mKey] = $mDef;
        }

        $currentDef = $isDynamicTableMode ? [
            'group' => 'System & Any Table',
            'title' => 'Universal Database Table Explorer',
            'description' => 'Directly filter and bulk update any table in the entire database.',
            'icon' => 'fas fa-database text-warning',
            'table' => $selectedTable,
            'filterable_fields' => [],
            'updatable_fields' => [],
        ] : $modules[$selectedModule];

        return view('tools.bulk-updater.index', [
            'modules' => $modules,
            'groupedModules' => $groupedModules,
            'selectedModule' => $selectedModule,
            'currentDef' => $currentDef,
            'lookups' => $lookups,
            'isDynamicTableMode' => $isDynamicTableMode,
            'allTables' => $allTables,
            'selectedTable' => $selectedTable,
        ]);
    }

    /**
     * Get columns of a specific database table for dynamic table mode.
     */
    public function getTableColumns(Request $request): JsonResponse
    {
        $this->checkAuthorization();

        $table = $request->input('table');
        try {
            $columns = $this->bulkService->getTableColumns($table);
            return response()->json([
                'success' => true,
                'table' => $table,
                'columns' => $columns,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview matching records via AJAX.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->checkAuthorization();

        $moduleKey = $request->input('module');
        $filters = $request->input('filters', []);
        $dynamicTable = $request->input('dynamic_table');

        try {
            $result = $this->bulkService->preview($moduleKey, $filters, $dynamicTable);
            return response()->json([
                'success' => true,
                'total_count' => $result['total_count'],
                'sample_rows' => $result['sample_rows'],
                'module' => $result['module'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Execute the bulk update.
     */
    public function execute(Request $request): JsonResponse|RedirectResponse
    {
        $this->checkAuthorization();

        $moduleKey = $request->input('module');
        $filters = $request->input('filters', []);
        $targetField = $request->input('target_field');
        $targetValue = $request->input('target_value');
        $mathMode = $request->input('math_mode');
        $mathPercent = $request->filled('math_percent') ? (float) $request->input('math_percent') : null;
        $dynamicTable = $request->input('dynamic_table');

        try {
            $result = $this->bulkService->executeUpdate(
                moduleKey: $moduleKey,
                filters: $filters,
                targetField: $targetField,
                targetValue: $targetValue,
                mathMode: $mathMode,
                mathPercent: $mathPercent,
                dynamicTable: $dynamicTable
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->route('tools.bulk-updater.index', [
                'module' => $moduleKey,
                'table' => $dynamicTable,
            ])->with('success', $result['message']);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->route('tools.bulk-updater.index', [
                'module' => $moduleKey,
                'table' => $dynamicTable,
            ])->with('error', 'Bulk update failed: ' . $e->getMessage());
        }
    }
}
