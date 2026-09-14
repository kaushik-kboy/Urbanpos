@extends('adminlte::page')

@section('title', 'Barcode Printing')

@section('content_header')
    <h1><i class="fas fa-barcode text-primary mr-2"></i>Barcode Printing</h1>
@stop

@section('content')
    <div class="row">
        <!-- Filter & Item Selection Card -->
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">Select Items to Print</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('inventory.barcode-printing.index') }}" class="mb-3">
                        <div class="form-group">
                            <label>Location / Branch</label>
                            <select name="branch_id" class="form-control" onchange="this.form.submit()">
                                @foreach ($branches as $id => $name)
                                    <option value="{{ $id }}" @selected((string)$branchId === (string)$id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Load from Purchase Invoice</label>
                            <select name="purchase_invoice_id" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Or choose Purchase Invoice --</option>
                                @foreach ($invoices as $inv)
                                    <option value="{{ $inv->id }}" @selected((string)$invoiceId === (string)$inv->id)>
                                        {{ $inv->invoice_number }} ({{ $inv->invoice_date->format('d-m-Y') }}) - ₹{{ number_format($inv->total, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    <hr>

                    <div class="form-group">
                        <label>Search & Add Individual Item</label>
                        <div class="input-group">
                            <input type="text" id="item-search-input" class="form-control" placeholder="Type item name or barcode...">
                            <div class="input-group-append">
                                <button type="button" id="btn-search-item" class="btn btn-outline-secondary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="search-results" class="list-group mt-2 shadow-sm" style="display: none; max-height: 250px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Queue Table Card -->
        <div class="col-md-8">
            <form method="POST" action="{{ route('inventory.barcode-printing.print') }}" target="_blank">
                @csrf
                <div class="card card-primary card-outline">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title font-weight-bold">Print Queue</h3>
                        <div class="card-tools">
                            <label class="mr-2 mb-0">Label Format:</label>
                            <select name="label_size" class="form-control form-control-sm d-inline-block w-auto mr-2">
                                <option value="standard">Standard Sticker (50mm x 25mm)</option>
                                <option value="compact">Compact Sticker (38mm x 25mm)</option>
                                <option value="shelf">Shelf Tag (65mm x 35mm)</option>
                            </select>
                            <button type="submit" class="btn btn-success btn-sm font-weight-bold">
                                <i class="fas fa-print mr-1"></i> Print Barcodes
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="print-queue-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Item Name</th>
                                        <th style="width: 130px;">Barcode</th>
                                        <th style="width: 100px;">Sell Price</th>
                                        <th style="width: 100px;">MRP</th>
                                        <th style="width: 90px;">Print Qty</th>
                                        <th style="width: 50px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="print-queue-body">
                                    @forelse ($selectedItems as $idx => $it)
                                        <tr data-item-id="{{ $it['id'] }}">
                                            <td>
                                                <input type="hidden" name="items[{{ $idx }}][name]" value="{{ $it['name'] }}">
                                                <span class="font-weight-bold">{{ $it['name'] }}</span>
                                            </td>
                                            <td>
                                                <input type="text" name="items[{{ $idx }}][barcode]" value="{{ $it['barcode'] }}" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="items[{{ $idx }}][sell_price]" value="{{ $it['sell_price'] }}" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="items[{{ $idx }}][mrp]" value="{{ $it['mrp'] }}" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="number" min="1" name="items[{{ $idx }}][qty]" value="{{ $it['qty'] }}" class="form-control form-control-sm font-weight-bold text-center">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-xs btn-outline-danger btn-remove-row">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="empty-queue-row">
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-barcode fa-2x mb-2 d-block text-muted"></i>
                                                No items in print queue. Search items on the left or load from a Purchase Invoice.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
$(document).ready(function() {
    let rowIndex = {{ $selectedItems->count() }};

    $('#item-search-input').on('keyup', function(e) {
        let q = $(this).val().trim();
        if (q.length < 2) {
            $('#search-results').hide().empty();
            return;
        }

        $.get('{{ route("inventory.barcode-printing.search-items") }}', { q: q }, function(items) {
            let container = $('#search-results').empty();
            if (items.length === 0) {
                container.append('<div class="list-group-item text-muted">No items found</div>').show();
                return;
            }

            items.forEach(function(item) {
                let btn = $('<button type="button" class="list-group-item list-group-item-action py-2">')
                    .html('<strong>' + item.name + '</strong><br><small class="text-muted">Barcode: ' + (item.barcode || 'N/A') + ' | MRP: ₹' + item.mrp + '</small>')
                    .data('item', item);

                btn.on('click', function() {
                    addItemToQueue($(this).data('item'));
                    $('#search-results').hide().empty();
                    $('#item-search-input').val('');
                });

                container.append(btn);
            });
            container.show();
        });
    });

    function addItemToQueue(item) {
        $('#empty-queue-row').remove();
        let html = `
            <tr data-item-id="${item.id}">
                <td>
                    <input type="hidden" name="items[${rowIndex}][name]" value="${item.name}">
                    <span class="font-weight-bold">${item.name}</span>
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][barcode]" value="${item.barcode}" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${rowIndex}][sell_price]" value="${item.sell_price}" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${rowIndex}][mrp]" value="${item.mrp}" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="number" min="1" name="items[${rowIndex}][qty]" value="1" class="form-control form-control-sm font-weight-bold text-center">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-row">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#print-queue-body').append(html);
        rowIndex++;
    }

    $(document).on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        if ($('#print-queue-body tr').length === 0) {
            $('#print-queue-body').append(`
                <tr id="empty-queue-row">
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-barcode fa-2x mb-2 d-block text-muted"></i>
                        No items in print queue. Search items on the left or load from a Purchase Invoice.
                    </td>
                </tr>
            `);
        }
    });
});
</script>
@stop
