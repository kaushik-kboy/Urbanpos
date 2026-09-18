import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('UrbanPOS Double-Tender & Rapid Barcode Double-Scan Prevention Engine', () => {
    // Pure logic mirroring public/js/pos-scan-guard.js
    const RAPID_SCAN_DEBOUNCE_MS = 400;
    let lastBarcode = null;
    let lastScanTimestamp = 0;
    let isSubmitting = false;

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function filterScan(barcode, mockNow = Date.now()) {
        if (!barcode || typeof barcode !== 'string') {
            return { allowed: true };
        }

        const cleanCode = barcode.trim();
        const elapsed = mockNow - lastScanTimestamp;

        if (cleanCode === lastBarcode && elapsed < RAPID_SCAN_DEBOUNCE_MS) {
            return {
                allowed: false,
                reason: 'rapid_duplicate',
                elapsedMs: elapsed
            };
        }

        lastBarcode = cleanCode;
        lastScanTimestamp = mockNow;
        return { allowed: true };
    }

    function lockSubmission(buttonEl) {
        if (isSubmitting) return false;
        isSubmitting = true;

        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i> Saving Bill...';
        }

        return true;
    }

    beforeEach(() => {
        lastBarcode = null;
        lastScanTimestamp = 0;
        isSubmitting = false;
        document.body.innerHTML = `
            <button type="button" id="tender-ok-btn" class="tender-btn">Ok</button>
            <input type="hidden" name="posting_key" id="sb-posting-key" value="">
        `;
    });

    it('generates valid RFC4122 UUID v4 for bill idempotency tokens', () => {
        const uuid = generateUUID();
        expect(uuid).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    });

    it('permits normal initial barcode scans', () => {
        const result = filterScan('8901030383712', 1000);
        expect(result.allowed).toBe(true);
    });

    it('blocks rapid hardware bounce double-scan of the same barcode within 400ms', () => {
        // First scan at t=1000ms
        const scan1 = filterScan('8901030383712', 1000);
        expect(scan1.allowed).toBe(true);

        // Rapid second bounce at t=1150ms (150ms later)
        const scan2 = filterScan('8901030383712', 1150);
        expect(scan2.allowed).toBe(false);
        expect(scan2.reason).toBe('rapid_duplicate');
        expect(scan2.elapsedMs).toBe(150);
    });

    it('permits scanning different barcodes even within 400ms', () => {
        // Item A at t=1000ms
        const scanA = filterScan('ITEM-A-123', 1000);
        expect(scanA.allowed).toBe(true);

        // Item B at t=1200ms
        const scanB = filterScan('ITEM-B-456', 1200);
        expect(scanB.allowed).toBe(true);
    });

    it('permits intentional re-scan of the same barcode after 400ms debounce threshold', () => {
        // First scan at t=1000ms
        filterScan('8901030383712', 1000);

        // Intentional second scan at t=1500ms (500ms later)
        const scan2 = filterScan('8901030383712', 1500);
        expect(scan2.allowed).toBe(true);
    });

    it('locks tender submission button and prevents multiple clicks', () => {
        const btn = document.getElementById('tender-ok-btn');

        // First click
        const locked1 = lockSubmission(btn);
        expect(locked1).toBe(true);
        expect(btn.disabled).toBe(true);
        expect(btn.innerHTML).toContain('Saving Bill...');

        // Accidental second click or Enter key
        const locked2 = lockSubmission(btn);
        expect(locked2).toBe(false);
    });
});
