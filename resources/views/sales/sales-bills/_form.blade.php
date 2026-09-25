@php
    $bill = $salesBill ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($bill?->items ?? ($convertedItems ?? collect()));
    $selectedCust = old('customer_id', old('header.customer_id', $bill->customer_id ?? ($sourceQuotation->customer_id ?? ($sourceOrder->customer_id ?? ($sourceDeliveryNote->customer_id ?? '')))));
    $selectedBranch = old('branch_id', old('header.branch_id', $bill->branch_id ?? ($sourceQuotation->branch_id ?? ($sourceOrder->branch_id ?? ($sourceDeliveryNote->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1))))))));
    $selectedSalesType = old('sales_type', old('header.sales_type', $bill->sales_type ?? ($sourceQuotation->sales_type ?? ($sourceOrder->sales_type ?? 'Local'))));
    $billNumberVal = old('bill_number', $bill->bill_number ?? ($nextBillNumber ?? ''));
@endphp

@if(isset($sourceQuotation))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Converting from <strong>Sales Quotation #{{ $sourceQuotation->quotation_number }}</strong> (Customer: {{ $sourceQuotation->customer?->name }}).
        <input type="hidden" name="from_quotation_id" value="{{ $sourceQuotation->id }}">
    </div>
@elseif(isset($sourceOrder))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Converting from <strong>Sales Order #{{ $sourceOrder->order_number }}</strong> (Customer: {{ $sourceOrder->customer?->name }}).
        <input type="hidden" name="from_order_id" value="{{ $sourceOrder->id }}">
    </div>
@elseif(isset($sourceDeliveryNote))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-truck mr-1"></i> Converting from <strong>Delivery Note #{{ $sourceDeliveryNote->delivery_number }}</strong> (Customer: {{ $sourceDeliveryNote->customer?->name }}). <em>Stock was already deducted upon dispatch.</em>
        <input type="hidden" name="sales_delivery_note_id" value="{{ $sourceDeliveryNote->id }}">
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-file-invoice mr-1 text-primary"></i> Bill Header</h5>
    <div class="d-flex align-items-center">
        <x-form-layout-customizer 
            form-key="sales_bills.header" 
            container-id="sb-header-fields-grid" 
            button-text="Customize Layout" 
            button-class="btn btn-outline-primary btn-xs font-weight-bold mr-2 shadow-sm" />
        @if(empty($bill?->id))
        <button type="button" id="btn-customer-invoices" class="btn btn-outline-info btn-sm font-weight-bold" disabled title="Select a customer first to view their invoice history">
            <i class="fas fa-file-invoice mr-1"></i> Invoices <span id="badge-cust-invoices-count" class="badge badge-info ml-1 d-none">0</span>
        </button>
        @endif
    </div>
</div>
<input type="hidden" name="posting_key" id="sb-posting-key" value="{{ old('posting_key', (string) \Illuminate\Support\Str::uuid()) }}">

<div class="row g-2 form-fields-grid" id="sb-header-fields-grid">
    {{-- Bill Number --}}
    <div class="field-wrapper col-md-4" data-field="bill_number" data-default-order="1">
        @if(!empty($bill?->id))
            <x-field name="bill_number" label="Bill No" :value="$bill->bill_number" readonly />
        @else
            <div class="form-group mb-2">
                <label class="font-weight-bold">Bill No</label>
                <div class="input-group">
                    <input type="text" name="bill_number" class="form-control font-weight-bold bg-light" value="{{ old('bill_number', '') }}" placeholder="Auto-Generated on Save" readonly>
                    <div class="input-group-append">
                        <span class="input-group-text bg-white text-muted small">
                            <i class="fas fa-lock mr-1 text-secondary"></i> Assigned on Save
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Customer --}}
    <div class="field-wrapper col-md-8" data-field="customer_id" data-default-order="2" data-core="1">
        <div class="form-group mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label for="customer_id" class="font-weight-bold mb-0">Customer <span class="text-danger">*</span></label>
                <button type="button" id="btn-quick-add-customer" class="btn btn-outline-primary btn-xs font-weight-bold">
                    <i class="fas fa-user-plus mr-1"></i> + New Customer
                </button>
            </div>
            {{-- No `required` attr: customer can be added before or after items in sales bills --}}
            <select name="customer_id" id="customer_id" class="form-control select2" data-sequential-optional="1">
                <option value="">-- Search Customer by Name or Mobile --</option>
                @foreach ($customers as $id => $name)
                    <option value="{{ $id }}" @selected($selectedCust == $id)>{{ $name }}</option>
                @endforeach
            </select>

        </div>
        <div id="sb-customer-loyalty-badge" class="alert alert-light border py-1 px-3 d-none mb-2 shadow-sm align-items-center justify-content-between">
            <div>
                <i class="fas fa-coins text-warning mr-1"></i>
                <strong>Loyalty Points:</strong> <span id="sb-loyalty-pts" class="text-primary font-weight-bold">0.00</span> pts
                <span class="text-muted">(≈ ₹<span id="sb-loyalty-val">0.00</span>)</span>
            </div>
            <span id="sb-loyalty-notice" class="badge badge-success"></span>
        </div>
    </div>

    {{-- Branch (Locked to Top Navbar Active Branch) --}}
    <div class="field-wrapper col-md-4" data-field="branch_id" data-default-order="3" data-core="1">
        <label class="font-weight-bold text-dark small mb-1">
            <i class="fas fa-store mr-1 text-primary"></i> Active Branch <span class="badge badge-light border ml-1 font-weight-normal text-muted">Controlled at Top Navbar</span>
        </label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control form-control-sm font-weight-bold bg-light text-dark" readonly tabindex="-1" value="{{ $branches[$selectedBranch] ?? 'Active Branch' }}">
            <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
            <div class="input-group-append">
                <span class="input-group-text bg-light text-primary" title="Branch is selected globally from the top navbar"><i class="fas fa-lock"></i></span>
            </div>
        </div>
    </div>

    {{-- Bill Date & Time --}}
    <div class="field-wrapper col-md-4" data-field="bill_date" data-default-order="4" data-core="1">
        <x-field name="bill_date" label="Bill Date & Time" type="datetime-local" :value="optional($bill->bill_date ?? now())->format('Y-m-d\TH:i')" max="{{ now()->format('Y-m-d\TH:i') }}" required />
    </div>

    {{-- Invoice Type --}}
    <div class="field-wrapper col-md-4" data-field="invoice_type" data-default-order="5">
        <x-select name="invoice_type" label="Invoice Type" :options="['Retail Invoice' => 'Retail Invoice', 'Tax Invoice' => 'Tax Invoice', 'Exempted' => 'Exempted']" :selected="$bill->invoice_type ?? 'Retail Invoice'" required />
    </div>

    {{-- Delivery Type --}}
    <div class="field-wrapper col-md-4" data-field="delivery_type" data-default-order="6">
        <x-select name="delivery_type" label="Delivery Type" :options="['Delivered' => 'Delivered', 'Home Delivery' => 'Home Delivery', 'Pickup' => 'Pickup']" :selected="$bill->delivery_type ?? 'Delivered'" required />
    </div>

    {{-- Delivery Time --}}
    <div class="field-wrapper col-md-4" data-field="delivery_time" data-default-order="7">
        <x-field name="delivery_time" label="Delivery Time" type="time" :value="$bill->delivery_time ?? ''" />
    </div>

    {{-- Sales Type --}}
    <div class="field-wrapper col-md-4" data-field="sales_type" data-default-order="8">
        <x-select name="sales_type" label="Sales Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$selectedSalesType" required />
    </div>

    {{-- Payment Type --}}
    <div class="field-wrapper col-md-4" data-field="payment_type" data-default-order="9">
        <x-select name="payment_type" label="Payment Mode" :options="['Cash' => 'Cash', 'UPI' => 'UPI', 'Card' => 'Card', 'Credit' => 'Credit', 'Bank Transfer' => 'Bank Transfer', 'Cheque' => 'Cheque']" :selected="old('payment_type', ($bill && $bill->payment_type && strtolower($bill->payment_type) !== 'none') ? $bill->payment_type : 'Cash')" />
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-boxes mr-1 text-primary"></i> Items</h5>
    <div>
        <button type="button" class="btn btn-outline-warning btn-sm mr-2 btn-reset-form"><i class="fas fa-undo mr-1"></i> Reset Form</button>
        <span class="badge badge-info px-3 py-2" id="sb-branch-badge"><i class="fas fa-store mr-1"></i> Active Branch: Loading…</span>
    </div>
</div>

{{-- Unsaved Bill Draft Recovery Alert Banner --}}
@if(empty($bill?->id))
<div id="sb-draft-recovery-alert" class="alert alert-warning py-2 px-3 mb-3 shadow-sm d-none align-items-center justify-content-between">
    <div>
        <i class="fas fa-history mr-2 text-dark"></i>
        <strong>Unsaved Bill Draft Found!</strong> You have an unsaved draft from <span id="sb-draft-saved-time" class="font-weight-bold text-dark"></span> containing <span id="sb-draft-item-count" class="badge badge-dark">0</span> item(s).
    </div>
    <div>
        <button type="button" class="btn btn-success btn-xs font-weight-bold px-3 py-1 shadow-sm mr-2" id="btn-restore-bill-draft">
            <i class="fas fa-undo mr-1"></i> Restore Bill
        </button>
        <button type="button" class="btn btn-outline-secondary btn-xs px-2" id="btn-discard-bill-draft">
            <i class="fas fa-trash-alt mr-1"></i> Discard Draft
        </button>
    </div>
</div>
@endif

@php
    $sbItemColumns = [
        'seq'          => ['label' => '#', 'default' => true],
        'code'         => ['label' => 'Code / Barcode', 'default' => true],
        'item'         => ['label' => 'Item Description', 'default' => true],
        'expiry'       => ['label' => 'Exp Date', 'default' => true],
        'qty'          => ['label' => 'Qty', 'default' => true],
        'sell_price'   => ['label' => 'Sell Price', 'default' => true],
        'mrp'          => ['label' => 'MRP', 'default' => true],
        'disc_percent' => ['label' => 'Disc %', 'default' => true],
        'disc_amt'     => ['label' => 'Disc Amt', 'default' => true],
        'gst_percent'  => ['label' => 'GST %', 'default' => true],
        'gst_amt'      => ['label' => 'GST Amt', 'default' => true],
        'net_amt'      => ['label' => 'Net Amount', 'default' => true],
        'actions'      => ['label' => 'Actions', 'default' => true],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-boxes mr-1 text-primary"></i> Bill Items</h6>
    <x-table-column-customizer
        table-key="sales.sales-bills.items"
        table-id="sb-items-table"
        :columns="$sbItemColumns"
    />
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-items-dense" id="sb-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 35px;" class="text-center" data-col-key="seq">#</th>
                <th style="width: 120px;" data-col-key="code">Code / Barcode</th>
                <th style="min-width: 230px;" data-col-key="item">Item Description</th>
                <th style="width: 140px;" data-col-key="expiry">Exp Date</th>
                <th style="width: 85px;" class="text-right" data-col-key="qty">Qty</th>
                <th style="width: 100px;" class="text-right" data-col-key="sell_price">Sell Price</th>
                <th style="width: 100px;" class="text-right" data-col-key="mrp">MRP</th>
                <th style="width: 80px;" class="text-right" data-col-key="disc_percent">Disc %</th>
                <th style="width: 95px;" class="text-right" data-col-key="disc_amt">Disc Amt</th>
                <th style="width: 75px;" class="text-right" data-col-key="gst_percent">GST %</th>
                <th style="width: 85px;" class="text-right" title="Included GST Amount" data-col-key="gst_amt">GST Amt</th>
                <th style="width: 105px;" class="text-right" data-col-key="net_amt">Net Amount</th>
                <th style="width: 35px;" class="text-center" data-col-key="actions"></th>
            </tr>
        </thead>
        <tbody id="sb-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-bills._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-bills._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="4" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary font-weight-bold" id="footer-sb-qty"></td>
                <td colspan="2" class="align-middle"></td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="footer-sb-disc"></td>
                <td class="align-middle"></td>
                <td class="text-right align-middle text-info font-weight-bold" id="footer-sb-tax"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="footer-sb-net"></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<button type="button" id="sb-add-row" class="btn btn-link btn-sm font-weight-bold"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-calculator mr-1 text-primary"></i> Bill Totals & Notes</h5>
    <x-form-layout-customizer 
        form-key="sales_bills.additional" 
        container-id="sb-additional-fields-grid" 
        button-text="Customize Layout" 
        button-class="btn btn-outline-primary btn-xs font-weight-bold shadow-sm" />
</div>

<div class="alert alert-light border py-2 d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted mr-2 font-weight-bold">Final Bill Total:</span>
        <strong class="text-success h4 mb-0">₹<span id="display-sb-final-total">0.00</span></strong>
    </div>
    <div id="sb-total-items-badge"><span class="badge badge-secondary px-3 py-2">0 Items</span></div>
</div>

<div class="row g-2 form-fields-grid" id="sb-additional-fields-grid">
    <div class="field-wrapper col-md-6" data-field="round_off" data-label="Round off Amount" data-default-order="1">
        <x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$bill->round_off ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="total_extra_cess" data-label="Total Extra Cess" data-default-order="2">
        <x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$bill->total_extra_cess ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="gst_calamity_cess" data-label="GST Calamity Cess" data-default-order="3">
        <x-field name="gst_calamity_cess" label="GST Calamity Cess" type="number" step="0.01" :value="$bill->gst_calamity_cess ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="total_weight" data-label="Total Weight" data-default-order="4">
        <x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$bill->total_weight ?? 0" />
    </div>
    <div class="field-wrapper col-md-6" data-field="remarks" data-label="Remarks" data-default-order="5">
        <x-textarea name="remarks" label="Remarks" :value="$bill->remarks ?? ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="message" data-label="Message" data-default-order="6">
        <x-textarea name="message" label="Message" :value="$bill->message ?? ''" />
    </div>
</div>

<x-custom-fields-renderer :module="'SalesBill'" :model="$bill ?? null" :cardStyle="true" />

<template id="sb-row-template">
    @include('sales.sales-bills._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

<!-- ============================================================
     ITEM SEARCH MODAL — opens on Code/Barcode field focus
     ============================================================ -->
<div class="modal fade" id="sb-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="sbItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="sbItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Filters -->
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="custom-control custom-checkbox ml-1">
                            <input type="checkbox" class="custom-control-input" id="isl-filter-show-all">
                            <label class="custom-control-label font-weight-bold text-dark small" for="isl-filter-show-all" style="cursor: pointer;">
                                <i class="fas fa-boxes text-primary mr-1"></i> Show All (Stock + Non-Stock)
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results states -->
                <div id="isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 140px;">Code</th>
                                <th class="text-right" style="width: 100px;">Qty (Stock)</th>
                                <th class="text-right" style="width: 105px;">Sell Price</th>
                                <th class="text-right" style="width: 105px;">MRP</th>
                                <th class="text-center" style="width: 80px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Multiple Batches Selection Modal -->
<div class="modal fade" id="sb-batch-modal" tabindex="-1" role="dialog" aria-labelledby="sbBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="sbBatchModalLabel">
                    <i class="fas fa-layer-group mr-1"></i> Multiple Batches Available — Select Batch
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 mb-3 small font-weight-bold">
                    Item: <span id="modal-item-title" class="text-dark font-weight-bold"></span> | 
                    Code: <span id="modal-item-code" class="text-dark font-weight-bold"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="modal-batches-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 90px;">Code</th>
                                <th class="text-center" style="width: 125px;">Expiry (Purchase Se)</th>
                                <th class="text-right" style="width: 85px;">Qty</th>
                                <th class="text-right" style="width: 95px;">Sell Price</th>
                                <th class="text-right" style="width: 95px;">MRP</th>
                                <th class="text-center" style="width: 85px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="modal-batches-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     QUICK CUSTOMER MASTER MODAL — Popup to Add Customer
     ============================================================ -->
<div class="modal fade" id="sb-quick-customer-modal" tabindex="-1" role="dialog" aria-labelledby="sbQuickCustLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content shadow-lg border-primary">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold" id="sbQuickCustLabel">
                    <i class="fas fa-user-plus mr-2"></i> Add New Customer
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div id="qc-alert" class="alert alert-danger py-2 d-none font-weight-bold small"></div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Mobile Number <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                        </div>
                        <input type="text" id="qc-mobile" class="form-control font-weight-bold" maxlength="10" placeholder="10 Digits Mobile No">
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Customer Name <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                        </div>
                        <input type="text" id="qc-name" class="form-control font-weight-bold" placeholder="Enter Full Name" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="small font-weight-bold mb-1">Customer Type</label>
                        <select id="qc-customer-type" class="form-control form-control-sm">
                            <option value="RETAIL INVOICE" selected>Retail Invoice</option>
                            <option value="TAX INVOICE">Tax Invoice</option>
                            <option value="EXEMPTED">Exempted</option>
                            <option value="E-COMMERCE">E-Commerce</option>
                        </select>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="small font-weight-bold mb-1">Sales Type</label>
                        <select id="qc-sales-type" class="form-control form-control-sm">
                            <option value="Local" selected>Local (CGST + SGST)</option>
                            <option value="Interstate">Interstate (IGST)</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="small font-weight-bold mb-1">GST Type</label>
                        <select id="qc-gst-type" class="form-control form-control-sm">
                            <option value="Un Register" selected>Un Register</option>
                            <option value="Regular">Regular</option>
                            <option value="Composite">Composite</option>
                        </select>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="small font-weight-bold mb-1">GST No</label>
                        <input type="text" id="qc-gst-no" class="form-control form-control-sm" placeholder="GSTIN (Optional)">
                    </div>
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Email</label>
                    <input type="email" id="qc-email" class="form-control form-control-sm" placeholder="Email Address (Optional)">
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold mb-1">Address / City</label>
                    <input type="text" id="qc-address" class="form-control form-control-sm" placeholder="Address (Optional)">
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <a href="{{ route('master.customers.create') }}" target="_blank" class="small text-primary font-weight-bold">
                        <i class="fas fa-external-link-alt mr-1"></i> Open Full Customer Master
                    </a>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" id="btn-save-quick-customer" class="btn btn-primary btn-sm font-weight-bold px-3">
                    <i class="fas fa-save mr-1"></i> Save & Select
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     TENDER / PAYMENT MODAL — Redesigned to match POS UI Screenshot
     ============================================================ --}}
<div class="modal fade" id="sb-tender-modal" tabindex="-1" role="dialog" aria-labelledby="sbTenderModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 820px;">
        <div class="modal-content rounded-0 border-primary shadow-lg">
            <div class="modal-header text-white py-1 px-3 rounded-0" style="background-color: #0078d7 !important;">
                <h5 class="modal-title font-weight-bold" id="sbTenderModalLabel" style="font-size: 1.15rem; letter-spacing: 0.5px;">Tender</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0" style="background-color: #f8f9fa;">
                <style>
                    .tender-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
                    .tender-table td { padding: 4px 8px; border: 1px solid #ced4da; vertical-align: middle; font-size: 0.92rem; }
                    .tender-label { background-color: #e9ecef; font-weight: 600; color: #212529; width: 38%; }
                    .tender-table input.form-control, .tender-table select.form-control {
                        border: 1px solid #adb5bd;
                        height: 28px;
                        padding: 2px 8px;
                        font-weight: 600;
                        border-radius: 2px;
                        font-size: 0.92rem;
                        background-color: #fff;
                    }
                    .tender-table input.form-control:focus, .tender-table select.form-control:focus {
                        border-color: #0078d7;
                        box-shadow: 0 0 0 2px rgba(0, 120, 215, 0.25);
                        background-color: #ffffea;
                    }
                    .tender-summary-label { background-color: #e9ecef; font-weight: 600; color: #212529; width: 40%; }
                    .tender-summary-val { font-weight: 700; font-size: 0.95rem; background-color: #fff; padding: 4px 10px; }
                    .tender-btn {
                        border: 1px solid #7092be;
                        background: linear-gradient(180deg, #fbfdff 0%, #e8f0f8 100%);
                        color: #111;
                        font-weight: 600;
                        padding: 3px 25px;
                        border-radius: 3px;
                        min-width: 85px;
                        font-size: 0.9rem;
                        cursor: pointer;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
                    }
                    .tender-btn:hover {
                        background: linear-gradient(180deg, #eaf2fc 0%, #d5e5f7 100%);
                        border-color: #3b6ea5;
                    }
                    .tender-hotkey-bar {
                        border-top: 1px solid #e05b5b;
                        color: #c92a2a;
                        font-size: 0.85rem;
                        padding: 4px 10px;
                        background-color: #fff5f5;
                        font-weight: 600;
                        letter-spacing: 0.2px;
                    }
                </style>

                <!-- Upper Section: 2 Column Inputs -->
                <div class="row no-gutters">
                    <!-- Left Column: Payment Modes -->
                    <div class="col-md-6" style="border-right: 1px solid #ced4da;">
                        <table class="tender-table">
                            <tbody>
                                <tr>
                                    <td class="tender-label">A). Cash</td>
                                    <td>
                                        <input type="number" step="any" id="tender-cash" class="form-control text-left font-weight-bold" placeholder="0.00" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">B). Credit</td>
                                    <td>
                                        <input type="number" step="any" id="tender-credit" class="form-control text-left font-weight-bold" placeholder="" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">C). Card</td>
                                    <td>
                                        <input type="number" step="any" id="tender-card" class="form-control text-left font-weight-bold" placeholder="0.00" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">W). Wallet</td>
                                    <td>
                                        <input type="number" step="any" id="tender-wallet" class="form-control text-left font-weight-bold" placeholder="" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">N). RRN</td>
                                    <td>
                                        <input type="text" id="tender-rrn" class="form-control text-left font-weight-bold" placeholder="" autocomplete="off">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Right Column: Wallet & Card Details -->
                    <div class="col-md-6">
                        <table class="tender-table">
                            <tbody>
                                <tr>
                                    <td class="tender-label">Wallet</td>
                                    <td>
                                        <input type="number" step="any" id="tender-wallet-side" class="form-control text-left font-weight-bold" placeholder="" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">Wallet Type</td>
                                    <td>
                                        <select id="tender-wallet-type" class="form-control font-weight-bold">
                                            <option value="PINELAB" selected>PINELAB</option>
                                            <option value="PAYTM">PAYTM</option>
                                            <option value="PHONEPE">PHONEPE</option>
                                            <option value="GPAY">GPAY</option>
                                            <option value="BHARATPE">BHARATPE</option>
                                            <option value="OTHER">OTHER</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">Card No</td>
                                    <td>
                                        <input type="text" id="tender-card-no" class="form-control text-left font-weight-bold" placeholder="" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label">Wallet RefNo</td>
                                    <td>
                                        <input type="text" id="tender-wallet-refno" class="form-control text-left font-weight-bold" placeholder="" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tender-label" style="height: 38px;">&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="border-top: 2px solid #555;"></div>

                <!-- Lower Section: Summary Grid -->
                <div class="row no-gutters">
                    <div class="col-md-12">
                        <table class="tender-table">
                            <tbody>
                                <tr>
                                    <td class="tender-summary-label">Total</td>
                                    <td class="tender-summary-val text-left" id="tender-total-display">0.00</td>
                                </tr>
                                <tr>
                                    <td class="tender-summary-label">Outstanding</td>
                                    <td class="tender-summary-val text-left text-danger" id="tender-outstanding-display">0.00</td>
                                </tr>
                                <tr>
                                    <td class="tender-summary-label">Advance</td>
                                    <td class="tender-summary-val text-left text-muted" id="tender-advance-display">0.00</td>
                                </tr>
                                <tr>
                                    <td class="tender-summary-label">Tender Amount</td>
                                    <td class="tender-summary-val text-left text-primary" id="tender-tendered-display">0.00</td>
                                </tr>
                                <tr>
                                    <td class="tender-summary-label">Balance</td>
                                    <td class="tender-summary-val text-left text-success" id="tender-balance-display">0.00</td>
                                </tr>
                                <tr>
                                    <td class="tender-summary-label">Loyalty Limit</td>
                                    <td class="tender-summary-val text-left text-muted" id="tender-loyalty-display">0.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Action Buttons: Save, Save & WhatsApp, Save & Print, Cancel (Task 5) -->
                <div class="p-2 d-flex align-items-center bg-white justify-content-between" style="border-top: 1px solid #ced4da;">
                    <div class="d-flex align-items-center">
                        <button type="button" id="tender-save-btn" data-action="save" class="btn btn-success font-weight-bold px-3 mr-2">
                            <i class="fas fa-save mr-1"></i> Save
                        </button>
                        <button type="button" id="tender-whatsapp-btn" data-action="whatsapp" class="btn text-white font-weight-bold px-3 mr-2" style="background-color: #25D366; border-color: #25D366;">
                            <i class="fab fa-whatsapp mr-1"></i> Save & WhatsApp
                        </button>
                        <button type="button" id="tender-print-btn" data-action="print" class="btn btn-primary font-weight-bold px-3 mr-2">
                            <i class="fas fa-print mr-1"></i> Save & Print
                        </button>
                        <button type="button" id="tender-cancel-btn" class="btn btn-secondary font-weight-bold px-3" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                    </div>
                    <div id="tender-error" class="ml-3 text-danger font-weight-bold small d-none"></div>
                </div>

                <!-- Bottom Hotkey Bar -->
                <div class="tender-hotkey-bar">
                    Press (A) - Cash; (B) - Credit; (C) - Card; (W) - Wallet; (N) - RRN
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================================
     CUSTOMER INVOICES HISTORY MODAL — Opens on Invoices button click
     ============================================================ -->
<div class="modal fade" id="customer-invoices-modal" tabindex="-1" role="dialog" aria-labelledby="custInvoicesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title font-weight-bold" id="custInvoicesModalLabel">
                    <i class="fas fa-history mr-2"></i><span id="cim-cust-title">Customer Invoice History</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row mb-2">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="cim-filter-input" class="form-control" placeholder="Search by invoice #, date, amount..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-5 text-right d-flex align-items-center justify-content-end">
                        <span id="cim-summary-count" class="badge badge-light border text-muted px-2 py-1 mr-2">0 Invoices</span>
                        <button type="button" id="cim-btn-refresh" class="btn btn-xs btn-outline-info"><i class="fas fa-sync-alt mr-1"></i> Refresh</button>
                    </div>
                </div>

                <div id="cim-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-info"></i>
                    <p class="mt-2 text-muted">Loading customer invoices...</p>
                </div>

                <div id="cim-empty" class="text-center py-4 d-none">
                    <i class="fas fa-receipt fa-2x text-muted"></i>
                    <p class="mt-2 text-muted" id="cim-empty-msg">No invoices found for this customer.</p>
                </div>

                <div class="table-responsive" id="cim-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="cim-table">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th style="width: 170px;">Date</th>
                                <th>Invoice #</th>
                                <th class="text-right" style="width: 120px;">Amount</th>
                                <th class="text-center" style="width: 170px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cim-tbody"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                    <small class="text-muted" id="cim-page-info">Showing 0 to 0</small>
                    <ul class="pagination pagination-sm mb-0" id="cim-pagination"></ul>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Hidden: JSON-encoded TenderTypes for JS --}}
<script id="tender-types-data" type="application/json">
    {!! json_encode($tenderTypes->map(function($t) {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'type' => $t->type,
            'mandate_refno' => (bool)$t->mandate_refno,
            'values' => $t->values->map(fn($v) => ['id' => $v->id, 'name' => $v->name])->values()
        ];
    })->values()) !!}
</script>

@push('js')
<script src="{{ asset('js/pos-scan-guard.js') }}?v={{ time() }}"></script>
<script>
    $(document).ready(function () {
        function formatDigits(num) {
            if (num === '' || num === null || num === undefined || isNaN(num)) return '';
            let n = parseFloat(num);
            return (n % 1 === 0) ? n.toFixed(0) : n.toString();
        }

        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeModalRow = null;
        let activeSearchRow = null;   // which row triggered the item search modal
        let islDebounce = null;
        const ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

        // Customer Select2 with remote AJAX search by name or mobile
        let $custSelect = $('#customer_id');
        if ($custSelect.hasClass('select2-hidden-accessible')) {
            $custSelect.select2('destroy');
        }

        let lastCustSearchTerm = '';
        $custSelect.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '-- Search Customer by Name or Mobile --',
            allowClear: true,
            ajax: {
                url: '{{ route("sales.sales-bills.customer-search") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    lastCustSearchTerm = $.trim(params.term || '');
                    return { q: lastCustSearchTerm };
                },
                processResults: function (data) {
                    let results = data.results || [];
                    let cleanMob = lastCustSearchTerm.replace(/[^0-9]/g, '');
                    if (cleanMob.length === 10 && results.length === 0) {
                        setTimeout(function () {
                            $custSelect.select2('close');
                            openQuickCustomerModal(cleanMob);
                        }, 200);
                    }
                    return { results: results };
                },
                cache: true
            }
        });

        // If user hits Enter in Select2 search field on a 10-digit number with no results
        $(document).on('keydown', '.select2-search__field', function (e) {
            if (e.key === 'Enter') {
                let term = $.trim($(this).val());
                let cleanMob = term.replace(/[^0-9]/g, '');
                if (cleanMob.length === 10) {
                    let hasResults = $('.select2-results__option:not(.select2-results__message)').length > 0;
                    if (!hasResults) {
                        e.preventDefault();
                        $custSelect.select2('close');
                        openQuickCustomerModal(cleanMob);
                    }
                }
            }
        });

        // Customer selected -> fetch loyalty points and customer invoice history
        $custSelect.on('change select2:select', function () {
            let custId = $(this).val();
            if (custId) {
                fetchCustomerLoyalty(custId);
                fetchCustomerInvoices(custId);
                setTimeout(function () {
                    $('#sb-items-body tr:first .sb-item-code').focus();
                }, 150);
            } else {
                fetchCustomerLoyalty(null);
                resetCustomerInvoices();
            }
        });
        $custSelect.on('select2:clear', function () {
            fetchCustomerLoyalty(null);
            resetCustomerInvoices();
        });

        // Quick Customer Modal
        function openQuickCustomerModal(prefillMobile = '') {
            $('#qc-alert').addClass('d-none').text('');
            $('#qc-mobile').val(prefillMobile);
            $('#qc-name').val('');
            $('#qc-email').val('');
            $('#qc-address').val('');
            $('#qc-gst-no').val('');
            $('#sb-quick-customer-modal').modal('show');
            setTimeout(function () {
                if ($('#qc-mobile').val().length === 10) {
                    $('#qc-name').focus();
                } else {
                    $('#qc-mobile').focus();
                }
            }, 400);
        }

        $('#btn-quick-add-customer').on('click', function () {
            openQuickCustomerModal();
        });

        $('#btn-save-quick-customer').on('click', function () {
            let name = $.trim($('#qc-name').val());
            let mobile = $.trim($('#qc-mobile').val()).replace(/[^0-9]/g, '');

            if (!name) {
                $('#qc-alert').removeClass('d-none').text('Please enter customer name.');
                $('#qc-name').focus();
                return;
            }
            if (!mobile || mobile.length !== 10) {
                $('#qc-alert').removeClass('d-none').text('Please enter a valid 10-digit mobile number.');
                $('#qc-mobile').focus();
                return;
            }

            let $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            $('#qc-alert').addClass('d-none');

            $.ajax({
                url: '{{ route("master.customers.store") }}',
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                data: {
                    _token: '{{ csrf_token() }}',
                    name: name,
                    mobile: mobile,
                    customer_type: $('#qc-customer-type').val() || 'RETAIL INVOICE',
                    sales_type: $('#qc-sales-type').val() || 'Local',
                    gst_type: $('#qc-gst-type').val() || 'Un Register',
                    gst_no: $('#qc-gst-no').val() || '',
                    payment_mode: 'Cash Only',
                    credit_limit: 0,
                    credit_balance: 0,
                    monthly_credit_balance: 0,
                    credit_days: 0,
                    status: 1,
                    sms_consent: 1,
                    email: $('#qc-email').val() || '',
                    address1: $('#qc-address').val() || '',
                    branch_id: $('[name="branch_id"]').val() || null
                },
                success: function (res) {
                    if (res && res.customer) {
                        let c = res.customer;
                        let opt = new Option(c.text || `${c.name} (${c.mobile})`, c.id, true, true);
                        $custSelect.append(opt).trigger('change');
                        $('#sb-quick-customer-modal').modal('hide');
                        fetchCustomerLoyalty(c.id);
                        fetchCustomerInvoices(c.id);
                        setTimeout(function () {
                            $('#sb-items-body tr:first .sb-item-code').focus();
                        }, 200);
                    }
                },
                error: function (xhr) {
                    let err = 'Failed to save customer.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        err = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        err = xhr.responseJSON.message;
                    }
                    $('#qc-alert').removeClass('d-none').html(err);
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save & Select');
                }
            });
        });

        $('#sb-quick-customer-modal input').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#btn-save-quick-customer').trigger('click');
            }
        });

        /* ================================================================
           CUSTOMER INVOICES HISTORY (Task 4)
           ================================================================ */
        let customerInvoicesData = [];
        let filteredInvoices = [];
        let cimCurrentPage = 1;
        const CIM_PAGE_SIZE = 10;

        function resetCustomerInvoices() {
            customerInvoicesData = [];
            filteredInvoices = [];
            $('#badge-cust-invoices-count').addClass('d-none').text('0');
            $('#btn-customer-invoices').prop('disabled', true).attr('title', 'Select a customer first to view their invoice history');
        }

        function fetchCustomerInvoices(customerId) {
            if (!customerId) {
                resetCustomerInvoices();
                return;
            }
            $.getJSON(`/sales/sales-bills/customer-invoices/${customerId}`, function (res) {
                if (res && res.invoices) {
                    customerInvoicesData = res.invoices;
                    filteredInvoices = [...customerInvoicesData];
                    let count = customerInvoicesData.length;
                    $('#badge-cust-invoices-count').removeClass('d-none').text(count);
                    $('#btn-customer-invoices').prop('disabled', false).attr('title', `Click to view ${count} past invoice(s) for ${res.customer_name}`);
                    $('#cim-cust-title').text(`Invoices: ${res.customer_name} ${res.customer_mobile ? '(' + res.customer_mobile + ')' : ''}`);
                }
            }).fail(function () {
                resetCustomerInvoices();
            });
        }

        $('#btn-customer-invoices').on('click', function () {
            let custId = $custSelect.val();
            if (!custId) return;
            $('#cim-filter-input').val('');
            filteredInvoices = [...customerInvoicesData];
            cimCurrentPage = 1;
            renderCustomerInvoicesPage();
            $('#customer-invoices-modal').modal('show');
        });

        $('#cim-btn-refresh').on('click', function () {
            let custId = $custSelect.val();
            if (custId) {
                $('#cim-loading').removeClass('d-none');
                $('#cim-table-wrap').addClass('d-none');
                fetchCustomerInvoices(custId);
                setTimeout(function () {
                    $('#cim-loading').addClass('d-none');
                    $('#cim-table-wrap').removeClass('d-none');
                    filteredInvoices = [...customerInvoicesData];
                    renderCustomerInvoicesPage();
                }, 400);
            }
        });

        $('#cim-filter-input').on('input', function () {
            let q = $.trim($(this).val()).toLowerCase();
            if (!q) {
                filteredInvoices = [...customerInvoicesData];
            } else {
                filteredInvoices = customerInvoicesData.filter(function (inv) {
                    return (inv.bill_number && inv.bill_number.toLowerCase().includes(q)) ||
                           (inv.bill_date && inv.bill_date.toLowerCase().includes(q)) ||
                           (inv.total && inv.total.toString().includes(q)) ||
                           (inv.invoice_type && inv.invoice_type.toLowerCase().includes(q));
                });
            }
            cimCurrentPage = 1;
            renderCustomerInvoicesPage();
        });

        function renderCustomerInvoicesPage() {
            let total = filteredInvoices.length;
            $('#cim-summary-count').text(`${total} Invoice${total === 1 ? '' : 's'}`);

            if (total === 0) {
                $('#cim-empty').removeClass('d-none');
                $('#cim-table-wrap').addClass('d-none');
                $('#cim-page-info').text('Showing 0 of 0');
                $('#cim-pagination').empty();
                return;
            }

            $('#cim-empty').addClass('d-none');
            $('#cim-table-wrap').removeClass('d-none');

            let totalPages = Math.ceil(total / CIM_PAGE_SIZE) || 1;
            if (cimCurrentPage > totalPages) cimCurrentPage = totalPages;
            if (cimCurrentPage < 1) cimCurrentPage = 1;

            let start = (cimCurrentPage - 1) * CIM_PAGE_SIZE;
            let end = Math.min(start + CIM_PAGE_SIZE, total);
            let pageItems = filteredInvoices.slice(start, end);

            let html = '';
            pageItems.forEach(function (inv, idx) {
                let statusBadge = inv.status === 'Cancelled'
                    ? `<span class="badge badge-danger ml-1">${inv.status}</span>`
                    : '';
                html += `
                    <tr>
                        <td class="text-center font-weight-bold text-muted">${start + idx + 1}</td>
                        <td><i class="far fa-calendar-alt mr-1 text-muted"></i>${inv.bill_date}</td>
                        <td class="font-weight-bold text-primary">${inv.bill_number} ${statusBadge}</td>
                        <td class="text-right font-weight-bold text-success">₹${parseFloat(inv.total).toFixed(2)}</td>
                        <td class="text-center text-nowrap">
                            <a href="${inv.view_url}" target="_blank" class="btn btn-xs btn-outline-info mr-1" title="View Bill">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            <a href="${inv.edit_url}" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="Edit Bill">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                            <a href="${inv.print_url}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Bill Receipt">
                                <i class="fas fa-print mr-1"></i>Print
                            </a>
                        </td>
                    </tr>
                `;
            });
            $('#cim-tbody').html(html);
            $('#cim-page-info').text(`Showing ${start + 1} to ${end} of ${total} entries`);

            // Build pagination
            let pagHtml = '';
            if (totalPages > 1) {
                pagHtml += `<li class="page-item ${cimCurrentPage === 1 ? 'disabled' : ''}"><a class="page-link cim-page-btn" href="#" data-page="${cimCurrentPage - 1}">Prev</a></li>`;
                for (let p = 1; p <= totalPages; p++) {
                    if (p === 1 || p === totalPages || (p >= cimCurrentPage - 1 && p <= cimCurrentPage + 1)) {
                        pagHtml += `<li class="page-item ${p === cimCurrentPage ? 'active' : ''}"><a class="page-link cim-page-btn" href="#" data-page="${p}">${p}</a></li>`;
                    } else if (p === cimCurrentPage - 2 || p === cimCurrentPage + 2) {
                        pagHtml += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
                    }
                }
                pagHtml += `<li class="page-item ${cimCurrentPage === totalPages ? 'disabled' : ''}"><a class="page-link cim-page-btn" href="#" data-page="${cimCurrentPage + 1}">Next</a></li>`;
            }
            $('#cim-pagination').html(pagHtml);
        }

        $(document).on('click', '.cim-page-btn', function (e) {
            e.preventDefault();
            let p = parseInt($(this).data('page'));
            if (p) {
                cimCurrentPage = p;
                renderCustomerInvoicesPage();
            }
        });

        // Initialize customer invoices if customer is already selected (e.g. edit mode)
        if ($custSelect.val()) {
            fetchCustomerInvoices($custSelect.val());
        }

        /* ================================================================
           ITEM SEARCH MODAL — open on Focus or Click of Code/Barcode (Task 2)
           ================================================================ */
        let islModalOpen = false;
        let islModalClosing = false;

        function openItemSearchModal($input) {
            if (islModalOpen || islModalClosing || isSyncing) return;
            activeSearchRow = $input.closest('tr');
            let prefill = $.trim($input.val());
            $('#isl-filter-name').val(prefill);
            $('#isl-filter-code').val('');
            $('#isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#sb-item-search-modal').modal('show');
            $('#sb-item-search-modal').one('shown.bs.modal', function () {
                $('#isl-filter-name').focus().select();
            });
        }

        let sbMouseDown = false;
        $(document).on('mousedown', '.sb-item-code', function () {
            sbMouseDown = true;
        });

        function checkAndOpenSbModal($input) {
            if (islModalOpen || islModalClosing || isSyncing) return false;
            let $row = $input.closest('tr');
            if ($row.find('.sb-item-select').val()) return false;
            openItemSearchModal($input);
            return true;
        }

        // Tab or Enter/F2 opens modal. Mouse click DOES NOT open modal.
        $(document).off('click focus keydown', '.sb-item-code')
            .on('focus', '.sb-item-code', function () {
                if (sbMouseDown) {
                    sbMouseDown = false;
                    return; // Focused by mouse click - do not open modal!
                }
                // Focused by Tab / Keyboard navigation!
                checkAndOpenSbModal($(this));
            })
            .on('keydown', '.sb-item-code', function (e) {
                if (e.key === 'Enter' || e.key === 'F2') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    if (val && e.key === 'Enter') {
                        let $row = $(this).closest('tr');
                        processItemLookup(val, $row, null);
                    } else {
                        checkAndOpenSbModal($(this));
                    }
                }
            })
            .on('click', '.sb-item-code', function () {
                sbMouseDown = false;
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

        // Keyboard navigation in Item Search Modal (ArrowUp, ArrowDown, Enter)
        $('#sb-item-search-modal').on('keydown', function (e) {
            let $rows = $('#isl-items-body tr.isl-item-row:not(.isl-item-disabled)');
            if (!$rows.length) return;

            let $current = $rows.filter('.table-primary');
            let idx = $rows.index($current);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = (idx + 1) >= $rows.length ? 0 : idx + 1;
                $rows.removeClass('table-primary');
                let $target = $rows.eq(idx).addClass('table-primary');
                if ($target[0]) {
                    $target[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = (idx - 1) < 0 ? $rows.length - 1 : idx - 1;
                $rows.removeClass('table-primary');
                let $target = $rows.eq(idx).addClass('table-primary');
                if ($target[0]) {
                    $target[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                let $target = $current.length ? $current : $rows.first();
                if ($target.length) {
                    $target.trigger('click');
                }
            }
        });



        // Debounced filter inputs — 400ms to avoid firing on every keystroke
        $('#isl-filter-name, #isl-filter-code').on('input', function () {
            clearTimeout(islDebounce);
            islDebounce = setTimeout(fetchItemList, 400);
        });

        $('#isl-filter-show-all').on('change', function () {
            fetchItemList();
        });

        $('#isl-btn-clear').on('click', function () {
            $('#isl-filter-name, #isl-filter-code').val('');
            $('#isl-filter-show-all').prop('checked', false);
            fetchItemList();
        });

        // Client-side response cache to avoid redundant API calls
        let islCache = {};
        let islLastKey = null;

        function showHintState(msg) {
            $('#isl-loading').addClass('d-none');
            $('#isl-table-wrap').addClass('d-none');
            $('#isl-items-body').empty();
            let $nr = $('#isl-no-results');
            $nr.removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + msg + '</p>'
            );
            $('#isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let srch    = $('#isl-filter-name').val().trim();
            let code    = $('#isl-filter-code').val().trim();
            let showAll = $('#isl-filter-show-all').is(':checked') ? 1 : 0;

            // No filter — show hint, skip AJAX unless showAll is checked
            if (! srch && ! code && ! showAll) {
                showHintState('Start typing to search items or check "Show All"…');
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + showAll;

            // Return cached result if available (same query, same branch)
            if (islCache[cacheKey]) {
                if (islLastKey !== cacheKey) {
                    islLastKey = cacheKey;
                    renderItems(islCache[cacheKey]);
                }
                return;
            }

            islLastKey = cacheKey;
            let params = { branch_id: branchId, search: srch, code: code, show_all: showAll };

            $('#isl-loading').removeClass('d-none');
            $('#isl-no-results').addClass('d-none');
            $('#isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#isl-loading').addClass('d-none');
                // Cache for 60s
                islCache[cacheKey] = res.items || [];
                setTimeout(function() { delete islCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        function renderItems(items) {
            let $tbody = $('#isl-items-body');
            $tbody.empty();

            if (items.length === 0) {
                $('#isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#isl-count-label').text('');
                return;
            }

            // Build rows in one string for faster DOM insertion
            let html = '';
            let todayStr = new Date().toISOString().substring(0, 10);
            items.forEach(function (it, idx) {
                let itExpStr = it.exp_date ? it.exp_date.toString().substring(0, 10) : '';
                let isExpired = itExpStr && (itExpStr < todayStr);

                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                let isAllowNeg = !!(it.allow_negative_stock);
                let isOutOfStock = parseFloat(it.qty) <= 0 && !isAllowNeg;
                let isBlocked = isOutOfStock || isExpired;
                let qtyClass = isOutOfStock ? 'text-danger font-weight-bold' : (parseFloat(it.qty) <= 0 ? 'text-warning font-weight-bold' : 'text-success font-weight-bold');
                let rowClass = isBlocked ? 'isl-item-row isl-item-disabled text-muted bg-light' : 'isl-item-row';
                let rowStyle = isBlocked ? 'cursor: not-allowed; opacity: 0.6;' : 'cursor: pointer;';
                let actionBtn = isExpired
                    ? `<button type="button" class="btn btn-danger btn-xs px-2" disabled title="Expired Item - Cannot sell">
                        <i class="fas fa-ban mr-1"></i>Expired
                       </button>`
                    : (isOutOfStock
                        ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock - Cannot select">
                            <i class="fas fa-ban mr-1"></i>Out of Stock
                           </button>`
                        : `<button type="button" class="btn btn-success btn-xs px-2 isl-btn-select"
                            data-id="${it.id}" data-code="${it.code}">
                            <i class="fas fa-check mr-1"></i>Select
                           </button>`);

                html += `
                    <tr class="${rowClass}" style="${rowStyle}"
                        data-id="${it.id}"
                        data-code="${it.code}"
                        data-sell="${it.sell_price}"
                        data-mrp="${it.mrp}"
                        data-gst="${it.gst_percent}"
                        data-qty="${it.qty}"
                        data-allow-negative="${isAllowNeg ? '1' : '0'}"
                        data-exp="${it.exp_date || ''}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx+1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name} ${isExpired ? '<span class="badge badge-danger ml-1 small">EXPIRED</span>' : (isOutOfStock ? '<span class="badge badge-secondary ml-1 small">Out of Stock</span>' : (parseFloat(it.qty) <= 0 && isAllowNeg ? '<span class="badge badge-warning ml-1 small">Allow Neg Stock</span>' : ''))}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-right ${qtyClass}">${formatDigits(it.qty)}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${it.sell_price > 0 ? '\u20b9' + parseFloat(it.sell_price).toFixed(2) : '\u2014'}</td>
                        <td class="align-middle text-right text-muted">${it.mrp > 0 ? '\u20b9' + parseFloat(it.mrp).toFixed(2) : '\u2014'}</td>
                        <td class="align-middle text-center">
                            ${actionBtn}
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            $('#isl-table-wrap').removeClass('d-none');
            $('#isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');
            // Auto-highlight top selectable row
            $tbody.find('tr.isl-item-row:not(.isl-item-disabled)').first().addClass('table-primary');
        }

        $(document).on('mouseenter', '#isl-items-body tr.isl-item-row', function () {
            if (!$(this).hasClass('isl-item-disabled')) {
                $('#isl-items-body tr.isl-item-row').removeClass('table-primary');
                $(this).addClass('table-primary');
            }
        });

        // Clicking a row or its Select button picks the item
        $(document).on('click', '.isl-item-row, .isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('isl-item-row') ? $(this) : $(this).closest('tr');
            let allowNeg = $row.data('allow-negative') == 1 || $row.data('allow-negative-stock') == 1;
            if ($row.hasClass('isl-item-disabled') || (parseFloat($row.data('qty')) <= 0 && !allowNeg)) {
                return false;
            }
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');

            if (! activeSearchRow || ! itemId) return;

            sbItemSelectedInModal = true;
            sbCancellingRow = null;

            // Fill code field with Item ID (Task 4) and trigger the existing lookup (which handles expiry / batch)
            activeSearchRow.find('.sb-item-code').val(itemId);
            activeSearchRow.find('.sb-item-select').val(itemId);
            let itemExp = $row.data('exp');
            if (itemExp) {
                activeSearchRow.find('.sb-exp-date').val(itemExp.toString().substring(0, 10));
            }
            processItemLookup(null, activeSearchRow, itemId);
            $('#sb-item-search-modal').modal('hide');
        });

        let sbCancellingRow = null;
        let sbItemSelectedInModal = false;

        // When modal closes, cleanly dismiss and prevent auto-reopen
        $('#sb-item-search-modal').on('show.bs.modal', function () {
            islModalOpen = true;
            islModalClosing = false;
            sbItemSelectedInModal = false;
            sbCancellingRow = null;
        });

        $('#sb-item-search-modal').on('hide.bs.modal', function () {
            islModalOpen = false;
            islModalClosing = true;
            if (!sbItemSelectedInModal && activeSearchRow && activeSearchRow.length) {
                let selectedId = activeSearchRow.find('.sb-item-select').val();
                if (!selectedId) {
                    sbCancellingRow = activeSearchRow;
                }
            }
        });

        $('#sb-item-search-modal').on('hidden.bs.modal', function () {
            islModalOpen = false;
            islModalClosing = true;
            setTimeout(function () {
                islModalClosing = false;
            }, 350);

            if (!sbItemSelectedInModal && sbCancellingRow && sbCancellingRow.length) {
                let totalRows = $('#sb-items-body tr').length;
                if (totalRows > 1) {
                    sbCancellingRow.remove();
                    updateRowNumbers();
                    calculateTotals();
                } else {
                    sbCancellingRow.find('.sb-item-code').val('');
                    sbCancellingRow.find('.sb-item-desc').val('');
                }
                sbCancellingRow = null;
                activeSearchRow = null;
                setTimeout(function () {
                    let $tender = $('#tender-cash-amount, #btn-tender-save, #sb-add-row');
                    if ($tender.length) {
                        $tender.first().focus();
                    }
                }, 60);
                return;
            }

            sbItemSelectedInModal = false;
            sbCancellingRow = null;
            activeSearchRow = null;
        });


        function updateBranchBadge() {
            let branchName = $('input[name="branch_id"]').prev().val() || 'Active Branch';
            $('#sb-branch-badge').html('<i class="fas fa-store mr-1"></i> Active Branch: <strong>' + branchName + '</strong>');
        }
        updateBranchBadge();

        function updateRowNumbers() {
            $('#sb-items-body tr').each(function (idx) {
                $(this).find('.sb-sr-no').text(idx + 1);
            });
        }

        function formatDigits(num) {
            if (num === '' || num === null || num === undefined || isNaN(num)) return '';
            let n = parseFloat(num);
            return (n % 1 === 0) ? n.toFixed(0) : n.toString();
        }

        function calculateRow($row, source) {
            let qty = parseFloat($row.find('.sb-qty').val()) || 0;
            let sellPrice = parseFloat($row.find('.sb-sell-price').val()) || 0;
            let mrp = parseFloat($row.find('.sb-mrp').val()) || 0;
            let stockVal = $row.find('.sb-item-stock-val').val();
            if (stockVal === undefined || stockVal === '') stockVal = $row.data('stock');
            let stock = parseFloat(stockVal) || 0;

            let base = qty * sellPrice;
            let $discPct = $row.find('.sb-disc-percent');
            let $discAmt = $row.find('.sb-disc-amount');
            let gst = parseFloat($row.find('.sb-gst-percent').val()) || 0;

            let discPct = parseFloat($discPct.val()) || 0;
            let discAmt = parseFloat($discAmt.val()) || 0;

            if (source === 'percent') {
                if (base > 0 && discPct > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discPct === 0) {
                    discAmt = 0;
                    $discAmt.val('');
                }
            } else if (source === 'amount') {
                if (base > 0 && discAmt > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? formatDigits(discPct) : '');
                } else if (discAmt === 0) {
                    discPct = 0;
                    $discPct.val('');
                }
            } else {
                if (discPct > 0 && base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discAmt > 0 && base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? formatDigits(discPct) : '');
                }
            }

            // Tax-Inclusive GST calculation
            // Selling price already includes GST. Net Amount = Base - Disc Amount!
            let net = Math.max(0, base - discAmt);
            let gstTaxAmt = 0;
            if (net > 0 && gst > 0) {
                let preTax = net / (1 + (gst / 100));
                gstTaxAmt = Math.round((net - preTax) * 100) / 100;
            }

            $row.find('.sb-gst-tax-amount').val(gstTaxAmt > 0 ? gstTaxAmt.toFixed(2) : '0.00');

            if (base > 0) {
                $row.find('.sb-row-net').text(net > 0 ? net.toFixed(2) : '');
            } else {
                $row.find('.sb-row-net').text('');
            }

            // Strict stock validation: check TOTAL qty across ALL rows for the same item
            let $qtyInput = $row.find('.sb-qty');
            let itemId = $row.find('.sb-item-select').val();
            let isAllowNegative = $row.data('allow-negative-stock') == 1 ||
                                  ($row.data('item-data') && $row.data('item-data').allow_negative_stock);

            if (!isAllowNegative && stock >= 0 && itemId && qty > 0) {
                let totalForItem = 0;
                $('#sb-items-body tr').each(function () {
                    if ($(this).find('.sb-item-select').val() === itemId) {
                        totalForItem += parseFloat($(this).find('.sb-qty').val()) || 0;
                    }
                });
                $('#sb-items-body tr').each(function () {
                    if ($(this).find('.sb-item-select').val() === itemId) {
                        let $q = $(this).find('.sb-qty');
                        if (totalForItem > stock) {
                            $q.addClass('border-danger text-danger is-invalid')
                              .attr('title', 'Total qty (' + formatDigits(totalForItem) + ') exceeds available stock (' + formatDigits(stock) + ')!');
                        } else {
                            $q.removeClass('border-danger text-danger is-invalid').attr('title', '');
                        }
                    }
                });
                if (source !== 'initial' && totalForItem > stock && !$qtyInput.data('stock-alerted')) {
                    $qtyInput.data('stock-alerted', true);
                    alert('Stock is only ' + formatDigits(stock) + '. Quantity (' + formatDigits(totalForItem) + ') cannot exceed available stock!');
                } else if (totalForItem <= stock) {
                    $qtyInput.data('stock-alerted', false);
                }
            } else {
                $qtyInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
            }

            // Real-time inline field validation (Task 11)
            if (itemId) {
                if (qty <= 0) {
                    $qtyInput.addClass('border-danger text-danger is-invalid')
                             .attr('title', 'Quantity must be greater than 0');
                } else if (!isAllowNegative && stock >= 0 && qty > stock) {
                    $qtyInput.addClass('border-danger text-danger is-invalid')
                             .attr('title', 'Quantity exceeds available stock (' + formatDigits(stock) + ')');
                } else {
                    $qtyInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                let $sell = $row.find('.sb-sell-price');
                if (mrp > 0 && sellPrice > mrp) {
                    $sell.addClass('border-danger text-danger is-invalid')
                         .attr('title', 'Selling price cannot exceed MRP (₹' + mrp + ')');
                } else {
                    $sell.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                let $discPctInput = $row.find('.sb-disc-percent');
                if (discPct < 0 || discPct > 100) {
                    $discPctInput.addClass('border-danger text-danger is-invalid')
                                 .attr('title', 'Discount cannot exceed 100%');
                } else {
                    $discPctInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                let $exp = $row.find('.sb-exp-date');
                let expVal = $exp.val();
                let todayStr = new Date().toISOString().substring(0, 10);
                if (expVal && expVal.substring(0, 10) < todayStr) {
                    $exp.addClass('border-danger bg-danger text-white is-invalid')
                        .attr('title', 'Product is expired!');
                } else {
                    $exp.removeClass('border-danger bg-danger text-white is-invalid').attr('title', '');
                }
            }

            calculateTotals();
        }

        // Header Validation (Task 11)
        function validateSbHeader(showAlert = false) {
            let isValid = true;
            let $cust = $('#customer_id');
            let custVal = $cust.val();
            let $custSelect2 = $cust.next('.select2-container').find('.select2-selection');

            if (!custVal) {
                $cust.addClass('is-invalid');
                $custSelect2.addClass('border-danger');
                if (showAlert) {
                    alert('Please select a Customer first before entering items.');
                    $cust.select2('open');
                }
                isValid = false;
            } else {
                $cust.removeClass('is-invalid');
                $custSelect2.removeClass('border-danger');
            }

            let $branch = $('[name="branch_id"]');
            if (!$branch.val()) {
                if (showAlert && isValid) alert('Please select an active Branch.');
                isValid = false;
            }

            let $date = $('input[name="bill_date"]');
            if (!$date.val()) {
                $date.addClass('is-invalid border-danger');
                if (showAlert && isValid) {
                    alert('Please select a Bill Date.');
                    $date.focus();
                }
                isValid = false;
            } else {
                $date.removeClass('is-invalid border-danger');
            }

            return isValid;
        }

        // Validate stock, quantities, pricing, and expiry across all rows (Task 11)
        function validateStockErrors() {
            let itemTotals = {};
            let itemStocks = {};
            let todayStr = new Date().toISOString().substring(0, 10);

            $('#sb-items-body tr').each(function () {
                let itemId = $(this).find('.sb-item-select').val();
                let qty = parseFloat($(this).find('.sb-qty').val()) || 0;
                let stockVal = $(this).find('.sb-item-stock-val').val();
                if (stockVal === undefined || stockVal === '') stockVal = $(this).data('stock');
                let stock = parseFloat(stockVal);

                if (itemId) {
                    itemTotals[itemId] = (itemTotals[itemId] || 0) + qty;
                    if (!isNaN(stock)) {
                        itemStocks[itemId] = stock;
                    }
                }
            });

            let hasError = false;
            let firstErrorMsg = '';
            let firstErrorEl = null;
            let validItemCount = 0;

            $('#sb-items-body tr').each(function (idx) {
                let $row = $(this);
                let itemId = $row.find('.sb-item-select').val();
                let $qtyInput = $row.find('.sb-qty');
                let qty = parseFloat($qtyInput.val()) || 0;
                let sellPrice = parseFloat($row.find('.sb-sell-price').val()) || 0;
                let mrp = parseFloat($row.find('.sb-mrp').val()) || 0;
                let exp = $row.find('.sb-exp-date').val();

                if (itemId) {
                    let totalQty = itemTotals[itemId] || 0;
                    let stock = itemStocks[itemId] !== undefined ? itemStocks[itemId] : null;
                    let isAllowNegative = $row.data('allow-negative-stock') == 1 ||
                                          ($row.data('item-data') && $row.data('item-data').allow_negative_stock);

                    if (qty <= 0) {
                        $qtyInput.addClass('border-danger text-danger is-invalid')
                                 .attr('title', 'Quantity must be greater than 0.');
                        hasError = true;
                        if (!firstErrorMsg) {
                            firstErrorMsg = `Row #${idx + 1}: Quantity must be greater than 0.`;
                            firstErrorEl = $qtyInput;
                        }
                    } else if (!isAllowNegative && stock !== null && stock >= 0 && totalQty > stock) {
                        $qtyInput.addClass('border-danger text-danger is-invalid')
                                 .attr('title', 'Total qty (' + formatDigits(totalQty) + ') across all rows exceeds stock (' + formatDigits(stock) + ')!');
                        hasError = true;
                        if (!firstErrorMsg) {
                            firstErrorMsg = `Row #${idx + 1}: Quantity exceeds available stock (${formatDigits(stock)}).`;
                            firstErrorEl = $qtyInput;
                        }
                    } else {
                        $qtyInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                        validItemCount++;
                    }

                    if (mrp > 0 && sellPrice > mrp) {
                        $row.find('.sb-sell-price').addClass('border-danger text-danger is-invalid');
                        hasError = true;
                        if (!firstErrorMsg) {
                            firstErrorMsg = `Row #${idx + 1}: Selling price (₹${sellPrice}) cannot exceed MRP (₹${mrp}).`;
                            firstErrorEl = $row.find('.sb-sell-price');
                        }
                    }

                    if (exp && exp.substring(0, 10) < todayStr) {
                        $row.find('.sb-exp-date').addClass('border-danger bg-danger text-white is-invalid');
                        hasError = true;
                        if (!firstErrorMsg) {
                            firstErrorMsg = `Row #${idx + 1}: Product has expired (${exp}) and cannot be sold.`;
                            firstErrorEl = $row.find('.sb-exp-date');
                        }
                    }
                } else {
                    $qtyInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }
            });

            return {
                hasStockError: hasError,
                hasError: hasError,
                errorMsg: firstErrorMsg,
                errorEl: firstErrorEl,
                validItemCount: validItemCount
            };
        }

        function calculateTotals(isManualRoundOff) {
            let totalQty = 0;
            let totalDisc = 0;
            let totalGst = 0;
            let totalNet = 0;
            let itemCount = 0;

            $('#sb-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.sb-qty').val()) || 0;
                let sellPrice = parseFloat($r.find('.sb-sell-price').val()) || 0;
                let discAmt = parseFloat($r.find('.sb-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.sb-gst-percent').val()) || 0;

                if (qty > 0 || sellPrice > 0) {
                    itemCount++;
                    let base = qty * sellPrice;
                    let net = Math.max(0, base - discAmt);
                    let gstAmt = 0;
                    if (net > 0 && gst > 0) {
                        let preTax = net / (1 + (gst / 100));
                        gstAmt = Math.round((net - preTax) * 100) / 100;
                    }

                    totalQty += qty;
                    totalDisc += discAmt;
                    totalGst += gstAmt;
                    totalNet += net;
                }
            });

            let extraCess = parseFloat($('input[name="total_extra_cess"]').val()) || 0;
            let calCess = parseFloat($('input[name="gst_calamity_cess"]').val()) || 0;
            let rawTotal = totalNet + extraCess + calCess;

            let finalTotal = 0;
            let roundOff = 0;

            if (isManualRoundOff) {
                roundOff = parseFloat($('input[name="round_off"]').val()) || 0;
                finalTotal = Math.round((rawTotal + roundOff) * 100) / 100;
            } else {
                let roundedTotal = Math.round(rawTotal);
                roundOff = Math.round((roundedTotal - rawTotal) * 100) / 100;
                $('input[name="round_off"]').val(roundOff !== 0 ? roundOff.toFixed(2) : '0.00');
                finalTotal = roundedTotal;
            }

            $('#footer-sb-qty').text(totalQty > 0 ? formatDigits(totalQty) : '');
            $('#footer-sb-disc').text(totalDisc > 0 ? totalDisc.toFixed(2) : '');
            $('#footer-sb-tax').text(totalGst > 0 ? totalGst.toFixed(2) : '');
            $('#footer-sb-net').text(totalNet > 0 ? totalNet.toFixed(2) : '');

            $('#display-sb-final-total').text(finalTotal > 0 ? finalTotal.toFixed(2) : '0.00');
            $('#sb-total-items-badge').html('<span class="badge badge-primary px-3 py-2 font-weight-bold">' + itemCount + ' Item' + (itemCount === 1 ? '' : 's') + '</span>');

            updateSaveButtonState();
        }

        // Update Save button status title based on stock and items validity
        function updateSaveButtonState() {
            let res = validateStockErrors();
            let $saveBtn = $('button[type="submit"]');

            if (res.hasStockError || res.validItemCount === 0) {
                let reason = res.validItemCount === 0 ? 'Please add at least 1 item with valid quantity.' : 'Some item quantities exceed available stock or are invalid.';
                $saveBtn.attr('title', reason);
            } else {
                $saveBtn.attr('title', '');
            }
        }

        // Open Batch Selection Modal
        function showBatchModal($row, item, batches) {
            activeModalRow = $row;
            $('#modal-item-title').text(item.name || 'Item');
            $('#modal-item-code').text(item.item_code || item.ean_upc_code || '—');

            let $tbody = $('#modal-batches-body');
            $tbody.empty();

            let todayBatchStr = new Date().toISOString().substring(0, 10);
            batches.forEach(function (b, idx) {
                let pName = b.productname || item.name || 'Item';
                let pCode = b.code || item.item_code || item.ean_upc_code || '—';
                let expDisplay = b.exp_date || 'No Expiry';
                let bExpStr = b.exp_date ? b.exp_date.toString().substring(0, 10) : '';
                let isBatchExpired = bExpStr && (bExpStr < todayBatchStr);
                let qtyNum = parseFloat(b.qty || 0);
                let isBatchOOS = qtyNum <= 0;
                let isBlocked = isBatchOOS || isBatchExpired;
                let qtyDisplay = qtyNum.toFixed(3);
                let sellDisplay = b.sell_price ? '₹' + parseFloat(b.sell_price).toFixed(2) : '—';
                let mrpDisplay = b.mrp ? '₹' + parseFloat(b.mrp).toFixed(2) : '—';
                let bRowClass = isBlocked ? 'batch-select-row batch-disabled text-muted bg-light' : 'batch-select-row';
                let bRowStyle = isBlocked ? 'cursor: not-allowed; opacity: 0.65;' : 'cursor: pointer;';
                let bActionBtn = isBatchExpired
                    ? `<button type="button" class="btn btn-danger btn-xs px-2" disabled title="Batch Expired">
                        <i class="fas fa-ban mr-1"></i>Expired
                       </button>`
                    : (isBatchOOS
                        ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock">
                            <i class="fas fa-ban mr-1"></i>Out of Stock
                           </button>`
                        : `<button type="button" class="btn btn-success btn-xs px-2 btn-apply-batch" 
                            data-exp="${b.exp_date || ''}" 
                            data-sell="${b.sell_price || ''}" 
                            data-mrp="${b.mrp || ''}">
                            <i class="fas fa-check mr-1"></i> Select
                           </button>`);

                let tr = `
                    <tr class="${bRowClass}" style="${bRowStyle}" 
                        data-exp="${b.exp_date || ''}" 
                        data-sell="${b.sell_price || ''}" 
                        data-mrp="${b.mrp || ''}"
                        data-qty="${qtyNum}"
                        title="${isBatchExpired ? 'Batch expired - cannot select' : (isBatchOOS ? 'Batch out of stock' : 'Click to select this batch')}">
                        <td class="align-middle text-center font-weight-bold">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${pName} ${isBatchExpired ? '<span class="badge badge-danger ml-1 small">EXPIRED</span>' : (isBatchOOS ? '<span class="badge badge-secondary ml-1 small">No Stock</span>' : '')}</td>
                        <td class="align-middle text-center"><span class="badge badge-secondary px-2 py-1">${pCode}</span></td>
                        <td class="align-middle text-center font-weight-bold ${isBatchExpired ? 'text-danger font-weight-bolder' : 'text-primary'}"><i class="far fa-calendar-alt mr-1"></i> ${expDisplay}</td>
                        <td class="align-middle text-right font-weight-bold ${isBatchOOS ? 'text-danger' : ''}">${qtyDisplay}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-center">
                            ${bActionBtn}
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });

            $('#sb-batch-modal').modal('show');
            setTimeout(function() {
                $('#modal-batches-body tr:not(.batch-disabled):first .btn-apply-batch').focus();
            }, 350);
        }

        function applyBatchToRow(exp, sell, mrp) {
            if (!activeModalRow) return;
            if (exp) {
                let cleanExp = exp.toString().substring(0, 10);
                let todayStr = new Date().toISOString().substring(0, 10);
                if (cleanExp < todayStr) {
                    alert('Cannot select expired batch (Expired on ' + cleanExp + '). Selling expired products is prohibited.');
                    return;
                }
                activeModalRow.find('.sb-exp-date').val(cleanExp);
            }
            if (sell && parseFloat(sell) > 0) activeModalRow.find('.sb-sell-price').val(parseFloat(sell).toFixed(2));
            if (mrp && parseFloat(mrp) > 0) activeModalRow.find('.sb-mrp').val(parseFloat(mrp).toFixed(2));

            $('#sb-batch-modal').modal('hide');
            calculateRow(activeModalRow, 'base');
            saveBillDraft(); // Save draft after batch is applied
            setTimeout(() => activeModalRow.find('.sb-qty').focus().select(), 100);
        }

        // When user selects a batch from modal button or row
        $(document).on('click', '.btn-apply-batch', function (e) {
            e.stopPropagation();
            if ($(this).closest('tr').hasClass('batch-disabled')) return false;
            let exp = $(this).data('exp') || '';
            let sell = $(this).data('sell') || '';
            let mrp = $(this).data('mrp') || '';
            applyBatchToRow(exp, sell, mrp);
        });

        $(document).on('click', '.batch-select-row', function () {
            if ($(this).hasClass('batch-disabled')) return false;
            let exp = $(this).data('exp') || '';
            let sell = $(this).data('sell') || '';
            let mrp = $(this).data('mrp') || '';
            applyBatchToRow(exp, sell, mrp);
        });

        // Click on batch button in row to re-open modal
        $(document).on('click', '.sb-btn-choose-batch', function () {
            let $row = $(this).closest('tr');
            let batches = $row.data('batches') || [];
            let item = $row.data('item-data') || {};
            if (batches.length > 1) {
                showBatchModal($row, item, batches);
            }
        });

        let isSyncing = false;

        // Main Item Lookup Function
        function processItemLookup(query, $row, itemId) {
            let branchId = $('[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let $select = $row.find('.sb-item-select');
            let $desc = $row.find('.sb-item-desc');
            let $code = $row.find('.sb-item-code');
            let $exp = $row.find('.sb-exp-date');
            let $sell = $row.find('.sb-sell-price');
            let $mrp = $row.find('.sb-mrp');
            let $gst = $row.find('.sb-gst-percent');
            let $batchWrap = $row.find('.sb-batch-btn-wrap');

            let billId = '{{ $bill->id ?? "" }}';
            let params = { branch_id: branchId };
            if (billId) {
                params.sales_bill_id = billId;
            }
            if (itemId) {
                params.item_id = itemId;
            } else if (query) {
                params.query = query;
            } else {
                return;
            }

            $.getJSON('{{ route("sales.sales-bills.lookup-item") }}', params, function (res) {
                if (res && res.found && res.item) {
                    let item = res.item;
                    let batches = res.batches || [];

                    $row.data('item-data', item);
                    $row.data('batches', batches);

                    isSyncing = true;
                    // Sync Code: Task 4 requirement - display Item ID in code field even if scanned by EAN
                    $code.val(item.id);

                    // Sync description display & hidden item id
                    $desc.val(item.name + (item.item_code ? ' [' + item.item_code + ']' : ''));
                    $select.val(item.id);
                    isSyncing = false;

                    // Store Product Stock for validation
                    let stockNum = item.stock ? parseFloat(item.stock) : 0;
                    $row.attr('data-stock', stockNum);
                    $row.find('.sb-item-stock-val').val(stockNum);
                    if (item.allow_negative_stock !== undefined) {
                        $row.attr('data-allow-negative-stock', item.allow_negative_stock ? '1' : '0');
                        $row.data('allow-negative-stock', item.allow_negative_stock ? 1 : 0);
                    }

                    // Set Sell Price, MRP, GST %
                    if (item.sell_price > 0 && (!$sell.val() || parseFloat($sell.val()) === 0)) {
                        $sell.val(parseFloat(item.sell_price).toFixed(2));
                    }
                    if (item.mrp > 0 && (!$mrp.val() || parseFloat($mrp.val()) === 0)) {
                        $mrp.val(parseFloat(item.mrp).toFixed(2));
                    }
                    if (item.gst_percent !== undefined && item.gst_percent !== null) {
                        $gst.val(formatDigits(item.gst_percent));
                    }
                    if (!$row.find('.sb-qty').val() || parseFloat($row.find('.sb-qty').val()) === 0) {
                        $row.find('.sb-qty').val('1');
                    }

                    // =========================================================
                    // BATCH / EXPIRY SELECTION LOGIC:
                    // Auto-fill expiry date from purchase records!
                    // =========================================================
                    let bestExp = '';
                    if (batches.length > 0 && batches[0].exp_date) {
                        bestExp = batches[0].exp_date.toString().substring(0, 10);
                    } else if (item.exp_date) {
                        bestExp = item.exp_date.toString().substring(0, 10);
                    }
                    if (bestExp) {
                        $exp.val(bestExp);
                    }

                    if (batches.length === 1) {
                        let singleBatch = batches[0];
                        if (singleBatch && singleBatch.sell_price > 0) {
                            $sell.val(parseFloat(singleBatch.sell_price).toFixed(2));
                        }
                        if (singleBatch && singleBatch.mrp > 0) {
                            $mrp.val(parseFloat(singleBatch.mrp).toFixed(2));
                        }
                        $batchWrap.addClass('d-none');
                        calculateRow($row, 'base');
                        saveBillDraft();
                        setTimeout(() => $row.find('.sb-qty').focus().select(), 60);
                    } else if (batches.length > 1) {
                        // Multiple batches exist: prefill earliest expiry and show button/modal
                        $batchWrap.removeClass('d-none');
                        showBatchModal($row, item, batches);
                    } else {
                        $batchWrap.addClass('d-none');
                        calculateRow($row, 'base');
                        saveBillDraft();
                        setTimeout(() => $row.find('.sb-qty').focus().select(), 60);
                    }

                    calculateRow($row, 'base');
                } else {
                    $code.addClass('is-invalid');
                    setTimeout(() => $code.removeClass('is-invalid'), 2000);
                }
            });
        }

        // 1. Enter Code / Barcode in row
        $(document).on('change blur keydown', '.sb-item-code', function (e) {
            if (isSyncing) return;
            if (e.type === 'keydown' && e.key !== 'Enter') return;
            if (e.type === 'keydown' && e.key === 'Enter') {
                e.preventDefault();
            }
            let $input = $(this);
            let query = $.trim($input.val());
            if (!query) return;

            // Barcode Gun Rapid Double-Scan Filter (< 400ms hardware bounce guard)
            if (window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(query);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate bounce
                }
            }

            let $row = $input.closest('tr');
            processItemLookup(query, $row, null);
        });

        // 2. Select Item from Description Select2
        $(document).on('change', '.sb-item-select', function () {
            if (isSyncing) return;
            let $select = $(this);
            let itemId = $select.val();
            let $row = $select.closest('tr');

            if (!itemId) {
                $row.find('.sb-item-code').val('');
                $row.find('.sb-item-stock').val('');
                $row.find('.sb-exp-date').val('');
                $row.find('.sb-batch-btn-wrap').addClass('d-none');
                return;
            }

            processItemLookup(null, $row, itemId);
        });

        // 3. Real-time Calculation Listeners
        $(document).on('input', '.sb-qty, .sb-sell-price, .sb-mrp', function () {
            calculateRow($(this).closest('tr'), 'base');
        });

        $(document).on('input', '.sb-disc-percent', function () {
            calculateRow($(this).closest('tr'), 'percent');
        });

        $(document).on('input', '.sb-disc-amount', function () {
            calculateRow($(this).closest('tr'), 'amount');
        });

        $(document).on('input change blur', '.sb-gst-percent', function () {
            calculateRow($(this).closest('tr'), 'other');
        });

        $(document).on('input change', 'input[name="round_off"], input[name="total_extra_cess"], input[name="gst_calamity_cess"]', function () {
            calculateTotals();
        });

        // 4. Add Row
        $('#sb-add-row').on('click', function () {
            let html = $('#sb-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#sb-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);
            $newRow.find('input').attr('autocomplete', 'off');
            rowIndex++;
            updateRowNumbers();
            calculateTotals();
            $newRow.find('.sb-item-code').focus();
        });

        // Strict stock validation for sales bill quantity
        function validateSbQty($qtyInput, showAlert = true) {
            let $row = $qtyInput.closest('tr');
            let itemId = $row.find('.sb-item-select').val();
            if (!itemId) return true;

            let qty = parseFloat($qtyInput.val()) || 0;
            let stockVal = $row.find('.sb-item-stock-val').val();
            if (stockVal === undefined || stockVal === '') stockVal = $row.data('stock');
            let stock = parseFloat(stockVal) || 0;

            let isAllowNegative = $row.data('allow-negative-stock') == 1 ||
                                  ($row.data('item-data') && $row.data('item-data').allow_negative_stock);

            if (!isAllowNegative && stock >= 0) {
                let totalForItem = 0;
                $('#sb-items-body tr').each(function () {
                    if ($(this).find('.sb-item-select').val() === itemId) {
                        totalForItem += parseFloat($(this).find('.sb-qty').val()) || 0;
                    }
                });

                if (totalForItem > stock) {
                    $qtyInput.addClass('border-danger text-danger is-invalid');
                    if (showAlert) {
                        alert('Stock is only ' + formatDigits(stock) + '. Quantity (' + formatDigits(totalForItem) + ') cannot exceed available stock!');
                        setTimeout(function () { $qtyInput.focus().select(); }, 10);
                    }
                    return false;
                }
            }
            $qtyInput.removeClass('border-danger text-danger is-invalid');
            return true;
        }

        // Fast POS keyboard flow: Qty -> Disc % -> Disc Amt -> next row (if exists)
        $(document).on('keydown', '.sb-qty', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                if (!validateSbQty($(this), true)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).closest('tr').find('.sb-disc-percent').focus().select();
                }
            }
        });

        $(document).on('change', '.sb-qty', function () {
            validateSbQty($(this), true);
        });

        $(document).on('keydown', '.sb-disc-percent', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('tr').find('.sb-disc-amount').focus().select();
            }
        });

        $(document).off('keydown', '.sb-disc-amount').on('keydown', '.sb-disc-amount', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $nextRow = $(this).closest('tr').next('tr');
                if ($nextRow.length) {
                    e.preventDefault();
                    $nextRow.find('.sb-item-code').focus();
                } else {
                    e.preventDefault();
                    $('#sb-add-row').trigger('click');
                }
            }
        });

        // 5. Remove Row
        $('#sb-items-body').on('click', '.sb-remove-row', function () {
            let rows = $('#sb-items-body tr');
            if (rows.length <= 1) return;
            $(this).closest('tr').remove();
            updateRowNumbers();
            calculateTotals();
        });

        // 6. Initial Run on existing rows
        updateRowNumbers();
        $('#sb-items-body tr').each(function () {
            let $r = $(this);
            calculateRow($r, 'initial');
            let itemId = $r.find('.sb-item-select').val();
            if (itemId) {
                processItemLookup(null, $r, itemId);
            }
        });
        calculateTotals();

        /* ================================================================
           TENDER / PAYMENT MODAL — intercept form submit
           ================================================================ */
        const TENDER_TYPES = JSON.parse(document.getElementById('tender-types-data').textContent || '[]');
        let tenderBillTotal = 0;

        function recalcTender() {
            let cash = parseFloat($('#tender-cash').val()) || 0;
            let credit = parseFloat($('#tender-credit').val()) || 0;
            let card = parseFloat($('#tender-card').val()) || 0;
            let wallet = parseFloat($('#tender-wallet').val()) || 0;
            let rrn = parseFloat($('#tender-rrn').val()) || 0;

            let tendered = Math.round((cash + credit + card + wallet + rrn) * 100) / 100;
            let balance = Math.round((tendered - tenderBillTotal) * 100) / 100;

            $('#tender-tendered-display').text(tendered.toFixed(2));

            if (balance >= 0) {
                $('#tender-balance-display').text(balance.toFixed(2));
                $('#tender-outstanding-display').text('0.00');
            } else {
                $('#tender-balance-display').text('0.00');
                $('#tender-outstanding-display').text(Math.abs(balance).toFixed(2));
            }
            $('#tender-error').addClass('d-none').text('');
        }

        // Synchronize Wallet amounts on left and right columns
        $(document).on('input', '#tender-wallet', function () {
            $('#tender-wallet-side').val($(this).val());
            recalcTender();
        });
        $(document).on('input', '#tender-wallet-side', function () {
            $('#tender-wallet').val($(this).val());
            recalcTender();
        });

        $(document).on('input', '#tender-cash, #tender-credit, #tender-card, #tender-rrn', function () {
            recalcTender();
        });

        // Guard adding items or focusing code if header is invalid (Task 11)
        // Customer is optional during item entry - can be added before or after items
        // (No blocking gate here — customer is validated at submit time only)


        $(document).on('change', '#customer_id', function () {
            validateSbHeader(false);
        });

        $(document).on('change blur', 'input[name="bill_date"]', function () {
            validateSbHeader(false);
        });

        // Open tender modal when Save button clicked
        $(document).on('click', 'button[type="submit"]', function (e) {
            let $btn = $(this);
            let $form = $btn.closest('form');
            if (!$form.length) return;

            // 1. Validate Header First (Task 11)
            if (!validateSbHeader(true)) {
                e.preventDefault();
                return false;
            }

            // Prune empty rows (where no item is selected) if multiple rows exist
            $('#sb-items-body tr').each(function () {
                let $r = $(this);
                let itemId = $r.find('.sb-item-select').val();
                if (!itemId && $('#sb-items-body tr').length > 1) {
                    $r.remove();
                }
            });
            updateRowNumbers();
            calculateTotals();

            // 2. Validate All Line Items (Task 11)
            let valResult = validateStockErrors();
            if (valResult.validItemCount === 0) {
                e.preventDefault();
                alert('Please select at least one item and enter a valid quantity.');
                return false;
            }

            if (valResult.hasError) {
                e.preventDefault();
                alert('Cannot proceed: ' + valResult.errorMsg);
                if (valResult.errorEl && valResult.errorEl.length) {
                    valResult.errorEl.focus();
                    if (valResult.errorEl[0].select) valResult.errorEl[0].select();
                }
                return false;
            }

            e.preventDefault();

            // Read current bill total from display
            tenderBillTotal = parseFloat($('#display-sb-final-total').text()) || 0;
            $('#tender-total-display').text(tenderBillTotal.toFixed(2));
            $('#tender-advance-display').text('0.00');
            $('#tender-loyalty-display').text(tenderBillTotal.toFixed(2));

            @if(isset($bill) && $bill->payments && $bill->payments->count())
                let existingBillPayments = @json($bill->payments);
                let hasPreFilled = false;
                $('#tender-cash, #tender-credit, #tender-card, #tender-wallet, #tender-wallet-side, #tender-rrn, #tender-card-no, #tender-wallet-refno').val('');
                existingBillPayments.forEach(function(bp) {
                    let amt = parseFloat(bp.amount) || 0;
                    if (amt <= 0) return;
                    let typeName = (bp.tender_type ? (bp.tender_type.type || bp.tender_type.name) : '').toLowerCase();
                    if (typeName === 'cash') {
                        $('#tender-cash').val(amt.toFixed(2));
                        hasPreFilled = true;
                    } else if (typeName === 'credit') {
                        $('#tender-credit').val(amt.toFixed(2));
                        hasPreFilled = true;
                    } else if (typeName === 'card') {
                        $('#tender-card').val(amt.toFixed(2));
                        hasPreFilled = true;
                    } else if (typeName === 'wallet') {
                        $('#tender-wallet, #tender-wallet-side').val(amt.toFixed(2));
                        hasPreFilled = true;
                    } else if (typeName === 'finance' || typeName === 'rrn') {
                        $('#tender-rrn').val(amt.toFixed(2));
                        hasPreFilled = true;
                    }
                });
                if (!hasPreFilled) {
                    $('#tender-cash').val(tenderBillTotal.toFixed(2));
                }
            @else
                // Default: Cash pre-filled with total
                $('#tender-cash').val(tenderBillTotal.toFixed(2));
                $('#tender-credit').val('');
                $('#tender-card').val('0.00');
                $('#tender-wallet').val('');
                $('#tender-wallet-side').val('');
                $('#tender-rrn').val('');
                $('#tender-card-no').val('');
                $('#tender-wallet-refno').val('');
            @endif
            $('#tender-error').addClass('d-none').text('');

            recalcTender();

            $('#sb-tender-modal').modal('show');
            setTimeout(function () {
                $('#tender-cash').focus().select();
            }, 200);
        });

        // Tender Modal Hotkeys: (A) Cash, (B) Credit, (C) Card, (W) Wallet, (N) RRN, Enter = Ok, Esc = Cancel
        $(document).on('keydown', function (e) {
            if (!$('#sb-tender-modal').is(':visible')) return;

            let key = e.key.toUpperCase();
            let target = e.target;
            let isInput = $(target).is('input, select, textarea');

            if (e.altKey || !isInput || target.id === 'tender-ok-btn') {
                if (key === 'A') { e.preventDefault(); $('#tender-cash').focus().select(); }
                else if (key === 'B') { e.preventDefault(); $('#tender-credit').focus().select(); }
                else if (key === 'C') { e.preventDefault(); $('#tender-card').focus().select(); }
                else if (key === 'W') { e.preventDefault(); $('#tender-wallet').focus().select(); }
                else if (key === 'N') { e.preventDefault(); $('#tender-rrn').focus().select(); }
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                $('#tender-save-btn').trigger('click');
            }
        });

        // Save / Tender Buttons Handler (Task 5)
        $(document).on('click', '#tender-save-btn, #tender-whatsapp-btn, #tender-print-btn, #tender-ok-btn', function () {
            let actionType = $(this).data('action') || 'save';
            $('#tender-error').addClass('d-none');

            let cash = parseFloat($('#tender-cash').val()) || 0;
            let credit = parseFloat($('#tender-credit').val()) || 0;
            let card = parseFloat($('#tender-card').val()) || 0;
            let wallet = parseFloat($('#tender-wallet').val()) || 0;
            let rrn = parseFloat($('#tender-rrn').val()) || 0;

            let tendered = Math.round((cash + credit + card + wallet + rrn) * 100) / 100;
            if (tendered <= 0 && tenderBillTotal > 0) {
                $('#tender-error').removeClass('d-none').text('Please enter payment amount.');
                return;
            }

            // Match payment modes to TenderType
            let cashType = TENDER_TYPES.find(t => t.type === 'Cash' || t.name.toLowerCase() === 'cash') || TENDER_TYPES[0];
            let creditType = TENDER_TYPES.find(t => t.type === 'Credit' || t.name.toLowerCase() === 'credit') || cashType;
            let cardType = TENDER_TYPES.find(t => t.type === 'Card' || t.name.toLowerCase() === 'card') || cashType;
            let walletType = TENDER_TYPES.find(t => t.type === 'Wallet' || t.name.toLowerCase() === 'wallet') || cashType;
            let rrnType = TENDER_TYPES.find(t => t.name.toLowerCase() === 'rrn' || t.type === 'Finance') || walletType;

            let selectedWalletTypeName = $('#tender-wallet-type').val();
            let walletValueId = null;
            if (walletType && walletType.values && walletType.values.length) {
                let matchedVal = walletType.values.find(v => v.name.toUpperCase() === selectedWalletTypeName.toUpperCase());
                if (matchedVal) walletValueId = matchedVal.id;
            }

            let otherPayments = Math.round((credit + card + wallet + rrn) * 100) / 100;
            if (otherPayments > tenderBillTotal) {
                $('#tender-error').removeClass('d-none').text('Payment amount (₹' + otherPayments.toFixed(2) + ') cannot exceed bill total (₹' + tenderBillTotal.toFixed(2) + ').');
                return;
            }

            let payments = [];

            // If tendered < total (unpaid balance), assign remaining to credit
            let outstanding = Math.max(0, Math.round((tenderBillTotal - tendered) * 100) / 100);
            if (outstanding > 0) {
                credit += outstanding;
            }

            // If cash tendered > bill total, cap cash payment amount at bill total (minus other modes)
            let effectiveCash = cash;
            if (cash + otherPayments > tenderBillTotal) {
                effectiveCash = Math.max(0, Math.round((tenderBillTotal - otherPayments) * 100) / 100);
            }

            if (effectiveCash > 0 && cashType) {
                payments.push({ tender_type_id: cashType.id, amount: effectiveCash });
            }
            if (credit > 0 && creditType) {
                payments.push({ tender_type_id: creditType.id, amount: credit });
            }
            if (card > 0 && cardType) {
                payments.push({ tender_type_id: cardType.id, amount: card });
            }
            if (wallet > 0 && walletType) {
                payments.push({
                    tender_type_id: walletType.id,
                    tender_type_value_id: walletValueId,
                    amount: wallet
                });
            }
            if (rrn > 0 && rrnType) {
                payments.push({ tender_type_id: rrnType.id, amount: rrn });
            }

            // Fallback if none entered
            if (payments.length === 0 && cashType) {
                payments.push({ tender_type_id: cashType.id, amount: tenderBillTotal });
            }

            // Balance payments sum to exactly match tenderBillTotal
            let paySum = payments.reduce((acc, p) => acc + p.amount, 0);
            paySum = Math.round(paySum * 100) / 100;
            let diff = Math.round((tenderBillTotal - paySum) * 100) / 100;
            if (Math.abs(diff) <= 0.05 && diff !== 0 && payments.length > 0) {
                payments[0].amount = Math.round((payments[0].amount + diff) * 100) / 100;
            }

            // Prune empty item rows from DOM before form submit
            $('#sb-items-body tr').each(function () {
                let $r = $(this);
                let itemId = $r.find('.sb-item-select').val();
                if (!itemId) {
                    $r.remove();
                }
            });

            // Re-index remaining rows so indices are contiguous
            $('#sb-items-body tr').each(function (idx) {
                let $r = $(this);
                $r.find('input[name], select[name]').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.startsWith('items[')) {
                        $(this).attr('name', name.replace(/items\[\d+\]/, `items[${idx}]`));
                    }
                });
            });

            // Inject hidden payment and save_action inputs into form
            let $form = $('#sales-bill-form').length ? $('#sales-bill-form') : $('form[action*="sales-bills"]').first();
            $form.find('input[name^="payments"]').remove();
            $form.find('input[name="save_action"]').remove();

            payments.forEach(function (p, i) {
                $form.append(`<input type="hidden" name="payments[${i}][tender_type_id]" value="${p.tender_type_id}">`);
                if (p.tender_type_value_id) {
                    $form.append(`<input type="hidden" name="payments[${i}][tender_type_value_id]" value="${p.tender_type_value_id}">`);
                }
                $form.append(`<input type="hidden" name="payments[${i}][amount]" value="${p.amount}">`);
            });

            $form.append(`<input type="hidden" name="save_action" value="${actionType}">`);

            // Use native form submit with double-submission lock
            if (window.PosScanGuard) {
                window.PosScanGuard.lockSubmission(this);
            }

            let submitted = false;
            function doSubmit() {
                if (!submitted) {
                    submitted = true;
                    let formEl = $form && $form.length ? $form[0] : document.getElementById('sales-bill-form');
                    if (formEl) {
                        if (typeof HTMLFormElement.prototype.submit === 'function') {
                            HTMLFormElement.prototype.submit.call(formEl);
                        } else {
                            formEl.submit();
                        }
                    }
                }
            }

            $('#sb-tender-modal').one('hidden.bs.modal', function () {
                doSubmit();
            });
            $('#sb-tender-modal').modal('hide');
            setTimeout(doSubmit, 350);
        });

        // Form Reset Button Handler
        $(document).on('click', '.btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset this form? All unsaved inputs will be lost.')) {
                window.location.reload();
            }
        });

        // Prevent future dates on bill_date
        $('#bill_date').on('change', function () {
            const now = new Date();
            const localIso = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            if (this.value && this.value > localIso) {
                alert('Future date & time is not allowed for Bill Date!');
                this.value = localIso;
            }
        });

        function fetchCustomerLoyalty(customerId) {
            if (!customerId) {
                $('#sb-customer-loyalty-badge').addClass('d-none').removeClass('d-flex');
                return;
            }
            $.ajax({
                url: "{{ url('sales/sales-bills/customer-loyalty') }}/" + customerId,
                type: 'GET',
                dataType: 'json',
                success: function (res) {
                    if (res && res.enable_loyalty) {
                        $('#sb-loyalty-pts').text(Number(res.balance_points).toFixed(2));
                        $('#sb-loyalty-val').text(Number(res.rupee_value).toFixed(2));
                        $('#sb-loyalty-notice').text(res.can_redeem ? 'Eligible to Redeem' : 'Min ' + res.min_points_redeem + ' pts required');
                        $('#sb-customer-loyalty-badge').removeClass('d-none').addClass('d-flex');
                    } else {
                        $('#sb-customer-loyalty-badge').addClass('d-none').removeClass('d-flex');
                    }
                }
            });
        }

        $('select[name="customer_id"]').on('change', function () {
            fetchCustomerLoyalty($(this).val());
        });

        if ($('select[name="customer_id"]').val()) {
            fetchCustomerLoyalty($('select[name="customer_id"]').val());
        }

        /* ================================================================
           AUTO-DRAFT & INSTANT BILL RECOVERY (Zero Data Loss Architecture)
           ================================================================ */
        const DRAFT_KEY = 'urbanpos_sales_bill_draft_v1';
        let draftDebounceTimer = null;

        function saveBillDraft() {
            @if(!empty($bill?->id))
                return; // Only active for new bills (not historical edits)
            @endif

            clearTimeout(draftDebounceTimer);
            draftDebounceTimer = setTimeout(function () {
                try {
                    let items = [];
                    $('#sb-items-body tr').each(function () {
                        let $r = $(this);
                        let itemId = $r.find('.sb-item-select').val();
                        let itemCode = $r.find('.sb-item-code').val();
                        let itemDesc = $r.find('.sb-item-desc').val();
                        let qty = $r.find('.sb-qty').val();
                        let sell = $r.find('.sb-sell-price').val();
                        let mrp = $r.find('.sb-mrp').val();
                        let exp = $r.find('.sb-exp-date').val();
                        let gst = $r.find('.sb-gst-percent').val();
                        let discPerc = $r.find('.sb-disc-percent').val();
                        let discAmt = $r.find('.sb-disc-amount').val();

                        if (itemId || itemCode || (parseFloat(qty) > 0)) {
                            let stockVal = $r.find('.sb-item-stock-val').val();
                            let parsedQty = parseFloat(qty) || 1;
                            if (parsedQty <= 0) parsedQty = 1; // Never save negative or zero qty in draft
                            items.push({
                                item_id: itemId,
                                item_code: itemCode,
                                item_desc: itemDesc,
                                qty: parsedQty,
                                sell_price: sell,
                                mrp: mrp,
                                exp_date: exp,
                                gst_percent: gst,
                                disc_percent: discPerc,
                                disc_amount: discAmt,
                                stock_val: stockVal || '' // Save stock so validation works on restore
                            });
                        }
                    });

                    if (items.length > 0) {
                        let draft = {
                            saved_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                            timestamp: Date.now(),
                            customer_id: $custSelect.val(),
                            customer_text: $custSelect.find('option:selected').text(),
                            branch_id: $('[name="branch_id"]').val(),
                            invoice_type: $('select[name="invoice_type"]').val(),
                            delivery_type: $('select[name="delivery_type"]').val(),
                            sales_type: $('select[name="sales_type"]').val(),
                            items: items
                        };
                        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
                    }
                } catch (e) {}
            }, 400);
        }

        // On load: check if an uncommitted draft exists (slight delay to ensure DOM is ready)
        @if(empty($bill?->id))
        setTimeout(function() {
            try {
                let rawDraft = localStorage.getItem(DRAFT_KEY);
                if (rawDraft) {
                    let draft = JSON.parse(rawDraft);
                    if (draft && draft.items && draft.items.length > 0) {
                        $('#sb-draft-saved-time').text(draft.saved_at || 'earlier today');
                        $('#sb-draft-item-count').text(draft.items.length);
                        $('#sb-draft-recovery-alert').removeClass('d-none').addClass('d-flex');
                    }
                }
            } catch (e) {
                console.warn('[UrbanPOS Draft] Error reading draft:', e);
            }
        }, 300);

        // Restore draft button
        $('#btn-restore-bill-draft').on('click', function () {
            try {
                let rawDraft = localStorage.getItem(DRAFT_KEY);
                if (!rawDraft) return;
                let draft = JSON.parse(rawDraft);
                if (!draft || !draft.items || !draft.items.length) return;

                if (draft.customer_id) {
                    if (!$custSelect.find(`option[value="${draft.customer_id}"]`).length) {
                        let opt = new Option(draft.customer_text || 'Customer', draft.customer_id, true, true);
                        $custSelect.append(opt);
                    }
                    $custSelect.val(draft.customer_id).trigger('change');
                }
                if (draft.branch_id) $('select[name="branch_id"]').val(draft.branch_id).trigger('change');
                if (draft.invoice_type) $('select[name="invoice_type"]').val(draft.invoice_type);
                if (draft.delivery_type) $('select[name="delivery_type"]').val(draft.delivery_type);
                if (draft.sales_type) $('select[name="sales_type"]').val(draft.sales_type);

                let $tbody = $('#sb-items-body');
                $tbody.empty();

                draft.items.forEach(function (it, idx) {
                    let html = $('#sb-row-template').html().replaceAll('__INDEX__', idx);
                    let $newRow = $(html);

                    // Restore item fields
                    $newRow.find('.sb-item-select').val(it.item_id);
                    $newRow.find('.sb-item-code').val(it.item_id || it.item_code);
                    $newRow.find('.sb-item-desc').val(it.item_desc);

                    // Ensure qty is always a positive number (never negative)
                    let restoredQty = parseFloat(it.qty) || 1;
                    if (restoredQty <= 0) restoredQty = 1;
                    $newRow.find('.sb-qty').val(restoredQty);

                    $newRow.find('.sb-sell-price').val(it.sell_price);
                    $newRow.find('.sb-mrp').val(it.mrp);
                    $newRow.find('.sb-exp-date').val(it.exp_date);
                    $newRow.find('.sb-gst-percent').val(it.gst_percent);
                    $newRow.find('.sb-disc-percent').val(it.disc_percent);
                    $newRow.find('.sb-disc-amount').val(it.disc_amount);

                    // Restore stock_val so that stock validation doesn't flag as over-stock
                    if (it.stock_val !== undefined && it.stock_val !== '') {
                        $newRow.find('.sb-item-stock-val').val(it.stock_val);
                        $newRow.attr('data-stock', it.stock_val);
                    } else {
                        // If no saved stock, set a large safe value to avoid false invalid state
                        $newRow.find('.sb-item-stock-val').val('9999');
                        $newRow.attr('data-stock', '9999');
                    }

                    $tbody.append($newRow);
                    calculateRow($newRow, 'base');
                });

                rowIndex = draft.items.length;
                updateRowNumbers();
                calculateTotals();

                // Focus first qty field for cashier convenience
                setTimeout(function() {
                    $('#sb-items-body tr:first .sb-qty').focus().select();
                }, 200);

                $('#sb-draft-recovery-alert').addClass('d-none').removeClass('d-flex');

                // Trigger background stock refresh for restored items
                setTimeout(function() {
                    $('#sb-items-body tr').each(function() {
                        let $r = $(this);
                        let itemId = $r.find('.sb-item-select').val();
                        if (itemId && !$r.find('.sb-item-stock-val').val()) {
                            // Only refresh if stock wasn't in draft
                            processItemLookup(null, $r, itemId);
                        }
                    });
                }, 500);
            } catch (err) {
                alert('Could not restore draft: ' + err);
            }
        });

        // Discard draft button
        $('#btn-discard-bill-draft').on('click', function () {
            if (confirm('Discard this saved bill draft?')) {
                localStorage.removeItem(DRAFT_KEY);
                $('#sb-draft-recovery-alert').addClass('d-none').removeClass('d-flex');
            }
        });
        @endif

        // Trigger draft saving on any input changes (qty, discount, price, customer, etc.)
        $(document).on('input change', '#sb-items-body input, #sb-items-body select, select[name="customer_id"], select[name="branch_id"]', function () {
            saveBillDraft();
        });

        // Clear draft upon successful submit or manual reset
        $(document).on('submit', '#sales-bill-form, form[action*="sales-bills"]', function () {
            localStorage.removeItem(DRAFT_KEY);
        });
        $(document).on('click', '.btn-reset-form', function () {
            localStorage.removeItem(DRAFT_KEY);
        });

    });
</script>
@endpush
