<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'title' => 'Urbanpos',
    'title_prefix' => '',
    'title_postfix' => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Here you can allow or not the use of external google fonts. Disabling the
    | google fonts may be useful if your admin panel internet access is
    | restricted somehow.
    |
    | For detailed instructions you can look the google fonts section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'google_fonts' => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'logo' => '<b>Urban</b>pos',
    'logo_img' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'Admin Logo',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    |
    | Here you can setup an alternative logo to use on your login and register
    | screens. When disabled, the admin panel logo will be used instead.
    |
    | For detailed instructions you can look the auth logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'auth_logo' => [
        'enabled' => false,
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'Auth Logo',
            'class' => '',
            'width' => 50,
            'height' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
    |
    | Here you can change the preloader animation configuration. Currently, two
    | modes are supported: 'fullscreen' for a fullscreen preloader animation
    | and 'cwrapper' to attach the preloader animation into the content-wrapper
    | element and avoid overlapping it with the sidebars and the top navbar.
    |
    | For detailed instructions you can look the preloader section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'preloader' => [
        'enabled' => true,
        'mode' => 'fullscreen',
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'AdminLTE Preloader Image',
            'effect' => 'animation__shake',
            'width' => 60,
            'height' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'usermenu_enabled' => true,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => null,
    'layout_fixed_navbar' => null,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card' => 'card-outline card-primary',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-dark-primary elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_route_url' => false,
    'dashboard_url' => 'home',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => 'register',
    'password_reset_url' => 'password/reset',
    'password_email_url' => 'password/email',
    'profile_url' => false,
    'disable_darkmode_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Asset Bundling
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Asset Bundling option for the admin panel.
    | Currently, the next modes are supported: 'mix', 'vite' and 'vite_js_only'.
    | When using 'vite_js_only', it's expected that your CSS is imported using
    | JavaScript. Typically, in your application's 'resources/js/app.js' file.
    | If you are not using any of these, leave it as 'false'.
    |
    | For detailed instructions you can look the asset bundling section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'laravel_asset_bundling' => false,
    'laravel_css_path' => 'css/app.css',
    'laravel_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'menu' => [
        // Navbar items:
        [
            'type' => 'navbar-search',
            'text' => 'search',
            'topnav_right' => true,
        ],
        [
            'type' => 'fullscreen-widget',
            'topnav_right' => true,
        ],

        // Sidebar items:
        [
            'type' => 'sidebar-menu-search',
            'text' => 'search',
        ],
        [
            'text' => 'Dashboard',
            'url' => 'home',
            'icon' => 'fas fa-fw fa-tachometer-alt',
        ],
        ['header' => 'Master'],
        [
            'text' => 'Item',
            'icon' => 'fas fa-fw fa-boxes',
            'submenu' => [
                ['text' => 'Item Category', 'url' => 'master/item-categories'],
                ['text' => 'Item Category Values', 'url' => 'master/item-category-values'],
                ['text' => 'Brand', 'url' => 'master/brands'],
                ['text' => 'Item', 'url' => 'master/items'],
                ['text' => 'Item Property Setting', 'url' => 'master/aux/item-property-setting'],
                ['text' => 'Item EAN/UPC Entry', 'url' => 'master/aux/item-ean-upc-entry'],
                ['text' => 'Assembly', 'url' => 'master/aux/assembly'],
                ['text' => 'Kit Mapping', 'url' => 'master/aux/kit-mapping'],
                ['text' => 'Item Price Change', 'url' => 'master/item-price-change'],
                ['text' => 'UOM', 'url' => 'master/uoms'],
                ['text' => 'UOM Vs Item Mapping', 'url' => 'master/aux/uom-vs-item-mapping'],
                ['text' => 'Tax Slab', 'url' => 'master/aux/tax-slab'],
            ],
        ],
        [
            'text' => 'Customer',
            'icon' => 'fas fa-fw fa-users',
            'submenu' => [
                ['text' => 'Customer Category', 'url' => 'master/customer-categories'],
                ['text' => 'Customer', 'url' => 'master/customers'],
                ['text' => 'Area', 'url' => 'master/areas'],
                ['text' => 'Loyalty Program Info', 'url' => 'master/aux/loyalty-program-info'],
                ['text' => 'Loyalty Points Update', 'url' => 'master/aux/loyalty-points-update'],
                [
                    'text' => 'Pet Masters',
                    'submenu' => [
                        ['text' => 'Pet Types', 'url' => 'master/pet-types'],
                        ['text' => 'Breed Master', 'url' => 'master/breeds'],
                        ['text' => 'Color Master', 'url' => 'master/colors'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Supplier',
            'url' => 'master/suppliers',
            'icon' => 'fas fa-fw fa-truck',
        ],
        [
            'text' => 'Tax',
            'icon' => 'fas fa-fw fa-percent',
            'submenu' => [
                ['text' => 'GST Tax', 'url' => 'master/gst-taxes'],
                ['text' => 'GSTNo Restriction Master', 'url' => 'master/aux/gstno-restriction'],
            ],
        ],
        [
            'text' => 'Branch',
            'icon' => 'fas fa-fw fa-store',
            'submenu' => [
                ['text' => 'Branch', 'url' => 'master/branches'],
                ['text' => 'Distribution Centre Mapping', 'url' => 'master/aux/distribution-centre-mapping'],
            ],
        ],
        [
            'text' => 'Register',
            'url' => 'master/registers',
            'icon' => 'fas fa-fw fa-cash-register',
        ],
        [
            'text' => 'Promotion',
            'icon' => 'fas fa-fw fa-bullhorn',
            'submenu' => [
                ['text' => 'Promotion Management', 'url' => 'master/aux/promotion-management'],
            ],
        ],
        [
            'text' => 'More Master',
            'icon' => 'fas fa-fw fa-ellipsis-h',
            'submenu' => [
                [
                    'text' => 'Tools',
                    'submenu' => [
                        ['text' => 'Master Configuration', 'url' => 'master/aux/master-configuration'],
                        ['text' => 'Tender Type Values', 'url' => 'master/tender-type-values'],
                        ['text' => 'Unicode Master', 'url' => 'master/aux/unicode-master'],
                        ['text' => 'Tender Type', 'url' => 'master/tender-types'],
                        ['text' => 'Master Attributes', 'url' => 'master/aux/master-attributes'],
                        ['text' => 'Addon Devices Inactivation', 'url' => 'master/aux/addon-devices'],
                    ],
                ],
                [
                    'text' => 'Utility',
                    'submenu' => [
                        ['text' => 'Transporter', 'url' => 'master/aux/transporters'],
                        ['text' => 'Freight Settings', 'url' => 'master/aux/freight-settings'],
                    ],
                ],
            ],
        ],
        ['header' => 'Sales'],
        [
            'text' => 'Sales Quotation',
            'url' => 'sales/aux/quotations',
            'icon' => 'fas fa-fw fa-file-signature',
        ],
        [
            'text' => 'Sales Order',
            'url' => 'sales/aux/orders',
            'icon' => 'fas fa-fw fa-shopping-basket',
        ],
        [
            'text' => 'Sales Order Approval',
            'url' => 'sales/aux/order-approval',
            'icon' => 'fas fa-fw fa-user-check',
        ],
        [
            'text' => 'Delivery Note',
            'url' => 'sales/aux/delivery-notes',
            'icon' => 'fas fa-fw fa-truck',
        ],
        [
            'text' => 'Sales Bill',
            'url' => 'sales/sales-bills',
            'icon' => 'fas fa-fw fa-cash-register',
        ],
        [
            'text' => 'Sales Return',
            'url' => 'sales/sales-returns',
            'icon' => 'fas fa-fw fa-undo',
        ],
        [
            'text' => 'Delivery Note Return',
            'url' => 'sales/aux/delivery-note-returns',
            'icon' => 'fas fa-fw fa-truck-loading',
        ],
        [
            'text' => 'More Sales',
            'icon' => 'fas fa-fw fa-ellipsis-h',
            'submenu' => [
                ['text' => 'Transfer Out', 'url' => 'inventory/stock-transfers'],
                ['text' => 'Transfer Out Approval & Auto TI', 'url' => 'sales/aux/transfer-out-approval'],
            ],
        ],
        ['header' => 'Purchase'],
        [
            'text' => 'Purchase Order',
            'url' => 'purchase/purchase-orders',
            'icon' => 'fas fa-fw fa-file-invoice',
        ],
        [
            'text' => 'Receipt Note',
            'url' => 'purchase/aux/receipt-notes',
            'icon' => 'fas fa-fw fa-receipt',
        ],
        [
            'text' => 'Purchase Invoice',
            'url' => 'purchase/purchase-invoices',
            'icon' => 'fas fa-fw fa-file-invoice-dollar',
        ],
        [
            'text' => 'Purchase Returns',
            'url' => 'purchase/aux/purchase-returns',
            'icon' => 'fas fa-fw fa-undo-alt',
        ],
        [
            'text' => 'PO Cancel',
            'url' => 'purchase/purchase-orders',
            'icon' => 'fas fa-fw fa-ban',
        ],
        [
            'text' => 'Transfer In',
            'url' => 'inventory/stock-transfers/pending-receipt',
            'icon' => 'fas fa-fw fa-dolly-flatbed',
        ],
        [
            'text' => 'Indent',
            'url' => 'purchase/aux/indents',
            'icon' => 'fas fa-fw fa-clipboard-list',
        ],
        [
            'text' => 'More Purchase',
            'icon' => 'fas fa-fw fa-ellipsis-h',
            'submenu' => [
                ['text' => 'Auto Indent', 'url' => 'purchase/aux/auto-indent'],
                ['text' => 'Indent Cancellation', 'url' => 'purchase/aux/indent-cancellation'],
                ['text' => 'Transfer In Touch', 'url' => 'purchase/aux/transfer-in-touch'],
                ['text' => 'Indent CutOff Time Configuration', 'url' => 'purchase/aux/indent-cutoff'],
            ],
        ],
        ['header' => 'Till'],
        [
            'text' => 'Open Till',
            'url' => 'till/sessions/open',
            'icon' => 'fas fa-fw fa-door-open',
        ],
        [
            'text' => 'Till Sessions',
            'url' => 'till/sessions',
            'icon' => 'fas fa-fw fa-cash-register',
        ],
        ['header' => 'Inventory'],
        [
            'text' => 'Opening Stock Entry',
            'url' => 'inventory/opening-stocks',
            'icon' => 'fas fa-fw fa-dolly',
        ],
        [
            'text' => 'Damage Stock Entry',
            'url' => 'inventory/damage-stocks',
            'icon' => 'fas fa-fw fa-dumpster-fire',
        ],
        [
            'text' => 'Stock Update Entry',
            'url' => 'inventory/stock-updates',
            'icon' => 'fas fa-fw fa-warehouse',
        ],
        [
            'text' => 'Stock Update Approval',
            'url' => 'inventory/stock-update-approval',
            'icon' => 'fas fa-fw fa-clipboard-check',
        ],
        [
            'text' => 'Barcode Printing',
            'url' => 'inventory/barcode-printing',
            'icon' => 'fas fa-fw fa-barcode',
        ],
        [
            'text' => 'Price Fixing',
            'icon' => 'fas fa-fw fa-tags',
            'submenu' => [
                [
                    'text' => 'Price Fixing(markup/markdown)',
                    'url' => 'inventory/price-fixing/markup-markdown',
                ],
                [
                    'text' => 'Price Level',
                    'url' => 'inventory/price-fixing/price-level',
                ],
                [
                    'text' => 'Price Level Vs Items',
                    'url' => 'inventory/price-fixing/price-level-items',
                ],
            ],
        ],
        [
            'text' => 'Change Selling',
            'url' => 'inventory/change-selling',
            'icon' => 'fas fa-fw fa-dollar-sign',
        ],
        [
            'text' => 'More Operations',
            'icon' => 'fas fa-fw fa-layer-group',
            'submenu' => [
                ['text' => 'Repack', 'url' => 'inventory/repack', 'icon' => 'fas fa-fw fa-boxes'],
                ['text' => 'Change Serial No', 'url' => 'inventory/change-serial-no', 'icon' => 'fas fa-fw fa-barcode'],
                ['text' => 'Price Drop', 'url' => 'inventory/price-drop', 'icon' => 'fas fa-fw fa-level-down-alt'],
                ['text' => 'Kit Preparation', 'url' => 'inventory/kit-preparation', 'icon' => 'fas fa-fw fa-tools'],
                ['text' => 'Kit Unpack', 'url' => 'inventory/kit-unpack', 'icon' => 'fas fa-fw fa-box-open'],
                ['text' => 'Shelf Talker', 'url' => 'inventory/shelf-talker', 'icon' => 'fas fa-fw fa-sticky-note'],
            ],
        ],
        ['header' => 'Reports'],
        [
            'text' => 'Reports Center',
            'icon' => 'fas fa-fw fa-chart-bar',
            'submenu' => [
                ['text' => 'Masters Reports', 'url' => 'reports?group=masters'],
                ['text' => 'Purchase Reports', 'url' => 'reports/purchase-detail'],
                ['text' => 'Sales Reports', 'url' => 'reports/sales-summary'],
                ['text' => 'EOD / Settlement', 'url' => 'reports/eod'],
                ['text' => 'Inventory Reports', 'url' => 'reports/current-stock'],
                ['text' => 'Audit Reports', 'url' => 'reports?group=audit'],
                ['text' => 'My Reports', 'url' => 'reports?group=my-reports'],
            ],
        ],
        ['header' => 'Tools'],
        [
            'text' => 'Configuration',
            'icon' => 'fas fa-fw fa-cogs',
            'submenu' => [
                ['text' => 'Role Master', 'url' => 'master/users'],
                ['text' => 'Financial Years', 'url' => 'master/financial-years'],
                ['text' => 'Userwise Configuration', 'url' => 'tools/userwise-config'],
                ['text' => 'Business Configuration', 'url' => 'tools/business-config'],
                ['text' => 'Function Key Mapping', 'url' => 'tools/function-keys'],
                ['text' => 'Ledger Map', 'url' => 'tools/ledger-map'],
                ['text' => 'Asset LedgerMap', 'url' => 'tools/asset-ledger'],
                ['text' => 'Mail Server Configuration', 'url' => 'tools/mail-server'],
                ['text' => 'Category Wise Sequence', 'url' => 'tools/category-sequence'],
            ],
        ],
        [
            'text' => 'Integrations',
            'icon' => 'fas fa-fw fa-plug',
            'submenu' => [
                ['text' => 'GoFrugal Alert', 'url' => 'tools/integrations-alert'],
                ['text' => 'GST Efiling', 'url' => 'tools/integrations-gst'],
                ['text' => 'GOFRUGAL Gosure', 'url' => 'tools/integrations-gosure'],
            ],
        ],
        [
            'text' => 'Master Migration',
            'url' => 'tools/master-migration',
            'icon' => 'fas fa-fw fa-database',
        ],
        [
            'text' => 'Manage Subscription',
            'url' => 'tools/manage-subscription',
            'icon' => 'fas fa-fw fa-certificate',
        ],
        [
            'text' => 'More Tools',
            'icon' => 'fas fa-fw fa-ellipsis-h',
            'submenu' => [
                ['text' => 'Year Begin Sequence Change', 'url' => 'tools/year-begin-sequence'],
                ['text' => 'Session Management', 'url' => 'tools/session-management'],
                ['text' => 'Reprint', 'url' => 'tools/reprint'],
                ['text' => 'Multiple Dispatch', 'url' => 'tools/multiple-dispatch'],
                ['text' => 'Report Scheduler', 'url' => 'tools/report-scheduler'],
                ['text' => 'E-way Update', 'url' => 'tools/eway-update'],
                ['text' => 'Barcode Config', 'url' => 'tools/barcode-config'],
                ['text' => 'Service User Consent', 'url' => 'tools/service-consent'],
            ],
        ],
        ['header' => 'Finance And Accounts'],
        [
            'text' => 'Ledger Master',
            'url' => 'finance/ledgers',
            'icon' => 'fas fa-fw fa-book',
        ],
        [
            'text' => 'Voucher Entry',
            'url' => 'finance/vouchers',
            'icon' => 'fas fa-fw fa-file-invoice',
        ],
        [
            'text' => 'Finance Reports',
            'url' => 'finance/reports',
            'icon' => 'fas fa-fw fa-chart-pie',
            'submenu' => [
                ['text' => 'General Ledger', 'url' => 'finance/reports/general-ledger'],
                ['text' => 'Day Book', 'url' => 'finance/reports/day-book'],
                ['text' => 'Trial Balance', 'url' => 'finance/reports/trial-balance'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Plugins-Configuration
    |
    */

    'plugins' => [
        'Datatables' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8',
                ],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    |
    | Here we change the IFrame mode configuration. Note these changes will
    | only apply to the view that extends and enable the IFrame mode.
    |
    | For detailed instructions you can look the iframe mode section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/IFrame-Mode-Configuration
    |
    */

    'iframe' => [
        'default_tab' => [
            'url' => null,
            'title' => null,
        ],
        'buttons' => [
            'close' => true,
            'close_all' => true,
            'close_all_other' => true,
            'scroll_left' => true,
            'scroll_right' => true,
            'fullscreen' => true,
        ],
        'options' => [
            'loading_screen' => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'livewire' => false,
];
