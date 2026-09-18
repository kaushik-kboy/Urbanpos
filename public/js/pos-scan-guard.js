/**
 * UrbanPOS Cashier Scan Guard & Double-Tender Prevention Engine
 * Prevents hardware barcode scanner bounce double-scans and form double-submission.
 */
(function () {
    'use strict';

    const RAPID_SCAN_DEBOUNCE_MS = 400; // 400ms threshold
    let lastBarcode = null;
    let lastScanTimestamp = 0;
    let isSubmitting = false;

    /**
     * Generate standard RFC4122 UUID v4
     */
    function generateUUID() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Filter barcode scans to prevent rapid hardware bounces
     */
    function filterScan(barcode) {
        if (!barcode || typeof barcode !== 'string') {
            return { allowed: true };
        }

        const cleanCode = barcode.trim();
        const now = Date.now();
        const elapsed = now - lastScanTimestamp;

        if (cleanCode === lastBarcode && elapsed < RAPID_SCAN_DEBOUNCE_MS) {
            showDoubleScanNotice(cleanCode, elapsed);
            return {
                allowed: false,
                reason: 'rapid_duplicate',
                elapsedMs: elapsed
            };
        }

        lastBarcode = cleanCode;
        lastScanTimestamp = now;
        return { allowed: true };
    }

    /**
     * Display a non-blocking floating alert notice when a rapid double-scan is detected
     */
    function showDoubleScanNotice(barcode, elapsedMs) {
        let toast = document.getElementById('pos-scan-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'pos-scan-toast';
            toast.style.cssText = `
                position: fixed;
                top: 70px;
                right: 20px;
                z-index: 1060;
                background-color: #ffc107;
                color: #212529;
                padding: 10px 18px;
                border-radius: 6px;
                font-weight: bold;
                font-size: 0.9rem;
                box-shadow: 0 4px 15px rgba(0,0,0,0.25);
                display: none;
                align-items: center;
                gap: 8px;
                transition: opacity 0.25s ease-in-out;
            `;
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <i class="fas fa-exclamation-circle fa-lg text-dark"></i>
            <span>Double-scan ignored (<small>${elapsedMs}ms</small>): <code>${barcode}</code>. Press <strong>F4</strong> to edit Qty.</span>
        `;
        toast.style.display = 'flex';
        toast.style.opacity = '1';

        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => { toast.style.display = 'none'; }, 250);
        }, 2500);
    }

    /**
     * Lock the submission buttons and form to prevent duplicate tender requests
     */
    function lockSubmission(buttonEl) {
        if (isSubmitting) return false;
        isSubmitting = true;
        window._posIsSubmitting = true;

        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Saving Bill...';
        }

        // Disable all submit buttons on the page
        const submitButtons = document.querySelectorAll('button[type="submit"], #tender-ok-btn, .btn-submit-bill');
        submitButtons.forEach(btn => {
            btn.disabled = true;
        });

        // Add subtle overlay
        let overlay = document.getElementById('pos-submit-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'pos-submit-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(0,0,0,0.15);
                z-index: 9999;
                cursor: wait;
            `;
            document.body.appendChild(overlay);
        }

        return true;
    }

    window.PosScanGuard = {
        filterScan,
        generateUUID,
        lockSubmission,
        isSubmitting: () => isSubmitting,
        getDebounceThreshold: () => RAPID_SCAN_DEBOUNCE_MS
    };
})();
