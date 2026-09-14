$(document).ready(function () {
    if (typeof $.fn.select2 === 'undefined') {
        return;
    }

    function initSelect2(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find('select.select2').each(function () {
            var $this = $(this);
            if (!$this.hasClass('select2-hidden-accessible')) {
                var placeholderText = $this.attr('placeholder') || 
                                     $this.data('placeholder') || 
                                     $this.find('option[value=""]').text() || 
                                     '-- Select --';
                
                var hasEmptyOption = Boolean($this.find('option[value=""]').length);

                $this.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: placeholderText,
                    allowClear: hasEmptyOption && !$this.prop('multiple')
                });
            }
        });
    }

    initSelect2();

    // Re-initialize when Bootstrap modals are opened
    $(document).on('shown.bs.modal', function () {
        initSelect2(this);
    });

    // Global custom event for dynamically added rows or AJAX content
    $(document).on('reinit:select2', function (e, target) {
        initSelect2(target || document);
    });

    // =========================================================================
    // INSTANT SELECT2 OPEN: Single Mouse Click, Tab Key Focus, or Enter Key
    // Works for both Single-select and Multi-value Select boxes
    // Fixes the 2-click issue so the dropdown opens on the 1st click
    // =========================================================================
    var isSelect2Closing = false;
    var closingTimer = null;
    var justOpened = false;
    var justOpenedTimer = null;

    function setClosingLatch() {
        isSelect2Closing = true;
        if (closingTimer) clearTimeout(closingTimer);
        closingTimer = setTimeout(function () {
            isSelect2Closing = false;
        }, 250);
    }

    $(document).on('select2:closing', function () {
        setClosingLatch();
    });

    $(document).on('select2:close', function () {
        setClosingLatch();
    });

    $(document).on('select2:open', function () {
        justOpened = true;
        if (justOpenedTimer) clearTimeout(justOpenedTimer);
        justOpenedTimer = setTimeout(function () {
            justOpened = false;
        }, 300);
    });

    // CRITICAL: Capturing click listener prevents Select2 from toggling closed
    // on the immediate click event that follows mousedown/focus opening!
    document.addEventListener('click', function (e) {
        if (justOpened) {
            var $target = $(e.target).closest('.select2-selection');
            if ($target.length && !$(e.target).is('.select2-selection__choice__remove, .select2-selection__clear')) {
                e.stopImmediatePropagation();
            }
        }
    }, true);

    // 1. Single click on mouse: Opens immediately on the very first click
    $(document).on('mousedown', '.select2-container', function (e) {
        if (isSelect2Closing) return;
        // Do not force-open if clicking remove tag on multi-select or clear button
        if ($(e.target).closest('.select2-selection__choice__remove, .select2-selection__clear').length) {
            return;
        }
        var $select = $(this).prev('select.select2');
        if ($select.length && $select.data('select2') && !$select.data('select2').isOpen()) {
            setTimeout(function () {
                if (!$select.data('select2').isOpen()) {
                    $select.select2('open');
                }
            }, 0);
        }
    });

    // 2. Tab key navigation: Opens automatically as soon as focus lands on Select2 (single or multi)
    $(document).on('focus', '.select2-selection, .select2-container .select2-search__field', function (e) {
        if (isSelect2Closing) return;
        var $container = $(this).closest('.select2-container');
        var $select = $container.prev('select.select2');
        if ($select.length && $select.data('select2') && !$select.data('select2').isOpen()) {
            $select.select2('open');
        }
    });

    // 3. Enter key on Select2: Opens the dropdown if closed
    $(document).on('keydown', '.select2-selection, .select2-container .select2-search__field', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            var $container = $(this).closest('.select2-container');
            var $select = $container.prev('select.select2');
            if ($select.length && $select.data('select2')) {
                if (!$select.data('select2').isOpen()) {
                    e.preventDefault();
                    e.stopPropagation();
                    $select.select2('open');
                    return false;
                }
            }
        }
    });

    // 4. On selection: smoothly move focus to next input field in row (for single-selects only)
    $(document).on('select2:select', function (e) {
        var $select = $(this);
        // If it's a multi-select box, keep focus inside for user to add more values
        if ($select.prop('multiple')) {
            return;
        }
        setTimeout(function () {
            var $row = $select.closest('tr');
            if ($row.length) {
                var $next = $row.find('.pinv-exp-date, .pinv-qty, input:not([readonly]):not([type="hidden"])').filter(':visible').first();
                if ($next.length) {
                    $next.focus();
                }
            }
        }, 50);
    });

    // Ensure Select2 dropdown cleanly closes and passes focus on Tab
    $(document).on('keydown', '.select2-search__field, .select2-selection', function (e) {
        if (e.key === 'Tab' || e.keyCode === 9) {
            var $openSelect = $('select.select2').filter(function () {
                return $(this).data('select2') && $(this).data('select2').isOpen();
            });
            if ($openSelect.length) {
                $openSelect.select2('close');
            }
        }
    });

    // =========================================================================
    // GLOBAL ENTER KEY PREVENTION: Prevent accidental form submission on Enter
    // =========================================================================
    $(document).on('keydown', 'form input:not([type="submit"]):not([type="button"]):not([type="reset"]):not(.select2-search__field)', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            var $form = $(this).closest('form');
            var method = ($form.attr('method') || 'GET').toUpperCase();

            // Allow Enter to trigger search ONLY on GET filter forms
            if (method === 'GET') {
                return true;
            }

            // On all Create / Edit / Data-entry forms: prevent accidental submit!
            e.preventDefault();
            return false;
        }
    });

    // =========================================================================
    // REMOVE NUMBER SPINNERS/ARROWS AND AUTOCOMPLETE DROPDOWNS ON NUMBER FIELDS
    // =========================================================================
    var numStyle = document.createElement('style');
    numStyle.textContent = 
        'input[type=number]::-webkit-inner-spin-button, ' +
        'input[type=number]::-webkit-outer-spin-button { ' +
        '    -webkit-appearance: none !important; ' +
        '    margin: 0 !important; ' +
        '} ' +
        'input[type=number] { ' +
        '    -moz-appearance: textfield !important; ' +
        '    appearance: textfield !important; ' +
        '}';
    document.head.appendChild(numStyle);

    // Disable browser autocomplete dropdown on all number and code inputs
    $(document).on('focus', 'input[type="number"], input.pinv-item-code, input.pinv-qty, input.pinv-cost', function () {
        $(this).attr('autocomplete', 'off');
    });
});
