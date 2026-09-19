<?php

use App\Models\FormFieldValidation;
use App\Models\ItemStock;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clamp any existing negative stock in database to zero
        ItemStock::where('quantity', '<', 0)->update(['quantity' => 0]);

        // 2. Register table grid line-item fields for Inventory modules
        $lineItemFields = [
            // stock_transfers
            [
                'module_key' => 'stock_transfers',
                'field_name' => 'item_code',
                'field_label' => 'Code / Barcode',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => 'Item Code / Barcode is required.',
                'sort_order' => 5,
            ],
            [
                'module_key' => 'stock_transfers',
                'field_name' => 'item_name',
                'field_label' => 'Item Description',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 6,
            ],
            [
                'module_key' => 'stock_transfers',
                'field_name' => 'exp_date',
                'field_label' => 'Exp Dt',
                'field_type' => 'date',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 7,
            ],
            [
                'module_key' => 'stock_transfers',
                'field_name' => 'available',
                'field_label' => 'Available',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 8,
            ],
            [
                'module_key' => 'stock_transfers',
                'field_name' => 'qty',
                'field_label' => 'Qty',
                'field_type' => 'number',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => 'Transfer Quantity is required.',
                'sort_order' => 9,
            ],

            // opening_stocks
            [
                'module_key' => 'opening_stocks',
                'field_name' => 'item_code',
                'field_label' => 'Code / Barcode',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 5,
            ],
            [
                'module_key' => 'opening_stocks',
                'field_name' => 'item_name',
                'field_label' => 'Item Description',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 6,
            ],
            [
                'module_key' => 'opening_stocks',
                'field_name' => 'exp_date',
                'field_label' => 'Exp Dt',
                'field_type' => 'date',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 7,
            ],
            [
                'module_key' => 'opening_stocks',
                'field_name' => 'qty',
                'field_label' => 'Qty',
                'field_type' => 'number',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => 'Opening quantity is required.',
                'sort_order' => 8,
            ],

            // damage_stocks
            [
                'module_key' => 'damage_stocks',
                'field_name' => 'item_code',
                'field_label' => 'Code / Barcode',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 6,
            ],
            [
                'module_key' => 'damage_stocks',
                'field_name' => 'item_name',
                'field_label' => 'Item Description',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 7,
            ],
            [
                'module_key' => 'damage_stocks',
                'field_name' => 'exp_date',
                'field_label' => 'Exp Dt',
                'field_type' => 'date',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 8,
            ],
            [
                'module_key' => 'damage_stocks',
                'field_name' => 'available',
                'field_label' => 'Available',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 9,
            ],
            [
                'module_key' => 'damage_stocks',
                'field_name' => 'qty',
                'field_label' => 'Qty',
                'field_type' => 'number',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => 'Damage quantity is required.',
                'sort_order' => 10,
            ],

            // stock_updates
            [
                'module_key' => 'stock_updates',
                'field_name' => 'item_code',
                'field_label' => 'Code / Barcode',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 4,
            ],
            [
                'module_key' => 'stock_updates',
                'field_name' => 'item_name',
                'field_label' => 'Item Description',
                'field_type' => 'text',
                'is_required' => true,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 5,
            ],
            [
                'module_key' => 'stock_updates',
                'field_name' => 'exp_date',
                'field_label' => 'Exp Dt',
                'field_type' => 'date',
                'is_required' => false,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 6,
            ],
            [
                'module_key' => 'stock_updates',
                'field_name' => 'available',
                'field_label' => 'Available',
                'field_type' => 'number',
                'is_required' => false,
                'is_readonly' => true,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => null,
                'sort_order' => 7,
            ],
            [
                'module_key' => 'stock_updates',
                'field_name' => 'qty',
                'field_label' => 'Qty',
                'field_type' => 'number',
                'is_required' => true,
                'is_readonly' => false,
                'block_future_date' => false,
                'is_unique' => false,
                'custom_error_message' => 'Adjusted quantity is required.',
                'sort_order' => 8,
            ],
        ];

        foreach ($lineItemFields as $data) {
            FormFieldValidation::firstOrCreate(
                [
                    'module_key' => $data['module_key'],
                    'field_name' => $data['field_name'],
                ],
                $data
            );
        }

        app(\App\Services\DynamicValidationService::class)->clearCache();
    }

    public function down(): void
    {
        $keys = ['item_code', 'item_name', 'exp_date', 'available', 'qty'];
        FormFieldValidation::whereIn('module_key', ['stock_transfers', 'opening_stocks', 'damage_stocks', 'stock_updates'])
            ->whereIn('field_name', $keys)
            ->delete();

        app(\App\Services\DynamicValidationService::class)->clearCache();
    }
};
