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

    'logo' => '<b>URBAN</b> PETS',
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
    'classes_sidebar' => 'sidebar-light-primary',
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
    'sidebar_collapse' => true,
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
            'text' => 'Dashboard',
            'url' => 'home',
            'icon' => 'fas fa-fw fa-tachometer-alt',
        ],
        [
            'text' => 'Master',
            'icon' => 'fas fa-fw fa-th-large',
            'can' => ['items.create', 'item-categories.create', 'brands.create', 'customers.view', 'suppliers.create', 'gst-taxes.create', 'branches.create', 'registers.create'],
            'submenu' => [
                [
                    'text' => 'Item',
                    'icon' => 'fas fa-fw fa-boxes',
                    'can' => ['items.create', 'item-categories.create', 'brands.create', 'uoms.create', 'item-price-change.edit'],
                    'submenu' => [
                        ['text' => 'Item Category', 'url' => 'master/item-categories', 'icon' => 'fas fa-fw fa-folder', 'can' => 'item-categories.create'],
                        ['text' => 'Item Category Values', 'url' => 'master/item-category-values', 'icon' => 'fas fa-fw fa-folder-open', 'can' => 'item-category-values.create'],
                        ['text' => 'Product Types', 'url' => 'master/product-types', 'icon' => 'fas fa-fw fa-cubes', 'can' => 'product-types.create'],
                        ['text' => 'Brand', 'url' => 'master/brands', 'icon' => 'fas fa-fw fa-tag', 'can' => 'brands.create'],
                        ['text' => 'Item', 'url' => 'master/items', 'icon' => 'fas fa-fw fa-box', 'can' => 'items.create'],
                        ['text' => 'Item Property Setting', 'url' => 'master/aux/item-property-setting', 'icon' => 'fas fa-fw fa-sliders-h', 'can' => 'items.create'],
                        ['text' => 'Item EAN/UPC Entry', 'url' => 'master/aux/item-ean-upc-entry', 'icon' => 'fas fa-fw fa-barcode', 'can' => 'items.create'],
                        ['text' => 'Assembly', 'url' => 'master/aux/assembly', 'icon' => 'fas fa-fw fa-puzzle-piece', 'can' => 'items.create'],
                        ['text' => 'Kit Mapping', 'url' => 'master/aux/kit-mapping', 'icon' => 'fas fa-fw fa-sitemap', 'can' => 'items.create'],
                        ['text' => 'Item Price Change', 'url' => 'master/item-price-change', 'icon' => 'fas fa-fw fa-tags', 'can' => 'item-price-change.edit'],
                        ['text' => 'UOM', 'url' => 'master/uoms', 'icon' => 'fas fa-fw fa-balance-scale', 'can' => 'uoms.create'],
                        ['text' => 'UOM Vs Item Mapping', 'url' => 'master/aux/uom-vs-item-mapping', 'icon' => 'fas fa-fw fa-link', 'can' => 'items.create'],
                        ['text' => 'Tax Slab', 'url' => 'master/aux/tax-slab', 'icon' => 'fas fa-fw fa-percentage', 'can' => 'items.create'],
                    ],
                ],
                [
                    'text' => 'Customer',
                    'icon' => 'fas fa-fw fa-users',
                    'can' => ['customers.view', 'customer-categories.create', 'customer-types.create'],
                    'submenu' => [
                        ['text' => 'Customer Category', 'url' => 'master/customer-categories', 'icon' => 'fas fa-fw fa-user-tag', 'can' => 'customer-categories.create'],
                        ['text' => 'Customer Types', 'url' => 'master/customer-types', 'icon' => 'fas fa-fw fa-id-card', 'can' => 'customer-types.create'],
                        ['text' => 'Sales Types', 'url' => 'master/sales-types', 'icon' => 'fas fa-fw fa-file-contract', 'can' => 'sales-types.create'],
                        ['text' => 'Customer', 'url' => 'master/customers', 'icon' => 'fas fa-fw fa-user', 'can' => 'customers.view'],
                        ['text' => 'Area', 'url' => 'master/areas', 'icon' => 'fas fa-fw fa-map-marker-alt', 'can' => 'areas.create'],
                        ['text' => 'Loyalty Program Info', 'url' => 'master/loyalty-programs', 'icon' => 'fas fa-fw fa-award', 'can' => 'loyalty-programs.create'],
                        ['text' => 'Loyalty Points Update', 'url' => 'master/loyalty-points', 'icon' => 'fas fa-fw fa-star', 'can' => 'loyalty-programs.create'],
                        [
                            'text' => 'Pet Masters',
                            'icon' => 'fas fa-fw fa-paw',
                            'can' => ['pet-types.create', 'breeds.create', 'colors.create'],
                            'submenu' => [
                                ['text' => 'Pet Types', 'url' => 'master/pet-types', 'icon' => 'fas fa-fw fa-dog', 'can' => 'pet-types.create'],
                                ['text' => 'Breed Master', 'url' => 'master/breeds', 'icon' => 'fas fa-fw fa-bone', 'can' => 'breeds.create'],
                                ['text' => 'Color Master', 'url' => 'master/colors', 'icon' => 'fas fa-fw fa-palette', 'can' => 'colors.create'],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'Supplier',
                    'url' => 'master/suppliers',
                    'icon' => 'fas fa-fw fa-truck',
                    'active' => ['master/suppliers*'],
                    'can' => 'suppliers.create',
                ],
                [
                    'text' => 'Tax',
                    'icon' => 'fas fa-fw fa-percent',
                    'can' => ['gst-taxes.create', 'gst-types.create'],
                    'submenu' => [
                        ['text' => 'GST Tax', 'url' => 'master/gst-taxes', 'icon' => 'fas fa-fw fa-receipt', 'can' => 'gst-taxes.create'],
                        ['text' => 'GST Types', 'url' => 'master/gst-types', 'icon' => 'fas fa-fw fa-file-invoice', 'can' => 'gst-types.create'],
                        ['text' => 'GSTNo Restriction Master', 'url' => 'master/aux/gstno-restriction', 'icon' => 'fas fa-fw fa-shield-alt', 'can' => 'gst-taxes.create'],
                    ],
                ],
                [
                    'text' => 'Branch',
                    'icon' => 'fas fa-fw fa-store',
                    'can' => 'branches.create',
                    'submenu' => [
                        ['text' => 'Branch', 'url' => 'master/branches', 'icon' => 'fas fa-fw fa-store-alt', 'can' => 'branches.create'],
                        ['text' => 'Distribution Centre Mapping', 'url' => 'master/aux/distribution-centre-mapping', 'icon' => 'fas fa-fw fa-network-wired', 'can' => 'branches.create'],
                    ],
                ],
                [
                    'text' => 'Register',
                    'url' => 'master/registers',
                    'icon' => 'fas fa-fw fa-cash-register',
                    'active' => ['master/registers*'],
                    'can' => 'registers.create',
                ],
                [
                    'text' => 'Promotion',
                    'icon' => 'fas fa-fw fa-bullhorn',
                    'can' => 'loyalty-programs.create',
                    'submenu' => [
                        ['text' => 'Promotion Management', 'url' => 'master/aux/promotion-management', 'icon' => 'fas fa-fw fa-gift', 'can' => 'loyalty-programs.create'],
                    ],
                ],
                [
                    'text' => 'More Master',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'can' => ['tender-types.create', 'tender-type-values.create', 'users.create', 'branches.create'],
                    'submenu' => [
                        [
                            'text' => 'Tools',
                            'icon' => 'fas fa-fw fa-tools',
                            'can' => ['tender-types.create', 'tender-type-values.create', 'users.create'],
                            'submenu' => [
                                ['text' => 'Master Configuration', 'url' => 'master/aux/master-configuration', 'icon' => 'fas fa-fw fa-cogs', 'can' => 'users.create'],
                                ['text' => 'Tender Type Values', 'url' => 'master/tender-type-values', 'icon' => 'fas fa-fw fa-coins', 'can' => 'tender-type-values.create'],
                                ['text' => 'Unicode Master', 'url' => 'master/aux/unicode-master', 'icon' => 'fas fa-fw fa-font', 'can' => 'users.create'],
                                ['text' => 'Tender Type', 'url' => 'master/tender-types', 'icon' => 'fas fa-fw fa-credit-card', 'can' => 'tender-types.create'],
                                ['text' => 'Master Attributes', 'url' => 'master/aux/master-attributes', 'icon' => 'fas fa-fw fa-list-ul', 'can' => 'users.create'],
                                ['text' => 'Addon Devices Inactivation', 'url' => 'master/aux/addon-devices', 'icon' => 'fas fa-fw fa-power-off', 'can' => 'users.create'],
                            ],
                        ],
                        [
                            'text' => 'Utility',
                            'icon' => 'fas fa-fw fa-wrench',
                            'can' => 'branches.create',
                            'submenu' => [
                                ['text' => 'Transporter', 'url' => 'master/aux/transporters', 'icon' => 'fas fa-fw fa-shipping-fast', 'can' => 'branches.create'],
                                ['text' => 'Freight Settings', 'url' => 'master/aux/freight-settings', 'icon' => 'fas fa-fw fa-calculator', 'can' => 'branches.create'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Sales',
            'icon' => 'fas fa-fw fa-shopping-cart',
            'can' => ['sales-bills.create', 'sales-returns.create', 'sales-quotations.create', 'sales-orders.create', 'sales-delivery-notes.create', 'stock-transfers.create'],
            'submenu' => [
                [
                    'text' => 'Sales Bill',
                    'url' => 'sales/sales-bills/create',
                    'icon' => 'fas fa-fw fa-cash-register',
                    'active' => ['sales/sales-bills*'],
                    'can' => 'sales-bills.create',
                ],
                [
                    'text' => 'Sales Return',
                    'url' => 'sales/sales-returns/create',
                    'icon' => 'fas fa-fw fa-undo',
                    'active' => ['sales/sales-returns*'],
                    'can' => 'sales-returns.create',
                ],
                [
                    'text' => 'Sales Quotation',
                    'url' => 'sales/sales-quotations/create',
                    'icon' => 'fas fa-fw fa-file-signature',
                    'active' => ['sales/sales-quotations*'],
                    'can' => 'sales-quotations.create',
                ],
                [
                    'text' => 'Sales Order',
                    'url' => 'sales/sales-orders/create',
                    'icon' => 'fas fa-fw fa-shopping-basket',
                    'active' => ['sales/sales-orders*'],
                    'can' => 'sales-orders.create',
                ],
                [
                    'text' => 'Sales Order Approval',
                    'url' => 'sales/aux/order-approval',
                    'icon' => 'fas fa-fw fa-user-check',
                    'active' => ['sales/aux/order-approval*'],
                    'can' => 'sales-orders.create',
                ],
                [
                    'text' => 'Delivery Note',
                    'url' => 'sales/delivery-notes/create',
                    'icon' => 'fas fa-fw fa-truck',
                    'active' => ['sales/delivery-notes*'],
                    'can' => 'sales-delivery-notes.create',
                ],
                [
                    'text' => 'Delivery Note Return',
                    'url' => 'sales/aux/delivery-note-returns',
                    'icon' => 'fas fa-fw fa-truck-loading',
                    'active' => ['sales/aux/delivery-note-returns*'],
                    'can' => 'sales-delivery-notes.create',
                ],
                [
                    'text' => 'More Sales',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'can' => 'stock-transfers.create',
                    'submenu' => [
                        ['text' => 'Transfer Out', 'url' => 'inventory/stock-transfers', 'icon' => 'fas fa-fw fa-dolly', 'can' => 'stock-transfers.create'],
                        ['text' => 'Transfer Out Approval & Auto TI', 'url' => 'sales/aux/transfer-out-approval', 'icon' => 'fas fa-fw fa-check-double', 'can' => 'stock-transfers.create'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Purchase',
            'icon' => 'fas fa-fw fa-shopping-bag',
            'can' => ['purchase-orders.create', 'purchase-receipt-notes.create', 'purchase-invoices.create', 'purchase-returns.create', 'purchase-indents.create', 'stock-transfers.receive'],
            'submenu' => [
                [
                    'text' => 'Purchase Order',
                    'url' => 'purchase/purchase-orders/create',
                    'icon' => 'fas fa-fw fa-file-invoice',
                    'active' => ['purchase/purchase-orders/create*'],
                    'can' => 'purchase-orders.create',
                ],
                [
                    'text' => 'Receipt Note',
                    'url' => 'purchase/purchase-receipt-notes/create',
                    'icon' => 'fas fa-fw fa-receipt',
                    'active' => ['purchase/purchase-receipt-notes*'],
                    'can' => 'purchase-receipt-notes.create',
                ],
                [
                    'text' => 'Purchase Invoice',
                    'url' => 'purchase/purchase-invoices/create',
                    'icon' => 'fas fa-fw fa-file-invoice-dollar',
                    'active' => ['purchase/purchase-invoices*'],
                    'can' => 'purchase-invoices.create',
                ],
                [
                    'text' => 'Purchase Returns',
                    'url' => 'purchase/purchase-returns/create',
                    'icon' => 'fas fa-fw fa-undo-alt',
                    'active' => ['purchase/purchase-returns*'],
                    'can' => 'purchase-returns.create',
                ],
                [
                    'text' => 'Purchase Orders',
                    'url' => 'purchase/purchase-orders',
                    'icon' => 'fas fa-fw fa-list-alt',
                    'active' => ['purchase/purchase-orders'],
                    'can' => 'purchase-orders.create',
                ],
                [
                    'text' => 'Transfer In',
                    'url' => 'inventory/stock-transfers/pending-receipt',
                    'icon' => 'fas fa-fw fa-dolly-flatbed',
                    'active' => ['inventory/stock-transfers/pending-receipt*'],
                    'can' => 'stock-transfers.receive',
                ],
                [
                    'text' => 'Purchase Indent',
                    'url' => 'purchase/purchase-indents/create',
                    'icon' => 'fas fa-fw fa-clipboard-list',
                    'active' => ['purchase/purchase-indents*'],
                    'can' => 'purchase-indents.create',
                ],
                [
                    'text' => 'More Purchase',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'can' => ['purchase-indents.create', 'purchase-indents.cancel', 'stock-transfers.receive'],
                    'submenu' => [
                        ['text' => 'Auto Indent', 'url' => 'purchase/aux/auto-indent', 'icon' => 'fas fa-fw fa-magic', 'can' => 'purchase-indents.create'],
                        ['text' => 'Indent Cancellation', 'url' => 'purchase/aux/indent-cancellation', 'icon' => 'fas fa-fw fa-times-circle', 'can' => 'purchase-indents.cancel'],
                        ['text' => 'Transfer In Touch', 'url' => 'purchase/aux/transfer-in-touch', 'icon' => 'fas fa-fw fa-hand-pointer', 'can' => 'stock-transfers.receive'],
                        ['text' => 'Indent CutOff Time Configuration', 'url' => 'purchase/aux/indent-cutoff', 'icon' => 'fas fa-fw fa-clock', 'can' => 'purchase-indents.create'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Till',
            'icon' => 'fas fa-fw fa-cash-register',
            'can' => ['till.open', 'till.close'],
            'submenu' => [
                [
                    'text' => 'Open Till',
                    'url' => 'till/sessions/open',
                    'icon' => 'fas fa-fw fa-door-open',
                    'active' => ['till/sessions/open*'],
                    'can' => 'till.open',
                ],
                [
                    'text' => 'Till Sessions',
                    'url' => 'till/sessions',
                    'icon' => 'fas fa-fw fa-history',
                    'active' => ['till/sessions*'],
                    'can' => ['till.open', 'till.close'],
                ],
            ],
        ],
        [
            'text' => 'Inventory',
            'icon' => 'fas fa-fw fa-warehouse',
            'can' => ['opening-stocks.create', 'damage-stocks.create', 'stock-updates.create', 'stock-update-approval.approve', 'price-fixing.apply', 'change-selling.edit', 'repack.create', 'kit-preparation.create', 'kit-unpack.create', 'items.create', 'barcode-printing.print', 'kit-recipes.create', 'stock-verification.review'],
            'submenu' => [
                [
                    'text' => 'Opening Stock Entry',
                    'url' => 'inventory/opening-stocks/create',
                    'icon' => 'fas fa-fw fa-dolly',
                    'active' => ['inventory/opening-stocks*'],
                    'can' => 'opening-stocks.create',
                ],
                [
                    'text' => 'Damage Stock Entry',
                    'url' => 'inventory/damage-stocks/create',
                    'icon' => 'fas fa-fw fa-dumpster-fire',
                    'active' => ['inventory/damage-stocks*'],
                    'can' => 'damage-stocks.create',
                ],
                [
                    'text' => 'Stock Update Entry',
                    'url' => 'inventory/stock-updates/create',
                    'icon' => 'fas fa-fw fa-sync-alt',
                    'active' => ['inventory/stock-updates*'],
                    'can' => 'stock-updates.create',
                ],
                [
                    'text' => 'Stock Update Approval',
                    'url' => 'inventory/stock-update-approval',
                    'icon' => 'fas fa-fw fa-clipboard-check',
                    'active' => ['inventory/stock-update-approval*'],
                    'can' => 'stock-update-approval.approve',
                ],
                [
                    'text' => 'Barcode Printing',
                    'url' => 'inventory/barcode',
                    'icon' => 'fas fa-fw fa-barcode',
                    'active' => ['inventory/barcode*'],
                    'can' => ['barcode-printing.print', 'items.create'],
                ],
                [
                    'text' => 'Stock & Expiry Verification',
                    'icon' => 'fas fa-fw fa-mobile-alt',
                    'can' => 'stock-verification.review',
                    'submenu' => [
                        [
                            'text' => 'Manager Review Queue',
                            'url' => 'inventory/stock-verification/review-queue',
                            'icon' => 'fas fa-fw fa-clipboard-check',
                            'active' => ['inventory/stock-verification/review-queue*'],
                            'can' => 'stock-verification.review',
                        ],
                        [
                            'text' => 'History & Audit',
                            'url' => 'inventory/stock-verification/history',
                            'icon' => 'fas fa-fw fa-history',
                            'active' => ['inventory/stock-verification/history*'],
                            'can' => 'stock-verification.review',
                        ],
                    ],
                ],
                [
                    'text' => 'Price Fixing',
                    'icon' => 'fas fa-fw fa-tags',
                    'can' => 'price-fixing.apply',
                    'submenu' => [
                        [
                            'text' => 'Price Fixing(markup/markdown)',
                            'url' => 'inventory/price-fixing/markup-markdown',
                            'icon' => 'fas fa-fw fa-sort-amount-up-alt',
                            'can' => 'price-fixing.apply',
                        ],
                        [
                            'text' => 'Price Level',
                            'url' => 'inventory/price-fixing/price-level',
                            'icon' => 'fas fa-fw fa-layer-group',
                            'can' => 'price-fixing.apply',
                        ],
                        [
                            'text' => 'Price Level Vs Items',
                            'url' => 'inventory/price-fixing/price-level-items',
                            'icon' => 'fas fa-fw fa-tasks',
                            'can' => 'price-fixing.apply',
                        ],
                    ],
                ],
                [
                    'text' => 'Change Selling',
                    'url' => 'inventory/change-selling',
                    'icon' => 'fas fa-fw fa-dollar-sign',
                    'active' => ['inventory/change-selling*'],
                    'can' => 'change-selling.edit',
                ],
                [
                    'text' => 'More Operations',
                    'icon' => 'fas fa-fw fa-cubes',
                    'can' => ['repack.create', 'kit-preparation.create', 'kit-unpack.create', 'items.create', 'price-fixing.apply', 'kit-recipes.create'],
                    'submenu' => [
                        ['text' => 'Repack', 'url' => 'inventory/repack', 'icon' => 'fas fa-fw fa-boxes', 'can' => 'repack.create'],
                        ['text' => 'Change Serial No', 'url' => 'inventory/change-serial-no', 'icon' => 'fas fa-fw fa-barcode', 'can' => 'items.create'],
                        ['text' => 'Price Drop', 'url' => 'inventory/price-drop', 'icon' => 'fas fa-fw fa-level-down-alt', 'can' => 'price-fixing.apply'],
                        ['text' => 'Kit Preparation', 'url' => 'inventory/kit-preparation', 'icon' => 'fas fa-fw fa-tools', 'can' => 'kit-preparation.create'],
                        ['text' => 'Kit Unpack', 'url' => 'inventory/kit-unpack', 'icon' => 'fas fa-fw fa-box-open', 'can' => 'kit-unpack.create'],
                        ['text' => 'Kit Recipes (BOM)', 'url' => 'inventory/kit-recipes', 'icon' => 'fas fa-fw fa-layer-group', 'can' => 'kit-recipes.create'],
                        ['text' => 'Shelf Talker', 'url' => 'inventory/shelf-talker', 'icon' => 'fas fa-fw fa-sticky-note', 'can' => 'items.create'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Reports',
            'icon' => 'fas fa-fw fa-chart-bar',
            'can' => 'access-reports-menu',
            'submenu' => [
                [
                    'text' => 'Reports Dashboard',
                    'url' => 'reports',
                    'icon' => 'fas fa-fw fa-th-large',
                    'active' => ['reports'],
                    'can' => 'reports-dashboard.view',
                ],
                [
                    'text' => 'Custom Report Studio (Builder)',
                    'url' => 'reports/analytics-builder',
                    'icon' => 'fas fa-fw fa-magic text-info',
                    'active' => ['reports/analytics-builder*'],
                    'can' => 'reports-analytics-builder.view',
                ],
                [
                    'text' => 'Smart Item & Customer 360°',
                    'url' => 'reports/smart-analytics',
                    'icon' => 'fas fa-fw fa-chart-line text-warning',
                    'active' => ['reports/smart-analytics*'],
                    'can' => 'reports-smart-analytics.view',
                ],
                [
                    'text' => 'Masters',
                    'can' => 'access-masters-reports',
                    'submenu' => [
                        ['text' => 'Customer Master', 'url' => 'reports/customer-master', 'icon' => 'fas fa-fw fa-users', 'can' => ['reports-sales.view', 'reports-customer-master.view']],
                        ['text' => 'UOM Vs Item Mapping', 'url' => 'reports/view/uom-vs-item-mapping', 'icon' => 'fas fa-fw fa-balance-scale', 'can' => ['reports-sales.view', 'reports-uom-vs-item-mapping.view']],
                        ['text' => 'Kit Mapping', 'url' => 'reports/view/kit-mapping', 'icon' => 'fas fa-fw fa-box-open', 'can' => ['reports-sales.view', 'reports-kit-mapping.view']],
                        ['text' => 'Customer Parent List', 'url' => 'reports/view/customer-parent-list', 'icon' => 'fas fa-fw fa-user-friends', 'can' => ['reports-sales.view', 'reports-customer-parent-list.view']],
                        ['text' => 'Customer Loyalty Details', 'url' => 'reports/customer-loyalty', 'icon' => 'fas fa-fw fa-star', 'can' => ['reports-finance.view', 'reports-customer-loyalty.view']],
                        ['text' => 'Customer Pet Details', 'url' => 'reports/customer-pet-details', 'icon' => 'fas fa-fw fa-paw', 'can' => ['reports-sales.view', 'reports-customer-pet-details.view']],
                        ['text' => 'Supplier Master', 'url' => 'reports/supplier-master', 'icon' => 'fas fa-fw fa-truck-loading', 'can' => ['reports-sales.view', 'reports-supplier-master.view']],
                        ['text' => 'Brand Master', 'url' => 'reports/view/brand-master', 'icon' => 'fas fa-fw fa-tag', 'can' => ['reports-sales.view', 'reports-brand-master.view']],
                        ['text' => 'Tax Master', 'url' => 'reports/view/tax-master', 'icon' => 'fas fa-fw fa-percentage', 'can' => ['reports-sales.view', 'reports-tax-master.view']],
                        ['text' => 'Area', 'url' => 'reports/view/area', 'icon' => 'fas fa-fw fa-map-marked-alt', 'can' => ['reports-sales.view', 'reports-area.view']],
                        ['text' => 'Branch Master', 'url' => 'reports/view/branch-master', 'icon' => 'fas fa-fw fa-code-branch', 'can' => ['reports-sales.view', 'reports-branch-master.view']],
                        ['text' => 'Item Master', 'url' => 'reports/item-master', 'icon' => 'fas fa-fw fa-box', 'can' => ['reports-sales.view', 'reports-item-master.view']],
                        ['text' => 'Supplier Vs Items', 'url' => 'reports/view/supplier-vs-items', 'icon' => 'fas fa-fw fa-handshake', 'can' => ['reports-sales.view', 'reports-supplier-vs-items.view']],
                        ['text' => 'Price List', 'url' => 'reports/view/price-list', 'icon' => 'fas fa-fw fa-dollar-sign', 'can' => ['reports-sales.view', 'reports-price-list.view']],
                        ['text' => 'Employee Master', 'url' => 'reports/view/employee-master', 'icon' => 'fas fa-fw fa-user-tie', 'can' => ['reports-sales.view', 'reports-employee-master.view']],
                        ['text' => 'RO Master', 'url' => 'reports/view/ro-master', 'icon' => 'fas fa-fw fa-clipboard-check', 'can' => ['reports-sales.view', 'reports-ro-master.view']],
                    ],
                ],
                [
                    'text' => 'Purchase',
                    'icon' => 'fas fa-fw fa-truck',
                    'can' => 'access-purchase-reports',
                    'submenu' => [
                        [
                            'text' => 'Transactions',
                            'icon' => 'fas fa-fw fa-exchange-alt',
                            'submenu' => [
                                ['text' => 'Purchase Order Summary', 'url' => 'reports/purchase-order-summary', 'icon' => 'fas fa-fw fa-clipboard-list', 'can' => ['reports-purchase.view', 'reports-purchase-order-summary.view']],
                                ['text' => 'Purchase Transit', 'url' => 'reports/view/purchase-transit', 'icon' => 'fas fa-fw fa-shipping-fast', 'can' => ['reports-purchase.view', 'reports-purchase-transit.view']],
                                ['text' => 'Purchase Order Details', 'url' => 'reports/view/purchase-order-details', 'icon' => 'fas fa-fw fa-file-invoice', 'can' => ['reports-purchase.view', 'reports-purchase-order-details.view']],
                                ['text' => 'ReceiptNote Nowise Summary', 'url' => 'reports/view/receiptnote-nowise-summary', 'icon' => 'fas fa-fw fa-receipt', 'can' => ['reports-purchase.view', 'reports-receiptnote-nowise-summary.view']],
                                ['text' => 'ReceiptNote Nowise Detail', 'url' => 'reports/view/receiptnote-nowise-detail', 'icon' => 'fas fa-fw fa-list-alt', 'can' => ['reports-purchase.view', 'reports-receiptnote-nowise-detail.view']],
                                ['text' => 'Purchase Summary', 'url' => 'reports/view/purchase-summary', 'icon' => 'fas fa-fw fa-file-invoice-dollar', 'can' => ['reports-purchase.view', 'reports-purchase-summary.view']],
                                ['text' => 'GST Purchase Detail', 'url' => 'reports/purchase-detail', 'icon' => 'fas fa-fw fa-cart-arrow-down', 'can' => ['reports-purchase.view', 'reports-purchase-detail.view']],
                                ['text' => 'GIN Summary', 'url' => 'reports/view/gin-summary', 'icon' => 'fas fa-fw fa-warehouse', 'can' => ['reports-purchase.view', 'reports-gin-summary.view']],
                                ['text' => 'Purchase Detail Serial', 'url' => 'reports/view/purchase-detail-serial', 'icon' => 'fas fa-fw fa-barcode', 'can' => ['reports-purchase.view', 'reports-purchase-detail-serial.view']],
                                ['text' => 'GIN Detail', 'url' => 'reports/view/gin-detail', 'icon' => 'fas fa-fw fa-dolly', 'can' => ['reports-purchase.view', 'reports-gin-detail.view']],
                                ['text' => 'GST Purchase Summary', 'url' => 'reports/gst-purchase-summary', 'icon' => 'fas fa-fw fa-percentage', 'can' => ['reports-purchase.view', 'reports-gst-purchase-summary.view']],
                                ['text' => 'Purchase Order vs Invoice Report', 'url' => 'reports/view/po-vs-invoice', 'icon' => 'fas fa-fw fa-balance-scale', 'can' => ['reports-purchase.view', 'reports-po-vs-invoice.view']],
                                ['text' => 'ReceiptNote Itemwise', 'url' => 'reports/view/receiptnote-itemwise', 'icon' => 'fas fa-fw fa-boxes', 'can' => ['reports-purchase.view', 'reports-receiptnote-itemwise.view']],
                            ],
                        ],
                        ['text' => 'Pending/Cancelled Transactions', 'url' => 'reports/view/purchase-pending-cancelled', 'icon' => 'fas fa-fw fa-ban', 'can' => ['reports-purchase.view', 'reports-purchase-pending-cancelled.view']],
                        ['text' => 'Returned Transactions', 'url' => 'reports/view/purchase-returned-transactions', 'icon' => 'fas fa-fw fa-undo', 'can' => ['reports-purchase.view', 'reports-purchase-returned-transactions.view']],
                        [
                            'text' => 'Purchase Analysis',
                            'icon' => 'fas fa-fw fa-chart-line',
                            'submenu' => [
                                ['text' => 'Supplierwise Purchase Summary', 'url' => 'reports/view/supplierwise-purchase-summary', 'icon' => 'fas fa-fw fa-truck', 'can' => ['reports-purchase.view', 'reports-supplierwise-purchase-summary.view']],
                                ['text' => 'PO/Purchase Discrepancy', 'url' => 'reports/view/po-purchase-discrepancy', 'icon' => 'fas fa-fw fa-exclamation-circle', 'can' => ['reports-purchase.view', 'reports-po-purchase-discrepancy.view']],
                                ['text' => 'Supplierwise Purchase Details', 'url' => 'reports/view/supplierwise-purchase-details', 'icon' => 'fas fa-fw fa-list-ul', 'can' => ['reports-purchase.view', 'reports-supplierwise-purchase-details.view']],
                                ['text' => 'Datewise Itemwise Consolidated Purchase Report', 'url' => 'reports/view/datewise-itemwise-purchase', 'icon' => 'fas fa-fw fa-calendar-alt', 'can' => ['reports-purchase.view', 'reports-datewise-itemwise-purchase.view']],
                                ['text' => 'Purchase Register Summary', 'url' => 'reports/view/purchase-register-summary', 'icon' => 'fas fa-fw fa-book', 'can' => ['reports-purchase.view', 'reports-purchase-register-summary.view']],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'Audit',
                    'icon' => 'fas fa-fw fa-shield-alt',
                    'can' => 'access-audit-reports',
                    'submenu' => [
                        ['text' => 'Foot Fall Details', 'url' => 'reports/view/foot-fall-details', 'icon' => 'fas fa-fw fa-shoe-prints', 'can' => ['reports-audit.view', 'reports-foot-fall-details.view']],
                        ['text' => 'Reprint Count Details', 'url' => 'reports/view/reprint-count-details', 'icon' => 'fas fa-fw fa-print', 'can' => ['reports-audit.view', 'reports-reprint-count-details.view']],
                        ['text' => 'User Login Summary', 'url' => 'reports/view/user-login-summary', 'icon' => 'fas fa-fw fa-user-clock', 'can' => ['reports-audit.view', 'reports-user-login-summary.view']],
                        ['text' => 'Audit Viewer Report', 'url' => 'reports/audit-logs', 'icon' => 'fas fa-fw fa-history', 'can' => ['reports-audit.view', 'reports-audit-logs.view']],
                        ['text' => 'Audit Detail Report', 'url' => 'reports/view/audit-detail-report', 'icon' => 'fas fa-fw fa-search', 'can' => ['reports-audit.view', 'reports-audit-detail-report.view']],
                        ['text' => 'GST Tax Change Audit Report', 'url' => 'reports/view/gst-tax-change-audit', 'icon' => 'fas fa-fw fa-percentage', 'can' => ['reports-audit.view', 'reports-gst-tax-change-audit.view']],
                        ['text' => 'Service User Consent Summary', 'url' => 'reports/view/service-user-consent', 'icon' => 'fas fa-fw fa-user-check', 'can' => ['reports-audit.view', 'reports-service-user-consent.view']],
                        ['text' => 'Email Audit Viewer Report', 'url' => 'reports/view/email-audit-viewer', 'icon' => 'fas fa-fw fa-envelope', 'can' => ['reports-audit.view', 'reports-email-audit-viewer.view']],
                        ['text' => 'Audit Report (Cart entry Clear)', 'url' => 'reports/view/audit-cart-clear', 'icon' => 'fas fa-fw fa-shopping-cart', 'can' => ['reports-audit.view', 'reports-audit-cart-clear.view']],
                        ['text' => 'Audit Detail (Cart entry Clear)', 'url' => 'reports/view/audit-detail-cart-clear', 'icon' => 'fas fa-fw fa-trash-alt', 'can' => ['reports-audit.view', 'reports-audit-detail-cart-clear.view']],
                    ],
                ],
                [
                    'text' => 'Sales',
                    'icon' => 'fas fa-fw fa-shopping-cart',
                    'can' => 'access-sales-reports',
                    'submenu' => [
                        [
                            'text' => 'Sales',
                            'icon' => 'fas fa-fw fa-cash-register',
                            'submenu' => [
                                ['text' => 'Monthly Sales Summary [Storewise]', 'url' => 'reports/view/monthly-sales-summary-storewise', 'icon' => 'fas fa-fw fa-calendar-alt', 'can' => ['reports-sales.view', 'reports-monthly-sales-summary-storewise.view']],
                                ['text' => 'Monthly Sales Summary [Till Wise]', 'url' => 'reports/view/monthly-sales-summary-tillwise', 'icon' => 'fas fa-fw fa-calculator', 'can' => ['reports-sales.view', 'reports-monthly-sales-summary-tillwise.view']],
                                ['text' => 'Serial Number Wise Price Details', 'url' => 'reports/view/serial-wise-price-details', 'icon' => 'fas fa-fw fa-barcode', 'can' => ['reports-sales.view', 'reports-serial-wise-price-details.view']],
                                ['text' => 'Daily Sales Summary [Store Wise]', 'url' => 'reports/sales-summary', 'icon' => 'fas fa-fw fa-chart-line', 'can' => ['reports-sales.view', 'reports-sales-summary.view']],
                                ['text' => 'Daily Sales Summary [Till Wise]', 'url' => 'reports/view/daily-sales-summary-tillwise', 'icon' => 'fas fa-fw fa-cash-register', 'can' => ['reports-sales.view', 'reports-daily-sales-summary-tillwise.view']],
                                ['text' => 'Daily Sales [Bill No Wise]', 'url' => 'reports/view/daily-sales-billwise', 'icon' => 'fas fa-fw fa-file-invoice', 'can' => ['reports-sales.view', 'reports-daily-sales-billwise.view']],
                                ['text' => 'Billwise Itemwise Sales Detail', 'url' => 'reports/billwise-sales', 'icon' => 'fas fa-fw fa-receipt', 'can' => ['reports-sales.view', 'reports-billwise-sales.view']],
                                ['text' => 'Daily Sales [Bill No Wise With Timefilter]', 'url' => 'reports/view/daily-sales-timefilter', 'icon' => 'fas fa-fw fa-clock', 'can' => ['reports-sales.view', 'reports-daily-sales-timefilter.view']],
                                ['text' => 'Offline Sales Bill Details', 'url' => 'reports/view/offline-sales-bill-details', 'icon' => 'fas fa-fw fa-wifi-slash', 'can' => ['reports-sales.view', 'reports-offline-sales-bill-details.view']],
                                ['text' => 'Billwise Itemwise Sales Detail Serialwise', 'url' => 'reports/view/billwise-sales-serialwise', 'icon' => 'fas fa-fw fa-list-ol', 'can' => ['reports-sales.view', 'reports-billwise-sales-serialwise.view']],
                                ['text' => 'GST Sales Summary', 'url' => 'reports/gst-sales-summary', 'icon' => 'fas fa-fw fa-percentage', 'can' => ['reports-sales.view', 'reports-gst-sales-summary.view']],
                                ['text' => 'GST Sales Taxwise', 'url' => 'reports/view/gst-sales-taxwise', 'icon' => 'fas fa-fw fa-coins', 'can' => ['reports-sales.view', 'reports-gst-sales-taxwise.view']],
                                ['text' => 'Billwise Itemwise Sales With Assembly Details', 'url' => 'reports/view/billwise-sales-assembly', 'icon' => 'fas fa-fw fa-cogs', 'can' => ['reports-sales.view', 'reports-billwise-sales-assembly.view']],
                                ['text' => 'Tender Type Detail', 'url' => 'reports/tender-summary', 'icon' => 'fas fa-fw fa-wallet', 'can' => ['reports-sales.view', 'reports-tender-summary.view']],
                                ['text' => 'Sales Register Summary', 'url' => 'reports/view/sales-register-summary', 'icon' => 'fas fa-fw fa-book', 'can' => ['reports-sales.view', 'reports-sales-register-summary.view']],
                                ['text' => 'Offer Claim Report', 'url' => 'reports/view/offer-claim-report', 'icon' => 'fas fa-fw fa-gift', 'can' => ['reports-sales.view', 'reports-offer-claim-report.view']],
                                ['text' => 'Counterwise Sales Report', 'url' => 'reports/view/counterwise-sales-report', 'icon' => 'fas fa-fw fa-store', 'can' => ['reports-sales.view', 'reports-counterwise-sales-report.view']],
                                ['text' => 'Sales Item Margin', 'url' => 'reports/sales-margin-itemwise', 'icon' => 'fas fa-fw fa-coins', 'can' => ['reports-sales.view', 'reports-sales-margin-itemwise.view']],
                                ['text' => 'Customerwise Itemwise Sales', 'url' => 'reports/view/customerwise-itemwise-sales', 'icon' => 'fas fa-fw fa-user-tag', 'can' => ['reports-sales.view', 'reports-customerwise-itemwise-sales.view']],
                                ['text' => 'Itemwise Customerwise Sales', 'url' => 'reports/view/itemwise-customerwise-sales', 'icon' => 'fas fa-fw fa-boxes', 'can' => ['reports-sales.view', 'reports-itemwise-customerwise-sales.view']],
                                ['text' => 'Categorywise Sales', 'url' => 'reports/sales-margin-category', 'icon' => 'fas fa-fw fa-chart-pie', 'can' => ['reports-sales.view', 'reports-sales-margin-category.view']],
                                ['text' => 'Categorywise Sales Detail', 'url' => 'reports/view/categorywise-sales-detail', 'icon' => 'fas fa-fw fa-layer-group', 'can' => ['reports-sales.view', 'reports-categorywise-sales-detail.view']],
                            ],
                        ],
                        [
                            'text' => 'Orders And Quotation',
                            'icon' => 'fas fa-fw fa-file-contract',
                            'submenu' => [
                                ['text' => 'Quotation Summary', 'url' => 'reports/quotation-order-summary', 'icon' => 'fas fa-fw fa-file-signature', 'can' => ['reports-sales.view', 'reports-quotation-order-summary.view']],
                                ['text' => 'Quotation Details', 'url' => 'reports/view/quotation-details', 'icon' => 'fas fa-fw fa-file-alt', 'can' => ['reports-sales.view', 'reports-quotation-details.view']],
                                ['text' => 'Sales Order Summary', 'url' => 'reports/view/sales-order-summary', 'icon' => 'fas fa-fw fa-shopping-basket', 'can' => ['reports-sales.view', 'reports-sales-order-summary.view']],
                                ['text' => 'Sales Order Detail', 'url' => 'reports/view/sales-order-detail', 'icon' => 'fas fa-fw fa-list-alt', 'can' => ['reports-sales.view', 'reports-sales-order-detail.view']],
                                ['text' => 'Sales Order Stock Status', 'url' => 'reports/view/sales-order-stock-status', 'icon' => 'fas fa-fw fa-boxes', 'can' => ['reports-sales.view', 'reports-sales-order-stock-status.view']],
                            ],
                        ],
                        [
                            'text' => 'Delivery Reports',
                            'icon' => 'fas fa-fw fa-truck',
                            'submenu' => [
                                ['text' => 'Sales DeliveryNote Summary', 'url' => 'reports/view/sales-deliverynote-summary', 'icon' => 'fas fa-fw fa-clipboard-check', 'can' => ['reports-sales.view', 'reports-sales-deliverynote-summary.view']],
                                ['text' => 'Sales DeliveryNote Detail', 'url' => 'reports/view/sales-deliverynote-detail', 'icon' => 'fas fa-fw fa-dolly-flatbed', 'can' => ['reports-sales.view', 'reports-sales-deliverynote-detail.view']],
                                ['text' => 'Kitchen Preparation Report', 'url' => 'reports/view/kitchen-preparation-report', 'icon' => 'fas fa-fw fa-utensils', 'can' => ['reports-sales.view', 'reports-kitchen-preparation-report.view']],
                                ['text' => 'Delivery Bill Summary Report', 'url' => 'reports/view/delivery-bill-summary', 'icon' => 'fas fa-fw fa-file-invoice', 'can' => ['reports-sales.view', 'reports-delivery-bill-summary.view']],
                                ['text' => 'Delivery Bill Detail Report', 'url' => 'reports/view/delivery-bill-detail', 'icon' => 'fas fa-fw fa-list', 'can' => ['reports-sales.view', 'reports-delivery-bill-detail.view']],
                                ['text' => 'Kitchen Preparation Report [Timewise]', 'url' => 'reports/view/kitchen-preparation-timewise', 'icon' => 'fas fa-fw fa-stopwatch', 'can' => ['reports-sales.view', 'reports-kitchen-preparation-timewise.view']],
                            ],
                        ],
                        [
                            'text' => 'Returned Transactions',
                            'icon' => 'fas fa-fw fa-undo-alt',
                            'submenu' => [
                                ['text' => 'Sales Return Advice Detail', 'url' => 'reports/view/sales-return-advice-detail', 'icon' => 'fas fa-fw fa-undo', 'can' => ['reports-sales.view', 'reports-sales-return-advice-detail.view']],
                                ['text' => 'Buy Back Details', 'url' => 'reports/view/buy-back-details', 'icon' => 'fas fa-fw fa-hand-holding-usd', 'can' => ['reports-sales.view', 'reports-buy-back-details.view']],
                                ['text' => 'Sale Return Customerwise Detail', 'url' => 'reports/view/sale-return-customerwise', 'icon' => 'fas fa-fw fa-user-minus', 'can' => ['reports-sales.view', 'reports-sale-return-customerwise.view']],
                                ['text' => 'Sales Return Itemwise', 'url' => 'reports/view/sales-return-itemwise', 'icon' => 'fas fa-fw fa-box-open', 'can' => ['reports-sales.view', 'reports-sales-return-itemwise.view']],
                                ['text' => 'Sale Return Datewise', 'url' => 'reports/view/sale-return-datewise', 'icon' => 'fas fa-fw fa-calendar-times', 'can' => ['reports-sales.view', 'reports-sale-return-datewise.view']],
                                ['text' => 'Sale Return Monthwise', 'url' => 'reports/view/sale-return-monthwise', 'icon' => 'fas fa-fw fa-calendar-alt', 'can' => ['reports-sales.view', 'reports-sale-return-monthwise.view']],
                                ['text' => 'Sales Return Summary', 'url' => 'reports/sales-return-summary', 'icon' => 'fas fa-fw fa-history', 'can' => ['reports-sales.view', 'reports-sales-return-summary.view']],
                            ],
                        ],
                        ['text' => 'Cancelled Transactions', 'url' => 'reports/view/sales-cancelled-transactions', 'icon' => 'fas fa-fw fa-times-circle', 'can' => ['reports-sales.view', 'reports-sales-cancelled-transactions.view']],
                        [
                            'text' => 'Sales Analysis',
                            'icon' => 'fas fa-fw fa-chart-pie',
                            'submenu' => [
                                ['text' => 'Margin Summary', 'url' => 'reports/view/margin-summary', 'icon' => 'fas fa-fw fa-percentage', 'can' => ['reports-sales.view', 'reports-margin-summary.view']],
                                ['text' => 'Session Report', 'url' => 'reports/eod', 'icon' => 'fas fa-fw fa-calendar-check', 'can' => ['reports-sales.view', 'reports-eod.view']],
                                ['text' => 'Supplier Sales Report', 'url' => 'reports/view/supplier-sales-report', 'icon' => 'fas fa-fw fa-truck', 'can' => ['reports-sales.view', 'reports-supplier-sales-report.view']],
                                ['text' => 'Supplierwise Sales Details', 'url' => 'reports/view/supplierwise-sales-details', 'icon' => 'fas fa-fw fa-list-ul', 'can' => ['reports-sales.view', 'reports-supplierwise-sales-details.view']],
                                ['text' => 'Date Timewise Sales', 'url' => 'reports/view/date-timewise-sales', 'icon' => 'fas fa-fw fa-clock', 'can' => ['reports-sales.view', 'reports-date-timewise-sales.view']],
                                ['text' => 'Consumption Sales Summary', 'url' => 'reports/view/consumption-sales-summary', 'icon' => 'fas fa-fw fa-fire', 'can' => ['reports-sales.view', 'reports-consumption-sales-summary.view']],
                                ['text' => 'Consumption Sales Detail', 'url' => 'reports/view/consumption-sales-detail', 'icon' => 'fas fa-fw fa-burn', 'can' => ['reports-sales.view', 'reports-consumption-sales-detail.view']],
                                ['text' => 'Areawise Sales Summary', 'url' => 'reports/view/areawise-sales-summary', 'icon' => 'fas fa-fw fa-map-marker-alt', 'can' => ['reports-sales.view', 'reports-areawise-sales-summary.view']],
                                ['text' => 'Monthly Sales Detail', 'url' => 'reports/view/monthly-sales-detail', 'icon' => 'fas fa-fw fa-calendar-day', 'can' => ['reports-sales.view', 'reports-monthly-sales-detail.view']],
                                ['text' => 'Non Purchase Customer List', 'url' => 'reports/view/non-purchase-customer-list', 'icon' => 'fas fa-fw fa-user-slash', 'can' => ['reports-sales.view', 'reports-non-purchase-customer-list.view']],
                                ['text' => 'Counterwise Datewise Sales Summary', 'url' => 'reports/view/counterwise-datewise-sales', 'icon' => 'fas fa-fw fa-store-alt', 'can' => ['reports-sales.view', 'reports-counterwise-datewise-sales.view']],
                                ['text' => 'Itemwise Monthly Sales Qty/Amt Details', 'url' => 'reports/view/itemwise-monthly-sales-details', 'icon' => 'fas fa-fw fa-chart-bar', 'can' => ['reports-sales.view', 'reports-itemwise-monthly-sales-details.view']],
                            ],
                        ],
                        [
                            'text' => 'Hold Transactions',
                            'icon' => 'fas fa-fw fa-pause-circle',
                            'submenu' => [
                                ['text' => 'Hold Bill Details', 'url' => 'reports/view/hold-bill-details', 'icon' => 'fas fa-fw fa-pause', 'can' => ['reports-sales.view', 'reports-hold-bill-details.view']],
                                ['text' => 'ItemWise Discount Approval Details', 'url' => 'reports/view/itemwise-discount-approval', 'icon' => 'fas fa-fw fa-tags', 'can' => ['reports-sales.view', 'reports-itemwise-discount-approval.view']],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'Inventory',
                    'icon' => 'fas fa-fw fa-boxes',
                    'can' => 'access-inventory-reports',
                    'submenu' => [
                        [
                            'text' => 'Stock Ledger',
                            'icon' => 'fas fa-fw fa-book-open',
                            'submenu' => [
                                ['text' => 'Itemwise Stock Statement', 'url' => 'reports/view/itemwise-stock-statement', 'icon' => 'fas fa-fw fa-list', 'can' => ['reports-inventory.view', 'reports-itemwise-stock-statement.view']],
                                ['text' => 'Itemwise Stock and Sales Detail', 'url' => 'reports/view/itemwise-stock-sales-detail', 'icon' => 'fas fa-fw fa-clipboard-check', 'can' => ['reports-inventory.view', 'reports-itemwise-stock-sales-detail.view']],
                                ['text' => 'Itemwise Stock and Sales Detail Transit with Transit', 'url' => 'reports/view/itemwise-stock-sales-transit', 'icon' => 'fas fa-fw fa-truck-moving', 'can' => ['reports-inventory.view', 'reports-itemwise-stock-sales-transit.view']],
                                ['text' => 'Transactionwise Stock Register', 'url' => 'reports/view/transactionwise-stock-register', 'icon' => 'fas fa-fw fa-book', 'can' => ['reports-inventory.view', 'reports-transactionwise-stock-register.view']],
                                ['text' => 'Closing Stock', 'url' => 'reports/view/closing-stock', 'icon' => 'fas fa-fw fa-door-closed', 'can' => ['reports-inventory.view', 'reports-closing-stock.view']],
                                ['text' => 'Current Stock Branchwise', 'url' => 'reports/current-stock', 'icon' => 'fas fa-fw fa-warehouse', 'can' => ['reports-inventory.view', 'reports-current-stock.view']],
                                ['text' => 'Categorywise Datewise Stock Report', 'url' => 'reports/view/categorywise-datewise-stock', 'icon' => 'fas fa-fw fa-layer-group', 'can' => ['reports-inventory.view', 'reports-categorywise-datewise-stock.view']],
                                ['text' => 'Branchwise Stock Age Analysis', 'url' => 'reports/view/branchwise-stock-age-analysis', 'icon' => 'fas fa-fw fa-hourglass-start', 'can' => ['reports-inventory.view', 'reports-branchwise-stock-age-analysis.view']],
                                ['text' => 'Categorywise Stock Age Analysis', 'url' => 'reports/view/categorywise-stock-age-analysis', 'icon' => 'fas fa-fw fa-hourglass-end', 'can' => ['reports-inventory.view', 'reports-categorywise-stock-age-analysis.view']],
                            ],
                        ],
                        ['text' => 'Price Drop', 'url' => 'reports/view/inventory-price-drop', 'icon' => 'fas fa-fw fa-level-down-alt', 'can' => ['reports-inventory.view', 'reports-inventory-price-drop.view']],
                        ['text' => 'Price Level Advance', 'url' => 'reports/view/inventory-price-level-advance', 'icon' => 'fas fa-fw fa-layer-group', 'can' => ['reports-inventory.view', 'reports-inventory-price-level-advance.view']],
                        [
                            'text' => 'Stock Movement',
                            'icon' => 'fas fa-fw fa-exchange-alt',
                            'submenu' => [
                                ['text' => 'Transfer Out Approval Detail', 'url' => 'reports/view/transfer-out-approval-detail', 'icon' => 'fas fa-fw fa-check-double', 'can' => ['reports-inventory.view', 'reports-transfer-out-approval-detail.view']],
                                ['text' => 'Stock TransferOut Summary', 'url' => 'reports/stock-transfer-summary', 'icon' => 'fas fa-fw fa-arrow-circle-right', 'can' => ['reports-inventory.view', 'reports-stock-transfer-summary.view']],
                                ['text' => 'Stock TransferOut Detail', 'url' => 'reports/view/stock-transferout-detail', 'icon' => 'fas fa-fw fa-file-export', 'can' => ['reports-inventory.view', 'reports-stock-transferout-detail.view']],
                                ['text' => 'Itemwise Storewise TransferOut Detail', 'url' => 'reports/view/itemwise-storewise-transferout', 'icon' => 'fas fa-fw fa-boxes', 'can' => ['reports-inventory.view', 'reports-itemwise-storewise-transferout.view']],
                                ['text' => 'Stock TransferIn Summary', 'url' => 'reports/view/stock-transferin-summary', 'icon' => 'fas fa-fw fa-arrow-circle-left', 'can' => ['reports-inventory.view', 'reports-stock-transferin-summary.view']],
                                ['text' => 'Stock TransferIn Detail', 'url' => 'reports/view/stock-transferin-detail', 'icon' => 'fas fa-fw fa-file-import', 'can' => ['reports-inventory.view', 'reports-stock-transferin-detail.view']],
                                ['text' => 'Itemwise Storewise Transfer In Detail', 'url' => 'reports/view/itemwise-storewise-transferin', 'icon' => 'fas fa-fw fa-dolly', 'can' => ['reports-inventory.view', 'reports-itemwise-storewise-transferin.view']],
                                ['text' => 'Stock In Transit', 'url' => 'reports/view/stock-in-transit', 'icon' => 'fas fa-fw fa-shipping-fast', 'can' => ['reports-inventory.view', 'reports-stock-in-transit.view']],
                                ['text' => 'Stock Transfer Discrepancy', 'url' => 'reports/view/stock-transfer-discrepancy', 'icon' => 'fas fa-fw fa-exclamation-triangle', 'can' => ['reports-inventory.view', 'reports-stock-transfer-discrepancy.view']],
                                ['text' => 'TO Vs TIN', 'url' => 'reports/view/to-vs-tin', 'icon' => 'fas fa-fw fa-random', 'can' => ['reports-inventory.view', 'reports-to-vs-tin.view']],
                                ['text' => 'Stock Conversion Report', 'url' => 'reports/view/stock-conversion-report', 'icon' => 'fas fa-fw fa-recycle', 'can' => ['reports-inventory.view', 'reports-stock-conversion-report.view']],
                            ],
                        ],
                        [
                            'text' => 'Stock Analysis',
                            'icon' => 'fas fa-fw fa-search-plus',
                            'submenu' => [
                                ['text' => 'Stock Update Detail', 'url' => 'reports/view/stock-update-detail', 'icon' => 'fas fa-fw fa-edit', 'can' => ['reports-inventory.view', 'reports-stock-update-detail.view']],
                                ['text' => 'Wastage/Damage Stock', 'url' => 'reports/damage-stock-summary', 'icon' => 'fas fa-fw fa-heart-broken', 'can' => ['reports-inventory.view', 'reports-damage-stock-summary.view']],
                                ['text' => 'Categorywise Storewise Current Stock Summary', 'url' => 'reports/view/categorywise-storewise-stock-summary', 'icon' => 'fas fa-fw fa-layer-group', 'can' => ['reports-inventory.view', 'reports-categorywise-storewise-stock-summary.view']],
                                ['text' => 'Categorywise Storewise Current Stock Detail', 'url' => 'reports/view/categorywise-storewise-stock-detail', 'icon' => 'fas fa-fw fa-list-alt', 'can' => ['reports-inventory.view', 'reports-categorywise-storewise-stock-detail.view']],
                                ['text' => 'Stock - Categorwise FastMoving - Item', 'url' => 'reports/view/stock-fastmoving-item', 'icon' => 'fas fa-fw fa-bolt', 'can' => ['reports-inventory.view', 'reports-stock-fastmoving-item.view']],
                                ['text' => 'Stock - Categorwise - SlowMoving - Item', 'url' => 'reports/view/stock-slowmoving-item', 'icon' => 'fas fa-fw fa-bed', 'can' => ['reports-inventory.view', 'reports-stock-slowmoving-item.view']],
                                ['text' => 'Wastage/Damage Stock Detail', 'url' => 'reports/view/wastage-damage-stock-detail', 'icon' => 'fas fa-fw fa-file-excel', 'can' => ['reports-inventory.view', 'reports-wastage-damage-stock-detail.view']],
                                ['text' => 'Item Age Analysis', 'url' => 'reports/view/item-age-analysis', 'icon' => 'fas fa-fw fa-clock', 'can' => ['reports-inventory.view', 'reports-item-age-analysis.view']],
                                ['text' => 'Item Expiry Update Details', 'url' => 'reports/view/item-expiry-update-details', 'icon' => 'fas fa-fw fa-calendar-times', 'can' => ['reports-inventory.view', 'reports-item-expiry-update-details.view']],
                                ['text' => 'Items Details In Cart', 'url' => 'reports/view/items-details-in-cart', 'icon' => 'fas fa-fw fa-shopping-basket', 'can' => ['reports-inventory.view', 'reports-items-details-in-cart.view']],
                                ['text' => 'Stock Reserve Status', 'url' => 'reports/view/stock-reserve-status', 'icon' => 'fas fa-fw fa-bookmark', 'can' => ['reports-inventory.view', 'reports-stock-reserve-status.view']],
                            ],
                        ],
                        [
                            'text' => 'Stock Replenishment',
                            'icon' => 'fas fa-fw fa-sync-alt',
                            'submenu' => [
                                ['text' => 'MBQ Detail', 'url' => 'reports/view/mbq-detail', 'icon' => 'fas fa-fw fa-chart-line', 'can' => ['reports-inventory.view', 'reports-mbq-detail.view']],
                                ['text' => 'Kit Preparation', 'url' => 'reports/view/kit-preparation', 'icon' => 'fas fa-fw fa-tools', 'can' => ['reports-inventory.view', 'reports-kit-preparation.view']],
                                ['text' => 'Kit Unpack', 'url' => 'reports/view/kit-unpack', 'icon' => 'fas fa-fw fa-box-open', 'can' => ['reports-inventory.view', 'reports-kit-unpack.view']],
                                ['text' => 'Itemwise Stock Transfer Advice', 'url' => 'reports/view/itemwise-stock-transfer-advice', 'icon' => 'fas fa-fw fa-file-prescription', 'can' => ['reports-inventory.view', 'reports-itemwise-stock-transfer-advice.view']],
                                ['text' => 'Indent Based On Replenishment', 'url' => 'reports/view/indent-based-replenishment', 'icon' => 'fas fa-fw fa-indent', 'can' => ['reports-inventory.view', 'reports-indent-based-replenishment.view']],
                                ['text' => 'Indent Summary', 'url' => 'reports/view/indent-summary', 'icon' => 'fas fa-fw fa-list', 'can' => ['reports-inventory.view', 'reports-indent-summary.view']],
                                ['text' => 'PO Replenishment', 'url' => 'reports/view/po-replenishment', 'icon' => 'fas fa-fw fa-truck-loading', 'can' => ['reports-inventory.view', 'reports-po-replenishment.view']],
                                ['text' => 'Picklist Detail', 'url' => 'reports/view/picklist-detail', 'icon' => 'fas fa-fw fa-tasks', 'can' => ['reports-inventory.view', 'reports-picklist-detail.view']],
                                ['text' => 'Picking List Discrepancy', 'url' => 'reports/view/picking-list-discrepancy', 'icon' => 'fas fa-fw fa-exclamation', 'can' => ['reports-inventory.view', 'reports-picking-list-discrepancy.view']],
                                ['text' => 'Opening Stock Detail', 'url' => 'reports/view/opening-stock-detail', 'icon' => 'fas fa-fw fa-door-open', 'can' => ['reports-inventory.view', 'reports-opening-stock-detail.view']],
                                ['text' => 'Repack Summary', 'url' => 'reports/view/repack-summary', 'icon' => 'fas fa-fw fa-box', 'can' => ['reports-inventory.view', 'reports-repack-summary.view']],
                                ['text' => 'Eancode Detail', 'url' => 'reports/view/eancode-detail', 'icon' => 'fas fa-fw fa-barcode', 'can' => ['reports-inventory.view', 'reports-eancode-detail.view']],
                                ['text' => 'Repack Detail', 'url' => 'reports/view/repack-detail', 'icon' => 'fas fa-fw fa-boxes', 'can' => ['reports-inventory.view', 'reports-repack-detail.view']],
                                ['text' => 'Re-order Report', 'url' => 'reports/reorder-report', 'icon' => 'fas fa-fw fa-exclamation-triangle', 'can' => ['reports-inventory.view', 'reports-reorder-report.view']],
                                ['text' => 'Issue Date Expiry Details', 'url' => 'reports/view/issue-date-expiry-details', 'icon' => 'fas fa-fw fa-calendar-day', 'can' => ['reports-inventory.view', 'reports-issue-date-expiry-details.view']],
                            ],
                        ],
                    ],
                ],
                [
                    'text' => 'My Reports',
                    'icon' => 'fas fa-fw fa-folder-open',
                    'can' => 'access-sales-reports',
                    'submenu' => [
                        ['text' => 'Counterwise Sales Summary', 'url' => 'reports/view/my-counterwise-sales-summary', 'icon' => 'fas fa-fw fa-store', 'can' => ['reports-sales.view', 'reports-my-counterwise-sales-summary.view']],
                        ['text' => 'Sales MIS report', 'url' => 'reports/view/my-sales-mis-report', 'icon' => 'fas fa-fw fa-file-invoice-dollar', 'can' => ['reports-sales.view', 'reports-my-sales-mis-report.view']],
                        ['text' => 'Off Take - Dealer Tracker Report', 'url' => 'reports/view/my-offtake-dealer-tracker', 'icon' => 'fas fa-fw fa-chart-line', 'can' => ['reports-sales.view', 'reports-my-offtake-dealer-tracker.view']],
                    ],
                ],
                [
                    'text' => 'Production',
                    'icon' => 'fas fa-fw fa-industry',
                    'can' => 'access-production-reports',
                    'submenu' => [
                        ['text' => 'Production Plan', 'url' => 'reports/view/production-plan', 'icon' => 'fas fa-fw fa-tasks', 'can' => ['reports-inventory.view', 'reports-production-plan.view']],
                        ['text' => 'BOM Report', 'url' => 'reports/view/bom-report', 'icon' => 'fas fa-fw fa-sitemap', 'can' => ['reports-inventory.view', 'reports-bom-report.view']],
                        ['text' => 'Production Costing Summary', 'url' => 'reports/view/production-costing-summary', 'icon' => 'fas fa-fw fa-calculator', 'can' => ['reports-inventory.view', 'reports-production-costing-summary.view']],
                        ['text' => 'Production Costing Detail', 'url' => 'reports/view/production-costing-detail', 'icon' => 'fas fa-fw fa-list-ol', 'can' => ['reports-inventory.view', 'reports-production-costing-detail.view']],
                        ['text' => 'Sales/Production Variance', 'url' => 'reports/view/sales-production-variance', 'icon' => 'fas fa-fw fa-balance-scale', 'can' => ['reports-inventory.view', 'reports-sales-production-variance.view']],
                    ],
                ],
                [
                    'text' => 'More',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'can' => 'access-reports-menu',
                    'submenu' => [
                        [
                            'text' => 'Serialized Reports',
                            'icon' => 'fas fa-fw fa-barcode',
                            'submenu' => [
                                ['text' => 'Serial Number History', 'url' => 'reports/view/serial-number-history', 'icon' => 'fas fa-fw fa-history', 'can' => ['reports-dashboard.view', 'reports-serial-number-history.view']],
                                ['text' => 'Serial No Wise Stock Detail', 'url' => 'reports/view/serial-nowise-stock-detail', 'icon' => 'fas fa-fw fa-cubes', 'can' => ['reports-dashboard.view', 'reports-serial-nowise-stock-detail.view']],
                            ],
                        ],
                        ['text' => 'Offline Exported Reports', 'url' => 'reports/view/offline-exported-reports', 'icon' => 'fas fa-fw fa-file-export', 'can' => ['reports-dashboard.view', 'reports-offline-exported-reports.view']],
                        ['text' => 'Dashboard', 'url' => 'reports', 'icon' => 'fas fa-fw fa-tachometer-alt', 'can' => ['reports-dashboard.view', 'reports-dashboard.view']],
                        ['text' => 'Monthly Transaction Summary', 'url' => 'reports/view/monthly-transaction-summary', 'icon' => 'fas fa-fw fa-calendar-alt', 'can' => ['reports-dashboard.view', 'reports-monthly-transaction-summary.view']],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Tools',
            'icon' => 'fas fa-fw fa-tools',
            'can' => ['users.create', 'whatsapp-settings.edit', 'whatsapp-settings.send', 'receipt-designer.edit', 'document-sequences.edit', 'custom-fields.create', 'form-validations.edit', 'system-error-logs.view', 'system-health.view', 'database-backups.create', 'bulk-updater.apply', 'einvoices.view', 'gst-returns.view', 'financial-years.create'],
            'submenu' => [
                [
                    'text' => 'Configuration',
                    'icon' => 'fas fa-fw fa-cogs',
                    'can' => ['users.create', 'financial-years.create'],
                    'submenu' => [
                        ['text' => 'User Master', 'url' => 'master/users', 'icon' => 'fas fa-fw fa-users-cog', 'active' => ['master/users*'], 'can' => 'users.create'],
                        ['text' => 'Role & Permission Master', 'url' => 'master/roles', 'icon' => 'fas fa-fw fa-user-shield', 'active' => ['master/roles*'], 'can' => 'users.create'],
                        ['text' => 'Financial Years', 'url' => 'master/financial-years', 'icon' => 'fas fa-fw fa-calendar-alt', 'can' => 'financial-years.create'],
                        ['text' => 'Userwise Configuration', 'url' => 'tools/userwise-config', 'icon' => 'fas fa-fw fa-user-cog', 'can' => 'users.create'],
                        ['text' => 'Business Configuration', 'url' => 'tools/business-config', 'icon' => 'fas fa-fw fa-briefcase', 'can' => 'users.create'],
                        ['text' => 'Function Key Mapping', 'url' => 'tools/function-keys', 'icon' => 'fas fa-fw fa-keyboard', 'can' => 'users.create'],
                        ['text' => 'Ledger Map', 'url' => 'tools/ledger-map', 'icon' => 'fas fa-fw fa-map', 'can' => 'users.create'],
                        ['text' => 'Asset LedgerMap', 'url' => 'tools/asset-ledger', 'icon' => 'fas fa-fw fa-landmark', 'can' => 'users.create'],
                        ['text' => 'Mail Server Configuration', 'url' => 'tools/mail-server', 'icon' => 'fas fa-fw fa-envelope', 'can' => 'users.create'],
                        ['text' => 'Category Wise Sequence', 'url' => 'tools/category-sequence', 'icon' => 'fas fa-fw fa-sort-numeric-down', 'can' => 'users.create'],
                        ['text' => 'POS Screen Lock Settings', 'url' => 'tools/pos-settings', 'icon' => 'fas fa-fw fa-lock text-warning', 'active' => ['tools/pos-settings*'], 'can' => 'users.create'],
                        ['text' => 'Form Field Validations', 'url' => 'tools/form-validations', 'icon' => 'fas fa-fw fa-check-double', 'can' => 'form-validations.edit'],
                    ],
                ],
                [
                    'text' => 'Integrations',
                    'icon' => 'fas fa-fw fa-plug',
                    'can' => ['whatsapp-settings.edit', 'einvoices.view', 'gst-returns.view', 'users.create'],
                    'submenu' => [
                        ['text' => 'WhatsApp Integration', 'url' => 'tools/whatsapp-settings', 'icon' => 'fab fa-fw fa-whatsapp text-success', 'active' => ['tools/whatsapp-settings*'], 'can' => ['whatsapp-settings.edit', 'whatsapp-settings.send']],
                        ['text' => 'GoFrugal Alert', 'url' => 'tools/integrations-alert', 'icon' => 'fas fa-fw fa-bell', 'can' => 'users.create'],
                        ['text' => 'GST Efiling', 'url' => 'tools/integrations-gst', 'icon' => 'fas fa-fw fa-cloud-upload-alt', 'can' => ['einvoices.view', 'gst-returns.view']],
                        ['text' => 'GOFRUGAL Gosure', 'url' => 'tools/integrations-gosure', 'icon' => 'fas fa-fw fa-shield-alt', 'can' => 'users.create'],
                    ],
                ],
                [
                    'text' => 'WhatsApp Settings',
                    'url' => 'tools/whatsapp-settings',
                    'icon' => 'fab fa-fw fa-whatsapp text-success',
                    'active' => ['tools/whatsapp-settings*'],
                    'can' => ['whatsapp-settings.edit', 'whatsapp-settings.send'],
                ],
                [
                    'text' => 'Receipt Designer',
                    'url' => 'tools/receipt-designer',
                    'icon' => 'fas fa-fw fa-receipt text-warning',
                    'active' => ['tools/receipt-designer*'],
                    'can' => 'receipt-designer.edit',
                ],
                [
                    'text' => 'Document Sequences',
                    'url' => 'tools/document-sequences',
                    'icon' => 'fas fa-fw fa-sort-numeric-up-alt text-primary',
                    'active' => ['tools/document-sequences*'],
                    'can' => 'document-sequences.edit',
                ],
                [
                    'text' => 'Custom Fields Builder',
                    'url' => 'tools/custom-fields',
                    'icon' => 'fas fa-fw fa-sliders-h text-info',
                    'active' => ['tools/custom-fields*'],
                    'can' => 'custom-fields.create',
                ],
                [
                    'text' => 'Form Validations',
                    'url' => 'tools/form-validations',
                    'icon' => 'fas fa-fw fa-check-double text-success',
                    'active' => ['tools/form-validations*'],
                    'can' => 'form-validations.edit',
                ],
                [
                    'text' => 'Master Migration',
                    'url' => 'tools/master-migration',
                    'icon' => 'fas fa-fw fa-database',
                    'can' => 'users.create',
                ],
                [
                    'text' => 'System Error Logs',
                    'url' => 'tools/system-error-logs',
                    'icon' => 'fas fa-fw fa-bug text-danger',
                    'active' => ['tools/system-error-logs*'],
                    'can' => 'system-error-logs.view',
                ],
                [
                    'text' => 'System Health & Monitor',
                    'url' => 'tools/system-health',
                    'icon' => 'fas fa-fw fa-heartbeat text-success',
                    'active' => ['tools/system-health*'],
                    'can' => 'system-health.view',
                ],
                [
                    'text' => 'GoFrugal TruePOS Sync',
                    'url' => 'tools/gofrugal-sync',
                    'icon' => 'fas fa-fw fa-sync-alt text-success',
                    'active' => ['tools/gofrugal-sync*'],
                ],
                [
                    'text' => 'Database Backups',
                    'url' => 'tools/backups',
                    'icon' => 'fas fa-fw fa-database text-primary',
                    'active' => ['tools/backups*'],
                    'can' => 'database-backups.create',
                ],
                [
                    'text' => 'Universal Bulk Modifier',
                    'url' => 'tools/bulk-updater',
                    'icon' => 'fas fa-fw fa-layer-group text-primary',
                    'active' => ['tools/bulk-updater*'],
                    'can' => 'bulk-updater.apply',
                ],
                [
                    'text' => 'Manage Subscription',
                    'url' => 'tools/manage-subscription',
                    'icon' => 'fas fa-fw fa-certificate',
                    'can' => 'users.create',
                ],
                [
                    'text' => 'More Tools',
                    'icon' => 'fas fa-fw fa-ellipsis-h',
                    'can' => 'users.create',
                    'submenu' => [
                        ['text' => 'Year Begin Sequence Change', 'url' => 'tools/year-begin-sequence', 'icon' => 'fas fa-fw fa-calendar-plus', 'can' => 'users.create'],
                        ['text' => 'Session Management', 'url' => 'tools/session-management', 'icon' => 'fas fa-fw fa-user-clock', 'can' => 'users.create'],
                        ['text' => 'Reprint', 'url' => 'tools/reprint', 'icon' => 'fas fa-fw fa-print', 'can' => 'users.create'],
                        ['text' => 'Multiple Dispatch', 'url' => 'tools/multiple-dispatch', 'icon' => 'fas fa-fw fa-paper-plane', 'can' => 'users.create'],
                        ['text' => 'Report Scheduler', 'url' => 'tools/report-scheduler', 'icon' => 'fas fa-fw fa-clock', 'can' => 'users.create'],
                        ['text' => 'E-way Update', 'url' => 'tools/eway-update', 'icon' => 'fas fa-fw fa-route', 'can' => 'eway-bills.create'],
                        ['text' => 'Barcode Config', 'url' => 'tools/barcode-config', 'icon' => 'fas fa-fw fa-barcode', 'can' => 'users.create'],
                        ['text' => 'Service User Consent', 'url' => 'tools/service-consent', 'icon' => 'fas fa-fw fa-file-contract', 'can' => 'users.create'],
                    ],
                ],
            ],
        ],
        [
            'text' => 'Finance & Accounts',
            'icon' => 'fas fa-fw fa-file-invoice-dollar',
            'can' => ['ledgers.create', 'vouchers.create', 'bill-settlements.create', 'reports-finance.view'],
            'submenu' => [
                [
                    'text' => 'Ledger Master',
                    'url' => 'finance/ledgers',
                    'icon' => 'fas fa-fw fa-book',
                    'active' => ['finance/ledgers*'],
                    'can' => 'ledgers.create',
                ],
                [
                    'text' => 'Voucher Entry',
                    'url' => 'finance/vouchers',
                    'icon' => 'fas fa-fw fa-file-invoice',
                    'active' => ['finance/vouchers*'],
                    'can' => 'vouchers.create',
                ],
                [
                    'text' => 'Credit Settlement',
                    'url' => 'finance/settlements',
                    'icon' => 'fas fa-fw fa-hand-holding-usd',
                    'active' => ['finance/settlements*'],
                    'can' => 'bill-settlements.create',
                ],
                [
                    'text' => 'Finance Reports',
                    'url' => 'finance/reports',
                    'icon' => 'fas fa-fw fa-chart-pie',
                    'can' => 'reports-finance.view',
                    'submenu' => [
                        ['text' => 'General Ledger', 'url' => 'finance/reports/general-ledger', 'icon' => 'fas fa-fw fa-book', 'can' => 'reports-finance.view'],
                        ['text' => 'Day Book', 'url' => 'finance/reports/day-book', 'icon' => 'fas fa-fw fa-calendar-day', 'can' => 'reports-finance.view'],
                        ['text' => 'Cash & Bank Book', 'url' => 'finance/reports/cash-bank-book', 'icon' => 'fas fa-fw fa-money-check-alt', 'can' => 'reports-finance.view'],
                        ['text' => 'Billwise Outstanding Aging', 'url' => 'finance/reports/outstanding-aging', 'icon' => 'fas fa-fw fa-hourglass-half', 'can' => 'reports-finance.view'],
                        ['text' => 'Trial Balance', 'url' => 'finance/reports/trial-balance', 'icon' => 'fas fa-fw fa-balance-scale', 'can' => 'reports-finance.view'],
                        ['text' => 'Profit & Loss', 'url' => 'finance/reports/profit-loss', 'icon' => 'fas fa-fw fa-chart-pie', 'can' => 'reports-finance.view'],
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
                    'location' => 'js/select2-init.js?v=20260925_fix_focus_open',
                ],
            ],
        ],
        'DateRangePicker' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/moment/moment.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/daterangepicker/daterangepicker.js',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/daterangepicker/daterangepicker.css',
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/datepicker-init.js?v=20260925_1',
                ],
            ],
        ],
        'Toastr' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/toastr/toastr.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/toastr/toastr.min.js',
                ],
            ],
        ],
        'PosScanGuard' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/pos-scan-guard.js',
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
