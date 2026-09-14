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
                var maxLimit = $this.data('maximum-selection-length') || $this.data('max-selections') || null;

                var selectOptions = {
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: placeholderText,
                    allowClear: hasEmptyOption && !$this.prop('multiple')
                };

                if (maxLimit) {
                    selectOptions.maximumSelectionLength = parseInt(maxLimit, 10);
                }

                $this.select2(selectOptions);
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
    // INSTANT SELECT2 OPEN & AUTOFOCUS: Single Click, Tab, Direct Typing, Enter
    // Exact UX: Click/Tab -> Blinking Cursor -> Type Immediately -> Enter to select!
    // Works seamlessly for both Single-select and Multi-value Select boxes
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

    function focusActiveSearchField() {
        var el = document.querySelector('.select2-container--open .select2-search__field');
        if (el) {
            el.focus();
        }
    }

    $(document).on('select2:open', function () {
        justOpened = true;
        if (justOpenedTimer) clearTimeout(justOpenedTimer);
        justOpenedTimer = setTimeout(function () {
            justOpened = false;
        }, 300);

        // Instantly focus the search input so the user can type immediately without a 2nd click!
        focusActiveSearchField();
        requestAnimationFrame(focusActiveSearchField);
        setTimeout(focusActiveSearchField, 50);
        setTimeout(focusActiveSearchField, 150);
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

    // 3. Direct typing & Enter key on Select2:
    // - If closed and user presses Enter -> opens dropdown immediately.
    // - If closed and user types any letter/number -> opens dropdown and inputs that letter into the search field!
    $(document).on('keydown', '.select2-selection', function (e) {
        var $container = $(this).closest('.select2-container');
        var $select = $container.prev('select.select2');
        if (!$select.length || !$select.data('select2')) return;

        if (!$select.data('select2').isOpen()) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                $select.select2('open');
                return false;
            }

            // Printable character pressed while focused on closed select
            if (e.key && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                e.preventDefault();
                e.stopPropagation();
                $select.select2('open');
                setTimeout(function () {
                    var $search = $('.select2-container--open .select2-search__field');
                    if ($search.length) {
                        $search.val(e.key).trigger('input');
                        $search[0].focus();
                    }
                }, 40);
                return false;
            }
        }
    });

    // 4. On selection:
    // - For single-select: smoothly advance focus to next input field in row
    // - For multi-select: keep focus inside the box so user can continue adding tags without interruption
    $(document).on('select2:select', function (e) {
        var $select = $(this);
        if ($select.prop('multiple')) {
            setTimeout(focusActiveSearchField, 50);
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
