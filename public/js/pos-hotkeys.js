/**
 * UrbanPOS Global Keyboard Navigation & Hotkeys Engine
 * Enterprise ERP / Marg / Tally Style Mouse-Free POS Operation
 */
(function () {
    'use strict';

    // Normalize shortcut string from event: e.g. "Alt+S", "Ctrl+Shift+P", "F2", "F6"
    function getEventShortcutString(e) {
        let parts = [];
        if (e.ctrlKey) parts.push('Ctrl');
        if (e.altKey) parts.push('Alt');
        if (e.shiftKey) parts.push('Shift');

        let key = e.key;

        // Ignore modifier keys alone
        if (['Control', 'Alt', 'Shift', 'Meta'].includes(key)) {
            return null;
        }

        // Function keys (F1 - F12)
        if (/^F\d{1,2}$/i.test(key)) {
            return key.toUpperCase();
        }

        // Single alphanumeric characters
        if (key.length === 1) {
            parts.push(key.toUpperCase());
            return parts.join('+');
        }

        // Special keys
        if (['Escape', 'Enter', 'Delete', 'Backspace', 'ArrowUp', 'ArrowDown'].includes(key)) {
            parts.push(key);
            return parts.join('+');
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

    // Handle POS Form Actions (F2, F3, F6, etc.)
    function executeFormAction(actionKey, e) {
        let $ = window.jQuery;

        switch (actionKey) {
            case 'search_item': {
                // Check if search modal is already open
                let $openModal = $('.modal.show');
                if ($openModal.length) {
                    let $searchInput = $openModal.find('input[type="text"]:visible').first();
                    if ($searchInput.length) {
                        $searchInput.focus().select();
                    }
                    return;
                }

                // Trigger item search in active or last row
                let $targetInput = null;
                // Sales Bill
                if ($('.sb-item-code').length) {
                    $targetInput = $(':focus').hasClass('sb-item-code') ? $(':focus') : $('.sb-item-code').filter(function() { return !$(this).val(); }).first();
                    if (!$targetInput.length) $targetInput = $('.sb-item-code').last();
                }
                // Purchase Invoice
                else if ($('.pinv-item-code').length) {
                    $targetInput = $(':focus').hasClass('pinv-item-code') ? $(':focus') : $('.pinv-item-code').filter(function() { return !$(this).val(); }).first();
                    if (!$targetInput.length) $targetInput = $('.pinv-item-code').last();
                }
                // Stock Transfer
                else if ($('.st-item-code').length) {
                    $targetInput = $(':focus').hasClass('st-item-code') ? $(':focus') : $('.st-item-code').filter(function() { return !$(this).val(); }).first();
                    if (!$targetInput.length) $targetInput = $('.st-item-code').last();
                }

                if ($targetInput && $targetInput.length) {
                    $targetInput.trigger('focus').trigger('click');
                }
                break;
            }

            case 'new_entry': {
                // Add new row to current form
                let $addRowBtn = $('#sb-add-row, #pinv-add-row, #st-add-row, #sq-add-row, #so-add-row, #sr-add-row, [data-action="add-row"]').filter(':visible').first();
                if ($addRowBtn.length) {
                    $addRowBtn.trigger('click');
                    setTimeout(function () {
                        let $newCode = $('.sb-item-code, .pinv-item-code, .st-item-code').last();
                        if ($newCode.length) {
                            $newCode.focus();
                        }
                    }, 100);
                }
                break;
            }

            case 'edit_entry': {
                // Focus quantity or rate field of active row
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
                // Save and Tender / Submit Form
                let $tenderBtn = $('#btn-tender-save, #btn-tender, #btn-quick-tender').filter(':visible');
                if ($tenderBtn.length) {
                    $tenderBtn.trigger('click');
                    return;
                }

                let $submitBtn = $('button[type="submit"]:visible, .btn-save:visible').first();
                if ($submitBtn.length) {
                    $submitBtn.trigger('click');
                }
                break;
            }

            case 'view_records': {
                // View history / index listing
                let $indexLink = $('a[href*="/sales-bills"], a[href*="/purchase-invoices"], a[href*="/stock-transfers"], .btn-view-records').filter(function() {
                    let href = $(this).attr('href') || '';
                    return href.endsWith('/sales-bills') || href.endsWith('/purchase-invoices') || href.endsWith('/stock-transfers');
                }).first();

                if ($indexLink.length) {
                    window.location.href = $indexLink.attr('href');
                }
                break;
            }

            case 'print_form': {
                // Print active voucher / bill
                let $printBtn = $('.btn-print, [data-action="print"], a[href*="print"]').filter(':visible').first();
                if ($printBtn.length) {
                    $printBtn.trigger('click');
                } else {
                    window.print();
                }
                break;
            }

            case 'clear_form': {
                // Reset form
                let $resetBtn = $('.btn-reset-form, [type="reset"]').filter(':visible').first();
                if ($resetBtn.length) {
                    if (confirm('Are you sure you want to reset this form? All unsaved data will be cleared.')) {
                        $resetBtn.trigger('click');
                    }
                }
                break;
            }

            case 'close_modal': {
                // Close open modal or go back
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
        }
    }

    // Global Keydown Handler (Capturing phase for maximum reliability in Chrome)
    window.addEventListener('keydown', function (e) {
        let combo = getEventShortcutString(e);
        if (!combo) return;

        let lookup = getActiveMappingLookup();
        let target = lookup[combo];

        // 1. Function Keys (F1 - F12) interception
        if (/^F\d{1,2}$/i.test(combo)) {
            // Suppress browser default for function keys (e.g. F3 search, F6 address bar)
            e.preventDefault();
            e.stopPropagation();

            if (target && target.action_key) {
                executeFormAction(target.action_key, e);
            }
            return;
        }

        // 2. Escape Key (Close Modal / Back)
        if (combo === 'Escape') {
            let $ = window.jQuery;
            if ($ && $('.modal.show').length) {
                e.preventDefault();
                e.stopPropagation();
                $('.modal.show').modal('hide');
                return;
            }
        }

        // 3. Delete Row via Shift+Delete or Alt+Delete
        if (combo === 'Shift+Delete' || combo === 'Alt+Delete') {
            let $ = window.jQuery;
            if ($) {
                let $activeRow = $(':focus').closest('tr');
                if ($activeRow.length) {
                    let $removeBtn = $activeRow.find('.sb-remove-row, .pinv-remove-row, .st-remove-row, .btn-remove-row');
                    if ($removeBtn.length) {
                        e.preventDefault();
                        $removeBtn.trigger('click');
                        return;
                    }
                }
            }
        }

        // 4. Global Navigation Shortcuts (Alt + Key or Ctrl + Shift + Key)
        if (target && target.target_url) {
            e.preventDefault();
            e.stopPropagation();

            let appBase = (window.APP_URL || '').replace(/\/+$/, '');
            let dest = target.target_url.replace(/^\/+/, '');
            let finalUrl = appBase ? appBase + '/' + dest : '/' + dest;

            window.location.href = finalUrl;
        }
    }, true);

    // =========================================================================
    // Tab-less Enter Key Billing Navigation (Pure Enter Chain)
    // Barcode -> Enter -> Qty -> Enter -> Disc % -> Enter -> Auto New Row & Focus
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        let $ = window.jQuery;
        if (!$) return;

        // Sales Bills Enter-chaining
        $(document).on('keydown', '#sb-items-body input', function (e) {
            if (e.key !== 'Enter') return;
            // Ignore enter if search modal is open
            if ($('#sb-item-search-modal').hasClass('show')) return;

            let $input = $(this);
            let $row = $input.closest('tr');

            // From Item Code -> Qty (if not empty)
            if ($input.hasClass('sb-item-code')) {
                // If item is already selected, advance to qty
                if ($row.find('.sb-item-select').val()) {
                    e.preventDefault();
                    $row.find('.sb-qty').focus().select();
                }
            }
            // From Qty -> Disc % (or Disc Amt)
            else if ($input.hasClass('sb-qty')) {
                e.preventDefault();
                let $disc = $row.find('.sb-disc-percent');
                if ($disc.length) {
                    $disc.focus().select();
                }
            }
            // From Disc % -> Disc Amt or New Row
            else if ($input.hasClass('sb-disc-percent')) {
                e.preventDefault();
                let $discAmt = $row.find('.sb-disc-amount');
                if ($discAmt.length) {
                    $discAmt.focus().select();
                } else {
                    advanceToNextRowOrAdd($row, 'sales');
                }
            }
            // From Disc Amt -> Next Row or Add Row
            else if ($input.hasClass('sb-disc-amount')) {
                e.preventDefault();
                advanceToNextRowOrAdd($row, 'sales');
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
                    advanceToNextRowOrAdd($row, 'purchase');
                }
            } else if ($input.hasClass('pinv-disc-percent') || $input.hasClass('pinv-disc-amount')) {
                e.preventDefault();
                advanceToNextRowOrAdd($row, 'purchase');
            }
        });

        function advanceToNextRowOrAdd($currentRow, moduleType) {
            let $nextRow = $currentRow.next('tr');

            if ($nextRow.length) {
                let $code = $nextRow.find('.sb-item-code, .pinv-item-code');
                if ($code.length) {
                    $code.focus().select();
                    return;
                }
            }

            // If no next row, trigger Add Row button
            let $addBtn = moduleType === 'sales' ? $('#sb-add-row') : $('#pinv-add-row');
            if ($addBtn.length) {
                $addBtn.trigger('click');
                setTimeout(function () {
                    let $newRow = moduleType === 'sales' ? $('#sb-items-body tr:last-child') : $('#pinv-items-body tr:last-child');
                    let $code = $newRow.find('.sb-item-code, .pinv-item-code');
                    if ($code.length) {
                        $code.focus().select();
                    }
                }, 80);
            }
        }
    });

})();
