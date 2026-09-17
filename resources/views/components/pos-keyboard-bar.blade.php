<div class="pos-keyboard-bar py-1 px-3 bg-dark text-white border-top shadow-lg" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 1035; font-size: 0.8rem; display: flex !important; align-items: center; justify-content: space-between; flex-wrap: nowrap; overflow-x: auto; user-select: none;">
    <div class="d-flex align-items-center flex-shrink-0 mr-3">
        <span class="badge badge-warning text-dark font-weight-bold mr-2"><i class="fas fa-keyboard mr-1"></i> SHORTCUTS</span>
        <span class="text-muted d-none d-md-inline" style="font-size: 0.75rem;">Mouse-Free POS</span>
    </div>

    <div class="d-flex align-items-center flex-nowrap" style="gap: 6px;">
        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('search_item');" title="Search Item / Barcode (F2)">
            <span class="badge badge-primary mr-1">F2</span> Item Search
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('new_entry');" title="Add New Item Row / New Entry (F3)">
            <span class="badge badge-info mr-1">F3</span> New Row
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('edit_entry');" title="Edit / Focus Row (F4)">
            <span class="badge badge-secondary mr-1">F4</span> Edit Qty
        </button>

        <button type="button" class="btn btn-xs btn-outline-success text-white px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('save_form');" title="Save Bill / Tender (F6)">
            <span class="badge badge-success mr-1">F6</span> Save &amp; Tender
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('print_form');" title="Print Slip (F8)">
            <span class="badge badge-warning text-dark mr-1">F8</span> Print
        </button>

        <button type="button" class="btn btn-xs btn-outline-warning px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('clear_form');" title="Clear / Reset (F9)">
            <span class="badge badge-danger mr-1">F9</span> Reset
        </button>

        <button type="button" class="btn btn-xs btn-outline-light px-2 font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('close_modal');" title="Close Modal / Back (F10 / Esc)">
            <span class="badge badge-light text-dark mr-1">F10</span> Close/Back
        </button>
    </div>

    <div class="d-none d-lg-flex align-items-center flex-shrink-0 ml-3" style="gap: 5px; font-size: 0.75rem;">
        <span class="text-muted mr-1">Jump:</span>
        <a href="{{ url('sales/sales-bills/create') }}" class="badge badge-dark border border-secondary text-white px-2 py-1" title="Sales Bill"><kbd class="bg-primary text-white">Alt+S</kbd> Sales</a>
        <a href="{{ url('purchase/purchase-invoices/create') }}" class="badge badge-dark border border-secondary text-white px-2 py-1" title="Purchase Invoice"><kbd class="bg-info text-white">Alt+P</kbd> Purchase</a>
        <a href="{{ url('inventory/stock-transfers/create') }}" class="badge badge-dark border border-secondary text-white px-2 py-1" title="Stock Transfer"><kbd class="bg-secondary text-white">Alt+T</kbd> Transfer</a>
        <a href="{{ url('tools/function-keys') }}" class="badge badge-dark border border-secondary text-warning px-2 py-1" title="Configure Shortcuts"><i class="fas fa-cog"></i> Config</a>
    </div>
</div>

<style>
    /* Ensure content is not obscured by fixed shortcut bar */
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
