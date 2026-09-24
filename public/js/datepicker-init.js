/**
 * Urban Pets UI 2.0 - Centralized Date Picker & Smart Numeric Date Engine
 * Powered by DateRangePicker, Moment.js & UrbanPosDateConfig
 */
(function ($) {
    'use strict';

    /* ==========================================================================
       1. Global Date Preferences & Configuration (localStorage + API sync)
       ========================================================================== */
    var DateConfig = {
        KEY_MODE: 'urbanpos_date_mode',
        KEY_FORMAT: 'urbanpos_date_format',

        // Default to 'manual' (Hath se likhna - direct numeric typing without popup)
        getMode: function () {
            var m = localStorage.getItem(this.KEY_MODE);
            return (m === 'calendar') ? 'calendar' : 'manual';
        },

        setMode: function (mode) {
            localStorage.setItem(this.KEY_MODE, mode === 'calendar' ? 'calendar' : 'manual');
            this.syncPreferences();
            $(document).trigger('urbanpos:date-config-changed');
        },

        // Default format: 'DD-MM-YYYY' (e.g. 10-04-2026)
        getFormat: function () {
            var f = localStorage.getItem(this.KEY_FORMAT);
            if (f === 'YYYY-MM-DD' || f === 'DD/MM/YYYY') return f;
            return 'DD-MM-YYYY';
        },

        setFormat: function (format) {
            var valid = (format === 'YYYY-MM-DD' || format === 'DD/MM/YYYY') ? format : 'DD-MM-YYYY';
            localStorage.setItem(this.KEY_FORMAT, valid);
            this.syncPreferences();
            $(document).trigger('urbanpos:date-config-changed');
        },

        getPlaceholder: function () {
            var f = this.getFormat();
            if (f === 'YYYY-MM-DD') return 'YYYY-MM-DD (e.g. 20260410)';
            if (f === 'DD/MM/YYYY') return 'DD/MM/YYYY (e.g. 10042026)';
            return 'DD-MM-YYYY (e.g. 10042026)';
        },

        syncPreferences: function () {
            // Optional background sync with user dashboard preferences if endpoint exists
            if (window.APP_URL && window.$) {
                $.post(window.APP_URL + '/user/dashboard-preferences', {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    preferences: {
                        date_mode: this.getMode(),
                        date_format: this.getFormat()
                    }
                }).fail(function () {});
            }
        }
    };

    window.UrbanPosDateConfig = DateConfig;

    /* ==========================================================================
       2. Robust Smart Date Parser & Formatter
       Handles: 10042026, 10-04-2026, 10/04/2026, 2026-04-10, 1004, t/today
       ========================================================================== */
    function parseDateParts(raw) {
        if (!raw) return null;
        var str = String(raw).trim();
        if (!str) return null;

        var day, month, year;
        var currentYear = new Date().getFullYear();

        // Keyboard shortcuts 't' or 'today'
        var lower = str.toLowerCase();
        if (lower === 't' || lower === 'today') {
            var now = new Date();
            return {
                day: now.getDate(),
                month: now.getMonth() + 1,
                year: now.getFullYear()
            };
        }

        // Strip extraneous whitespace
        str = str.replace(/\s+/g, '');

        // Separated by /, -, or .
        var sepParts = str.split(/[\/\-\.]/);
        if (sepParts.length === 3) {
            if (sepParts[0].length === 4) {
                // YYYY-MM-DD or YYYY/MM/DD
                year = parseInt(sepParts[0], 10);
                month = parseInt(sepParts[1], 10);
                day = parseInt(sepParts[2], 10);
            } else {
                // DD-MM-YYYY or DD/MM/YYYY (Indian ERP standard)
                day = parseInt(sepParts[0], 10);
                month = parseInt(sepParts[1], 10);
                var yStr = sepParts[2];
                year = yStr.length === 2 ? 2000 + parseInt(yStr, 10) : parseInt(yStr, 10);
            }
        } else if (sepParts.length === 2) {
            // DD-MM (current year)
            day = parseInt(sepParts[0], 10);
            month = parseInt(sepParts[1], 10);
            year = currentYear;
        } else {
            // Pure raw digits
            var digits = str.replace(/\D/g, '');
            if (digits.length === 8) {
                var first4 = parseInt(digits.substring(0, 4), 10);
                if (first4 >= 1900 && first4 <= 2100) {
                    // YYYYMMDD (e.g. 20260410)
                    year = first4;
                    month = parseInt(digits.substring(4, 6), 10);
                    day = parseInt(digits.substring(6, 8), 10);
                } else {
                    // DDMMYYYY (e.g. 10042026 -> 10 April 2026)
                    day = parseInt(digits.substring(0, 2), 10);
                    month = parseInt(digits.substring(2, 4), 10);
                    year = parseInt(digits.substring(4, 8), 10);
                }
            } else if (digits.length === 6) {
                // DDMMYY (e.g. 100426)
                day = parseInt(digits.substring(0, 2), 10);
                month = parseInt(digits.substring(2, 4), 10);
                year = 2000 + parseInt(digits.substring(4, 6), 10);
            } else if (digits.length === 4) {
                // DDMM (e.g. 1004 -> 10 April current year)
                day = parseInt(digits.substring(0, 2), 10);
                month = parseInt(digits.substring(2, 4), 10);
                year = currentYear;
            } else if (digits.length === 7) {
                // DMMYYYY (e.g. 5042026 -> 5 Apr 2026)
                day = parseInt(digits.substring(0, 1), 10);
                month = parseInt(digits.substring(1, 3), 10);
                year = parseInt(digits.substring(3, 7), 10);
            } else {
                return null;
            }
        }

        if (!day || !month || !year) return null;
        if (month < 1 || month > 12) return null;
        if (day < 1 || day > 31) return null;
        if (year < 1900 || year > 2100) return null;

        // Accurate day-in-month validation (leap years, 28/29/30/31 days)
        var testDate = new Date(year, month - 1, day);
        if (testDate.getFullYear() !== year || testDate.getMonth() !== (month - 1) || testDate.getDate() !== day) {
            return null;
        }

        return { day: day, month: month, year: year };
    }

    function formatParts(parts, targetFormat) {
        if (!parts) return null;
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        var d = pad(parts.day);
        var m = pad(parts.month);
        var y = String(parts.year);

        if (targetFormat === 'YYYY-MM-DD') {
            return y + '-' + m + '-' + d;
        }
        if (targetFormat === 'DD/MM/YYYY') {
            return d + '/' + m + '/' + y;
        }
        // Default DD-MM-YYYY
        return d + '-' + m + '-' + y;
    }

    function parseFastDate(raw) {
        var parts = parseDateParts(raw);
        if (!parts) return null;
        return formatParts(parts, DateConfig.getFormat());
    }

    window.parseFastDate = parseFastDate;
    window.parseDateParts = parseDateParts;
    window.formatParts = formatParts;

    /* ==========================================================================
       3. Date Picker Setup & Mode Enforcement
       ========================================================================== */
    function setupDatePicker($input) {
        if (typeof $.fn.daterangepicker === 'undefined') return;

        var isRange = $input.data('mode') === 'range' || $input.hasClass('daterange');
        var currentFormat = DateConfig.getFormat();
        var currentMode = DateConfig.getMode();

        // Standardize placeholder based on format
        $input.attr('placeholder', DateConfig.getPlaceholder());

        // Format existing initial value to match active preference
        var rawVal = ($input.val() || '').trim();
        var parts = parseDateParts(rawVal);
        if (parts) {
            var formattedInitial = formatParts(parts, currentFormat);
            if ($input.val() !== formattedInitial) {
                $input.val(formattedInitial);
            }
        }

        // If daterangepicker is already initialized, adjust its behavior and return
        if ($input.data('daterangepicker')) {
            applyModeBehavior($input);
            return;
        }

        var minDateAttr = $input.attr('min');
        var maxDateAttr = $input.attr('max');
        var minDate = minDateAttr ? moment(minDateAttr, ['YYYY-MM-DD', 'DD-MM-YYYY', 'DD/MM/YYYY']) : undefined;
        var maxDate = maxDateAttr ? moment(maxDateAttr, ['YYYY-MM-DD', 'DD-MM-YYYY', 'DD/MM/YYYY']) : undefined;
        if (minDate && !minDate.isValid()) minDate = undefined;
        if (maxDate && !maxDate.isValid()) maxDate = undefined;

        var momentFmt = currentFormat === 'DD/MM/YYYY' ? 'DD/MM/YYYY' : (currentFormat === 'YYYY-MM-DD' ? 'YYYY-MM-DD' : 'DD-MM-YYYY');

        var config = {
            singleDatePicker: !isRange,
            showDropdowns: true,
            autoApply: true,
            autoUpdateInput: false,
            opens: 'right',
            drops: 'auto',
            locale: {
                format: momentFmt,
                separator: ' - ',
                applyLabel: 'Apply',
                cancelLabel: 'Clear',
                fromLabel: 'From',
                toLabel: 'To',
                customRangeLabel: 'Custom',
                firstDay: 1
            }
        };

        if (minDate) config.minDate = minDate;
        if (maxDate) config.maxDate = maxDate;

        if (parts) {
            config.startDate = moment([parts.year, parts.month - 1, parts.day]);
            if (isRange) config.endDate = config.startDate;
        }

        $input.daterangepicker(config);
        $input.addClass('picker-ready');

        // Apply selection from calendar dropdown
        $input.on('apply.daterangepicker', function (ev, picker) {
            var fmt = DateConfig.getFormat();
            var outFmt = fmt === 'DD/MM/YYYY' ? 'DD/MM/YYYY' : (fmt === 'YYYY-MM-DD' ? 'YYYY-MM-DD' : 'DD-MM-YYYY');
            if (isRange) {
                $(this).val(picker.startDate.format(outFmt) + ' - ' + picker.endDate.format(outFmt)).trigger('change');
            } else {
                $(this).val(picker.startDate.format(outFmt)).trigger('change');
            }
        });

        // Clear selection
        $input.on('cancel.daterangepicker', function () {
            $(this).val('').trigger('change');
        });

        applyModeBehavior($input);
    }

    /**
     * Enforces the chosen mode (Manual vs Calendar) on an input.
     * In 'manual' mode:
     *   - Focus/Click on the input NEVER opens the calendar popup!
     *   - Calendar popup only opens if user clicks the calendar icon in append button.
     * In 'calendar' mode:
     *   - Standard calendar popup opens on focus/click.
     */
    function applyModeBehavior($input) {
        var mode = DateConfig.getMode();
        var isRange = $input.hasClass('daterange') || $input.data('mode') === 'range';
        var drp = $input.data('daterangepicker');

        $input.attr('placeholder', DateConfig.getPlaceholder());

        if (mode === 'manual' && !isRange) {
            // Suppress the automatic popup on input click & focus
            $input.off('click.daterangepicker focus.daterangepicker');
        } else if (drp) {
            // Re-bind automatic popup for calendar mode
            $input.off('click.daterangepicker focus.daterangepicker').on({
                'click.daterangepicker': $.proxy(drp.show, drp),
                'focus.daterangepicker': $.proxy(drp.show, drp)
            });
        }

        // Setup input-group append button actions
        var $group = $input.closest('.input-group');
        if ($group.length) {
            // Update badge / icon state if toggle button exists
            updateFieldAddonUI($group, mode);
        }
    }

    function updateFieldAddonUI($group, mode) {
        var $btn = $group.find('.btn-date-mode-toggle');
        if ($btn.length) {
            if (mode === 'manual') {
                $btn.attr('title', 'Mode: Manual Typing (10042026). Click 📅 icon to open calendar / Click ⚙️ to configure.');
                $btn.find('.date-mode-label').html('<i class="fas fa-keyboard text-primary mr-1"></i><span class="small font-weight-bold text-dark">Manual</span>');
            } else {
                $btn.attr('title', 'Mode: Calendar Picker. Click to open calendar / Click ⚙️ to configure.');
                $btn.find('.date-mode-label').html('<i class="fas fa-calendar-alt text-success mr-1"></i><span class="small font-weight-bold text-dark">Picker</span>');
            }
        }
    }

    /* ==========================================================================
       4. Real-time Numeric Fast Typing (e.g. 10042026 -> 10-04-2026)
       ========================================================================== */
    function applyFastDateFormatting($input) {
        if ($input.hasClass('daterange') || $input.data('mode') === 'range') return;
        var val = ($input.val() || '').trim();
        if (!val) return;

        var parts = parseDateParts(val);
        if (parts) {
            var formatted = formatParts(parts, DateConfig.getFormat());
            if ($input.val() !== formatted) {
                $input.val(formatted);
            }
            var dp = $input.data('daterangepicker');
            if (dp && typeof moment !== 'undefined') {
                var m = moment([parts.year, parts.month - 1, parts.day]);
                dp.setStartDate(m);
                dp.setEndDate(m);
            }
            $input.removeClass('is-invalid');
            $input.trigger('change');
        }
    }

    // Auto-format on typing exact 8 raw digits (e.g. 10042026)
    $(document).on('input', '.datepicker, input[type="date"], input[name*="date"], input[id*="date"]', function () {
        var $this = $(this);
        if ($this.hasClass('daterange') || $this.data('mode') === 'range') return;

        var raw = ($this.val() || '').trim();
        // If user typed 8 digits without separators
        if (/^\d{8}$/.test(raw)) {
            var parts = parseDateParts(raw);
            if (parts) {
                var formatted = formatParts(parts, DateConfig.getFormat());
                $this.val(formatted).trigger('change');
            }
        }
    });

    // Format on Enter, Tab, or Blur
    $(document).on('keydown', '.datepicker, input[type="date"], input[name*="date"], input[id*="date"]', function (e) {
        if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
            applyFastDateFormatting($(this));
        }
    });

    $(document).on('blur', '.datepicker, input[type="date"], input[name*="date"], input[id*="date"]', function () {
        applyFastDateFormatting($(this));
    });

    /* ==========================================================================
       5. Preferences Changed Handler (Instant Live Refresh Across Page)
       ========================================================================== */
    $(document).on('urbanpos:date-config-changed', function () {
        var currentFormat = DateConfig.getFormat();
        var currentMode = DateConfig.getMode();

        $('.datepicker, input[data-date-field="true"]').each(function () {
            var $el = $(this);
            applyModeBehavior($el);

            // Re-format current value to the newly selected format
            var val = ($el.val() || '').trim();
            if (val) {
                var parts = parseDateParts(val);
                if (parts) {
                    $el.val(formatParts(parts, currentFormat));
                }
            }
        });

        // Show friendly notification if toastr is loaded
        if (window.toastr) {
            var modeLabel = currentMode === 'manual' ? 'Manual Typing (Hath se likhna - 10042026)' : 'Calendar Picker Popup';
            window.toastr.success('Date Preference updated: ' + modeLabel + ' [' + currentFormat + ']', 'Date Settings Saved');
        }
    });

    /* ==========================================================================
       6. Interactive Date Settings Modal Injection
       ========================================================================== */
    function injectDateSettingsModal() {
        if ($('#urbanpos-date-settings-modal').length) return;

        var modalHtml = '' +
            '<div class="modal fade" id="urbanpos-date-settings-modal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">' +
            '    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">' +
            '        <div class="modal-content shadow border-0">' +
            '            <div class="modal-header bg-light py-2">' +
            '                <h6 class="modal-title font-weight-bold text-dark mb-0">' +
            '                    <i class="fas fa-calendar-alt text-primary mr-2"></i> Date Entry & Format Preferences' +
            '                </h6>' +
            '                <button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
            '                    <span aria-hidden="true">&times;</span>' +
            '                </button>' +
            '            </div>' +
            '            <div class="modal-body p-3">' +
            '                <div class="form-group mb-3">' +
            '                    <label class="font-weight-bold text-uppercase small text-muted mb-2">' +
            '                        <i class="fas fa-hand-pointer mr-1 text-secondary"></i> Date Entry Mode' +
            '                    </label>' +
            '                    <div class="border rounded p-2 bg-light">' +
            '                        <div class="custom-control custom-radio mb-2">' +
            '                            <input type="radio" id="date-mode-manual" name="date_pref_mode" class="custom-control-input" value="manual">' +
            '                            <label class="custom-control-label font-weight-bold text-primary" for="date-mode-manual">' +
            '                                ✍️ Hath se likhna (Fast Keyboard Typing)' +
            '                            </label>' +
            '                            <div class="small text-muted ml-4">Direct typing (e.g. <code>10042026</code>). Calendar popup does NOT block the screen on click.</div>' +
            '                        </div>' +
            '                        <div class="custom-control custom-radio">' +
            '                            <input type="radio" id="date-mode-calendar" name="date_pref_mode" class="custom-control-input" value="calendar">' +
            '                            <label class="custom-control-label font-weight-bold text-success" for="date-mode-calendar">' +
            '                                📅 Calendar Picker Popup' +
            '                            </label>' +
            '                            <div class="small text-muted ml-4">Opens visual calendar popup whenever clicking or focusing the field.</div>' +
            '                        </div>' +
            '                    </div>' +
            '                </div>' +
            '                <div class="form-group mb-3">' +
            '                    <label class="font-weight-bold text-uppercase small text-muted mb-2">' +
            '                        <i class="fas fa-globe mr-1 text-secondary"></i> Display / Entry Format' +
            '                    </label>' +
            '                    <div class="border rounded p-2">' +
            '                        <div class="custom-control custom-radio mb-1">' +
            '                            <input type="radio" id="date-fmt-dmy-dash" name="date_pref_fmt" class="custom-control-input" value="DD-MM-YYYY">' +
            '                            <label class="custom-control-label font-weight-bold" for="date-fmt-dmy-dash">' +
            '                                <code>DD-MM-YYYY</code> <span class="badge badge-success ml-1">Indian ERP Standard</span> (e.g. 10-04-2026)' +
            '                            </label>' +
            '                        </div>' +
            '                        <div class="custom-control custom-radio mb-1">' +
            '                            <input type="radio" id="date-fmt-ymd-dash" name="date_pref_fmt" class="custom-control-input" value="YYYY-MM-DD">' +
            '                            <label class="custom-control-label font-weight-bold" for="date-fmt-ymd-dash">' +
            '                                <code>YYYY-MM-DD</code> <span class="badge badge-secondary ml-1">ISO Standard</span> (e.g. 2026-04-10)' +
            '                            </label>' +
            '                        </div>' +
            '                        <div class="custom-control custom-radio">' +
            '                            <input type="radio" id="date-fmt-dmy-slash" name="date_pref_fmt" class="custom-control-input" value="DD/MM/YYYY">' +
            '                            <label class="custom-control-label font-weight-bold" for="date-fmt-dmy-slash">' +
            '                                <code>DD/MM/YYYY</code> (e.g. 10/04/2026)' +
            '                            </label>' +
            '                        </div>' +
            '                    </div>' +
            '                </div>' +
            '                <div class="alert alert-info py-2 px-3 small mb-0">' +
            '                    <i class="fas fa-lightbulb text-warning mr-1"></i>' +
            '                    <strong>Fast Typing Tip:</strong> Type <code>10042026</code> to get <strong>10-04-2026</strong>. You can also type <code>t</code> or <code>today</code> for today\'s date!' +
            '                </div>' +
            '            </div>' +
            '            <div class="modal-footer bg-light py-2 d-flex justify-content-between">' +
            '                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>' +
            '                <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3 btn-save-date-settings">' +
            '                    <i class="fas fa-check mr-1"></i> Save Preferences' +
            '                </button>' +
            '            </div>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        if (!$('#urbanpos-date-settings-modal').length) {
            $('body').append(modalHtml);
        }
    }

    /* ==========================================================================
       6. Interactive Date Settings Modal Handlers
       ========================================================================== */
    // Open calendar picker button listener
    $(document).on('click', '.btn-open-datepicker', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $group = $(this).closest('.input-group');
        var $input = $group.find('.datepicker, input[data-date-field="true"]');
        var drp = $input.data('daterangepicker');
        if (drp) {
            drp.show();
        } else {
            $input.trigger('focus');
        }
    });

    // Open date settings modal gear button listener
    $(document).on('click', '.btn-date-settings-modal', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $modal = $('#urbanpos-date-settings-modal');
        if ($modal.length) {
            $modal.modal('show');
        }
    });

    // Populate active choices when Date Settings modal opens
    $(document).on('show.bs.modal', '#urbanpos-date-settings-modal', function () {
        var mode = DateConfig.getMode();
        var fmt = DateConfig.getFormat();
        $('input[name="date_pref_mode"][value="' + mode + '"]').prop('checked', true);
        $('input[name="date_pref_fmt"][value="' + fmt + '"]').prop('checked', true);
    });

    // Save preferences from modal
    $(document).on('click', '.btn-save-date-settings', function () {
        var selectedMode = $('input[name="date_pref_mode"]:checked').val() || 'manual';
        var selectedFmt = $('input[name="date_pref_fmt"]:checked').val() || 'DD-MM-YYYY';

        DateConfig.setMode(selectedMode);
        DateConfig.setFormat(selectedFmt);

        $('#urbanpos-date-settings-modal').modal('hide');
    });

    /* ==========================================================================
       7. Initializer
       ========================================================================== */
    function initAll(context) {
        var $scope = context ? $(context) : $(document);

        // Convert any native input[type="date"] to text with datepicker class
        $scope.find('input[type="date"]').each(function () {
            var $el = $(this);
            $el.attr('type', 'text').addClass('datepicker').attr('data-date-field', 'true');
        });

        // Initialize all .datepicker and [data-toggle="datepicker"] inputs
        $scope.find('.datepicker, [data-toggle="datepicker"]').each(function () {
            setupDatePicker($(this));
        });

        injectDateSettingsModal();
    }

    $(document).ready(function () {
        initAll();
    });

    $(document).on('shown.bs.modal', function () {
        initAll(this);
    });

    $(document).on('reinit:datepicker', function (e, target) {
        initAll(target || document);
    });

    $(document).on('focus click', '.datepicker:not(.picker-ready), input[type="date"]', function () {
        var $this = $(this);
        if ($this.attr('type') === 'date') {
            $this.attr('type', 'text').addClass('datepicker').attr('data-date-field', 'true');
        }
        setupDatePicker($this);
    });

})(jQuery);