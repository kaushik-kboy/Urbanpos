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

    /**
     * Dynamically apply rules and custom messages to Laravel validation arrays.
     */
    public function applyTo(string $moduleKey, array &$rules, array &$messages): void
    {
        try {
            $configs = $this->getConfigsForModule($moduleKey);
            if ($configs->isEmpty()) {
                return;
            }

            foreach ($configs as $fieldName => $config) {
                if (!isset($rules[$fieldName])) {
                    continue;
                }

                $ruleList = is_array($rules[$fieldName])
                    ? $rules[$fieldName]
                    : explode('|', $rules[$fieldName]);

                // 1. Handle Required / Nullable toggle
                if ($config->is_required) {
                    $ruleList = array_values(array_filter($ruleList, fn ($r) => $r !== 'nullable'));
                    if (!in_array('required', $ruleList, true)) {
                        array_unshift($ruleList, 'required');
                    }
                } else {
                    // Do not mark core foreign keys nullable if database schema forbids null
                    $protectedKeys = ['branch_id', 'supplier_id', 'customer_id', 'from_branch_id', 'to_branch_id'];
                    if (!in_array($fieldName, $protectedKeys, true)) {
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

                $rules[$fieldName] = $ruleList;

                // 3. Handle Custom Error Message
                if (!empty($config->custom_error_message)) {
                    $msg = trim($config->custom_error_message);
                    $messages["{$fieldName}.required"] = $msg;
                    $messages["{$fieldName}.before_or_equal"] = $msg;
                    $messages["{$fieldName}.unique"] = $msg;
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
            $modules = ['purchase_invoices', 'sales_bills', 'stock_transfers', 'customers', 'suppliers'];
            foreach ($modules as $mod) {
                Cache::forget(self::CACHE_PREFIX . $mod);
            }
        }
    }
}
