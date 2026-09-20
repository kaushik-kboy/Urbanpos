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
    'classes_sidebar_nav' => 'nav-child-indent',
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
            'text' => 'POS Terminal',
            'url' => 'pos',
            'icon' => 'fas fa-cash-register text-success',
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
        [
            'text' => 'Master',
            'icon' => 'fas fa-fw fa-th-large',
            'submenu' => [
                [
                    'text' => 'Item',
                    'icon' => 'fas fa-fw fa-boxes',
                    'submenu' => [
                        ['text' => 'Item Category', 'url' => 'master/item-categories', 'icon' => 'fas fa-fw fa-folder'],
                        ['text' => 'Item Category Values', 'url' => 'master/item-category-values', 'icon' => 'fas fa-fw fa-folder-open'],
                        ['text' => 'Brand', 'url' => 'master/brands', 'icon' => 'fas fa-fw fa-tag'],
                        ['text' => 'Item', 'url' => 'master/items', 'icon' => 'fas fa-fw fa-box'],
                        ['text' => 'Item Property Setting', 'url' => 'master/aux/item-property-setting', 'icon' => 'fas fa-fw fa-sliders-h'],
                        ['text' => 'Item EAN/UPC Entry', 'url' => 'master/aux/item-ean-upc-entry', 'icon' => 'fas fa-fw fa-barcode'],
                        ['text' => 'Assembly', 'url' => 'master/aux/assembly', 'icon' => 'fas fa-fw fa-puzzle-piece'],
                        ['text' => 'Kit Mapping', 'url' => 'master/aux/kit-mapping', 'icon' => 'fas fa-fw fa-sitemap'],
                        ['text' => 'Item Price Change', 'url' => 'master/item-price-change', 'icon' => 'fas fa-fw fa-tags'],
                        ['text' => 'UOM', 'url' => 'master/uoms', 'icon' => 'fas fa-fw fa-balance-scale'],
                        ['text' => 'UOM Vs Item Mapping', 'url' => 'master/aux/uom-vs-item-mapping', 'icon' => 'fas fa-fw fa-link'],
                        ['text' => 'Tax Slab', 'url' => 'master/aux/tax-slab', 'icon' => 'fas fa-fw fa-percentage'],
                    ],
                ],
                [
                    'text' => 'Customer',
                    'icon' => 'fas fa-fw fa-users',
                    'submenu' => [
                        ['text' => 'Customer Category', 'url' => 'master/customer-categories', 'icon' => 'fas fa-fw fa-user-tag'],
                        ['text' => 'Customer', 'url' => 'master/customers', 'icon' => 'fas fa-fw fa-user'],
                        ['text' => 'Area', 'url' => 'master/areas', 'icon' => 'fas fa-fw fa-map-marker-alt'],
                        ['text' => 'Loyalty Program Info', 'url' => 'master/loyalty-programs', 'icon' => 'fas fa-fw fa-award'],
                        ['text' => 'Loyalty Points Update', 'url' => 'master/loyalty-points', 'icon' => 'fas fa-fw fa-star'],
                        [
                            'text' => 'Pet Masters',
                            'icon' => 'fas fa-fw fa-paw',
                            'submenu' => [
                                ['text' => 'Pet Types', 'url' => 'master/pet-types', 'icon' => 'fas fa-fw fa-dog'],
                                ['text' => 'Breed Master', 'url' => 'master/breeds', 'icon' => 'fas fa-fw fa-bone'],
                                ['text' => 'Color Master', 'url' => 'master/colors', 'icon' => 'fas fa-fw fa-palette'],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'Supplier',
                    'url' => 'master/suppliers',
                    'icon' => 'fas fa-fw fa-truck',
                    'active' => ['master/suppliers*'],
                ],
                [
                    'text' => 'Tax',
                    'icon' => 'fas fa-fw fa-percent',
                    'submenu' => [
                        ['text' => 'GST Tax', 'url' => 'master/gst-taxes', 'icon' => 'fas fa-fw fa-receipt'],
                        ['text' => 'GSTNo Restriction Master', 'url' => 'master/aux/gstno-restriction', 'icon' => 'fas fa-fw fa-shield-alt'],
                    ],
                ],
                [
                    'text' => 'Branch',
                    'icon' => 'fas fa-fw fa-store',
                    'submenu' => [
                        ['text' => 'Branch', 'url' => 'master/branches', 'icon' => 'fas fa-fw fa-store-alt'],
                        ['text' => 'Distribution Centre Mapping', 'url' => 'master/aux/distribution-centre-mapping', 'icon' => 'fas fa-fw fa-network-wired'],
                    ],
                ],
                [
                    'text' => 'Register',
                    'url' => 'master/registers',
                    'icon' => 'fas fa-fw fa-cash-register',
                    'active' => ['master/registers*'],
                ],
                [
                    'text' => 'Promotion',
                    'icon' => 'fas fa-fw fa-bullhorn',
                    'submenu' => [
                        ['text' => 'Promotion Management', 'url' => 'master/aux/promotion-management', 'icon' => 'fas fa-fw fa-gift'],
                    ],
                ],
                [
                    'text' => 'More Master',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'submenu' => [
                        [
                            'text' => 'Tools',
                            'icon' => 'fas fa-fw fa-tools',
                            'submenu' => [
                                ['text' => 'Master Configuration', 'url' => 'master/aux/master-configuration', 'icon' => 'fas fa-fw fa-cogs'],
                                ['text' => 'Tender Type Values', 'url' => 'master/tender-type-values', 'icon' => 'fas fa-fw fa-coins'],
                                ['text' => 'Unicode Master', 'url' => 'master/aux/unicode-master', 'icon' => 'fas fa-fw fa-font'],
                                ['text' => 'Tender Type', 'url' => 'master/tender-types', 'icon' => 'fas fa-fw fa-credit-card'],
                                ['text' => 'Master Attributes', 'url' => 'master/aux/master-attributes', 'icon' => 'fas fa-fw fa-list-ul'],
                                ['text' => 'Addon Devices Inactivation', 'url' => 'master/aux/addon-devices', 'icon' => 'fas fa-fw fa-power-off'],
                            ],
                        ],
                        [
                            'text' => 'Utility',
                            'icon' => 'fas fa-fw fa-wrench',
                            'submenu' => [
                                ['text' => 'Transporter', 'url' => 'master/aux/transporters', 'icon' => 'fas fa-fw fa-shipping-fast'],
                                ['text' => 'Freight Settings', 'url' => 'master/aux/freight-settings', 'icon' => 'fas fa-fw fa-calculator'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Sales',
            'icon' => 'fas fa-fw fa-shopping-cart',
            'submenu' => [
                [
                    'text' => 'Sales Bill',
                    'url' => 'sales/sales-bills',
                    'icon' => 'fas fa-fw fa-cash-register',
                    'active' => ['sales/sales-bills*'],
                ],
                [
                    'text' => 'Sales Return',
                    'url' => 'sales/sales-returns',
                    'icon' => 'fas fa-fw fa-undo',
                    'active' => ['sales/sales-returns*'],
                ],
                [
                    'text' => 'Sales Quotation',
                    'url' => 'sales/sales-quotations',
                    'icon' => 'fas fa-fw fa-file-signature',
                    'active' => ['sales/sales-quotations*'],
                ],
                [
                    'text' => 'Sales Order',
                    'url' => 'sales/sales-orders',
                    'icon' => 'fas fa-fw fa-shopping-basket',
                    'active' => ['sales/sales-orders*'],
                ],
                [
                    'text' => 'Sales Order Approval',
                    'url' => 'sales/aux/order-approval',
                    'icon' => 'fas fa-fw fa-user-check',
                    'active' => ['sales/aux/order-approval*'],
                ],
                [
                    'text' => 'Delivery Note',
                    'url' => 'sales/delivery-notes',
                    'icon' => 'fas fa-fw fa-truck',
                    'active' => ['sales/delivery-notes*'],
                ],
                [
                    'text' => 'Delivery Note Return',
                    'url' => 'sales/aux/delivery-note-returns',
                    'icon' => 'fas fa-fw fa-truck-loading',
                    'active' => ['sales/aux/delivery-note-returns*'],
                ],
                [
                    'text' => 'More Sales',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'submenu' => [
                        ['text' => 'Transfer Out', 'url' => 'inventory/stock-transfers', 'icon' => 'fas fa-fw fa-dolly'],
                        ['text' => 'Transfer Out Approval & Auto TI', 'url' => 'sales/aux/transfer-out-approval', 'icon' => 'fas fa-fw fa-check-double'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Purchase',
            'icon' => 'fas fa-fw fa-shopping-bag',
            'submenu' => [
                [
                    'text' => 'Purchase Order',
                    'url' => 'purchase/purchase-orders',
                    'icon' => 'fas fa-fw fa-file-invoice',
                    'active' => ['purchase/purchase-orders*'],
                ],
                [
                    'text' => 'Receipt Note',
                    'url' => 'purchase/purchase-receipt-notes',
                    'icon' => 'fas fa-fw fa-receipt',
                    'active' => ['purchase/purchase-receipt-notes*'],
                ],
                [
                    'text' => 'Purchase Invoice',
                    'url' => 'purchase/purchase-invoices',
                    'icon' => 'fas fa-fw fa-file-invoice-dollar',
                    'active' => ['purchase/purchase-invoices*'],
                ],
                [
                    'text' => 'Purchase Returns',
                    'url' => 'purchase/purchase-returns',
                    'icon' => 'fas fa-fw fa-undo-alt',
                    'active' => ['purchase/purchase-returns*'],
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
                    'active' => ['inventory/stock-transfers/pending-receipt*'],
                ],
                [
                    'text' => 'Purchase Indent',
                    'url' => 'purchase/purchase-indents',
                    'icon' => 'fas fa-fw fa-clipboard-list',
                    'active' => ['purchase/purchase-indents*'],
                ],
                [
                    'text' => 'More Purchase',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'submenu' => [
                        ['text' => 'Auto Indent', 'url' => 'purchase/aux/auto-indent', 'icon' => 'fas fa-fw fa-magic'],
                        ['text' => 'Indent Cancellation', 'url' => 'purchase/aux/indent-cancellation', 'icon' => 'fas fa-fw fa-times-circle'],
                        ['text' => 'Transfer In Touch', 'url' => 'purchase/aux/transfer-in-touch', 'icon' => 'fas fa-fw fa-hand-pointer'],
                        ['text' => 'Indent CutOff Time Configuration', 'url' => 'purchase/aux/indent-cutoff', 'icon' => 'fas fa-fw fa-clock'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Till',
            'icon' => 'fas fa-fw fa-cash-register',
            'submenu' => [
                [
                    'text' => 'Open Till',
                    'url' => 'till/sessions/open',
                    'icon' => 'fas fa-fw fa-door-open',
                    'active' => ['till/sessions/open*'],
                ],
                [
                    'text' => 'Till Sessions',
                    'url' => 'till/sessions',
                    'icon' => 'fas fa-fw fa-history',
                    'active' => ['till/sessions*'],
                ],
            ],
        ],
        [
            'text' => 'Inventory',
            'icon' => 'fas fa-fw fa-warehouse',
            'submenu' => [
                [
                    'text' => 'Opening Stock Entry',
                    'url' => 'inventory/opening-stocks',
                    'icon' => 'fas fa-fw fa-dolly',
                    'active' => ['inventory/opening-stocks*'],
                ],
                [
                    'text' => 'Damage Stock Entry',
                    'url' => 'inventory/damage-stocks',
                    'icon' => 'fas fa-fw fa-dumpster-fire',
                    'active' => ['inventory/damage-stocks*'],
                ],
                [
                    'text' => 'Stock Update Entry',
                    'url' => 'inventory/stock-updates',
                    'icon' => 'fas fa-fw fa-sync-alt',
                    'active' => ['inventory/stock-updates*'],
                ],
                [
                    'text' => 'Stock Update Approval',
                    'url' => 'inventory/stock-update-approval',
                    'icon' => 'fas fa-fw fa-clipboard-check',
                    'active' => ['inventory/stock-update-approval*'],
                ],
                [
                    'text' => 'Barcode Printing',
                    'url' => 'inventory/barcode',
                    'icon' => 'fas fa-fw fa-barcode',
                    'active' => ['inventory/barcode*'],
                ],
                [
                    'text' => 'Price Fixing',
                    'icon' => 'fas fa-fw fa-tags',
                    'submenu' => [
                        [
                            'text' => 'Price Fixing(markup/markdown)',
                            'url' => 'inventory/price-fixing/markup-markdown',
                            'icon' => 'fas fa-fw fa-sort-amount-up-alt',
                        ],
                        [
                            'text' => 'Price Level',
                            'url' => 'inventory/price-fixing/price-level',
                            'icon' => 'fas fa-fw fa-layer-group',
                        ],
                        [
                            'text' => 'Price Level Vs Items',
                            'url' => 'inventory/price-fixing/price-level-items',
                            'icon' => 'fas fa-fw fa-tasks',
                        ],
                    ],
                ],
                [
                    'text' => 'Change Selling',
                    'url' => 'inventory/change-selling',
                    'icon' => 'fas fa-fw fa-dollar-sign',
                    'active' => ['inventory/change-selling*'],
                ],
                [
                    'text' => 'More Operations',
                    'icon' => 'fas fa-fw fa-cubes',
                    'submenu' => [
                        ['text' => 'Repack', 'url' => 'inventory/repack', 'icon' => 'fas fa-fw fa-boxes'],
                        ['text' => 'Change Serial No', 'url' => 'inventory/change-serial-no', 'icon' => 'fas fa-fw fa-barcode'],
                        ['text' => 'Price Drop', 'url' => 'inventory/price-drop', 'icon' => 'fas fa-fw fa-level-down-alt'],
                        ['text' => 'Kit Preparation', 'url' => 'inventory/kit-preparation', 'icon' => 'fas fa-fw fa-tools'],
                        ['text' => 'Kit Unpack', 'url' => 'inventory/kit-unpack', 'icon' => 'fas fa-fw fa-box-open'],
                        ['text' => 'Shelf Talker', 'url' => 'inventory/shelf-talker', 'icon' => 'fas fa-fw fa-sticky-note'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Reports',
            'icon' => 'fas fa-fw fa-chart-bar',
            'submenu' => [
                [
                    'text' => 'Reports Dashboard',
                    'url' => 'reports',
                    'icon' => 'fas fa-fw fa-th-large',
                    'active' => ['reports'],
                ],
                [
                    'text' => 'Custom Report Studio (Builder)',
                    'url' => 'reports/analytics-builder',
                    'icon' => 'fas fa-fw fa-magic text-info',
                    'active' => ['reports/analytics-builder*'],
                ],
                [
                    'text' => 'Smart Item & Customer 360°',
                    'url' => 'reports/smart-analytics',
                    'icon' => 'fas fa-fw fa-chart-line text-warning',
                    'active' => ['reports/smart-analytics*'],
                ],
                [
                    'text' => 'Masters',
                    'icon' => 'fas fa-fw fa-database',
                    'submenu' => [
                        ['text' => 'Customer Master', 'url' => 'reports/customer-master', 'icon' => 'fas fa-fw fa-users'],
                        ['text' => 'UOM Vs Item Mapping', 'url' => 'reports/view/uom-vs-item-mapping', 'icon' => 'fas fa-fw fa-balance-scale'],
                        ['text' => 'Kit Mapping', 'url' => 'reports/view/kit-mapping', 'icon' => 'fas fa-fw fa-box-open'],
                        ['text' => 'Customer Parent List', 'url' => 'reports/view/customer-parent-list', 'icon' => 'fas fa-fw fa-user-friends'],
                        ['text' => 'Customer Loyalty Details', 'url' => 'reports/customer-loyalty', 'icon' => 'fas fa-fw fa-star'],
                        ['text' => 'Customer Pet Details', 'url' => 'reports/customer-pet-details', 'icon' => 'fas fa-fw fa-paw'],
                        ['text' => 'Supplier Master', 'url' => 'reports/supplier-master', 'icon' => 'fas fa-fw fa-truck-loading'],
                        ['text' => 'Brand Master', 'url' => 'reports/view/brand-master', 'icon' => 'fas fa-fw fa-tag'],
                        ['text' => 'Tax Master', 'url' => 'reports/view/tax-master', 'icon' => 'fas fa-fw fa-percentage'],
                        ['text' => 'Area', 'url' => 'reports/view/area', 'icon' => 'fas fa-fw fa-map-marked-alt'],
                        ['text' => 'Branch Master', 'url' => 'reports/view/branch-master', 'icon' => 'fas fa-fw fa-code-branch'],
                        ['text' => 'Item Master', 'url' => 'reports/item-master', 'icon' => 'fas fa-fw fa-box'],
                        ['text' => 'Supplier Vs Items', 'url' => 'reports/view/supplier-vs-items', 'icon' => 'fas fa-fw fa-handshake'],
                        ['text' => 'Price List', 'url' => 'reports/view/price-list', 'icon' => 'fas fa-fw fa-dollar-sign'],
                        ['text' => 'Employee Master', 'url' => 'reports/view/employee-master', 'icon' => 'fas fa-fw fa-user-tie'],
                        ['text' => 'RO Master', 'url' => 'reports/view/ro-master', 'icon' => 'fas fa-fw fa-clipboard-check'],
                    ],
                ],
                [
                    'text' => 'Purchase',
                    'icon' => 'fas fa-fw fa-truck',
                    'submenu' => [
                        [
                            'text' => 'Transactions',
                            'icon' => 'fas fa-fw fa-exchange-alt',
                            'submenu' => [
                                ['text' => 'Purchase Order Summary', 'url' => 'reports/purchase-order-summary', 'icon' => 'fas fa-fw fa-clipboard-list'],
                                ['text' => 'Purchase Transit', 'url' => 'reports/view/purchase-transit', 'icon' => 'fas fa-fw fa-shipping-fast'],
                                ['text' => 'Purchase Order Details', 'url' => 'reports/view/purchase-order-details', 'icon' => 'fas fa-fw fa-file-invoice'],
                                ['text' => 'ReceiptNote Nowise Summary', 'url' => 'reports/view/receiptnote-nowise-summary', 'icon' => 'fas fa-fw fa-receipt'],
                                ['text' => 'ReceiptNote Nowise Detail', 'url' => 'reports/view/receiptnote-nowise-detail', 'icon' => 'fas fa-fw fa-list-alt'],
                                ['text' => 'Purchase Summary', 'url' => 'reports/view/purchase-summary', 'icon' => 'fas fa-fw fa-file-invoice-dollar'],
                                ['text' => 'Purchase Detail', 'url' => 'reports/purchase-detail', 'icon' => 'fas fa-fw fa-cart-arrow-down'],
                                ['text' => 'GIN Summary', 'url' => 'reports/view/gin-summary', 'icon' => 'fas fa-fw fa-warehouse'],
                                ['text' => 'Purchase Detail Serial', 'url' => 'reports/view/purchase-detail-serial', 'icon' => 'fas fa-fw fa-barcode'],
                                ['text' => 'GIN Detail', 'url' => 'reports/view/gin-detail', 'icon' => 'fas fa-fw fa-dolly'],
                                ['text' => 'GST Purchase Summary', 'url' => 'reports/gst-purchase-summary', 'icon' => 'fas fa-fw fa-percentage'],
                                ['text' => 'Purchase Order vs Invoice Report', 'url' => 'reports/view/po-vs-invoice', 'icon' => 'fas fa-fw fa-balance-scale'],
                                ['text' => 'ReceiptNote Itemwise', 'url' => 'reports/view/receiptnote-itemwise', 'icon' => 'fas fa-fw fa-boxes'],
                            ],
                        ],
                        ['text' => 'Pending/Cancelled Transactions', 'url' => 'reports/view/purchase-pending-cancelled', 'icon' => 'fas fa-fw fa-ban'],
                        ['text' => 'Returned Transactions', 'url' => 'reports/view/purchase-returned-transactions', 'icon' => 'fas fa-fw fa-undo'],
                        [
                            'text' => 'Purchase Analysis',
                            'icon' => 'fas fa-fw fa-chart-line',
                            'submenu' => [
                                ['text' => 'Supplierwise Purchase Summary', 'url' => 'reports/view/supplierwise-purchase-summary', 'icon' => 'fas fa-fw fa-truck'],
                                ['text' => 'PO/Purchase Discrepancy', 'url' => 'reports/view/po-purchase-discrepancy', 'icon' => 'fas fa-fw fa-exclamation-circle'],
                                ['text' => 'Supplierwise Purchase Details', 'url' => 'reports/view/supplierwise-purchase-details', 'icon' => 'fas fa-fw fa-list-ul'],
                                ['text' => 'Datewise Itemwise Consolidated Purchase Report', 'url' => 'reports/view/datewise-itemwise-purchase', 'icon' => 'fas fa-fw fa-calendar-alt'],
                                ['text' => 'Purchase Register Summary', 'url' => 'reports/view/purchase-register-summary', 'icon' => 'fas fa-fw fa-book'],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'Audit',
                    'icon' => 'fas fa-fw fa-shield-alt',
                    'submenu' => [
                        ['text' => 'Foot Fall Details', 'url' => 'reports/view/foot-fall-details', 'icon' => 'fas fa-fw fa-shoe-prints'],
                        ['text' => 'Reprint Count Details', 'url' => 'reports/view/reprint-count-details', 'icon' => 'fas fa-fw fa-print'],
                        ['text' => 'User Login Summary', 'url' => 'reports/view/user-login-summary', 'icon' => 'fas fa-fw fa-user-clock'],
                        ['text' => 'Audit Viewer Report', 'url' => 'reports/audit-logs', 'icon' => 'fas fa-fw fa-history'],
                        ['text' => 'Audit Detail Report', 'url' => 'reports/view/audit-detail-report', 'icon' => 'fas fa-fw fa-search'],
                        ['text' => 'GST Tax Change Audit Report', 'url' => 'reports/view/gst-tax-change-audit', 'icon' => 'fas fa-fw fa-percentage'],
                        ['text' => 'Service User Consent Summary', 'url' => 'reports/view/service-user-consent', 'icon' => 'fas fa-fw fa-user-check'],
                        ['text' => 'Email Audit Viewer Report', 'url' => 'reports/view/email-audit-viewer', 'icon' => 'fas fa-fw fa-envelope'],
                        ['text' => 'Audit Report (Cart entry Clear)', 'url' => 'reports/view/audit-cart-clear', 'icon' => 'fas fa-fw fa-shopping-cart'],
                        ['text' => 'Audit Detail (Cart entry Clear)', 'url' => 'reports/view/audit-detail-cart-clear', 'icon' => 'fas fa-fw fa-trash-alt'],
                    ],
                ],
                [
                    'text' => 'Sales',
                    'icon' => 'fas fa-fw fa-shopping-cart',
                    'submenu' => [
                        [
                            'text' => 'Sales',
                            'icon' => 'fas fa-fw fa-cash-register',
                            'submenu' => [
                                ['text' => 'Monthly Sales Summary [Storewise]', 'url' => 'reports/view/monthly-sales-summary-storewise', 'icon' => 'fas fa-fw fa-calendar-alt'],
                                ['text' => 'Monthly Sales Summary [Till Wise]', 'url' => 'reports/view/monthly-sales-summary-tillwise', 'icon' => 'fas fa-fw fa-calculator'],
                                ['text' => 'Serial Number Wise Price Details', 'url' => 'reports/view/serial-wise-price-details', 'icon' => 'fas fa-fw fa-barcode'],
                                ['text' => 'Daily Sales Summary [Store Wise]', 'url' => 'reports/sales-summary', 'icon' => 'fas fa-fw fa-chart-line'],
                                ['text' => 'Daily Sales Summary [Till Wise]', 'url' => 'reports/view/daily-sales-summary-tillwise', 'icon' => 'fas fa-fw fa-cash-register'],
                                ['text' => 'Daily Sales [Bill No Wise]', 'url' => 'reports/view/daily-sales-billwise', 'icon' => 'fas fa-fw fa-file-invoice'],
                                ['text' => 'Billwise Itemwise Sales Detail', 'url' => 'reports/billwise-sales', 'icon' => 'fas fa-fw fa-receipt'],
                                ['text' => 'Daily Sales [Bill No Wise With Timefilter]', 'url' => 'reports/view/daily-sales-timefilter', 'icon' => 'fas fa-fw fa-clock'],
                                ['text' => 'Offline Sales Bill Details', 'url' => 'reports/view/offline-sales-bill-details', 'icon' => 'fas fa-fw fa-wifi-slash'],
                                ['text' => 'Billwise Itemwise Sales Detail Serialwise', 'url' => 'reports/view/billwise-sales-serialwise', 'icon' => 'fas fa-fw fa-list-ol'],
                                ['text' => 'GST Sales Summary', 'url' => 'reports/gst-sales-summary', 'icon' => 'fas fa-fw fa-percentage'],
                                ['text' => 'GST Sales Taxwise', 'url' => 'reports/view/gst-sales-taxwise', 'icon' => 'fas fa-fw fa-coins'],
                                ['text' => 'Billwise Itemwise Sales With Assembly Details', 'url' => 'reports/view/billwise-sales-assembly', 'icon' => 'fas fa-fw fa-cogs'],
                                ['text' => 'Tender Type Detail', 'url' => 'reports/tender-summary', 'icon' => 'fas fa-fw fa-wallet'],
                                ['text' => 'Sales Register Summary', 'url' => 'reports/view/sales-register-summary', 'icon' => 'fas fa-fw fa-book'],
                                ['text' => 'Offer Claim Report', 'url' => 'reports/view/offer-claim-report', 'icon' => 'fas fa-fw fa-gift'],
                                ['text' => 'Counterwise Sales Report', 'url' => 'reports/view/counterwise-sales-report', 'icon' => 'fas fa-fw fa-store'],
                                ['text' => 'Sales Item Margin', 'url' => 'reports/sales-margin-itemwise', 'icon' => 'fas fa-fw fa-coins'],
                                ['text' => 'Customerwise Itemwise Sales', 'url' => 'reports/view/customerwise-itemwise-sales', 'icon' => 'fas fa-fw fa-user-tag'],
                                ['text' => 'Itemwise Customerwise Sales', 'url' => 'reports/view/itemwise-customerwise-sales', 'icon' => 'fas fa-fw fa-boxes'],
                                ['text' => 'Categorywise Sales', 'url' => 'reports/sales-margin-category', 'icon' => 'fas fa-fw fa-chart-pie'],
                                ['text' => 'Categorywise Sales Detail', 'url' => 'reports/view/categorywise-sales-detail', 'icon' => 'fas fa-fw fa-layer-group'],
                            ],
                        ],
                        [
                            'text' => 'Orders And Quotation',
                            'icon' => 'fas fa-fw fa-file-contract',
                            'submenu' => [
                                ['text' => 'Quotation Summary', 'url' => 'reports/quotation-order-summary', 'icon' => 'fas fa-fw fa-file-signature'],
                                ['text' => 'Quotation Details', 'url' => 'reports/view/quotation-details', 'icon' => 'fas fa-fw fa-file-alt'],
                                ['text' => 'Sales Order Summary', 'url' => 'reports/view/sales-order-summary', 'icon' => 'fas fa-fw fa-shopping-basket'],
                                ['text' => 'Sales Order Detail', 'url' => 'reports/view/sales-order-detail', 'icon' => 'fas fa-fw fa-list-alt'],
                                ['text' => 'Sales Order Stock Status', 'url' => 'reports/view/sales-order-stock-status', 'icon' => 'fas fa-fw fa-boxes'],
                            ],
                        ],
                        [
                            'text' => 'Delivery Reports',
                            'icon' => 'fas fa-fw fa-truck',
                            'submenu' => [
                                ['text' => 'Sales DeliveryNote Summary', 'url' => 'reports/view/sales-deliverynote-summary', 'icon' => 'fas fa-fw fa-clipboard-check'],
                                ['text' => 'Sales DeliveryNote Detail', 'url' => 'reports/view/sales-deliverynote-detail', 'icon' => 'fas fa-fw fa-dolly-flatbed'],
                                ['text' => 'Kitchen Preparation Report', 'url' => 'reports/view/kitchen-preparation-report', 'icon' => 'fas fa-fw fa-utensils'],
                                ['text' => 'Delivery Bill Summary Report', 'url' => 'reports/view/delivery-bill-summary', 'icon' => 'fas fa-fw fa-file-invoice'],
                                ['text' => 'Delivery Bill Detail Report', 'url' => 'reports/view/delivery-bill-detail', 'icon' => 'fas fa-fw fa-list'],
                                ['text' => 'Kitchen Preparation Report [Timewise]', 'url' => 'reports/view/kitchen-preparation-timewise', 'icon' => 'fas fa-fw fa-stopwatch'],
                            ],
                        ],
                        [
                            'text' => 'Returned Transactions',
                            'icon' => 'fas fa-fw fa-undo-alt',
                            'submenu' => [
                                ['text' => 'Sales Return Advice Detail', 'url' => 'reports/view/sales-return-advice-detail', 'icon' => 'fas fa-fw fa-undo'],
                                ['text' => 'Buy Back Details', 'url' => 'reports/view/buy-back-details', 'icon' => 'fas fa-fw fa-hand-holding-usd'],
                                ['text' => 'Sale Return Customerwise Detail', 'url' => 'reports/view/sale-return-customerwise', 'icon' => 'fas fa-fw fa-user-minus'],
                                ['text' => 'Sales Return Itemwise', 'url' => 'reports/view/sales-return-itemwise', 'icon' => 'fas fa-fw fa-box-open'],
                                ['text' => 'Sale Return Datewise', 'url' => 'reports/view/sale-return-datewise', 'icon' => 'fas fa-fw fa-calendar-times'],
                                ['text' => 'Sale Return Monthwise', 'url' => 'reports/view/sale-return-monthwise', 'icon' => 'fas fa-fw fa-calendar-alt'],
                                ['text' => 'Sales Return Summary', 'url' => 'reports/sales-return-summary', 'icon' => 'fas fa-fw fa-history'],
                            ],
                        ],
                        ['text' => 'Cancelled Transactions', 'url' => 'reports/view/sales-cancelled-transactions', 'icon' => 'fas fa-fw fa-times-circle'],
                        [
                            'text' => 'Sales Analysis',
                            'icon' => 'fas fa-fw fa-chart-pie',
                            'submenu' => [
                                ['text' => 'Margin Summary', 'url' => 'reports/view/margin-summary', 'icon' => 'fas fa-fw fa-percentage'],
                                ['text' => 'Session Report', 'url' => 'reports/eod', 'icon' => 'fas fa-fw fa-calendar-check'],
                                ['text' => 'Supplier Sales Report', 'url' => 'reports/view/supplier-sales-report', 'icon' => 'fas fa-fw fa-truck'],
                                ['text' => 'Supplierwise Sales Details', 'url' => 'reports/view/supplierwise-sales-details', 'icon' => 'fas fa-fw fa-list-ul'],
                                ['text' => 'Date Timewise Sales', 'url' => 'reports/view/date-timewise-sales', 'icon' => 'fas fa-fw fa-clock'],
                                ['text' => 'Consumption Sales Summary', 'url' => 'reports/view/consumption-sales-summary', 'icon' => 'fas fa-fw fa-fire'],
                                ['text' => 'Consumption Sales Detail', 'url' => 'reports/view/consumption-sales-detail', 'icon' => 'fas fa-fw fa-burn'],
                                ['text' => 'Areawise Sales Summary', 'url' => 'reports/view/areawise-sales-summary', 'icon' => 'fas fa-fw fa-map-marker-alt'],
                                ['text' => 'Monthly Sales Detail', 'url' => 'reports/view/monthly-sales-detail', 'icon' => 'fas fa-fw fa-calendar-day'],
                                ['text' => 'Non Purchase Customer List', 'url' => 'reports/view/non-purchase-customer-list', 'icon' => 'fas fa-fw fa-user-slash'],
                                ['text' => 'Counterwise Datewise Sales Summary', 'url' => 'reports/view/counterwise-datewise-sales', 'icon' => 'fas fa-fw fa-store-alt'],
                                ['text' => 'Itemwise Monthly Sales Qty/Amt Details', 'url' => 'reports/view/itemwise-monthly-sales-details', 'icon' => 'fas fa-fw fa-chart-bar'],
                            ],
                        ],
                        [
                            'text' => 'Hold Transactions',
                            'icon' => 'fas fa-fw fa-pause-circle',
                            'submenu' => [
                                ['text' => 'Hold Bill Details', 'url' => 'reports/view/hold-bill-details', 'icon' => 'fas fa-fw fa-pause'],
                                ['text' => 'ItemWise Discount Approval Details', 'url' => 'reports/view/itemwise-discount-approval', 'icon' => 'fas fa-fw fa-tags'],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'Inventory',
                    'icon' => 'fas fa-fw fa-boxes',
                    'submenu' => [
                        [
                            'text' => 'Stock Ledger',
                            'icon' => 'fas fa-fw fa-book-open',
                            'submenu' => [
                                ['text' => 'Itemwise Stock Statement', 'url' => 'reports/view/itemwise-stock-statement', 'icon' => 'fas fa-fw fa-list'],
                                ['text' => 'Itemwise Stock and Sales Detail', 'url' => 'reports/view/itemwise-stock-sales-detail', 'icon' => 'fas fa-fw fa-clipboard-check'],
                                ['text' => 'Itemwise Stock and Sales Detail Transit with Transit', 'url' => 'reports/view/itemwise-stock-sales-transit', 'icon' => 'fas fa-fw fa-truck-moving'],
                                ['text' => 'Transactionwise Stock Register', 'url' => 'reports/view/transactionwise-stock-register', 'icon' => 'fas fa-fw fa-book'],
                                ['text' => 'Closing Stock', 'url' => 'reports/view/closing-stock', 'icon' => 'fas fa-fw fa-door-closed'],
                                ['text' => 'Current Stock Branchwise', 'url' => 'reports/current-stock', 'icon' => 'fas fa-fw fa-warehouse'],
                                ['text' => 'Categorywise Datewise Stock Report', 'url' => 'reports/view/categorywise-datewise-stock', 'icon' => 'fas fa-fw fa-layer-group'],
                                ['text' => 'Branchwise Stock Age Analysis', 'url' => 'reports/view/branchwise-stock-age-analysis', 'icon' => 'fas fa-fw fa-hourglass-start'],
                                ['text' => 'Categorywise Stock Age Analysis', 'url' => 'reports/view/categorywise-stock-age-analysis', 'icon' => 'fas fa-fw fa-hourglass-end'],
                            ],
                        ],
                        ['text' => 'Price Drop', 'url' => 'reports/view/inventory-price-drop', 'icon' => 'fas fa-fw fa-level-down-alt'],
                        ['text' => 'Price Level Advance', 'url' => 'reports/view/inventory-price-level-advance', 'icon' => 'fas fa-fw fa-layer-group'],
                        [
                            'text' => 'Stock Movement',
                            'icon' => 'fas fa-fw fa-exchange-alt',
                            'submenu' => [
                                ['text' => 'Transfer Out Approval Detail', 'url' => 'reports/view/transfer-out-approval-detail', 'icon' => 'fas fa-fw fa-check-double'],
                                ['text' => 'Stock TransferOut Summary', 'url' => 'reports/stock-transfer-summary', 'icon' => 'fas fa-fw fa-arrow-circle-right'],
                                ['text' => 'Stock TransferOut Detail', 'url' => 'reports/view/stock-transferout-detail', 'icon' => 'fas fa-fw fa-file-export'],
                                ['text' => 'Itemwise Storewise TransferOut Detail', 'url' => 'reports/view/itemwise-storewise-transferout', 'icon' => 'fas fa-fw fa-boxes'],
                                ['text' => 'Stock TransferIn Summary', 'url' => 'reports/view/stock-transferin-summary', 'icon' => 'fas fa-fw fa-arrow-circle-left'],
                                ['text' => 'Stock TransferIn Detail', 'url' => 'reports/view/stock-transferin-detail', 'icon' => 'fas fa-fw fa-file-import'],
                                ['text' => 'Itemwise Storewise Transfer In Detail', 'url' => 'reports/view/itemwise-storewise-transferin', 'icon' => 'fas fa-fw fa-dolly'],
                                ['text' => 'Stock In Transit', 'url' => 'reports/view/stock-in-transit', 'icon' => 'fas fa-fw fa-shipping-fast'],
                                ['text' => 'Stock Transfer Discrepancy', 'url' => 'reports/view/stock-transfer-discrepancy', 'icon' => 'fas fa-fw fa-exclamation-triangle'],
                                ['text' => 'TO Vs TIN', 'url' => 'reports/view/to-vs-tin', 'icon' => 'fas fa-fw fa-random'],
                                ['text' => 'Stock Conversion Report', 'url' => 'reports/view/stock-conversion-report', 'icon' => 'fas fa-fw fa-recycle'],
                            ],
                        ],
                        [
                            'text' => 'Stock Analysis',
                            'icon' => 'fas fa-fw fa-search-plus',
                            'submenu' => [
                                ['text' => 'Stock Update Detail', 'url' => 'reports/view/stock-update-detail', 'icon' => 'fas fa-fw fa-edit'],
                                ['text' => 'Wastage/Damage Stock', 'url' => 'reports/damage-stock-summary', 'icon' => 'fas fa-fw fa-heart-broken'],
                                ['text' => 'Categorywise Storewise Current Stock Summary', 'url' => 'reports/view/categorywise-storewise-stock-summary', 'icon' => 'fas fa-fw fa-layer-group'],
                                ['text' => 'Categorywise Storewise Current Stock Detail', 'url' => 'reports/view/categorywise-storewise-stock-detail', 'icon' => 'fas fa-fw fa-list-alt'],
                                ['text' => 'Stock - Categorwise FastMoving - Item', 'url' => 'reports/view/stock-fastmoving-item', 'icon' => 'fas fa-fw fa-bolt'],
                                ['text' => 'Stock - Categorwise - SlowMoving - Item', 'url' => 'reports/view/stock-slowmoving-item', 'icon' => 'fas fa-fw fa-bed'],
                                ['text' => 'Wastage/Damage Stock Detail', 'url' => 'reports/view/wastage-damage-stock-detail', 'icon' => 'fas fa-fw fa-file-excel'],
                                ['text' => 'Item Age Analysis', 'url' => 'reports/view/item-age-analysis', 'icon' => 'fas fa-fw fa-clock'],
                                ['text' => 'Item Expiry Update Details', 'url' => 'reports/view/item-expiry-update-details', 'icon' => 'fas fa-fw fa-calendar-times'],
                                ['text' => 'Items Details In Cart', 'url' => 'reports/view/items-details-in-cart', 'icon' => 'fas fa-fw fa-shopping-basket'],
                                ['text' => 'Stock Reserve Status', 'url' => 'reports/view/stock-reserve-status', 'icon' => 'fas fa-fw fa-bookmark'],
                            ],
                        ],
                        [
                            'text' => 'Stock Replenishment',
                            'icon' => 'fas fa-fw fa-sync-alt',
                            'submenu' => [
                                ['text' => 'MBQ Detail', 'url' => 'reports/view/mbq-detail', 'icon' => 'fas fa-fw fa-chart-line'],
                                ['text' => 'Kit Preparation', 'url' => 'reports/view/kit-preparation', 'icon' => 'fas fa-fw fa-tools'],
                                ['text' => 'Kit Unpack', 'url' => 'reports/view/kit-unpack', 'icon' => 'fas fa-fw fa-box-open'],
                                ['text' => 'Itemwise Stock Transfer Advice', 'url' => 'reports/view/itemwise-stock-transfer-advice', 'icon' => 'fas fa-fw fa-file-prescription'],
                                ['text' => 'Indent Based On Replenishment', 'url' => 'reports/view/indent-based-replenishment', 'icon' => 'fas fa-fw fa-indent'],
                                ['text' => 'Indent Summary', 'url' => 'reports/view/indent-summary', 'icon' => 'fas fa-fw fa-list'],
                                ['text' => 'PO Replenishment', 'url' => 'reports/view/po-replenishment', 'icon' => 'fas fa-fw fa-truck-loading'],
                                ['text' => 'Picklist Detail', 'url' => 'reports/view/picklist-detail', 'icon' => 'fas fa-fw fa-tasks'],
                                ['text' => 'Picking List Discrepancy', 'url' => 'reports/view/picking-list-discrepancy', 'icon' => 'fas fa-fw fa-exclamation'],
                                ['text' => 'Opening Stock Detail', 'url' => 'reports/view/opening-stock-detail', 'icon' => 'fas fa-fw fa-door-open'],
                                ['text' => 'Repack Summary', 'url' => 'reports/view/repack-summary', 'icon' => 'fas fa-fw fa-box'],
                                ['text' => 'Eancode Detail', 'url' => 'reports/view/eancode-detail', 'icon' => 'fas fa-fw fa-barcode'],
                                ['text' => 'Repack Detail', 'url' => 'reports/view/repack-detail', 'icon' => 'fas fa-fw fa-boxes'],
                                ['text' => 'Re-order Report', 'url' => 'reports/reorder-report', 'icon' => 'fas fa-fw fa-exclamation-triangle'],
                                ['text' => 'Issue Date Expiry Details', 'url' => 'reports/view/issue-date-expiry-details', 'icon' => 'fas fa-fw fa-calendar-day'],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'My Reports',
                    'icon' => 'fas fa-fw fa-folder-open',
                    'submenu' => [
                        ['text' => 'Counterwise Sales Summary', 'url' => 'reports/view/my-counterwise-sales-summary', 'icon' => 'fas fa-fw fa-store'],
                        ['text' => 'Sales MIS report', 'url' => 'reports/view/my-sales-mis-report', 'icon' => 'fas fa-fw fa-file-invoice-dollar'],
                        ['text' => 'Off Take - Dealer Tracker Report', 'url' => 'reports/view/my-offtake-dealer-tracker', 'icon' => 'fas fa-fw fa-chart-line'],
                    ],
                ],
                [
                    'text' => 'Production',
                    'icon' => 'fas fa-fw fa-industry',
                    'submenu' => [
                        ['text' => 'Production Plan', 'url' => 'reports/view/production-plan', 'icon' => 'fas fa-fw fa-tasks'],
                        ['text' => 'BOM Report', 'url' => 'reports/view/bom-report', 'icon' => 'fas fa-fw fa-sitemap'],
                        ['text' => 'Production Costing Summary', 'url' => 'reports/view/production-costing-summary', 'icon' => 'fas fa-fw fa-calculator'],
                        ['text' => 'Production Costing Detail', 'url' => 'reports/view/production-costing-detail', 'icon' => 'fas fa-fw fa-list-ol'],
                        ['text' => 'Sales/Production Variance', 'url' => 'reports/view/sales-production-variance', 'icon' => 'fas fa-fw fa-balance-scale'],
                    ],
                ],
                [
                    'text' => 'More',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'submenu' => [
                        [
                            'text' => 'Serialized Reports',
                            'icon' => 'fas fa-fw fa-barcode',
                            'submenu' => [
                                ['text' => 'Serial Number History', 'url' => 'reports/view/serial-number-history', 'icon' => 'fas fa-fw fa-history'],
                                ['text' => 'Serial No Wise Stock Detail', 'url' => 'reports/view/serial-nowise-stock-detail', 'icon' => 'fas fa-fw fa-cubes'],
                            ],
                        ],
                        ['text' => 'Offline Exported Reports', 'url' => 'reports/view/offline-exported-reports', 'icon' => 'fas fa-fw fa-file-export'],
                        ['text' => 'Dashboard', 'url' => 'reports', 'icon' => 'fas fa-fw fa-tachometer-alt'],
                        ['text' => 'Monthly Transaction Summary', 'url' => 'reports/view/monthly-transaction-summary', 'icon' => 'fas fa-fw fa-calendar-alt'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Tools',
            'icon' => 'fas fa-fw fa-tools',
            'submenu' => [
                [
                    'text' => 'Configuration',
                    'icon' => 'fas fa-fw fa-cogs',
                    'submenu' => [
                        ['text' => 'Role Master', 'url' => 'master/users', 'icon' => 'fas fa-fw fa-user-shield'],
                        ['text' => 'Financial Years', 'url' => 'master/financial-years', 'icon' => 'fas fa-fw fa-calendar-alt'],
                        ['text' => 'Userwise Configuration', 'url' => 'tools/userwise-config', 'icon' => 'fas fa-fw fa-user-cog'],
                        ['text' => 'Business Configuration', 'url' => 'tools/business-config', 'icon' => 'fas fa-fw fa-briefcase'],
                        ['text' => 'Function Key Mapping', 'url' => 'tools/function-keys', 'icon' => 'fas fa-fw fa-keyboard'],
                        ['text' => 'Ledger Map', 'url' => 'tools/ledger-map', 'icon' => 'fas fa-fw fa-map'],
                        ['text' => 'Asset LedgerMap', 'url' => 'tools/asset-ledger', 'icon' => 'fas fa-fw fa-landmark'],
                        ['text' => 'Mail Server Configuration', 'url' => 'tools/mail-server', 'icon' => 'fas fa-fw fa-envelope'],
                        ['text' => 'Category Wise Sequence', 'url' => 'tools/category-sequence', 'icon' => 'fas fa-fw fa-sort-numeric-down'],
                        ['text' => 'Form Field Validations', 'url' => 'tools/form-validations', 'icon' => 'fas fa-fw fa-check-double'],
                    ],
                ],
                [
                    'text' => 'Integrations',
                    'icon' => 'fas fa-fw fa-plug',
                    'submenu' => [
                        ['text' => 'WhatsApp Integration', 'url' => 'tools/whatsapp-settings', 'icon' => 'fab fa-fw fa-whatsapp text-success', 'active' => ['tools/whatsapp-settings*']],
                        ['text' => 'GoFrugal Alert', 'url' => 'tools/integrations-alert', 'icon' => 'fas fa-fw fa-bell'],
                        ['text' => 'GST Efiling', 'url' => 'tools/integrations-gst', 'icon' => 'fas fa-fw fa-cloud-upload-alt'],
                        ['text' => 'GOFRUGAL Gosure', 'url' => 'tools/integrations-gosure', 'icon' => 'fas fa-fw fa-shield-alt'],
                    ],
                ],
                [
                    'text' => 'WhatsApp Settings',
                    'url' => 'tools/whatsapp-settings',
                    'icon' => 'fab fa-fw fa-whatsapp text-success',
                    'active' => ['tools/whatsapp-settings*'],
                ],
                [
                    'text' => 'Receipt Designer',
                    'url' => 'tools/receipt-designer',
                    'icon' => 'fas fa-fw fa-receipt text-warning',
                    'active' => ['tools/receipt-designer*'],
                ],
                [
                    'text' => 'Master Migration',
                    'url' => 'tools/master-migration',
                    'icon' => 'fas fa-fw fa-database',
                ],
                [
                    'text' => 'System Error Logs',
                    'url' => 'tools/system-error-logs',
                    'icon' => 'fas fa-fw fa-bug text-danger',
                    'active' => ['tools/system-error-logs*'],
                ],
                [
                    'text' => 'System Health & Monitor',
                    'url' => 'tools/system-health',
                    'icon' => 'fas fa-fw fa-heartbeat text-success',
                    'active' => ['tools/system-health*'],
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
                        ['text' => 'Year Begin Sequence Change', 'url' => 'tools/year-begin-sequence', 'icon' => 'fas fa-fw fa-calendar-plus'],
                        ['text' => 'Session Management', 'url' => 'tools/session-management', 'icon' => 'fas fa-fw fa-user-clock'],
                        ['text' => 'Reprint', 'url' => 'tools/reprint', 'icon' => 'fas fa-fw fa-print'],
                        ['text' => 'Multiple Dispatch', 'url' => 'tools/multiple-dispatch', 'icon' => 'fas fa-fw fa-paper-plane'],
                        ['text' => 'Report Scheduler', 'url' => 'tools/report-scheduler', 'icon' => 'fas fa-fw fa-clock'],
                        ['text' => 'E-way Update', 'url' => 'tools/eway-update', 'icon' => 'fas fa-fw fa-route'],
                        ['text' => 'Barcode Config', 'url' => 'tools/barcode-config', 'icon' => 'fas fa-fw fa-barcode'],
                        ['text' => 'Service User Consent', 'url' => 'tools/service-consent', 'icon' => 'fas fa-fw fa-file-contract'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Finance & Accounts',
            'icon' => 'fas fa-fw fa-file-invoice-dollar',
            'submenu' => [
                [
                    'text' => 'Ledger Master',
                    'url' => 'finance/ledgers',
                    'icon' => 'fas fa-fw fa-book',
                    'active' => ['finance/ledgers*'],
                ],
                [
                    'text' => 'Voucher Entry',
                    'url' => 'finance/vouchers',
                    'icon' => 'fas fa-fw fa-file-invoice',
                    'active' => ['finance/vouchers*'],
                ],
                [
                    'text' => 'Credit Settlement',
                    'url' => 'finance/settlements',
                    'icon' => 'fas fa-fw fa-hand-holding-usd',
                    'active' => ['finance/settlements*'],
                ],
                [
                    'text' => 'Finance Reports',
                    'url' => 'finance/reports',
                    'icon' => 'fas fa-fw fa-chart-pie',
                    'submenu' => [
                        ['text' => 'General Ledger', 'url' => 'finance/reports/general-ledger', 'icon' => 'fas fa-fw fa-book'],
                        ['text' => 'Day Book', 'url' => 'finance/reports/day-book', 'icon' => 'fas fa-fw fa-calendar-day'],
                        ['text' => 'Cash & Bank Book', 'url' => 'finance/reports/cash-bank-book', 'icon' => 'fas fa-fw fa-money-check-alt'],
                        ['text' => 'Billwise Outstanding Aging', 'url' => 'finance/reports/outstanding-aging', 'icon' => 'fas fa-fw fa-hourglass-half'],
                        ['text' => 'Trial Balance', 'url' => 'finance/reports/trial-balance', 'icon' => 'fas fa-fw fa-balance-scale'],
                        ['text' => 'Profit & Loss', 'url' => 'finance/reports/profit-loss', 'icon' => 'fas fa-fw fa-chart-pie'],
                    ],
                ],
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
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/select2/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/select2/css/select2.min.css',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/select2-init.js?v=20260917_branch2',
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
