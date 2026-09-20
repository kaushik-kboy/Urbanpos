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
     * Initializes all date pickers within a given context
     */
    function initAll(context) {
        var $scope = context ? $(context) : $(document);

        // Convert any native input[type="date"] to text with datepicker class
        $scope.find('input[type="date"]').each(function () {
            var $el = $(this);
            $el.attr('type', 'text').addClass('datepicker');
            if (!$el.attr('placeholder')) {
                $el.attr('placeholder', 'YYYY-MM-DD');
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
                $this.attr('placeholder', 'YYYY-MM-DD');
            }
        }
        setupDatePicker($this);
    });

})(jQuery);