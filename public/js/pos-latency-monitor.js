/**
 * UrbanPOS Real-Time Network & Server Latency Monitor
 * Detects slow networks, server latency, and offline state for cashiers.
 */
(function () {
    'use strict';

    const LATENCY_SLOW_THRESHOLD = 1500;       // ms
    const LATENCY_CRITICAL_THRESHOLD = 3000;   // ms
    const LATENCY_FAIR_THRESHOLD = 600;        // ms
    const PING_INTERVAL = 30000;               // 30s
    const PING_URL = (window.APP_URL ? window.APP_URL.replace(/\/$/, '') : '') + '/pos-ping';

    let lastLatency = null;
    let currentStatus = 'optimal'; // optimal | fair | slow | offline
    let isChecking = false;
    let pingTimer = null;

    function classifyLatency(ms) {
        if (ms === null || ms === undefined || isNaN(ms)) return 'offline';
        if (ms < LATENCY_FAIR_THRESHOLD) return 'optimal';
        if (ms < LATENCY_SLOW_THRESHOLD) return 'fair';
        return 'slow';
    }

    function updateBannerUI(status, latencyMs) {
        const banner = document.getElementById('pos-latency-banner');
        if (!banner) return;

        const iconEl = document.getElementById('pos-latency-banner-icon');
        const titleEl = document.getElementById('pos-latency-banner-title');
        const descEl = document.getElementById('pos-latency-banner-desc');
        const badgeEl = document.getElementById('pos-latency-banner-badge');

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
            // Optimal or Fair: hide alert banner
            banner.style.display = 'none';
        }
    }

    function updateIndicatorUI(status, latencyMs) {
        const indicator = document.getElementById('pos-latency-indicator');
        const dot = document.getElementById('pos-latency-dot');
        const valEl = document.getElementById('pos-latency-val');
        const textEl = document.getElementById('pos-latency-text');

        if (!indicator || !dot) return;

        if (status === 'offline') {
            dot.className = 'pos-dot pos-dot-red';
            if (valEl) valEl.textContent = 'OFFLINE';
            if (textEl) textEl.innerHTML = '<span class="text-danger font-weight-bold">Disconnected</span>';
            indicator.title = 'Offline: Cannot reach server. Click to retry ping.';
        } else if (status === 'slow') {
            dot.className = 'pos-dot pos-dot-amber';
            if (valEl) valEl.textContent = latencyMs;
            if (textEl) textEl.innerHTML = `<span class="text-warning font-weight-bold">${latencyMs}ms</span> (Slow)`;
            indicator.title = `Slow connection (${latencyMs}ms). Click to test again.`;
        } else if (status === 'fair') {
            dot.className = 'pos-dot pos-dot-green';
            if (valEl) valEl.textContent = latencyMs;
            if (textEl) textEl.innerHTML = `<span class="text-success">${latencyMs}ms</span>`;
            indicator.title = `Connection OK (${latencyMs}ms). Click to test.`;
        } else {
            dot.className = 'pos-dot pos-dot-green';
            if (valEl) valEl.textContent = latencyMs;
            if (textEl) textEl.innerHTML = `<span class="text-success font-weight-bold">${latencyMs}ms</span>`;
            indicator.title = `Optimal fast connection (${latencyMs}ms). Click to test.`;
        }
    }

    async function checkLatency() {
        if (isChecking) return;

        if (!navigator.onLine) {
            lastLatency = null;
            currentStatus = 'offline';
            updateBannerUI('offline', null);
            updateIndicatorUI('offline', null);
            dispatchLatencyEvent(null, 'offline');
            return;
        }

        isChecking = true;
        const btnRetry = document.getElementById('pos-latency-retry-btn');
        if (btnRetry) btnRetry.disabled = true;

        const startTime = performance.now();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 6000);

        try {
            const separator = PING_URL.includes('?') ? '&' : '?';
            const response = await fetch(`${PING_URL}${separator}_t=${Date.now()}`, {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`Server returned HTTP ${response.status}`);
            }

            const elapsed = Math.round(performance.now() - startTime);
            lastLatency = elapsed;
            currentStatus = classifyLatency(elapsed);

            updateBannerUI(currentStatus, elapsed);
            updateIndicatorUI(currentStatus, elapsed);
            dispatchLatencyEvent(elapsed, currentStatus);
        } catch (err) {
            clearTimeout(timeoutId);
            lastLatency = null;
            currentStatus = 'offline';
            updateBannerUI('offline', null);
            updateIndicatorUI('offline', null);
            dispatchLatencyEvent(null, 'offline');
        } finally {
            isChecking = false;
            if (btnRetry) btnRetry.disabled = false;
        }
    }

    function dispatchLatencyEvent(latencyMs, status) {
        try {
            window.dispatchEvent(new CustomEvent('pos:latency', {
                detail: { latencyMs, status, timestamp: Date.now() }
            }));
        } catch (e) {
            // fallback
        }
    }

    function init() {
        // Offline / Online listeners
        window.addEventListener('offline', () => {
            currentStatus = 'offline';
            updateBannerUI('offline', null);
            updateIndicatorUI('offline', null);
            dispatchLatencyEvent(null, 'offline');
        });

        window.addEventListener('online', () => {
            checkLatency();
        });

        // Tab focus listener
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                checkLatency();
            }
        });

        // Click on indicator
        const indicator = document.getElementById('pos-latency-indicator');
        if (indicator) {
            indicator.addEventListener('click', () => checkLatency());
        }

        // Click on retry in banner
        document.addEventListener('click', (e) => {
            if (e.target && (e.target.id === 'pos-latency-retry-btn' || e.target.closest('#pos-latency-retry-btn'))) {
                checkLatency();
            }
            if (e.target && (e.target.id === 'pos-latency-dismiss-btn' || e.target.closest('#pos-latency-dismiss-btn'))) {
                const banner = document.getElementById('pos-latency-banner');
                if (banner) banner.style.display = 'none';
            }
        });

        // Initial check after 1.2s delay
        setTimeout(() => {
            checkLatency();
        }, 1200);

        // Recurring ping
        pingTimer = setInterval(() => {
            checkLatency();
        }, PING_INTERVAL);
    }

    // Expose API for external scripts & testing
    window.PosLatencyMonitor = {
        checkLatency,
        classifyLatency,
        getStatus: () => currentStatus,
        getLatency: () => lastLatency,
        init
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
