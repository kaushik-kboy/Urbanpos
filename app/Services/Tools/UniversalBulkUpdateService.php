<?php

namespace App\Services\Tools;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Breed;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerPet;
use App\Models\CustomerType;
use App\Models\CustomFieldDefinition;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use App\Models\PetType;
use App\Models\Register;
use App\Models\SalesType;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

class UniversalBulkUpdateService
{
    /**
     * Get definitions for all supported standard business modules in the universal bulk modifier.
     */
    public function getModuleDefinitions(): array
    {
        return [
            // ==========================================
            // 1. PRODUCTS, CATALOG & INVENTORY
            // ==========================================
            'items' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Product Items & Catalog',
                'description' => 'Bulk update GST slabs, HSN codes, categories, brands, pricing, and stock policies.',
                'icon' => 'fas fa-boxes text-primary',
                'model' => Item::class,
                'table' => 'items',
                'label_field' => 'name',
                'code_field' => 'item_code',
                'filterable_fields' => [
                    'gst_tax_id' => ['label' => 'Current GST Tax Slab', 'type' => 'relation', 'relation_table' => 'gst_taxes', 'relation_label' => 'description'],
                    'category_value_id' => ['label' => 'Category', 'type' => 'relation', 'relation_table' => 'item_category_values', 'relation_label' => 'value'],
                    'brand_id' => ['label' => 'Brand', 'type' => 'relation', 'relation_table' => 'brands', 'relation_label' => 'name'],
                    'department_value_id' => ['label' => 'Department', 'type' => 'relation', 'relation_table' => 'item_category_values', 'relation_label' => 'value'],
                    'hsn_code' => ['label' => 'HSN Code', 'type' => 'string'],
                    'status' => ['label' => 'Active Status', 'type' => 'boolean'],
                    'allow_negative_stock' => ['label' => 'Allow Negative Stock', 'type' => 'boolean'],
                    'tax_inclusive' => ['label' => 'Tax Inclusive Flag', 'type' => 'boolean'],
                    'product_type' => ['label' => 'Product Type', 'type' => 'select', 'options' => ['Standard', 'Serialized', 'Service Component', 'Kit', 'Assembly']],
                ],
                'updatable_fields' => [
                    'gst_tax_id' => ['label' => 'New GST Tax Slab', 'type' => 'relation', 'relation_table' => 'gst_taxes', 'relation_label' => 'description'],
                    'hsn_code' => ['label' => 'HSN Code', 'type' => 'string'],
                    'category_value_id' => ['label' => 'Category', 'type' => 'relation', 'relation_table' => 'item_category_values', 'relation_label' => 'value'],
                    'brand_id' => ['label' => 'Brand', 'type' => 'relation', 'relation_table' => 'brands', 'relation_label' => 'name'],
                    'department_value_id' => ['label' => 'Department', 'type' => 'relation', 'relation_table' => 'item_category_values', 'relation_label' => 'value'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'allow_negative_stock' => ['label' => 'Allow Negative Stock', 'type' => 'boolean'],
                    'tax_inclusive' => ['label' => 'Tax Inclusive Flag', 'type' => 'boolean'],
                    'product_type' => ['label' => 'Product Type', 'type' => 'select', 'options' => ['Standard', 'Serialized', 'Service Component', 'Kit', 'Assembly']],
                    'sell_price' => ['label' => 'Selling Price (Fixed or % Adjust)', 'type' => 'math_number'],
                    'mrp' => ['label' => 'MRP (Fixed or % Adjust)', 'type' => 'math_number'],
                ],
            ],
            'item_stocks' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Branch Inventory & Storage Locations',
                'description' => 'Bulk assign warehouse bin/rack locations and stock alert thresholds.',
                'icon' => 'fas fa-warehouse text-info',
                'model' => ItemStock::class,
                'table' => 'item_stocks',
                'label_field' => 'batch_no',
                'code_field' => 'rack_location',
                'filterable_fields' => [
                    'branch_id' => ['label' => 'Branch Outlet', 'type' => 'relation', 'relation_table' => 'branches', 'relation_label' => 'name'],
                    'rack_location' => ['label' => 'Rack / Bin Location', 'type' => 'string'],
                ],
                'updatable_fields' => [
                    'rack_location' => ['label' => 'Rack / Bin Location', 'type' => 'string'],
                ],
            ],
            'brands' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Brands Master',
                'description' => 'Bulk activate or deactivate product brands.',
                'icon' => 'fas fa-tag text-secondary',
                'model' => Brand::class,
                'table' => 'brands',
                'label_field' => 'name',
                'code_field' => 'code',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'description' => ['label' => 'Description / Notes', 'type' => 'string'],
                ],
            ],
            'item_categories' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Item Categories Master',
                'description' => 'Bulk update item category active statuses.',
                'icon' => 'fas fa-sitemap text-indigo',
                'model' => ItemCategory::class,
                'table' => 'item_categories',
                'label_field' => 'name',
                'code_field' => 'code',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'item_category_values' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Category Values / Subcategories',
                'description' => 'Bulk update subcategories, departments, and category values status.',
                'icon' => 'fas fa-folder-tree text-teal',
                'model' => ItemCategoryValue::class,
                'table' => 'item_category_values',
                'label_field' => 'value',
                'code_field' => 'code',
                'filterable_fields' => [
                    'item_category_id' => ['label' => 'Parent Category', 'type' => 'relation', 'relation_table' => 'item_categories', 'relation_label' => 'name'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'uoms' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Units of Measure (UOM)',
                'description' => 'Bulk activate or deactivate measurement units.',
                'icon' => 'fas fa-balance-scale text-maroon',
                'model' => Uom::class,
                'table' => 'uoms',
                'label_field' => 'name',
                'code_field' => 'alias',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'colors' => [
                'group' => 'Catalog & Inventory',
                'title' => 'Colors Master',
                'description' => 'Bulk update color variant master statuses.',
                'icon' => 'fas fa-palette text-purple',
                'model' => Color::class,
                'table' => 'colors',
                'label_field' => 'name',
                'code_field' => 'name',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],

            // ==========================================
            // 2. CUSTOMERS, SUPPLIERS & CRM
            // ==========================================
            'customers' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Customers Directory',
                'description' => 'Bulk assign customer categories, sales types, area groupings, and credit terms.',
                'icon' => 'fas fa-users text-success',
                'model' => Customer::class,
                'table' => 'customers',
                'label_field' => 'name',
                'code_field' => 'mobile',
                'filterable_fields' => [
                    'customer_category_id' => ['label' => 'Customer Category', 'type' => 'relation', 'relation_table' => 'customer_categories', 'relation_label' => 'name'],
                    'area_id' => ['label' => 'Area', 'type' => 'relation', 'relation_table' => 'areas', 'relation_label' => 'name'],
                    'sales_type' => ['label' => 'Sales Type', 'type' => 'select', 'options' => ['Local', 'Interstate', 'Credit', 'Walk-in']],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                    'status' => ['label' => 'Active Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'customer_category_id' => ['label' => 'Customer Category', 'type' => 'relation', 'relation_table' => 'customer_categories', 'relation_label' => 'name'],
                    'area_id' => ['label' => 'Area', 'type' => 'relation', 'relation_table' => 'areas', 'relation_label' => 'name'],
                    'sales_type' => ['label' => 'Sales Type', 'type' => 'select', 'options' => ['Local', 'Interstate', 'Credit', 'Walk-in']],
                    'credit_limit' => ['label' => 'Credit Limit (₹)', 'type' => 'number'],
                    'credit_days' => ['label' => 'Credit Days', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                ],
            ],
            'suppliers' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Suppliers & Vendors',
                'description' => 'Bulk update supplier credit terms, geographical regions, and active status.',
                'icon' => 'fas fa-truck text-warning',
                'model' => Supplier::class,
                'table' => 'suppliers',
                'label_field' => 'name',
                'code_field' => 'gstin',
                'filterable_fields' => [
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'credit_limit' => ['label' => 'Credit Limit (₹)', 'type' => 'number'],
                    'credit_days' => ['label' => 'Credit Days', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                ],
            ],
            'customer_categories' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Customer Categories & Pricing Tiers',
                'description' => 'Bulk update customer discount percentages, app access, and loyalty rules.',
                'icon' => 'fas fa-id-badge text-primary',
                'model' => CustomerCategory::class,
                'table' => 'customer_categories',
                'label_field' => 'name',
                'code_field' => 'business_type',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'enable_loyalty' => ['label' => 'Loyalty Enabled', 'type' => 'boolean'],
                    'app_access' => ['label' => 'App Access', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'discount_percent' => ['label' => 'Discount Percent (%)', 'type' => 'number'],
                    'enable_loyalty' => ['label' => 'Enable Loyalty Points', 'type' => 'boolean'],
                    'app_access' => ['label' => 'Allow App Access', 'type' => 'boolean'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'customer_types' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Customer Types Master',
                'description' => 'Bulk update customer types status.',
                'icon' => 'fas fa-user-tag text-secondary',
                'model' => CustomerType::class,
                'table' => 'customer_types',
                'label_field' => 'name',
                'code_field' => 'code',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'customer_pets' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Customer Pets Registry',
                'description' => 'Bulk update pet records by pet type, breed, or gender.',
                'icon' => 'fas fa-paw text-success',
                'model' => CustomerPet::class,
                'table' => 'customer_pets',
                'label_field' => 'name',
                'code_field' => 'gender',
                'filterable_fields' => [
                    'pet_type_id' => ['label' => 'Pet Type', 'type' => 'relation', 'relation_table' => 'pet_types', 'relation_label' => 'name'],
                    'breed_id' => ['label' => 'Breed', 'type' => 'relation', 'relation_table' => 'breeds', 'relation_label' => 'name'],
                    'gender' => ['label' => 'Gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Unknown']],
                ],
                'updatable_fields' => [
                    'gender' => ['label' => 'Gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Unknown']],
                    'remarks' => ['label' => 'Remarks / Notes', 'type' => 'string'],
                ],
            ],
            'pet_types' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Pet Types Master',
                'description' => 'Bulk update pet species and categories.',
                'icon' => 'fas fa-dog text-orange',
                'model' => PetType::class,
                'table' => 'pet_types',
                'label_field' => 'name',
                'code_field' => 'name',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'breeds' => [
                'group' => 'CRM & Stakeholders',
                'title' => 'Breeds Master',
                'description' => 'Bulk update pet breeds status by pet type.',
                'icon' => 'fas fa-shield-cat text-olive',
                'model' => Breed::class,
                'table' => 'breeds',
                'label_field' => 'name',
                'code_field' => 'name',
                'filterable_fields' => [
                    'pet_type_id' => ['label' => 'Pet Type', 'type' => 'relation', 'relation_table' => 'pet_types', 'relation_label' => 'name'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],

            // ==========================================
            // 3. POS, OUTLETS & OPERATIONS
            // ==========================================
            'branches' => [
                'group' => 'POS & Operations',
                'title' => 'Branches & Store Outlets',
                'description' => 'Bulk update store outlets status, webstore enablement, or loyalty.',
                'icon' => 'fas fa-store text-danger',
                'model' => Branch::class,
                'table' => 'branches',
                'label_field' => 'name',
                'code_field' => 'city',
                'filterable_fields' => [
                    'state' => ['label' => 'State', 'type' => 'string'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                    'status' => ['label' => 'Active Status', 'type' => 'boolean'],
                    'webstore' => ['label' => 'Webstore Enabled', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'webstore' => ['label' => 'Webstore Enabled', 'type' => 'boolean'],
                    'enable_thirdparty_loyalty' => ['label' => 'Enable Third-party Loyalty', 'type' => 'boolean'],
                    'city' => ['label' => 'City', 'type' => 'string'],
                    'state' => ['label' => 'State', 'type' => 'string'],
                ],
            ],
            'registers' => [
                'group' => 'POS & Operations',
                'title' => 'POS Counter Registers',
                'description' => 'Bulk update counter registers status and online sales permissions.',
                'icon' => 'fas fa-cash-register text-info',
                'model' => Register::class,
                'table' => 'registers',
                'label_field' => 'name',
                'code_field' => 'register_prefix',
                'filterable_fields' => [
                    'branch_id' => ['label' => 'Branch Outlet', 'type' => 'relation', 'relation_table' => 'branches', 'relation_label' => 'name'],
                    'status' => ['label' => 'Active Status', 'type' => 'boolean'],
                    'online_sales_allowed' => ['label' => 'Online Sales Allowed', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'online_sales_allowed' => ['label' => 'Online Sales Allowed', 'type' => 'boolean'],
                ],
            ],
            'sales_types' => [
                'group' => 'POS & Operations',
                'title' => 'Sales Types Master',
                'description' => 'Bulk update billing and sales types status.',
                'icon' => 'fas fa-receipt text-secondary',
                'model' => SalesType::class,
                'table' => 'sales_types',
                'label_field' => 'name',
                'code_field' => 'code',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'tender_types' => [
                'group' => 'POS & Operations',
                'title' => 'Tender & Payment Types',
                'description' => 'Bulk configure payment methods and cash tender options.',
                'icon' => 'fas fa-credit-card text-success',
                'model' => TenderType::class,
                'table' => 'tender_types',
                'label_field' => 'name',
                'code_field' => 'code',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'areas' => [
                'group' => 'POS & Operations',
                'title' => 'Geographic Sales Areas',
                'description' => 'Bulk update delivery and sales territory areas.',
                'icon' => 'fas fa-map-marked-alt text-primary',
                'model' => Area::class,
                'table' => 'areas',
                'label_field' => 'name',
                'code_field' => 'name',
                'filterable_fields' => [
                    'branch_id' => ['label' => 'Branch Outlet', 'type' => 'relation', 'relation_table' => 'branches', 'relation_label' => 'name'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'users' => [
                'group' => 'POS & Operations',
                'title' => 'Users & Staff Branch Assignment',
                'description' => 'Bulk allocate staff to specific branches and update system access status.',
                'icon' => 'fas fa-user-tie text-danger',
                'model' => User::class,
                'table' => 'users',
                'label_field' => 'name',
                'code_field' => 'email',
                'filterable_fields' => [
                    'branch_id' => ['label' => 'Branch Outlet', 'type' => 'relation', 'relation_table' => 'branches', 'relation_label' => 'name'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'branch_id' => ['label' => 'Assign Branch Outlet', 'type' => 'relation', 'relation_table' => 'branches', 'relation_label' => 'name'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],

            // ==========================================
            // 4. TAXES & SYSTEM MASTERS
            // ==========================================
            'gst_taxes' => [
                'group' => 'Taxes & System Masters',
                'title' => 'GST Tax Slabs Master',
                'description' => 'Bulk update tax slab percentages, rates, and active states.',
                'icon' => 'fas fa-percent text-warning',
                'model' => GstTax::class,
                'table' => 'gst_taxes',
                'label_field' => 'description',
                'code_field' => 'percentage',
                'filterable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'percentage' => ['label' => 'Tax Percentage (%)', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                ],
            ],
            'custom_field_definitions' => [
                'group' => 'Taxes & System Masters',
                'title' => 'Custom Field Definitions',
                'description' => 'Bulk update custom fields required flags and visibility.',
                'icon' => 'fas fa-sliders-h text-info',
                'model' => CustomFieldDefinition::class,
                'table' => 'custom_field_definitions',
                'label_field' => 'field_name',
                'code_field' => 'module',
                'filterable_fields' => [
                    'module' => ['label' => 'Module Name', 'type' => 'string'],
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'is_required' => ['label' => 'Required Flag', 'type' => 'boolean'],
                ],
                'updatable_fields' => [
                    'status' => ['label' => 'Status', 'type' => 'boolean'],
                    'is_required' => ['label' => 'Is Required', 'type' => 'boolean'],
                ],
            ],
        ];
    }

    /**
     * Get all database tables available for dynamic table mode, excluding system/framework internal tables.
     */
    public function getAvailableDatabaseTables(): array
    {
        $rawTables = Schema::getTableListing();

        $excludedTables = [
            'migrations',
            'failed_jobs',
            'jobs',
            'job_batches',
            'personal_access_tokens',
            'sessions',
            'password_resets',
            'password_reset_tokens',
            'cache',
            'cache_locks',
            'sqlite_sequence',
        ];

        $tables = [];
        foreach ($rawTables as $tName) {
            if (! in_array($tName, $excludedTables, true)) {
                $tables[] = $tName;
            }
        }

        sort($tables);
        return $tables;
    }

    /**
     * Get columns of any database table.
     */
    public function getTableColumns(string $tableName): array
    {
        if (! in_array($tableName, $this->getAvailableDatabaseTables(), true)) {
            throw new \InvalidArgumentException("Table '{$tableName}' is not allowed or does not exist.");
        }

        return Schema::getColumnListing($tableName);
    }

    /**
     * Build the filtered query based on module definition or dynamic table mode.
     */
    public function buildQuery(string $moduleKey, array $filters, ?string $dynamicTable = null)
    {
        // 1. DYNAMIC ANY-TABLE MODE
        if ($moduleKey === 'dynamic_table' || str_starts_with($moduleKey, 'table:')) {
            $tableName = $dynamicTable ?: str_replace('table:', '', $moduleKey);
            $allowedTables = $this->getAvailableDatabaseTables();
            if (! in_array($tableName, $allowedTables, true)) {
                throw new \InvalidArgumentException("Table '{$tableName}' is invalid or restricted.");
            }

            $query = DB::table($tableName);
            $columns = Schema::getColumnListing($tableName);

            foreach ($filters as $col => $val) {
                if ($val === null || $val === '' || ! in_array($col, $columns, true)) {
                    continue;
                }

                if (is_numeric($val)) {
                    $query->where($col, $val);
                } else {
                    $query->where($col, 'like', "%{$val}%");
                }
            }

            return $query;
        }

        // 2. PRE-CONFIGURED STANDARD MODULES
        $defs = $this->getModuleDefinitions();
        if (! isset($defs[$moduleKey])) {
            throw new \InvalidArgumentException("Unsupported module: {$moduleKey}");
        }

        $def = $defs[$moduleKey];
        $modelClass = $def['model'];
        $query = $modelClass::query();

        foreach ($filters as $field => $val) {
            if ($val === null || $val === '') {
                continue;
            }

            if (! isset($def['filterable_fields'][$field])) {
                continue;
            }

            $fieldMeta = $def['filterable_fields'][$field];
            $type = $fieldMeta['type'] ?? 'string';

            if ($type === 'boolean') {
                $boolVal = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                $query->where($field, $boolVal ? 1 : 0);
            } elseif ($type === 'relation' || $type === 'number') {
                $query->where($field, $val);
            } else {
                $query->where($field, 'like', "%{$val}%");
            }
        }

        return $query;
    }

    /**
     * Preview matching records count and sample dataset.
     */
    public function preview(string $moduleKey, array $filters, ?string $dynamicTable = null): array
    {
        $isDynamic = ($moduleKey === 'dynamic_table' || str_starts_with($moduleKey, 'table:'));

        if ($isDynamic) {
            $tableName = $dynamicTable ?: str_replace('table:', '', $moduleKey);
            $query = $this->buildQuery('dynamic_table', $filters, $tableName);
            $totalCount = $query->count();
            $columns = Schema::getColumnListing($tableName);
            $primaryKey = in_array('id', $columns, true) ? 'id' : ($columns[0] ?? 'id');
            $labelKey = in_array('name', $columns, true) ? 'name' : ($columns[1] ?? $primaryKey);
            $codeKey = in_array('code', $columns, true) ? 'code' : ($columns[2] ?? $primaryKey);

            $sampleRows = $query->take(20)->get()->map(function ($row) use ($primaryKey, $labelKey, $codeKey) {
                $arr = (array) $row;
                return [
                    'id' => $arr[$primaryKey] ?? 'N/A',
                    'label' => $arr[$labelKey] ?? 'N/A',
                    'code' => $arr[$codeKey] ?? '-',
                    'raw' => $arr,
                ];
            })->toArray();

            return [
                'total_count' => $totalCount,
                'sample_rows' => $sampleRows,
                'module' => [
                    'title' => "Custom Table: {$tableName}",
                    'table' => $tableName,
                    'is_dynamic' => true,
                ],
            ];
        }

        $defs = $this->getModuleDefinitions();
        $def = $defs[$moduleKey];

        $query = $this->buildQuery($moduleKey, $filters);
        $totalCount = $query->count();

        $labelField = $def['label_field'];
        $codeField = $def['code_field'];

        $sampleRows = $query->take(20)->get()->map(function ($row) use ($labelField, $codeField) {
            return [
                'id' => $row->id,
                'label' => $row->{$labelField} ?? 'N/A',
                'code' => $row->{$codeField} ?? '-',
                'raw' => $row->toArray(),
            ];
        })->toArray();

        return [
            'total_count' => $totalCount,
            'sample_rows' => $sampleRows,
            'module' => $def,
        ];
    }

    /**
     * Execute the bulk update safely inside a database transaction with audit logging.
     */
    public function executeUpdate(
        string $moduleKey,
        array $filters,
        string $targetField,
        mixed $targetValue,
        ?string $mathMode = null,
        ?float $mathPercent = null,
        ?string $dynamicTable = null
    ): array {
        $isDynamic = ($moduleKey === 'dynamic_table' || str_starts_with($moduleKey, 'table:'));
        $user = Auth::user();

        if ($isDynamic) {
            $tableName = $dynamicTable ?: str_replace('table:', '', $moduleKey);
            $allowedTables = $this->getAvailableDatabaseTables();
            if (! in_array($tableName, $allowedTables, true)) {
                throw new \InvalidArgumentException("Invalid or restricted table: {$tableName}");
            }

            $columns = Schema::getColumnListing($tableName);
            if (! in_array($targetField, $columns, true)) {
                throw new \InvalidArgumentException("Column '{$targetField}' does not exist in {$tableName}");
            }

            // Guard against changing primary key
            if ($targetField === 'id') {
                throw new \InvalidArgumentException("Modifying primary key 'id' is prohibited for database integrity.");
            }

            $query = $this->buildQuery('dynamic_table', $filters, $tableName);
            $matchingCount = $query->count();

            if ($matchingCount === 0) {
                return [
                    'success' => false,
                    'message' => 'No records matched the filter criteria in table ' . $tableName,
                    'updated_count' => 0,
                ];
            }

            $auditReason = "Universal Bulk Modifier: Updated {$matchingCount} records in table [{$tableName}] column [{$targetField}]";

            DB::transaction(function () use ($query, $tableName, $targetField, $targetValue, $user, $auditReason, $filters, $matchingCount) {
                $query->update([
                    $targetField => ($targetValue === '' || $targetValue === null) ? null : $targetValue,
                ]);

                AuditLog::create([
                    'user_id' => $user?->id,
                    'branch_id' => $user?->branch_id,
                    'action' => 'bulk_update',
                    'auditable_type' => "Table: {$tableName}",
                    'auditable_id' => 0,
                    'old_values' => ['table' => $tableName, 'filters' => $filters, 'affected_rows' => $matchingCount],
                    'new_values' => ['column' => $targetField, 'value' => $targetValue],
                    'reason' => $auditReason,
                    'ip_address' => Request::ip() ?: '127.0.0.1',
                ]);
            });

            return [
                'success' => true,
                'message' => "Successfully updated {$matchingCount} records in table '{$tableName}'.",
                'updated_count' => $matchingCount,
            ];
        }

        // PRE-CONFIGURED MODULES
        $defs = $this->getModuleDefinitions();
        if (! isset($defs[$moduleKey])) {
            throw new \InvalidArgumentException("Invalid module: {$moduleKey}");
        }

        $def = $defs[$moduleKey];
        if (! isset($def['updatable_fields'][$targetField])) {
            throw new \InvalidArgumentException("Field '{$targetField}' is not updatable on {$moduleKey}");
        }

        $fieldMeta = $def['updatable_fields'][$targetField];
        $fieldType = $fieldMeta['type'] ?? 'string';

        $query = $this->buildQuery($moduleKey, $filters);
        $matchingCount = $query->count();

        if ($matchingCount === 0) {
            return [
                'success' => false,
                'message' => 'No records matched the filter criteria.',
                'updated_count' => 0,
            ];
        }

        $auditReason = "Universal Bulk Modifier: Updated {$matchingCount} records in {$def['title']} [{$targetField}]";

        DB::transaction(function () use ($query, $targetField, $targetValue, $fieldType, $mathMode, $mathPercent, $def, $user, $auditReason, $filters, $matchingCount) {
            // Handle Math Percentage Adjustment on Price fields
            if ($fieldType === 'math_number' && $mathMode === 'percent' && $mathPercent !== null) {
                $multiplier = 1 + ($mathPercent / 100);
                $query->update([
                    $targetField => DB::raw("ROUND({$targetField} * {$multiplier}, 2)"),
                ]);
            } else {
                // Standard value assignment
                $finalValue = $targetValue;
                if ($fieldType === 'boolean') {
                    $finalValue = filter_var($targetValue, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                } elseif ($fieldType === 'relation' || $fieldType === 'number') {
                    $finalValue = ($targetValue === '' || $targetValue === null) ? null : $targetValue;
                }

                $query->update([
                    $targetField => $finalValue,
                ]);
            }

            // Create Master Audit Log entry
            AuditLog::create([
                'user_id' => $user?->id,
                'branch_id' => $user?->branch_id,
                'action' => 'bulk_update',
                'auditable_type' => $def['model'],
                'auditable_id' => 0,
                'old_values' => ['filters' => $filters, 'affected_rows' => $matchingCount],
                'new_values' => [
                    'field' => $targetField,
                    'value' => $targetValue,
                    'math_mode' => $mathMode,
                    'math_percent' => $mathPercent,
                ],
                'reason' => $auditReason,
                'ip_address' => Request::ip() ?: '127.0.0.1',
            ]);
        });

        return [
            'success' => true,
            'message' => "Successfully updated {$matchingCount} records in {$def['title']}.",
            'updated_count' => $matchingCount,
        ];
    }
}
