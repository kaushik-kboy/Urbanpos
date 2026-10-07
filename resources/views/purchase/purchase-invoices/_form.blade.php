@push('css')
    <link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
@endpush

@php
    $inv = $purchaseInvoice ?? null;
    $sourceRn = $sourceReceiptNote ?? null;
    $sourcePo = $sourceOrder ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($inv?->items ?? ($convertedItems ?? collect()));

    $selectedSupplier = old('supplier_id', $inv->supplier_id ?? ($sourceRn->supplier_id ?? ($sourcePo->supplier_id ?? '')));
    $selectedBranch = old('branch_id', $inv->branch_id ?? ($sourceRn->branch_id ?? ($sourcePo->branch_id ?? (session('active_branch_id') ?: (auth()->user()?->branch_id ?: ($branches->keys()->first() ?: (\App\Models\Branch::value('id') ?? 1)))))));
    $selectedPo = old('purchase_order_id', $inv->purchase_order_id ?? ($sourceRn->purchase_order_id ?? ($sourcePo->id ?? '')));
    $grnNumberVal = old('grn_number', $inv->grn_number ?? ($sourceRn->receipt_number ?? ($nextGrnNumber ?? '')));
    $grnDateVal = old('grn_date', optional($inv->grn_date ?? ($sourceRn->receipt_date ?? now()))->format('Y-m-d'));
    $rnIdVal = old('purchase_receipt_note_id', $inv->purchase_receipt_note_id ?? ($sourceRn->id ?? ''));
@endphp

<style>
    /* Remove spinners / up-down stepper buttons from all number inputs */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }
    input[type=number] {
        -moz-appearance: textfield !important;
        appearance: textfield !important;
    }
</style>

@if ($sourceRn)
    <div class="alert alert-info py-2 mb-3">
        <i class="fas fa-receipt mr-1"></i> Converting from Receipt Note <strong>{{ $sourceRn->receipt_number }}</strong>.
        Physical stock was already received on {{ optional($sourceRn->receipt_date)->format('d M Y') }}; saving this invoice books financial liabilities and updates item prices without duplicating inventory.
    </div>
@elseif ($sourcePo)
    <div class="alert alert-info py-2 mb-3">
        <i class="fas fa-file-invoice mr-1"></i> Converting directly from Purchase Order <strong>{{ $sourcePo->po_number }}</strong>.
        Items, quantities, and costs have been loaded automatically. Stock will be added to inventory upon saving this invoice.
    </div>
@endif

<input type="hidden" name="purchase_receipt_note_id" value="{{ $rnIdVal }}">
<input type="hidden" name="branch_id" id="branch_id" value="{{ $selectedBranch }}">

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0 text-muted font-weight-bold text-uppercase small"><i class="fas fa-file-invoice text-primary mr-1"></i> Invoice Details</h5>
    <x-form-layout-customizer
        form-key="purchase_invoices.header"
        container-id="pinv-header-fields-grid"
        title="Customize Purchase Invoice Header"
    />
</div>

<style>
    #pinv-header-fields-grid .btn-open-datepicker,
    #pinv-header-fields-grid .btn-date-settings-modal,
    #pinv-items-table .btn-open-datepicker,
    #pinv-items-table .btn-date-settings-modal {
        display: none !important;
    }
    #pinv-header-fields-grid .input-group-append:empty,
    #pinv-header-fields-grid .urbanpos-date-group .input-group-append,
    #pinv-items-table .urbanpos-date-group .input-group-append {
        display: none !important;
    }
    #pinv-header-fields-grid .urbanpos-date-group input,
    #pinv-items-table .urbanpos-date-group input {
        border-top-right-radius: 0.25rem !important;
        border-bottom-right-radius: 0.25rem !important;
    }
</style>

<div class="row g-2 form-fields-grid tx-header-fields-grid" id="pinv-header-fields-grid">
    <div class="field-wrapper col-md-2" data-field="invoice_date" data-label="Invoice Date" data-default-order="1" data-core="1">
        <x-field name="invoice_date" label="Invoice Date" type="date" :value="optional($inv->invoice_date ?? now())->format('Y-m-d')" max="{{ date('Y-m-d') }}" required />
    </div>

    <div class="field-wrapper col-md-2" data-field="supplier_id" data-label="Supplier" data-default-order="2" data-core="1">
        <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$selectedSupplier" placeholder="Select a Supplier" required />
        <div class="form-group row mt-n2 mb-2" id="supplier-prev-inv-wrapper">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <a href="javascript:void(0);" id="btn-supplier-prev-invoices" class="small font-weight-bold text-muted disabled-link" style="pointer-events: none; opacity: 0.5; text-decoration: none;" title="Select a supplier to view previous invoices">
                    <i class="fas fa-history mr-1 text-info"></i> <span id="supplier-prev-inv-text">View Previous Invoices</span>
                    <span id="supplier-prev-inv-badge" class="badge badge-info ml-1 d-none">0</span>
                </a>
            </div>
        </div>
    </div>

    <div class="field-wrapper col-md-2" data-field="purchase_order_id" data-label="Purchase Order" data-default-order="4">
        <x-select name="purchase_order_id" label="Purchase Order" :options="$purchaseOrders" :selected="$selectedPo" placeholder="Select PO" />
    </div>

    <div class="field-wrapper col-md-2" data-field="purchase_type" data-label="Purchase Type" data-default-order="5" data-core="1">
        <x-select name="purchase_type" id="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$inv->purchase_type ?? 'Local'" required />
    </div>

    <div class="field-wrapper col-md-2" data-field="c_form" data-label="C-Form" data-default-order="6">
        <x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$inv->c_form ?? 'No Forms'" required />
    </div>

    <div class="field-wrapper col-md-2" data-field="grn_number" data-label="GRN Number" data-default-order="7">
        <x-field name="grn_number" label="GRN Number" :value="$grnNumberVal" readonly />
    </div>

    <div class="field-wrapper col-md-2" data-field="grn_date" data-label="GRN Date" data-default-order="8">
        <x-field name="grn_date" label="GRN Date" type="date" :value="$grnDateVal" max="{{ date('Y-m-d') }}" />
    </div>

    <div class="field-wrapper col-md-2" data-field="supplier_inv_no" data-label="Inv No (Supplier)" data-default-order="9">
        <x-field name="supplier_inv_no" label="Inv No (Supplier)" :value="$inv->supplier_inv_no ?? ''" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();" placeholder="e.g. INV-2026-001" required data-check-url="{{ route('purchase.purchase-invoices.check-supplier-inv') }}" data-invoice-id="{{ $inv?->id ?? '' }}" />
        <div class="form-group row mt-n2 mb-2" id="supplier-inv-feedback-container" style="display: none;">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <div id="supplier-inv-feedback" class="small font-weight-bold text-danger"></div>
            </div>
        </div>
    </div>

    <div class="field-wrapper col-md-2" data-field="supplier_inv_date" data-label="Inv Date (Supplier)" data-default-order="10">
        <x-field name="supplier_inv_date" label="Inv Date (Supplier)" type="date" :value="optional($inv->supplier_inv_date ?? now())->format('Y-m-d')" max="{{ date('Y-m-d') }}" />
    </div>

    <div class="field-wrapper col-md-2" data-field="supplier_inv_amount" data-label="Inv Amount (Supplier)" data-default-order="11" data-core="1">
        <x-field name="supplier_inv_amount" label="Inv Amount (Supplier)" type="number" step="0.01" :value="isset($inv->supplier_inv_amount) && $inv->supplier_inv_amount != 0 ? $inv->supplier_inv_amount : ''" required />
        <div class="form-group row mt-n2 mb-2" id="supplier-inv-amount-match-container">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <div id="supplier-inv-amount-match-status" class="small font-weight-bold"></div>
            </div>
        </div>
    </div>
</div>

<hr>
@php
    $pinvItemColumns = [
        'seq'          => ['label' => '#', 'default' => true],
        'code'         => ['label' => 'Code / Barcode', 'default' => true],
        'item'         => ['label' => 'Description', 'default' => true],
        'expiry'       => ['label' => 'Exp Date', 'default' => true],
        'qty'          => ['label' => 'Qty', 'default' => true],
        'free'         => ['label' => 'Free', 'default' => true],
        'cost_price'   => ['label' => 'Cost Price', 'default' => true],
        'landing_cost' => ['label' => 'Landing Cost', 'default' => true],
        'sell_price'   => ['label' => 'Sell Price', 'default' => true],
        'mrp'          => ['label' => 'MRP', 'default' => true],
        'margin'       => ['label' => 'Margin %', 'default' => true],
        'profit'       => ['label' => 'Profit %', 'default' => true],
        'disc_percent' => ['label' => 'Disc %', 'default' => true],
        'disc_amt'     => ['label' => 'Disc Amt', 'default' => true],
        'gst_percent'  => ['label' => 'GST %', 'default' => true],
        'gst_amt'      => ['label' => 'GST Amt', 'default' => true],
        'net_amt'      => ['label' => 'Net Amt', 'default' => true],
        'actions'      => ['label' => 'Actions', 'default' => true],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Items</h5>
    <div class="d-flex align-items-center">
        <button type="button" id="pinv-btn-reset-table" class="btn btn-outline-danger btn-xs font-weight-bold mr-2 btn-reset-table" title="Clear all table items and reset to 1 empty row"><i class="fas fa-trash-alt mr-1"></i> Reset Table</button>
        <button type="button" id="pinv-add-row" class="btn btn-primary btn-xs font-weight-bold mr-2" title="Add a new row"><i class="fas fa-plus mr-1"></i> Add Row</button>
        <x-table-column-customizer
            table-key="purchase.purchase-invoices.items"
            table-id="pinv-items-table"
            :columns="$pinvItemColumns"
        />
    </div>
</div>

<style>
    .pinv-table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #ced4da;
        border-radius: 4px;
        margin-bottom: 0.5rem;
        background-color: #fff;
    }
    #pinv-items-table {
        min-width: 1220px;
        width: 100%;
        margin-bottom: 0;
    }
    #pinv-items-table th {
        vertical-align: middle;
        text-align: center;
        background-color: #f4f6f9;
        font-weight: 600;
        font-size: 0.77rem;
        padding: 4px 2px !important;
        white-space: nowrap;
    }
    #pinv-items-table td {
        vertical-align: middle;
        padding: 1px 1px !important;
    }
    #pinv-items-table input.form-control-sm {
        font-size: 0.81rem;
        padding: 1px 3px !important;
        height: 27px !important;
        border-radius: 2px;
    }
    #pinv-items-table .btn-xs {
        padding: 1px 4px !important;
        font-size: 0.75rem;
        line-height: 1.2;
    }
    .pinv-table-wrapper::-webkit-scrollbar {
        height: 7px;
    }
    .pinv-table-wrapper::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    .pinv-table-wrapper::-webkit-scrollbar-thumb {
        background: #007bff;
        border-radius: 4px;
    }
    .pinv-table-wrapper::-webkit-scrollbar-thumb:hover {
        background: #0056b3;
    }
</style>

<div class="pinv-table-wrapper table-responsive tx-items-scroll-container">
    <table class="table table-sm table-bordered table-items-dense" id="pinv-items-table">
        <thead>
            <tr>
                <th style="width:28px; min-width:28px;" class="text-center px-0" data-col-key="seq">#</th>
                <th style="width:110px; min-width:100px;" class="px-1" data-col-key="code">Code / Barcode</th>
                <th style="min-width:180px; width:195px;" class="px-1" data-col-key="item">Description</th>
                <th style="width:80px; min-width:80px;" class="px-1" data-col-key="batch">Batch</th>
                <th style="width:115px; min-width:115px;" class="px-1" data-col-key="expiry">Exp Date</th>
                <th style="width:65px; min-width:65px;" class="px-1" data-col-key="qty">Qty</th>
                <th style="width:45px; min-width:45px;" class="px-0" data-col-key="free">Free</th>
                <th style="width:80px; min-width:80px;" class="px-1" data-col-key="cost_price">Cost Price</th>
                <th style="width:80px; min-width:80px;" class="px-1" data-col-key="landing_cost" title="Landing Cost Price (Effective unit cost after free qty & discount)">Landing Cost</th>
                <th style="width:80px; min-width:80px;" class="px-1" data-col-key="sell_price">Sell Price</th>
                <th style="width:75px; min-width:75px;" class="px-1" data-col-key="mrp">MRP</th>
                <th style="width:50px; min-width:50px;" class="px-0" title="Margin %" data-col-key="margin">Margin %</th>
                <th style="width:50px; min-width:50px;" class="px-0" title="Profit %" data-col-key="profit">Profit %</th>
                <th style="width:48px; min-width:48px;" class="px-0" data-col-key="disc_percent">Disc %</th>
                <th style="width:70px; min-width:70px;" class="px-1" data-col-key="disc_amt">Disc Amt</th>
                <th style="width:45px; min-width:45px;" class="px-0" data-col-key="gst_percent">GST %</th>
                <th style="width:75px; min-width:75px;" class="px-1" data-col-key="gst_amt">GST Amt</th>
                <th style="width:85px; min-width:85px;" class="text-right px-1" data-col-key="net_amt">Net Amt</th>
                <th style="width:32px; min-width:32px;" class="px-0" data-col-key="actions"></th>
            </tr>
        </thead>

        <tbody id="pinv-items-body">
            @forelse ($existingItems as $index => $line)
                @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td class="text-center align-middle" data-col-key="seq">&nbsp;</td>
                <td class="text-right align-middle" data-col-key="code" style="white-space:nowrap;">Totals:</td>
                <td class="align-middle" data-col-key="item"></td>
                <td class="align-middle" data-col-key="batch"></td>
                <td class="align-middle" data-col-key="expiry"></td>
                <td class="text-right align-middle text-primary font-weight-bold" id="footer-total-qty" data-col-key="qty"></td>
                <td class="align-middle" data-col-key="free"></td>
                <td class="text-right align-middle font-weight-bold" id="footer-total-cost" data-col-key="cost_price"></td>
                <td class="align-middle" data-col-key="landing_cost"></td>
                <td class="align-middle" data-col-key="sell_price"></td>
                <td class="align-middle" data-col-key="mrp"></td>
                <td class="align-middle" data-col-key="margin"></td>
                <td class="text-right align-middle small text-muted" data-col-key="profit" style="white-space:nowrap;">Total Disc:</td>
                <td class="align-middle" data-col-key="disc_percent"></td>
                <td class="text-right align-middle text-danger font-weight-bold" id="footer-total-disc" data-col-key="disc_amt"></td>
                <td class="align-middle" data-col-key="gst_percent"></td>
                <td class="text-right align-middle font-weight-bold text-dark" id="footer-total-gst" data-col-key="gst_amt"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="footer-grand-net" data-col-key="net_amt"></td>
                <td data-col-key="actions"></td>
            </tr>
        </tfoot>
    </table>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-calculator mr-1 text-primary"></i> Totals & Charges</h5>
    <x-form-layout-customizer
        form-key="purchase_invoices.totals"
        container-id="pinv-totals-fields-grid"
        title="Customize Purchase Invoice Totals Layout"
    />
</div>

<div class="row g-2 form-fields-grid" id="pinv-totals-fields-grid">
    <div class="field-wrapper col-md-6" data-field="freight" data-label="Freight" data-default-order="1">
        <x-field name="freight" label="Freight" type="number" step="0.01" :value="isset($inv->freight) && $inv->freight != 0 ? $inv->freight : ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="round_off" data-label="Round off Amount" data-default-order="2">
        <x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="isset($inv->round_off) && $inv->round_off != 0 ? $inv->round_off : ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="scheme_item_disc_amt" data-label="Scheme ItemDiscAmt" data-default-order="3">
        <x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="isset($inv->scheme_item_disc_amt) && $inv->scheme_item_disc_amt != 0 ? $inv->scheme_item_disc_amt : ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="other_disc_amt" data-label="OtherDiscAmt" data-default-order="4">
        <x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="isset($inv->other_disc_amt) && $inv->other_disc_amt != 0 ? $inv->other_disc_amt : ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="total_extra_cess" data-label="Total Extra Cess" data-default-order="5">
        <x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="isset($inv->total_extra_cess) && $inv->total_extra_cess != 0 ? $inv->total_extra_cess : ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="total_weight" data-label="Total Weight" data-default-order="6">
        <x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="isset($inv->total_weight) && $inv->total_weight != 0 ? $inv->total_weight : ''" />
    </div>
    <div class="field-wrapper col-md-6 col-sm-12" data-field="remarks" data-label="Remarks" data-default-order="7">
        <x-field name="remarks" label="Remarks" :value="$inv->remarks ?? ''" placeholder="Optional remarks..." />
    </div>
    <div class="field-wrapper col-md-6 col-sm-12" data-field="message" data-label="Message" data-default-order="8">
        <x-field name="message" label="Message" :value="$inv->message ?? ''" placeholder="Optional message..." />
    </div>
    <div class="field-wrapper col-md-6" data-field="tcs_amount" data-label="TCS Amt" data-default-order="9">
        <x-field name="tcs_amount" label="TCS Amt" type="number" step="0.01" :value="isset($inv->tcs_amount) && $inv->tcs_amount != 0 ? $inv->tcs_amount : ''" />
    </div>
    <div class="field-wrapper col-md-6" data-field="scheme_item_disc_percent" data-label="Scheme ItemDisc%" data-default-order="10">
        <x-field name="scheme_item_disc_percent" label="Scheme ItemDisc%" type="number" step="0.01" min="0" max="100" :value="isset($inv->scheme_item_disc_percent) && $inv->scheme_item_disc_percent != 0 ? $inv->scheme_item_disc_percent : ''" />
    </div>
</div>

<x-custom-fields-renderer :module="'PurchaseInvoice'" :model="$inv ?? null" :cardStyle="true" />

<template id="pinv-row-template">
    @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

<!-- ============================================================
     ITEM SEARCH MODAL — opens on Code/Barcode field focus
     ============================================================ -->
<div class="modal fade" id="pinv-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="pinvItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="pinvItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="pinv-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="pinv-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="pinv-isl-filter-expiry" class="form-control" placeholder="Filter expiry…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right d-flex justify-content-end align-items-center">
                        <x-table-column-customizer table-key="modal.purchase-invoices.item-search" table-id="pinv-isl-items-table" button-class="btn btn-sm btn-outline-secondary mr-2" button-text="Columns" title="Customize Columns & Order" />
                        <button type="button" id="pinv-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results / Hint states -->
                <div id="pinv-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="pinv-isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="pinv-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="pinv-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;" data-col-key="seq">#</th>
                                <th data-col-key="name">Product Name</th>
                                <th class="text-center" style="width: 120px;" data-col-key="code">Code</th>
                                <th class="text-right" style="width: 95px;" data-col-key="cost_price">Cost Price</th>
                                <th class="text-right" style="width: 95px;" data-col-key="sell_price">Sell Price</th>
                                <th class="text-right" style="width: 90px;" data-col-key="mrp">MRP</th>
                                <th class="text-right" style="width: 85px;" data-col-key="qty">Stock</th>
                                <th class="text-center" style="width: 120px;" data-col-key="expiry">Expiry / Batch</th>
                                <th class="text-center" style="width: 80px;" data-col-key="action">Select</th>
                            </tr>
                        </thead>
                        <tbody id="pinv-isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="pinv-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     SUPPLIER PREVIOUS INVOICES MODAL
     ============================================================ -->
<div class="modal fade" id="supplier-prev-invoices-modal" tabindex="-1" role="dialog" aria-labelledby="supplierPrevInvoicesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title font-weight-bold" id="supplierPrevInvoicesLabel">
                    <i class="fas fa-history text-info mr-2"></i>Previous Invoices &mdash; <span id="spi-modal-supplier-name" class="text-warning"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Filters -->
                <div class="card card-light card-outline mb-3 p-2 bg-light border">
                    <div class="row align-items-end g-2">
                        <div class="col-md-5 col-12 mb-1">
                            <label class="small font-weight-bold mb-1">Search by Invoice Number</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="spi-filter-search" class="form-control" placeholder="Inv No / Supplier Inv No..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-1">
                            <label class="small font-weight-bold mb-1">From Date</label>
                            <input type="date" id="spi-filter-from" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 col-6 mb-1">
                            <label class="small font-weight-bold mb-1">To Date</label>
                            <input type="date" id="spi-filter-to" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-1 col-6 mb-1">
                            <button type="button" id="spi-filter-reset" class="btn btn-outline-secondary btn-sm btn-block" title="Reset Filters">
                                <i class="fas fa-undo"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table: date, invoice no, amount, view -->
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped table-hover mb-0" id="spi-modal-table">
                        <thead class="bg-secondary text-white sticky-top">
                            <tr>
                                <th style="width: 130px;">Date</th>
                                <th>Invoice No</th>
                                <th class="text-right" style="width: 140px;">Amount</th>
                                <th class="text-center" style="width: 90px;">View</th>
                            </tr>
                        </thead>
                        <tbody id="spi-modal-tbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted" id="spi-modal-count-info"></small>
                    <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Clicking 'View' opens invoice in a new tab.</small>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
    $(document).ready(function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeSearchRow = null;   // which row triggered the item search modal
        let pendingFocusExpRow = null; // row to focus on Exp Date after modal hide
        let islDebounce = null;
        let islCache = {};
        let islLastKey = null;
        let islModalOpen = false;
        const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';

        const supplierPurchaseTypes = @json(\App\Models\Supplier::pluck('purchase_type', 'id'));

        // Auto-set purchase_type based on supplier's purchase_type (supplier master drives this)
        function applySupplierPurchaseType(sId) {
            if (sId && supplierPurchaseTypes[sId]) {
                let pType = String(supplierPurchaseTypes[sId]).trim();
                let normalized = pType.charAt(0).toUpperCase() + pType.slice(1).toLowerCase();
                if (normalized === 'Local' || normalized === 'Interstate') {
                    $('#purchase_type').val(normalized).trigger('change');
                    $('#purchase_type').prop('disabled', true).closest('.field-wrapper').find('.select2-selection').css({'pointer-events':'none','background':'#e9ecef','opacity':'0.85'});
                    if (!$('#purchase_type_locked_note').length) {
                        $('#purchase_type').closest('.field-wrapper').append('<small id="purchase_type_locked_note" class="text-muted"><i class="fas fa-lock mr-1"></i>Auto-set from Supplier Master</small>');
                    }
                }
            } else {
                $('#purchase_type').prop('disabled', false).closest('.field-wrapper').find('.select2-selection').css({'pointer-events':'','background':'','opacity':''});
                $('#purchase_type_locked_note').remove();
            }
        }

        $('#supplier_id').on('change', function () {
            let val = $(this).val();
            applySupplierPurchaseType(val);
            if ($.trim($('#supplier_inv_no').val())) {
                validateSupplierInvNo();
            } else {
                $('#supplier_inv_no').removeClass('is-invalid border-danger');
                $('#supplier-inv-feedback-container').hide();
            }
            updateSupplierPrevInvoicesLink(val);
            updateSupplierOpenPOs(val);
        });

        // Dynamic Open PO by Supplier & Auto-select + Auto-populate items
        let _loadingPoId = null;
        function loadPoItemsIntoPinv(poId, force = false) {
            if (!poId || _loadingPoId === poId) return;
            let isEdit = {{ isset($inv) && $inv->id ? 'true' : 'false' }};
            if (isEdit && !force) return;
            _loadingPoId = poId;

            let url = "{{ url('purchase/purchase-orders') }}/" + poId + "/items";
            $.getJSON(url, function (res) {
                _loadingPoId = null;
                if (!res || !res.items || !res.items.length) {
                    return;
                }

                let items = res.items;
                let $tbody = $('#pinv-items-body');

                // Clear existing table items to populate from selected PO
                $tbody.empty();
                rowIndex = 0;

                items.forEach(function (it) {
                    let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
                    let $row = $(html);
                    $tbody.append($row);
                    initPinvItemSelect2($row.find('.pinv-item-select'));
                    $row.find('input').attr('autocomplete', 'off');

                    $row.find('.pinv-item-select').val(it.item_id);
                    $row.find('.pinv-item-code').val(it.item_code || it.code || it.item_id);
                    $row.find('.pinv-item-desc').val(it.name);
                    $row.find('.pinv-qty').val(it.qty > 0 ? it.qty : '');
                    $row.find('.pinv-free-qty').val(it.free_qty > 0 ? it.free_qty : '');
                    $row.find('.pinv-cost').val(it.cost_price > 0 ? it.cost_price.toFixed(2) : '');
                    $row.find('.pinv-sell').val(it.sell_price > 0 ? it.sell_price.toFixed(2) : '');
                    $row.find('.pinv-mrp').val(it.mrp > 0 ? it.mrp.toFixed(2) : '');
                    $row.find('.pinv-disc-percent').val(it.disc_percent > 0 ? it.disc_percent.toFixed(2) : '');
                    $row.find('.pinv-disc-amount').val(it.disc_amount > 0 ? it.disc_amount.toFixed(2) : '');
                    $row.find('.pinv-gst').val(it.gst_percent >= 0 ? it.gst_percent.toFixed(2) : '');

                    updateExpiryRequirement($row, it.batch_expiry_details, it.shelf_life_days);
                    calculateRow($row, 'percent');
                    rowIndex++;
                });

                if (res.purchase_order) {
                    if (parseFloat(res.purchase_order.freight) > 0) {
                        $('#freight').val(parseFloat(res.purchase_order.freight).toFixed(2));
                    }
                    if (parseFloat(res.purchase_order.round_off) !== 0) {
                        $('#round_off').val(parseFloat(res.purchase_order.round_off).toFixed(2));
                    }
                }

                updateRowNumbers();
                calculateTotals();

                if (window.toastr) {
                    toastr.success('Loaded ' + items.length + ' item(s) from Purchase Order #' + res.purchase_order.po_number, 'PO Loaded');
                }
            }).fail(function () {
                _loadingPoId = null;
            });
        }

        $('#purchase_order_id').on('change', function () {
            let poId = $(this).val();
            if (!poId) return;
            let hasExistingItems = $('#pinv-items-body tr').find('.pinv-item-select').filter((_, el) => !!$(el).val()).length > 0;
            if (hasExistingItems) {
                if (confirm('Load items from this Purchase Order? Existing table items will be replaced.')) {
                    loadPoItemsIntoPinv(poId, true);
                }
            } else {
                loadPoItemsIntoPinv(poId, true);
            }
        });

        function updateSupplierOpenPOs(suppId, selectedPoId = null) {
            let $poSelect = $('#purchase_order_id');
            if (!$poSelect.length) return;

            if (!suppId) {
                $poSelect.html('<option value="">Select PO</option>').val('').trigger('change.select2');
                return;
            }

            $.getJSON("{{ route('purchase.purchase-orders.open-by-supplier') }}", { supplier_id: suppId }, function (res) {
                let optionsHtml = '<option value="">Select PO</option>';
                let poList = (res && res.purchase_orders) ? res.purchase_orders : [];
                let currentVal = (selectedPoId !== null && selectedPoId !== undefined && selectedPoId !== '') ? String(selectedPoId) : '';
                let foundMatch = false;

                poList.forEach(function (po) {
                    let isSel = (String(po.id) === currentVal);
                    if (isSel) foundMatch = true;
                    optionsHtml += `<option value="${po.id}" ${isSel ? 'selected' : ''}>${po.po_number}</option>`;
                });

                $poSelect.html(optionsHtml);
                if (foundMatch && currentVal) {
                    $poSelect.val(currentVal).trigger('change.select2');
                    // In edit mode or if invoice already has items, NEVER auto-overwrite table with PO items on load
                    let isEdit = {{ isset($inv) && $inv->id ? 'true' : 'false' }};
                    let hasExistingItems = $('#pinv-items-body tr').find('.pinv-item-select').filter((_, el) => !!$(el).val()).length > 0;
                    if (!isEdit && !hasExistingItems) {
                        loadPoItemsIntoPinv(currentVal);
                    }
                } else {
                    $poSelect.val('').trigger('change.select2');
                }
            }).fail(function () {
                $poSelect.html('<option value="">Select PO</option>').val('').trigger('change.select2');
            });
        }

        // Supplier Previous Invoices link & modal logic
        const supplierInvoicesRouteTemplate = "{{ route('purchase.purchase-invoices.supplier-invoices', ':id') }}";

        function updateSupplierPrevInvoicesLink(suppId) {
            let $link = $('#btn-supplier-prev-invoices');
            let $badge = $('#supplier-prev-inv-badge');
            if (suppId) {
                $link.removeClass('text-muted disabled-link')
                     .addClass('text-primary font-weight-bold')
                     .css({'pointer-events': 'auto', 'opacity': '1', 'cursor': 'pointer'})
                     .attr('title', 'Click to view previous invoices of this supplier');

                // Quick load count
                let url = supplierInvoicesRouteTemplate.replace(':id', suppId);
                $.getJSON(url, function (res) {
                    if (res && res.invoices) {
                        let count = res.invoices.length;
                        $badge.text(count + (count >= 100 ? '+' : '')).removeClass('d-none');
                    }
                }).fail(function () {
                    $badge.addClass('d-none');
                });
            } else {
                $link.addClass('text-muted disabled-link')
                     .removeClass('text-primary')
                     .css({'pointer-events': 'none', 'opacity': '0.5', 'cursor': 'not-allowed'})
                     .attr('title', 'Select a supplier to view previous invoices');
                $badge.addClass('d-none').text('0');
            }
        }

        // On page load: if supplier already selected (edit mode), lock purchase_type & enable previous invoices link
        (function () {
            let initialSuppId = $('#supplier_id').val();
            let initialPoId = '{{ $selectedPo ?? "" }}';
            if (initialSuppId) {
                applySupplierPurchaseType(initialSuppId);
                updateSupplierPrevInvoicesLink(initialSuppId);
                updateSupplierOpenPOs(initialSuppId, initialPoId);
            }
        })();

        // Click on Previous Invoices link opens modal
        $('#btn-supplier-prev-invoices').on('click', function (e) {
            e.preventDefault();
            let suppId = $('#supplier_id').val();
            if (!suppId) return;

            let suppText = $('#supplier_id option:selected').text();
            $('#spi-modal-supplier-name').text(suppText);
            $('#spi-filter-search').val('');
            $('#spi-filter-from').val('');
            $('#spi-filter-to').val('');
            $('#supplier-prev-invoices-modal').modal('show');
            loadSupplierPreviousInvoices(suppId);
        });

        let spiFilterDebounce = null;
        function loadSupplierPreviousInvoices(suppId) {
            if (!suppId) return;
            let $tbody = $('#spi-modal-tbody');
            let $countInfo = $('#spi-modal-count-info');

            $tbody.html('<tr><td colspan="4" class="text-center text-primary py-4"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i>Loading previous invoices...</td></tr>');
            $countInfo.text('Loading...');

            let url = supplierInvoicesRouteTemplate.replace(':id', suppId);
            let params = {
                search: $('#spi-filter-search').val(),
                date_from: $('#spi-filter-from').val(),
                date_to: $('#spi-filter-to').val()
            };

            $.getJSON(url, params, function (res) {
                $tbody.empty();
                if (!res || !res.invoices || res.invoices.length === 0) {
                    $tbody.html('<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-folder-open fa-2x mb-2 d-block text-secondary"></i>No previous invoices found for this supplier matching filter.</td></tr>');
                    $countInfo.text('Showing 0 invoices');
                    return;
                }

                $countInfo.text('Showing ' + res.invoices.length + ' invoice(s)');

                res.invoices.forEach(function (inv) {
                    let supplierInvNoBadge = inv.supplier_inv_no && inv.supplier_inv_no !== '-'
                        ? '<span class="badge badge-light border text-dark font-weight-bold">' + $('<div>').text(inv.supplier_inv_no).html() + '</span>'
                        : '<span class="text-muted">-</span>';

                    let sysInv = inv.invoice_number ? '<br><small class="text-muted">Sys: ' + $('<div>').text(inv.invoice_number).html() + '</small>' : '';

                    let row = '<tr>' +
                        '<td class="align-middle text-nowrap"><i class="far fa-calendar-alt text-muted mr-1"></i> ' + (inv.date || '-') + '</td>' +
                        '<td class="align-middle">' + supplierInvNoBadge + sysInv + '</td>' +
                        '<td class="align-middle text-right font-weight-bold text-nowrap">₹ ' + inv.amount + '</td>' +
                        '<td class="align-middle text-center">' +
                            '<a href="' + inv.view_url + '" target="_blank" class="btn btn-xs btn-outline-info" title="View Invoice in New Tab">' +
                                '<i class="fas fa-eye mr-1"></i> View' +
                            '</a>' +
                        '</td>' +
                    '</tr>';
                    $tbody.append(row);
                });
            }).fail(function () {
                $tbody.html('<tr><td colspan="4" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>Failed to load previous invoices. Please try again.</td></tr>');
                $countInfo.text('');
            });
        }

        $('#spi-filter-search').on('input', function () {
            clearTimeout(spiFilterDebounce);
            spiFilterDebounce = setTimeout(function () {
                let suppId = $('#supplier_id').val();
                loadSupplierPreviousInvoices(suppId);
            }, 300);
        });

        $('#spi-filter-from, #spi-filter-to').on('change', function () {
            let suppId = $('#supplier_id').val();
            loadSupplierPreviousInvoices(suppId);
        });

        $('#spi-filter-reset').on('click', function () {
            $('#spi-filter-search').val('');
            $('#spi-filter-from').val('');
            $('#spi-filter-to').val('');
            let suppId = $('#supplier_id').val();
            loadSupplierPreviousInvoices(suppId);
        });

        // Real-time Supplier Invoice Number validation & duplication check
        let suppInvDebounce = null;
        function validateSupplierInvNo(callback) {
            let $input = $('#supplier_inv_no');
            let $feedbackContainer = $('#supplier-inv-feedback-container');
            let $feedback = $('#supplier-inv-feedback');
            let invNo = $.trim($input.val()).toUpperCase();
            let suppId = $('#supplier_id').val();
            let ignoreId = $input.data('invoice-id') || '';
            let checkUrl = $input.data('check-url');

            if (!invNo) {
                $input.addClass('is-invalid border-danger').removeClass('is-valid');
                $input.data('is-duplicate', false);
                $input.removeData('verified-key');
                $feedback.text('Supplier Invoice Number is required before moving forward.').show();
                $feedbackContainer.show();
                if (callback) callback(false);
                return false;
            }

            if (!suppId) {
                $input.removeClass('is-invalid is-valid border-danger');
                $input.data('is-duplicate', false);
                $input.removeData('verified-key');
                $feedbackContainer.hide();
                if (callback) callback(true);
                return true;
            }

            let checkKey = suppId + ':' + invNo;
            $.getJSON(checkUrl, { supplier_id: suppId, supplier_inv_no: invNo, ignore_id: ignoreId }, function (res) {
                if (res && res.is_duplicate) {
                    $input.addClass('is-invalid border-danger').removeClass('is-valid');
                    $input.data('is-duplicate', true);
                    $input.data('has-duplicate-error', true);
                    $input.data('duplicate-error-message', res.message);
                    $input.data('verified-key', checkKey);
                    let msg = res.message || `Supplier Invoice Number '${invNo}' is already recorded for this supplier.`;
                    if (res.edit_url) {
                        $feedback.html(`${msg} <a href="${res.edit_url}" target="_blank" class="ml-1 text-primary font-weight-bold" style="text-decoration: underline;"><i class="fas fa-external-link-alt"></i> View ${res.existing_invoice_number || 'Invoice'}</a>`).show();
                    } else {
                        $feedback.text(msg).show();
                    }
                    $feedbackContainer.show();
                    if (callback) callback(false);
                } else {
                    $input.removeClass('is-invalid border-danger').addClass('is-valid');
                    $input.data('is-duplicate', false);
                    $input.data('has-duplicate-error', false);
                    $input.removeData('duplicate-error-message');
                    $input.data('verified-key', checkKey);
                    $feedback.text('').hide();
                    $feedbackContainer.hide();
                    if (callback) callback(true);
                }
            });
            return true;
        }

        function triggerFieldShake($el) {
            if (!$el || !$el.length) return;
            $el.removeClass('up-field-shake');
            void $el[0].offsetWidth;
            $el.addClass('up-field-shake');
            setTimeout(function () {
                $el.removeClass('up-field-shake');
            }, 400);
        }

        // Strict Gatekeeper: No user can proceed to items until Supplier & Inv No (Supplier) are valid & verified unique!
        function canProceedToItems(silent) {
            let $supp = $('#supplier_id');
            let suppId = $supp.val();
            let $suppInv = $('#supplier_inv_no');
            let invNo = $.trim($suppInv.val()).toUpperCase();
            let $feedbackContainer = $('#supplier-inv-feedback-container');
            let $feedback = $('#supplier-inv-feedback');

            // 1. Supplier must be selected
            if (!suppId) {
                if (!silent) {
                    $supp.next('.select2-container').find('.select2-selection').addClass('is-invalid border-danger');
                    triggerFieldShake($supp.next('.select2-container').find('.select2-selection'));
                    if (window.toastr) {
                        window.toastr.warning('Please select a Supplier first before proceeding to items.', 'Supplier Required');
                    }
                    $supp.select2('open');
                }
                return false;
            }

            // 2. Inv No (Supplier) must be filled
            if (!invNo) {
                $suppInv.addClass('is-invalid border-danger').removeClass('is-valid');
                $feedback.text('Supplier Invoice Number is required before moving forward.').show();
                $feedbackContainer.show();
                triggerFieldShake($suppInv);
                if (!silent) {
                    if (window.toastr) {
                        window.toastr.warning('Supplier Invoice Number is required before entering items.', 'Invoice Number Required');
                    }
                    $suppInv.focus();
                }
                return false;
            }

            // 3. Duplicate check - if already marked as duplicate
            if ($suppInv.data('is-duplicate') === true || $suppInv.data('has-duplicate-error') === true) {
                let msg = $feedback.text() || `Supplier Invoice Number '${invNo}' is already recorded for this supplier.`;
                triggerFieldShake($suppInv);
                if (!silent) {
                    if (window.toastr) {
                        window.toastr.error(msg, 'Duplicate Invoice Number');
                    }
                    $suppInv.focus();
                }
                return false;
            }

            // 4. If current supplier:invNo pair has not been verified yet, trigger async verification
            let currentCheckKey = suppId + ':' + invNo;
            if ($suppInv.data('verified-key') !== currentCheckKey) {
                validateSupplierInvNo();
            }

            // 5. Inv Amount (Supplier) must be filled and > 0
            let $invAmt = $('input[name="supplier_inv_amount"]');
            let invAmtVal = parseFloat($.trim($invAmt.val()));
            if (!$invAmt.val() || isNaN(invAmtVal) || invAmtVal <= 0) {
                $invAmt.addClass('is-invalid border-danger').removeClass('is-valid');
                triggerFieldShake($invAmt);
                if (!silent) {
                    if (window.toastr) {
                        window.toastr.warning('Inv Amount (Supplier) is required before entering items.', 'Invoice Amount Required');
                    }
                    $invAmt.focus();
                }
                return false;
            }

            return true;
        }


        $('#supplier_inv_no').on('blur change', function () {
            validateSupplierInvNo();
        });

        $('#supplier_inv_no').on('input', function () {
            $(this).removeData('verified-key');
            clearTimeout(suppInvDebounce);
            suppInvDebounce = setTimeout(validateSupplierInvNo, 400);
        });

        $('#supplier_inv_no').on('keydown', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                if (!canProceedToItems()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }
        });


        /* ================================================================
           ITEM SEARCH MODAL — open on Code/Barcode focus
           ================================================================ */

        // Debounced filter inputs — 400ms to avoid firing on every keystroke
        $('#pinv-isl-filter-name, #pinv-isl-filter-code, #pinv-isl-filter-expiry').on('input', function () {
            clearTimeout(islDebounce);
            islDebounce = setTimeout(fetchItemList, 400);
        });

        $('#pinv-isl-btn-clear').on('click', function () {
            $('#pinv-isl-filter-name, #pinv-isl-filter-code, #pinv-isl-filter-expiry').val('');
            fetchItemList();
        });

        function showHintState(msg) {
            $('#pinv-isl-loading').addClass('d-none');
            $('#pinv-isl-table-wrap').addClass('d-none');
            $('#pinv-isl-items-body').empty();
            $('#pinv-isl-no-results').removeClass('d-none').html(
                '<i class="fas fa-search fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + (msg || 'Type at least 1 character to search…') + '</p>'
            );
            $('#pinv-isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let srch     = $.trim($('#pinv-isl-filter-name').val());
            let code     = $.trim($('#pinv-isl-filter-code').val());
            let expiry   = $.trim($('#pinv-isl-filter-expiry').val());

            // If no filter at all, show hint without hitting database
            if (!srch && !code && !expiry) {
                showHintState('Type product name, code or barcode to search…');
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;

            // Return cached result if available (same query, same branch)
            if (islCache[cacheKey]) {
                if (islLastKey !== cacheKey) {
                    islLastKey = cacheKey;
                    renderItems(islCache[cacheKey]);
                }
                return;
            }

            islLastKey = cacheKey;
            let params = { branch_id: branchId, search: srch, code: code, expiry: expiry };

            $('#pinv-isl-loading').removeClass('d-none');
            $('#pinv-isl-no-results').addClass('d-none');
            $('#pinv-isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#pinv-isl-loading').addClass('d-none');
                // Cache for 60s
                islCache[cacheKey] = res.items || [];
                setTimeout(function() { delete islCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#pinv-isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        let islSelectedIdx = -1;

        function updateModalHighlight() {
            let $rows = $('#pinv-isl-items-body tr.pinv-isl-item-row');
            $rows.removeClass('table-primary');
            if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                let $target = $rows.eq(islSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#pinv-isl-table-wrap')[0];
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

        $('#pinv-isl-filter-name, #pinv-isl-filter-code, #pinv-isl-filter-expiry').on('keydown', function (e) {
            let $rows = $('#pinv-isl-items-body tr.pinv-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                islSelectedIdx = Math.min(islSelectedIdx + 1, $rows.length - 1);
                updateModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                islSelectedIdx = Math.max(islSelectedIdx - 1, 0);
                updateModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                    $rows.eq(islSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });

        function renderItems(items) {
            let $tbody = $('#pinv-isl-items-body');
            $tbody.empty();

            if (items.length === 0) {
                $('#pinv-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#pinv-isl-count-label').text('');
                islSelectedIdx = -1;
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = '<span class="text-muted">—</span>';
                if (it.exp_date) {
                    expBadge = `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`;
                } else if (['Mandatory', 'Days', 'Month'].includes(it.batch_expiry_details)) {
                    expBadge = `<span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation-circle mr-1"></i>${it.batch_expiry_details}</span>`;
                }

                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;

                let qtyClass = it.qty <= 0 ? 'text-muted' : 'text-primary font-weight-bold';
                let costDisplay = it.cost_price > 0 ? '₹' + parseFloat(it.cost_price).toFixed(2) : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay  = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';

                html += `
                    <tr class="pinv-isl-item-row ${idx === 0 ? 'table-primary' : ''}" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code}">
                        <td data-col-key="seq" class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td data-col-key="name" class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td data-col-key="code" class="align-middle text-center">${codeBadge}</td>
                        <td data-col-key="cost_price" class="align-middle text-right font-weight-bold text-primary">${costDisplay}</td>
                        <td data-col-key="sell_price" class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td data-col-key="mrp" class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td data-col-key="qty" class="align-middle text-right ${qtyClass}">${parseFloat(it.qty).toFixed(2)}</td>
                        <td data-col-key="expiry" class="align-middle text-center">${expBadge}</td>
                        <td data-col-key="action" class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 pinv-isl-btn-select"
                                data-id="${it.id}" data-code="${it.code}">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            if (window.applyTablePreferences) {
                window.applyTablePreferences('pinv-isl-items-table');
            }
            $('#pinv-isl-table-wrap').removeClass('d-none');
            $('#pinv-isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');
            islSelectedIdx = items.length > 0 ? 0 : -1;
        }

        let islModalClosing = false;
        let itemSelectedInModal = false;
        let cancellingRow = null;

        // Clicking a row or its Select button picks the item
        $(document).on('click', '.pinv-isl-item-row, .pinv-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('pinv-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');

            let $targetRow = activeSearchRow;
            if (! $targetRow || ! itemId) return;

            itemSelectedInModal = true;
            cancellingRow = null;
            pendingFocusExpRow = $targetRow;

            $targetRow.find('.pinv-item-select').val(itemId);
            $targetRow.find('.pinv-item-code').val(itemCode || itemId);

            processPurchaseItemLookup($targetRow, itemId);
            $('#pinv-item-search-modal').modal('hide');
        });

        $('#pinv-item-search-modal').on('show.bs.modal', function(e) {
            if (!canProceedToItems()) {
                e.preventDefault();
                e.stopPropagation();
                islModalOpen = false;
                return false;
            }
            islModalOpen = true;
            islModalClosing = false;
            itemSelectedInModal = false;
            cancellingRow = null;
        });

        $('#pinv-item-search-modal').on('hide.bs.modal', function() {
            islModalOpen = false;
            islModalClosing = true;
            if (!itemSelectedInModal && activeSearchRow && activeSearchRow.length) {
                let selectedId = activeSearchRow.find('.pinv-item-select').val();
                if (!selectedId) {
                    cancellingRow = activeSearchRow;
                }
            }
        });

        $('#pinv-item-search-modal').on('hidden.bs.modal', function() {
            islModalOpen = false;
            islModalClosing = true;
            setTimeout(function() { islModalClosing = false; }, 350);

            if (!itemSelectedInModal) {
                if (cancellingRow && cancellingRow.length) {
                    let totalRows = $('#pinv-items-body tr').length;
                    if (totalRows > 1) {
                        cancellingRow.remove();
                        updateRowNumbers();
                        calculateTotals();
                    } else {
                        cancellingRow.find('.pinv-item-code').val('');
                        cancellingRow.find('.pinv-item-desc').val('');
                    }
                }
                cancellingRow = null;
                activeSearchRow = null;
                setTimeout(function() {
                    let $freight = $('input[name="freight"], #freight');
                    if ($freight.length) {
                        $freight.first().focus().select();
                    } else {
                        $('#pinv-add-row').focus();
                    }
                }, 80);
                return;
            }

            itemSelectedInModal = false;
            cancellingRow = null;
            activeSearchRow = null;

            if (pendingFocusExpRow && pendingFocusExpRow.length) {
                let $target = pendingFocusExpRow;
                pendingFocusExpRow = null;
                setTimeout(function () {
                    focusExpDateField($target);
                }, 100);
            }
        });

        let pinvMouseDown = false;
        $(document).on('mousedown', '.pinv-item-code', function () {
            pinvMouseDown = true;
        });

        function checkSupplierAndOpenPinvModal($input) {
            if (!canProceedToItems()) {
                $input.blur();
                return false;
            }
            if (islModalOpen || islModalClosing) return false;
            let $row = $input.closest('tr');
            if ($row.find('.pinv-item-select').val()) return false;
            activeSearchRow = $row;
            let prefill = $.trim($input.val());
            $('#pinv-isl-filter-name').val(prefill);
            $('#pinv-isl-filter-code').val('');
            $('#pinv-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#pinv-item-search-modal').modal('show');
            $('#pinv-item-search-modal').one('shown.bs.modal', function () {
                $('#pinv-isl-filter-name').focus().select();
                if (prefill) fetchItemList();
            });
            return true;
        }

        // Standardized Barcode & Item Code events are bound in the Item Lookup section below

        // Tab starts from invoice_date on page load (Task 2)
        setTimeout(function () {
            let $first = $('#invoice_date');
            if ($first.length) {
                $first.focus();
            }
        }, 150);

        $('#invoice_date').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let $supp = $('#supplier_id');
                if ($supp.length && $supp.data('select2')) {
                    $supp.data('select2').$container.find('.select2-selection').focus();
                } else if ($supp.length) {
                    $supp.focus();
                }
            }
        });

        // -----------------------------------------------------------------------
        // CAPTURE-PHASE gate: native addEventListener with capture=true
        // runs BEFORE any jQuery bubble-phase handler, so mouse clicks on the
        // items table are killed stone-dead when the gate is not yet open.
        // -----------------------------------------------------------------------
        let _pinvGateDenied = false;
        const _pinvTableEl = document.getElementById('pinv-items-table');
        if (_pinvTableEl) {
            _pinvTableEl.addEventListener('mousedown', function (e) {
                // Allow remove-row buttons
                if (e.target && (e.target.closest('.pinv-remove-row') || e.target.closest('[data-dismiss]'))) return;
                if (!canProceedToItems()) {
                    _pinvGateDenied = true;
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    // Clear flag after this tick so click is also blocked
                    setTimeout(function () { _pinvGateDenied = false; }, 300);
                    return false;
                }
            }, true); // capture phase

            _pinvTableEl.addEventListener('click', function (e) {
                if (_pinvGateDenied) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                }
            }, true); // capture phase

            _pinvTableEl.addEventListener('focus', function (e) {
                if (!canProceedToItems(true)) {
                    // Allow remove-row
                    if (e.target && e.target.closest && e.target.closest('.pinv-remove-row')) return;
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    if (e.target && typeof e.target.blur === 'function') {
                        setTimeout(function () { e.target.blur(); }, 0);
                    }
                    return false;
                }
            }, true); // capture phase
        }

        // Universal guard for any click/focus inside items table (jQuery backup layer)
        $(document).on('mousedown focusin click', '#pinv-items-table input, #pinv-items-table select, #pinv-items-table .select2-selection, #pinv-items-table button:not(.pinv-remove-row)', function (e) {
            if (!canProceedToItems()) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                $(this).blur();
                return false;
            }
        });


        // Disable browser autocomplete dropdown on all number and text inputs in form
        $('#pinv-items-table input, form input').attr('autocomplete', 'off');

        function updateRowNumbers() {
            $('#pinv-items-body tr').each(function (idx) {
                $(this).find('.pinv-sr-no').text(idx + 1);
            });
        }

        function calculateRow($row, source) {
            let qtyStr = $row.find('.pinv-qty').val();
            let costStr = $row.find('.pinv-cost').val();
            let sellStr = $row.find('.pinv-sell').val();
            let mrpStr = $row.find('.pinv-mrp').val();
            let gstStr = $row.find('.pinv-gst').val();

            let qty = parseFloat(qtyStr) || 0;
            let freeQty = parseFloat($row.find('.pinv-free-qty').val()) || 0;
            let cost = parseFloat(costStr) || 0;
            let sell = parseFloat(sellStr) || 0;
            let mrp = parseFloat(mrpStr) || 0;
            let gst = parseFloat(gstStr) || 0;
            let base = qty * cost;

            // Synchronize line discount percentage & amount
            let $discPct = $row.find('.pinv-disc-percent');
            let $discAmt = $row.find('.pinv-disc-amount');

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
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                } else if (discAmt === 0) {
                    discPct = 0;
                    $discPct.val('');
                }
            } else {
                // Qty, Cost, or Free Qty changed
                if (discPct > 0 && base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discAmt > 0 && base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                }
            }

            // Real-time inline field validation (Task 11)
            let $qtyInput = $row.find('.pinv-qty');
            let $costInput = $row.find('.pinv-cost');
            let itemId = $row.find('.pinv-item-select').val();
            if (itemId) {
                if (qty <= 0) {
                    $qtyInput.addClass('border-danger text-danger is-invalid').attr('title', 'Quantity must be greater than 0');
                } else {
                    $qtyInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                if (cost <= 0) {
                    $costInput.addClass('border-danger text-danger is-invalid').attr('title', 'Cost price must be greater than 0');
                } else {
                    $costInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                let isDiscPctInvalid = discPct < 0 || discPct > 100;
                let isDiscAmtInvalid = discAmt < 0 || (base > 0 && discAmt > base);

                if (isDiscPctInvalid) {
                    $discPct.addClass('border-danger text-danger is-invalid').attr('title', 'Discount % cannot exceed 100%');
                } else {
                    $discPct.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                if (isDiscAmtInvalid) {
                    $discAmt.addClass('border-danger text-danger is-invalid').attr('title', 'Discount amount cannot exceed item total (₹' + base.toFixed(2) + ')');
                } else {
                    $discAmt.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }
            }

            scheduleCalculateTotals();
        }

        let _calcTotalsRaf = null;
        function scheduleCalculateTotals() {
            if (_calcTotalsRaf) cancelAnimationFrame(_calcTotalsRaf);
            _calcTotalsRaf = requestAnimationFrame(function () {
                calculateTotals();
            });
        }

        function calculateTotals() {
            let schemeDisc = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let otherDisc = parseFloat($('input[name="other_disc_amt"]').val()) || 0;
            let totalHeaderDiscount = schemeDisc + otherDisc;

            // 1. Collect line data & calculate basic cost after item discount
            let rowsData = [];
            let totalBaseAfterItemDisc = 0;

            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let freeQty = parseFloat($r.find('.pinv-free-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let sell = parseFloat($r.find('.pinv-sell').val()) || 0;
                let mrp = parseFloat($r.find('.pinv-mrp').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.pinv-gst').val()) || 0;

                let base = qty * cost;
                let baseAfterDisc = Math.max(0, base - discAmt);
                totalBaseAfterItemDisc += baseAfterDisc;

                rowsData.push({
                    $row: $r,
                    qty: qty,
                    freeQty: freeQty,
                    cost: cost,
                    sell: sell,
                    mrp: mrp,
                    base: base,
                    discAmt: discAmt,
                    baseAfterDisc: baseAfterDisc,
                    gst: gst
                });
            });

            // 2. Allocate header discount (Scheme ItemDiscAmt + OtherDiscAmt) on basic cost without GST
            let remainingDiscount = totalHeaderDiscount;
            let totalQty = 0;
            let totalCost = 0;
            let totalDiscAmt = 0;
            let totalGstAmt = 0;
            let totalNetAmt = 0;
            let hasAny = false;

            for (let i = 0; i < rowsData.length; i++) {
                let d = rowsData[i];
                let extraDeduction = 0;

                if (totalBaseAfterItemDisc > 0 && totalHeaderDiscount > 0) {
                    if (i === rowsData.length - 1) {
                        extraDeduction = Math.round(remainingDiscount * 100) / 100;
                    } else {
                        extraDeduction = Math.round(((d.baseAfterDisc / totalBaseAfterItemDisc) * totalHeaderDiscount) * 100) / 100;
                        remainingDiscount -= extraDeduction;
                    }
                }
                extraDeduction = Math.max(0, extraDeduction);

                let taxable = Math.max(0, d.baseAfterDisc - extraDeduction);
                let taxAmt = Math.round((taxable * d.gst / 100) * 100) / 100;
                let net = taxable + taxAmt;

                if (d.base > 0) {
                    d.$row.find('.pinv-gst-amt').val(taxAmt > 0 ? taxAmt.toFixed(2) : '');
                    d.$row.find('.pinv-row-net').text(net > 0 ? net.toFixed(2) : '');
                    hasAny = true;
                } else {
                    d.$row.find('.pinv-gst-amt').val('');
                    d.$row.find('.pinv-row-net').text('');
                }

                // True Landing Cost = (Billed Base Cost - Line Disc - Allocated Scheme/Other Disc) / (Billed Qty + Free Qty)
                let totalUnits = d.qty + d.freeQty;
                let trueLandingCost = 0;
                if (totalUnits > 0 && d.cost > 0) {
                    let netCostAfterAllDisc = Math.max(0, d.baseAfterDisc - extraDeduction);
                    trueLandingCost = netCostAfterAllDisc / totalUnits;
                } else if (d.cost > 0) {
                    trueLandingCost = d.cost;
                }

                let $landingInput = d.$row.find('.pinv-landing-cost');
                if ($landingInput.length) {
                    $landingInput.val(trueLandingCost > 0 ? trueLandingCost.toFixed(2) : '');
                    let discBreakdown = [];
                    if (d.freeQty > 0) discBreakdown.push(d.freeQty + ' free');
                    if (d.discAmt > 0) discBreakdown.push('₹' + d.discAmt.toFixed(2) + ' item disc');
                    if (extraDeduction > 0) discBreakdown.push('₹' + extraDeduction.toFixed(2) + ' scheme/other disc');

                    if (discBreakdown.length > 0) {
                        $landingInput.attr('title', 'Landing Cost Price: ₹' + trueLandingCost.toFixed(2) + ' (Effective unit cost after ' + discBreakdown.join(', ') + ')');
                    } else {
                        $landingInput.attr('title', 'Landing Cost Price');
                    }
                }

                // Effective cost used for Margin % and Profit % calculations
                let effectiveCost = trueLandingCost > 0 ? trueLandingCost : d.cost;
                let baseSell = d.sell > 0 ? d.sell : d.mrp;
                let sellExclGst = (baseSell > 0) ? (baseSell / (1 + (d.gst / 100))) : 0;
                let profitAmount = (sellExclGst > 0 && effectiveCost > 0) ? (sellExclGst - effectiveCost) : null;
                let marginPct = (sellExclGst > 0 && profitAmount !== null) ? ((profitAmount / sellExclGst) * 100) : null;
                let profitPct = (effectiveCost > 0 && profitAmount !== null) ? ((profitAmount / effectiveCost) * 100) : null;

                d.$row.find('.pinv-margin').val(marginPct !== null && isFinite(marginPct) ? marginPct.toFixed(2) + '%' : '');
                d.$row.find('.pinv-profit').val(profitPct !== null && isFinite(profitPct) ? profitPct.toFixed(2) + '%' : '');

                // Inline price validations against True Landing Cost
                let $sellInput = d.$row.find('.pinv-sell');
                let $mrpInput = d.$row.find('.pinv-mrp');
                $sellInput.removeClass('border-danger text-danger border-warning text-warning is-invalid').attr('title', '');
                $mrpInput.removeClass('border-danger text-danger border-warning text-warning is-invalid').attr('title', '');

                let benchmarkCost = effectiveCost > 0 ? effectiveCost : d.cost;
                if (benchmarkCost > 0 && d.sell > 0 && d.sell <= benchmarkCost) {
                    $sellInput.addClass('border-danger text-danger')
                              .attr('title', 'Sell Price (₹' + d.sell.toFixed(2) + ') must be greater than Landing Cost (₹' + benchmarkCost.toFixed(2) + ')!');
                } else if (d.mrp > 0 && d.sell > 0 && d.sell > d.mrp) {
                    $sellInput.addClass('border-warning text-warning')
                              .attr('title', 'Sell Price (₹' + d.sell.toFixed(2) + ') must not exceed MRP (₹' + d.mrp.toFixed(2) + ')!');
                }

                if (benchmarkCost > 0 && d.mrp > 0 && d.mrp <= benchmarkCost) {
                    $mrpInput.addClass('border-danger text-danger')
                             .attr('title', 'MRP (₹' + d.mrp.toFixed(2) + ') must be greater than Landing Cost (₹' + benchmarkCost.toFixed(2) + ')!');
                } else if (d.sell > 0 && d.mrp > 0 && d.mrp < d.sell) {
                    $mrpInput.addClass('border-warning text-warning')
                             .attr('title', 'MRP (₹' + d.mrp.toFixed(2) + ') cannot be less than Sell Price (₹' + d.sell.toFixed(2) + ')!');
                }

                totalQty += (d.qty + d.freeQty);
                totalCost += d.base;
                totalDiscAmt += (d.discAmt + extraDeduction); // item disc + proportional header disc
                totalGstAmt += taxAmt;
                totalNetAmt += net;
            }

            // 3. Update footer totals
            if (hasAny) {
                $('#footer-total-qty').text(totalQty > 0 ? totalQty.toFixed(3) : '');
                $('#footer-total-cost').text(totalCost > 0 ? totalCost.toFixed(2) : '');
                $('#footer-total-disc').text(totalDiscAmt > 0 ? totalDiscAmt.toFixed(2) : '');
                $('#footer-total-gst').text(totalGstAmt > 0 ? totalGstAmt.toFixed(2) : '');
                $('#footer-grand-net').text(totalNetAmt > 0 ? totalNetAmt.toFixed(2) : '');
            } else {
                $('#footer-total-qty').text('');
                $('#footer-total-cost').text('');
                $('#footer-total-disc').text('');
                $('#footer-total-gst').text('');
                $('#footer-grand-net').text('');
            }

            // 4. Update Final Amount & check match
            let freight = parseFloat($('input[name="freight"]').val()) || 0;
            let roundOff = parseFloat($('input[name="round_off"]').val()) || 0;
            let tcsAmt = parseFloat($('input[name="tcs_amount"]').val()) || 0;
            let finalTotal = Math.round((totalNetAmt + freight + roundOff + tcsAmt) * 100) / 100;

            $('#display-final-total').text(finalTotal.toFixed(2));
            $('#display-pinv-final-total').text(finalTotal.toFixed(2));
            let pinvItemCount = $('#pinv-items-body tr').filter(function () {
                return !!$(this).find('.pinv-item-select').val();
            }).length;
            $('#pinv-total-items-badge').text(pinvItemCount + (pinvItemCount === 1 ? ' Item' : ' Items'));
            checkAmountMatch(finalTotal);
        }

        function getLiveFinalTotal() {
            let schemeDisc = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let otherDisc = parseFloat($('input[name="other_disc_amt"]').val()) || 0;
            let totalHeaderDiscount = schemeDisc + otherDisc;

            let rowsData = [];
            let totalBaseAfterItemDisc = 0;

            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.pinv-gst').val()) || 0;

                let base = qty * cost;
                let baseAfterDisc = Math.max(0, base - discAmt);
                totalBaseAfterItemDisc += baseAfterDisc;

                rowsData.push({
                    baseAfterDisc: baseAfterDisc,
                    gst: gst
                });
            });

            let remainingDiscount = totalHeaderDiscount;
            let totalNetAmt = 0;

            for (let i = 0; i < rowsData.length; i++) {
                let d = rowsData[i];
                let extraDeduction = 0;

                if (totalBaseAfterItemDisc > 0 && totalHeaderDiscount > 0) {
                    if (i === rowsData.length - 1) {
                        extraDeduction = Math.round(remainingDiscount * 100) / 100;
                    } else {
                        extraDeduction = Math.round(((d.baseAfterDisc / totalBaseAfterItemDisc) * totalHeaderDiscount) * 100) / 100;
                        remainingDiscount -= extraDeduction;
                    }
                }
                extraDeduction = Math.max(0, extraDeduction);

                let taxable = Math.max(0, d.baseAfterDisc - extraDeduction);
                let taxAmt = Math.round((taxable * d.gst / 100) * 100) / 100;
                totalNetAmt += (taxable + taxAmt);
            }

            let freight = parseFloat($('input[name="freight"]').val()) || 0;
            let roundOff = parseFloat($('input[name="round_off"]').val()) || 0;
            let tcsAmt = parseFloat($('input[name="tcs_amount"]').val()) || 0;

            return Math.round((totalNetAmt + freight + roundOff + tcsAmt) * 100) / 100;
        }

        function checkAmountMatch(finalTotal) {
            if (finalTotal === undefined) {
                finalTotal = getLiveFinalTotal();
            }
            $('#display-final-total').text(finalTotal.toFixed(2));
            $('#display-pinv-final-total').text(finalTotal.toFixed(2));

            let $invAmtInput = $('input[name="supplier_inv_amount"]');
            let invAmtVal = $invAmtInput.val();
            let $statusDiv = $('#supplier-inv-amount-match-status');
            let $badgeDiv = $('#final-amount-match-badge');

            if (!invAmtVal && finalTotal === 0) {
                $statusDiv.html('');
                $badgeDiv.html('');
                $invAmtInput.removeClass('is-valid is-invalid');
                return;
            }

            let invAmt = parseFloat(invAmtVal) || 0;
            let diff = Math.round((invAmt - finalTotal) * 100) / 100;

            if (invAmt > 0 && Math.abs(diff) <= 0.01) {
                $statusDiv.html('<span class="text-success"><i class="fas fa-check-circle"></i> Inv Amount (Supplier) matches Final Amount (₹' + finalTotal.toFixed(2) + ')</span>');
                $badgeDiv.html('<span class="badge badge-success px-3 py-2 font-weight-bold"><i class="fas fa-check-circle"></i> Amounts Matched (₹' + finalTotal.toFixed(2) + ')</span>');
                $invAmtInput.removeClass('is-invalid').addClass('is-valid');
            } else {
                let diffText = (diff > 0 ? '+' : '') + diff.toFixed(2);
                let msg = 'Diff: ₹' + diffText + ' (Supplier Inv: ₹' + invAmt.toFixed(2) + ' vs Final: ₹' + finalTotal.toFixed(2) + ')';
                $statusDiv.html('<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ' + msg + ' — Both amounts must match to save</span>');
                $badgeDiv.html('<span class="badge badge-danger px-3 py-2 font-weight-bold"><i class="fas fa-exclamation-triangle"></i> ' + msg + '</span>');
                if (invAmtVal) {
                    $invAmtInput.removeClass('is-valid').addClass('is-invalid');
                }
            }
        }

        function focusExpDateField($row) {
            if (!$row || !$row.length) return;
            let $exp = $row.find('.pinv-exp-date');
            // If exp date column is visible, focus it
            if ($exp.length && !$exp.closest('td').hasClass('table-col-hidden') && $exp.is(':visible')) {
                $exp[0].focus();
            } else {
                // Fall back to qty field if expiry is hidden
                let $qty = $row.find('.pinv-qty');
                if ($qty.length && $qty.is(':visible')) {
                    $qty[0].focus();
                    $qty[0].select && $qty[0].select();
                }
            }
        }

        function updateExpiryRequirement($row, batchExpiry, shelfLife) {
            let $expInput = $row.find('.pinv-exp-date');
            let $expBadge = $row.find('.pinv-exp-badge');

            if (batchExpiry === undefined || batchExpiry === null) {
                let $sel = $row.find('.pinv-item-select');
                batchExpiry = $sel.attr('data-batch-expiry') || 'Not Required';
                shelfLife = parseInt($sel.attr('data-shelf-life') || 0);
            }

            if (batchExpiry === 'Mandatory' || batchExpiry === 'Days' || batchExpiry === 'Month') {
                $expInput.prop('required', true).addClass('border-danger');
                // Only show badge if date is NOT already filled
                if (!$expInput.val()) {
                    $expBadge.removeClass('d-none').html('<i class="fas fa-exclamation-circle"></i> ' + (batchExpiry === 'Mandatory' ? 'Required' : batchExpiry));
                } else {
                    $expBadge.addClass('d-none');
                }
                $expInput.attr('title', 'Expiry date is mandatory for this item (' + batchExpiry + ')');
            } else {
                // Not Required or Optional: no validation needed!
                $expInput.prop('required', false).removeClass('border-danger');
                $expBadge.addClass('d-none');
                $expInput.attr('title', 'Expiry date (optional)');
            }
        }

        function processPurchaseItemLookup($row, itemId, query, isDirectLookup = false) {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let $select = $row.find('.pinv-item-select');
            let $desc = $row.find('.pinv-item-desc');
            let $code = $row.find('.pinv-item-code');

            if (query && window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(query);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate hardware bounce
                }
            }

            let params = { branch_id: branchId };
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

            $.getJSON('{{ route("purchase.purchase-invoices.lookup-item") }}', params, function (data) {
                if (data && data.id) {
                    $row.data('last-processed-code', query || data.item_code || data.ean_upc_code || data.id);
                    $code.removeClass('is-invalid border-danger');

                    // Display actual item code
                    let codeVal = data.item_code || data.ean_upc_code || data.code || data.id;
                    $code.val(codeVal);

                    $select.val(data.id);
                    $select.attr('data-batch-expiry', data.batch_expiry_details || 'Not Required');
                    $select.attr('data-shelf-life', data.shelf_life_days || 0);
                    $desc.val(data.name + (data.item_code ? ' [' + data.item_code + ']' : ''));

                    // Set pricing fields
                    if (data.cost_price > 0) {
                        $row.find('.pinv-cost').val(parseFloat(data.cost_price).toFixed(2));
                    }
                    if (data.sell_price > 0) {
                        $row.find('.pinv-sell').val(parseFloat(data.sell_price).toFixed(2));
                    }
                    if (data.mrp > 0) {
                        $row.find('.pinv-mrp').val(parseFloat(data.mrp).toFixed(2));
                    }
                    if (data.gst_percent >= 0) {
                        $row.find('.pinv-gst').val(parseFloat(data.gst_percent).toFixed(2));
                    }

                    // Update expiry rules
                    updateExpiryRequirement($row, data.batch_expiry_details, data.shelf_life_days);

                    // Do not auto-populate default expiry date on item select as requested
                    $row.find('.pinv-exp-date').val('');

                    calculateRow($row, 'base');

                    // Standard Barcode Flow: Focus Qty or Batch No if mandatory
                    setTimeout(function () {
                        let isExpMandatory = (data.batch_expiry_details === 'Mandatory' || data.batch_expiry_details === 'Days' || data.batch_expiry_details === 'Month');
                        if (isExpMandatory && !$row.find('.pinv-batch-no').val()) {
                            $row.find('.pinv-batch-no').focus().select();
                        } else {
                            $row.find('.pinv-qty').focus().select();
                        }
                    }, 60);
                } else {
                    $row.data('last-processed-code', null);
                    $code.addClass('is-invalid border-danger');
                    const errMsg = "Product not found for this Item Code/Barcode.";
                    if (window.toastr && typeof window.toastr.warning === 'function') {
                        toastr.clear();
                        toastr.warning(errMsg, 'Item Not Found');
                    } else {
                        alert(errMsg);
                    }
                    setTimeout(function () {
                        $code.focus().select();
                    }, 50);
                }
            });
        }

        // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation
        $(document).off('keydown change input', '.pinv-item-code')
            .on('keydown', '.pinv-item-code', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        // Direct exact lookup without opening popup modal
                        processPurchaseItemLookup($row, null, val, true);
                    } else {
                        // Empty field -> Open Item Search Modal
                        checkSupplierAndOpenPinvModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr');
                    if (val) {
                        e.preventDefault();
                        processPurchaseItemLookup($row, null, val, true);
                    } else {
                        e.preventDefault();
                        checkSupplierAndOpenPinvModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkSupplierAndOpenPinvModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr');
                    let itemId = $row.find('.pinv-item-select').val();
                    if (!itemId && $('#pinv-items-body tr').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr');
                        $row.remove();
                        updateRowNumbers();
                        calculateTotals();
                        if ($prevRow.length) {
                            $prevRow.find('.pinv-qty').focus().select();
                        }
                    }
                }
            })
            .on('change', '.pinv-item-code', function () {
                let $input = $(this);
                let query = $.trim($input.val());
                let $row = $input.closest('tr');
                if (!query) {
                    let existingItemId = $row.find('.pinv-item-select').val();
                    if (existingItemId) {
                        $row.find('.pinv-item-select').val('');
                        $row.find('.pinv-item-desc').val('');
                        updateExpiryRequirement($row, 'Not Required', 0);
                        calculateRow($row);
                    }
                    $row.data('last-processed-code', '');
                    return;
                }

                if ($row.data('last-processed-code') === query) return;
                processPurchaseItemLookup($row, null, query, true);
            })
            .on('input', '.pinv-item-code', function () {
                $(this).removeClass('is-invalid border-danger');
            });

        // Clicking on description also opens item search modal
        $(document).on('click', '.pinv-item-desc', function () {
            let $code = $(this).closest('tr').find('.pinv-item-code');
            checkSupplierAndOpenPinvModal($code);
        });

        // 2. Item Selection: Auto-populate Code, Cost, Sell, MRP, GST, Margin %, Profit %, and apply Batch/Expiry rule
        $(document).on('change', '.pinv-item-select', function () {
            let $select = $(this);
            let $row = $select.closest('tr');
            let itemId = $select.val();

            if (!itemId) {
                $row.find('.pinv-item-code').val('');
                updateExpiryRequirement($row, 'Not Required', 0);
                return;
            }

            let $opt = $select.find('option:selected');
            let itemCode = $opt.attr('data-code') || $opt.data('code') || $opt.attr('data-ean') || $opt.data('ean') || '';
            if (itemCode) {
                $row.find('.pinv-item-code').val(itemCode);
            }

            let cost = parseFloat($opt.attr('data-cost') !== undefined ? $opt.attr('data-cost') : $opt.data('cost'));
            let sell = parseFloat($opt.attr('data-sell') !== undefined ? $opt.attr('data-sell') : $opt.data('sell'));
            let mrp = parseFloat($opt.attr('data-mrp') !== undefined ? $opt.attr('data-mrp') : $opt.data('mrp'));
            let gst = parseFloat($opt.attr('data-gst') !== undefined ? $opt.attr('data-gst') : $opt.data('gst'));
            let batchExpiry = $opt.attr('data-batch-expiry') || $opt.data('batch-expiry');
            let shelfLife = parseInt($opt.attr('data-shelf-life') || $opt.data('shelf-life') || 0);

            updateExpiryRequirement($row, batchExpiry, shelfLife);

            if (!isNaN(cost) || !isNaN(sell) || !isNaN(mrp) || !isNaN(gst)) {
                $row.find('.pinv-cost').val(!isNaN(cost) && cost > 0 ? cost.toFixed(2) : '');
                $row.find('.pinv-sell').val(!isNaN(sell) && sell > 0 ? sell.toFixed(2) : '');
                $row.find('.pinv-mrp').val(!isNaN(mrp) && mrp > 0 ? mrp.toFixed(2) : '');
                $row.find('.pinv-gst').val(!isNaN(gst) && gst >= 0 ? gst.toFixed(2) : '');

                calculateRow($row);
            } else {
                // Fallback: Fetch from API endpoint if data attributes missing
                $.getJSON('{{ url("purchase/purchase-invoices/item-details") }}/' + itemId, function (data) {
                    if (data) {
                        if (data.item_code || data.ean_upc_code) {
                            $row.find('.pinv-item-code').val(data.item_code || data.ean_upc_code);
                        }
                        updateExpiryRequirement($row, data.batch_expiry_details, data.shelf_life_days);
                        $row.find('.pinv-cost').val(data.cost_price > 0 ? Number(data.cost_price).toFixed(2) : '');
                        $row.find('.pinv-sell').val(data.sell_price > 0 ? Number(data.sell_price).toFixed(2) : '');
                        $row.find('.pinv-mrp').val(data.mrp > 0 ? Number(data.mrp).toFixed(2) : '');
                        $row.find('.pinv-gst').val(Number(data.gst_percent || 0) >= 0 ? Number(data.gst_percent).toFixed(2) : '');

                        calculateRow($row);
                        focusExpDateField($row);
                    }
                });
            }
        });

        function getTodayIsoString() {
            let now = new Date();
            let pad = n => n < 10 ? '0' + n : String(n);
            return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        }

        function parseToYmd(val) {
            if (!val) return null;
            val = $.trim(val);
            if (/^\d{4}-\d{2}-\d{2}$/.test(val)) {
                let p = val.split('-');
                let y = parseInt(p[0], 10), m = parseInt(p[1], 10), d = parseInt(p[2], 10);
                if (m >= 1 && m <= 12 && d >= 1 && d <= 31) return val;
                return null;
            }
            let m = val.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);
            if (m) {
                let d = parseInt(m[1], 10);
                let mo = parseInt(m[2], 10);
                let y = parseInt(m[3], 10);
                if (mo >= 1 && mo <= 12 && d >= 1 && d <= 31) {
                    return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                }
            }
            return null;
        }

        function validatePinvExpDate($input, showToast = false) {
            let rawVal = $.trim($input.val());
            let $row = $input.closest('tr');
            let $badge = $row.find('.pinv-exp-badge');
            let isRequired = $input.prop('required');
            let todayStr = getTodayIsoString();

            if (rawVal) {
                let ymd = parseToYmd(rawVal);
                if (!ymd) {
                    $input.addClass('border-danger is-invalid').removeClass('border-success');
                    $badge.removeClass('d-none').addClass('text-danger').html('<i class="fas fa-exclamation-triangle"></i> Invalid Date');
                    return false;
                }
                if (ymd < todayStr) {
                    $input.addClass('border-danger is-invalid').removeClass('border-success');
                    $badge.removeClass('d-none').addClass('text-danger').html('<i class="fas fa-exclamation-triangle"></i> Expired (Past Date)');
                    return false;
                }
                $badge.addClass('d-none');
                $input.removeClass('border-danger is-invalid').addClass('border-success');
                return true;
            }

            if (isRequired) {
                $badge.removeClass('d-none').addClass('text-danger').html('<i class="fas fa-exclamation-circle"></i> Required');
                $input.addClass('border-danger is-invalid').removeClass('border-success');
                return false;
            }

            $badge.addClass('d-none');
            $input.removeClass('border-danger border-success is-invalid');
            return true;
        }

        // Validate immediately when expiry date is changed/input/blur
        $(document).on('change input', '.pinv-exp-date', function (e) {
            validatePinvExpDate($(this), false);
        });

        $(document).on('blur', '.pinv-exp-date', function (e) {
            let $input = $(this);
            let isValid = validatePinvExpDate($input, false);
            if (!isValid && ($input.val() || $input.prop('required'))) {
                setTimeout(function () {
                    $input.focus();
                }, 10);
            }
        });

        // Tab & Enter navigation on Exp Date:
        // If date is invalid or in the past, block navigation completely!
        $(document).on('keydown', '.pinv-exp-date', function (e) {
            let isTab = (e.key === 'Tab' && !e.shiftKey);
            let isEnter = (e.key === 'Enter' || e.keyCode === 13);

            if (isTab || isEnter) {
                let isValid = validatePinvExpDate($(this), false);
                if (!isValid && ($(this).val() || $(this).prop('required'))) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    $(this).focus();
                    return false;
                }
                if (isEnter) {
                    e.preventDefault();
                    $(this).closest('tr').find('.pinv-qty').focus().select();
                }
            }
        });

        // Qty Tab & Enter navigation and validation (PHASE 12)
        $(document).on('keydown', '.pinv-qty', function (e) {
            if (e.key === 'Tab' || e.key === 'Enter') {
                let qty = parseFloat($(this).val()) || 0;
                let $row = $(this).closest('tr');
                let itemId = $row.find('.pinv-item-select').val();
                if (itemId && qty <= 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('is-invalid border-danger');
                    let $feedback = $(this).siblings('.pinv-qty-error-msg');
                    if (!$feedback.length) {
                        $feedback = $('<div class="pinv-qty-error-msg invalid-feedback text-danger font-weight-bold" style="display:none; font-size:11px;"></div>');
                        $(this).after($feedback);
                    }
                    $feedback.text('Quantity is required and must be greater than 0.').css('display', 'block');
                    $(this).focus().select();
                    return false;
                }
            }
        });

        $(document).on('input', '.pinv-qty', function () {
            let qty = parseFloat($(this).val()) || 0;
            if (qty > 0) {
                $(this).removeClass('is-invalid border-danger');
                $(this).siblings('.pinv-qty-error-msg').css('display', 'none');
            }
        });


        // 3. Real-time Calculation Listeners
        $(document).on('input', '.pinv-qty, .pinv-cost, .pinv-sell, .pinv-mrp', function () {
            calculateRow($(this).closest('tr'), 'base');
        });

        $(document).on('input', '.pinv-disc-percent', function () {
            calculateRow($(this).closest('tr'), 'percent');
        });

        $(document).on('input', '.pinv-disc-amount', function () {
            calculateRow($(this).closest('tr'), 'amount');
        });

        $(document).on('input change blur', '.pinv-gst, .pinv-free-qty', function () {
            calculateRow($(this).closest('tr'), 'other');
        });



        function getPinvTotalBaseCost() {
            let total = 0;
            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let base = qty * cost;
                total += Math.max(0, base - discAmt);
            });
            return total;
        }

        let schemeSyncing = false;

        $(document).on('input', 'input[name="scheme_item_disc_amt"]', function () {
            if (schemeSyncing) return;
            schemeSyncing = true;
            let amt = parseFloat($(this).val()) || 0;
            let base = getPinvTotalBaseCost();
            if (base > 0 && amt > 0) {
                let pct = (amt / base) * 100;
                $('input[name="scheme_item_disc_percent"]').val(pct.toFixed(2));
            } else if (amt === 0) {
                $('input[name="scheme_item_disc_percent"]').val('');
            }
            schemeSyncing = false;
            scheduleCalculateTotals();
        });

        $(document).on('input', 'input[name="scheme_item_disc_percent"]', function () {
            if (schemeSyncing) return;
            schemeSyncing = true;
            let pct = parseFloat($(this).val()) || 0;
            let base = getPinvTotalBaseCost();
            if (base > 0 && pct > 0) {
                let amt = (base * pct) / 100;
                $('input[name="scheme_item_disc_amt"]').val(amt.toFixed(2));
            } else if (pct === 0) {
                $('input[name="scheme_item_disc_amt"]').val('');
            }
            schemeSyncing = false;
            scheduleCalculateTotals();
        });

        function syncSchemePercentFromAmount() {
            if (schemeSyncing) return;
            let amt = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let base = getPinvTotalBaseCost();
            if (base > 0 && amt > 0) {
                let pct = (amt / base) * 100;
                $('input[name="scheme_item_disc_percent"]').val(pct.toFixed(2));
            }
        }

        $(document).on('input change', 'input[name="supplier_inv_amount"], input[name="freight"], input[name="round_off"], input[name="other_disc_amt"], input[name="tcs_amount"]', function () {
            scheduleCalculateTotals();
        });

        // 4. Form Submit Guard
        $('form').on('submit', function (e) {
            // Always re-enable purchase_type so its value gets submitted even if disabled
            $('#purchase_type').prop('disabled', false);

            if ($('#supplier_inv_no').data('is-duplicate') === true) {
                e.preventDefault();
                let dupMsg = $('#supplier-inv-feedback').text() || 'Supplier Invoice Number is already recorded for this supplier.';
                if (window.toastr) {
                    toastr.error(dupMsg, 'Duplicate Invoice Number');
                }
                $('#supplier_inv_no').focus();
                return false;
            }

            let priceError = null;
            $('#pinv-items-body tr').each(function (idx) {
                let $r = $(this);
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let sell = parseFloat($r.find('.pinv-sell').val()) || 0;
                let itemName = $r.find('.pinv-item-desc').val() || ('Row #' + (idx + 1));
                if (cost > 0 && sell <= cost) {
                    priceError = {
                        row: idx + 1,
                        item: itemName,
                        cost: cost,
                        sell: sell,
                        $input: $r.find('.pinv-sell')
                    };
                    return false; // break loop
                }
            });

            if (priceError) {
                e.preventDefault();
                let errMsg = "Row #" + priceError.row + " (" + priceError.item + "): Sell Price (₹" + priceError.sell.toFixed(2) + ") must be greater than Cost Price (₹" + priceError.cost.toFixed(2) + ")!";
                if (window.toastr) {
                    toastr.error(errMsg, 'Price Validation Error');
                }
                priceError.$input.focus().addClass('border-danger text-danger');
                return false;
            }

            // Rule: Sell Price must be <= MRP
            let mrpError = null;
            $('#pinv-items-body tr').each(function (idx) {
                let $r = $(this);
                let sell = parseFloat($r.find('.pinv-sell').val()) || 0;
                let mrp = parseFloat($r.find('.pinv-mrp').val()) || 0;
                let itemName = $r.find('.pinv-item-desc').val() || ('Row #' + (idx + 1));
                if (mrp > 0 && sell > mrp) {
                    mrpError = {
                        row: idx + 1,
                        item: itemName,
                        sell: sell,
                        mrp: mrp,
                        $input: $r.find('.pinv-sell')
                    };
                    return false; // break loop
                }
            });

            if (mrpError) {
                e.preventDefault();
                let errMsg = "Row #" + mrpError.row + " (" + mrpError.item + "): Sell Price (₹" + mrpError.sell.toFixed(2) + ") must not exceed MRP (₹" + mrpError.mrp.toFixed(2) + ")!";
                if (window.toastr) {
                    toastr.error(errMsg, 'Price Validation Error');
                }
                mrpError.$input.focus().addClass('border-warning text-warning');
                return false;
            }

            // Rule: MRP must be greater than Cost Price
            let mrpCostError = null;
            $('#pinv-items-body tr').each(function (idx) {
                let $r = $(this);
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let mrp = parseFloat($r.find('.pinv-mrp').val()) || 0;
                let itemName = $r.find('.pinv-item-desc').val() || ('Row #' + (idx + 1));
                if (cost > 0 && mrp > 0 && mrp <= cost) {
                    mrpCostError = {
                        row: idx + 1,
                        item: itemName,
                        cost: cost,
                        mrp: mrp,
                        $input: $r.find('.pinv-mrp')
                    };
                    return false; // break loop
                }
            });

            if (mrpCostError) {
                e.preventDefault();
                let errMsg = "Row #" + mrpCostError.row + " (" + mrpCostError.item + "): MRP (₹" + mrpCostError.mrp.toFixed(2) + ") must be greater than Cost Price (₹" + mrpCostError.cost.toFixed(2) + ")!";
                if (window.toastr) {
                    toastr.error(errMsg, 'Price Validation Error');
                }
                mrpCostError.$input.focus().addClass('border-danger text-danger');
                return false;
            }

            let invAmt = parseFloat($('input[name="supplier_inv_amount"]').val()) || 0;
            let finalTotal = getLiveFinalTotal();
            let diff = Math.round((invAmt - finalTotal) * 100) / 100;

            if (invAmt > 0 && Math.abs(diff) > 0.01) {
                e.preventDefault();
                let diffMsg = (diff > 0 ? '+' : '') + diff.toFixed(2);
                let amtMsg = "Supplier Invoice Amount [₹" + invAmt.toFixed(2) + "] must match the Final Amount [₹" + finalTotal.toFixed(2) + "] before saving! Difference: ₹" + diffMsg;
                if (window.toastr) {
                    toastr.warning(amtMsg, 'Amount Mismatch');
                }
                $('input[name="supplier_inv_amount"]').focus().addClass('is-invalid');
                checkAmountMatch();
                return false;
            }

            // Show submit loading indicator
            let $btn = $(this).find('button[type="submit"]');
            if ($btn.length) {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            }
        });

        function initPinvItemSelect2($el) {
            // Item description is now a clean readonly text input
        }

        function canAddPinvRow() {
            let $lastRow = $('#pinv-items-body tr:last');
            if ($lastRow.length) {
                let itemId = $lastRow.find('.pinv-item-select').val();
                let qtyVal = parseFloat($lastRow.find('.pinv-qty').val()) || 0;

                if (!itemId) {
                    let msg = 'Pehle current row me item select karein.';
                    if (window.toastr) toastr.warning(msg, 'Incomplete Row');
                    else alert(msg);
                    $lastRow.find('.pinv-item-code').focus();
                    return false;
                }

                if (qtyVal <= 0) {
                    let msg = 'Pehle item ki valid quantity enter karein.';
                    if (window.toastr) toastr.warning(msg, 'Quantity Required');
                    else alert(msg);
                    $lastRow.find('.pinv-qty').focus().select();
                    return false;
                }
            }
            return true;
        }

        function addPinvRowAndOpenSearchModal() {
            if (!canProceedToItems()) {
                return;
            }
            if (!canAddPinvRow()) {
                return;
            }
            let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#pinv-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);
            initPinvItemSelect2($newRow.find('.pinv-item-select'));
            $newRow.find('input').attr('autocomplete', 'off');
            updateExpiryRequirement($newRow, 'Not Required', 0);
            rowIndex++;
            updateRowNumbers();
            calculateTotals();

            // Immediately trigger the item search modal for the newly added row
            activeSearchRow = $newRow;
            $('#pinv-isl-filter-name').val('');
            $('#pinv-isl-filter-code').val('');
            $('#pinv-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#pinv-item-search-modal').modal('show');
            $('#pinv-item-search-modal').one('shown.bs.modal', function () {
                $('#pinv-isl-filter-name').focus();
            });
        }

        // On Disc Amount field: Tab or Enter creates new row and opens item search popup; if user cancels without selecting an item, that row is automatically deleted and focus moves to Freight!
        $(document).off('keydown', '.pinv-disc-amount').on('keydown', '.pinv-disc-amount', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                e.preventDefault();
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if ($nextRow.length) {
                    $nextRow.find('.pinv-item-code').focus();
                } else {
                    addPinvRowAndOpenSearchModal();
                }
            }
        });

        // 5. Add Row
        $('#pinv-add-row').on('click', function (e) {
            if (!canProceedToItems()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            if (!canAddPinvRow()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#pinv-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);

            // Initialize Select2 on the newly added row's dropdown
            initPinvItemSelect2($newRow.find('.pinv-item-select'));

            // Ensure autocomplete is off on new row inputs
            $newRow.find('input').attr('autocomplete', 'off');

            updateExpiryRequirement($newRow, 'Not Required', 0);
            rowIndex++;
            updateRowNumbers();
            calculateTotals();
        });

        // 6. Remove Row
        $('#pinv-items-body').on('click', '.pinv-remove-row', function () {
            let rows = $('#pinv-items-body tr');
            if (rows.length <= 1) return;
            $(this).closest('tr').remove();
            updateRowNumbers();
            calculateTotals();
        });

        // 7. Initial Run on existing rows
        initPinvItemSelect2($('.pinv-item-select'));
        updateRowNumbers();
        $('#pinv-items-body tr').each(function () {
            let $r = $(this);
            calculateRow($r, 'initial');
            updateExpiryRequirement($r);
        });
        syncSchemePercentFromAmount();
        checkAmountMatch();

        // Prevent future dates on invoice_date, grn_date, supplier_inv_date
        $('#invoice_date, #grn_date, #supplier_inv_date').on('change blur', function () {
            if (!this.value) return;
            let parts = typeof window.parseDateParts === 'function' ? window.parseDateParts(this.value) : null;
            if (parts) {
                let pad = n => n < 10 ? '0' + n : String(n);
                let isoVal = parts.year + '-' + pad(parts.month) + '-' + pad(parts.day);
                let now = new Date();
                let todayIso = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
                if (isoVal > todayIso) {
                    let fieldName = $(this).closest('.form-group').find('label').text().trim().replace('*', '').trim() || 'Date';
                    if (window.toastr) {
                        window.toastr.warning('Future date is not allowed for ' + fieldName + '. Date reset to today.');
                    }
                    let todayFormatted = typeof window.formatParts === 'function' && typeof window.UrbanPosDateConfig !== 'undefined'
                        ? window.formatParts({ day: now.getDate(), month: now.getMonth() + 1, year: now.getFullYear() }, window.UrbanPosDateConfig.getFormat())
                        : todayIso;
                    this.value = todayFormatted;
                }
            }
        });

        // Form Submit Guard (Task 11)
        $('form').on('submit', function (e) {
            let supp = $('select[name="supplier_id"]').val();
            let $suppContainer = $('select[name="supplier_id"]').next('.select2-container').find('.select2-selection');
            if (!supp) {
                e.preventDefault();
                $suppContainer.addClass('border-danger');
                if (window.toastr) {
                    toastr.warning('Please select a Supplier for this purchase invoice.', 'Supplier Required');
                }
                $('select[name="supplier_id"]').select2('open');
                return false;
            } else {
                $suppContainer.removeClass('border-danger');
            }

            let $suppInvInput = $('#supplier_inv_no');
            let suppInvVal = $.trim($suppInvInput.val());
            if (!suppInvVal) {
                e.preventDefault();
                $suppInvInput.addClass('is-invalid border-danger');
                $('#supplier-inv-feedback').text('Supplier Invoice Number is required before saving.').show();
                $('#supplier-inv-feedback-container').show();
                if (window.toastr) {
                    toastr.warning('Supplier Invoice Number is required before saving.', 'Invoice Number Required');
                }
                $suppInvInput.focus();
                return false;
            }
            if ($suppInvInput.hasClass('is-invalid')) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Please resolve the Supplier Invoice Number error before saving.', 'Invoice Number Error');
                }
                $suppInvInput.focus();
                return false;
            }

            let hasError = false;
            let validRows = 0;
            $('#pinv-items-body tr').each(function (idx) {
                let id = $(this).find('.pinv-item-select').val();
                let $q = $(this).find('.pinv-qty');
                let q = parseFloat($q.val()) || 0;
                let $cost = $(this).find('.pinv-cost');
                let cost = parseFloat($cost.val()) || 0;

                if (id) {
                    if (q <= 0) {
                        $q.addClass('is-invalid border-danger');
                        if (window.toastr) {
                            toastr.warning(`Row #${idx + 1}: Quantity must be greater than 0.`, 'Invalid Quantity');
                        }
                        $q.focus();
                        hasError = true;
                        return false;
                    }
                    if (cost <= 0) {
                        $cost.addClass('is-invalid border-danger');
                        if (window.toastr) {
                            toastr.warning(`Row #${idx + 1}: Cost price must be greater than 0.`, 'Invalid Cost');
                        }
                        $cost.focus();
                        hasError = true;
                        return false;
                    }
                    let $exp = $(this).find('.pinv-exp-date');
                    let expVal = $exp.val();
                    let todayStr = getTodayIsoString();
                    if (expVal && expVal < todayStr) {
                        $exp.addClass('is-invalid border-danger');
                        let errMsg = `Row #${idx + 1}: Expiry date (${expVal}) cannot be in the past!`;
                        if (window.toastr) {
                            toastr.error(errMsg, 'Invalid Expiry Date');
                        }
                        $exp.focus();
                        hasError = true;
                        return false;
                    }
                    if ($exp.prop('required') && !expVal) {
                        $exp.addClass('is-invalid border-danger');
                        let errMsg = `Row #${idx + 1}: Expiry date is required for this item.`;
                        if (window.toastr) {
                            toastr.error(errMsg, 'Expiry Date Required');
                        }
                        $exp.focus();
                        hasError = true;
                        return false;
                    }

                    let $discPct = $(this).find('.pinv-disc-percent');
                    let discPctVal = parseFloat($discPct.val()) || 0;
                    let $discAmt = $(this).find('.pinv-disc-amount');
                    let discAmtVal = parseFloat($discAmt.val()) || 0;
                    let baseTotal = q * cost;

                    if (discPctVal < 0 || discPctVal > 100) {
                        $discPct.addClass('is-invalid border-danger');
                        if (window.toastr) {
                            toastr.warning(`Row #${idx + 1}: Discount % (${discPctVal}%) cannot exceed 100%.`, 'Invalid Discount %');
                        }
                        $discPct.focus();
                        hasError = true;
                        return false;
                    }

                    if (discAmtVal < 0 || (baseTotal > 0 && discAmtVal > baseTotal)) {
                        $discAmt.addClass('is-invalid border-danger');
                        if (window.toastr) {
                            toastr.warning(`Row #${idx + 1}: Discount amount (₹${discAmtVal}) cannot exceed item total (₹${baseTotal.toFixed(2)}).`, 'Invalid Discount Amount');
                        }
                        $discAmt.focus();
                        hasError = true;
                        return false;
                    }

                    validRows++;
                }
            });

            let $schemePct = $('input[name="scheme_item_disc_percent"]');
            let schemePctVal = parseFloat($schemePct.val()) || 0;
            if (schemePctVal < 0 || schemePctVal > 100) {
                e.preventDefault();
                $schemePct.addClass('is-invalid border-danger');
                if (window.toastr) {
                    toastr.warning('Scheme Item Discount % cannot exceed 100%.', 'Invalid Scheme Discount');
                }
                $schemePct.focus();
                return false;
            } else {
                $schemePct.removeClass('is-invalid border-danger');
            }

            if (hasError) {
                e.preventDefault();
                return false;
            }

            if (validRows === 0) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Pehle item add karein. Please add at least one valid item before saving.', 'No Items Added');
                }
                $('#pinv-items-body tr:first .pinv-item-code').focus();
                return false;
            }

            // Remove blank rows before submitting
            $('#pinv-items-body tr').each(function () {
                let id = $(this).find('.pinv-item-select').val();
                if (!id) {
                    $(this).remove();
                }
            });
        });

        // Ensure exactly ONE empty row for new item entry (Task 4)
        function ensureSingleEmptyPinvRow() {
            let $tbody = $('#pinv-items-body');
            let $emptyRows = $tbody.find('tr').filter(function () {
                let id = $(this).find('.pinv-item-select').val();
                let code = $(this).find('.pinv-item-code').val();
                return (!id || id === '') && (!code || $.trim(code) === '');
            });

            if ($emptyRows.length > 1) {
                // Keep only the last empty row, remove duplicate empty rows
                $emptyRows.slice(0, $emptyRows.length - 1).remove();
            } else if ($emptyRows.length === 0) {
                let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
                let $newRow = $(html);
                $tbody.append($newRow);
                initPinvItemSelect2($newRow.find('.pinv-item-select'));
                $newRow.find('input').attr('autocomplete', 'off');
                updateExpiryRequirement($newRow, 'Not Required', 0);
                rowIndex++;
            }
            updateRowNumbers();
            calculateTotals();
        }

        // Form Reset Button Handler: resets header form fields without deleting table items (Task 4)
        $(document).on('click', '#btn-reset-form, .btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Reset header form inputs? (Existing table items will be preserved)')) {
                // Reset Supplier & trigger associated cascades (PO list, prev invoices link, lock indicator)
                $('#supplier_id').val('').trigger('change');
                applySupplierPurchaseType(null);
                updateSupplierPrevInvoicesLink('');
                updateSupplierOpenPOs('');

                // Reset PO
                $('#purchase_order_id').val('').trigger('change.select2');

                // Reset Purchase Type & C-Form
                $('#purchase_type').val('Local').trigger('change');
                $('select[name="c_form"]').val('No Forms').trigger('change');

                // Reset Dates to today
                let now = new Date();
                let pad = n => String(n).padStart(2, '0');
                let todayParts = { day: now.getDate(), month: now.getMonth() + 1, year: now.getFullYear() };
                let todayFormatted = typeof window.formatParts === 'function' && typeof window.UrbanPosDateConfig !== 'undefined'
                    ? window.formatParts(todayParts, window.UrbanPosDateConfig.getFormat())
                    : (todayParts.year + '-' + pad(todayParts.month) + '-' + pad(todayParts.day));

                function setDateVal($input, val) {
                    if (!$input.length) return;
                    $input.val(val).trigger('change');
                    let dp = $input.data('daterangepicker');
                    if (dp && typeof moment !== 'undefined') {
                        let m = moment();
                        dp.setStartDate(m);
                        dp.setEndDate(m);
                    }
                }

                setDateVal($('#invoice_date'), todayFormatted);
                setDateVal($('#grn_date'), todayFormatted);
                setDateVal($('#supplier_inv_date'), todayFormatted);

                // Reset Supplier Inv No & Amount & Feedbacks
                $('#supplier_inv_no').val('').removeClass('is-invalid border-danger');
                $('#supplier-inv-feedback-container').hide();
                $('#supplier-inv-feedback').text('');
                $('#supplier_inv_amount').val('');
                $('#supplier-inv-amount-match-status').empty().removeClass('text-success text-danger');

                // Reset Footer & Additional Fields
                $('#freight').val('0.00');
                $('#round_off').val('0.00');
                $('#scheme_item_disc_amt').val('0.00');
                $('#scheme_item_disc_percent').val('');
                $('#other_disc_amt').val('0.00');
                $('#total_extra_cess').val('0.00');
                $('#total_weight').val('0');
                $('#tcs_amount').val('0.00');
                $('#remarks').val('');
                $('#message').val('');

                calculateTotals();

                if (window.toastr) {
                    toastr.info('Header form inputs have been reset. Existing table items are preserved.', 'Form Reset');
                }
            }
        });

        // Table Reset Button Handler: clears table and keeps exactly 1 empty row (Task 4)
        $(document).on('click', '.btn-reset-table', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to clear all items in the table?')) {
                $('#pinv-items-body').empty();
                ensureSingleEmptyPinvRow();
                calculateTotals();
                if (window.toastr) {
                    toastr.info('Table items cleared. Exactly 1 empty row ready for new entry.', 'Table Reset');
                }
            }
        });

        // Run on initial load to ensure exactly 1 empty row if no items
        ensureSingleEmptyPinvRow();
    });
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
<script>
    $(document).ready(function () {
        if (window.initTransactionCompactLayout) {
            window.initTransactionCompactLayout({
                containerSelector: '.tx-items-scroll-container',
                footerSelector: '.tx-rich-footer',
                tableSelector: '#pinv-items-table',
                minHeight: 180
            });
        }
    });
</script>
@endpush
