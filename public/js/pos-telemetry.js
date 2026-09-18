/**
 * UrbanPOS Client-Side Telemetry & Error Boundary
 * Automatically captures unhandled JavaScript errors and reports them
 * to the System Error & Exception Hub without disrupting cashier operations.
 */
(function () {
    'use strict';

    let errorCount = 0;
    const MAX_ERRORS_PER_SESSION = 5;
    let lastErrorTime = 0;

    function reportClientError(errorData) {
        const now = Date.now();
        // Rate limit: max 1 report every 5 seconds, max 5 per page session
        if (now - lastErrorTime < 5000 || errorCount >= MAX_ERRORS_PER_SESSION) {
            return;
        }
        lastErrorTime = now;
        errorCount++;

        const payload = {
            message: errorData.message || 'Unknown JavaScript Error',
            file: errorData.file || window.location.href,
            line: errorData.line || 0,
            url: window.location.href,
            stack: errorData.stack || '',
            extra: {
                screen: window.location.pathname,
                userAgent: navigator.userAgent,
                screenResolution: `${window.innerWidth}x${window.innerHeight}`,
                timestamp: new Date().toISOString()
            }
        };

        const targetUrl = (window.APP_URL || '') + '/tools/client-error-logs';

        try {
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                navigator.sendBeacon(targetUrl, blob);
            } else {
                fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(payload),
                    keepalive: true
                }).catch(function () {});
            }
        } catch (e) {
            // Failsafe
        }
    }

    // Global Error Listener
    window.addEventListener('error', function (event) {
        if (!event) return;
        reportClientError({
            message: event.message || 'Script Error',
            file: event.filename || 'inline script',
            line: event.lineno || 0,
            stack: event.error ? (event.error.stack || '') : ''
        });
    });

    // Unhandled Promise Rejections
    window.addEventListener('unhandledrejection', function (event) {
        if (!event) return;
        const reason = event.reason;
        reportClientError({
            message: 'Unhandled Promise: ' + (reason ? (reason.message || String(reason)) : 'Promise rejected'),
            file: window.location.pathname,
            line: 0,
            stack: reason && reason.stack ? reason.stack : ''
        });
    });

    window.UrbanPosTelemetry = {
        report: reportClientError
    };
})();
