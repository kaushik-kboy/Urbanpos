@extends('adminlte::page')

@section('title', 'Create Goods Receipt Note (GRN)')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Create Goods Receipt Note (GRN)</h1>
        <a href="{{ route('purchase.purchase-receipt-notes.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>
@stop

@section('content')
    <form action="{{ route('purchase.purchase-receipt-notes.store') }}" method="POST" id="grn-form" novalidate>
        @csrf

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle mr-1"></i> Please fix the errors below:</h5>
                <ul class="mb-0 pl-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($sourceOrder))
            <div class="alert alert-info py-2 mb-3">
                <i class="fas fa-link mr-1"></i> Creating Goods Receipt Note against Purchase Order: <strong>{{ $sourceOrder->po_number }}</strong>
                (Supplier: {{ $sourceOrder->supplier?->name }}, Branch: {{ $sourceOrder->branch?->name }}).
            </div>
        @endif

        <div class="card card-primary card-outline shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-truck-loading mr-1"></i> Receipt & Challan Details</h3>
                <x-form-layout-customizer
                    form-key="receipt_notes.header"
                    container-id="grn-header-fields-grid"
                    title="Customize Receipt Note Header"
                />
            </div>
            <div class="card-body p-3">
                <div class="row g-2 form-fields-grid" id="grn-header-fields-grid">
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="receipt_date" data-label="Receipt Date" data-default-order="1" data-core="1">
                        <label class="font-weight-bold">Receipt Date <span class="text-danger">*</span></label>
                        <input type="date" name="receipt_date" class="form-control" value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                    </div>
                    @php
                    $grnItemColumns = [
                        'seq'        => ['label' => '#', 'default' => true],
                        'code'       => ['label' => 'Code / Barcode', 'default' => true],
                        'item'       => ['label' => 'Item Description', 'default' => true],
                        'ordered'    => ['label' => 'Ordered', 'default' => true],
                        'received'   => ['label' => 'Received', 'default' => true],
                        'accepted'   => ['label' => 'Accepted', 'default' => true],
                        'rejected'   => ['label' => 'Rejected', 'default' => true],
                        'cost'       => ['label' => 'Unit Cost', 'default' => true],
                        'mrp'        => ['label' => 'MRP', 'default' => true],
                        'batch'      => ['label' => 'Batch No', 'default' => true],
                        'exp_date'   => ['label' => 'Expiry Date', 'default' => true],
                        'line_total' => ['label' => 'Line Total', 'default' => true],
                        'actions'    => ['label' => 'Actions', 'default' => true],
                    ];
                @endphp
                <div class="d-flex align-items-center">
                    <x-table-column-customizer
                        table-key="purchase.receipt-notes.items"
                        table-id="grn-items-table"
                        :columns="$grnItemColumns"
                    />
                    <button type="button" class="btn btn-xs btn-primary ml-2" id="add-row-btn">
                        <i class="fas fa-plus mr-1"></i> Add Item Line
                    </button>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-sm mb-0 table-items-dense" id="grn-items-table">
                    <thead class="thead-light">
                        <tr class="text-center">
                            <th style="width: 40px;" data-col-key="seq">#</th>
                            <th style="width: 140px;" data-col-key="code">Code / Barcode</th>
                            <th style="min-width: 230px;" data-col-key="item">Item Description</th>
                            <th style="width: 100px;" data-col-key="ordered">Ordered</th>
                            <th style="width: 110px;" data-col-key="received">Received <span class="text-danger">*</span></th>
                            <th style="width: 110px;" data-col-key="accepted">Accepted <span class="text-danger">*</span></th>
                            <th style="width: 90px;" data-col-key="rejected">Rejected</th>
                            <th style="width: 110px;" data-col-key="cost">Unit Cost (₹)</th>
                            <th style="width: 100px;" data-col-key="mrp">MRP (₹)</th>
                            <th style="width: 110px;" data-col-key="batch">Batch No</th>
                            <th style="width: 130px;" data-col-key="exp_date">Expiry Date</th>
                            <th style="width: 120px;" class="text-right" data-col-key="line_total">Line Total</th>
                            <th style="width: 40px;" data-col-key="actions"></th>
                        </tr>
                    </thead>
                    <tbody id="grn-items-body">
                        @php
                            $oldRows = old('items');
                            $rowsToRender = !empty($oldRows) ? collect($oldRows) : (!empty($convertedItems) && $convertedItems->isNotEmpty() ? $convertedItems : collect([null]));
                        @endphp

                        @foreach ($rowsToRender as $idx => $row)
                            @php
                                $r = is_array($row) ? (object) $row : $row;
                                $itemId = $r->item_id ?? '';
                                $poItemId = $r->purchase_order_item_id ?? '';
                                $ordered = $r->ordered_qty ?? 0;
                                $received = $r->received_qty ?? '';
                                $accepted = $r->accepted_qty ?? '';
                                $rejected = $r->rejected_qty ?? 0;
                                $cost = $r->unit_cost ?? 0;
                                $mrp = $r->mrp ?? 0;
                                $batch = $r->batch_no ?? '';
                                $exp = $r->exp_date ?? '';

                                $matchedItem = null;
                                if ($itemId && isset($items)) {
                                    $matchedItem = $items->firstWhere('id', $itemId);
                                } elseif (!empty($r->item)) {
                                    $matchedItem = $r->item;
                                }
                                $codeVal = $matchedItem ? ($matchedItem->item_code ?: ($matchedItem->ean_upc_code ?: '')) : '';
                                $nameVal = $matchedItem ? ($matchedItem->name . ($codeVal ? ' ['.$codeVal.']' : '')) : '';
                            @endphp
                            <tr class="grn-item-row" data-index="{{ $idx }}">
                                <td class="text-center align-middle row-number" data-col-key="seq">{{ $idx + 1 }}</td>
                                <td data-col-key="code" style="min-width: 130px;">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm prn-item-code font-weight-bold" value="{{ $codeVal }}" placeholder="Scan/Code" autocomplete="off" title="Enter or F2 to search item">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm prn-btn-search" title="Search Items Popup (F2)" tabindex="-1">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                <td data-col-key="item">
                                    <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $poItemId }}">
                                    <input type="hidden" name="items[{{ $idx }}][item_id]" class="prn-item-id item-select" value="{{ $itemId }}">
                                    <input type="text" class="form-control form-control-sm prn-item-desc bg-light font-weight-bold text-truncate" readonly tabindex="-1" value="{{ $nameVal }}" placeholder="Product Description (auto-filled)">
                                </td>
                                <td data-col-key="ordered">
                                    <input type="number" step="0.001" name="items[{{ $idx }}][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="{{ $ordered }}" readonly tabindex="-1">
                                </td>
                                <td data-col-key="received">
                                    <input type="number" step="0.001" min="0" name="items[{{ $idx }}][received_qty]" class="form-control form-control-sm text-right font-weight-bold row-received" value="{{ $received }}" placeholder="0.00">
                                </td>
                                <td data-col-key="accepted">
                                    <input type="number" step="0.001" min="0" name="items[{{ $idx }}][accepted_qty]" class="form-control form-control-sm text-right font-weight-bold text-success row-accepted" value="{{ $accepted }}" placeholder="0.00">
                                </td>
                                <td data-col-key="rejected">
                                    <input type="number" step="0.001" min="0" name="items[{{ $idx }}][rejected_qty]" class="form-control form-control-sm text-right text-danger row-rejected" value="{{ $rejected }}" readonly tabindex="-1">
                                </td>
                                <td data-col-key="cost">
                                    <input type="number" step="0.0001" min="0" name="items[{{ $idx }}][unit_cost]" class="form-control form-control-sm text-right row-cost" value="{{ $cost }}" placeholder="0.00">
                                </td>
                                <td data-col-key="mrp">
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][mrp]" class="form-control form-control-sm text-right row-mrp" value="{{ $mrp }}" placeholder="0.00">
                                </td>
                                <td data-col-key="batch">
                                    <input type="text" name="items[{{ $idx }}][batch_no]" class="form-control form-control-sm" value="{{ $batch }}" placeholder="Batch">
                                </td>
                                <td data-col-key="exp_date">
                                    <input type="date" name="items[{{ $idx }}][exp_date]" class="form-control form-control-sm" value="{{ $exp }}">
                                </td>
                                <td class="text-right align-middle font-weight-bold text-primary row-total" data-col-key="line_total">₹0.00</td>
                                <td class="text-center align-middle" data-col-key="actions">
                                    <button type="button" class="btn btn-xs btn-outline-danger remove-row-btn" title="Remove line"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <th colspan="3" class="text-right align-middle">Totals:</th>
                            <th class="text-right align-middle" id="summary-ordered">0.00</th>
                            <th class="text-right align-middle" id="summary-received">0.00</th>
                            <th class="text-right align-middle text-success" id="summary-accepted">0.00</th>
                            <th class="text-right align-middle text-danger" id="summary-rejected">0.00</th>
                            <th colspan="4" class="text-right align-middle">Total Goods Value:</th>
                            <th class="text-right align-middle text-primary h6 mb-0" id="summary-amount">₹0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <x-custom-fields-renderer :module="'PurchaseReceiptNote'" :model="null" :cardStyle="true" />

        <div class="card card-default shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-md-8">
                        <label class="font-weight-bold">Inspection / Receiver Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Notes on packaging condition, delivery discrepancies, or receiving notes...">{{ old('remarks') }}</textarea>
                    </div>
                    <div class="col-md-4 d-flex flex-column justify-content-end text-right">
                        <div class="small text-muted mb-2">Inventory will be received into the selected branch upon saving.</div>
                        <div>
                            <a href="{{ route('purchase.purchase-receipt-notes.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                            <button type="submit" class="btn btn-success px-4 font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Save & Inward Goods</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Template for dynamic rows --}}
    <template id="row-template">
        <tr class="grn-item-row" data-index="__INDEX__">
            <td class="text-center align-middle row-number" data-col-key="seq">__NUMBER__</td>
            <td data-col-key="code" style="min-width: 130px;">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm prn-item-code font-weight-bold" placeholder="Scan/Code" autocomplete="off" title="Enter or F2 to search item">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary btn-sm prn-btn-search" title="Search Items Popup (F2)" tabindex="-1">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </td>
            <td data-col-key="item">
                <input type="hidden" name="items[__INDEX__][purchase_order_item_id]" value="">
                <input type="hidden" name="items[__INDEX__][item_id]" class="prn-item-id item-select" value="">
                <input type="text" class="form-control form-control-sm prn-item-desc bg-light font-weight-bold text-truncate" readonly tabindex="-1" placeholder="Product Description (auto-filled)">
            </td>
            <td data-col-key="ordered">
                <input type="number" step="0.001" name="items[__INDEX__][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="0" readonly tabindex="-1">
            </td>
            <td data-col-key="received">
                <input type="number" step="0.001" min="0" name="items[__INDEX__][received_qty]" class="form-control form-control-sm text-right font-weight-bold row-received" placeholder="0.00">
            </td>
            <td data-col-key="accepted">
                <input type="number" step="0.001" min="0" name="items[__INDEX__][accepted_qty]" class="form-control form-control-sm text-right font-weight-bold text-success row-accepted" placeholder="0.00">
            </td>
            <td data-col-key="rejected">
                <input type="number" step="0.001" min="0" name="items[__INDEX__][rejected_qty]" class="form-control form-control-sm text-right text-danger row-rejected" value="0" readonly tabindex="-1">
            </td>
            <td data-col-key="cost">
                <input type="number" step="0.0001" min="0" name="items[__INDEX__][unit_cost]" class="form-control form-control-sm text-right row-cost" placeholder="0.00">
            </td>
            <td data-col-key="mrp">
                <input type="number" step="0.01" min="0" name="items[__INDEX__][mrp]" class="form-control form-control-sm text-right row-mrp" placeholder="0.00">
            </td>
            <td data-col-key="batch">
                <input type="text" name="items[__INDEX__][batch_no]" class="form-control form-control-sm" placeholder="Batch">
            </td>
            <td data-col-key="exp_date">
                <input type="date" name="items[__INDEX__][exp_date]" class="form-control form-control-sm">
            </td>
            <td class="text-right align-middle font-weight-bold text-primary row-total" data-col-key="line_total">₹0.00</td>
            <td class="text-center align-middle" data-col-key="actions">
                <button type="button" class="btn btn-xs btn-outline-danger remove-row-btn" title="Remove line"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    </template>

    <!-- ============================================================
         ITEM SEARCH MODAL — opens on Code/Barcode field Enter / F2
         ============================================================ -->
    <div class="modal fade" id="prn-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="prnItemSearchLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title" id="prnItemSearchLabel">
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
                                <input type="text" id="prn-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                </div>
                                <input type="text" id="prn-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                </div>
                                <input type="text" id="prn-isl-filter-expiry" class="form-control" placeholder="Filter expiry…" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-2 text-right">
                            <button type="button" id="prn-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times mr-1"></i>Clear
                            </button>
                        </div>
                    </div>

                    <!-- Loading / No-results / Hint states -->
                    <div id="prn-isl-loading" class="text-center py-4 d-none">
                        <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Loading items…</p>
                    </div>
                    <div id="prn-isl-no-results" class="text-center py-4 d-none">
                        <i class="fas fa-inbox fa-2x text-muted"></i>
                        <p class="mt-2 text-muted">No items found.</p>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive" id="prn-isl-table-wrap">
                        <table class="table table-sm table-bordered table-hover mb-0" id="prn-isl-items-table">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th class="text-center" style="width: 40px;">#</th>
                                    <th>Product Name</th>
                                    <th class="text-center" style="width: 120px;">Code</th>
                                    <th class="text-right" style="width: 95px;">Cost Price</th>
                                    <th class="text-right" style="width: 95px;">Sell Price</th>
                                    <th class="text-right" style="width: 90px;">MRP</th>
                                    <th class="text-right" style="width: 85px;">Stock</th>
                                    <th class="text-center" style="width: 120px;">Expiry / Batch</th>
                                    <th class="text-center" style="width: 80px;">Select</th>
                                </tr>
                            </thead>
                            <tbody id="prn-isl-items-body">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted mt-2 d-block" id="prn-isl-count-label"></small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
    $(function () {
        $('.select2').select2({ width: '100%' });

        let rowIndex = {{ max(count($rowsToRender), 1) }};
        const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';
        let islDebounce = null;
        let islCache = {};
        let prnModalOpen = false;
        let prnModalClosing = false;
        let prnActiveSearchRow = null;
        let prnCancellingRow = null;
        let prnItemSelectedInModal = false;
        let islSelectedIdx = -1;

        function recalculate() {
            let totOrdered = 0;
            let totReceived = 0;
            let totAccepted = 0;
            let totRejected = 0;
            let totAmount = 0;

            $('#grn-items-body tr.grn-item-row').each(function (idx) {
                $(this).find('.row-number').text(idx + 1);

                let ordered = parseFloat($(this).find('.row-ordered').val()) || 0;
                let received = parseFloat($(this).find('.row-received').val()) || 0;
                let accepted = parseFloat($(this).find('.row-accepted').val()) || 0;
                let cost = parseFloat($(this).find('.row-cost').val()) || 0;

                // Auto compute rejected if received > accepted
                let rejected = Math.max(0, received - accepted);
                $(this).find('.row-rejected').val(rejected.toFixed(2));

                let lineTotal = accepted * cost;
                $(this).find('.row-total').text('₹' + lineTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                totOrdered += ordered;
                totReceived += received;
                totAccepted += accepted;
                totRejected += rejected;
                totAmount += lineTotal;
            });

            $('#summary-ordered').text(totOrdered.toFixed(2));
            $('#summary-received').text(totReceived.toFixed(2));
            $('#summary-accepted').text(totAccepted.toFixed(2));
            $('#summary-rejected').text(totRejected.toFixed(2));
            $('#summary-amount').text('₹' + totAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }

        // On received change, if accepted is empty, default accepted = received
        $('#grn-items-body').on('input', '.row-received', function () {
            let $row = $(this).closest('tr');
            let rec = $(this).val();
            let $acc = $row.find('.row-accepted');
            if ($acc.val() === '' || parseFloat($acc.val()) === 0 || parseFloat($acc.val()) > parseFloat(rec)) {
                $acc.val(rec);
            }
            recalculate();
        });

        $('#grn-items-body').on('input', '.row-accepted, .row-cost', function () {
            recalculate();
        });

        /* ================================================================
           ITEM SEARCH MODAL LOGIC (Arrow Up/Down, Enter selection, Tab on MRP)
           ================================================================ */
        function openPrnModal($row, initialQuery) {
            prnActiveSearchRow = $row;
            let prefill = $.trim(initialQuery || '');
            $('#prn-isl-filter-name').val(prefill);
            $('#prn-isl-filter-code').val('');
            $('#prn-isl-filter-expiry').val('');
            fetchPrnItemList();
            prnModalOpen = true;
            $('#prn-item-search-modal').modal('show');
            $('#prn-item-search-modal').one('shown.bs.modal', function () {
                $('#prn-isl-filter-name').focus().select();
                if (prefill) fetchPrnItemList();
            });
        }

        // Open modal on Code/Barcode field: Keydown Enter/F2 ONLY — Mouse Click & Focus disabled
        $(document).off('click focus keydown', '.prn-item-code').on('keydown', '.prn-item-code', function (e) {
            if (e.key !== 'Enter' && e.key !== 'F2') return;
            e.preventDefault();
            if (prnModalOpen || prnModalClosing) return;
            openPrnModal($(this).closest('tr'), $(this).val());
        });

        $(document).on('click', '.prn-btn-search', function (e) {
            e.preventDefault();
            if (prnModalOpen || prnModalClosing) return;
            openPrnModal($(this).closest('tr'), $(this).closest('tr').find('.prn-item-code').val());
        });

        $('#prn-item-search-modal').on('show.bs.modal', function () {
            prnModalOpen = true;
            prnModalClosing = false;
            prnItemSelectedInModal = false;
            prnCancellingRow = null;
        });

        $('#prn-item-search-modal').on('hide.bs.modal', function () {
            prnModalOpen = false;
            prnModalClosing = true;
            if (!prnItemSelectedInModal && prnActiveSearchRow && prnActiveSearchRow.length) {
                let selectedId = prnActiveSearchRow.find('.prn-item-id').val();
                if (!selectedId) {
                    prnCancellingRow = prnActiveSearchRow;
                }
            }
        });

        $('#prn-item-search-modal').on('hidden.bs.modal', function () {
            prnModalOpen = false;
            prnModalClosing = true;
            setTimeout(function () { prnModalClosing = false; }, 350);

            if (!prnItemSelectedInModal && prnCancellingRow && prnCancellingRow.length) {
                let totalRows = $('#grn-items-body tr.grn-item-row').length;
                if (totalRows > 1) {
                    prnCancellingRow.remove();
                    recalculate();
                } else {
                    prnCancellingRow.find('.prn-item-code').val('');
                    prnCancellingRow.find('.prn-item-desc').val('');
                }
                prnCancellingRow = null;
                prnActiveSearchRow = null;
                return;
            }
            prnActiveSearchRow = null;
        });

        $('#prn-isl-filter-name, #prn-isl-filter-code, #prn-isl-filter-expiry').on('input', function () {
            clearTimeout(islDebounce);
            islDebounce = setTimeout(fetchPrnItemList, 350);
        });

        $('#prn-isl-btn-clear').on('click', function () {
            $('#prn-isl-filter-name, #prn-isl-filter-code, #prn-isl-filter-expiry').val('');
            fetchPrnItemList();
        });

        function fetchPrnItemList() {
            let branchId = $('[name="branch_id"]').val() || '';
            let srch = $.trim($('#prn-isl-filter-name').val());
            let code = $.trim($('#prn-isl-filter-code').val());
            let expiry = $.trim($('#prn-isl-filter-expiry').val());

            if (!srch && !code && !expiry) {
                $('#prn-isl-loading').addClass('d-none');
                $('#prn-isl-table-wrap').addClass('d-none');
                $('#prn-isl-items-body').empty();
                $('#prn-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-search fa-2x text-muted"></i><p class="mt-2 text-muted">Type product name, code or barcode to search…</p>'
                );
                $('#prn-isl-count-label').text('');
                islSelectedIdx = -1;
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;
            if (islCache[cacheKey]) {
                renderPrnItems(islCache[cacheKey]);
                return;
            }

            $('#prn-isl-loading').removeClass('d-none');
            $('#prn-isl-no-results').addClass('d-none');
            $('#prn-isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, { branch_id: branchId, search: srch, code: code, expiry: expiry }, function (res) {
                $('#prn-isl-loading').addClass('d-none');
                let items = res.items || [];
                islCache[cacheKey] = items;
                renderPrnItems(items);
            }).fail(function () {
                $('#prn-isl-loading').addClass('d-none');
                $('#prn-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-exclamation-triangle fa-2x text-danger"></i><p class="mt-2 text-danger">Error loading items.</p>'
                );
            });
        }

        function renderPrnItems(items) {
            let $tbody = $('#prn-isl-items-body').empty();

            if (items.length === 0) {
                $('#prn-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i><p class="mt-2 text-muted">No items found.</p>'
                );
                $('#prn-isl-count-label').text('');
                islSelectedIdx = -1;
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let codeBadge = it.code ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>` : '—';
                let costDisplay = it.cost_price > 0 ? '₹' + parseFloat(it.cost_price).toFixed(2) : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';
                let qtyClass = it.qty <= 0 ? 'text-danger' : 'text-primary font-weight-bold';

                html += `
                    <tr class="prn-isl-item-row" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code || ''}"
                        data-name="${it.name}"
                        data-cost="${it.cost_price || 0}"
                        data-sell="${it.sell_price || 0}"
                        data-mrp="${it.mrp || 0}"
                        data-stock="${it.qty || 0}">
                        <td class="align-middle text-center text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-right">${costDisplay}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-right ${qtyClass}">${parseFloat(it.qty || 0).toFixed(2)}</td>
                        <td class="align-middle text-center text-muted">—</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 prn-isl-btn-select">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });

            $tbody.html(html);
            $('#prn-isl-table-wrap').removeClass('d-none');
            $('#prn-isl-count-label').text(items.length + ' item(s) found');
            islSelectedIdx = items.length > 0 ? 0 : -1;
            updatePrnModalHighlight();
        }

        function updatePrnModalHighlight() {
            let $rows = $('#prn-isl-items-body tr.prn-isl-item-row');
            $rows.removeClass('table-primary');
            if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                let $target = $rows.eq(islSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#prn-isl-table-wrap')[0];
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

        $('#prn-isl-filter-name, #prn-isl-filter-code, #prn-isl-filter-expiry').on('keydown', function (e) {
            let $rows = $('#prn-isl-items-body tr.prn-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                islSelectedIdx = Math.min(islSelectedIdx + 1, $rows.length - 1);
                updatePrnModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                islSelectedIdx = Math.max(islSelectedIdx - 1, 0);
                updatePrnModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                    $rows.eq(islSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });

        // Selecting an item from modal
        $(document).on('click', '.prn-isl-item-row, .prn-isl-btn-select', function (e) {
            e.stopPropagation();
            let $tr = $(this).hasClass('prn-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemData = {
                id: $tr.data('id'),
                name: $tr.data('name'),
                code: $tr.data('code'),
                cost: $tr.data('cost'),
                sell: $tr.data('sell'),
                mrp: $tr.data('mrp')
            };

            if (!prnActiveSearchRow || !itemData.id) return;
            prnItemSelectedInModal = true;
            prnCancellingRow = null;

            let $row = prnActiveSearchRow;
            $row.find('.prn-item-code').val(itemData.code || '');
            $row.find('.prn-item-desc').val(itemData.name + (itemData.code ? ' [' + itemData.code + ']' : ''));
            $row.find('.prn-item-id').val(itemData.id);

            if (parseFloat(itemData.cost) > 0) {
                $row.find('.row-cost').val(parseFloat(itemData.cost).toFixed(2));
            }
            if (parseFloat(itemData.mrp) > 0) {
                $row.find('.row-mrp').val(parseFloat(itemData.mrp).toFixed(2));
            }

            $('#prn-item-search-modal').modal('hide');
            recalculate();
            setTimeout(function () {
                $row.find('.row-received').focus().select();
            }, 60);
        });

        // Tab on MRP on last row adds a new row automatically and opens the search modal
        $(document).on('keydown', '.row-mrp', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr.grn-item-row');
                if (!$nextRow.length) {
                    e.preventDefault();
                    $('#add-row-btn').trigger('click');
                    let $newRow = $('#grn-items-body tr.grn-item-row:last');
                    setTimeout(function () {
                        $newRow.find('.prn-item-code').focus();
                        openPrnModal($newRow, '');
                    }, 60);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    $nextRow.find('.prn-item-code').focus();
                }
            }
        });

        // Add row
        $('#add-row-btn').on('click', function () {
            let template = $('#row-template').html();
            template = template.replace(/__INDEX__/g, rowIndex);
            template = template.replace(/__NUMBER__/g, $('#grn-items-body tr').length + 1);

            let $newRow = $(template);
            $('#grn-items-body').append($newRow);
            rowIndex++;
            recalculate();
        });

        // Remove row
        $('#grn-items-body').on('click', '.remove-row-btn', function () {
            if ($('#grn-items-body tr').length > 1) {
                $(this).closest('tr').remove();
                recalculate();
            } else {
                let $row = $(this).closest('tr');
                $row.find('.prn-item-code').val('');
                $row.find('.prn-item-desc').val('');
                $row.find('.prn-item-id').val('');
                $row.find('.row-received, .row-accepted, .row-rejected, .row-cost, .row-mrp').val('');
                recalculate();
            }
        });

        // If PO changed dynamically in dropdown, reload with from_po
        $('#grn-po-select').on('change', function () {
            let poId = $(this).val();
            if (poId && confirm('Load items and details from this Purchase Order? Any unsaved changes will be replaced.')) {
                window.location.href = "{{ route('purchase.purchase-receipt-notes.create') }}?from_po=" + poId;
            }
        });

        // Form Submit Validation
        $('#grn-form').on('submit', function (e) {
            let supplierId = $('select[name="supplier_id"]').val();
            if (!supplierId) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Please select a Supplier first.', 'Supplier Required');
                } else {
                    alert('Please select a Supplier first.');
                }
                $('select[name="supplier_id"]').select2('open');
                return false;
            }

            let validCount = 0;
            let hasError = false;

            $('#grn-items-body .grn-item-row').each(function () {
                let $row = $(this);
                let itemId = $row.find('.prn-item-id').val();
                let itemName = $row.find('.prn-item-desc').val() || 'Selected Item';
                let $recvInput = $row.find('.row-received');
                let recvQty = parseFloat($recvInput.val()) || 0;

                if (!itemId) {
                    return; // blank row
                }

                validCount++;
                if (recvQty <= 0) {
                    e.preventDefault();
                    if (window.toastr) {
                        toastr.warning(`Please enter received quantity for: "${itemName}"`, 'Quantity Required');
                    } else {
                        alert(`Please enter received quantity for: "${itemName}"`);
                    }
                    $recvInput.focus().select();
                    hasError = true;
                    return false;
                }
            });

            if (hasError) return false;

            if (validCount === 0) {
                e.preventDefault();
                if (window.toastr) {
                    toastr.warning('Pehle item add karein. Please add at least one item.', 'No Items Added');
                } else {
                    alert('Pehle item add karein. Please add at least one item.');
                }
                $('#grn-items-body .grn-item-row:first .prn-item-code').focus();
                return false;
            }

            // Remove blank rows before submitting
            $('#grn-items-body .grn-item-row').each(function () {
                let itemId = $(this).find('.prn-item-id').val();
                if (!itemId) {
                    $(this).remove();
                }
            });

            // Re-index remaining rows so items[0], items[1] are contiguous
            $('#grn-items-body .grn-item-row').each(function (idx) {
                $(this).find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.indexOf('items[') !== -1) {
                        $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                    }
                });
            });
        });

        recalculate();
    });
</script>
@endpush
