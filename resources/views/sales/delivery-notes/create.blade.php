@extends('adminlte::page')

@section('title', 'Create Sales Delivery Note')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold"><i class="fas fa-truck-loading text-primary mr-2"></i>Create Delivery Note (Challan)</h1>
            <small class="text-muted">Outward goods dispatch from warehouse</small>
        </div>
        <a href="{{ route('sales.delivery-notes.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>
@stop

@section('content')
    <form action="{{ route('sales.delivery-notes.store') }}" method="POST" id="sdn-form">
        @csrf

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
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
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="fas fa-link mr-1"></i> Dispatching against Sales Order: <strong>{{ $sourceOrder->order_number }}</strong>
                (Customer: {{ $sourceOrder->customer?->name }}, Branch: {{ $sourceOrder->branch?->name }}).
            </div>
        @endif

        <div class="card card-primary card-outline shadow-sm mb-3">
            <div class="card-header bg-light py-2">
                <h3 class="card-title font-weight-bold"><i class="fas fa-shipping-fast mr-1"></i> Dispatch & Transport Details</h3>
            </div>
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Dispatch Date <span class="text-danger">*</span></label>
                        <input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-control select2" required>
                            <option value="">-- Select Branch --</option>
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" {{ old('branch_id', $sourceOrder->branch_id ?? session('active_branch_id')) == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control select2" required>
                            <option value="">-- Select Customer --</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" {{ old('customer_id', $sourceOrder->customer_id ?? '') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Sales Order (Ref)</label>
                        <select name="sales_order_id" class="form-control select2" id="sdn-so-select">
                            <option value="">-- Direct Dispatch (No SO) --</option>
                            @foreach ($salesOrders as $id => $soNumber)
                                <option value="{{ $id }}" {{ old('sales_order_id', $sourceOrder->id ?? '') == $id ? 'selected' : '' }}>
                                    {{ $soNumber }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Customer Ref / PO No</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. PO-8492" value="{{ old('reference_no') }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Vehicle No</label>
                        <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. MH-12-AB-1234" value="{{ old('vehicle_no') }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Transporter Name</label>
                        <input type="text" name="transporter_name" class="form-control" placeholder="e.g. Blue Dart / Own Fleet" value="{{ old('transporter_name') }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">LR / Bilty No</label>
                        <input type="text" name="lr_no" class="form-control" placeholder="e.g. LR-90812" value="{{ old('lr_no') }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">LR Date</label>
                        <input type="date" name="lr_date" class="form-control" value="{{ old('lr_date') }}">
                    </div>
                    <div class="col-md-9 col-sm-6 mb-3">
                        <label class="font-weight-bold">Delivery / Destination Address</label>
                        <input type="text" name="delivery_address" class="form-control" placeholder="Site or client shipping address" value="{{ old('delivery_address') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-default shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold"><i class="fas fa-boxes mr-1"></i> Dispatched Goods Grid</h3>
                <button type="button" class="btn btn-xs btn-primary" id="add-row-btn">
                    <i class="fas fa-plus mr-1"></i> Add Item Line
                </button>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-sm mb-0" id="sdn-items-table">
                    <thead class="thead-light">
                        <tr class="text-center">
                            <th style="width: 40px;">#</th>
                            <th style="min-width: 250px;">Item Description</th>
                            <th style="width: 110px;">Ordered Qty</th>
                            <th style="width: 130px;">Dispatched Qty <span class="text-danger">*</span></th>
                            <th style="width: 120px;">Unit Price (₹) <span class="text-danger">*</span></th>
                            <th style="width: 110px;">MRP (₹)</th>
                            <th style="width: 120px;">Batch No</th>
                            <th style="width: 130px;">Expiry Date</th>
                            <th style="width: 130px;" class="text-right">Line Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="sdn-items-body">
                        @php
                            $oldRows = old('items');
                            $rowsToRender = !empty($oldRows) ? collect($oldRows) : (!empty($convertedItems) && $convertedItems->isNotEmpty() ? $convertedItems : collect([null]));
                        @endphp

                        @foreach ($rowsToRender as $idx => $row)
                            @php
                                $r = is_array($row) ? (object) $row : $row;
                                $itemId = $r->item_id ?? '';
                                $soItemId = $r->sales_order_item_id ?? '';
                                $ordered = $r->ordered_qty ?? 0;
                                $dispatched = $r->dispatched_qty ?? '';
                                $price = $r->unit_price ?? 0;
                                $mrp = $r->mrp ?? 0;
                                $batch = $r->batch_no ?? '';
                                $exp = $r->exp_date ?? '';
                            @endphp
                            <tr class="sdn-item-row" data-index="{{ $idx }}">
                                <td class="text-center align-middle row-number">{{ $idx + 1 }}</td>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][sales_order_item_id]" value="{{ $soItemId }}">
                                    <select name="items[{{ $idx }}][item_id]" class="form-control form-control-sm select2 item-select" required>
                                        <option value="">-- Select Item --</option>
                                        @foreach ($items as $itm)
                                            <option value="{{ $itm->id }}" data-price="{{ $itm->sell_price }}" data-mrp="{{ $itm->mrp }}" {{ $itemId == $itm->id ? 'selected' : '' }}>
                                                {{ $itm->item_code ? '['.$itm->item_code.'] ' : '' }}{{ $itm->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.001" name="items[{{ $idx }}][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="{{ $ordered }}" readonly tabindex="-1">
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0.001" name="items[{{ $idx }}][dispatched_qty]" class="form-control form-control-sm text-right font-weight-bold text-primary row-dispatched" value="{{ $dispatched }}" placeholder="0.00" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_price]" class="form-control form-control-sm text-right row-price" value="{{ $price }}" placeholder="0.00" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][mrp]" class="form-control form-control-sm text-right row-mrp" value="{{ $mrp }}" placeholder="0.00">
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $idx }}][batch_no]" class="form-control form-control-sm" value="{{ $batch }}" placeholder="Batch">
                                </td>
                                <td>
                                    <input type="date" name="items[{{ $idx }}][exp_date]" class="form-control form-control-sm" value="{{ $exp }}">
                                </td>
                                <td class="text-right align-middle font-weight-bold text-success row-total">₹0.00</td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-xs btn-outline-danger remove-row-btn" title="Remove line"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <th colspan="2" class="text-right align-middle">Totals:</th>
                            <th class="text-right align-middle" id="summary-ordered">0.00</th>
                            <th class="text-right align-middle text-primary" id="summary-dispatched">0.00</th>
                            <th colspan="4" class="text-right align-middle">Total Challan Value:</th>
                            <th class="text-right align-middle text-success h6 mb-0" id="summary-amount">₹0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card card-default shadow-sm mb-4">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <label class="font-weight-bold">Internal Remarks / Transport Notes</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Optional dispatch notes or special delivery instructions" value="{{ old('remarks') }}">
                    </div>
                    <div class="col-md-4 text-right pt-3">
                        <a href="{{ route('sales.delivery-notes.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm" id="submit-btn">
                            <i class="fas fa-check-circle mr-1"></i> Save & Dispatch Goods
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Template for new row --}}
    <template id="row-template">
        <tr class="sdn-item-row" data-index="__INDEX__">
            <td class="text-center align-middle row-number">__NUM__</td>
            <td>
                <input type="hidden" name="items[__INDEX__][sales_order_item_id]" value="">
                <select name="items[__INDEX__][item_id]" class="form-control form-control-sm select2 item-select" required>
                    <option value="">-- Select Item --</option>
                    @foreach ($items as $itm)
                        <option value="{{ $itm->id }}" data-price="{{ $itm->sell_price }}" data-mrp="{{ $itm->mrp }}">
                            {{ $itm->item_code ? '['.$itm->item_code.'] ' : '' }}{{ $itm->name }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="number" step="0.001" name="items[__INDEX__][ordered_qty]" class="form-control form-control-sm text-right row-ordered" value="0" readonly tabindex="-1">
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="items[__INDEX__][dispatched_qty]" class="form-control form-control-sm text-right font-weight-bold text-primary row-dispatched" value="" placeholder="0.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]" class="form-control form-control-sm text-right row-price" value="0.00" placeholder="0.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[__INDEX__][mrp]" class="form-control form-control-sm text-right row-mrp" value="" placeholder="0.00">
            </td>
            <td>
                <input type="text" name="items[__INDEX__][batch_no]" class="form-control form-control-sm" value="" placeholder="Batch">
            </td>
            <td>
                <input type="date" name="items[__INDEX__][exp_date]" class="form-control form-control-sm" value="">
            </td>
            <td class="text-right align-middle font-weight-bold text-success row-total">₹0.00</td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-xs btn-outline-danger remove-row-btn" title="Remove line"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    </template>
@stop

@section('js')
<script>
$(function () {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    let rowIndex = {{ max(1, count($rowsToRender)) }};

    function recalculate() {
        let totOrdered = 0;
        let totDispatched = 0;
        let totAmount = 0;

        $('#sdn-items-body tr.sdn-item-row').each(function (i) {
            $(this).find('.row-number').text(i + 1);

            let ord = parseFloat($(this).find('.row-ordered').val()) || 0;
            let disp = parseFloat($(this).find('.row-dispatched').val()) || 0;
            let prc = parseFloat($(this).find('.row-price').val()) || 0;

            let lineTot = Math.round(disp * prc * 100) / 100;
            $(this).find('.row-total').text('₹' + lineTot.toFixed(2));

            totOrdered += ord;
            totDispatched += disp;
            totAmount += lineTot;
        });

        $('#summary-ordered').text(totOrdered.toFixed(2));
        $('#summary-dispatched').text(totDispatched.toFixed(2));
        $('#summary-amount').text('₹' + totAmount.toFixed(2));
    }

    // Recalculate on input
    $(document).on('input change', '.row-dispatched, .row-price', function () {
        recalculate();
    });

    // Item selection autofill
    $(document).on('change', '.item-select', function () {
        let opt = $(this).find(':selected');
        let row = $(this).closest('tr');
        if (opt.val()) {
            let prc = parseFloat(opt.data('price')) || 0;
            let mrp = parseFloat(opt.data('mrp')) || 0;
            if (!row.find('.row-price').val() || parseFloat(row.find('.row-price').val()) === 0) {
                row.find('.row-price').val(prc.toFixed(2));
            }
            if (!row.find('.row-mrp').val() || parseFloat(row.find('.row-mrp').val()) === 0) {
                row.find('.row-mrp').val(mrp.toFixed(2));
            }
        }
        recalculate();
    });

    // Add Row
    $('#add-row-btn').on('click', function () {
        let tmpl = $('#row-template').html();
        tmpl = tmpl.replace(/__INDEX__/g, rowIndex);
        tmpl = tmpl.replace(/__NUM__/g, $('#sdn-items-body tr').length + 1);

        let $newRow = $(tmpl);
        $('#sdn-items-body').append($newRow);
        $newRow.find('.select2').select2({ theme: 'bootstrap4', width: '100%' });
        rowIndex++;
        recalculate();
    });

    // Remove Row
    $(document).on('click', '.remove-row-btn', function () {
        if ($('#sdn-items-body tr').length <= 1) {
            alert('At least one item row is required.');
            return;
        }
        $(this).closest('tr').remove();
        recalculate();
    });

    // SO Ref change reloads form
    $('#sdn-so-select').on('change', function () {
        let soId = $(this).val();
        if (soId) {
            window.location.href = "{{ route('sales.delivery-notes.create') }}?from_order=" + soId;
        }
    });

    // Initial calculation
    recalculate();
});
</script>
@stop
