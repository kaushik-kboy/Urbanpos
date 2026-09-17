<div class="pos-keyboard-bar py-1 px-3 bg-dark text-white border-top shadow-lg" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 1040; font-size: 0.8rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: nowrap; overflow-x: auto; user-select: none;">
    <div class="d-flex align-items-center flex-shrink-0 mr-3">
        <span class="badge badge-warning text-dark font-weight-bold mr-2"><i class="fas fa-keyboard mr-1"></i> SHORTCUTS</span>
        <span class="text-muted d-none d-md-inline" style="font-size: 0.75rem;">100% Mouse-Free ERP Mode</span>
    </div>

    <div class="d-flex align-items-center flex-nowrap" style="gap: 6px;">
        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F2', bubbles: true}))" title="Search Item / Barcode">
            <span class="badge badge-primary mr-1">F2</span> Item Search
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F3', bubbles: true}))" title="Add New Item Row">
            <span class="badge badge-info mr-1">F3</span> New Row
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F4', bubbles: true}))" title="Edit / Focus Active Row">
            <span class="badge badge-secondary mr-1">F4</span> Edit Qty
        </button>

        <button type="button" class="btn btn-xs btn-outline-success text-white px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F6', bubbles: true}))" title="Save Bill / Tender">
            <span class="badge badge-success mr-1">F6</span> Save &amp; Tender
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F8', bubbles: true}))" title="Print Slip">
            <span class="badge badge-warning text-dark mr-1">F8</span> Print
        </button>

        <button type="button" class="btn btn-xs btn-outline-warning px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F9', bubbles: true}))" title="Clear / Reset Bill">
            <span class="badge badge-danger mr-1">F9</span> Reset
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="window.dispatchEvent(new KeyboardEvent('keydown', {key: 'F10', bubbles: true}))" title="Close Modal / Back">
            <span class="badge badge-light text-dark mr-1">F10</span> Close/Back
        </button>
    </div>

    <div class="d-none d-lg-flex align-items-center flex-shrink-0 ml-3" style="gap: 5px; font-size: 0.75rem;">
        <span class="text-muted mr-1">Jump:</span>
        <a href="{{ url('sales/sales-bills/create') }}" class="badge badge-dark border border-secondary text-white px-2 py-1" title="Sales Bill"><kbd class="bg-primary text-white">Alt+S</kbd> Sales</a>
        <a href="{{ url('purchase/purchase-invoices/create') }}" class="badge badge-dark border border-secondary text-white px-2 py-1" title="Purchase Invoice"><kbd class="bg-info text-white">Alt+P</kbd> Purchase</a>
        <a href="{{ url('inventory/stock-transfers/create') }}" class="badge badge-dark border border-secondary text-white px-2 py-1" title="Stock Transfer"><kbd class="bg-secondary text-white">Alt+T</kbd> Transfer</a>
        <a href="{{ route('tools.function-keys.index') }}" class="badge badge-dark border border-secondary text-warning px-2 py-1" title="Configure Shortcuts"><i class="fas fa-cog"></i> Config</a>
    </div>
</div>

<style>
    /* Give page footer padding so content is not obscured by fixed shortcut bar */
    body {
        padding-bottom: 34px !important;
    }
    .pos-keyboard-bar kbd {
        font-size: 0.7rem;
        padding: 1px 4px;
        border-radius: 3px;
        margin-right: 3px;
    }
    .pos-keyboard-bar button:hover, .pos-keyboard-bar a:hover {
        transform: translateY(-1px);
        transition: 0.15s ease-in-out;
    }
</style>
