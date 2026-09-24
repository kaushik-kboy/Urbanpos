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
    <form action="{{ route('purchase.purchase-receipt-notes.store') }}" method="POST" id="grn-form">
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
                        $selectedBranch = old('branch_id', $sourceOrder->branch_id ?? session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
                    @endphp
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="branch_id" data-label="Branch" data-default-order="2" data-core="1">
                        <label class="font-weight-bold">Active Branch <span class="badge badge-light border ml-1 font-weight-normal text-muted">Top Navbar</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control font-weight-bold bg-light text-dark" readonly tabindex="-1" value="{{ $branches[$selectedBranch] ?? 'Active Branch' }}">
                            <input type="hidden" name="branch_id" value="{{ $selectedBranch }}">
                            <div class="input-group-append">
                                <span class="input-group-text bg-light text-primary" title="Branch is selected globally from top navbar"><i class="fas fa-lock"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="supplier_id" data-label="Supplier" data-default-order="3" data-core="1">
                        <label class="font-weight-bold">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control select2" required>
                            <option value="">-- Select Supplier --</option>
                            @foreach ($suppliers as $id => $name)
                                <option value="{{ $id }}" {{ old('supplier_id', $sourceOrder->supplier_id ?? '') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="purchase_order_id" data-label="Purchase Order (Ref)" data-default-order="4">
                        <label class="font-weight-bold">Purchase Order (Ref)</label>
                        <select name="purchase_order_id" class="form-control select2" id="grn-po-select">
                            <option value="">-- Direct Receipt (No PO) --</option>
                            @foreach ($purchaseOrders as $id => $poNumber)
                                <option value="{{ $id }}" {{ old('purchase_order_id', $sourceOrder->id ?? '') == $id ? 'selected' : '' }}>
                                    {{ $poNumber }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="supplier_challan_no" data-label="Supplier Challan / DC No" data-default-order="5">
                        <label class="font-weight-bold">Supplier Challan / DC No</label>
                        <input type="text" name="supplier_challan_no" class="form-control" placeholder="e.g. DC-9842" value="{{ old('supplier_challan_no') }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="supplier_challan_date" data-label="Challan Date" data-default-order="6">
                        <label class="font-weight-bold">Challan Date</label>
                        <input type="date" name="supplier_challan_date" class="form-control" value="{{ old('supplier_challan_date', date('Y-m-d')) }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="vehicle_no" data-label="Vehicle No" data-default-order="7">
                        <label class="font-weight-bold">Vehicle No</label>
                        <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. GJ-01-AB-1234" value="{{ old('vehicle_no') }}">
                    </div>
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="transporter_name" data-label="Transporter Name" data-default-order="8">
                        <label class="font-weight-bold">Transporter Name</label>
                        <input type="text" name="transporter_name" class="form-control" placeholder="e.g. SafeXpress" value="{{ old('transporter_name') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-default shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold"><i class="fas fa-boxes mr-1"></i> Inward Goods & Inspection Grid</h3>
                @php
                    $grnItemColumns = [
                        'seq'        => ['label' => '#', 'default' => true],
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
                            <th style="min-width: 250px;" data-col-key="item">Item Description</th>
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
                            @endphp
                            <tr class="grn-item-row" data-index="{{ $idx }}">
                                <td class="text-center align-middle row-number" data-col-key="seq">{{ $idx + 1 }}</td>
                                <td data-col-key="item">
                                    <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $poItemId }}">
                                    <select name="items[{{ $idx }}][item_id]" class="form-control form-control-sm select2 item-select" required>
                                        <option value="">-- Select Item --</option>
                                        @foreach ($items as $itm)
                                            <option value="{{ $itm->id }}" data-cost="{{ $itm->cost_price }}" data-mrp="{{ $itm->mrp }}" {{ $itemId == $itm->id ? 'selected' : '' }}>
                                                {{ $itm->item_code ? '['.$itm->item_code.'] ' : '' }}{{ $itm->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-col-key="ordered">
                                    <input type="number" step="0.001" name="items[{{ $idx }}][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="{{ $ordered }}" readonly tabindex="-1">
                                </td>
                                <td data-col-key="received">
                                    <input type="number" step="0.001" min="0" name="items[{{ $idx }}][received_qty]" class="form-control form-control-sm text-right font-weight-bold row-received" value="{{ $received }}" placeholder="0.00" required>
                                </td>
                                <td data-col-key="accepted">
                                    <input type="number" step="0.001" min="0" name="items[{{ $idx }}][accepted_qty]" class="form-control form-control-sm text-right font-weight-bold text-success row-accepted" value="{{ $accepted }}" placeholder="0.00" required>
                                </td>
                                <td data-col-key="rejected">
                                    <input type="number" step="0.001" min="0" name="items[{{ $idx }}][rejected_qty]" class="form-control form-control-sm text-right text-danger row-rejected" value="{{ $rejected }}" readonly tabindex="-1">
                                </td>
                                <td data-col-key="cost">
                                    <input type="number" step="0.0001" min="0" name="items[{{ $idx }}][unit_cost]" class="form-control form-control-sm text-right row-cost" value="{{ $cost }}" placeholder="0.00" required>
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
                            <th colspan="2" class="text-right align-middle">Totals:</th>
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
            <td data-col-key="item">
                <input type="hidden" name="items[__INDEX__][purchase_order_item_id]" value="">
                <select name="items[__INDEX__][item_id]" class="form-control form-control-sm item-select" required>
                    <option value="">-- Select Item --</option>
                    @foreach ($items as $itm)
                        <option value="{{ $itm->id }}" data-cost="{{ $itm->cost_price }}" data-mrp="{{ $itm->mrp }}">
                            {{ $itm->item_code ? '['.$itm->item_code.'] ' : '' }}{{ $itm->name }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td data-col-key="ordered">
                <input type="number" step="0.001" name="items[__INDEX__][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="0" readonly tabindex="-1">
            </td>
            <td data-col-key="received">
                <input type="number" step="0.001" min="0" name="items[__INDEX__][received_qty]" class="form-control form-control-sm text-right font-weight-bold row-received" placeholder="0.00" required>
            </td>
            <td data-col-key="accepted">
                <input type="number" step="0.001" min="0" name="items[__INDEX__][accepted_qty]" class="form-control form-control-sm text-right font-weight-bold text-success row-accepted" placeholder="0.00" required>
            </td>
            <td data-col-key="rejected">
                <input type="number" step="0.001" min="0" name="items[__INDEX__][rejected_qty]" class="form-control form-control-sm text-right text-danger row-rejected" value="0" readonly tabindex="-1">
            </td>
            <td data-col-key="cost">
                <input type="number" step="0.0001" min="0" name="items[__INDEX__][unit_cost]" class="form-control form-control-sm text-right row-cost" placeholder="0.00" required>
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
@stop

@push('js')
<script>
    $(function () {
        $('.select2').select2({ width: '100%' });

        let rowIndex = {{ max(count($rowsToRender), 1) }};

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

        // Item selection auto-fills cost & mrp
        $('#grn-items-body').on('change', '.item-select', function () {
            let $row = $(this).closest('tr');
            let $selected = $(this).find('option:selected');
            let cost = $selected.data('cost') || 0;
            let mrp = $selected.data('mrp') || 0;

            if (!$row.find('.row-cost').val()) {
                $row.find('.row-cost').val(cost);
            }
            if (!$row.find('.row-mrp').val()) {
                $row.find('.row-mrp').val(mrp);
            }
            recalculate();
        });

        // Tab on MRP on last row adds a new row automatically
        $('#grn-items-body').on('keydown', '.row-mrp', function (e) {
            if (e.key === 'Tab' && !e.shiftKey) {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if (!$nextRow.length) {
                    e.preventDefault();
                    $('#add-row-btn').trigger('click');
                    let $newRow = $('#grn-items-body tr.grn-item-row:last');
                    $newRow.find('.item-select').select2('open');
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
            $newRow.find('.item-select').select2({ width: '100%' });
            rowIndex++;
            recalculate();
        });

        // Remove row
        $('#grn-items-body').on('click', '.remove-row-btn', function () {
            if ($('#grn-items-body tr').length > 1) {
                $(this).closest('tr').remove();
                recalculate();
            } else {
                alert('At least one item row is required.');
            }
        });

        // If PO changed dynamically in dropdown, reload with from_po
        $('#grn-po-select').on('change', function () {
            let poId = $(this).val();
            if (poId && confirm('Load items and details from this Purchase Order? Any unsaved changes will be replaced.')) {
                window.location.href = "{{ route('purchase.purchase-receipt-notes.create') }}?from_po=" + poId;
            }
        });

        recalculate();
    });
</script>
@endpush
