<?php

namespace App\Services\Menu;

use App\Models\MenuVisibilitySetting;
use App\Models\User;
use App\Services\Reports\ReportPermissionRegistry;
use Spatie\Permission\Models\Role;

class MenuCatalogService
{
    /**
     * Get structured catalog of all main application menus & submenus.
     */
    public static function getMainMenusCatalog(): array
    {
        return [
            'master' => [
                'label' => 'Master Catalog & Entities',
                'icon' => 'fas fa-th-large',
                'category' => 'Master',
                'permission' => 'items.create',
                'items' => [
                    'master.item-categories' => ['label' => 'Item Category', 'url' => 'master/item-categories', 'permission' => 'item-categories.create'],
                    'master.item-category-values' => ['label' => 'Item Category Values', 'url' => 'master/item-category-values', 'permission' => 'item-category-values.create'],
                    'master.product-types' => ['label' => 'Product Types', 'url' => 'master/product-types', 'permission' => 'product-types.create'],
                    'master.brands' => ['label' => 'Brand', 'url' => 'master/brands', 'permission' => 'brands.create'],
                    'master.items' => ['label' => 'Item Master', 'url' => 'master/items', 'permission' => 'items.create'],
                    'master.item-price-change' => ['label' => 'Item Price Change / MRP', 'url' => 'master/item-price-change', 'permission' => 'item-price-change.edit'],
                    'master.uoms' => ['label' => 'UOM (Unit of Measure)', 'url' => 'master/uoms', 'permission' => 'uoms.create'],
                    'master.customers' => ['label' => 'Customer Master', 'url' => 'master/customers', 'permission' => 'customers.view'],
                    'master.customer-categories' => ['label' => 'Customer Category', 'url' => 'master/customer-categories', 'permission' => 'customer-categories.create'],
                    'master.customer-types' => ['label' => 'Customer Type', 'url' => 'master/customer-types', 'permission' => 'customer-types.create'],
                    'master.loyalty-programs' => ['label' => 'Loyalty Programs', 'url' => 'master/loyalty-programs', 'permission' => 'loyalty-programs.create'],
                    'master.suppliers' => ['label' => 'Supplier Master', 'url' => 'master/suppliers', 'permission' => 'suppliers.create'],
                    'master.branches' => ['label' => 'Branches', 'url' => 'master/branches', 'permission' => 'branches.create'],
                    'master.registers' => ['label' => 'POS Registers / Counters', 'url' => 'master/registers', 'permission' => 'registers.create'],
                    'master.gst-taxes' => ['label' => 'GST Tax Rates', 'url' => 'master/gst-taxes', 'permission' => 'gst-taxes.create'],
                    'master.gst-types' => ['label' => 'GST Types', 'url' => 'master/gst-types', 'permission' => 'gst-types.create'],
                    'master.tender-types' => ['label' => 'Tender Types (Payment Modes)', 'url' => 'master/tender-types', 'permission' => 'tender-types.create'],
                    // Auxiliary / Advanced Masters
                    'master.assembly' => ['label' => 'Assembly & BOM', 'url' => 'master/aux/assembly', 'permission' => 'items.create'],
                    'master.kit-mapping' => ['label' => 'Kit Mapping', 'url' => 'master/aux/kit-mapping', 'permission' => 'items.create'],
                    'master.item-property' => ['label' => 'Item Property Setting', 'url' => 'master/aux/item-property-setting', 'permission' => 'items.create'],
                    'master.item-ean-upc' => ['label' => 'Item EAN/UPC Entry', 'url' => 'master/aux/item-ean-upc-entry', 'permission' => 'items.create'],
                    'master.uom-mapping' => ['label' => 'UOM Vs Item Mapping', 'url' => 'master/aux/uom-vs-item-mapping', 'permission' => 'items.create'],
                ]
            ],
            'sales' => [
                'label' => 'Sales & Billing Counter',
                'icon' => 'fas fa-shopping-cart',
                'category' => 'Sales',
                'permission' => 'sales-bills.create',
                'items' => [
                    'sales.pos' => ['label' => 'POS Terminal', 'url' => 'pos', 'permission' => 'sales-bills.create'],
                    'sales.sales-bills' => ['label' => 'Sales Bills (Tax Invoices)', 'url' => 'sales/sales-bills', 'permission' => 'sales-bills.create'],
                    'sales.sales-returns' => ['label' => 'Sales Returns (Credit Notes)', 'url' => 'sales/sales-returns', 'permission' => 'sales-returns.create'],
                    'sales.sales-quotations' => ['label' => 'Sales Quotations / Estimates', 'url' => 'sales/sales-quotations', 'permission' => 'sales-quotations.create'],
                    'sales.sales-orders' => ['label' => 'Sales Orders', 'url' => 'sales/sales-orders', 'permission' => 'sales-orders.create'],
                    'sales.sales-delivery-notes' => ['label' => 'Sales Delivery Notes', 'url' => 'sales/sales-delivery-notes', 'permission' => 'sales-delivery-notes.create'],
                    'sales.eway-bills' => ['label' => 'E-Way Bills Management', 'url' => 'sales/eway-bills', 'permission' => 'eway-bills.view'],
                    'sales.einvoices' => ['label' => 'E-Invoices Portal (IRN)', 'url' => 'sales/einvoices', 'permission' => 'einvoices.view'],
                ]
            ],
            'purchase' => [
                'label' => 'Purchase & Procurement',
                'icon' => 'fas fa-truck',
                'category' => 'Purchase',
                'permission' => 'purchase-invoices.create',
                'items' => [
                    'purchase.purchase-invoices' => ['label' => 'Purchase Invoices (Inward Bill)', 'url' => 'purchase/purchase-invoices', 'permission' => 'purchase-invoices.create'],
                    'purchase.purchase-orders' => ['label' => 'Purchase Orders (PO)', 'url' => 'purchase/purchase-orders', 'permission' => 'purchase-orders.create'],
                    'purchase.purchase-receipt-notes' => ['label' => 'Goods Receipt Notes (GRN)', 'url' => 'purchase/purchase-receipt-notes', 'permission' => 'purchase-receipt-notes.create'],
                    'purchase.purchase-returns' => ['label' => 'Purchase Returns (Debit Notes)', 'url' => 'purchase/purchase-returns', 'permission' => 'purchase-returns.create'],
                    'purchase.purchase-indents' => ['label' => 'Purchase Indents / Requisitions', 'url' => 'purchase/purchase-indents', 'permission' => 'purchase-indents.create'],
                ]
            ],
            'till' => [
                'label' => 'Till & Cash Drawer',
                'icon' => 'fas fa-cash-register',
                'category' => 'Till',
                'permission' => 'till.open',
                'items' => [
                    'till.sessions' => ['label' => 'Till Shifts (Open / Close Cash)', 'url' => 'till/sessions', 'permission' => 'till.open'],
                    'till.movements' => ['label' => 'Cash In / Cash Out Movements', 'url' => 'till/movements', 'permission' => 'till.open'],
                ]
            ],
            'inventory' => [
                'label' => 'Inventory & Stock Management',
                'icon' => 'fas fa-boxes',
                'category' => 'Inventory',
                'permission' => 'stock-updates.create',
                'items' => [
                    'inventory.stock-transfers' => ['label' => 'Stock Transfers (Branch / Warehouse)', 'url' => 'inventory/stock-transfers', 'permission' => 'stock-transfers.create'],
                    'inventory.stock-updates' => ['label' => 'Stock Adjustments & Corrections', 'url' => 'inventory/stock-updates', 'permission' => 'stock-updates.create'],
                    'inventory.stock-approvals' => ['label' => 'Stock Adjustment Approvals', 'url' => 'inventory/stock-updates/approvals', 'permission' => 'stock-update-approval.approve'],
                    'inventory.opening-stocks' => ['label' => 'Opening Stock Setup', 'url' => 'inventory/opening-stocks', 'permission' => 'opening-stocks.create'],
                    'inventory.damage-stocks' => ['label' => 'Damage & Wastage Stocks', 'url' => 'inventory/damage-stocks', 'permission' => 'damage-stocks.create'],
                    'inventory.barcode-printing' => ['label' => 'Barcode Label Studio', 'url' => 'inventory/barcode-printing', 'permission' => 'barcode-printing.print'],
                    'inventory.kit-recipes' => ['label' => 'Kit Recipes & Assembly', 'url' => 'inventory/kit-recipes', 'permission' => 'kit-recipes.create'],
                    'inventory.kit-preparation' => ['label' => 'Kit Preparation', 'url' => 'inventory/kit-preparation', 'permission' => 'kit-preparation.create'],
                    'inventory.repack' => ['label' => 'Bulk Repack Operations', 'url' => 'inventory/repack', 'permission' => 'repack.create'],
                ]
            ],
            'tools' => [
                'label' => 'Tools, Utilities & Configurations',
                'icon' => 'fas fa-tools',
                'category' => 'Tools',
                'permission' => 'manage-tools',
                'items' => [
                    'tools.whatsapp-settings' => ['label' => 'WhatsApp Integration & Templates', 'url' => 'tools/whatsapp-settings', 'permission' => 'manage-tools'],
                    'tools.receipt-designer' => ['label' => 'Receipt & Invoice Print Designer', 'url' => 'tools/receipt-designer', 'permission' => 'manage-tools'],
                    'tools.document-sequences' => ['label' => 'Document Sequences & Numbering', 'url' => 'tools/document-sequences', 'permission' => 'manage-tools'],
                    'tools.custom-fields' => ['label' => 'No-Code Custom Fields Builder', 'url' => 'tools/custom-fields', 'permission' => 'manage-tools'],
                    'tools.bulk-updater' => ['label' => 'Universal Bulk Modifier', 'url' => 'tools/bulk-updater', 'permission' => 'manage-tools'],
                    'tools.backups' => ['label' => 'Database Backups Management', 'url' => 'tools/backups', 'permission' => 'manage-tools'],
                    'tools.system-health' => ['label' => 'System Health & Diagnostics', 'url' => 'tools/system-health', 'permission' => 'manage-tools'],
                    'tools.system-error-logs' => ['label' => 'System Error & Exception Logs', 'url' => 'tools/system-error-logs', 'permission' => 'manage-tools'],
                    'tools.pos-settings' => ['label' => 'POS Screen Lock Settings', 'url' => 'tools/pos-settings', 'permission' => 'manage-tools'],
                    'tools.integrations-gst' => ['label' => 'GST E-Filing Hub', 'url' => 'tools/integrations-gst', 'permission' => 'manage-tools'],
                    'tools.module-integrations' => ['label' => 'Module & Menu Integration Manager', 'url' => 'tools/module-integrations', 'permission' => 'manage-tools'],
                ]
            ],
            'finance' => [
                'label' => 'Finance & Accounts',
                'icon' => 'fas fa-file-invoice-dollar',
                'category' => 'Finance',
                'permission' => 'vouchers.create',
                'items' => [
                    'finance.ledgers' => ['label' => 'Ledger Accounts Master', 'url' => 'finance/ledgers', 'permission' => 'ledgers.create'],
                    'finance.vouchers' => ['label' => 'Voucher Entry (Payment/Receipt/Journal)', 'url' => 'finance/vouchers', 'permission' => 'vouchers.create'],
                    'finance.settlements' => ['label' => 'Customer / Supplier Credit Settlement', 'url' => 'finance/settlements', 'permission' => 'bill-settlements.create'],
                    'finance.day-book' => ['label' => 'Day Book', 'url' => 'finance/reports/day-book', 'permission' => 'vouchers.create'],
                    'finance.general-ledger' => ['label' => 'General Ledger', 'url' => 'finance/reports/general-ledger', 'permission' => 'vouchers.create'],
                    'finance.trial-balance' => ['label' => 'Trial Balance', 'url' => 'finance/reports/trial-balance', 'permission' => 'vouchers.create'],
                    'finance.profit-loss' => ['label' => 'Trading - Profit & Loss', 'url' => 'finance/reports/profit-loss', 'permission' => 'vouchers.create'],
                ]
            ],
        ];
    }

    /**
     * Get grouped reports catalog from ReportPermissionRegistry.
     */
    public static function getReportsCatalog(): array
    {
        $allReports = ReportPermissionRegistry::getAllReports();
        $grouped = [];

        foreach ($allReports as $slug => $meta) {
            $cat = $meta['category'] ?? 'General';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'label' => $cat . ' Reports',
                    'category' => $cat,
                    'items' => []
                ];
            }

            $grouped[$cat]['items']['report.' . $slug] = [
                'slug' => $slug,
                'label' => $meta['label'],
                'url' => $meta['url'] ?? "reports/view/{$slug}",
                'permission' => "{$meta['perm_key']}.view",
                'parent_perm' => "{$meta['parent_perm']}.view",
                'has_export' => $meta['has_export'] ?? false,
            ];
        }

        return $grouped;
    }

    /**
     * Quick Presets for 1-click configuration.
     */
    public static function getPresets(): array
    {
        return [
            'minimal_retail' => [
                'name' => 'Minimal Retail POS (कैशियर व सरल दुकान)',
                'description' => 'सिर्फ बिलिंग, टिल, इन्वेंटरी और डेली रिपोर्ट्स चालू रहेंगी। बाकी 280+ फालतू मेनू 1-क्लिक में गायब।',
                'badge' => 'badge-success',
                'enabled_keys' => [
                    // Main Menus
                    'master.items',
                    'master.item-price-change',
                    'master.customers',
                    'sales.pos',
                    'sales.sales-bills',
                    'sales.sales-returns',
                    'till.sessions',
                    'till.movements',
                    'inventory.stock-transfers',
                    'inventory.stock-updates',
                    'tools.whatsapp-settings',
                    'tools.receipt-designer',
                    'tools.module-integrations',
                    // Reports
                    'report.dashboard',
                    'report.sales-summary',
                    'report.billwise-sales',
                    'report.eod',
                    'report.tender-summary',
                    'report.current-stock',
                    'report.reorder-report',
                ],
            ],
            'standard_store' => [
                'name' => 'Standard Supermarket / Store (स्टैंडर्ड स्टोर)',
                'description' => 'बिलिंग, परचेज, सप्लायर, स्टॉक एडजस्टमेंट, जीएसटी रिपोर्ट्स और अकाउंट्स लेजर सक्रिय।',
                'badge' => 'badge-primary',
                'enabled_keys' => [
                    // Master
                    'master.items', 'master.item-categories', 'master.brands', 'master.item-price-change',
                    'master.uoms', 'master.customers', 'master.suppliers', 'master.gst-taxes', 'master.registers',
                    // Sales
                    'sales.pos', 'sales.sales-bills', 'sales.sales-returns', 'sales.sales-quotations', 'sales.sales-orders',
                    // Purchase
                    'purchase.purchase-invoices', 'purchase.purchase-orders', 'purchase.purchase-receipt-notes', 'purchase.purchase-returns',
                    // Till
                    'till.sessions', 'till.movements',
                    // Inventory
                    'inventory.stock-transfers', 'inventory.stock-updates', 'inventory.damage-stocks', 'inventory.barcode-printing',
                    // Tools
                    'tools.whatsapp-settings', 'tools.receipt-designer', 'tools.document-sequences', 'tools.backups', 'tools.module-integrations',
                    // Finance
                    'finance.ledgers', 'finance.vouchers', 'finance.settlements', 'finance.day-book',
                    // Reports
                    'report.dashboard', 'report.sales-summary', 'report.billwise-sales', 'report.gst-sales-summary',
                    'report.sales-return-summary', 'report.tender-summary', 'report.eod', 'report.sales-margin-itemwise',
                    'report.purchase-detail', 'report.gst-purchase-summary', 'report.purchase-order-summary',
                    'report.current-stock', 'report.stock-transfer-summary', 'report.damage-stock-summary', 'report.reorder-report',
                    'report.gst-summary-report', 'report.gst-hsn-sales-summary',
                ],
            ],
            'full_erp' => [
                'name' => 'Full Enterprise ERP (सारे मेनू चालू)',
                'description' => 'सभी 320+ मेनू, सबमेनू और रिपोर्ट्स पूरी तरह चालू रहेंगी।',
                'badge' => 'badge-dark',
                'enabled_keys' => 'ALL',
            ],
        ];
    }

    /**
     * Apply a preset for a role (or global).
     */
    public static function applyPreset(string $presetKey, ?int $roleId = null): void
    {
        $presets = self::getPresets();
        if (!isset($presets[$presetKey])) {
            return;
        }

        $preset = $presets[$presetKey];

        // If 'ALL', reset all restrictions
        if ($preset['enabled_keys'] === 'ALL') {
            MenuVisibilitySetting::resetRole($roleId);
            return;
        }

        $allMainMenus = self::getMainMenusCatalog();
        $allReports = self::getReportsCatalog();

        $keyMap = [];
        $enabledSet = array_flip($preset['enabled_keys']);

        // Collect all main menu keys
        foreach ($allMainMenus as $cat) {
            foreach (array_keys($cat['items']) as $key) {
                $keyMap[$key] = isset($enabledSet[$key]);
            }
        }

        // Collect all report keys
        foreach ($allReports as $cat) {
            foreach (array_keys($cat['items']) as $key) {
                $keyMap[$key] = isset($enabledSet[$key]);
            }
        }

        // Ensure module-integrations itself is ALWAYS enabled!
        $keyMap['tools.module-integrations'] = true;

        MenuVisibilitySetting::bulkSetVisibility($keyMap, $roleId);
    }

    /**
     * Resolve a menu item key from AdminLTE menu array item.
     */
    public static function resolveMenuKey(array $item): ?string
    {
        if (isset($item['visibility_key'])) {
            return $item['visibility_key'];
        }

        $url = $item['url'] ?? ($item['route'] ?? null);
        if (!$url && isset($item['text'])) {
            return 'header.' . \Illuminate\Support\Str::slug($item['text']);
        }

        if (!$url) return null;

        // Clean url prefix/suffix
        $cleanUrl = trim(str_replace(['reports/view/', 'reports/'], '', $url), '/');

        // Check report slug
        if (str_starts_with($url, 'reports')) {
            return 'report.' . $cleanUrl;
        }

        // Replace slashes with dots
        return str_replace('/', '.', trim($url, '/'));
    }

    /**
     * Check if a report should be displayed on reports index page.
     */
    public static function isReportVisible(string $slug, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        $roleId = $user ? $user->roles->first()?->id : null;
        
        return MenuVisibilitySetting::isVisible('report.' . $slug, $roleId);
    }
}
