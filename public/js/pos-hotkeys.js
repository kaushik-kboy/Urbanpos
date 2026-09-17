/**
 * UrbanPOS Global Keyboard Navigation & Hotkeys Engine
 * Enterprise ERP / Marg / Tally Style Mouse-Free POS Operation
 */
(function () {
    'use strict';

    // Normalize shortcut string from event: e.g. "ALT+S", "CTRL+SHIFT+P", "F2", "F6"
    function getNormalizedKey(e) {
        let key = e.key || '';
        let code = e.code || '';
        let alt = e.altKey;
        let ctrl = e.ctrlKey;
        let shift = e.shiftKey;

        // Function keys (F1 - F12)
        if (/^F\d{1,2}$/i.test(key)) {
            return key.toUpperCase();
        }

        // Single letter keys with Alt or Ctrl+Shift
        if (alt || (ctrl && shift)) {
            let parts = [];
            if (ctrl) parts.push('CTRL');
            if (alt) parts.push('ALT');
            if (shift) parts.push('SHIFT');

            // Key character (e.g. 's' -> 'S')
            if (key && key.length === 1 && key !== ' ') {
                parts.push(key.toUpperCase());
                return parts.join('+');
            }

            // Fallback to e.code (e.g. KeyS -> 'S', KeyP -> 'P')
            if (code && code.startsWith('Key')) {
                parts.push(code.replace('Key', '').toUpperCase());
                return parts.join('+');
            }
        }

        // Escape Key
        if (key === 'Escape' || code === 'Escape') {
            return 'ESCAPE';
        }

        // Shift+Delete or Alt+Delete
        if ((shift || alt) && (key === 'Delete' || code === 'Delete' || key === 'Del')) {
            return (shift ? 'SHIFT' : 'ALT') + '+DELETE';
        }

        return null;
    }

    // Default built-in ERP shortcuts fallback
    const DEFAULT_ACTIONS = {
        'ALT+S': { action_key: 'open_sales_bill', target_url: 'sales/sales-bills/create' },
        'ALT+P': { action_key: 'open_purchase_invoice', target_url: 'purchase/purchase-invoices/create' },
        'ALT+T': { action_key: 'open_stock_transfer', target_url: 'inventory/stock-transfers/create' },
        'ALT+C': { action_key: 'open_customer_master', target_url: 'master/customers' },
        'ALT+I': { action_key: 'open_item_master', target_url: 'master/items' },
        'ALT+O': { action_key: 'open_purchase_order', target_url: 'purchase/purchase-orders/create' },
        'ALT+Q': { action_key: 'open_sales_quotation', target_url: 'sales/sales-quotations/create' },
        'ALT+R': { action_key: 'open_sales_return', target_url: 'sales/sales-returns/create' },
        'CTRL+SHIFT+S': { action_key: 'open_sales_bill', target_url: 'sales/sales-bills/create' },
        'CTRL+SHIFT+P': { action_key: 'open_purchase_invoice', target_url: 'purchase/purchase-invoices/create' },
        'CTRL+SHIFT+T': { action_key: 'open_stock_transfer', target_url: 'inventory/stock-transfers/create' },
        'F2': { action_key: 'search_item' },
        'F3': { action_key: 'new_entry' },
        'F4': { action_key: 'edit_entry' },
        'F6': { action_key: 'save_form' },
        'F7': { action_key: 'view_records' },
        'F8': { action_key: 'print_form' },
        'F9': { action_key: 'clear_form' },
        'F10': { action_key: 'close_modal' },
    };

    // Build lookup table from window.POS_HOTKEYS or fallback
    function getActiveMappingLookup() {
        let lookup = {};
        let mappings = window.POS_HOTKEYS || [];

        if (Array.isArray(mappings) && mappings.length > 0) {
            mappings.forEach(function (item) {
                if (item.is_enabled && item.shortcut_combination) {
                    let combo = item.shortcut_combination.toUpperCase().replace(/\s+/g, '');
                    lookup[combo] = item;
                }
            });
        }

        // Merge defaults if not overridden
        for (let combo in DEFAULT_ACTIONS) {
            if (!lookup[combo]) {
                lookup[combo] = DEFAULT_ACTIONS[combo];
            }
        }

        return lookup;
    }

    function navigateTo(targetUrl) {
        if (!targetUrl) return;
        let appBase = (window.APP_URL || window.location.origin).replace(/\/+$/, '');
        let dest = targetUrl.replace(/^\/+/, '');
        let finalUrl = appBase + '/' + dest;
        window.location.assign(finalUrl);
    }

    // Form / Billing Actions Execution Engine
    window.posTriggerAction = function (actionKey) {
        let $ = window.jQuery;
        if (!$) return;

        switch (actionKey) {
            case 'search_item': {
                // 1. If search modal is already open, focus its search input
                let $openModal = $('.modal.show');
                if ($openModal.length) {
                    let $searchInput = $openModal.find('#isl-filter-name, #pinv-isl-filter-name, #st-isl-filter-name, input[type="text"]:visible').first();
                    if ($searchInput.length) {
                        $searchInput.focus().select();
                        return;
                    }
                }

                // 2. Open item lookup modal on the active or last item row
                let $targetInput = null;
                if ($('.sb-item-code').length) {
                    $targetInput = $(':focus').hasClass('sb-item-code') ? $(':focus') : $('.sb-item-code').filter(function () { return !$(this).val(); }).first();
                    if (!$targetInput.length) $targetInput = $('.sb-item-code').last();
                } else if ($('.pinv-item-code').length) {
                    $targetInput = $(':focus').hasClass('pinv-item-code') ? $(':focus') : $('.pinv-item-code').filter(function () { return !$(this).val(); }).first();
                    if (!$targetInput.length) $targetInput = $('.pinv-item-code').last();
                } else if ($('.st-item-code, .item-code-input').length) {
                    $targetInput = $(':focus').hasClass('st-item-code') ? $(':focus') : $('.st-item-code, .item-code-input').last();
                } else if ($('.sq-item-code').length) {
                    $targetInput = $('.sq-item-code').last();
                } else if ($('.so-item-code').length) {
                    $targetInput = $('.so-item-code').last();
                } else if ($('.sr-item-code').length) {
                    $targetInput = $('.sr-item-code').last();
                }

                if ($targetInput && $targetInput.length) {
                    $targetInput.trigger('click');
                } else {
                    navigateTo('master/items');
                }
                break;
            }

            case 'new_entry': {
                // Add new row when explicitly requested via F3 or button
                let $addRowBtn = $('#sb-add-row, #pinv-add-row, #st-add-row, #sq-add-row, #so-add-row, #sr-add-row, #add-row, [data-action="add-row"]').filter(':visible').first();
                if ($addRowBtn.length) {
                    $addRowBtn.trigger('click');
                    setTimeout(function () {
                        let $newCode = $('.sb-item-code, .pinv-item-code, .st-item-code, .item-code-input').last();
                        if ($newCode.length) {
                            $newCode.focus();
                        }
                    }, 80);
                    return;
                }

                // If on a listing table page, find "Create / Add New" button
                let $createBtn = $('a[href$="/create"], .btn-primary:contains("Add"), .btn-primary:contains("New"), .btn-primary:contains("Create")').filter(':visible').first();
                if ($createBtn.length && $createBtn.attr('href')) {
                    window.location.href = $createBtn.attr('href');
                    return;
                }

                // Default: open Sales Bill
                navigateTo('sales/sales-bills/create');
                break;
            }

            case 'edit_entry': {
                let $focused = $(':focus');
                let $row = $focused.closest('tr');
                if ($row.length) {
                    let $qty = $row.find('.sb-qty, .pinv-qty, .st-qty, input[name*="[qty]"]').first();
                    if ($qty.length) {
                        $qty.focus().select();
                    }
                }
                break;
            }

            case 'save_form': {
                let $tenderBtn = $('#btn-tender-save, #btn-tender, #btn-quick-tender').filter(':visible');
                if ($tenderBtn.length) {
                    $tenderBtn.trigger('click');
                    return;
                }

                let $submitBtn = $('button[type="submit"]:visible, .btn-save:visible, form .card-footer .btn-primary:visible').first();
                if ($submitBtn.length) {
                    $submitBtn.trigger('click');
                }
                break;
            }

            case 'view_records': {
                let loc = window.location.pathname;
                if (loc.includes('/sales-bills')) {
                    navigateTo('sales/sales-bills');
                } else if (loc.includes('/purchase-invoices')) {
                    navigateTo('purchase/purchase-invoices');
                } else if (loc.includes('/stock-transfers')) {
                    navigateTo('inventory/stock-transfers');
                } else {
                    let $indexLink = $('a[href*="/sales-bills"], a[href*="/purchase-invoices"], a[href*="/stock-transfers"]').first();
                    if ($indexLink.length) {
                        window.location.href = $indexLink.attr('href');
                    }
                }
                break;
            }

            case 'print_form': {
                let $printBtn = $('.btn-print, [data-action="print"], a[href*="print"]').filter(':visible').first();
                if ($printBtn.length) {
                    $printBtn.trigger('click');
                } else {
                    window.print();
                }
                break;
            }

            case 'clear_form': {
                let $resetBtn = $('.btn-reset-form, [type="reset"]').filter(':visible').first();
                if ($resetBtn.length) {
                    if (confirm('Are you sure you want to reset this form? All unsaved data will be cleared.')) {
                        $resetBtn.trigger('click');
                    }
                }
                break;
            }

            case 'close_modal': {
                let $openModal = $('.modal.show');
                if ($openModal.length) {
                    $openModal.modal('hide');
                } else {
                    let $backBtn = $('.btn-back-to-bill, .btn-back, a.btn-default').filter(':visible').first();
                    if ($backBtn.length && $backBtn.attr('href')) {
                        window.location.href = $backBtn.attr('href');
                    } else if (window.history.length > 1) {
                        window.history.back();
                    }
                }
                break;
            }

            // Global navigation keys mapped directly
            case 'open_sales_bill': navigateTo('sales/sales-bills/create'); break;
            case 'open_purchase_invoice': navigateTo('purchase/purchase-invoices/create'); break;
            case 'open_stock_transfer': navigateTo('inventory/stock-transfers/create'); break;
            case 'open_customer_master': navigateTo('master/customers'); break;
            case 'open_item_master': navigateTo('master/items'); break;
            case 'open_purchase_order': navigateTo('purchase/purchase-orders/create'); break;
            case 'open_sales_quotation': navigateTo('sales/sales-quotations/create'); break;
            case 'open_sales_return': navigateTo('sales/sales-returns/create'); break;
        }
    };

    // Global Keydown Handler (Capturing phase for 100% interception in Chrome)
    window.addEventListener('keydown', function (e) {
        let keyCombo = getNormalizedKey(e);
        if (!keyCombo) return;

        let lookup = getActiveMappingLookup();
        let target = lookup[keyCombo];

        // 1. Function Keys (F1 - F12)
        if (/^F\d{1,2}$/.test(keyCombo)) {
            e.preventDefault();
            e.stopImmediatePropagation();

            let action = target ? target.action_key : (DEFAULT_ACTIONS[keyCombo] ? DEFAULT_ACTIONS[keyCombo].action_key : null);
            if (action) {
                window.posTriggerAction(action);
            }
            return;
        }

        // 2. Escape Key (Close Modal / Back)
        if (keyCombo === 'ESCAPE') {
            let $ = window.jQuery;
            if ($ && $('.modal.show').length) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $('.modal.show').modal('hide');
                return;
            }
        }

        // 3. Delete Row via Shift+Delete or Alt+Delete
        if (keyCombo === 'SHIFT+DELETE' || keyCombo === 'ALT+DELETE') {
            let $ = window.jQuery;
            if ($) {
                let $activeRow = $(':focus').closest('tr');
                if ($activeRow.length) {
                    let $removeBtn = $activeRow.find('.sb-remove-row, .pinv-remove-row, .st-remove-row, .btn-remove-row');
                    if ($removeBtn.length) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        $removeBtn.trigger('click');
                        return;
                    }
                }
            }
        }

        // 4. Global Navigation Shortcuts (Alt + Key, Ctrl + Shift + Key)
        if (target && target.target_url) {
            e.preventDefault();
            e.stopImmediatePropagation();
            navigateTo(target.target_url);
            return;
        }

        // Direct fallback navigation
        if (DEFAULT_ACTIONS[keyCombo] && DEFAULT_ACTIONS[keyCombo].target_url) {
            e.preventDefault();
            e.stopImmediatePropagation();
            navigateTo(DEFAULT_ACTIONS[keyCombo].target_url);
            return;
        }
    }, true);

    // =========================================================================
    // Tab-less Enter Key Navigation inside billing rows
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        let $ = window.jQuery;
        if (!$) return;

        // Sales Bills Enter-chaining (focus only next field; NEVER auto-add empty row or force modal)
        $(document).on('keydown', '#sb-items-body input', function (e) {
            if (e.key !== 'Enter') return;
            if ($('#sb-item-search-modal').hasClass('show')) return;

            let $input = $(this);
            let $row = $input.closest('tr');

            if ($input.hasClass('sb-item-code')) {
                if ($row.find('.sb-item-select').val()) {
                    e.preventDefault();
                    $row.find('.sb-qty').focus().select();
                }
            } else if ($input.hasClass('sb-qty')) {
                e.preventDefault();
                let $disc = $row.find('.sb-disc-percent');
                if ($disc.length) {
                    $disc.focus().select();
                }
            } else if ($input.hasClass('sb-disc-percent')) {
                e.preventDefault();
                let $discAmt = $row.find('.sb-disc-amount');
                if ($discAmt.length) {
                    $discAmt.focus().select();
                } else {
                    advanceToNextExistingRow($row);
                }
            } else if ($input.hasClass('sb-disc-amount')) {
                e.preventDefault();
                advanceToNextExistingRow($row);
            }
        });

        // Purchase Invoice Enter-chaining
        $(document).on('keydown', '#pinv-items-body input', function (e) {
            if (e.key !== 'Enter') return;
            if ($('#pinv-item-search-modal').hasClass('show')) return;

            let $input = $(this);
            let $row = $input.closest('tr');

            if ($input.hasClass('pinv-item-code')) {
                if ($row.find('.pinv-item-select').val()) {
                    e.preventDefault();
                    let $exp = $row.find('.pinv-exp-date');
                    if ($exp.length && $exp.is(':visible') && !$exp.prop('readonly')) {
                        $exp.focus();
                    } else {
                        $row.find('.pinv-qty').focus().select();
                    }
                }
            } else if ($input.hasClass('pinv-exp-date')) {
                e.preventDefault();
                $row.find('.pinv-qty').focus().select();
            } else if ($input.hasClass('pinv-qty')) {
                e.preventDefault();
                let $cost = $row.find('.pinv-cost');
                if ($cost.length) {
                    $cost.focus().select();
                }
            } else if ($input.hasClass('pinv-cost')) {
                e.preventDefault();
                let $disc = $row.find('.pinv-disc-percent');
                if ($disc.length) {
                    $disc.focus().select();
                } else {
                    advanceToNextExistingRow($row);
                }
            } else if ($input.hasClass('pinv-disc-percent') || $input.hasClass('pinv-disc-amount')) {
                e.preventDefault();
                advanceToNextExistingRow($row);
            }
        });

        // Advance to next row ONLY if it already exists; otherwise advance to freight or totals
        function advanceToNextExistingRow($currentRow) {
            let $nextRow = $currentRow.next('tr');
            if ($nextRow.length) {
                let $code = $nextRow.find('.sb-item-code, .pinv-item-code, .item-code-input');
                if ($code.length) {
                    $code.focus();
                    return;
                }
            }
            let $freight = $('#freight');
            if ($freight.length) {
                $freight.focus().select();
            }
        }

        // Global smart autofocus: focus first editable input of the form when page loads
        setTimeout(function () {
            if (document.activeElement && document.activeElement !== document.body && $(document.activeElement).is('input, select, textarea')) {
                return;
            }

            let $form = $('form.card-body, .card form, form').first();
            if ($form.length) {
                let $target = $form.find('input:not([type=hidden]):not([readonly]):not([disabled]):visible, select:not([disabled]):visible')
                    .filter(function () {
                        return !$(this).closest('.navbar, .main-header, .sidebar, .pos-keyboard-bar, .modal').length;
                    }).first();

                if ($target.length) {
                    $target.focus();
                }
            }
        }, 150);
    });

})();
