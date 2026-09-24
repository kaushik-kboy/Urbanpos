/**
 * UrbanPOS Real-Time Sequential Form Validator
 * 
 * Enforces immediate real-time validation on compulsory/required form fields:
 * - Validates instantaneously as users interact (input, change, select2:select, blur).
 * - Blocks progression to next fields (via Tab, Enter, or mouse click) until 
 *   current compulsory field validation rules pass.
 * - Deeply validates both Header Details and Line Item rows (item selection, qty, prices, expiry dates).
 * - Seamlessly integrates with standard inputs, selects, textareas, and Select2 containers.
 * - Safely permits cancellation, reset, and modal dismissals without locking.
 */
(function ($) {
    'use strict';

    // Animation helper
    function triggerShake($el) {
        if (!$el || !$el.length) return;
        $el.removeClass('up-field-shake');
        // Force reflow
        void $el[0].offsetWidth;
        $el.addClass('up-field-shake');
        setTimeout(function () {
            $el.removeClass('up-field-shake');
        }, 400);
    }

    // Resolve underlying form control if user interacted with Select2 or wrapped container
    function resolveControl($el) {
        if (!$el || !$el.length) return null;
        if ($el.is('.select2-selection') || $el.closest('.select2-selection').length) {
            const $container = $el.closest('.select2-container');
            const $select = $container.prev('select');
            if ($select.length) return $select;
        }
        if ($el.is('input, select, textarea')) {
            return $el;
        }
        const $nested = $el.find('input, select, textarea').first();
        if ($nested.length) return $nested;
        return $el;
    }

    // Get human readable field label
    function getFieldLabel($field) {
        const id = $field.attr('id');
        if (id) {
            const $label = $('label[for="' + id + '"]');
            if ($label.length) {
                return $label.text().replace('*', '').trim();
            }
        }
        const $parentGroup = $field.closest('.form-group, .field-wrapper');
        const $groupLabel = $parentGroup.find('label').first();
        if ($groupLabel.length) {
            return $groupLabel.text().replace('*', '').trim();
        }
        const placeholder = $field.attr('placeholder');
        if (placeholder) {
            return placeholder.replace('Select', '').replace('Enter', '').replace('e.g.', '').trim();
        }
        const title = $field.attr('title');
        if (title) {
            return title.replace('Enter', '').trim();
        }
        return $field.attr('name') || 'This field';
    }

    // Check if field is required
    function isFieldRequired($field) {
        if ($field.prop('disabled') || $field.prop('readonly')) {
            return false;
        }
        if ($field.is(':hidden') && !$field.hasClass('select2-hidden-accessible')) {
            return false;
        }
        if ($field.attr('tabindex') === '-1' && !$field.hasClass('select2-hidden-accessible')) {
            return false;
        }
        if ($field.is('[required]') || $field.prop('required') || $field.attr('aria-required') === 'true' || $field.data('required') === true || $field.data('required') === 1) {
            return true;
        }
        const $wrapper = $field.closest('.field-wrapper, .form-group');
        if ($wrapper.length && $wrapper.find('label .text-danger, label .required').length > 0) {
            return true;
        }
        return false;
    }

    // Check if element belongs to a table item row
    function isLineItemField($field) {
        return $field.closest('tr').length > 0 && (
            $field.closest('tbody').attr('id') === 'items-body' ||
            $field.closest('tbody').attr('id') === 'pinv-items-body' ||
            $field.closest('tbody').attr('id') === 'indent-items-body' ||
            $field.closest('tbody').attr('id') === 'grn-items-body' ||
            $field.closest('table').find('[name*="items["]').length > 0 ||
            $field.attr('name')?.includes('items[') ||
            $field.hasClass('item-code-input') ||
            $field.hasClass('pinv-item-code') ||
            $field.hasClass('po-item-code') ||
            $field.hasClass('sr-item-code') ||
            $field.hasClass('so-item-code') ||
            $field.hasClass('sq-item-code') ||
            $field.hasClass('indent-item-code')
        );
    }

    // Comprehensive validity evaluator
    function evaluateFieldValidity($field) {
        const field = $field[0];
        if (!field) return { valid: true };

        // 0. Active asynchronous duplicate error
        if ($field.data('has-duplicate-error')) {
            return { valid: false, message: $field.data('duplicate-error-message') || 'This value already exists.' };
        }

        let val = $field.val();

        // 1. Line Item Row specialized validation
        if (isLineItemField($field)) {
            const $row = $field.closest('tr');
            const isCodeInput = $field.hasClass('item-code-input') || 
                                $field.hasClass('pinv-item-code') || 
                                $field.hasClass('po-item-code') || 
                                $field.hasClass('sr-item-code') || 
                                $field.hasClass('so-item-code') || 
                                $field.hasClass('sq-item-code') || 
                                $field.hasClass('indent-item-code');

            const isQtyInput = $field.hasClass('pinv-qty') || 
                               $field.hasClass('po-qty') || 
                               $field.hasClass('item-qty') || 
                               $field.hasClass('qty-input') || 
                               $field.hasClass('sr-qty') || 
                               $field.hasClass('so-qty') || 
                               $field.hasClass('sq-qty') || 
                               $field.hasClass('row-received') || 
                               $field.hasClass('row-accepted') || 
                               ($field.attr('name') && $field.attr('name').includes('[qty]')) ||
                               ($field.attr('name') && $field.attr('name').includes('[requested_qty]'));

            const isCostInput = $field.hasClass('pinv-cost') || 
                                $field.hasClass('po-cost') || 
                                $field.hasClass('item-cost') || 
                                $field.hasClass('pr-cost') || 
                                $field.hasClass('row-cost') || 
                                ($field.attr('name') && $field.attr('name').includes('[cost_price]')) ||
                                ($field.attr('name') && $field.attr('name').includes('[unit_cost]'));

            const isSellInput = $field.hasClass('pinv-sell') || 
                                $field.hasClass('so-sell-price') || 
                                $field.hasClass('sq-sell-price') || 
                                ($field.attr('name') && $field.attr('name').includes('[sell_price]'));

            const isExpDateInput = $field.hasClass('pinv-exp-date') || 
                                   $field.hasClass('item-exp-date') || 
                                   $field.hasClass('pr-exp-date') || 
                                   $field.hasClass('sr-exp-date') || 
                                   ($field.attr('name') && $field.attr('name').includes('[exp_date]'));

            // Find item ID in this row
            const $itemIdEl = $row.find('input[name*="[item_id]"], select[name*="[item_id]"], .pinv-item-select, .po-item-select, .item-select, .item-id-hidden, .indent-item-select, .pr-item-id, .sr-item-select, .so-item-select, .sq-item-select');
            const hasItemId = $itemIdEl.length && ($itemIdEl.val() || '').trim() !== '';

            // A. Item Code / Selection: if row has entered code or has other filled values (qty, cost)
            if (isCodeInput) {
                const codeVal = (val || '').trim();
                if (codeVal !== '' && !hasItemId) {
                    return { valid: false, message: 'Please select a valid product for code: "' + codeVal + '"' };
                }
            }

            // B. Quantity validation: Quantity must be > 0 once item is selected or if required
            if (isQtyInput) {
                const numVal = parseFloat(val);
                if (hasItemId || isFieldRequired($field)) {
                    if (val === null || val === undefined || String(val).trim() === '') {
                        return { valid: false, message: 'Quantity is required and cannot be empty.' };
                    }
                    if (isNaN(numVal) || numVal <= 0) {
                        return { valid: false, message: 'Quantity must be greater than 0.' };
                    }
                }
            }

            // C. Cost Price validation (in Purchase Invoices, Purchase Orders, Opening Stocks, etc.)
            if (isCostInput && isFieldRequired($field)) {
                if (val === null || val === undefined || String(val).trim() === '') {
                    return { valid: false, message: 'Unit Cost Price is required.' };
                }
                const numCost = parseFloat(val);
                if (isNaN(numCost) || numCost < 0) {
                    return { valid: false, message: 'Cost price must be 0 or greater.' };
                }
            }

            // D. Selling Price validation (in Sales Orders, Quotations, Sales Bills)
            if (isSellInput && isFieldRequired($field)) {
                if (val === null || val === undefined || String(val).trim() === '') {
                    return { valid: false, message: 'Selling Price is required.' };
                }
                const numSell = parseFloat(val);
                if (isNaN(numSell) || numSell < 0) {
                    return { valid: false, message: 'Selling price must be 0 or greater.' };
                }
            }

            // E. Expiry Date validation
            if (isExpDateInput) {
                const isMandatory = $field.is('[required]') || 
                                    $field.hasClass('border-danger') || 
                                    $row.find('.pinv-exp-badge:not(.d-none)').length > 0;
                if (isMandatory && (!val || String(val).trim() === '')) {
                    return { valid: false, message: 'Expiry date is mandatory for this product.' };
                }
            }
        }

        const required = isFieldRequired($field);

        // 2. Check compulsory / required for Header or Standard fields
        if (required) {
            if ($field.is('select')) {
                if (val === null || val === undefined || String(val).trim() === '') {
                    const label = getFieldLabel($field);
                    return { valid: false, message: 'Please select ' + label + ' before proceeding.' };
                }
            } else if ($field.is(':checkbox')) {
                if (!$field.is(':checked')) {
                    return { valid: false, message: 'This checkbox is required.' };
                }
            } else if ($field.is(':radio')) {
                const name = $field.attr('name');
                if (!$('input[name="' + name + '"]:checked').length) {
                    return { valid: false, message: 'Please make a selection.' };
                }
            } else {
                if ($field.hasClass('datepicker') || $field.is('[type="date"]') || ($field.attr('name') && $field.attr('name').includes('date'))) {
                    if (typeof window.parseFastDate === 'function' && val) {
                        var fastFormatted = window.parseFastDate(val);
                        if (fastFormatted) {
                            if ($field.val() !== fastFormatted) {
                                $field.val(fastFormatted);
                            }
                            val = fastFormatted;
                        }
                    }
                }
                if (!val || String(val).trim() === '') {
                    const label = getFieldLabel($field);
                    return { valid: false, message: label + ' is compulsory and cannot be empty.' };
                }
            }
        }

        // 3. Block future date validation
        var maxAttr = $field.attr('max');
        var blockFuture = $field.data('block-future-date') || Boolean(maxAttr);
        if (blockFuture && val && String(val).trim() !== '') {
            var rawStr = String(val).trim();
            // Validate only if complete date parseable (avoids premature errors during numeric typing)
            var dateParts = typeof window.parseDateParts === 'function' ? window.parseDateParts(rawStr) : null;
            if (dateParts) {
                var pad = function (n) { return n < 10 ? '0' + n : String(n); };
                var inputIso = dateParts.year + '-' + pad(dateParts.month) + '-' + pad(dateParts.day);
                var now = new Date();
                var todayIso = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
                var maxIso = (maxAttr && /^\d{4}-\d{2}-\d{2}$/.test(maxAttr)) ? maxAttr : todayIso;

                if (inputIso > maxIso) {
                    return { valid: false, message: 'Future date is not allowed for this field.' };
                }
            }
        }

        // 4. HTML5 native constraint validation
        if (val && String(val).trim() !== '' && typeof field.checkValidity === 'function') {
            if (!field.checkValidity()) {
                const msg = field.validationMessage || 'Please enter a valid value.';
                return { valid: false, message: msg };
            }
        }

        // 5. Custom type validations
        if (val && String(val).trim() !== '') {
            const type = ($field.attr('type') || '').toLowerCase();
            if (type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(String(val).trim())) {
                    return { valid: false, message: 'Please enter a valid email address.' };
                }
            } else if (type === 'number') {
                const min = $field.attr('min');
                if (min !== undefined && parseFloat(val) < parseFloat(min)) {
                    return { valid: false, message: 'Value must be greater than or equal to ' + min + '.' };
                }
                const max = $field.attr('max');
                if (max !== undefined && parseFloat(val) > parseFloat(max)) {
                    return { valid: false, message: 'Value must not exceed ' + max + '.' };
                }
            }
        }

        return { valid: true };
    }

    // Display error state
    function showFieldError($field, message) {
        $field.addClass('is-invalid');

        // Select2 handling
        const $select2Selection = $field.next('.select2-container').find('.select2-selection');
        if ($select2Selection.length) {
            $select2Selection.addClass('is-invalid');
        }

        // Feedback element placement
        const $container = $field.closest('.input-group').length 
            ? $field.closest('.input-group') 
            : ($field.next('.select2-container').length ? $field.next('.select2-container') : $field);

        let $feedback = $container.siblings('.up-sequential-feedback');
        if (!$feedback.length) {
            $feedback = $field.closest('.form-group, .field-wrapper, td').find('.up-sequential-feedback');
        }

        if (!$feedback.length) {
            $feedback = $('<div class="invalid-feedback d-block up-sequential-feedback" style="font-size: 11px; font-weight: 600;"></div>');
            $container.after($feedback);
        }

        $feedback.html('<i class="fas fa-exclamation-circle mr-1"></i> ' + message).show();
        triggerShake($container.length ? $container : $field);
    }

    // Clear error state
    function clearFieldError($field) {
        $field.removeClass('is-invalid');
        $field.data('has-duplicate-error', false);
        $field.removeData('duplicate-error-message');

        const $select2Selection = $field.next('.select2-container').find('.select2-selection');
        if ($select2Selection.length) {
            $select2Selection.removeClass('is-invalid');
        }

        const $wrapper = $field.closest('.form-group, .field-wrapper, td');
        $wrapper.find('.up-sequential-feedback').remove();
    }

    // Asynchronous duplicate checker for unique fields (e.g. Inv No Supplier)
    let asyncCheckTimers = {};
    let activeXhrs = {};

    function checkAsyncDuplicate($field) {
        const checkUrl = $field.data('check-url');
        if (!checkUrl) return;

        const fieldName = $field.attr('name') || 'field';
        const val = ($field.val() || '').trim();
        if (!val) {
            $field.data('has-duplicate-error', false);
            return;
        }

        const $form = $field.closest('form');
        let data = {};
        data[fieldName] = val;

        if (fieldName === 'supplier_inv_no') {
            const supplierId = $form.find('[name="supplier_id"]').val();
            if (!supplierId) return; // Supplier not selected yet
            data.supplier_id = supplierId;
            const ignoreId = $field.data('invoice-id') || $form.find('[name="invoice_id"]').val();
            if (ignoreId) data.ignore_id = ignoreId;
        }

        if (activeXhrs[fieldName]) {
            try { activeXhrs[fieldName].abort(); } catch (e) {}
        }

        activeXhrs[fieldName] = $.ajax({
            url: checkUrl,
            method: 'GET',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res && res.is_duplicate) {
                    $field.data('has-duplicate-error', true);
                    $field.data('duplicate-error-message', res.message);
                    showFieldError($field, res.message);
                } else {
                    $field.data('has-duplicate-error', false);
                    $field.removeData('duplicate-error-message');
                    const stdRes = evaluateFieldValidity($field);
                    if (stdRes.valid) {
                        clearFieldError($field);
                    }
                }
            }
        });
    }

    // Check if target is a safe bypass (e.g. Cancel button, reset, close, modal, sidebar)
    function isSafeBypass(target) {
        if (!target) return false;
        const $t = $(target);
        if ($t.is('a') || $t.closest('a').length) return true;
        if ($t.is('button[type="reset"], .btn-reset-form, [data-dismiss], .close, .btn-secondary, .btn-default')) return true;
        if ($t.closest('[data-dismiss], .main-sidebar, .main-header, .modal, .dropdown-menu, .nav-tabs, .nav-pills, .open-item-modal, .pr-search-btn, #btn-add-row, #add-row, .row-remove, .pinv-remove-row, .po-remove-row, .sr-row-remove, .so-remove-row, .sq-remove-row, .remove-row-btn').length) return true;
        return false;
    }

    // Focus field smoothly (supports Select2)
    function focusField($field) {
        if (!$field || !$field.length) return;
        if ($field.hasClass('select2-hidden-accessible')) {
            try {
                $field.select2('open');
            } catch (e) {
                $field.next('.select2-container').find('.select2-selection').focus();
            }
        } else {
            $field.focus();
            if ($field.is('input:text, input[type="number"], input[type="date"]')) {
                try { $field.select(); } catch (e) {}
            }
        }
    }

    // Get all sequential fields in a form in DOM order
    function getFormFields($form) {
        return $form.find('input, select, textarea').filter(function () {
            const $el = $(this);
            if ($el.is(':hidden') && !$el.hasClass('select2-hidden-accessible')) return false;
            if ($el.prop('disabled') || $el.prop('readonly')) return false;
            if ($el.attr('tabindex') === '-1' && !$el.hasClass('select2-hidden-accessible')) return false;
            if ($el.is('button, [type="button"], [type="submit"], [type="reset"]')) return false;
            return true;
        });
    }

    $(document).ready(function () {
        // Track the current active input
        let currentActiveControl = null;
        let isRedirecting = false;

        function isManagedForm($form) {
            if (!$form || !$form.length) return false;
            if ($form.attr('method') && $form.attr('method').toUpperCase() === 'GET') return false;
            if ($form.hasClass('pos-scan-bar') || $form.hasClass('search-form')) return false;
            return true;
        }

        // Track focus in
        $(document).on('focusin', 'input, select, textarea, .select2-selection', function () {
            const $ctrl = resolveControl($(this));
            if ($ctrl && $ctrl.length) {
                currentActiveControl = $ctrl;
            }
        });

        // 1. Immediate validation on blur / focusout across all pages
        $(document).on('blur focusout', 'input, select, textarea', function () {
            const $field = $(this);
            const $form = $field.closest('form');
            if (!isManagedForm($form)) return;
            if ($field.is('button, [type="button"], [type="submit"], [type="reset"]')) return;

            const res = evaluateFieldValidity($field);
            if (!res.valid) {
                showFieldError($field, res.message);
            } else {
                if ($field.data('check-url')) {
                    checkAsyncDuplicate($field);
                } else {
                    clearFieldError($field);
                }
            }
        });

        // 2. Real-time typing / value change
        $(document).on('input keyup', 'input, textarea', function () {
            const $field = $(this);
            const $form = $field.closest('form');
            if (!isManagedForm($form)) return;

            const val = ($field.val() || '').trim();

            // If compulsory and user empties it, show immediate error
            if (isFieldRequired($field) && val === '') {
                showFieldError($field, getFieldLabel($field) + ' is compulsory and cannot be empty.');
                return;
            }

            // If field already has an error, re-evaluate to clear it immediately once valid
            if ($field.hasClass('is-invalid')) {
                const res = evaluateFieldValidity($field);
                if (res.valid) {
                    clearFieldError($field);
                }
            }

            // Debounced duplicate check while typing
            if ($field.data('check-url') && val !== '') {
                const fieldName = $field.attr('name') || 'field';
                clearTimeout(asyncCheckTimers[fieldName]);
                asyncCheckTimers[fieldName] = setTimeout(function () {
                    checkAsyncDuplicate($field);
                }, 400);
            }
        });

        // 3. Selection change integration (Select, Select2, Dates, Radios, Checkboxes)
        $(document).on('change select2:select select2:clear', 'select, input[type="date"], .datepicker, input[type="radio"], input[type="checkbox"]', function () {
            const $field = $(this);
            const $form = $field.closest('form');
            if (!isManagedForm($form)) return;

            const res = evaluateFieldValidity($field);
            if (!res.valid) {
                showFieldError($field, res.message);
            } else {
                clearFieldError($field);
            }

            // If supplier changes, re-check any supplier invoice number in this form
            if ($field.attr('name') === 'supplier_id') {
                const $suppInv = $form.find('[name="supplier_inv_no"]');
                if ($suppInv.length && $suppInv.val() && $suppInv.data('check-url')) {
                    checkAsyncDuplicate($suppInv);
                }
            }
        });

        // 4. PREVENT TAB / ENTER from jumping to next field if current field is invalid or compulsory empty
        $(document).on('keydown', 'input, select, textarea, .select2-selection', function (e) {
            const $current = resolveControl($(this));
            if (!$current || !$current.length) return;
            const $form = $current.closest('form');
            if (!isManagedForm($form)) return;

            // Check when attempting forward progression (Tab without Shift, or Enter in standard inputs)
            const isTabForward = (e.key === 'Tab' && !e.shiftKey);
            const isEnter = (e.key === 'Enter' && !$current.is('textarea, [type="submit"]'));

            if (isTabForward || isEnter) {
                const res = evaluateFieldValidity($current);
                if (!res.valid) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    showFieldError($current, res.message);
                    focusField($current);
                    return false;
                }
            }
        });

        // 5. PREVENT MOUSE CLICK AWAY if current active field is invalid or compulsory empty
        $(document).on('mousedown pointerdown', function (e) {
            if (isRedirecting) return;
            if (!currentActiveControl || !currentActiveControl.length) return;

            const target = e.target;
            if (isSafeBypass(target)) {
                return; // Allow safe bypass buttons (Cancel, Reset, modal close, navigation)
            }

            const $targetEl = $(target);

            // If click is within the current control's container, datepicker, or select2 dropdown, allow it
            if ($targetEl.closest(currentActiveControl).length || 
                $targetEl.closest(currentActiveControl.next('.select2-container')).length ||
                $targetEl.closest('.select2-dropdown').length ||
                $targetEl.closest('.ui-datepicker, .flatpickr-calendar, .bootstrap-datetimepicker-widget').length) {
                return;
            }

            const $form = currentActiveControl.closest('form');
            if (!isManagedForm($form)) return;

            // Evaluate current active field before allowing mouse click to shift focus
            const res = evaluateFieldValidity(currentActiveControl);
            if (!res.valid) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                showFieldError(currentActiveControl, res.message);
                isRedirecting = true;
                setTimeout(function () {
                    focusField(currentActiveControl);
                    isRedirecting = false;
                }, 50);

                return false;
            }
        });

        // 6. Form Submit verification - validate entire sequence before allowing submit
        $(document).on('submit', 'form', function (e) {
            const $form = $(this);
            if (!isManagedForm($form)) return;

            const fields = getFormFields($form);
            let firstInvalid = null;

            fields.each(function () {
                const $f = $(this);
                const res = evaluateFieldValidity($f);
                if (!res.valid) {
                    showFieldError($f, res.message);
                    if (!firstInvalid) {
                        firstInvalid = $f;
                    }
                }
            });

            if (firstInvalid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                focusField(firstInvalid);

                // Reset any submit loader buttons if triggered
                const $submitBtn = $form.find('button[type="submit"]');
                $submitBtn.prop('disabled', false).html($submitBtn.data('original-text') || 'Save');
                return false;
            }
        });
    });

})(jQuery);
