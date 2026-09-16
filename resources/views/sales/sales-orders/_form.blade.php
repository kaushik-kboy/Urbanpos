@php
    $order = $salesOrder ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($order?->items ?? ($convertedItems ?? collect()));
    $selectedCust = $order->customer_id ?? ($sourceQuotation->customer_id ?? old('customer_id'));
    $selectedBranch = $order->branch_id ?? ($sourceQuotation->branch_id ?? old('branch_id'));
    $selectedSalesType = $order->sales_type ?? ($sourceQuotation->sales_type ?? old('sales_type', 'Local'));
@endphp

@if(isset($sourceQuotation))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Creating Sales Order from <strong>Quotation #{{ $sourceQuotation->quotation_number }}</strong> (Customer: {{ $sourceQuotation->customer?->name }}).
        <input type="hidden" name="from_quotation_id" value="{{ $sourceQuotation->id }}">
    </div>
@endif

<div class="row">
    <div class="col-md-3 form-group">
        <label>Customer <span class="text-danger">*</span></label>
        <select name="customer_id" class="form-control form-control-sm select2" required>
            <option value="">Select a customer</option>
            @foreach($customers as $id => $name)
                <option value="{{ $id }}" @selected($selectedCust == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label>Branch <span class="text-danger">*</span></label>
        <select name="branch_id" class="form-control form-control-sm select2" required>
            <option value="">Select a branch</option>
            @foreach($branches as $id => $name)
                <option value="{{ $id }}" @selected($selectedBranch == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 form-group">
        <label>Order Date <span class="text-danger">*</span></label>
        <input type="date" name="order_date" class="form-control form-control-sm" value="{{ optional($order?->order_date ?? now())->format('Y-m-d') }}" required>
    </div>
    <div class="col-md-2 form-group">
        <label>Expected Delivery</label>
        <input type="date" name="expected_delivery_date" class="form-control form-control-sm" value="{{ optional($order?->expected_delivery_date)->format('Y-m-d') }}">
    </div>
    <div class="col-md-2 form-group">
        <label>Sales Type <span class="text-danger">*</span></label>
        <select name="sales_type" id="so-sales-type" class="form-control form-control-sm" required>
            <option value="Local" @selected($selectedSalesType === 'Local')>Local</option>
            <option value="Interstate" @selected($selectedSalesType === 'Interstate')>Interstate</option>
        </select>
    </div>
</div>

<div class="row">
    <div class="col-md-2 form-group">
        <label>Advance Amount (₹)</label>
        <input type="number" step="0.01" min="0" name="advance_amount" class="form-control form-control-sm font-weight-bold text-primary" placeholder="0.00" value="{{ $order->advance_amount ?? old('advance_amount', '0.00') }}">
    </div>
    <div class="col-md-2 form-group">
        <label>Status</label>
        <select name="status" class="form-control form-control-sm">
            @foreach(['Open', 'Partially Fulfilled'] as $st)
                <option value="{{ $st }}" @selected(($order->status ?? 'Open') === $st)>{{ $st }}</option>
            @endforeach
            @if(isset($order) && in_array($order->status, ['Converted', 'Cancelled']))
                <option value="{{ $order->status }}" selected>{{ $order->status }}</option>
            @endif
        </select>
    </div>
    <div class="col-md-8 form-group">
        <label>Remarks</label>
        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional delivery notes or customer remarks..." value="{{ $order->remarks ?? old('remarks') }}">
    </div>
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 text-primary font-weight-bold"><i class="fas fa-boxes mr-1"></i> Order Items</h5>
    <button type="button" class="btn btn-sm btn-outline-primary" id="so-add-row-btn">
        <i class="fas fa-plus mr-1"></i> Add Row
    </button>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="so-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th style="min-width: 250px;">Item Description</th>
                <th style="width: 100px;" class="text-right">Qty</th>
                <th style="width: 120px;" class="text-right">Sell Price</th>
                <th style="width: 110px;" class="text-right">MRP</th>
                <th style="width: 90px;" class="text-right">Disc %</th>
                <th style="width: 100px;" class="text-right">Disc Amt</th>
                <th style="width: 85px;" class="text-right">GST %</th>
                <th style="width: 120px;" class="text-right">Net Amount</th>
                <th style="width: 35px;" class="text-center"></th>
            </tr>
        </thead>
        <tbody id="so-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-orders._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-orders._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="2" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary font-weight-bold" id="so-footer-qty">0.00</td>
                <td colspan="2" class="align-middle"></td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="so-footer-disc">0.00</td>
                <td class="align-middle"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="so-footer-net">0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="row justify-content-end mt-3">
    <div class="col-md-4">
        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Sub Total:</span>
                    <span class="font-weight-bold" id="so-summary-subtotal">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Total Discount:</span>
                    <span class="text-danger font-weight-bold" id="so-summary-disc">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">GST Amount:</span>
                    <span class="text-info font-weight-bold" id="so-summary-gst">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted">Round Off:</span>
                    <input type="number" step="0.01" name="round_off" id="so-round-off" class="form-control form-control-sm text-right font-weight-bold" style="width: 100px;" value="{{ $order->round_off ?? old('round_off', '0.00') }}">
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between text-lg font-weight-bold">
                    <span>Grand Total:</span>
                    <span class="text-success" id="so-summary-total">₹0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row Template for JS --}}
<table class="d-none">
    <tbody id="so-row-template">
        @include('sales.sales-orders._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
    </tbody>
</table>

@push('js')
<script>
$(function() {
    let nextIndex = {{ count($existingItems) > 0 ? count($existingItems) : 1 }};

    function initRowSelect2($row) {
        $row.find('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
    }

    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    $('#so-add-row-btn').on('click', function() {
        let html = $('#so-row-template').html().replace(/__INDEX__/g, nextIndex++);
        let $newRow = $(html);
        $('#so-items-body').append($newRow);
        initRowSelect2($newRow);
        recalcAll();
    });

    $(document).on('click', '.so-remove-row', function() {
        if ($('#so-items-body tr').length > 1) {
            $(this).closest('tr').remove();
            recalcAll();
        } else {
            alert('At least one line item is required.');
        }
    });

    $(document).on('change', '.so-item-select', function() {
        let $opt = $(this).find(':selected');
        let $row = $(this).closest('tr');
        if ($opt.val()) {
            let sell = parseFloat($opt.data('sell')) || 0;
            let mrp = parseFloat($opt.data('mrp')) || 0;
            let gst = parseFloat($opt.data('gst')) || 0;

            if (!$row.find('.so-qty').val()) {
                $row.find('.so-qty').val(1);
            }
            $row.find('.so-sell-price').val(sell.toFixed(2));
            $row.find('.so-mrp').val(mrp.toFixed(2));
            $row.find('.so-gst-percent').val(gst.toFixed(2));
            recalcRow($row);
        }
    });

    $(document).on('input', '.so-qty, .so-sell-price, .so-disc-percent, .so-disc-amount, .so-gst-percent', function() {
        let $row = $(this).closest('tr');
        let isDiscPct = $(this).hasClass('so-disc-percent');
        recalcRow($row, isDiscPct);
    });

    $('#so-round-off').on('input', function() {
        recalcSummary();
    });

    function recalcRow($row, isDiscPctChanged) {
        let qty = parseFloat($row.find('.so-qty').val()) || 0;
        let price = parseFloat($row.find('.so-sell-price').val()) || 0;
        let base = qty * price;

        let discPctInput = $row.find('.so-disc-percent');
        let discAmtInput = $row.find('.so-disc-amount');
        let discPct = parseFloat(discPctInput.val()) || 0;
        let discAmt = parseFloat(discAmtInput.val()) || 0;

        if (isDiscPctChanged) {
            discAmt = (base * discPct) / 100;
            discAmtInput.val(discAmt > 0 ? discAmt.toFixed(2) : '');
        } else if (discAmt > 0 && base > 0) {
            discPct = (discAmt / base) * 100;
            discPctInput.val(discPct.toFixed(2));
        }

        let taxable = Math.max(0, base - discAmt);
        let gstPct = parseFloat($row.find('.so-gst-percent').val()) || 0;
        let gstAmt = (taxable * gstPct) / 100;
        let net = taxable + gstAmt;

        $row.find('.so-row-net').text(net.toFixed(2));
        recalcSummary();
    }

    function recalcSummary() {
        let totQty = 0;
        let totBase = 0;
        let totDisc = 0;
        let totGst = 0;
        let totNet = 0;

        $('#so-items-body tr').each(function(i) {
            $(this).find('.so-sr-no').text(i + 1);
            let qty = parseFloat($(this).find('.so-qty').val()) || 0;
            let price = parseFloat($(this).find('.so-sell-price').val()) || 0;
            let base = qty * price;
            let disc = parseFloat($(this).find('.so-disc-amount').val()) || 0;
            let taxable = Math.max(0, base - disc);
            let gstPct = parseFloat($(this).find('.so-gst-percent').val()) || 0;
            let gst = (taxable * gstPct) / 100;
            let net = taxable + gst;

            totQty += qty;
            totBase += base;
            totDisc += disc;
            totGst += gst;
            totNet += net;
        });

        let roundOff = parseFloat($('#so-round-off').val()) || 0;
        let grandTotal = totNet + roundOff;

        $('#so-footer-qty').text(totQty.toFixed(2));
        $('#so-footer-disc').text('₹' + totDisc.toFixed(2));
        $('#so-footer-net').text('₹' + totNet.toFixed(2));

        $('#so-summary-subtotal').text('₹' + totBase.toFixed(2));
        $('#so-summary-disc').text('-₹' + totDisc.toFixed(2));
        $('#so-summary-gst').text('₹' + totGst.toFixed(2));
        $('#so-summary-total').text('₹' + grandTotal.toFixed(2));
    }

    function recalcAll() {
        $('#so-items-body tr').each(function() {
            recalcRow($(this));
        });
    }

    recalcAll();
});
</script>
@endpush
