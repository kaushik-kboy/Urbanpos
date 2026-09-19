<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('form_field_validations') && ! Schema::hasColumn('form_field_validations', 'section')) {
            Schema::table('form_field_validations', function (Blueprint $table) {
                $table->string('section', 100)->nullable()->default('General')->after('field_label');
            });
        }

        // Populate sections for Item Master (matching the 5 tabs in /master/items/create: General, Taxes, Sales, Category, GST)
        $itemSections = [
            'General' => [
                'item_code', 'ean_upc_code', 'name', 'alias', 'brand_id', 'supplier_id',
                'product_type', 'cost_price', 'landing_cost', 'sell_price', 'mrp',
                'store_pickup', 'status',
            ],
            'Taxes' => [
                'tax_inclusive',
            ],
            'Sales' => [
                'batch_expiry_details', 'shelf_life_days', 'minimum_shelf_life_days', 'allow_negative_stock',
            ],
            'Category' => [
                'department_value_id', 'category_value_id', 'brand_value_id',
            ],
            'GST' => [
                'gst_tax_id', 'hsn_code',
            ],
        ];

        foreach ($itemSections as $section => $fields) {
            DB::table('form_field_validations')
                ->where('module_key', 'items')
                ->whereIn('field_name', $fields)
                ->update(['section' => $section]);
        }

        // Populate sections for Supplier Master
        $supplierSections = [
            'General & Contact' => ['name', 'mobile', 'phone', 'email', 'website', 'contact_person'],
            'Tax & Legal' => ['gst_no', 'pan_no', 'aadhar_no'],
            'Address' => ['address', 'city', 'state', 'pincode', 'country'],
            'Bank & Financial' => ['bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch', 'credit_days', 'credit_limit', 'status'],
        ];
        foreach ($supplierSections as $section => $fields) {
            DB::table('form_field_validations')
                ->where('module_key', 'suppliers')
                ->whereIn('field_name', $fields)
                ->update(['section' => $section]);
        }

        // Populate sections for Customer Master
        $customerSections = [
            'General & Contact' => ['name', 'mobile', 'phone', 'email'],
            'Tax & Legal' => ['gstin', 'pan_no', 'aadhar_no'],
            'Address' => ['address', 'city', 'state', 'pincode', 'country'],
            'Settings' => ['credit_days', 'credit_limit', 'status', 'customer_group_id'],
        ];
        foreach ($customerSections as $section => $fields) {
            DB::table('form_field_validations')
                ->where('module_key', 'customers')
                ->whereIn('field_name', $fields)
                ->update(['section' => $section]);
        }

        // Populate sections for Branch Master
        $branchSections = [
            'General Details' => ['name', 'code', 'contact_person', 'email', 'phone', 'mobile', 'status'],
            'Address' => ['address', 'city', 'state', 'pincode', 'country'],
            'Tax & Invoicing' => [
                'gstin', 'pan_no', 'invoice_prefix', 'receipt_prefix', 'purchase_return_prefix',
                'sales_return_prefix', 'quotation_prefix', 'order_prefix', 'delivery_prefix',
            ],
        ];
        foreach ($branchSections as $section => $fields) {
            DB::table('form_field_validations')
                ->where('module_key', 'branches')
                ->whereIn('field_name', $fields)
                ->update(['section' => $section]);
        }

        // Populate sections for Inventory & Transaction modules (Header vs Line Items vs Totals)
        $lineItemFieldNames = [
            'item_code', 'item_name', 'exp_date', 'available', 'qty', 'physical_qty',
            'system_qty_at_entry', 'cost_price', 'sell_price', 'mrp', 'rate',
            'discount', 'tax', 'gst_percent', 'gst_tax_amount', 'net_amount',
        ];

        DB::table('form_field_validations')
            ->whereIn('field_name', $lineItemFieldNames)
            ->where('module_key', '!=', 'items')
            ->update(['section' => 'Line Items']);

        $totalsFieldNames = [
            'payment_mode', 'round_off', 'discount_amount', 'shipping_charges', 'tender_amount', 'change_return',
        ];

        DB::table('form_field_validations')
            ->whereIn('field_name', $totalsFieldNames)
            ->update(['section' => 'Payment & Totals']);

        // Default any remaining null sections to 'Header Details' or 'General'
        DB::table('form_field_validations')
            ->whereNull('section')
            ->whereIn('module_key', [
                'purchase_invoices', 'purchase_orders', 'purchase_receipt_notes', 'purchase_indents',
                'purchase_returns', 'sales_bills', 'sales_returns', 'sales_quotations',
                'sales_orders', 'sales_delivery_notes', 'stock_transfers', 'opening_stocks',
                'damage_stocks', 'stock_updates',
            ])
            ->update(['section' => 'Header Details']);

        DB::table('form_field_validations')
            ->whereNull('section')
            ->update(['section' => 'General']);
    }

    public function down(): void
    {
        if (Schema::hasTable('form_field_validations') && Schema::hasColumn('form_field_validations', 'section')) {
            Schema::table('form_field_validations', function (Blueprint $table) {
                $table->dropColumn('section');
            });
        }
    }
};
