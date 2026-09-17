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

    // =========================================================================
    // GLOBAL ACTIVE BRANCH SYNCHRONIZATION (E.g. Motera)
    // "upar jo branch select ki hai vo hi sab mein auto aani chiye jaise motera"
    // =========================================================================
    var $navSelect = $('#top-navbar-branch-select');
    var navSelectedVal = $navSelect.length ? $navSelect.val() : null;
    var activeBranchId = (navSelectedVal !== null)
        ? (navSelectedVal === 'all' ? null : String(navSelectedVal))
        : localStorage.getItem('urbanpos_active_branch_id');

    // Keep localStorage in sync with currently rendered navbar select
    if (navSelectedVal !== null) {
        if (navSelectedVal === 'all') {
            localStorage.removeItem('urbanpos_active_branch_id');
        } else {
            localStorage.setItem('urbanpos_active_branch_id', String(navSelectedVal));
        }
    }

    function syncBranchAcrossApp(branchId, triggerReload) {
        var val = (!branchId || branchId === 'all' || branchId === '0') ? 'all' : String(branchId);

        if (val === 'all') {
            localStorage.removeItem('urbanpos_active_branch_id');
            activeBranchId = null;
        } else {
            localStorage.setItem('urbanpos_active_branch_id', val);
            activeBranchId = val;
        }

        // Keep top navbar select in sync immediately
        if ($('#top-navbar-branch-select').length && $('#top-navbar-branch-select').val() !== val) {
            $('#top-navbar-branch-select').val(val);
        }

        // Sync to server session via AJAX
        var csrfToken = $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val();
        if (csrfToken) {
            $.ajax({
                url: '/active-branch',
                type: 'POST',
                data: {
                    _token: csrfToken,
                    branch_id: val
                },
                dataType: 'json',
                complete: function () {
                    if (triggerReload) {
                        window.location.reload();
                    }
                }
            });
        } else if (triggerReload) {
            window.location.reload();
        }

        // Sync any branch selects on the current page that match
        if (val !== 'all') {
            $('select[name="branch_id"], select[name="from_branch_id"]').each(function () {
                var $sel = $(this);
                if ($sel.val() !== val && $sel.find('option[value="' + val + '"]').length) {
                    $sel.val(val);
                    if ($sel.hasClass('select2-hidden-accessible')) {
                        $sel.trigger('change.select2');
                    }
                }
            });
        }
    }

    // Auto-apply active branch to all unselected branch dropdowns on page load (transaction/entry forms only)
    function applyActiveBranchToForm(context) {
        var $scope = context ? $(context) : $(document);
        if (!activeBranchId || activeBranchId === 'all') {
            return;
        }

        // Only target transaction / data-entry forms (POST forms or modal inputs), NEVER GET filter/search forms
        $scope.find('form:not([method="GET"]) select[name="branch_id"], form:not([method="GET"]) select[name="from_branch_id"], .modal select[name="branch_id"]').each(function () {
            var $sel = $(this);
            var currentVal = $sel.val();
            // If empty or placeholder selected, auto-select active branch (e.g. Motera)
            if (!currentVal || currentVal === '' || currentVal === '0') {
                if ($sel.find('option[value="' + activeBranchId + '"]').length) {
                    $sel.val(activeBranchId);
                    if ($sel.hasClass('select2-hidden-accessible')) {
                        $sel.trigger('change.select2');
                    }
                }
            }
        });
    }

    applyActiveBranchToForm();

    // Re-apply when modals or dynamic content load
    $(document).on('shown.bs.modal reinit:select2', function () {
        applyActiveBranchToForm(this);
    });

    // When user changes top navbar branch -> sync and immediately refresh/redirect page
    $(document).on('change', '#top-navbar-branch-select', function () {
        var selectedVal = $(this).val();
        try {
            var url = new URL(window.location.href);
            if (url.searchParams.has('branch_id')) {
                if (selectedVal === 'all' || !selectedVal) {
                    url.searchParams.delete('branch_id');
                } else {
                    url.searchParams.set('branch_id', selectedVal);
                }
                syncBranchAcrossApp(selectedVal, false);
                setTimeout(function () {
                    window.location.href = url.toString();
                }, 100);
                return;
            }
        } catch (e) {}

        syncBranchAcrossApp(selectedVal, true);
    });

    // When user changes branch in ANY form on the page (excluding top navbar)
    $(document).on('change', 'select[name="branch_id"]:not(#top-navbar-branch-select), select[name="from_branch_id"]', function (e) {
        var val = $(this).val();
        var targetVal = (!val || val === 'all' || val === '0') ? 'all' : String(val);
        var currentActive = activeBranchId || 'all';
        if (targetVal !== currentActive) {
            syncBranchAcrossApp(targetVal, false);
        }
    });

    // =========================================================================
    // GLOBAL 10-DIGIT NUMERIC-ONLY MOBILE VALIDATION & INPUT RESTRICTION
    // =========================================================================
    var mobileSelector = 'input[name="mobile"], input[name*="[mobile]"], input[id*="mobile"], input[class*="mobile"], input[data-type="mobile"], #sb-customer-mobile';

    function setupMobileInputs(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find(mobileSelector).each(function () {
            var $this = $(this);
            $this.attr('maxlength', '10');
            $this.attr('inputmode', 'numeric');
            $this.attr('pattern', '[0-9]{10}');
            // Clean up any initial non-numeric characters or overflow
            var cur = $this.val();
            if (cur && /[^0-9]/.test(cur)) {
                $this.val(cur.replace(/[^0-9]/g, '').slice(0, 10));
            }
        });
    }

    setupMobileInputs();

    $(document).on('shown.bs.modal reinit:select2', function () {
        setupMobileInputs(this);
    });

    $(document).on('keypress', mobileSelector, function (e) {
        // Allow navigation/control keys
        if (e.which === 0 || e.which === 8 || e.which === 13) return;
        // Only allow numbers 0-9
        if (e.which < 48 || e.which > 57) {
            e.preventDefault();
            return false;
        }
        // Limit to 10 digits
        var current = $(this).val();
        if (current.length >= 10 && this.selectionStart === this.selectionEnd) {
            e.preventDefault();
            return false;
        }
    });

    $(document).on('input paste', mobileSelector, function () {
        var $this = $(this);
        setTimeout(function () {
            var val = $this.val();
            var cleaned = val.replace(/[^0-9]/g, '').slice(0, 10);
            if (val !== cleaned) {
                $this.val(cleaned);
            }
        }, 10);
    });

    // =========================================================================
    // GLOBAL GSTIN VALIDATION (Indian GST Number — 15 chars, strict format)
    // Format: 2 digits + 5 letters + 4 digits + 1 letter + 1 alphanumeric + Z + 1 alphanumeric
    // Example: 27AAPFU0939F1ZV
    // =========================================================================
    var gstSelector = 'input[name="gst_no"], input[id="qc-gst-no"], input[data-type="gstin"]';
    var GSTIN_REGEX = /^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[A-Z\d]{1}Z[A-Z\d]{1}$/;

    function validateGstInput($el) {
        var val = $.trim($el.val()).toUpperCase();
        if (val !== $el.val()) {
            $el.val(val);
        }

        // Remove previous feedback
        $el.removeClass('is-valid is-invalid border-success border-danger');
        var $feedback = $el.siblings('.gstin-feedback');
        if (!$feedback.length) {
            $feedback = $('<small class="gstin-feedback form-text" style="font-size:0.78rem;"></small>');
            $el.after($feedback);
        }

        if (val === '') {
            $feedback.text('').hide();
            return;
        }

        if (val.length < 15) {
            $el.addClass('border-warning');
            $feedback.removeClass('text-success text-danger').addClass('text-muted').text('GST No: ' + val.length + '/15 characters').show();
            return;
        }

        $el.removeClass('border-warning');
        if (GSTIN_REGEX.test(val)) {
            $el.addClass('is-valid border-success');
            $feedback.removeClass('text-muted text-danger').addClass('text-success').text('✓ Valid GSTIN').show();
        } else {
            $el.addClass('is-invalid border-danger');
            $feedback.removeClass('text-muted text-success').addClass('text-danger').text('✗ Invalid GSTIN format. Example: 27AAPFU0939F1ZV').show();
        }
    }

    function setupGstInputs(context) {
        var $scope = context ? $(context) : $(document);
        $scope.find(gstSelector).each(function () {
            var $this = $(this);
            $this.attr('maxlength', '15');
            $this.attr('placeholder', $this.attr('placeholder') || 'e.g. 27AAPFU0939F1ZV');
            $this.attr('style', ($this.attr('style') || '') + ' text-transform:uppercase;');
        });
    }

    setupGstInputs();

    $(document).on('shown.bs.modal reinit:select2', function () {
        setupGstInputs(this);
    });

    $(document).on('input blur', gstSelector, function () {
        validateGstInput($(this));
    });

    $(document).on('paste', gstSelector, function () {
        var $this = $(this);
        setTimeout(function () { validateGstInput($this); }, 20);
    });
});
