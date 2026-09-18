{{-- Real-Time Cashier Latency Alert Banner --}}
<div id="pos-latency-banner" class="pos-latency-banner alert alert-warning shadow-lg py-2 px-3 mb-0" style="display: none; position: sticky; top: 0; z-index: 1040; border-radius: 0; align-items: center; justify-content: space-between; flex-wrap: wrap; transition: all 0.3s ease-in-out;">
    <div class="d-flex align-items-center">
        <i id="pos-latency-banner-icon" class="fas fa-exclamation-triangle fa-2x text-warning mr-3"></i>
        <div>
            <div id="pos-latency-banner-title" class="font-weight-bold" style="font-size: 0.95rem;">
                Network Latency Notice
            </div>
            <div id="pos-latency-banner-desc" class="small text-muted mb-0">
                Checking server latency...
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <span id="pos-latency-banner-badge" class="badge badge-warning text-dark font-weight-bold px-2 py-1 mr-2">
            CHECKING
        </span>
        <button type="button" id="pos-latency-retry-btn" class="btn btn-sm btn-dark font-weight-bold shadow-xs mr-2" title="Test connection latency now">
            <i class="fas fa-sync-alt mr-1"></i> Test Connection
        </button>
        <button type="button" id="pos-latency-dismiss-btn" class="btn btn-sm btn-outline-secondary" title="Dismiss banner">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<style>
    /* Latency Status Indicator Dots */
    .pos-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        vertical-align: middle;
        transition: background-color 0.3s ease;
    }
    .pos-dot-green {
        background-color: #28a745;
        box-shadow: 0 0 5px rgba(40, 167, 69, 0.8);
    }
    .pos-dot-amber {
        background-color: #ffc107;
        box-shadow: 0 0 6px rgba(255, 193, 7, 0.9);
    }
    .pos-dot-red {
        background-color: #dc3545;
        box-shadow: 0 0 8px rgba(220, 53, 69, 1);
        animation: pulseRed 1s infinite alternate;
    }
    @keyframes pulseRed {
        0% { opacity: 0.4; transform: scale(0.9); }
        100% { opacity: 1; transform: scale(1.15); }
    }
    .animate-pulse {
        animation: pulseRed 1s infinite alternate;
    }
</style>
