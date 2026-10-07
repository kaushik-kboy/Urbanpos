@php
    $ret = $salesReturn ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($ret?->items ?? collect());
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
<style>
.sr-exp-date[readonly] {
    pointer-events: none !important;
    user-select: none !important;
}
.sr-exp-date[readonly]::-webkit-calendar-picker-indicator {
    display: none !important;
}
#sr-header-fields-grid .btn-open-datepicker,
#sr-header-fields-grid .btn-date-settings-modal,
#sr-header-fields-grid .urbanpos-date-group .input-group-append,
#sr-items-table .btn-open-datepicker,
#sr-items-table .btn-date-settings-modal,
#sr-items-table .urbanpos-date-group .input-group-append {
    display: none !important;
}
#sr-header-fields-grid .urbanpos-date-group input,
#sr-items-table .urbanpos-date-group input {
    border-top-right-radius: 0.25rem !important;
    border-bottom-right-radius: 0.25rem !important;
}
</style>
@endpush

<div class="d-flex justify-content-between align-items-center mb-1 tx-compact-section-header">
    <h6 class="font-weight-bold text-dark mb-0"><i class="fas fa-undo text-primary mr-1"></i> Sales Return Header</h6>
    <x-form-layout-customizer
        form-key="sales_returns.header"
        container-id="sr-header-fields-grid"
        title="Customize Sales Return Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-1 tx-header-fields-grid" id="sr-header-fields-grid">
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-6 col-12 mb-1" data-field="customer_id" data-label="Customer" data-default-order="1" data-core="1">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="customer_id" class="font-weight-bold mb-0">Customer <span class="text-danger">*</span></label>
            <button type="button" class="btn btn-xs btn-primary font-weight-bold" id="sr-btn-open-history" title="Pick items from customer's previous sales bills">
                <i class="fas fa-history mr-1"></i> Pick History
            </button>
        </div>
        <select name="customer_id" id="customer_id" class="form-control select2" required>
            <option value="">-- Select Customer --</option>
            @php
                $selectedCustId = old('customer_id', $ret->customer_id ?? ($presetCustomerId ?? ''));
                $selectedBillId = old('sales_bill_id', $ret->sales_bill_id ?? ($presetBillId ?? ''));
            @endphp
            @foreach ($customers as $id => $name)
                <option value="{{ $id }}" @selected($selectedCustId == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    @php
        $selectedBranch = old('branch_id', $ret->branch_id ?? session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
    @endphp
    <input type="hidden" name="branch_id" id="branch_id" value="{{ $selectedBranch }}">
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-6 col-6 mb-1" data-field="return_date" data-label="Return Date" data-default-order="3" data-core="1">
        <label for="return_date" class="font-weight-bold mb-1">Return Date <span class="text-danger">*</span></label>
        <input type="date" name="return_date" id="return_date" class="form-control" value="{{ old('return_date', optional($ret->return_date ?? now())->format('Y-m-d')) }}" required>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-6 col-6 mb-1" data-field="sales_type" data-label="Sales Type" data-default-order="4" data-core="1">
        <label for="sales_type" class="font-weight-bold mb-1">Sales Type <span class="text-danger">*</span></label>
        <select name="sales_type" id="sales_type" class="form-control" required>
            <option value="Local" @selected(old('sales_type', $ret->sales_type ?? 'Local') === 'Local')>Local (CGST + SGST)</option>
            <option value="Interstate" @selected(old('sales_type', $ret->sales_type ?? '') === 'Interstate')>Interstate (IGST)</option>
        </select>
    </div>
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-6 col-6 mb-1" data-field="sales_bill_id" data-label="Original Sales Bill" data-default-order="5">
        <label for="sales_bill_id" class="font-weight-bold mb-1">Original Sales Bill</label>
        <select name="sales_bill_id" id="sales_bill_id" class="form-control select2">
            <option value="">-- No Bill / Direct Return --</option>
            @foreach ($salesBills as $id => $no)
                <option value="{{ $id }}" @selected($selectedBillId == $id)>{{ $no }}</option>
            @endforeach
        </select>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-6 col-6 mb-1" data-field="return_mode" data-label="Return Mode" data-default-order="6" data-core="1">
        <label for="return_mode" class="font-weight-bold mb-1">Return Mode <span class="text-danger">*</span></label>
        <select name="return_mode" id="return_mode" class="form-control" required>
            @foreach (['Cash' => 'Cash', 'Credit Note' => 'Credit Note', 'Wallet' => 'Wallet', 'Card' => 'Card', 'RRN' => 'RRN'] as $val => $lbl)
                <option value="{{ $val }}" @selected(old('return_mode', $ret->return_mode ?? 'Cash') === $val)>{{ $lbl }}</option>
            @endforeach
        </select>
    </div>
</div>

<hr>
{{-- Smart Bill Item Picker: shown when a Sales Bill is selected --}}
<div id="sr-bill-picker-wrap" class="card border-primary mb-3 bg-light shadow-sm" style="display: none;">
    <div class="card-body py-2 px-3">
        {{-- Invoice Summary Row --}}
        <div id="sr-bill-summary" class="alert alert-info py-2 px-3 mb-2 d-none">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <i class="fas fa-file-invoice mr-1"></i>
                    <strong>Original Invoice:</strong>
                    <span id="sr-bill-summary-num" class="font-weight-bold text-dark ml-1"></span>
                    <span class="text-muted small ml-2" id="sr-bill-summary-date"></span>
                </div>
                <div class="d-flex flex-wrap mt-1 mt-md-0">
                    <span class="mr-3"><span class="text-muted small">Discount:</span> <strong class="text-danger" id="sr-bill-summary-disc">₹0.00</strong></span>
                    <span class="mr-3"><span class="text-muted small">GST:</span> <strong class="text-primary" id="sr-bill-summary-gst">₹0.00</strong></span>
                    <span><span class="text-muted small">Invoice Total:</span> <strong class="text-success h6 mb-0" id="sr-bill-summary-total">₹0.00</strong></span>
                </div>
            </div>
        </div>
        <div class="row align-items-center">
            <div class="col-12">
                <label class="small font-weight-bold text-primary mb-1">
                    <i class="fas fa-receipt mr-1"></i> Select Item(s) from Sales Bill to Return:
                </label>
                <div id="sr-bill-item-checklist" class="border rounded bg-white p-2" style="max-height: 220px; overflow-y: auto;" tabindex="0">
                    <div class="text-muted small p-2">-- Load a Sales Bill above to see its items --</div>
                </div>
                <div id="sr-bill-item-error" class="text-danger font-weight-bold small mt-1" style="display: none;"></div>
                <small class="text-muted">Check the item(s) you want to return — checked items appear in Return Items below immediately; uncheck to remove them again.</small>
            </div>
        </div>
    </div>
</div>

@php
    $srItemColumns = [
        'code'         => ['label' => 'Code / Barcode', 'default' => true],
        'item'         => ['label' => 'Item Description', 'default' => true],
        'expiry'       => ['label' => 'Exp Date', 'default' => true],
        'qty'          => ['label' => 'Qty', 'default' => true],
        'sell_price'   => ['label' => 'Sell Price', 'default' => true],
        'mrp'          => ['label' => 'MRP', 'default' => true],
        'disc_percent' => ['label' => 'Disc %', 'default' => true],
        'disc_amt'     => ['label' => 'Disc Amt', 'default' => true],
        'gst_percent'  => ['label' => 'GST %', 'default' => true],
        'net_amt'      => ['label' => 'Net Amount', 'default' => true],
        'actions'      => ['label' => 'Actions', 'default' => true],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-1 tx-compact-section-header">
    <h6 class="mb-0 font-weight-bold text-dark">
        <i class="fas fa-boxes mr-1 text-primary"></i> Return Items
    </h6>
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-outline-info btn-xs font-weight-bold mr-2" id="sr-btn-open-history-header" title="Pick items from customer's previous bills across multiple dates">
            <i class="fas fa-history mr-1"></i> Pick from Purchase History
        </button>
        <button type="button" class="btn btn-outline-danger btn-xs font-weight-bold mr-2 btn-reset-table" id="sr-btn-reset-table" title="Clear all table items and reset to 1 empty row">
            <i class="fas fa-undo mr-1"></i> Reset Table
        </button>
        <x-table-column-customizer
            table-key="sales.sales-returns.items"
            table-id="sr-items-table"
            :columns="$srItemColumns"
        />
        <button type="button" id="sr-add-row" class="btn btn-primary btn-xs font-weight-bold ml-2">
            <i class="fas fa-plus mr-1"></i> Add Row
        </button>
    </div>
</div>

<div class="table-responsive tx-items-scroll-container">
    <table class="table table-sm table-bordered table-hover table-items-dense mb-0" id="sr-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 115px;" data-col-key="code">Code / Barcode</th>
                <th style="min-width: 220px;" data-col-key="item">Item Description <span class="text-danger">*</span></th>
                <th style="width: 135px;" data-col-key="expiry">Exp Date</th>
                <th style="width: 90px;" class="text-right" data-col-key="qty">Qty <span class="text-danger">*</span></th>
                <th style="width: 110px;" class="text-right" data-col-key="sell_price">Sell Price <span class="text-danger">*</span></th>
                <th style="width: 100px;" class="text-right" data-col-key="mrp">MRP</th>
                <th style="width: 48px;" class="text-right" data-col-key="disc_percent">Disc %</th>
                <th style="width: 100px;" class="text-right" data-col-key="disc_amt">Disc Amt</th>
                <th style="width: 45px;" class="text-right" data-col-key="gst_percent">GST %</th>
                <th style="width: 115px;" class="text-right" data-col-key="net_amt">Net Amount</th>
                <th style="width: 40px;" class="text-center" data-col-key="actions"></th>
            </tr>
        </thead>
        <tbody id="sr-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-returns._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-returns._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="3" class="text-right align-middle">Summary Totals:</td>
                <td class="text-right align-middle text-primary" id="footer-sr-qty">0.000</td>
                <td colspan="2"></td>
                <td colspan="2" class="text-right align-middle text-danger" id="footer-sr-disc">₹0.00</td>
                <td></td>
                <td class="text-right align-middle text-success h6 mb-0" id="footer-sr-net">₹0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<hr class="tx-divider-compact my-1">
<div class="d-flex justify-content-between align-items-center mb-1 tx-compact-section-header">
    <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-calculator mr-1 text-primary"></i> Totals & Notes</h6>
    <x-form-layout-customizer 
        form-key="sales_returns.additional" 
        container-id="sr-additional-fields-grid" 
        button-text="Customize Layout" 
        button-class="btn btn-outline-primary btn-xs font-weight-bold shadow-sm" />
</div>

<div class="row g-2 form-fields-grid align-items-end mb-1" id="sr-additional-fields-grid">
    <div class="field-wrapper col-lg-3 col-md-4 col-sm-6 col-12" data-field="remarks" data-label="Remarks / Return Reason" data-default-order="1">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="remarks">Remarks / Return Reason</label>
            <input type="text" name="remarks" id="remarks" class="form-control" placeholder="Reason for customer return..." value="{{ old('remarks', $ret->remarks ?? '') }}">
        </div>
    </div>
    <div class="field-wrapper col-lg-1 col-md-2 col-sm-3 col-4" data-field="round_off" data-label="Round Off" data-default-order="2">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="round_off">Round Off</label>
            <input type="number" step="0.01" name="round_off" id="round_off" value="{{ old('round_off', $ret->round_off ?? 0) }}" class="form-control text-right">
        </div>
    </div>
    <div class="field-wrapper col-lg-1 col-md-2 col-sm-3 col-4" data-field="total_extra_cess" data-label="Total Extra Cess" data-default-order="3">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="total_extra_cess">Extra Cess</label>
            <input type="number" step="0.01" min="0" name="total_extra_cess" id="total_extra_cess" value="{{ old('total_extra_cess', $ret->total_extra_cess ?? 0) }}" class="form-control text-right">
        </div>
    </div>
    <div class="field-wrapper col-lg-1 col-md-2 col-sm-3 col-4" data-field="gst_calamity_cess" data-label="GST Calamity Cess" data-default-order="4">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1" for="gst_calamity_cess">Calamity Cess</label>
            <input type="number" step="0.01" min="0" name="gst_calamity_cess" id="gst_calamity_cess" value="{{ old('gst_calamity_cess', $ret->gst_calamity_cess ?? 0) }}" class="form-control text-right">
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="items_taxable_amount" data-label="Items Taxable Amount" data-default-order="5">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Items Taxable Amount:</label>
            <div class="form-control text-right font-weight-bold bg-light" style="line-height: 24px;" id="display-sr-taxable">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-3 col-sm-4 col-6" data-field="total_gst_tax" data-label="Total GST Tax" data-default-order="6">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-muted" style="white-space: nowrap;">Total GST Tax:</label>
            <div class="form-control text-right font-weight-bold text-primary bg-light" style="line-height: 24px;" id="display-sr-gst">₹0.00</div>
        </div>
    </div>
    <div class="field-wrapper col-lg-2 col-md-4 col-sm-4 col-12" data-field="net_return_amount" data-label="Net Return Amount" data-default-order="7">
        <div class="form-group mb-1">
            <label class="font-weight-bold mb-1 text-success" style="white-space: nowrap;">Net Return Amount:</label>
            <div class="form-control text-right font-weight-bold text-success bg-white border-success" style="line-height: 24px; font-size: 0.95rem;" id="display-sr-grand-total">₹0.00</div>
        </div>
    </div>
</div>

<x-custom-fields-renderer :module="'SalesReturn'" :model="$ret ?? null" :cardStyle="true" />

<template id="sr-row-template">
    @include('sales.sales-returns._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

{{-- ============================================================
     CUSTOMER PURCHASE HISTORY PICKER MODAL (Multi-Bill / Multi-Date)
     ============================================================ --}}
<div class="modal fade" id="sr-customer-history-modal" tabindex="-1" role="dialog" aria-labelledby="srHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 8px; overflow: hidden;">
            <div class="modal-header text-white py-3" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
                <div class="d-flex align-items-center">
                    <div class="bg-white text-primary rounded-circle mr-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-history fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0 text-white" id="srHistoryModalLabel">
                            Customer Purchase History Picker
                        </h5>
                        <small class="text-white-50">Select items across multiple invoices to return in this credit note</small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge badge-light text-primary font-weight-bold px-3 py-2 mr-3 shadow-sm" id="sr-cph-customer-badge" style="font-size: 13px;">
                        <i class="fas fa-user mr-1"></i> <span id="sr-cph-customer-name">Customer</span>
                    </span>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85; text-shadow: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="modal-body p-3 bg-light">
                {{-- Search & Date Filter Bar --}}
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-2">
                        <div class="row align-items-center g-2">
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                    <input type="text" id="sr-cph-search-input" class="form-control border-left-0" placeholder="Search item name, code, barcode, or bill #..." autocomplete="off">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="sr-cph-search-clear" title="Clear search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5 text-md-center">
                                <div class="btn-group btn-group-sm" role="group" id="sr-cph-date-pills">
                                    <button type="button" class="btn btn-outline-primary active" data-days="30">Last 30 Days</button>
                                    <button type="button" class="btn btn-outline-primary" data-days="60">Last 60 Days</button>
                                    <button type="button" class="btn btn-outline-primary" data-days="90">Last 90 Days</button>
                                    <button type="button" class="btn btn-outline-primary" data-days="all">All Time</button>
                                </div>
                            </div>
                            <div class="col-md-2 text-md-right text-right">
                                <span class="badge badge-info py-2 px-2 font-weight-bold" id="sr-cph-stats-badge">0 Bills Found</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Loading Spinner --}}
                <div id="sr-cph-loading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="mt-3 font-weight-bold text-muted">Fetching customer purchase history...</div>
                </div>

                {{-- Empty State --}}
                <div id="sr-cph-empty" class="text-center py-5 d-none">
                    <i class="fas fa-receipt fa-3x text-muted mb-3 opacity-50"></i>
                    <h5 class="text-muted font-weight-bold">No Invoices Found</h5>
                    <p class="text-muted mb-0">This customer has no purchase history in the selected time range.</p>
                </div>

                {{-- Accordion List of Bills --}}
                <div id="sr-cph-bills-container"></div>
            </div>

            <div class="modal-footer bg-white border-top d-flex justify-content-between align-items-center py-2 px-3">
                <div class="d-flex align-items-center">
                    <span class="badge badge-primary px-3 py-2 font-weight-bold mr-3" style="font-size: 13px;">
                        <span id="sr-cph-selected-count">0</span> item(s) selected
                    </span>
                    <span class="font-weight-bold text-dark" style="font-size: 14px;">
                        Est. Refund Total: <strong class="text-success h6 mb-0 ml-1" id="sr-cph-selected-total">₹0.00</strong>
                    </span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold px-3" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success btn-sm font-weight-bold px-4 shadow-sm" id="sr-cph-btn-add-selected" disabled>
                        <i class="fas fa-plus-circle mr-1"></i> Add Selected Items to Return
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     ITEM SEARCH MODAL for Sales Return — opens on Code/Barcode click
     ============================================================ --}}
<div class="modal fade" id="sr-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="srItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="srItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Return Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="sr-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="sr-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4 text-right d-flex justify-content-end align-items-center">
                        <x-table-column-customizer table-key="modal.sales-returns.item-search" table-id="sr-isl-items-table" button-class="btn btn-sm btn-outline-secondary mr-2" button-text="Columns" title="Customize Columns & Order" />
                        <button type="button" id="sr-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <div id="sr-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="sr-isl-no-results" class="text-center py-4">
                    <i class="fas fa-keyboard fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Start typing to search items…</p>
                </div>

                <div class="table-responsive d-none" id="sr-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="sr-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;" data-col-key="seq">#</th>
                                <th data-col-key="name">Product Name</th>
                                <th class="text-center" style="width: 120px;" data-col-key="code">Code</th>
                                <th class="text-center" style="width: 130px;" data-col-key="expiry">Expiry</th>
                                <th class="text-right" style="width: 85px;" data-col-key="sell_price">Sell Price</th>
                                <th class="text-right" style="width: 85px;" data-col-key="mrp">MRP</th>
                                <th class="text-right" style="width: 80px;" data-col-key="gst">GST %</th>
                                <th class="text-center" style="width: 80px;" data-col-key="action">Select</th>
                            </tr>
                        </thead>
                        <tbody id="sr-isl-items-body"></tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="sr-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
<script>
    (function () {
        let rowIndex = {{ max(count($existingItems), 1) }};
        let srActiveSearchRow = null;
        let srIslDebounce = null;
        const SR_ISL_URL = '{{ route("sales.sales-bills.item-list") }}';
        const SR_LOOKUP_URL = '{{ route("sales.sales-bills.lookup-item") }}';
        const SR_CURRENT_RETURN_ID = '{{ $ret?->id ?? "" }}';
        const IS_EDIT_MODE = {{ $ret ? 'true' : 'false' }};

        /* ----------------------------------------------------------------
           ITEM SEARCH MODAL — open on click of Code/Barcode field
           ---------------------------------------------------------------- */
        let srCancellingRow = null;

        let srMouseDown = false;
        $(document).on('mousedown', '.sr-item-code', function () {
            srMouseDown = true;
        });

        function checkCustomerAndOpenSrModal($input) {
            let custId = $('#customer_id').val();
            if (!custId) {
                validateSrHeader(true);
                return false;
            }
            if ($('#sales_bill_id').val()) {
                $('#sr-bill-item-error').text('Items are restricted to the selected Sales Bill. Please check items from the list above.').show();
                $('#sr-bill-item-checklist').focus();
                return false;
            }
            let $row = $input.closest('tr');
            if ($row.find('.sr-item-select').val()) return false;
            srActiveSearchRow = $row;
            let prefill = $.trim($input.val());
            $('#sr-isl-filter-name').val(prefill);
            $('#sr-isl-filter-code').val('');
            srFetchItemList();
            $('#sr-item-search-modal').modal('show');
            $('#sr-item-search-modal').one('shown.bs.modal', function () {
                $('#sr-isl-filter-name').focus().select();
            });
            return true;
        }

        function processSrItemLookup($row, itemId, query, isDirectLookup = false) {
            let custId = $('#customer_id').val();
            if (!custId) {
                validateSrHeader(true);
                return;
            }
            if ($('#sales_bill_id').val()) {
                $('#sr-bill-item-error').text('Items are restricted to the selected Sales Bill. Please check items from the list above.').show();
                $('#sr-bill-item-checklist').focus();
                return;
            }

            if (query && window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(query);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate bounce
                }
            }

            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let params = { branch_id: branchId, show_all: 1 };
            if (itemId) {
                params.item_id = itemId;
            } else if (query) {
                params.query = query;
                if (isDirectLookup) {
                    params.exact_match_only = 1;
                }
            } else {
                return;
            }

            $.getJSON(SR_LOOKUP_URL, params, function (res) {
                if (res && res.found && res.item) {
                    let it = res.item;
                    let batches = res.batches || [];
                    $row.data('last-processed-code', query || it.item_code || it.ean_upc_code || it.id);
                    $row.find('.sr-item-code').removeClass('is-invalid border-danger').val(it.item_code || it.ean_upc_code || it.id);
                    $row.find('.sr-item-desc').val(it.name + (it.item_code ? ' [' + it.item_code + ']' : ''));
                    $row.find('.sr-item-select').val(it.id);
                    let exp = batches.length && batches[0].exp_date ? batches[0].exp_date.toString().substring(0, 10) : (it.exp_date ? it.exp_date.toString().substring(0, 10) : '');
                    $row.find('.sr-exp-date').val(exp).attr('data-original-exp', exp).data('original-exp', exp);
                    let sell = batches.length && batches[0].sell_price > 0 ? batches[0].sell_price : (it.sell_price || 0);
                    let mrp = batches.length && batches[0].mrp > 0 ? batches[0].mrp : (it.mrp || 0);
                    $row.find('.sr-price').val(sell > 0 ? parseFloat(sell).toFixed(2) : '');
                    $row.find('.sr-mrp').val(mrp > 0 ? parseFloat(mrp).toFixed(2) : '');
                    $row.find('.sr-gst-percent').val(it.gst_tax ? it.gst_tax.percentage : (it.gst_percent || ''));
                    $row.find('.sr-disc-percent').val('').trigger('input');
                    $row.find('.sr-disc-amount').val('');
                    recalculateRow($row[0]);
                    setTimeout(function () {
                        $row.find('.sr-qty').focus().select();
                    }, 60);
                } else {
                    $row.data('last-processed-code', null);
                    $row.find('.sr-item-code').addClass('is-invalid border-danger');
                    let msg = "Product not found for this Item Code/Barcode.";
                    if (window.toastr) {
                        toastr.warning(msg, 'Item Not Found');
                    }
                    setTimeout(function () {
                        $row.find('.sr-item-code').focus().select();
                    }, 50);
                }
            });
        }

        // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
        $(document).off('keydown change input', '.sr-item-code')
            .on('keydown', '.sr-item-code', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        processSrItemLookup($row, null, val, true);
                    } else {
                        checkCustomerAndOpenSrModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        e.preventDefault();
                        processSrItemLookup($row, null, val, true);
                    } else {
                        e.preventDefault();
                        checkCustomerAndOpenSrModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkCustomerAndOpenSrModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr');
                    let itemId = $row.find('.sr-item-select').val();
                    if (!itemId && $('#sr-items-body tr').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr');
                        $row.remove();
                        updateSrRowNumbers();
                        calculateSrTotals();
                        if ($prevRow.length) {
                            $prevRow.find('.sr-qty').focus().select();
                        }
                    }
                }
            })
            .on('change', '.sr-item-code', function () {
                let $input = $(this);
                let query = $.trim($input.val());
                let $row = $input.closest('tr');
                if (!query) {
                    $row.find('.sr-item-select').val('');
                    $row.find('.sr-item-desc').val('');
                    $row.data('last-processed-code', '');
                    recalculateRow($row[0]);
                    return;
                }
                if ($row.data('last-processed-code') === query) return;
                processSrItemLookup($row, null, query, true);
            })
            .on('input', '.sr-item-code', function () {
                $(this).removeClass('is-invalid border-danger');
            });

        // Safeguards to prevent Expiry Date from ever disappearing or getting wiped out on click/keydown/blur
        $(document).on('focus', '.sr-exp-date[readonly]', function () {
            $(this).blur();
        });

        $(document).on('keydown', '.sr-exp-date', function (e) {
            if ($(this).prop('readonly') || $(this).attr('readonly') || e.which === 8 || e.which === 46) {
                e.preventDefault();
                return false;
            }
        });

        $(document).on('input change blur', '.sr-exp-date', function () {
            let $el = $(this);
            let orig = $el.attr('data-original-exp') || $el.data('original-exp');
            if (!$el.val() && orig) {
                $el.val(orig);
            }
        });

        // Clicking on description also opens item search modal
        $(document).on('click', '.sr-item-desc', function () {
            let $code = $(this).closest('tr').find('.sr-item-code');
            checkCustomerAndOpenSrModal($code);
        });

        // Tab starts from first field (customer_id) on page load
        setTimeout(function () {
            let $cust = $('#customer_id');
            if ($cust.length && $cust.data('select2')) {
                $cust.data('select2').$container.find('.select2-selection').focus();
            } else if ($cust.length) {
                $cust.focus();
            }
        }, 150);

        // Filter inputs — debounced
        $('#sr-isl-filter-name, #sr-isl-filter-code').on('input', function () {
            clearTimeout(srIslDebounce);
            srIslDebounce = setTimeout(srFetchItemList, 400);
        });

        $('#sr-isl-btn-clear').on('click', function () {
            $('#sr-isl-filter-name, #sr-isl-filter-code').val('');
            srFetchItemList();
        });

        let srIslCache = {};

        function srFetchItemList() {
            let srch = $('#sr-isl-filter-name').val().trim();
            let code = $('#sr-isl-filter-code').val().trim();
            let custId = $('#customer_id').val() || '';

            if (!srch && !code) {
                $('#sr-isl-loading').addClass('d-none');
                $('#sr-isl-table-wrap').addClass('d-none');
                $('#sr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-keyboard fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">Start typing to search customer purchased items…</p>'
                );
                $('#sr-isl-count-label').text('');
                return;
            }

            let cacheKey = srch + '|' + code + '|' + custId;
            if (srIslCache[cacheKey]) {
                srRenderItems(srIslCache[cacheKey]);
                return;
            }

            $('#sr-isl-loading').removeClass('d-none');
            $('#sr-isl-no-results').addClass('d-none');
            $('#sr-isl-table-wrap').addClass('d-none');

            // show_all=1: a RETURN must be possible for an item that is currently out of stock (it is coming back INTO stock).
            $.getJSON(SR_ISL_URL, { search: srch, code: code, customer_id: custId, show_all: 1 }, function (res) {
                $('#sr-isl-loading').addClass('d-none');
                srIslCache[cacheKey] = res.items || [];
                setTimeout(function () { delete srIslCache[cacheKey]; }, 60000);
                srRenderItems(res.items || []);
            }).fail(function () {
                $('#sr-isl-loading').addClass('d-none');
                $('#sr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-exclamation-circle fa-2x text-danger"></i>' +
                    '<p class="mt-2 text-muted">Error loading items. Please try again.</p>'
                );
            });
        }

        function srRenderItems(items) {
            let $tbody = $('#sr-isl-items-body');
            $tbody.empty();
            if (items.length === 0) {
                $('#sr-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#sr-isl-count-label').text('');
                return;
            }
            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = it.exp_date
                    ? `<span class="badge badge-danger px-2 py-1">${it.exp_date}</span>`
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                html += `
                    <tr class="sr-isl-item-row" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code || ''}"
                        data-name="${it.name || ''}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}"
                        data-gst="${it.gst_percent || 0}"
                        data-exp="${it.exp_date || ''}">
                        <td data-col-key="seq" class="align-middle text-center font-weight-bold text-muted">${idx+1}</td>
                        <td data-col-key="name" class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td data-col-key="code" class="align-middle text-center">${codeBadge}</td>
                        <td data-col-key="expiry" class="align-middle text-center">${expBadge}</td>
                        <td data-col-key="sell_price" class="align-middle text-right text-success font-weight-bold">${it.sell_price > 0 ? '\u20b9'+parseFloat(it.sell_price).toFixed(2) : '\u2014'}</td>
                        <td data-col-key="mrp" class="align-middle text-right text-muted">${it.mrp > 0 ? '\u20b9'+parseFloat(it.mrp).toFixed(2) : '\u2014'}</td>
                        <td data-col-key="gst" class="align-middle text-right">${it.gst_percent || 0}%</td>
                        <td data-col-key="action" class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 sr-isl-btn-select"
                                data-id="${it.id}">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            if (window.applyTablePreferences) {
                window.applyTablePreferences('sr-isl-items-table');
            }
            $('#sr-isl-table-wrap').removeClass('d-none');
            $('#sr-isl-no-results').addClass('d-none');
            $('#sr-isl-count-label').text(items.length + ' item(s) found');
            srIslSelectedIdx = items.length > 0 ? 0 : -1;
            updateSrModalHighlight();
        }

        let srIslSelectedIdx = -1;
        function updateSrModalHighlight() {
            let $rows = $('#sr-isl-items-body tr.sr-isl-item-row');
            $('#sr-isl-items-body tr').removeClass('table-primary');
            if (srIslSelectedIdx >= 0 && srIslSelectedIdx < $rows.length) {
                let $target = $rows.eq(srIslSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#sr-isl-table-wrap')[0];
                let rowEl = $target[0];
                if (container && rowEl) {
                    let cTop = container.scrollTop;
                    let cBottom = cTop + container.clientHeight;
                    let rTop = rowEl.offsetTop;
                    let rBottom = rTop + rowEl.clientHeight;
                    if (rTop < cTop) container.scrollTop = rTop;
                    else if (rBottom > cBottom) container.scrollTop = rBottom - container.clientHeight;
                }
            }
        }

        $('#sr-item-search-modal').on('keydown', function (e) {
            let $rows = $('#sr-isl-items-body tr.sr-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                srIslSelectedIdx = (srIslSelectedIdx + 1) >= $rows.length ? 0 : srIslSelectedIdx + 1;
                updateSrModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                srIslSelectedIdx = (srIslSelectedIdx - 1) < 0 ? $rows.length - 1 : srIslSelectedIdx - 1;
                updateSrModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                let $target = (srIslSelectedIdx >= 0 && srIslSelectedIdx < $rows.length)
                    ? $rows.eq(srIslSelectedIdx)
                    : $rows.first();
                if ($target.length) {
                    $target.trigger('click');
                }
            }
        });

        // Row or Select button click — populate the active row
        $(document).on('click', '.sr-isl-item-row, .sr-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('sr-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');
            let itemName = $row.data('name');
            let sell     = $row.data('sell');
            let mrp      = $row.data('mrp');
            let gst      = $row.data('gst');
            let exp      = $row.data('exp');

            if (!srActiveSearchRow || !itemId) return;

            srItemSelectedInModal = true;
            srCancellingRow = null;

            // Show item_id in Code column as requested
            srActiveSearchRow.find('.sr-item-code').val(itemId);
            srActiveSearchRow.find('.sr-item-desc').val(itemName + (itemCode ? ' [' + itemCode + ']' : ''));
            srActiveSearchRow.find('.sr-item-select').val(itemId);
            srActiveSearchRow.find('.sr-exp-date').val(exp || '');
            srActiveSearchRow.find('.sr-price').val(sell > 0 ? parseFloat(sell).toFixed(2) : '');
            srActiveSearchRow.find('.sr-mrp').val(mrp > 0 ? parseFloat(mrp).toFixed(2) : '');
            srActiveSearchRow.find('.sr-gst-percent').val(gst || '');
            srActiveSearchRow.find('.sr-disc-percent').val('').trigger('input');
            srActiveSearchRow.find('.sr-disc-amount').val('');

            // Focus qty
            srActiveSearchRow.find('.sr-qty').val('').focus();
            $('#sr-item-search-modal').modal('hide');
        });


        let srItemSelectedInModal = false;

        // When modal closes, cleanly dismiss
        $('#sr-item-search-modal').on('show.bs.modal', function () {
            srItemSelectedInModal = false;
            srCancellingRow = null;
        });

        $('#sr-item-search-modal').on('hide.bs.modal', function () {
            if (!srItemSelectedInModal && srActiveSearchRow && srActiveSearchRow.length) {
                let selectedId = srActiveSearchRow.find('.sr-item-select').val();
                if (!selectedId) {
                    srCancellingRow = srActiveSearchRow;
                }
            }
        });
        $('#sr-item-search-modal').on('hidden.bs.modal', function () {
            if (!srItemSelectedInModal && srCancellingRow && srCancellingRow.length) {
                let totalRows = $('#sr-items-body tr').length;
                if (totalRows > 1) {
                    srCancellingRow.remove();
                    updateSrRowNumbers();
                    calculateSrTotals();
                } else {
                    srCancellingRow.find('.sr-item-code').val('');
                    srCancellingRow.find('.sr-item-desc').val('');
                }
                srCancellingRow = null;
                srActiveSearchRow = null;
                setTimeout(function () {
                    let $target = $('#sr-add-row, #freight, button[type=submit]');
                    $target.first().focus();
                }, 60);
                return;
            }
            srItemSelectedInModal = false;
            srCancellingRow = null;
            srActiveSearchRow = null;
        });

        function recalculateRow(row, triggerSource = null) {
            const qty = parseFloat(row.querySelector('.sr-qty')?.value) || 0;
            const price = parseFloat(row.querySelector('.sr-price')?.value) || 0;
            let discPercent = parseFloat(row.querySelector('.sr-disc-percent')?.value) || 0;
            let discAmount = parseFloat(row.querySelector('.sr-disc-amount')?.value) || 0;
            const gstPercent = parseFloat(row.querySelector('.sr-gst-percent')?.value) || 0;

            const base = qty * price;
            const discPctInput = row.querySelector('.sr-disc-percent');
            const discAmtInput = row.querySelector('.sr-disc-amount');

            // Requirement 2, 3, 4: Bidirectional discount sync
            if (triggerSource === 'percent') {
                if (base > 0 && discPercent > 0) {
                    discAmount = Math.round((base * discPercent / 100) * 100) / 100;
                } else {
                    discAmount = 0;
                }
                if (discAmtInput) {
                    discAmtInput.value = discAmount > 0 ? discAmount.toFixed(2) : '';
                }
            } else if (triggerSource === 'amount') {
                if (base > 0 && discAmount > 0) {
                    discPercent = Math.round(((discAmount / base) * 100) * 100) / 100;
                } else {
                    discPercent = 0;
                }
                if (discPctInput) {
                    discPctInput.value = discPercent > 0 ? discPercent : '';
                }
            } else if (triggerSource === 'qty') {
                if (base > 0 && discPercent > 0) {
                    discAmount = Math.round((base * discPercent / 100) * 100) / 100;
                    if (discAmtInput) {
                        discAmtInput.value = discAmount > 0 ? discAmount.toFixed(2) : '';
                    }
                } else if (base <= 0) {
                    discAmount = 0;
                    if (discAmtInput) discAmtInput.value = '';
                }
            } else {
                if (discPercent > 0 && base > 0 && (discAmount <= 0 || !discAmtInput || !discAmtInput.value)) {
                    discAmount = Math.round((base * discPercent / 100) * 100) / 100;
                    if (discAmtInput) {
                        discAmtInput.value = discAmount > 0 ? discAmount.toFixed(2) : '';
                    }
                } else if (discAmount > 0 && base > 0 && discPercent <= 0) {
                    discPercent = Math.round(((discAmount / base) * 100) * 100) / 100;
                    if (discPctInput) {
                        discPctInput.value = discPercent > 0 ? discPercent : '';
                    }
                }
            }

            // GST included in price (Tax-Inclusive matching Sales Bill)
            const net = Math.max(0, base - discAmount);
            const taxable = gstPercent > 0 ? (net / (1 + (gstPercent / 100))) : net;
            const gstAmount = Math.round((net - taxable) * 100) / 100;

            if (discPctInput) {
                if (discPercent < 0 || discPercent > 100) {
                    discPctInput.classList.add('border-danger', 'text-danger', 'is-invalid');
                    discPctInput.title = 'Discount % cannot exceed 100%';
                } else {
                    discPctInput.classList.remove('border-danger', 'text-danger', 'is-invalid');
                    discPctInput.title = '';
                }
            }

            if (discAmtInput) {
                if (discAmount < 0 || (base > 0 && discAmount > base)) {
                    discAmtInput.classList.add('border-danger', 'text-danger', 'is-invalid');
                    discAmtInput.title = 'Discount amount cannot exceed item gross total (₹' + base.toFixed(2) + ')';
                } else {
                    discAmtInput.classList.remove('border-danger', 'text-danger', 'is-invalid');
                    discAmtInput.title = '';
                }
            }

            const netSpan = row.querySelector('.sr-net-amount');
            if (netSpan) {
                netSpan.innerText = net.toFixed(2);
            }

            const qtyInput = row.querySelector('.sr-qty');
            if (qtyInput) {
                validateSrQuantity($(qtyInput), false);
            }

            return { qty, price, discAmount, taxable, gstAmount, net };
        }

        function validateSrQuantity($input) {
            let $row = $input.closest('tr');
            let itemId = $row.find('.sr-item-select').val();
            let enteredQty = parseFloat($input.val()) || 0;
            let $errBox = $row.find('.sr-qty-error-msg');

            let origQtyStr = $input.attr('data-original-qty');
            if (origQtyStr === undefined || origQtyStr === '' || origQtyStr === null) {
                $input.removeClass('is-invalid border-danger');
                $errBox.text('').hide();
                return true;
            }

            let origQty = parseFloat(origQtyStr) || 0;
            let returnedQty = parseFloat($input.attr('data-returned-qty')) || 0;
            let remainingQty = parseFloat($input.attr('data-remaining-qty'));
            if (isNaN(remainingQty)) {
                remainingQty = Math.max(0, origQty - returnedQty);
            }

            // Sum quantities across all rows for this same item_id
            let totalRequestedForThisItem = 0;
            $('#sr-items-body tr.sr-item-row').each(function () {
                let thisItemId = $(this).find('.sr-item-select').val();
                if (thisItemId && String(thisItemId) === String(itemId)) {
                    totalRequestedForThisItem += (parseFloat($(this).find('.sr-qty').val()) || 0);
                }
            });

            let remDisplay = (remainingQty === parseInt(remainingQty, 10)) ? parseInt(remainingQty, 10) : remainingQty;

            if (remainingQty <= 0 && origQty > 0) {
                let errMsg = "No returnable quantity available for this item.";
                $input.addClass('is-invalid border-danger').attr('title', errMsg);
                $errBox.text(errMsg).show();
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) { submitBtn.disabled = true; submitBtn.title = errMsg; }
                return false;
            }

            let isOverLimit = (origQty > 0) && ((totalRequestedForThisItem > remainingQty + 0.0001) || (enteredQty > remainingQty + 0.0001));

            if (origQty > 0 && isOverLimit) {
                let errMsg = "Maximum available quantity is " + remDisplay + ".";
                $input.addClass('is-invalid border-danger').attr('title', errMsg);
                $errBox.text(errMsg).show();
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) { submitBtn.disabled = true; submitBtn.title = errMsg; }
                return false;
            } else if (enteredQty <= 0) {
                let errMsg = "Quantity must be greater than 0.";
                $input.addClass('is-invalid border-danger').attr('title', errMsg);
                $errBox.text(errMsg).show();
                const submitBtn = document.querySelector('button[type="submit"]');
                if (submitBtn) { submitBtn.disabled = true; submitBtn.title = errMsg; }
                return false;
            } else {
                $input.removeClass('is-invalid border-danger').removeAttr('title');
                $errBox.text('').hide();
                return true;
            }
        }

        function recalculateAll(isManualRoundOff) {
            let totalQty = 0;
            let totalDisc = 0;
            let totalTaxable = 0;
            let totalGst = 0;
            let totalNet = 0;

            document.querySelectorAll('#sr-items-body .sr-item-row').forEach(row => {
                const res = recalculateRow(row);
                totalQty += res.qty;
                totalDisc += res.discAmount;
                totalTaxable += res.taxable;
                totalGst += res.gstAmount;
                totalNet += res.net;
            });

            const extraCess = parseFloat(document.getElementById('total_extra_cess')?.value) || 0;
            const calamityCess = parseFloat(document.getElementById('gst_calamity_cess')?.value) || 0;
            const rawTotal = totalNet + extraCess + calamityCess;

            let roundOff = 0;
            let grandTotal = 0;
            const roundOffInput = document.getElementById('round_off');

            if (isManualRoundOff) {
                roundOff = parseFloat(roundOffInput?.value) || 0;
                grandTotal = Math.round((rawTotal + roundOff) * 100) / 100;
            } else {
                const roundedTotal = Math.round(rawTotal);
                roundOff = Math.round((roundedTotal - rawTotal) * 100) / 100;
                if (roundOffInput) {
                    roundOffInput.value = roundOff !== 0 ? roundOff.toFixed(2) : '0.00';
                }
                grandTotal = roundedTotal;
            }

            document.getElementById('footer-sr-qty').innerText = totalQty.toFixed(3);
            document.getElementById('footer-sr-disc').innerText = '₹' + totalDisc.toFixed(2);
            document.getElementById('footer-sr-net').innerText = '₹' + totalNet.toFixed(2);
            document.getElementById('display-sr-taxable').innerText = '₹' + totalTaxable.toFixed(2);
            document.getElementById('display-sr-gst').innerText = '₹' + totalGst.toFixed(2);
            document.getElementById('display-sr-grand-total').innerText = '₹' + grandTotal.toFixed(2);

            // Update universal rich footer
            const displaySrFinal = document.getElementById('display-sr-final-total');
            if (displaySrFinal) {
                displaySrFinal.innerText = grandTotal.toFixed(2);
            }
            const itemsBadge = document.getElementById('sr-total-items-badge');
            if (itemsBadge) {
                const rowCount = document.querySelectorAll('#sr-items-body .sr-item-row').length;
                itemsBadge.innerHTML = '<span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">' + rowCount + ' Items</span>';
            }

            const submitBtn = document.querySelector('button[type="submit"], #sr-main-save-btn');
            if (submitBtn) {
                const hasValidCust = !!$('#customer_id').val();
                let hasInvalidQty = false;
                $('#sr-items-body .sr-qty').each(function () {
                    if ($(this).hasClass('is-invalid')) hasInvalidQty = true;
                });
                let hasInvalidDisc = false;
                $('#sr-items-body .sr-disc-percent, #sr-items-body .sr-disc-amount').each(function () {
                    if ($(this).hasClass('is-invalid')) hasInvalidDisc = true;
                });
                const hasValidItems = totalQty > 0 && !hasInvalidQty && !hasInvalidDisc && document.querySelectorAll('#sr-items-body .sr-item-row').length > 0;
                let isValid = hasValidCust && hasValidItems;
                submitBtn.disabled = !isValid;
                submitBtn.classList.toggle('disabled', !isValid);
                if (!hasValidCust) {
                    submitBtn.title = 'Please select a Customer.';
                } else if (hasInvalidQty) {
                    submitBtn.title = 'Please resolve quantity errors before submitting.';
                } else if (hasInvalidDisc) {
                    submitBtn.title = 'Please resolve discount errors before submitting.';
                } else if (!hasValidItems) {
                    submitBtn.title = 'Please add at least one item with valid quantity.';
                } else {
                    submitBtn.title = '';
                }
            }
        }

        $(document).on('change', '#customer_id', function () {
            recalculateAll();
        });

        document.getElementById('sr-btn-reset-table')?.addEventListener('click', function () {
            const tbody = document.getElementById('sr-items-body');
            const template = document.getElementById('sr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', 0);
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            tbody.innerHTML = '';
            tbody.appendChild(tempWrapper.firstElementChild);
            rowIndex = 1;
            $('#sr-bill-item-checklist input[type="checkbox"]').prop('checked', false);
            recalculateAll();
            setTimeout(function () {
                tbody.querySelector('.sr-item-code')?.focus();
            }, 50);
        });

        function canAddSrRow() {
            let $lastRow = $('#sr-items-body tr').last();
            if ($lastRow.length) {
                let itemId = $lastRow.find('.sr-item-id').val();
                let qtyVal = parseFloat($lastRow.find('.sr-qty').val()) || 0;

                if (!itemId) {
                    let msg = 'Pehle current row me item select karein.';
                    if (window.toastr) toastr.warning(msg, 'Incomplete Row');
                    else alert(msg);
                    $lastRow.find('.sr-item-code').focus();
                    return false;
                }

                if (qtyVal <= 0) {
                    let msg = 'Pehle item ki valid quantity enter karein.';
                    if (window.toastr) toastr.warning(msg, 'Quantity Required');
                    else alert(msg);
                    $lastRow.find('.sr-qty').focus().select();
                    return false;
                }
            }
            return true;
        }

        document.getElementById('sr-add-row')?.addEventListener('click', function (e) {
            if (!$('#customer_id').val()) {
                e?.preventDefault?.();
                validateSrHeader(true, ['customer']);
                return false;
            }
            if (!canAddSrRow()) {
                e?.preventDefault?.();
                return false;
            }
            const template = document.getElementById('sr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('sr-items-body');
            const tempWrapper = document.createElement('tbody');
            tempWrapper.innerHTML = html;
            const newRow = tempWrapper.firstElementChild;
            tbody.appendChild(newRow);
            setTimeout(function () {
                newRow.querySelector('.sr-item-code')?.focus();
            }, 50);
            rowIndex++;
            recalculateAll();
        });

        document.getElementById('sr-items-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.sr-row-remove');
            if (!btn) return;
            const row = btn.closest('tr');
            const itemId = row.querySelector('.sr-item-select')?.value;
            row.remove();
            // Keep the bill-item checkbox list in sync: manually removing a row
            // here should uncheck its checkbox above, not leave it stuck checked.
            if (itemId) {
                $(`.sr-bill-item-checkbox[data-item-id="${itemId}"]`).prop('checked', false);
            }
            recalculateAll();
        });

        document.getElementById('sr-items-body')?.addEventListener('input', function (e) {
            if (e.target.matches('.sr-disc-percent')) {
                recalculateRow(e.target.closest('tr'), 'percent');
                recalculateAll();
            } else if (e.target.matches('.sr-disc-amount')) {
                recalculateRow(e.target.closest('tr'), 'amount');
                recalculateAll();
            } else if (e.target.matches('.sr-qty')) {
                recalculateRow(e.target.closest('tr'), 'qty');
                recalculateAll();
            } else if (e.target.matches('.sr-price, .sr-mrp, .sr-gst-percent')) {
                recalculateAll();
            }
        });

        $(document).on('keydown', '.sr-qty', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                if (!validateSrQuantity($(this))) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    $(this).focus();
                    return false;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).closest('tr').find('.sr-disc-percent').focus().select();
                }
            }
        });

        $(document).off('keydown', '.sr-disc-amount, .sr-gst-percent').on('keydown', '.sr-disc-amount, .sr-gst-percent', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if ($nextRow.length) {
                    e.preventDefault();
                    $nextRow.find('.sr-item-code').focus();
                } else if (!$('#sales_bill_id').val()) {
                    e.preventDefault();
                    if (!canAddSrRow()) return;
                    $('#sr-add-row').trigger('click');
                }
            }
        });

        // Real-time listener for sr-qty typing, +, -, paste
        const SR_SOLD_QTY_URL = '{{ route("sales.sales-returns.item-sold-qty") }}';
        let srQtyDebounce = {};

        $(document).on('input change keyup', '.sr-qty', function () {
            let $input = $(this);
            let $row = $input.closest('tr');

            // Case 1: bill is selected
            if ($('#sales_bill_id').val()) {
                validateSrQuantity($input);
                recalculateRow($row, 'qty');
                recalculateAll();
                return;
            }

            // Case 2: no bill selected — AJAX check against total sold
            let itemId = $row.find('.sr-item-select').val();
            if (!itemId) {
                recalculateAll();
                return;
            }
            let customerId = $('#customer_id').val() || '';
            let rowKey = $row.index();
            clearTimeout(srQtyDebounce[rowKey]);
            srQtyDebounce[rowKey] = setTimeout(function () {
                $.getJSON(SR_SOLD_QTY_URL, { item_id: itemId, customer_id: customerId, ignore_return_id: SR_CURRENT_RETURN_ID }, function (res) {
                    if (res && res.available !== null) {
                        let avail = parseFloat(res.available);
                        $input.attr('data-max-no-bill', avail);
                        let $maxLabel = $row.find('.sr-max-qty-label');
                        if ($maxLabel.length) {
                            $maxLabel.text('Max: ' + avail.toFixed(3)).show();
                        }
                        let currentVal = parseFloat($input.val()) || 0;
                        // Inline error (no toast, no silent auto-adjust of a money quantity): the user corrects it.
                        if (currentVal > avail) {
                            let msg = 'Maximum returnable quantity is ' + (avail % 1 === 0 ? avail.toFixed(0) : avail.toFixed(3)) + ' (no bill selected: what this customer bought minus what was already returned).';
                            $input.addClass('is-invalid border-danger').attr('title', msg);
                            if ($maxLabel.length) {
                                $maxLabel.text(msg).addClass('text-danger font-weight-bold').show();
                            }
                        } else {
                            $input.removeClass('is-invalid border-danger').attr('title', '');
                            if ($maxLabel.length) {
                                $maxLabel.removeClass('text-danger font-weight-bold');
                            }
                        }
                        recalculateAll();
                    }
                });
            }, 400);
            recalculateAll();
        });


        document.getElementById('round_off')?.addEventListener('input', function() { recalculateAll(true); });
        document.getElementById('total_extra_cess')?.addEventListener('input', recalculateAll);
        document.getElementById('gst_calamity_cess')?.addEventListener('input', recalculateAll);

        // Customer Select2 Remote AJAX search (search any customer by name or mobile)
        let $custSelect = $('#customer_id');
        if ($custSelect.hasClass('select2-hidden-accessible')) {
            $custSelect.select2('destroy');
        }
        $custSelect.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Search Customer by Name or Mobile --',
            allowClear: true,
            ajax: {
                url: '{{ route("sales.sales-bills.customer-search") }}',
                dataType: 'json',
                delay: 200,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true
            }
        });

        // Smart Bill Items Picker & Automatic Loader when Bill is selected
        let cachedBillItems = [];
        let isAutoLoadingBill = false;

        function loadBillItems(billId, preserveExisting = false) {
            if (!billId || isAutoLoadingBill) return;

            isAutoLoadingBill = true;
            const tbody = document.getElementById('sr-items-body');
            const originalRows = tbody.innerHTML;
            if (!preserveExisting) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-primary"><i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2 font-weight-bold">Loading items from sales bill...</div></td></tr>';
            }

            let billUrl = `/sales/sales-returns/bill-items/${billId}`;
            if (SR_CURRENT_RETURN_ID) {
                billUrl += `?ignore_return_id=${SR_CURRENT_RETURN_ID}`;
            }

            fetch(billUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.branch_id) {
                    $('#branch_id').val(data.branch_id).trigger('change');
                }
                if (data.sales_type) {
                    $('#sales_type').val(data.sales_type).trigger('change');
                }

                // Populate invoice summary panel
                if (data.bill_number) {
                    $('#sr-bill-summary-num').text(data.bill_number);
                    $('#sr-bill-summary-date').text(data.bill_date ? '(' + data.bill_date + ')' : '');
                    $('#sr-bill-summary-disc').text('₹' + parseFloat(data.disc_amount || 0).toFixed(2));
                    $('#sr-bill-summary-gst').text('₹' + parseFloat(data.total_gst || 0).toFixed(2));
                    $('#sr-bill-summary-total').text('₹' + parseFloat(data.bill_total || 0).toFixed(2));
                    $('#sr-bill-summary').removeClass('d-none');
                }

                cachedBillItems = data.items || [];

                let hasActualItems = false;
                $('#sr-items-body .sr-item-row').each(function () {
                    if ($(this).find('.sr-item-select').val()) {
                        hasActualItems = true;
                    }
                });

                if (preserveExisting && hasActualItems) {
                    // Update max on existing rows
                    $('#sr-items-body .sr-item-row').each(function () {
                        let itemId = $(this).find('.sr-item-select').val();
                        let found = cachedBillItems.find(b => String(b.item_id) === String(itemId));
                        if (found) {
                            let remQty = typeof found.remaining_qty !== 'undefined' ? parseFloat(found.remaining_qty) : parseFloat(found.original_qty);
                            let origQty = parseFloat(found.original_qty) || 0;
                            let retQty = parseFloat(found.already_returned_qty) || 0;
                            $(this).find('.sr-qty')
                                .attr('max', remQty)
                                .attr('data-original-qty', origQty)
                                .attr('data-returned-qty', retQty)
                                .attr('data-remaining-qty', remQty);
                            let maxLabelText = (retQty > 0)
                                ? `Remaining: ${remQty} (Orig: ${origQty}, Ret: ${retQty})`
                                : `Max: ${origQty}`;
                            $(this).find('.sr-max-qty-label').text(maxLabelText).show();
                            $(this).find('.sr-item-code').prop('readonly', true);
                        }
                    });
                    renderBillItemChecklist();
                    recalculateAll();
                } else {
                    tbody.innerHTML = '';
                    let addedCount = 0;
                    cachedBillItems.forEach((item, idx) => {
                        let remQty = typeof item.remaining_qty !== 'undefined' ? parseFloat(item.remaining_qty) : parseFloat(item.original_qty);
                        if (remQty > 0) {
                            appendBillItemRow(item, remQty);
                            addedCount++;
                        }
                    });
                    if (addedCount === 0) {
                        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-info-circle text-info mr-1"></i> Original Sales Bill loaded, but all items have already been fully returned (0 returnable items).</td></tr>';
                    }
                    renderBillItemChecklist();
                    recalculateAll();
                }
            })
            .catch(err => {
                console.error(err);
                if (!preserveExisting) {
                    tbody.innerHTML = originalRows;
                }
                $('#sr-bill-item-error').text('Failed to load items from sales bill.').show();
            })
            .finally(() => {
                isAutoLoadingBill = false;
            });
        }

        function appendBillItemRow(item, initialQty) {
            const template = document.getElementById('sr-row-template').innerHTML;
            const html = template.replaceAll('__INDEX__', rowIndex);
            const tempTable = document.createElement('table');
            tempTable.innerHTML = '<tbody>' + html + '</tbody>';
            const row = tempTable.querySelector('tr');
            if (!row) return;

            // Fill item fields
            const hiddenId = row.querySelector('.sr-item-select');
            if (hiddenId) hiddenId.value = item.item_id;

            const hiddenBillId = row.querySelector('.sr-item-bill-id');
            if (hiddenBillId) hiddenBillId.value = $('#sales_bill_id').val() || item.sales_bill_id || '';

            const hiddenBillItemId = row.querySelector('.sr-item-bill-item-id');
            if (hiddenBillItemId) hiddenBillItemId.value = item.sales_bill_item_id || item.id || '';

            const badgeWrapper = row.querySelector('.sr-bill-badge-wrapper');
            const badgeText = row.querySelector('.sr-bill-badge-text');
            const billNumber = $('#sr-bill-summary-num').text() || (item.bill_number ? item.bill_number : '');
            if (badgeWrapper && badgeText && billNumber) {
                badgeText.innerText = 'Bill #' + billNumber;
                badgeWrapper.style.display = 'block';
            }

            const codeInput = row.querySelector('.sr-item-code');
            if (codeInput) {
                codeInput.value = item.item_code || item.item_id;
                codeInput.readOnly = true;
                codeInput.title = 'Item from Sales Bill (cannot be changed)';
            }

            const descInput = row.querySelector('.sr-item-desc');
            if (descInput) descInput.value = item.item_name + (item.item_code ? ' [' + item.item_code + ']' : '');

            const remQty = typeof item.remaining_qty !== 'undefined' ? parseFloat(item.remaining_qty) : parseFloat(item.original_qty);
            const origQty = typeof item.original_qty !== 'undefined' ? parseFloat(item.original_qty) : remQty;
            const retQty = typeof item.already_returned_qty !== 'undefined' ? parseFloat(item.already_returned_qty) : 0;

            const qtyInput = row.querySelector('.sr-qty');
            if (qtyInput) {
                qtyInput.value = initialQty;
                qtyInput.max = remQty;
                qtyInput.setAttribute('data-original-qty', origQty);
                qtyInput.setAttribute('data-returned-qty', retQty);
                qtyInput.setAttribute('data-remaining-qty', remQty);
            }

            const maxLabel = row.querySelector('.sr-max-qty-label');
            if (maxLabel) {
                let maxLabelText = (retQty > 0)
                    ? `Remaining: ${remQty} (Orig: ${origQty}, Ret: ${retQty})`
                    : `Max: ${origQty}`;
                maxLabel.innerText = maxLabelText;
                maxLabel.style.display = 'block';
            }

            const priceInput = row.querySelector('.sr-price');
            if (priceInput) priceInput.value = parseFloat(item.sell_price).toFixed(2);

            const mrpInput = row.querySelector('.sr-mrp');
            if (mrpInput) mrpInput.value = parseFloat(item.mrp || 0).toFixed(2);

            const discPctInput = row.querySelector('.sr-disc-percent');
            if (discPctInput) discPctInput.value = item.disc_percent || 0;

            const discAmtInput = row.querySelector('.sr-disc-amount');
            if (discAmtInput) discAmtInput.value = item.disc_amount || 0;

            const gstPctInput = row.querySelector('.sr-gst-percent');
            if (gstPctInput) gstPctInput.value = item.gst_percent || 0;

            const expInput = row.querySelector('.sr-exp-date');
            if (expInput && item.exp_date) {
                expInput.value = item.exp_date;
                expInput.setAttribute('data-original-exp', item.exp_date);
            }

            document.getElementById('sr-items-body').appendChild(row);
            rowIndex++;
            recalculateAll();

            // Focus the quantity input
            setTimeout(() => {
                row.querySelector('.sr-qty')?.focus();
                row.querySelector('.sr-qty')?.select();
            }, 50);
        }

        // Render the checkbox list of items from the loaded Sales Bill. Re-run
        // whenever cachedBillItems changes AND whenever Return Items rows change
        // out from under it (bill reload, row removed) so checked-state always
        // reflects what's actually in Return Items below.
        function renderBillItemChecklist() {
            let $list = $('#sr-bill-item-checklist');
            if (cachedBillItems.length === 0) {
                $list.html('<div class="text-muted small p-2">Original bill has 0 items.</div>');
                return;
            }
            let html = '';
            cachedBillItems.forEach((item, idx) => {
                let codeStr = item.item_code ? ' [' + item.item_code + ']' : '';
                let expStr = item.exp_date ? ' (Exp: ' + item.exp_date + ')' : '';
                let remQty = typeof item.remaining_qty !== 'undefined' ? parseFloat(item.remaining_qty) : parseFloat(item.original_qty);
                let origQty = parseFloat(item.original_qty) || 0;
                let retQty = parseFloat(item.already_returned_qty) || 0;
                let isChecked = $(`#sr-items-body .sr-item-row .sr-item-select[value="${item.item_id}"]`).length > 0;
                let isExhausted = remQty <= 0 && !isChecked;
                let remStr = isExhausted
                    ? ' <span class="text-danger font-weight-bold">[No returnable qty available]</span>'
                    : (isChecked 
                        ? ` <span class="badge badge-primary px-2 py-1 ml-1"><i class="fas fa-check mr-1"></i>In Return</span> <span class="text-muted small">(Available: ${remQty}/${origQty})</span>`
                        : ` <span class="text-success font-weight-bold">[Available: ${remQty}/${origQty}]</span>`);
                html += `
                    <div class="custom-control custom-checkbox py-1 border-bottom">
                        <input type="checkbox" class="custom-control-input sr-bill-item-checkbox" id="sr-bic-${idx}" data-idx="${idx}" data-item-id="${item.item_id}"${isExhausted ? ' disabled' : ''}${isChecked ? ' checked' : ''}>
                        <label class="custom-control-label w-100${isExhausted ? ' text-muted' : ''}" for="sr-bic-${idx}" style="cursor:pointer;">
                            <strong>${item.item_name}</strong>${codeStr} &mdash; Sold: ${origQty}${retQty > 0 ? ' (Other Returns: ' + retQty + ')' : ''} @ &#8377;${parseFloat(item.sell_price).toFixed(2)}${expStr}${remStr}
                        </label>
                    </div>`;
            });
            $list.html(html);
        }

        // Add one bill item (by its index into cachedBillItems) to the Return
        // Items table. Inline validation, no alerts — matches this form's
        // convention elsewhere.
        function addBillItemByIndex(idx) {
            let $errBox = $('#sr-bill-item-error');
            $errBox.hide().text('');

            let item = cachedBillItems[idx];
            if (!item) return;

            let remQty = typeof item.remaining_qty !== 'undefined' ? parseFloat(item.remaining_qty) : parseFloat(item.original_qty);
            if (remQty <= 0) {
                $errBox.text('No returnable quantity available for this item.').show();
                $(`.sr-bill-item-checkbox[data-idx="${idx}"]`).prop('checked', false);
                return;
            }

            let tbody = document.getElementById('sr-items-body');

            // Already in the table (shouldn't normally happen since the checkbox
            // reflects this, but guards a stale double-fire) — just focus it.
            let existingRow = $(tbody).find(`.sr-item-row .sr-item-select[value="${item.item_id}"]`).closest('tr');
            if (existingRow.length > 0) {
                existingRow.find('.sr-qty').focus().select();
                return;
            }

            // Remove placeholder if present
            if ($(tbody).find('td[colspan]').length > 0) {
                tbody.innerHTML = '';
            }

            appendBillItemRow(item, remQty);
        }

        // Remove this item's row from Return Items (fired when its checkbox is
        // unchecked).
        function removeReturnRowForItem(itemId) {
            $('#sr-items-body .sr-item-row').each(function () {
                if (String($(this).find('.sr-item-select').val()) === String(itemId)) {
                    $(this).remove();
                }
            });
            if ($('#sr-items-body .sr-item-row').length === 0) {
                document.getElementById('sr-items-body').innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-hand-pointer text-primary mr-1"></i> Check an item above to add it to Return Items.</td></tr>';
            }
            recalculateAll();
        }

        $(document).on('change', '.sr-bill-item-checkbox', function () {
            let idx = $(this).data('idx');
            if ($(this).is(':checked')) {
                addBillItemByIndex(idx);
            } else {
                let item = cachedBillItems[idx];
                if (item) removeReturnRowForItem(item.item_id);
            }
        });

        function updateBillModeUI() {
            let billId = $('#sales_bill_id').val();
            if (billId) {
                $('#sr-add-row').hide();
                $('#sr-bill-picker-wrap').slideDown(200);
            } else {
                $('#sr-add-row').show();
                $('#sr-bill-picker-wrap').slideUp(200);
                $('#sr-bill-summary').addClass('d-none');
                cachedBillItems = [];
                $('#sr-items-body .sr-item-row').each(function () {
                    $(this).find('.sr-qty').removeAttr('max').removeAttr('data-original-qty').removeAttr('data-returned-qty').removeAttr('data-remaining-qty');
                    $(this).find('.sr-max-qty-label').hide();
                    $(this).find('.sr-item-code').prop('readonly', false);
                });
            }
        }

        $('#sales_bill_id').on('change', function () {
            let billId = $(this).val();
            updateBillModeUI();
            $('#sr-bill-item-error').hide().text('');
            cachedBillItems = [];
            $('#sr-bill-item-checklist').html('<div class="text-muted small p-2">-- Load a Sales Bill above to see its items --</div>');

            const tbody = document.getElementById('sr-items-body');
            if (billId) {
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1 text-primary"></i> Loading items from sales bill...</td></tr>';
                }
                loadBillItems(billId, false);
            } else {
                if (tbody) {
                    tbody.innerHTML = '';
                }
                recalculateAll();
            }
        });

        // Customer Sales Bills filter: only show invoices belonging to selected customer
        let customerBillsLoading = false;
        function loadCustomerBills(customerId, selectedBillId = null, onDone = null) {
            let $billSelect = $('#sales_bill_id');
            if (!customerId) {
                $billSelect.html('<option value="">-- No Original Bill / Direct Return --</option>').val('').trigger('change.select2');
                $('#sr-bill-picker-wrap').slideUp(200);
                $('#sr-bill-summary').addClass('d-none');
                $('#sr-bill-item-error').hide().text('');
                const tbody = document.getElementById('sr-items-body');
                if (tbody) tbody.innerHTML = '';
                cachedBillItems = [];
                recalculateAll();
                if (onDone) onDone(null);
                return;
            }

            customerBillsLoading = true;
            $billSelect.prop('disabled', true);

            let custBillsUrl = '/sales/sales-returns/customer-bills/' + customerId;
            if (SR_CURRENT_RETURN_ID) {
                custBillsUrl += '?ignore_return_id=' + SR_CURRENT_RETURN_ID;
            }

            $.getJSON(custBillsUrl, function (bills) {
                let html = '<option value="">-- No Original Bill / Direct Return --</option>';
                if (bills && bills.length > 0) {
                    bills.forEach(function (b) {
                        let sel = (selectedBillId && String(b.id) === String(selectedBillId)) ? 'selected' : '';
                        html += `<option value="${b.id}" ${sel}>${b.label || b.bill_number}</option>`;
                    });
                }
                $billSelect.html(html);
                if (selectedBillId) {
                    $billSelect.val(selectedBillId);
                } else {
                    $billSelect.val('');
                }
            }).fail(function () {
                console.error('Failed to load customer bills');
            }).always(function () {
                $billSelect.prop('disabled', false);
                if (window.jQuery && jQuery.fn.select2) {
                    $billSelect.trigger('change.select2');
                }
                customerBillsLoading = false;
                updateBillModeUI();
                let activeBill = $billSelect.val();
                if (onDone) {
                    onDone(activeBill);
                } else if (activeBill && selectedBillId) {
                    loadBillItems(activeBill, false);
                }
            });
        }

        // Header Validation (Inline feedback, no popups)
        function validateSrHeader(showAlert = false, checkFields = ['customer', 'date']) {
            let isValid = true;
            let $cust = $('#customer_id');
            let custVal = $cust.val();
            let $custContainer = $cust.next('.select2-container').find('.select2-selection');
            let $custFeedback = $('#customer_id_error_msg');
            if (!$custFeedback.length) {
                $custFeedback = $('<div id="customer_id_error_msg" class="invalid-feedback text-danger font-weight-bold mt-1" style="display:none;">Please select a Customer first.</div>');
                $cust.closest('.field-wrapper').append($custFeedback);
            }

            if (checkFields.includes('customer')) {
                if (!custVal) {
                    $cust.addClass('is-invalid');
                    $custContainer.addClass('border-danger');
                    $custFeedback.css('display', 'block');
                    if (showAlert) {
                        $cust.select2('open');
                    }
                    isValid = false;
                } else {
                    $cust.removeClass('is-invalid');
                    $custContainer.removeClass('border-danger');
                    $custFeedback.css('display', 'none');
                }
            }

            let $date = $('#return_date');
            let $dateFeedback = $('#return_date_error_msg');
            if (!$dateFeedback.length) {
                $dateFeedback = $('<div id="return_date_error_msg" class="invalid-feedback text-danger font-weight-bold mt-1" style="display:none;">Please enter a valid date.</div>');
                $date.closest('.field-wrapper').append($dateFeedback);
            }

            if (checkFields.includes('date')) {
                let dateVal = ($date.val() || '').trim();
                if (!dateVal) {
                    $date.addClass('is-invalid border-danger');
                    $dateFeedback.css('display', 'block');
                    if (showAlert && isValid) {
                        $date.focus();
                    }
                    isValid = false;
                } else {
                    $date.removeClass('is-invalid border-danger');
                    $dateFeedback.css('display', 'none');
                }
            }

            return isValid;
        }

        $(document).on('click', '#sr-add-row', function (e) {
            if (!$('#customer_id').val()) {
                e.preventDefault();
                validateSrHeader(true, ['customer']);
                return false;
            }
        });

        $('#customer_id').on('change', function () {
            validateSrHeader(false, ['customer']);
            let custId = $(this).val();

            // Clear previous customer's sales bill selection and items
            let $billSelect = $('#sales_bill_id');
            $billSelect.val('').trigger('change.select2');
            $('#sr-bill-item-checklist').html('<div class="text-muted small p-2">-- Load a Sales Bill above to see its items --</div>');
            $('#sr-bill-picker-wrap').slideUp(200);
            $('#sr-bill-summary').addClass('d-none');
            $('#sr-bill-item-error').hide().text('');
            cachedBillItems = [];

            // Clear Return Items table
            const tbody = document.getElementById('sr-items-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4"><i class="fas fa-info-circle mr-1 text-info"></i> Please select a Sales Bill above.</td></tr>';
            }
            recalculateAll();

            loadCustomerBills(custId);
        });

        $('#return_date').on('change blur', function () {
            validateSrHeader(false);
        });

        let initialCustId = $('#customer_id').val();
        let initialBillId = '{{ old("sales_bill_id", $ret?->sales_bill_id ?? ($presetBillId ?? "")) }}';
        if (initialCustId) {
            loadCustomerBills(initialCustId, initialBillId, function(resolvedBillId) {
                // After customer bills are loaded, auto-trigger bill items if a bill is pre-selected
                if (resolvedBillId) {
                    loadBillItems(resolvedBillId, IS_EDIT_MODE);
                }
            });
        } else if (initialBillId) {
            updateBillModeUI();
            loadBillItems(initialBillId, IS_EDIT_MODE);
        }

        // Form Submit Handler: validate header, check items and quantities properly
        $('form').on('submit', function (e) {
            if (!validateSrHeader(true)) {
                e.preventDefault();
                return false;
            }

            let hasBill = !!$('#sales_bill_id').val();
            let totalSelectedItems = 0;
            let hasError = false;

            // Validate all rows that have items or entered input
            let $rows = $('#sr-items-body .sr-item-row');

            // Aggregate item quantities across rows
            let itemTotals = {};
            $rows.each(function () {
                let itemId = $(this).find('.sr-item-select').val();
                let $qtyInput = $(this).find('.sr-qty');
                let qty = parseFloat($qtyInput.val()) || 0;
                if (itemId && qty > 0) {
                    itemTotals[itemId] = (itemTotals[itemId] || 0) + qty;
                }
            });

            $rows.each(function () {
                let $row = $(this);
                let itemId = $row.find('.sr-item-select').val();
                let itemCode = $.trim($row.find('.sr-item-code').val());
                let itemName = $.trim($row.find('.sr-item-desc').val()) || itemCode || 'Selected Item';
                let $qtyInput = $row.find('.sr-qty');
                let qtyVal = $qtyInput.val();
                let qty = parseFloat(qtyVal) || 0;
                let origQty = parseFloat($qtyInput.attr('data-original-qty') || $qtyInput.attr('max')) || 0;
                let retQty = parseFloat($qtyInput.attr('data-returned-qty')) || 0;
                let remQtyAttr = $qtyInput.attr('data-remaining-qty');
                let remQty = (remQtyAttr !== undefined && remQtyAttr !== '') ? parseFloat(remQtyAttr) : (origQty - retQty);

                // Completely blank row (no item, no code) -> skip
                if (!itemId && !itemCode) {
                    return;
                }

                // Item code entered but not selected from search list
                if (!itemId && itemCode) {
                    e.preventDefault();
                    $row.find('.sr-item-code').addClass('is-invalid border-danger').focus();
                    hasError = true;
                    return false;
                }

                // Item IS selected:
                totalSelectedItems++;

                // Check 1: Qty missing or <= 0
                if (!qtyVal || qty <= 0) {
                    e.preventDefault();
                    let errMsg = 'Quantity must be greater than 0.';
                    $qtyInput.addClass('is-invalid border-danger').attr('title', errMsg);
                    $row.find('.sr-qty-error-msg').text(errMsg).show();
                    $qtyInput.focus().select();
                    hasError = true;
                    return false;
                }

                let $discPct = $row.find('.sr-disc-percent');
                let discPctVal = parseFloat($discPct.val()) || 0;
                let $discAmt = $row.find('.sr-disc-amount');
                let discAmtVal = parseFloat($discAmt.val()) || 0;
                let sellPriceVal = parseFloat($row.find('.sr-price').val()) || 0;
                let baseTotal = qty * sellPriceVal;

                if (discPctVal < 0 || discPctVal > 100) {
                    e.preventDefault();
                    $discPct.addClass('is-invalid border-danger');
                    let msg = `Row for "${itemName}": Discount % (${discPctVal}%) cannot exceed 100%.`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount %');
                    else alert(msg);
                    $discPct.focus().select();
                    hasError = true;
                    return false;
                }

                if (discAmtVal < 0 || (baseTotal > 0 && discAmtVal > baseTotal)) {
                    e.preventDefault();
                    $discAmt.addClass('is-invalid border-danger');
                    let msg = `Row for "${itemName}": Discount amount (₹${discAmtVal}) cannot exceed item total (₹${baseTotal.toFixed(2)}).`;
                    if (window.toastr) toastr.warning(msg, 'Invalid Discount Amount');
                    else alert(msg);
                    $discAmt.focus().select();
                    hasError = true;
                    return false;
                }

                // Check 2: Qty exceeds remaining returnable quantity
                let hasLineBillLimit = ($qtyInput.attr('data-remaining-qty') !== undefined && $qtyInput.attr('data-remaining-qty') !== '');
                if (hasBill || hasLineBillLimit) {
                    if (remQty <= 0) {
                        e.preventDefault();
                        let errMsg = "No returnable quantity available for this item.";
                        $qtyInput.addClass('is-invalid border-danger').attr('title', errMsg);
                        $row.find('.sr-qty-error-msg').text(errMsg).show();
                        $qtyInput.focus().select();
                        hasError = true;
                        return false;
                    }
                    let totalRequested = itemTotals[itemId] || qty;
                    if (totalRequested > remQty + 0.0001) {
                        e.preventDefault();
                        let remDisplay = (remQty === parseInt(remQty, 10)) ? parseInt(remQty, 10) : remQty;
                        let errMsg = "Maximum available quantity is " + remDisplay + ".";
                        $qtyInput.addClass('is-invalid border-danger').attr('title', errMsg);
                        $row.find('.sr-qty-error-msg').text(errMsg).show();
                        $qtyInput.focus().select();
                        hasError = true;
                        return false;
                    }
                }
            });

            if (hasError) {
                return false;
            }

            // If no items have been selected at all
            if (totalSelectedItems === 0) {
                e.preventDefault();
                $('#sr-bill-item-error').text('Please add at least one item before saving.').show();
                if ($('#sales_bill_id').val()) {
                    $('#sr-bill-item-checklist').focus();
                } else {
                    $('#sr-items-body .sr-item-row:first .sr-item-code').focus();
                }
                return false;
            }

            // Remove purely empty rows before submitting
            $('#sr-items-body .sr-item-row').each(function () {
                let itemId = $(this).find('.sr-item-select').val();
                if (!itemId) {
                    $(this).remove();
                }
            });

            // Re-index remaining rows so items[0], items[1] are contiguous
            $('#sr-items-body .sr-item-row').each(function (idx) {
                $(this).attr('data-row-index', idx);
                $(this).find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.indexOf('items[') !== -1) {
                        $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                    }
                });
            });
        });

        // ============================================================
        // CUSTOMER PURCHASE HISTORY PICKER (Multi-Bill / Multi-Date)
        // ============================================================
        let cphRawData = null;
        let cphCurrentDays = 30;

        function openCustomerHistoryModal() {
            let custId = $('#customer_id').val();
            if (!custId) {
                validateSrHeader(true, ['customer']);
                return;
            }

            let custName = $('#customer_id option:selected').text() || 'Selected Customer';
            $('#sr-cph-customer-name').text(custName);
            $('#sr-cph-search-input').val('');
            $('#sr-customer-history-modal').modal('show');
            fetchCustomerPurchaseHistory(cphCurrentDays);
        }

        $('#sr-btn-open-history, #sr-btn-open-history-header').on('click', function (e) {
            e.preventDefault();
            openCustomerHistoryModal();
        });

        $('#sr-cph-date-pills button').on('click', function () {
            $('#sr-cph-date-pills button').removeClass('active');
            $(this).addClass('active');
            cphCurrentDays = $(this).data('days');
            fetchCustomerPurchaseHistory(cphCurrentDays);
        });

        $('#sr-cph-search-input').on('input', function () {
            filterCustomerPurchaseHistory($(this).val());
        });

        $('#sr-cph-search-clear').on('click', function () {
            $('#sr-cph-search-input').val('');
            filterCustomerPurchaseHistory('');
        });

        function fetchCustomerPurchaseHistory(days) {
            let custId = $('#customer_id').val();
            if (!custId) return;

            $('#sr-cph-loading').removeClass('d-none');
            $('#sr-cph-empty').addClass('d-none');
            $('#sr-cph-bills-container').empty();
            $('#sr-cph-stats-badge').text('Loading...');
            updateCphFooterCounters();

            let url = `/sales/sales-returns/customer-purchased-items/${custId}?days=${days}`;
            @if(!empty($ret?->id))
                url += `&ignore_return_id={{ $ret->id }}`;
            @endif

            $.getJSON(url, function (res) {
                $('#sr-cph-loading').addClass('d-none');
                cphRawData = res;
                let bills = res.bills || [];

                if (bills.length === 0) {
                    $('#sr-cph-empty').removeClass('d-none');
                    $('#sr-cph-stats-badge').text('0 Bills Found');
                    return;
                }

                $('#sr-cph-stats-badge').text(`${res.summary.total_bills} Bills (${res.summary.total_returnable_items} Returnable Items)`);
                renderCustomerPurchaseHistoryBills(bills);
            }).fail(function () {
                $('#sr-cph-loading').addClass('d-none');
                $('#sr-cph-bills-container').html('<div class="alert alert-danger font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i>Failed to load customer purchase history. Please try again.</div>');
            });
        }

        function renderCustomerPurchaseHistoryBills(bills) {
            let $container = $('#sr-cph-bills-container');
            $container.empty();

            // Check what items are already in the return table
            let existingReturnMap = {};
            $('#sr-items-body .sr-item-row').each(function () {
                let itId = $(this).find('.sr-item-select').val();
                let bId = $(this).find('.sr-item-bill-id').val();
                if (itId) {
                    let k = (bId || '') + '_' + itId;
                    existingReturnMap[k] = parseFloat($(this).find('.sr-qty').val()) || 0;
                }
            });

            bills.forEach(function (bill, bIdx) {
                let collapseId = `cph-bill-collapse-${bill.id}`;
                let billHeaderId = `cph-bill-header-${bill.id}`;

                let itemsHtml = '';
                let returnableCountInBill = 0;

                bill.items.forEach(function (item) {
                    let remQty = parseFloat(item.remaining_qty) || 0;
                    let origQty = parseFloat(item.original_qty) || 0;
                    let retQty = parseFloat(item.already_returned_qty) || 0;
                    let isReturnable = remQty > 0;
                    if (isReturnable) returnableCountInBill++;

                    let key = bill.id + '_' + item.item_id;
                    let isAlreadyAdded = existingReturnMap[key] !== undefined;
                    let prefilledQty = isAlreadyAdded ? existingReturnMap[key] : remQty;

                    itemsHtml += `
                        <tr class="cph-item-row ${!isReturnable ? 'bg-light text-muted' : ''}"
                            data-item-search="${(item.item_name + ' ' + item.item_code + ' ' + bill.bill_number).toLowerCase()}">
                            <td class="text-center align-middle" style="width: 40px;">
                                <input type="checkbox" class="sr-cph-item-chk"
                                    ${!isReturnable ? 'disabled' : ''}
                                    ${isAlreadyAdded ? 'checked' : ''}
                                    data-bill-id="${bill.id}"
                                    data-bill-no="${bill.bill_number}"
                                    data-bill-date="${bill.bill_date}"
                                    data-bill-item-id="${item.sales_bill_item_id}"
                                    data-item-id="${item.item_id}"
                                    data-item-name="${item.item_name}"
                                    data-item-code="${item.item_code}"
                                    data-exp-date="${item.exp_date || ''}"
                                    data-orig-qty="${origQty}"
                                    data-ret-qty="${retQty}"
                                    data-rem-qty="${remQty}"
                                    data-sell-price="${item.sell_price}"
                                    data-mrp="${item.mrp}"
                                    data-disc-percent="${item.disc_percent}"
                                    data-disc-amount="${item.disc_amount}"
                                    data-gst-percent="${item.gst_percent}">
                            </td>
                            <td class="align-middle">
                                <div class="font-weight-bold text-dark">${item.item_name}</div>
                                <small class="text-muted">${item.item_code ? 'Code: ' + item.item_code : ''} ${item.exp_date ? ' | Exp: ' + item.exp_date : ''}</small>
                            </td>
                            <td class="text-right align-middle font-weight-bold">${origQty.toFixed(origQty % 1 === 0 ? 0 : 3)}</td>
                            <td class="text-right align-middle text-muted">${retQty.toFixed(retQty % 1 === 0 ? 0 : 3)}</td>
                            <td class="text-right align-middle">
                                ${isReturnable
                                    ? `<span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 12px;">${remQty.toFixed(remQty % 1 === 0 ? 0 : 3)}</span>`
                                    : `<span class="badge badge-secondary px-2 py-1">0 (Exhausted)</span>`
                                }
                            </td>
                            <td class="text-right align-middle">
                                <span class="font-weight-bold">₹${item.sell_price.toFixed(2)}</span>
                                ${item.disc_percent > 0 ? `<small class="text-danger d-block">-${item.disc_percent}%</small>` : ''}
                            </td>
                            <td class="text-right align-middle">${item.gst_percent}%</td>
                            <td class="text-right align-middle" style="width: 120px;">
                                <input type="number" step="0.001" min="0.001" max="${remQty}"
                                    class="form-control form-control-sm text-right sr-cph-item-qty font-weight-bold"
                                    value="${prefilledQty.toFixed(prefilledQty % 1 === 0 ? 0 : 3)}"
                                    ${!isReturnable ? 'disabled' : ''}
                                    style="min-width: 80px;">
                            </td>
                        </tr>
                    `;
                });

                let cardHtml = `
                    <div class="card border mb-3 shadow-sm cph-bill-card" id="cph-card-${bill.id}" data-bill-search="${bill.bill_number.toLowerCase()}">
                        <div class="card-header bg-white py-2 px-3 d-flex flex-wrap justify-content-between align-items-center" id="${billHeaderId}">
                            <div class="d-flex align-items-center mb-1 mb-md-0" style="cursor: pointer;" data-toggle="collapse" data-target="#${collapseId}">
                                <i class="fas fa-chevron-down text-primary mr-2 cph-collapse-icon"></i>
                                <span class="badge badge-primary px-2 py-1 mr-2 font-weight-bold" style="font-size: 13px;">
                                    <i class="fas fa-file-invoice mr-1"></i> Bill #${bill.bill_number}
                                </span>
                                <span class="text-muted small mr-3"><i class="far fa-calendar-alt mr-1"></i>${bill.bill_date}</span>
                                ${bill.branch_name ? `<span class="badge badge-light border text-muted mr-3"><i class="fas fa-store mr-1"></i>${bill.branch_name}</span>` : ''}
                                <span class="font-weight-bold text-dark mr-2">Total: <strong class="text-success">₹${bill.total.toFixed(2)}</strong></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge badge-info px-2 py-1 mr-3">
                                    ${returnableCountInBill}/${bill.items.length} Returnable
                                </span>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input sr-cph-bill-select-all" id="sr-cph-sel-all-${bill.id}" data-bill-id="${bill.id}" ${returnableCountInBill === 0 ? 'disabled' : ''}>
                                    <label class="custom-control-label font-weight-bold small text-primary" for="sr-cph-sel-all-${bill.id}" style="cursor: pointer;">
                                        Select All in Bill
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div id="${collapseId}" class="collapse show" aria-labelledby="${billHeaderId}">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="bg-light text-muted small">
                                            <tr>
                                                <th style="width: 40px;" class="text-center"></th>
                                                <th>Item Name & Code</th>
                                                <th class="text-right" style="width: 80px;">Sold Qty</th>
                                                <th class="text-right" style="width: 80px;">Returned</th>
                                                <th class="text-right" style="width: 90px;">Returnable</th>
                                                <th class="text-right" style="width: 90px;">Sold Rate</th>
                                                <th class="text-right" style="width: 60px;">GST %</th>
                                                <th class="text-right" style="width: 120px;">Return Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${itemsHtml}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $container.append(cardHtml);
            });

            updateCphFooterCounters();
        }

        function filterCustomerPurchaseHistory(query) {
            let q = $.trim(query).toLowerCase();
            if (!q) {
                $('.cph-bill-card').show();
                $('.cph-item-row').show();
                return;
            }

            $('.cph-bill-card').each(function () {
                let $card = $(this);
                let billSearch = $card.attr('data-bill-search') || '';
                let billMatches = billSearch.indexOf(q) !== -1;

                let visibleRows = 0;
                $card.find('.cph-item-row').each(function () {
                    let itemSearch = $(this).attr('data-item-search') || '';
                    if (billMatches || itemSearch.indexOf(q) !== -1) {
                        $(this).show();
                        visibleRows++;
                    } else {
                        $(this).hide();
                    }
                });

                if (visibleRows > 0) {
                    $card.show();
                    $card.find('.collapse').collapse('show');
                } else {
                    $card.hide();
                }
            });
        }

        // Toggle "Select All in Bill"
        $(document).on('change', '.sr-cph-bill-select-all', function () {
            let billId = $(this).data('bill-id');
            let isChecked = $(this).is(':checked');
            let $card = $(`#cph-card-${billId}`);
            $card.find('.sr-cph-item-chk:not(:disabled)').prop('checked', isChecked);
            updateCphFooterCounters();
        });

        // Individual item checkbox change
        $(document).on('change', '.sr-cph-item-chk, .sr-cph-item-qty', function () {
            let $row = $(this).closest('tr');
            let $chk = $row.find('.sr-cph-item-chk');
            if ($(this).hasClass('sr-cph-item-qty')) {
                let val = parseFloat($(this).val()) || 0;
                let max = parseFloat($chk.attr('data-rem-qty')) || 0;
                if (val > max) {
                    $(this).val(max);
                } else if (val <= 0) {
                    $(this).val(Math.min(1, max));
                }
                if (!$chk.is(':checked') && val > 0) {
                    $chk.prop('checked', true);
                }
            }
            updateCphFooterCounters();
        });

        function updateCphFooterCounters() {
            let totalSelected = 0;
            let estRefundTotal = 0;

            $('.sr-cph-item-chk:checked').each(function () {
                totalSelected++;
                let $chk = $(this);
                let $row = $chk.closest('tr');
                let qty = parseFloat($row.find('.sr-cph-item-qty').val()) || 0;
                let price = parseFloat($chk.attr('data-sell-price')) || 0;
                let discPct = parseFloat($chk.attr('data-disc-percent')) || 0;

                let base = qty * price;
                let discAmt = discPct > 0 ? (base * discPct / 100) : 0;
                let net = Math.max(0, base - discAmt);
                estRefundTotal += net;
            });

            $('#sr-cph-selected-count').text(totalSelected);
            $('#sr-cph-selected-total').text('₹' + estRefundTotal.toFixed(2));
            $('#sr-cph-btn-add-selected').prop('disabled', totalSelected === 0);
        }

        // Add Selected Items to Return
        $('#sr-cph-btn-add-selected').on('click', function () {
            let selectedCheckboxes = $('.sr-cph-item-chk:checked');
            if (selectedCheckboxes.length === 0) return;

            const tbody = document.getElementById('sr-items-body');

            // If table has empty placeholder row, remove it
            let $firstRow = $(tbody).find('.sr-item-row').first();
            if ($firstRow.length && !$firstRow.find('.sr-item-select').val() && $(tbody).find('.sr-item-row').length === 1) {
                tbody.innerHTML = '';
            } else if ($(tbody).find('td[colspan]').length > 0) {
                tbody.innerHTML = '';
            }

            let addedCount = 0;
            let billIdsUsed = [];

            selectedCheckboxes.each(function () {
                let $chk = $(this);
                let $cphRow = $chk.closest('tr');

                let itemId = $chk.attr('data-item-id');
                let billId = $chk.attr('data-bill-id');
                let billItemId = $chk.attr('data-bill-item-id');
                let billNo = $chk.attr('data-bill-no');
                let billDate = $chk.attr('data-bill-date');
                let itemName = $chk.attr('data-item-name');
                let itemCode = $chk.attr('data-item-code') || '';
                let expDate = $chk.attr('data-exp-date') || '';
                let origQty = parseFloat($chk.attr('data-orig-qty')) || 0;
                let retQty = parseFloat($chk.attr('data-ret-qty')) || 0;
                let remQty = parseFloat($chk.attr('data-rem-qty')) || 0;
                let sellPrice = parseFloat($chk.attr('data-sell-price')) || 0;
                let mrp = parseFloat($chk.attr('data-mrp')) || 0;
                let discPercent = parseFloat($chk.attr('data-disc-percent')) || 0;
                let discAmount = parseFloat($chk.attr('data-disc-amount')) || 0;
                let gstPercent = parseFloat($chk.attr('data-gst-percent')) || 0;
                let returnQty = parseFloat($cphRow.find('.sr-cph-item-qty').val()) || remQty;

                if (billId) billIdsUsed.push(billId);

                // Check if already in table
                let $existingRow = null;
                $(tbody).find('.sr-item-row').each(function () {
                    let thisItemId = $(this).find('.sr-item-select').val();
                    let thisBillId = $(this).find('.sr-item-bill-id').val();
                    if (String(thisItemId) === String(itemId) && String(thisBillId) === String(billId)) {
                        $existingRow = $(this);
                        return false;
                    }
                });

                if ($existingRow && $existingRow.length) {
                    $existingRow.find('.sr-qty').val(returnQty);
                    recalculateRow($existingRow[0], 'qty');
                    addedCount++;
                    return;
                }

                // Add new row via template
                const template = document.getElementById('sr-row-template').innerHTML;
                const html = template.replaceAll('__INDEX__', rowIndex);
                const tempTable = document.createElement('table');
                tempTable.innerHTML = '<tbody>' + html + '</tbody>';
                const newRow = tempTable.querySelector('tr');
                if (!newRow) return;

                let $nr = $(newRow);
                $nr.find('.sr-item-select').val(itemId);
                $nr.find('.sr-item-bill-id').val(billId);
                $nr.find('.sr-item-bill-item-id').val(billItemId);

                // Code / Barcode (readonly)
                $nr.find('.sr-item-code').val(itemId).prop('readonly', true).attr('title', `From Bill #${billNo}`);

                // Description + Bill Badge
                $nr.find('.sr-item-desc').val(itemName + (itemCode ? ' [' + itemCode + ']' : ''));
                $nr.find('.sr-bill-badge-text').text(`Bill #${billNo}` + (billDate ? ` (${billDate})` : ''));
                $nr.find('.sr-bill-badge-wrapper').show();

                // Quantities
                $nr.find('.sr-qty').val(returnQty)
                    .attr('max', remQty)
                    .attr('data-original-qty', origQty)
                    .attr('data-returned-qty', retQty)
                    .attr('data-remaining-qty', remQty);

                let maxLabelText = (retQty > 0)
                    ? `Remaining: ${remQty} (Orig: ${origQty}, Ret: ${retQty})`
                    : `Max: ${origQty}`;
                $nr.find('.sr-max-qty-label').text(maxLabelText).show();

                // Pricing & Tax
                $nr.find('.sr-price').val(sellPrice.toFixed(2));
                $nr.find('.sr-mrp').val(mrp.toFixed(2));
                $nr.find('.sr-disc-percent').val(discPercent > 0 ? discPercent : '');
                $nr.find('.sr-disc-amount').val(discAmount > 0 ? discAmount.toFixed(2) : '');
                $nr.find('.sr-gst-percent').val(gstPercent);
                $nr.find('.sr-exp-date').val(expDate).attr('data-original-exp', expDate).data('original-exp', expDate);

                tbody.appendChild(newRow);
                rowIndex++;
                recalculateRow(newRow, 'qty');
                addedCount++;
            });

            // If all selected items originate from 1 single bill, sync the header sales_bill_id
            let uniqueBills = [...new Set(billIdsUsed)];
            if (uniqueBills.length === 1 && !$('#sales_bill_id').val()) {
                $('#sales_bill_id').val(uniqueBills[0]).trigger('change.select2');
            } else if (uniqueBills.length > 1) {
                // Multi-bill mode: keep header bill clear so line-level bill IDs govern each item
                $('#sales_bill_id').val('').trigger('change.select2');
            }

            $('#sr-customer-history-modal').modal('hide');
            recalculateAll();

            if (window.toastr) {
                toastr.success(`${addedCount} item(s) added from purchase history!`, 'Items Added');
            }
        });

        // Initialize calculations
        recalculateAll();

        // Initialize universal compact transaction layout auto-fit engine
        if (window.initTransactionCompactLayout) {
            window.initTransactionCompactLayout({
                containerSelector: '.tx-items-scroll-container',
                footerSelector: '.tx-rich-footer',
                tableSelector: '#sr-items-table',
                minHeight: 160
            });
        }

        // Global F6 shortcut to save return form
        $(document).on('keydown', function (e) {
            if (e.key === 'F6') {
                e.preventDefault();
                e.stopPropagation();
                var $saveBtn = $('#sr-main-save-btn');
                if ($saveBtn.length && !$saveBtn.prop('disabled')) {
                    $saveBtn.trigger('click');
                } else if ($saveBtn.length && $saveBtn.prop('disabled')) {
                    var title = $saveBtn.attr('title') || 'Please select a Customer and add at least one item before saving.';
                    if (window.toastr) {
                        toastr.warning(title);
                    } else {
                        alert(title);
                    }
                }
            }
        });

        // Form Reset Button Handler
        $(document).on('click', '.btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset this form? All unsaved inputs will be lost.')) {
                window.location.reload();
            }
        });

    })();
</script>
@endpush
