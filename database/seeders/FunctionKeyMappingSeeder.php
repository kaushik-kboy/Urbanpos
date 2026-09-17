<?php

namespace Database\Seeders;

use App\Models\FunctionKeyMapping;
use Illuminate\Database\Seeder;

class FunctionKeyMappingSeeder extends Seeder
{
    public function run(): void
    {
        $mappings = [
            // --- Global Navigation Shortcuts (Alt + Key) ---
            [
                'action_key' => 'open_sales_bill',
                'action_title' => 'Open Sales Bill (New Bill)',
                'shortcut_combination' => 'Alt+S',
                'scope' => 'global',
                'target_url' => 'sales/sales-bills/create',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'action_key' => 'open_purchase_invoice',
                'action_title' => 'Open Purchase Invoice',
                'shortcut_combination' => 'Alt+P',
                'scope' => 'global',
                'target_url' => 'purchase/purchase-invoices/create',
                'is_enabled' => true,
                'sort_order' => 2,
            ],
            [
                'action_key' => 'open_stock_transfer',
                'action_title' => 'Open Stock Transfer',
                'shortcut_combination' => 'Alt+T',
                'scope' => 'global',
                'target_url' => 'inventory/stock-transfers/create',
                'is_enabled' => true,
                'sort_order' => 3,
            ],
            [
                'action_key' => 'open_customer_master',
                'action_title' => 'Open Customer Master',
                'shortcut_combination' => 'Alt+C',
                'scope' => 'global',
                'target_url' => 'master/customers',
                'is_enabled' => true,
                'sort_order' => 4,
            ],
            [
                'action_key' => 'open_item_master',
                'action_title' => 'Open Item Master',
                'shortcut_combination' => 'Alt+I',
                'scope' => 'global',
                'target_url' => 'master/items',
                'is_enabled' => true,
                'sort_order' => 5,
            ],
            [
                'action_key' => 'open_purchase_order',
                'action_title' => 'Open Purchase Order',
                'shortcut_combination' => 'Alt+O',
                'scope' => 'global',
                'target_url' => 'purchase/purchase-orders/create',
                'is_enabled' => true,
                'sort_order' => 6,
            ],
            [
                'action_key' => 'open_sales_quotation',
                'action_title' => 'Open Sales Quotation',
                'shortcut_combination' => 'Alt+Q',
                'scope' => 'global',
                'target_url' => 'sales/sales-quotations/create',
                'is_enabled' => true,
                'sort_order' => 7,
            ],
            [
                'action_key' => 'open_sales_return',
                'action_title' => 'Open Sales Return',
                'shortcut_combination' => 'Alt+R',
                'scope' => 'global',
                'target_url' => 'sales/sales-returns/create',
                'is_enabled' => true,
                'sort_order' => 8,
            ],

            // --- Form / Billing Function Keys (F1 - F10) ---
            [
                'action_key' => 'search_item',
                'action_title' => 'Item Search / Popup Lookup',
                'shortcut_combination' => 'F2',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 9,
            ],
            [
                'action_key' => 'new_entry',
                'action_title' => 'New Bill / Add Item Row',
                'shortcut_combination' => 'F3',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 10,
            ],
            [
                'action_key' => 'edit_entry',
                'action_title' => 'Edit Mode / Focus Row',
                'shortcut_combination' => 'F4',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 11,
            ],
            [
                'action_key' => 'save_form',
                'action_title' => 'Save & Tender / Submit Form',
                'shortcut_combination' => 'F6',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 12,
            ],
            [
                'action_key' => 'view_records',
                'action_title' => 'View Listing / History',
                'shortcut_combination' => 'F7',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 13,
            ],
            [
                'action_key' => 'print_form',
                'action_title' => 'Print Receipt / Invoice Slip',
                'shortcut_combination' => 'F8',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 14,
            ],
            [
                'action_key' => 'clear_form',
                'action_title' => 'Clear / Reset Form',
                'shortcut_combination' => 'F9',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 15,
            ],
            [
                'action_key' => 'close_modal',
                'action_title' => 'Close Modal / Navigate Back',
                'shortcut_combination' => 'F10',
                'scope' => 'all_forms',
                'target_url' => null,
                'is_enabled' => true,
                'sort_order' => 16,
            ],
        ];

        foreach ($mappings as $data) {
            FunctionKeyMapping::updateOrCreate(
                ['action_key' => $data['action_key']],
                $data
            );
        }

        FunctionKeyMapping::clearCache();
    }
}
