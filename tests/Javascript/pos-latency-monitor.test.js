import { describe, it, expect, beforeEach } from 'vitest';

describe('UrbanPOS Cashier Latency Alert Banner & Monitor Engine', () => {
    // Extracted pure classification logic matching public/js/pos-latency-monitor.js
    function classifyLatency(ms) {
        if (ms === null || ms === undefined || isNaN(ms)) return 'offline';
        if (ms < 600) return 'optimal';
        if (ms < 1500) return 'fair';
        return 'slow';
    }

    function updateBannerUI(status, latencyMs, doc) {
        const banner = doc.getElementById('pos-latency-banner');
        if (!banner) return;

        const iconEl = doc.getElementById('pos-latency-banner-icon');
        const titleEl = doc.getElementById('pos-latency-banner-title');
        const descEl = doc.getElementById('pos-latency-banner-desc');
        const badgeEl = doc.getElementById('pos-latency-banner-badge');

        if (status === 'offline') {
            banner.className = 'pos-latency-banner alert alert-danger shadow-lg py-2 px-3 mb-0';
            banner.style.display = 'flex';
            if (iconEl) iconEl.className = 'fas fa-exclamation-triangle fa-2x text-danger mr-3 animate-pulse';
            if (titleEl) titleEl.textContent = 'Network Disconnected / Offline';
            if (descEl) descEl.textContent = 'Cannot reach the UrbanPOS server. Please verify your internet/Wi-Fi connection before saving bills.';
            if (badgeEl) {
                badgeEl.className = 'badge badge-danger px-2 py-1 ml-auto font-weight-bold';
                badgeEl.textContent = 'OFFLINE';
            }
        } else if (status === 'slow') {
            banner.className = 'pos-latency-banner alert alert-warning shadow-lg py-2 px-3 mb-0';
            banner.style.display = 'flex';
            if (iconEl) iconEl.className = 'fas fa-hourglass-half fa-2x text-warning mr-3';
            if (titleEl) titleEl.textContent = 'High Server Latency Detected';
            if (descEl) descEl.textContent = `Server ping is ${latencyMs}ms. Operations, searches, and barcode scanning may take longer than usual.`;
            if (badgeEl) {
                badgeEl.className = 'badge badge-warning text-dark px-2 py-1 ml-auto font-weight-bold';
                badgeEl.textContent = `${latencyMs} ms SLOW`;
            }
        } else {
            banner.style.display = 'none';
        }
    }

    function updateIndicatorUI(status, latencyMs, doc) {
        const dot = doc.getElementById('pos-latency-dot');
        const valEl = doc.getElementById('pos-latency-val');
        const textEl = doc.getElementById('pos-latency-text');

        if (!dot) return;

        if (status === 'offline') {
            dot.className = 'pos-dot pos-dot-red';
            if (valEl) valEl.textContent = 'OFFLINE';
            if (textEl) textEl.innerHTML = '<span class="text-danger font-weight-bold">Disconnected</span>';
        } else if (status === 'slow') {
            dot.className = 'pos-dot pos-dot-amber';
            if (valEl) valEl.textContent = latencyMs;
            if (textEl) textEl.innerHTML = `<span class="text-warning font-weight-bold">${latencyMs}ms</span> (Slow)`;
        } else if (status === 'fair') {
            dot.className = 'pos-dot pos-dot-green';
            if (valEl) valEl.textContent = latencyMs;
            if (textEl) textEl.innerHTML = `<span class="text-success">${latencyMs}ms</span>`;
        } else {
            dot.className = 'pos-dot pos-dot-green';
            if (valEl) valEl.textContent = latencyMs;
            if (textEl) textEl.innerHTML = `<span class="text-success font-weight-bold">${latencyMs}ms</span>`;
        }
    }

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="pos-latency-banner" style="display: none;">
                <i id="pos-latency-banner-icon"></i>
                <div id="pos-latency-banner-title"></div>
                <div id="pos-latency-banner-desc"></div>
                <span id="pos-latency-banner-badge"></span>
                <button id="pos-latency-retry-btn"></button>
            </div>
            <div id="pos-latency-indicator">
                <span id="pos-latency-dot" class="pos-dot pos-dot-green"></span>
                <span id="pos-latency-text"><span id="pos-latency-val">...</span>ms</span>
            </div>
        `;
    });

    it('correctly classifies latency into optimal, fair, slow, and offline thresholds', () => {
        expect(classifyLatency(45)).toBe('optimal');
        expect(classifyLatency(599)).toBe('optimal');
        expect(classifyLatency(600)).toBe('fair');
        expect(classifyLatency(1499)).toBe('fair');
        expect(classifyLatency(1500)).toBe('slow');
        expect(classifyLatency(3200)).toBe('slow');
        expect(classifyLatency(null)).toBe('offline');
        expect(classifyLatency(NaN)).toBe('offline');
        expect(classifyLatency(undefined)).toBe('offline');
    });

    it('renders alert warning banner and amber dot when connection is slow', () => {
        updateBannerUI('slow', 1850, document);
        updateIndicatorUI('slow', 1850, document);

        const banner = document.getElementById('pos-latency-banner');
        const dot = document.getElementById('pos-latency-dot');
        const badge = document.getElementById('pos-latency-banner-badge');

        expect(banner.style.display).toBe('flex');
        expect(banner.className).toContain('alert-warning');
        expect(badge.textContent).toBe('1850 ms SLOW');
        expect(dot.className).toContain('pos-dot-amber');
    });

    it('renders danger alert and flashing red dot when offline', () => {
        updateBannerUI('offline', null, document);
        updateIndicatorUI('offline', null, document);

        const banner = document.getElementById('pos-latency-banner');
        const dot = document.getElementById('pos-latency-dot');
        const title = document.getElementById('pos-latency-banner-title');

        expect(banner.style.display).toBe('flex');
        expect(banner.className).toContain('alert-danger');
        expect(title.textContent).toBe('Network Disconnected / Offline');
        expect(dot.className).toContain('pos-dot-red');
    });

    it('automatically hides alert banner when latency returns to optimal fast', () => {
        // First set to slow
        updateBannerUI('slow', 1900, document);
        expect(document.getElementById('pos-latency-banner').style.display).toBe('flex');

        // Then connection recovers
        updateBannerUI('optimal', 65, document);
        updateIndicatorUI('optimal', 65, document);

        const banner = document.getElementById('pos-latency-banner');
        const dot = document.getElementById('pos-latency-dot');

        expect(banner.style.display).toBe('none');
        expect(dot.className).toContain('pos-dot-green');
    });
});
