<div id="urbanpos-command-palette-backdrop" class="up-command-backdrop" style="display: none;">
    <div class="up-command-modal">
        <div class="up-command-header">
            <i class="fas fa-search up-command-search-icon"></i>
            <input type="text" id="up-command-input" class="up-command-input" placeholder="Type a command or jump to... (e.g. sale, item, po, gst, stock)" autocomplete="off" spellcheck="false">
            <button type="button" id="up-command-close-btn" class="up-command-close-btn" title="Close (Esc)">
                <kbd>ESC</kbd>
            </button>
        </div>
        <div class="up-command-body">
            <div id="up-command-results" class="up-command-results">
                {{-- Dynamically populated and filtered via JS --}}
            </div>
            <div id="up-command-empty" class="up-command-empty" style="display: none;">
                <i class="fas fa-search-minus fa-2x mb-2 text-muted"></i>
                <p class="mb-0 text-muted">No commands or pages found for "<span id="up-command-query-text"></span>"</p>
            </div>
        </div>
        <div class="up-command-footer">
            <div class="up-command-shortcuts-hint">
                <span><kbd>↑</kbd> <kbd>↓</kbd> Navigate</span>
                <span class="mx-2">•</span>
                <span><kbd>↵</kbd> Select / Open</span>
                <span class="mx-2">•</span>
                <span><kbd>ESC</kbd> Close</span>
            </div>
            <div class="up-command-branding">
                <span class="text-muted"><i class="fas fa-bolt text-warning mr-1"></i> UrbanPOS Spotlight</span>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Command registry containing navigation links, actions, and tools
    var COMMAND_ITEMS = [
        // POS & Sales
        { id: 'pos-terminal', title: 'POS Terminal', desc: 'Fast retail cashier checkout & barcode billing', category: 'POS & Sales', icon: 'fas fa-cash-register text-success', url: '{{ Route::has("pos.terminal") ? route("pos.terminal") : url("/pos/terminal") }}', shortcut: 'Alt+P', keywords: 'pos cashier checkout terminal retail bill barcode counter' },
        { id: 'sale-bill-create', title: 'New Sales Bill', desc: 'Create wholesale / B2B / standard sales invoice', category: 'POS & Sales', icon: 'fas fa-plus-circle text-primary', url: '{{ Route::has("sales.sales-bills.create") ? route("sales.sales-bills.create") : url("/sales/sales-bills/create") }}', shortcut: 'Alt+S', keywords: 'new sale bill create invoice customer' },
        { id: 'sales-bills-list', title: 'Sales Bills List', desc: 'View, filter, and print previous sales invoices', category: 'POS & Sales', icon: 'fas fa-file-invoice text-info', url: '{{ Route::has("sales.sales-bills.index") ? route("sales.sales-bills.index") : url("/sales/sales-bills") }}', keywords: 'sales bills invoices history list print view' },
        { id: 'sales-return-list', title: 'Sales Returns (Credit Notes)', desc: 'Manage customer returns, refunds, and batch adjustments', category: 'POS & Sales', icon: 'fas fa-undo-alt text-danger', url: '{{ Route::has("sales.sales-returns.index") ? route("sales.sales-returns.index") : url("/sales/sales-returns") }}', keywords: 'sales return credit note refund customer back' },
        { id: 'sales-order-list', title: 'Sales Orders', desc: 'Customer advance bookings and orders', category: 'POS & Sales', icon: 'fas fa-shopping-cart text-warning', url: '{{ Route::has("sales.sales-orders.index") ? route("sales.sales-orders.index") : url("/sales/sales-orders") }}', keywords: 'sales order booking advance SO' },
        { id: 'sales-quotations-list', title: 'Sales Quotations (Estimates)', desc: 'Generate price estimates and quotations for customers', category: 'POS & Sales', icon: 'fas fa-calculator text-secondary', url: '{{ Route::has("sales.sales-quotations.index") ? route("sales.sales-quotations.index") : url("/sales/sales-quotations") }}', keywords: 'quotation estimate quote customer price' },
        { id: 'delivery-notes-list', title: 'Delivery Challans / Notes', desc: 'Dispatch items with delivery notes', category: 'POS & Sales', icon: 'fas fa-truck text-primary', url: '{{ Route::has("sales.delivery-notes.index") ? route("sales.delivery-notes.index") : url("/sales/delivery-notes") }}', keywords: 'delivery note challan dispatch shipping' },

        // Purchases
        { id: 'purchase-invoice-create', title: 'New Purchase Invoice', desc: 'Enter vendor bills with batch, exp, and GST breakdown', category: 'Purchases', icon: 'fas fa-file-invoice-dollar text-primary', url: '{{ Route::has("purchase.purchase-invoices.create") ? route("purchase.purchase-invoices.create") : url("/purchase/purchase-invoices/create") }}', shortcut: 'Alt+P', keywords: 'purchase invoice vendor bill inward goods entry PI' },
        { id: 'purchase-invoices-list', title: 'Purchase Invoices List', desc: 'Browse all vendor inward bills and payment ledger', category: 'Purchases', icon: 'fas fa-list-alt text-info', url: '{{ Route::has("purchase.purchase-invoices.index") ? route("purchase.purchase-invoices.index") : url("/purchase/purchase-invoices") }}', keywords: 'purchase bills invoices inward list suppliers' },
        { id: 'purchase-return-list', title: 'Purchase Returns (Debit Notes)', desc: 'Return defective or excess stock to vendor with debit note', category: 'Purchases', icon: 'fas fa-reply text-danger', url: '{{ Route::has("purchase.purchase-returns.index") ? route("purchase.purchase-returns.index") : url("/purchase/purchase-returns") }}', keywords: 'purchase return debit note supplier vendor return PR' },
        { id: 'purchase-orders-list', title: 'Purchase Orders (PO)', desc: 'Generate and send procurement purchase orders to vendors', category: 'Purchases', icon: 'fas fa-clipboard-list text-secondary', url: '{{ Route::has("purchase.purchase-orders.index") ? route("purchase.purchase-orders.index") : url("/purchase/purchase-orders") }}', keywords: 'purchase order PO supplier procurement' },

        // Inventory & Stocks
        { id: 'stock-transfers', title: 'Stock Transfer (Branch-to-Branch)', desc: 'Transfer inventory batches between multiple store branches', category: 'Inventory', icon: 'fas fa-exchange-alt text-primary', url: '{{ route("inventory.stock-transfers.index") }}', shortcut: 'Alt+T', keywords: 'stock transfer branch movement inter branch' },
        { id: 'opening-stocks', title: 'Opening Stocks', desc: 'Initialize and enter opening balances per batch', category: 'Inventory', icon: 'fas fa-folder-plus text-info', url: '{{ route("inventory.opening-stocks.index") }}', keywords: 'opening stock initial balance inventory setup' },
        { id: 'stock-updates', title: 'Stock Adjustments & Physical Audit', desc: 'Reconcile physical stock count with system ledger', category: 'Inventory', icon: 'fas fa-sync-alt text-warning', url: '{{ route("inventory.stock-updates.index") }}', keywords: 'stock update adjustment physical audit count variance' },
        { id: 'damage-stocks', title: 'Damage & Expired Stocks', desc: 'Write off damaged, broken, or expired items', category: 'Inventory', icon: 'fas fa-trash-alt text-danger', url: '{{ route("inventory.damage-stocks.index") }}', keywords: 'damage stock expired write off breakage loss' },
        { id: 'barcode-printing', title: 'Barcode Label Printing', desc: 'Print barcode stickers and price tags for inventory items', category: 'Inventory', icon: 'fas fa-barcode text-success', url: '{{ route("inventory.barcode.index") }}', keywords: 'barcode print label sticker tag' },

        // Masters
        { id: 'master-items', title: 'Items Master Catalog', desc: 'Manage products, barcodes, MRP, taxes, and categories', category: 'Masters', icon: 'fas fa-box text-primary', url: '{{ route("master.items.index") }}', shortcut: 'Alt+I', keywords: 'item master products catalog barcodes goods' },
        { id: 'master-items-create', title: 'Add New Item / Product', desc: 'Register a new product with barcode, GST rate, and MRP', category: 'Masters', icon: 'fas fa-plus text-success', url: '{{ route("master.items.create") }}', keywords: 'add item create product new barcode sku' },
        { id: 'master-customers', title: 'Customers Master', desc: 'Customer accounts, contact directory, and ledger balances', category: 'Masters', icon: 'fas fa-users text-info', url: '{{ route("master.customers.index") }}', shortcut: 'Alt+C', keywords: 'customer master client accounts phone gst' },
        { id: 'master-suppliers', title: 'Suppliers Master', desc: 'Vendor directory, GSTIN, and payable ledger accounts', category: 'Masters', icon: 'fas fa-truck-loading text-secondary', url: '{{ route("master.suppliers.index") }}', keywords: 'supplier vendor master distributor contacts' },
        { id: 'master-brands', title: 'Brands Master', desc: 'Manage brand hierarchy and manufacturers', category: 'Masters', icon: 'fas fa-copyright text-warning', url: '{{ route("master.brands.index") }}', keywords: 'brands master manufacturer maker' },
        { id: 'master-categories', title: 'Item Categories', desc: 'Organize items by department and category', category: 'Masters', icon: 'fas fa-tags text-teal', url: '{{ route("master.item-categories.index") }}', keywords: 'categories departments groups classification' },
        { id: 'master-taxes', title: 'GST Tax Rates', desc: 'Configure CGST, SGST, IGST percentage slabs', category: 'Masters', icon: 'fas fa-percent text-danger', url: '{{ route("master.gst-taxes.index") }}', keywords: 'taxes gst rate slab percentage cgst sgst igst' },
        { id: 'master-branches', title: 'Branch Locations', desc: 'Multi-store branch configurations and GSTINs', category: 'Masters', icon: 'fas fa-store text-info', url: '{{ route("master.branches.index") }}', keywords: 'branch store location warehouse branch_id' },

        // GST & Reports
        { id: 'gst-gstr1', title: 'GSTR-1 Tax Filing Tool', desc: 'B2B, B2CL, B2CS, and HSN summary export for GST portal', category: 'Reports & GST', icon: 'fas fa-university text-danger', url: '{{ route("tools.gst.gstr-1.page") }}', keywords: 'gstr1 gst return filing tax portal b2b b2c hsn export json excel' },
        { id: 'report-gst-sales', title: 'GST Sales Summary', desc: 'Detailed sales invoice tax-wise analysis and summaries', category: 'Reports & GST', icon: 'fas fa-chart-line text-success', url: '{{ route("reports.gst-sales-summary") }}', keywords: 'gst sales tax register monthly outward supply' },
        { id: 'report-gst-purchases', title: 'GST Purchase Summary', desc: 'Inward purchase tax breakdown and ITC input credit audit', category: 'Reports & GST', icon: 'fas fa-receipt text-primary', url: '{{ route("reports.gst-purchase-summary") }}', keywords: 'gst purchase input tax credit itc inward supply summary' },
        { id: 'report-general-ledger', title: 'General Ledger', desc: 'Comprehensive financial debit and credit ledger', category: 'Reports & GST', icon: 'fas fa-book text-warning', url: '{{ route("finance.reports.general-ledger") }}', keywords: 'general ledger accounting debit credit journal entries' },
        { id: 'report-day-book', title: 'Day Book & Transactions', desc: 'Daily cash/bank entries, receipts, and payments', category: 'Reports & GST', icon: 'fas fa-calendar-day text-secondary', url: '{{ route("finance.reports.day-book") }}', keywords: 'day book daily transactions cash bank receipts payments ledger' },
        { id: 'tools-health', title: 'System Health & Monitor', desc: 'Live database, queue, cache, and 24/7 diagnostic monitor', category: 'Reports & GST', icon: 'fas fa-heartbeat text-danger', url: '{{ route("tools.system-health.index") }}', keywords: 'health monitor diagnostics server performance' },


        // Quick System Actions
        { id: 'action-theme', title: 'Toggle Dark / Light Mode', desc: 'Switch interface between sleek Slate Dark and clean Light theme', category: 'Quick Actions', icon: 'fas fa-moon text-indigo', action: 'toggleTheme', keywords: 'dark mode light theme night shift color style' },
        { id: 'action-density', title: 'Toggle Table Density (Compact / Comfortable)', desc: 'Switch between dense data-table rows and spacious comfortable view', category: 'Quick Actions', icon: 'fas fa-compress-arrows-alt text-teal', action: 'toggleDensity', keywords: 'table density compact comfortable rows padding spacing view' },
        { id: 'action-shortcuts', title: 'Keyboard Shortcuts Cheat Sheet', desc: 'View complete list of Marg / Tally POS shortcut keys', category: 'Quick Actions', icon: 'fas fa-keyboard text-primary', action: 'openShortcuts', keywords: 'shortcuts keys hotkeys help tally marg f1 f2 alt' }
    ];

    var backdrop = document.getElementById('urbanpos-command-palette-backdrop');
    var input = document.getElementById('up-command-input');
    var resultsContainer = document.getElementById('up-command-results');
    var emptyContainer = document.getElementById('up-command-empty');
    var queryText = document.getElementById('up-command-query-text');
    var closeBtn = document.getElementById('up-command-close-btn');

    var activeIndex = 0;
    var filteredItems = [];

    // Render results
    function renderResults(items) {
        filteredItems = items;
        resultsContainer.innerHTML = '';
        if (items.length === 0) {
            emptyContainer.style.display = 'block';
            queryText.textContent = input.value.trim();
            resultsContainer.style.display = 'none';
            return;
        }

        emptyContainer.style.display = 'none';
        resultsContainer.style.display = 'block';

        // Group items by category
        var categories = {};
        items.forEach(function(item, idx) {
            if (!categories[item.category]) {
                categories[item.category] = [];
            }
            categories[item.category].push({ item: item, originalIndex: idx });
        });

        var currentItemIndex = 0;
        Object.keys(categories).forEach(function(cat) {
            var groupHeader = document.createElement('div');
            groupHeader.className = 'up-command-group-header';
            groupHeader.textContent = cat;
            resultsContainer.appendChild(groupHeader);

            categories[cat].forEach(function(entry) {
                var itm = entry.item;
                var el = document.createElement('div');
                el.className = 'up-command-item' + (currentItemIndex === activeIndex ? ' active' : '');
                el.dataset.index = currentItemIndex;

                var iconWrap = document.createElement('div');
                iconWrap.className = 'up-command-item-icon';
                iconWrap.innerHTML = '<i class="' + itm.icon + '"></i>';

                var contentWrap = document.createElement('div');
                contentWrap.className = 'up-command-item-content';

                var titleRow = document.createElement('div');
                titleRow.className = 'up-command-item-title';
                titleRow.textContent = itm.title;

                var descRow = document.createElement('div');
                descRow.className = 'up-command-item-desc';
                descRow.textContent = itm.desc;

                contentWrap.appendChild(titleRow);
                contentWrap.appendChild(descRow);

                el.appendChild(iconWrap);
                el.appendChild(contentWrap);

                if (itm.shortcut) {
                    var chip = document.createElement('div');
                    chip.className = 'up-command-item-badge';
                    chip.innerHTML = '<kbd>' + itm.shortcut + '</kbd>';
                    el.appendChild(chip);
                } else {
                    var jumpChip = document.createElement('div');
                    jumpChip.className = 'up-command-item-jump';
                    jumpChip.innerHTML = '<i class="fas fa-arrow-right"></i>';
                    el.appendChild(jumpChip);
                }

                // Click handler
                el.addEventListener('click', function() {
                    executeItem(itm);
                });

                // Mouseover
                el.addEventListener('mouseenter', function() {
                    setActiveIndex(parseInt(this.dataset.index, 10));
                });

                resultsContainer.appendChild(el);
                currentItemIndex++;
            });
        });

        scrollActiveIntoView();
    }

    function setActiveIndex(idx) {
        activeIndex = idx;
        var itemEls = resultsContainer.querySelectorAll('.up-command-item');
        itemEls.forEach(function(el, i) {
            if (i === activeIndex) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
    }

    function scrollActiveIntoView() {
        var activeEl = resultsContainer.querySelector('.up-command-item.active');
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    }

    function executeItem(itm) {
        if (!itm) return;
        closePalette();
        if (itm.action === 'toggleTheme') {
            window.UrbanPosTheme && window.UrbanPosTheme.toggle();
        } else if (itm.action === 'toggleDensity') {
            window.UrbanPosDensity && window.UrbanPosDensity.toggle();
        } else if (itm.action === 'openShortcuts') {
            if (typeof window.showPosKeyboardModal === 'function') {
                window.showPosKeyboardModal();
            } else {
                alert('Quick shortcuts:\n• Alt+S: New Sales Bill\n• Alt+P: POS Terminal / Purchase\n• Alt+T: Stock Transfer\n• Alt+C: Customers\n• Alt+I: Items Master\n• Ctrl+K: Spotlight Search');
            }
        } else if (itm.url) {
            window.location.href = itm.url;
        }
    }

    function filterItems(query) {
        var q = query.toLowerCase().trim();
        if (!q) {
            renderResults(COMMAND_ITEMS);
            return;
        }

        var results = COMMAND_ITEMS.filter(function(itm) {
            var fullSearchStr = (itm.title + ' ' + itm.desc + ' ' + itm.category + ' ' + (itm.keywords || '') + ' ' + (itm.shortcut || '')).toLowerCase();
            return fullSearchStr.indexOf(q) !== -1;
        });

        activeIndex = 0;
        renderResults(results);
    }

    // Open/Close
    window.openUrbanPosCommandPalette = function() {
        if (!backdrop) return;
        backdrop.style.display = 'flex';
        input.value = '';
        activeIndex = 0;
        renderResults(COMMAND_ITEMS);
        setTimeout(function() {
            input.focus();
        }, 50);
    };

    window.closeUrbanPosCommandPalette = function() {
        if (!backdrop) return;
        backdrop.style.display = 'none';
    };

    function closePalette() {
        window.closeUrbanPosCommandPalette();
    }

    // Keyboard navigation inside input & modal
    input.addEventListener('input', function() {
        filterItems(this.value);
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (filteredItems.length > 0) {
                var next = (activeIndex + 1) % filteredItems.length;
                setActiveIndex(next);
                scrollActiveIntoView();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (filteredItems.length > 0) {
                var prev = (activeIndex - 1 + filteredItems.length) % filteredItems.length;
                setActiveIndex(prev);
                scrollActiveIntoView();
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (filteredItems.length > 0 && filteredItems[activeIndex]) {
                executeItem(filteredItems[activeIndex]);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closePalette();
        }
    });

    // Close button
    if (closeBtn) {
        closeBtn.addEventListener('click', closePalette);
    }

    // Backdrop click outside
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) {
            closePalette();
        }
    });

    // Global Key Listener: Ctrl+K or Cmd+K
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            if (backdrop.style.display === 'none' || !backdrop.style.display) {
                window.openUrbanPosCommandPalette();
            } else {
                closePalette();
            }
        } else if (e.key === 'Escape' && backdrop.style.display !== 'none') {
            closePalette();
        }
    });

    // Wire up any trigger buttons with id or class
    document.addEventListener('DOMContentLoaded', function() {
        var triggers = document.querySelectorAll('#btn-open-command-palette, .btn-open-command-palette, #dashboardSearchInput');
        triggers.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.openUrbanPosCommandPalette();
            });
        });
    });
})();
</script>
