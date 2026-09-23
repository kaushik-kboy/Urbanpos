/**
 * Urban Pets UI 2.0 - Centralized Date Picker Initializer
 * Powered by DateRangePicker & Moment.js
 */
(function ($) {
    'use strict';

    if (typeof $.fn.daterangepicker === 'undefined') {
        console.warn('DateRangePicker plugin is not loaded.');
        return;
    }

    /**
     * Initializes a single date picker on an input element
     */
    function setupDatePicker($input) {
        if ($input.data('daterangepicker') || $input.hasClass('picker-ready')) {
            return;
        }

        var isRange = $input.data('mode') === 'range' || $input.hasClass('daterange');
        var minDate = $input.attr('min') || undefined;
        var maxDate = $input.attr('max') || undefined;
        var rawVal = ($input.val() || '').trim();
        var hasInitialVal = rawVal.length > 0 && moment(rawVal, 'YYYY-MM-DD', true).isValid();

        var config = {
            singleDatePicker: !isRange,
            showDropdowns: true,
            autoApply: true,
            autoUpdateInput: false,
            opens: 'right',
            drops: 'auto',
            locale: {
                format: 'YYYY-MM-DD',
                separator: ' - ',
                applyLabel: 'Apply',
                cancelLabel: 'Clear',
                fromLabel: 'From',
                toLabel: 'To',
                customRangeLabel: 'Custom',
                weekLabel: 'W',
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                monthNames: [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ],
                firstDay: 1
            }
        };

        if (minDate) config.minDate = minDate;
        if (maxDate) config.maxDate = maxDate;

        if (hasInitialVal) {
            config.startDate = rawVal;
            if (isRange) config.endDate = rawVal;
        }

        $input.daterangepicker(config);
        $input.addClass('picker-ready');

        // Apply selection to input
        $input.on('apply.daterangepicker', function (ev, picker) {
            if (isRange) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD')).trigger('change');
            } else {
                $(this).val(picker.startDate.format('YYYY-MM-DD')).trigger('change');
            }
        });

        // Cancel / Clear selection
        $input.on('cancel.daterangepicker', function () {
            $(this).val('').trigger('change');
        });

        // Handle calendar icon click inside input-group
        var $group = $input.closest('.input-group');
        if ($group.length) {
            $group.find('.input-group-append, .input-group-prepend').css('cursor', 'pointer').off('click.dp').on('click.dp', function () {
                $input.trigger('click');
            });
        }
    }

    /**
     * Parses raw user-typed dates such as:
     * - 10012026 (DDMMYYYY) -> 2026-01-10 (Meaning 10 Jan 2026)
     * - 10/01/2026 or 10-01-2026 (DD/MM/YYYY) -> 2026-01-10
     * - 100126 (DDMMYY) -> 2026-01-10
     * - 1001 (DDMM) -> 2026-01-10 (current year)
     * - 5012026 (DMMYYYY) -> 2026-01-05
     */
    function parseFastDate(raw) {
        if (!raw) return null;
        var str = String(raw).trim();
        if (!str) return null;

        // Already YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
            return (typeof moment !== 'undefined' && moment(str, 'YYYY-MM-DD', true).isValid()) ? str : str;
        }

        var day, month, year;
        var currentYear = new Date().getFullYear();

        // Strip extraneous spaces
        str = str.replace(/\s+/g, '');

        // Separated by /, -, or .
        var sepParts = str.split(/[\/\-\.]/);
        if (sepParts.length === 3) {
            if (sepParts[0].length === 4) {
                // YYYY/MM/DD
                year = parseInt(sepParts[0], 10);
                month = parseInt(sepParts[1], 10);
                day = parseInt(sepParts[2], 10);
            } else {
                // DD/MM/YYYY (Indian ERP standard as requested)
                day = parseInt(sepParts[0], 10);
                month = parseInt(sepParts[1], 10);
                var yStr = sepParts[2];
                year = yStr.length === 2 ? 2000 + parseInt(yStr, 10) : parseInt(yStr, 10);
            }
        } else if (sepParts.length === 2) {
            // DD/MM (current year)
            day = parseInt(sepParts[0], 10);
            month = parseInt(sepParts[1], 10);
            year = currentYear;
        } else {
            // Pure digits
            var digits = str.replace(/\D/g, '');
            if (digits.length === 8) {
                // DDMMYYYY (e.g. 10012026 -> 10 Jan 2026)
                day = parseInt(digits.substring(0, 2), 10);
                month = parseInt(digits.substring(2, 4), 10);
                year = parseInt(digits.substring(4, 8), 10);
            } else if (digits.length === 6) {
                // DDMMYY (e.g. 100126 -> 10 Jan 2026)
                day = parseInt(digits.substring(0, 2), 10);
                month = parseInt(digits.substring(2, 4), 10);
                year = 2000 + parseInt(digits.substring(4, 6), 10);
            } else if (digits.length === 4) {
                // DDMM (e.g. 1001 -> 10 Jan current year)
                day = parseInt(digits.substring(0, 2), 10);
                month = parseInt(digits.substring(2, 4), 10);
                year = currentYear;
            } else if (digits.length === 7) {
                // DMMYYYY (e.g. 5012026 -> 5 Jan 2026) or DDMYYYY
                var firstTwo = parseInt(digits.substring(0, 2), 10);
                if (firstTwo > 12 && firstTwo <= 31) {
                    day = firstTwo;
                    month = parseInt(digits.substring(2, 3), 10);
                    year = parseInt(digits.substring(3, 7), 10);
                } else {
                    day = parseInt(digits.substring(0, 1), 10);
                    month = parseInt(digits.substring(1, 3), 10);
                    year = parseInt(digits.substring(3, 7), 10);
                }
            } else {
                return null;
            }
        }

        if (!day || !month || !year) return null;
        if (month < 1 || month > 12) return null;
        if (day < 1 || day > 31) return null;
        if (year < 1900 || year > 2100) return null;

        if (typeof moment !== 'undefined') {
            var mDate = moment([year, month - 1, day]);
            if (!mDate.isValid() || mDate.date() !== day) {
                return null;
            }
            return mDate.format('YYYY-MM-DD');
        }

        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        return year + '-' + pad(month) + '-' + pad(day);
    }

    window.parseFastDate = parseFastDate;

    function applyFastDateFormatting($input) {
        if ($input.hasClass('daterange') || $input.data('mode') === 'range') return;
        var val = ($input.val() || '').trim();
        if (!val) return;
        var formatted = parseFastDate(val);
        if (formatted) {
            if ($input.val() !== formatted) {
                $input.val(formatted);
            }
            var dp = $input.data('daterangepicker');
            if (dp) {
                dp.setStartDate(formatted);
                dp.setEndDate(formatted);
            }
            $input.trigger('change');
        }
    }

    /**
     * Initializes all date pickers within a given context
     */
    function initAll(context) {
        var $scope = context ? $(context) : $(document);

        // Convert any native input[type="date"] to text with datepicker class
        $scope.find('input[type="date"]').each(function () {
            var $el = $(this);
            $el.attr('type', 'text').addClass('datepicker');
            if (!$el.attr('placeholder')) {
                $el.attr('placeholder', 'YYYY-MM-DD (e.g. 10012026)');
            }
        });

        // Initialize all .datepicker and [data-toggle="datepicker"] inputs
        $scope.find('.datepicker, [data-toggle="datepicker"]').each(function () {
            setupDatePicker($(this));
        });
    }

    // Run on document ready
    $(document).ready(function () {
        initAll();
    });

    // Re-run on Bootstrap modal shown
    $(document).on('shown.bs.modal', function () {
        initAll(this);
    });

    // Custom re-init event for dynamic table rows or AJAX content
    $(document).on('reinit:datepicker', function (e, target) {
        initAll(target || document);
    });

    // Delegated click/focus handler to catch dynamically inserted table rows
    $(document).on('focus click', '.datepicker:not(.picker-ready), input[type="date"]', function () {
        var $this = $(this);
        if ($this.attr('type') === 'date') {
            $this.attr('type', 'text').addClass('datepicker');
            if (!$this.attr('placeholder')) {
                $this.attr('placeholder', 'YYYY-MM-DD (e.g. 10012026)');
            }
        }
        setupDatePicker($this);
    });

    // Fast-typing keyboard & blur handlers for all date fields across the application
    $(document).on('keydown', '.datepicker, input[type="date"], input[name*="date"], input[id*="date"]', function (e) {
        if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
            applyFastDateFormatting($(this));
        }
    });

    $(document).on('blur', '.datepicker, input[type="date"], input[name*="date"], input[id*="date"]', function () {
        applyFastDateFormatting($(this));
    });

})(jQuery);