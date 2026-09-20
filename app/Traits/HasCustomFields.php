<?php

namespace App\Traits;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;

trait HasCustomFields
{
    /**
     * Polymorphic relation to custom field values.
     */
    public function customFieldValues()
    {
        return $this->morphMany(CustomFieldValue::class, 'entity');
    }

    /**
     * Get module name corresponding to the model class.
     */
    public function getCustomFieldsModuleName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Get all active definitions for this model's module.
     */
    public function getCustomFieldDefinitions()
    {
        return CustomFieldDefinition::forModule($this->getCustomFieldsModuleName())
            ->active()
            ->get();
    }

    /**
     * Retrieve custom field value by key.
     */
    public function getCustomFieldValue(string $key): mixed
    {
        $valuesMap = $this->custom_fields_map;
        return $valuesMap[$key] ?? null;
    }

    /**
     * Get all custom field values as an associative array: ['field_key' => 'value'].
     */
    public function getCustomFieldsMapAttribute(): array
    {
        if ($this->relationLoaded('customFieldValues')) {
            return $this->customFieldValues->mapWithKeys(function ($cfv) {
                return [$cfv->definition?->field_key ?? $cfv->custom_field_definition_id => $cfv->value];
            })->all();
        }

        return $this->customFieldValues()
            ->with('definition')
            ->get()
            ->mapWithKeys(function ($cfv) {
                return [$cfv->definition?->field_key ?? $cfv->custom_field_definition_id => $cfv->value];
            })->all();
    }

    /**
     * Sync custom field key-value pairs submitted from a request form.
     */
    public function syncCustomFields(array $values): void
    {
        $module = $this->getCustomFieldsModuleName();
        $definitions = CustomFieldDefinition::forModule($module)->get()->keyBy('field_key');

        foreach ($values as $key => $val) {
            $def = $definitions->get($key);
            if (! $def) {
                continue;
            }

            // Normalize array values (e.g. multi-select) to json
            $cleanVal = is_array($val) ? json_encode($val) : (is_null($val) ? null : trim((string) $val));

            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_definition_id' => $def->id,
                    'entity_type' => static::class,
                    'entity_id' => $this->id,
                ],
                [
                    'value' => $cleanVal,
                ]
            );
        }
    }
}
