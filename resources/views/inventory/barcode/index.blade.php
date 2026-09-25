@extends('adminlte::page')

@section('title', 'Barcode Printing')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-barcode mr-2 text-dark"></i> Barcode Printing</h1>
    </div>
@stop

@section('content')
<div class="row">
    {{-- Left: Search Panel --}}
    <div class="col-lg-6">
        <div class="card card-outline card-dark">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-search mr-1"></i> Search Items</h3>
                <div class="card-tools">
                    <small class="text-muted"><i class="fas fa-barcode mr-1"></i> Barcode scanner supported — just scan!</small>
                </div>
            </div>
            <div class="card-body">
                {{-- AJAX Live Search --}}
                <div class="form-group mb-2">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-dark text-white"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="barcode-search-input"
                               class="form-control form-control-lg font-weight-bold"
                               placeholder="Search by name, item code or barcode (or scan)…"
                               autocomplete="off" autofocus>
                        <div class="input-group-append">
                            <button type="button" id="barcode-search-clear" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block" id="barcode-search-status">
                        Type or scan a barcode to search items…
                    </small>
                </div>

                {{-- Optional Filters --}}
                <div class="row mb-2">
                    <div class="col-md-6">
                        <select id="filter-brand" class="form-control form-control-sm">
                            <option value="">All Brands</option>
                            @foreach ($brands as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select id="filter-category" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            @foreach ($categories as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- AJAX Search Results --}}
            <div class="card-body p-0 border-top" id="barcode-results-wrap" style="display:none;">
                <div id="barcode-results-loading" class="text-center py-3 d-none">
                    <i class="fas fa-spinner fa-spin fa-2x text-dark"></i>
                    <p class="mt-2 text-muted">Searching…</p>
                </div>
                <div id="barcode-results-empty" class="text-center text-muted py-4 d-none">
                    <i class="fas fa-search fa-2x mb-2 d-block"></i> No items found.
                </div>
                <table class="table table-sm table-hover mb-0" id="barcode-results-table" style="display:none;">
                    <thead class="thead-dark">
                        <tr>
                            <th>Item</th>
                            <th class="text-right" style="width:80px;">MRP</th>
                            <th class="text-right" style="width:80px;">Sell</th>
                            <th style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody id="barcode-results-body"></tbody>
                </table>
                <div class="text-center py-2 d-none" id="barcode-results-more">
                    <small class="text-muted">Showing first 80 results. Refine your search for more specific results.</small>
                </div>
            </div>
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
                            <th class="text-right" style="width:80px;">MRP</th>
                            <th class="text-center" style="width:100px;">Labels</th>
                            <th style="width:40px;"></th>
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
(function () {
    const SEARCH_URL = '{{ route("inventory.barcode.search") }}';
    let queue = {}; // { id: { id, code, barcode, name, mrp, sell_price, qty } }
    let searchTimer = null;
    let scanBuffer = '';
    let scanTimer = null;

    const $input       = $('#barcode-search-input');
    const $status      = $('#barcode-search-status');
    const $resultsWrap = $('#barcode-results-wrap');
    const $loading     = $('#barcode-results-loading');
    const $empty       = $('#barcode-results-empty');
    const $table       = $('#barcode-results-table');
    const $tbody       = $('#barcode-results-body');
    const $more        = $('#barcode-results-more');

    /* ─── Live search / scan ─────────────────────────────────── */
    $input.on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(searchTimer);
        clearTimeout(scanTimer);

        if (!val) {
            resetResults();
            return;
        }

        // Detect fast barcode scanner input (many chars very quickly)
        searchTimer = setTimeout(function () {
            performSearch(val);
        }, 350);
    });

    // Barcode scanner: fires Enter after scanning
    $input.on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = $(this).val().trim();
            if (val) performSearch(val, true); // true = scanner mode (exact match first)
        }
    });

    $('#barcode-search-clear').on('click', function () {
        $input.val('').focus();
        resetResults();
    });

    $('#filter-brand, #filter-category').on('change', function () {
        const val = $input.val().trim();
        if (val) performSearch(val);
    });

    function resetResults() {
        $resultsWrap.hide();
        $loading.addClass('d-none');
        $empty.addClass('d-none');
        $table.hide();
        $more.addClass('d-none');
        $status.text('Type or scan a barcode to search items…');
    }

    function performSearch(q, scannerMode) {
        $status.html('<i class="fas fa-spinner fa-spin mr-1"></i> Searching…');
        $resultsWrap.show();
        $loading.removeClass('d-none');
        $empty.addClass('d-none');
        $table.hide();
        $more.addClass('d-none');

        $.getJSON(SEARCH_URL, {
            q: q,
            brand_id: $('#filter-brand').val(),
            category_value_id: $('#filter-category').val()
        }, function (items) {
            $loading.addClass('d-none');

            if (!items || items.length === 0) {
                $empty.removeClass('d-none');
                $status.text('No items found for "' + q + '".');
                return;
            }

            // If scanner mode and exact barcode match → auto-add to queue
            if (scannerMode && items.length === 1) {
                addToQueue(items[0]);
                $input.val('').focus();
                resetResults();
                $status.text('"' + items[0].name + '" added to queue!');
                return;
            }

            // Check for exact barcode match
            if (scannerMode) {
                const exact = items.find(it =>
                    (it.barcode || '').toLowerCase() === q.toLowerCase() ||
                    (it.item_code || '').toLowerCase() === q.toLowerCase()
                );
                if (exact) {
                    addToQueue(exact);
                    $input.val('').focus();
                    resetResults();
                    $status.text('"' + exact.name + '" added to queue!');
                    return;
                }
            }

            $table.show();
            $status.html('Found <strong>' + items.length + '</strong> item(s) for "' + $('<div>').text(q).html() + '"');

            let html = '';
            items.forEach(function (item) {
                html += `
                    <tr>
                        <td>
                            <strong>${escHtml(item.name)}</strong>
                            <br><small class="text-muted">${escHtml(item.item_code || '')} | Barcode: ${escHtml(item.barcode || '')}</small>
                        </td>
                        <td class="text-right">₹${parseFloat(item.mrp || 0).toFixed(2)}</td>
                        <td class="text-right text-muted">₹${parseFloat(item.sell_price || 0).toFixed(2)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-outline-dark btn-add-result"
                                data-id="${item.id}"
                                data-code="${escHtml(item.item_code || '')}"
                                data-barcode="${escHtml(item.barcode || '')}"
                                data-name="${escHtml(item.name)}"
                                data-mrp="${parseFloat(item.mrp || 0).toFixed(2)}"
                                data-sell="${parseFloat(item.sell_price || 0).toFixed(2)}">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </td>
                    </tr>`;
            });
            $tbody.html(html);

            if (items.length >= 80) {
                $more.removeClass('d-none');
            }
        }).fail(function () {
            $loading.addClass('d-none');
            $status.text('Error fetching items. Please try again.');
        });
    }

    /* ─── Add result to queue ─────────────────────────────────── */
    $tbody.on('click', '.btn-add-result', function () {
        const item = {
            id:         $(this).data('id'),
            code:       $(this).data('code'),
            barcode:    $(this).data('barcode'),
            name:       $(this).data('name'),
            mrp:        $(this).data('mrp'),
            sell_price: $(this).data('sell'),
            qty: 1
        };
        addToQueue(item);
        // Flash button
        $(this).removeClass('btn-outline-dark').addClass('btn-dark');
        const $btn = $(this);
        setTimeout(() => $btn.removeClass('btn-dark').addClass('btn-outline-dark'), 600);
    });

    function addToQueue(item) {
        if (queue[item.id]) {
            queue[item.id].qty += 1;
        } else {
            queue[item.id] = { ...item, qty: 1 };
        }
        renderQueue();
    }

    /* ─── Render queue ────────────────────────────────────────── */
    function renderQueue() {
        const items = Object.values(queue);
        const $body = $('#queueBody');
        const $emptyRow = $('#emptyQueueRow');
        const $totalLabels = $('#totalLabels');
        const $printSubmit = $('#printSubmit');
        const $printInputs = $('#printInputs');

        $body.empty();

        if (items.length === 0) {
            $body.append($emptyRow);
            $totalLabels.text(0);
            $printSubmit.prop('disabled', true);
            $printInputs.empty();
            return;
        }

        let total = 0;
        $printInputs.empty();

        items.forEach(function (item) {
            total += item.qty;
            const $tr = $(`
                <tr>
                    <td>
                        <strong>${escHtml(item.name)}</strong>
                        <br><small class="text-muted">${escHtml(item.code)}</small>
                    </td>
                    <td class="text-right">₹${item.mrp}</td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center queue-qty-input"
                            data-id="${item.id}" value="${item.qty}" min="1" max="500"
                            style="width:70px;display:inline-block;">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-outline-danger queue-remove-btn" data-id="${item.id}">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>`);
            $body.append($tr);

            $printInputs.append(`<input type="hidden" name="items[${item.id}][id]" value="${item.id}">`);
            $printInputs.append(`<input type="hidden" name="items[${item.id}][qty]" value="${item.qty}" id="hiddenQty_${item.id}">`);
        });

        $totalLabels.text(total);
        $printSubmit.prop('disabled', false);

        // Queue qty change
        $body.off('change', '.queue-qty-input').on('change', '.queue-qty-input', function () {
            const id = $(this).data('id');
            queue[id].qty = parseInt($(this).val()) || 1;
            $('#hiddenQty_' + id).val(queue[id].qty);
            let t = Object.values(queue).reduce((s, i) => s + i.qty, 0);
            $totalLabels.text(t);
        });

        // Remove from queue
        $body.off('click', '.queue-remove-btn').on('click', '.queue-remove-btn', function () {
            delete queue[$(this).data('id')];
            renderQueue();
        });
    }

    /* ─── Clear queue ─────────────────────────────────────────── */
    $('#clearQueue').on('click', function () {
        queue = {};
        renderQueue();
    });

    /* ─── Helpers ─────────────────────────────────────────────── */
    function escHtml(str) {
        return (str || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Auto-focus search on page load
    setTimeout(() => $input.focus(), 150);
})();
</script>
@stop
