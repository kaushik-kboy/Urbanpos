@extends('adminlte::page')

@section('title', 'Barcode Printing')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-barcode mr-2 text-dark"></i> Barcode Printing</h1>
        <a href="{{ route('inventory.barcode.print') }}" id="printBtn" class="btn btn-dark d-none" target="_blank">
            <i class="fas fa-print mr-1"></i> Print Labels (<span id="labelCount">0</span>)
        </a>
    </div>
@stop

@section('content')
    <div class="row">
        {{-- Left: Search Panel --}}
        <div class="col-lg-6">
            <div class="card card-outline card-dark">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-search mr-1"></i> Search Items</h3></div>
                <div class="card-body">
                    <form method="GET" action="{{ route('inventory.barcode.index') }}" id="searchForm">
                        <div class="row">
                            <div class="col-md-12 mb-2">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, item code, or barcode…" value="{{ $search }}" autofocus>
                            </div>
                            <div class="col-md-4">
                                <select name="brand_id" class="form-control form-control-sm">
                                    <option value="">All Brands</option>
                                    @foreach ($brands as $id => $name)
                                        <option value="{{ $id }}" {{ $brandId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="category_value_id" class="form-control form-control-sm">
                                    <option value="">All Categories</option>
                                    @foreach ($categories as $id => $name)
                                        <option value="{{ $id }}" {{ $categoryValueId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-dark btn-block"><i class="fas fa-search mr-1"></i> Search</button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Search Results --}}
                @if ($items->isNotEmpty())
                <div class="card-body p-0 border-top">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-right">MRP</th>
                                <th class="text-right">Sell</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->name }}</strong>
                                    <br><small class="text-muted">{{ $item->item_code }} | Barcode: {{ $item->barcode }}</small>
                                </td>
                                <td class="text-right">₹{{ number_format($item->mrp, 2) }}</td>
                                <td class="text-right">₹{{ number_format($item->sell_price, 2) }}</td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-outline-dark add-to-queue"
                                        data-id="{{ $item->id }}"
                                        data-code="{{ $item->item_code }}"
                                        data-barcode="{{ $item->barcode }}"
                                        data-name="{{ $item->name }}"
                                        data-mrp="{{ number_format($item->mrp, 2) }}">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @elseif (request()->has('search') || request()->has('brand_id') || request()->has('category_value_id'))
                <div class="card-body text-center text-muted py-4">
                    <i class="fas fa-search fa-2x mb-2 d-block"></i>No items found.
                </div>
                @else
                <div class="card-body text-center text-muted py-4">
                    <i class="fas fa-barcode fa-3x mb-2 d-block"></i>Search for items to add them to the print queue.
                </div>
                @endif
            </div>
        </div>

        {{-- Right: Print Queue --}}
        <div class="col-lg-6">
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-1"></i> Print Queue</h3>
                    <div class="card-tools">
                        <button type="button" id="clearQueue" class="btn btn-xs btn-outline-danger">
                            <i class="fas fa-trash mr-1"></i> Clear All
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" id="queueTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-right">MRP</th>
                                <th class="text-center" style="width:100px;">Labels</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="queueBody">
                            <tr id="emptyQueueRow">
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>Queue is empty. Add items from the left.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-right">
                    <form id="printForm" method="GET" action="{{ route('inventory.barcode.print') }}" target="_blank">
                        <div id="printInputs"></div>
                        <span class="text-muted mr-3">Total labels: <strong id="totalLabels">0</strong></span>
                        <button type="submit" class="btn btn-success" id="printSubmit" disabled>
                            <i class="fas fa-print mr-1"></i> Print Labels
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
let queue = {}; // { id: { id, name, barcode, mrp, qty } }

function renderQueue() {
    const body = document.getElementById('queueBody');
    const emptyRow = document.getElementById('emptyQueueRow');
    const totalSpan = document.getElementById('totalLabels');
    const printSubmit = document.getElementById('printSubmit');
    const printInputs = document.getElementById('printInputs');

    const items = Object.values(queue);
    body.innerHTML = '';

    if (items.length === 0) {
        body.appendChild(emptyRow);
        totalSpan.textContent = 0;
        printSubmit.disabled = true;
        printInputs.innerHTML = '';
        return;
    }

    let total = 0;
    printInputs.innerHTML = '';

    items.forEach(item => {
        total += item.qty;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${item.name}</strong><br><small class="text-muted">${item.code}</small></td>
            <td class="text-right">₹${item.mrp}</td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center qty-input"
                    data-id="${item.id}" value="${item.qty}" min="1" max="500" style="width:70px;display:inline-block;">
            </td>
            <td>
                <button type="button" class="btn btn-xs btn-outline-danger remove-btn" data-id="${item.id}">
                    <i class="fas fa-times"></i>
                </button>
            </td>`;
        body.appendChild(tr);

        // Hidden inputs for print form
        printInputs.innerHTML += `<input type="hidden" name="items[${item.id}][id]" value="${item.id}">`;
        printInputs.innerHTML += `<input type="hidden" name="items[${item.id}][qty]" value="${item.qty}" id="hiddenQty_${item.id}">`;
    });

    totalSpan.textContent = total;
    printSubmit.disabled = false;

    // Qty input change
    document.querySelectorAll('.qty-input').forEach(inp => {
        inp.addEventListener('change', function() {
            const id = this.dataset.id;
            queue[id].qty = parseInt(this.value) || 1;
            document.getElementById('hiddenQty_' + id).value = queue[id].qty;
            let t = Object.values(queue).reduce((s, i) => s + i.qty, 0);
            totalSpan.textContent = t;
        });
    });

    // Remove buttons
    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            delete queue[this.dataset.id];
            renderQueue();
        });
    });
}

document.querySelectorAll('.add-to-queue').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        if (queue[id]) {
            queue[id].qty += 1;
        } else {
            queue[id] = {
                id: id,
                code: this.dataset.code,
                barcode: this.dataset.barcode,
                name: this.dataset.name,
                mrp: this.dataset.mrp,
                qty: 1
            };
        }
        renderQueue();
        // Flash the button
        this.classList.add('btn-dark');
        setTimeout(() => this.classList.remove('btn-dark'), 500);
    });
});

document.getElementById('clearQueue')?.addEventListener('click', function() {
    queue = {};
    renderQueue();
});
</script>
@stop
