<?php

namespace App\Services;

use App\Models\FormFieldValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DynamicValidationService
{
    private const CACHE_PREFIX = 'dynamic_form_validations_';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get all validation configurations for a specific module (cached).
     */
    public function getConfigsForModule(string $moduleKey): Collection
    {
        // Fail-safe: if table doesn't exist yet, return empty collection
        if (!Schema::hasTable('form_field_validations')) {
            return collect();
        }

        return Cache::remember(self::CACHE_PREFIX . $moduleKey, self::CACHE_TTL, function () use ($moduleKey) {
            return FormFieldValidation::where('module_key', $moduleKey)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('field_name');
        });
    }

    private array $moduleTables = [
        'suppliers' => 'suppliers',
        'customers' => 'customers',
        'items' => 'items',
        'branches' => 'branches',
        'purchase_invoices' => 'purchase_invoices',
        'purchase_orders' => 'purchase_orders',
        'purchase_receipt_notes' => 'purchase_receipt_notes',
        'purchase_indents' => 'purchase_indents',
        'purchase_returns' => 'purchase_returns',
        'sales_bills' => 'sales_bills',
        'sales_returns' => 'sales_returns',
        'sales_quotations' => 'sales_quotations',
        'sales_orders' => 'sales_orders',
        'sales_delivery_notes' => 'sales_delivery_notes',
        'stock_transfers' => 'stock_transfers',
        'opening_stocks' => 'opening_stocks',
        'damage_stocks' => 'damage_stocks',
        'stock_updates' => 'stock_updates',
    ];

    private array $fieldAliases = [
        'gstin' => 'gst_no',
        'gst_no' => 'gstin',
        'item_code' => 'ean_upc_code',
        'ean_upc_code' => 'item_code',
        'code' => 'erp_code',
        'erp_code' => 'code',
        'address' => 'address1',
        'address1' => 'address',
    ];

    /**
     * Dynamically apply rules and custom messages to Laravel validation arrays.
     */
    public function applyTo(string $moduleKey, array &$rules, array &$messages, mixed $ignoreId = null): void
    {
        try {
            if ($ignoreId === null && function_exists('request') && request()) {
                if (request()->isMethod('put') || request()->isMethod('patch')) {
                    $routeParams = request()->route() ? request()->route()->parameters() : [];
                    foreach ($routeParams as $param) {
                        if (is_object($param) && method_exists($param, 'getKey')) {
                            $ignoreId = $param->getKey();
                            break;
                        } elseif (is_numeric($param) && (int) $param > 0) {
                            $ignoreId = (int) $param;
                            break;
                        }
                    }
                }
            }

            $configs = $this->getConfigsForModule($moduleKey);
            if ($configs->isEmpty()) {
                return;
            }

            foreach ($configs as $fieldName => $config) {
                // If both canonical field and alias exist in configs, prevent optional/non-unique config from clobbering strict alias
                if (isset($this->fieldAliases[$fieldName])) {
                    $alias = $this->fieldAliases[$fieldName];
                    if ($configs->has($alias)) {
                        $aliasConfig = $configs[$alias];
                        if (!$config->is_required && $aliasConfig->is_required) {
                            continue;
                        }
                        if (!$config->is_unique && $aliasConfig->is_unique) {
                            continue;
                        }
                    }
                }

                $targetField = $fieldName;
                if (!isset($rules[$targetField])) {
                    $alias = $this->fieldAliases[$fieldName] ?? null;
                    if ($alias && isset($rules[$alias])) {
                        $targetField = $alias;
                    } else {
                        // If not in base rules but configured as required, add it dynamically
                        if ($config->is_required) {
                            $rules[$targetField] = ['required'];
                        } else {
                            continue;
                        }
                    }
                }

                $ruleList = is_array($rules[$targetField])
                    ? $rules[$targetField]
                    : explode('|', $rules[$targetField]);

                // 1. Handle Required / Nullable toggle
                if ($config->is_required) {
                    $ruleList = array_values(array_filter($ruleList, fn ($r) => $r !== 'nullable'));
                    if (!in_array('required', $ruleList, true)) {
                        array_unshift($ruleList, 'required');
                    }
                } else {
                    // Core structural identity and foreign keys that must never be stripped of 'required'
                    // to prevent MySQL 1048 Not Null constraint violations
                    $protectedKeys = [
                        'name',
                        'item_code',
                        'code',
                        'erp_code',
                        'branch_id',
                        'supplier_id',
                        'customer_id',
                        'from_branch_id',
                        'to_branch_id',
                        'bill_number',
                        'invoice_number',
                        'po_number',
                        'return_number',
                        'indent_number',
                        'receipt_number',
                        'delivery_number',
                        'transfer_number',
                        'entry_number',
                        'damage_number',
                        'update_number',
                    ];
                    if (!in_array($targetField, $protectedKeys, true)) {
                        $ruleList = array_values(array_filter($ruleList, fn ($r) => $r !== 'required'));
                        if (!in_array('nullable', $ruleList, true)) {
                            array_unshift($ruleList, 'nullable');
                        }
                    }
                }

                // 2. Handle Block Future Date toggle
                $futureDateRules = ['before_or_equal:today', 'before_or_equal:now'];
                if ($config->block_future_date) {
                    $dateConstraint = $config->field_type === 'datetime'
                        ? 'before_or_equal:' . now()->addMinutes(2)->format('Y-m-d H:i:s')
                        : 'before_or_equal:' . date('Y-m-d');

                    // Filter out existing before_or_equal rules
                    $ruleList = array_values(array_filter($ruleList, function ($r) {
                        return !str_starts_with((string) $r, 'before_or_equal');
                    }));
                    $ruleList[] = $dateConstraint;
                } else {
                    // If future date is allowed by admin, strip before_or_equal date rules
                    $ruleList = array_values(array_filter($ruleList, function ($r) {
                        return !str_starts_with((string) $r, 'before_or_equal');
                    }));
                }

                // 3. Handle Unique Check toggle
                if ($config->is_unique) {
                    $table = $this->moduleTables[$moduleKey] ?? $moduleKey;
                    $column = $targetField;

                    // Strip any existing unique rules to avoid duplicates
                    $ruleList = array_values(array_filter($ruleList, function ($r) {
                        return !($r instanceof \Illuminate\Validation\Rules\Unique) && !str_starts_with((string) $r, 'unique');
                    }));

                    $uniqueRule = \Illuminate\Validation\Rule::unique($table, $column);
                    if ($ignoreId) {
                        $uniqueRule->ignore($ignoreId);
                    }
                    $ruleList[] = $uniqueRule;
                }

                $rules[$targetField] = $ruleList;

                // 4. Handle Custom Error Message
                if (!empty($config->custom_error_message)) {
                    $msg = trim($config->custom_error_message);
                    $messages["{$targetField}.required"] = $msg;
                    $messages["{$targetField}.before_or_equal"] = $msg;
                    $messages["{$targetField}.unique"] = $msg;
                    $messages["{$targetField}.regex"] = $msg;
                }
            }
        } catch (\Throwable $e) {
            // Fail-safe: log warning without stopping normal business flow
            \Illuminate\Support\Facades\Log::warning("DynamicValidationService error on [{$moduleKey}]: " . $e->getMessage());
        }
    }

    /**
     * Check if a field is set to Required by admin.
     */
    public function isFieldRequired(string $moduleKey, string $fieldName, bool $default = false): bool
    {
        $configs = $this->getConfigsForModule($moduleKey);
        return $configs->has($fieldName) ? (bool) $configs[$fieldName]->is_required : $default;
    }

    /**
     * Check if a field is set to Readonly by admin.
     */
    public function isFieldReadonly(string $moduleKey, string $fieldName, bool $default = false): bool
    {
        $configs = $this->getConfigsForModule($moduleKey);
        return $configs->has($fieldName) ? (bool) $configs[$fieldName]->is_readonly : $default;
    }

    /**
     * Check if future date is blocked for this field.
     */
    public function isFutureDateBlocked(string $moduleKey, string $fieldName, bool $default = false): bool
    {
        $configs = $this->getConfigsForModule($moduleKey);
        return $configs->has($fieldName) ? (bool) $configs[$fieldName]->block_future_date : $default;
    }

    /**
     * Clear cached validation rules.
     */
    public function clearCache(?string $moduleKey = null): void
    {
        if ($moduleKey) {
            Cache::forget(self::CACHE_PREFIX . $moduleKey);
        } else {
            $modules = [
                'purchase_invoices', 'purchase_orders', 'purchase_receipt_notes', 'purchase_indents', 'purchase_returns',
                'sales_bills', 'sales_returns', 'sales_quotations', 'sales_orders', 'sales_delivery_notes',
                'stock_transfers', 'opening_stocks', 'damage_stocks', 'stock_updates',
                'customers', 'suppliers', 'items', 'branches',
            ];
            foreach ($modules as $mod) {
                Cache::forget(self::CACHE_PREFIX . $mod);
            }
        }
    }
}
