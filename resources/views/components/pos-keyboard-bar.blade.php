<div class="pos-keyboard-bar">
    <div class="d-flex align-items-center flex-shrink-0 mr-2">
        <span class="badge badge-warning font-weight-bold mr-1"><i class="fas fa-keyboard mr-1"></i> SHORTCUTS</span>
        <span class="text-muted d-none d-xxl-inline mr-2" style="font-size: 0.75rem;">Mouse-Free POS</span>
        <span id="pos-latency-indicator" class="badge cursor-pointer" style="cursor: pointer;" title="Server ping &amp; latency monitor (Click to test)">
            <span id="pos-latency-dot" class="pos-dot pos-dot-green mr-1"></span>
            <span id="pos-latency-text"><span id="pos-latency-val">...</span>ms</span>
        </span>
    </div>

    <div class="pos-shortcuts-group d-flex align-items-center flex-nowrap" style="gap: 4px;">
        <button type="button" tabindex="-1" class="btn btn-xs font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('search_item');" title="Search Item / Barcode (F2)">
            <span class="badge badge-primary mr-1">F2</span> Item Search
        </button>

        <button type="button" tabindex="-1" class="btn btn-xs font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('new_entry');" title="Add New Item Row / New Entry (F3)">
            <span class="badge badge-info mr-1">F3</span> New Row
        </button>

        <button type="button" tabindex="-1" class="btn btn-xs font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('edit_entry');" title="Edit / Focus Row (F4)">
            <span class="badge badge-secondary mr-1">F4</span> Edit Qty
        </button>

        <button type="button" tabindex="-1" class="btn btn-xs btn-outline-success font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('save_form');" title="Save Bill / Tender (F6)">
            <span class="badge badge-success mr-1">F6</span> Save &amp; Tender
        </button>

        <button type="button" tabindex="-1" class="btn btn-xs font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('print_form');" title="Print Slip (F8)">
            <span class="badge badge-warning mr-1">F8</span> Print
        </button>

        <button type="button" tabindex="-1" class="btn btn-xs btn-outline-warning font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('clear_form');" title="Clear / Reset (F9)">
            <span class="badge badge-danger mr-1">F9</span> Reset
        </button>

        <button type="button" tabindex="-1" class="btn btn-xs font-weight-bold" onclick="if(window.posTriggerAction) window.posTriggerAction('close_modal');" title="Close Modal / Back (F10 / Esc)">
            <span class="badge badge-light mr-1">F10</span> Close/Back
        </button>
    </div>

    <div class="pos-jump-wrapper d-none d-lg-flex align-items-center flex-shrink-0 ml-2" style="gap: 4px; font-size: 0.75rem;">
        <span class="text-muted mr-1">Jump:</span>
        <a href="{{ url('sales/sales-bills/create') }}" accesskey="s" tabindex="-1" class="badge" title="Sales Bill (Alt+S)"><kbd class="bg-primary">Alt+S</kbd> Sales</a>
        <a href="{{ url('purchase/purchase-invoices/create') }}" accesskey="p" tabindex="-1" class="badge" title="Purchase Invoice (Alt+P)"><kbd class="bg-info">Alt+P</kbd> Purchase</a>
        <a href="{{ url('inventory/stock-transfers/create') }}" accesskey="t" tabindex="-1" class="badge" title="Stock Transfer (Alt+T)"><kbd class="bg-secondary">Alt+T</kbd> Transfer</a>
        <a href="{{ url('master/customers') }}" accesskey="c" tabindex="-1" class="badge d-none d-xl-inline-block" title="Customer Master (Alt+C)"><kbd class="bg-success">Alt+C</kbd> Cust</a>
        <a href="{{ url('master/items') }}" accesskey="i" tabindex="-1" class="badge d-none d-xl-inline-block" title="Item Master (Alt+I)"><kbd class="bg-secondary">Alt+I</kbd> Items</a>
        <a href="{{ url('tools/function-keys') }}" accesskey="k" tabindex="-1" class="badge text-warning" title="Configure Shortcuts (Alt+K)"><i class="fas fa-cog"></i> Config</a>
    </div>
</div>
