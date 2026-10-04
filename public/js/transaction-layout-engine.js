/**
 * UrbanPOS Universal Transaction Layout Engine
 * 
 * Dynamically computes viewport geometry to ensure ZERO window-level vertical scrolling
 * on desktop displays, keeping table headers, footer totals, and action buttons 
 * visible on screen simultaneously.
 */

(function (window, $) {
    'use strict';

    if (!window.$) return;

    window.initTransactionCompactLayout = function (options) {
        options = $.extend({
            containerSelector: '.tx-items-scroll-container, .sb-items-scroll-container',
            footerSelector: '.tx-rich-footer, .sb-rich-footer',
            tableSelector: '.table-items-dense',
            minHeight: 160
        }, options || {});

        $('html, body').addClass('tx-viewport-fixed');

        function fitLayout() {
            var isDesktop = window.matchMedia('(min-width: 992px) and (min-height: 550px)').matches;
            var $container = $(options.containerSelector).first();
            var $footer = $(options.footerSelector).first();
            var $keyboardBar = $('.pos-keyboard-bar');

            if (!$container.length || !$footer.length) return;

            if (!isDesktop) {
                // Responsive fallback for mobile / tablet
                $container.css({
                    'height': '',
                    'max-height': ''
                });
                return;
            }

            var targetBottom = ($keyboardBar.length && $keyboardBar.is(':visible'))
                ? $keyboardBar[0].getBoundingClientRect().top
                : window.innerHeight;

            var footerRect = $footer[0].getBoundingClientRect();
            var currentHeight = $container[0].getBoundingClientRect().height;
            var gap = targetBottom - footerRect.bottom;

            var targetHeight = Math.max(options.minHeight, Math.floor(currentHeight + gap - 1));

            $container.css({
                'height': targetHeight + 'px',
                'max-height': targetHeight + 'px'
            });
        }

        // Auto-scroll items container when focused field moves out of visible view
        $(document).on('focus', options.tableSelector + ' input, ' + options.tableSelector + ' select, ' + options.tableSelector + ' button', function () {
            var el = this;
            var container = el.closest(options.containerSelector);
            if (!container) return;

            setTimeout(function () {
                var cRect = container.getBoundingClientRect();
                var eRect = el.getBoundingClientRect();

                // If element is below visible bottom
                if (eRect.bottom > (cRect.bottom - 20)) {
                    container.scrollTop += (eRect.bottom - cRect.bottom + 30);
                }
                // If element is above visible top (under sticky thead)
                else if (eRect.top < (cRect.top + 35)) {
                    container.scrollTop -= (cRect.top + 35 - eRect.top);
                }
            }, 50);
        });

        // Run layout calculation initially and at safe timeouts
        fitLayout();
        setTimeout(fitLayout, 80);
        setTimeout(fitLayout, 250);

        $(window).on('resize orientationchange', function () {
            fitLayout();
        });

        $(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', function () {
            setTimeout(fitLayout, 250);
        });

        // Suppress AdminLTE default copyright footer
        $('.main-footer, footer.main-footer').remove();

        return {
            fitLayout: fitLayout
        };
    };

})(window, window.jQuery);
