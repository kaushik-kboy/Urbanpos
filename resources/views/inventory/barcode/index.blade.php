@extends('adminlte::page')

@section('title', 'Barcode Printing Studio')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark"><i class="fas fa-barcode mr-2 text-primary"></i> Barcode Printing Studio</h1>
            <small class="text-muted">A4 Sticker Sheets (Laser/Inkjet) & Thermal Rolls supported with live Purchase Invoice loading</small>
        </div>
        <div>
            <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 13px;">
                <i class="fas fa-check-circle mr-1"></i> A4 Laser / Inkjet Ready
            </span>
        </div>
    </div>
@stop

@section('content')
<div class="row">
    {{-- Left: Invoices & Search Panel --}}
    <div class="col-lg-6">
        {{-- Load from Purchase Invoice / GRN --}}
        <div class="card card-outline card-primary shadow-sm mb-3">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-primary mb-0">
                    <i class="fas fa-file-invoice mr-1"></i> 1. Load from Purchase Invoice (GRN)
                </h3>
                <span class="badge badge-primary">Auto-fill Qty & MRP</span>
            </div>
            <div class="card-body py-3">
                <div class="form-group mb-0">
                    <label class="small font-weight-bold text-muted mb-1">Select Recent Purchase Invoice:</label>
                    <div class="input-group">
                        <select id="invoiceSelect" class="form-control select2">
                            <option value="">-- Choose Invoice to Load All Items --</option>
                            @foreach($recentInvoices as $inv)
                                <option value="{{ $inv->id }}" {{ ($selectedInvoiceId == $inv->id) ? 'selected' : '' }}>
                                    {{ $inv->invoice_number }} — {{ $inv->supplier?->name ?? 'Supplier' }} ({{ $inv->invoice_date?->format('d M Y') ?? '' }}) - ₹{{ number_format($inv->total_amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                            <button type="button" id="btnLoadInvoice" class="btn btn-primary font-weight-bold px-3">
                                <i class="fas fa-cloud-download-alt mr-1"></i> Load Items
                            </button>
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block" id="invoiceStatus">
                        Loads all items, batch number, expiry date, MRP and received quantities directly into the print queue.
                    </small>
                </div>
            </div>
        </div>

        {{-- Live Search & Scanner --}}
        <div class="card card-outline card-dark shadow-sm">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-search mr-1"></i> 2. Search or Scan Items</h3>
                <small class="text-muted"><i class="fas fa-barcode mr-1"></i> Scanner ready</small>
            </div>
            <div class="card-body">
                <div class="form-group mb-2">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-dark text-white"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" id="barcode-search-input"
                               class="form-control form-control-lg font-weight-bold"
                               placeholder="Type item name, code or scan barcode gun…"
                               autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" id="barcode-search-clear" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <small class="text-muted mt-1 d-block" id="barcode-search-status">
                        Type or scan a barcode to add ad-hoc items…
                    </small>
                </div>

                {{-- Filters --}}
                <div class="row mb-1">
                    <div class="col-md-6 mb-1">
                        <select id="filter-brand" class="form-control form-control-sm">
                            <option value="">All Brands</option>
                            @foreach ($brands as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-1">
                        <select id="filter-category" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            @foreach ($categories as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Results Table --}}
            <div class="card-body p-0 border-top" id="barcode-results-wrap" style="display:none; max-height: 380px; overflow-y: auto;">
                <div id="barcode-results-loading" class="text-center py-3 d-none">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Searching items…</p>
                </div>
                <div id="barcode-results-empty" class="text-center text-muted py-4 d-none">
                    <i class="fas fa-search fa-2x mb-2 d-block"></i> No items found matching query.
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
            </div>
        </div>
    </div>

    {{-- Right: Print Queue & Layout Settings --}}
    <div class="col-lg-6">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-success mb-0"><i class="fas fa-print mr-1"></i> Print Queue & Layout</h3>
                <div class="card-tools">
                    <button type="button" id="clearQueue" class="btn btn-xs btn-outline-danger font-weight-bold">
                        <i class="fas fa-trash mr-1"></i> Clear All
                    </button>
                </div>
            </div>

            <div class="card-body p-0" style="max-height: 340px; overflow-y: auto;">
                <table class="table table-sm table-striped mb-0" id="queueTable">
                    <thead class="thead-light">
                        <tr>
                            <th>Item Description</th>
                            <th class="text-right" style="width:85px;">Price</th>
                            <th class="text-center" style="width:110px;">Sticker Qty</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="queueBody">
                        <tr id="emptyQueueRow">
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="fas fa-barcode fa-3x mb-2 d-block text-secondary"></i>
                                <strong>Print queue is empty.</strong><br>
                                Load items from a Purchase Invoice or scan/search from the left.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Print Settings & Actions --}}
            <div class="card-footer bg-light border-top">
                <form id="printForm" method="POST" action="{{ route('inventory.barcode.print') }}" target="_blank">
                    @csrf
                    <div class="row align-items-center mb-3">
                        <div class="col-md-7 mb-2">
                            <label class="small font-weight-bold text-dark mb-1">
                                <i class="fas fa-file-alt mr-1 text-primary"></i> Label Sheet / Roll Format:
                            </label>
                            <select name="format" id="formatSelect" class="form-control form-control-sm font-weight-bold">
                                <option value="a4_24" selected>★ A4 Sheet (24-Up: 3x8 - Desmat / Avery Laser)</option>
                                <option value="a4_40">A4 Sheet (40-Up: 4x10 - Compact Laser)</option>
                                <option value="50x25_2up">50x25 mm (2-Up Thermal Roll)</option>
                                <option value="50x38_2up">50x38 mm (2-Up Thermal Roll)</option>
                                <option value="102x64">102x64 mm (1-Up Single Roll)</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="small font-weight-bold text-dark mb-1">
                                <i class="fas fa-toggle-on mr-1 text-primary"></i> Display Elements:
                            </label>
                            <div class="d-flex flex-wrap" style="gap: 8px;">
                                <label class="small mb-0 font-weight-bold">
                                    <input type="checkbox" name="show_store" value="1" checked> Store
                                </label>
                                <label class="small mb-0 font-weight-bold">
                                    <input type="checkbox" name="show_mrp" value="1" checked> MRP
                                </label>
                                <label class="small mb-0 font-weight-bold">
                                    <input type="checkbox" name="show_sell" value="1" checked> Sell
                                </label>
                                <label class="small mb-0 font-weight-bold">
                                    <input type="checkbox" name="show_exp" value="1" checked> Exp/Batch
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="printInputs"></div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="text-muted">Total Stickers: <strong id="totalLabels" class="text-primary font-weight-bold h5 mb-0">0</strong></span>
                        <div>
                            <button type="submit" class="btn btn-success btn-lg font-weight-bold px-4 shadow-sm" id="printSubmit" disabled>
                                <i class="fas fa-print mr-2"></i> Print Barcode Labels
                            </button>
                        </div>
                    </div>
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
    let queue = {}; // { id: { id, code, barcode, name, mrp, sell_price, qty, exp_date, batch_no } }
    let searchTimer = null;
    let initialInvoiceItems = @json($initialInvoiceItems ?? []);

    const $input       = $('#barcode-search-input');
    const $status      = $('#barcode-search-status');
    const $resultsWrap = $('#barcode-results-wrap');
    const $loading     = $('#barcode-results-loading');
    const $empty       = $('#barcode-results-empty');
    const $table       = $('#barcode-results-table');
    const $tbody       = $('#barcode-results-body');

    /* ─── Load from Purchase Invoice ──────────────────────────── */
    $('#btnLoadInvoice').on('click', function () {
        const invId = $('#invoiceSelect').val();
        if (!invId) {
            alert('Please select a Purchase Invoice from the dropdown first.');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Loading…');
        $('#invoiceStatus').html('<span class="text-primary"><i class="fas fa-spinner fa-spin mr-1"></i> Fetching invoice items…</span>');

        $.getJSON('/inventory/barcode/invoice-items/' + invId, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt mr-1"></i> Load Items');
            if (!res || !res.items || res.items.length === 0) {
                $('#invoiceStatus').html('<span class="text-warning">No items found in this invoice.</span>');
                return;
            }

            let loadedCount = 0;
            res.items.forEach(function (it) {
                queue[it.id] = {
                    id:         it.id,
                    code:       it.item_code,
                    barcode:    it.barcode,
                    name:       it.name,
                    mrp:        it.mrp,
                    sell_price: it.sell_price,
                    qty:        it.qty || 1,
                    exp_date:   it.exp_date || '',
                    batch_no:   it.batch_no || ''
                };
                loadedCount++;
            });

            renderQueue();
            $('#invoiceStatus').html(`<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Loaded ${loadedCount} items from Invoice #${res.invoice_number}!</span>`);
        }).fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-cloud-download-alt mr-1"></i> Load Items');
            $('#invoiceStatus').html('<span class="text-danger">Failed to load invoice items.</span>');
        });
    });

    // Auto-populate if opened from a specific purchase invoice
    if (initialInvoiceItems && initialInvoiceItems.length > 0) {
        initialInvoiceItems.forEach(function (it) {
            queue[it.id] = {
                id:         it.id,
                code:       it.item_code,
                barcode:    it.barcode,
                name:       it.name,
                mrp:        it.mrp,
                sell_price: it.sell_price,
                qty:        it.qty || 1,
                exp_date:   it.exp_date || '',
                batch_no:   it.batch_no || ''
            };
        });
        renderQueue();
    }

    /* ─── Live search / scan ─────────────────────────────────── */
    $input.on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(searchTimer);

        if (!val) {
            resetResults();
            return;
        }

        searchTimer = setTimeout(function () {
            performSearch(val, false);
        }, 300);
    });

    $input.on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = $(this).val().trim();
            if (val) performSearch(val, true);
        }
    });

    $('#barcode-search-clear').on('click', function () {
        $input.val('').focus();
        resetResults();
    });

    $('#filter-brand, #filter-category').on('change', function () {
        const val = $input.val().trim();
        if (val) performSearch(val, false);
    });

    function resetResults() {
        $resultsWrap.hide();
        $loading.addClass('d-none');
        $empty.addClass('d-none');
        $table.hide();
        $status.text('Type or scan a barcode to add ad-hoc items…');
    }

    function performSearch(q, scannerMode) {
        $status.html('<i class="fas fa-spinner fa-spin mr-1"></i> Searching…');
        $resultsWrap.show();
        $loading.removeClass('d-none');
        $empty.addClass('d-none');
        $table.hide();

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

            // Scanner mode exact match
            if (scannerMode) {
                const exact = items.find(it =>
                    (it.barcode || '').toLowerCase() === q.toLowerCase() ||
                    (it.item_code || '').toLowerCase() === q.toLowerCase()
                ) || (items.length === 1 ? items[0] : null);

                if (exact) {
                    addToQueue(exact);
                    $input.val('').focus();
                    resetResults();
                    $status.html(`<span class="text-success font-weight-bold">"${exact.name}" added to queue!</span>`);
                    return;
                }
            }

            $table.show();
            $status.html('Found <strong>' + items.length + '</strong> item(s). Click "+ Add" to put in queue.');

            let html = '';
            items.forEach(function (item) {
                html += `
                    <tr class="barcode-result-row">
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
        }).fail(function () {
            $loading.addClass('d-none');
            $status.text('Error fetching items. Please try again.');
        });
    }

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
        $(this).removeClass('btn-outline-dark').addClass('btn-success');
        setTimeout(() => $(this).removeClass('btn-success').addClass('btn-outline-dark'), 400);
    });

    function addToQueue(item) {
        if (queue[item.id]) {
            queue[item.id].qty += 1;
        } else {
            queue[item.id] = { ...item, qty: item.qty || 1 };
        }
        renderQueue();
    }

    /* ─── Render Queue ────────────────────────────────────────── */
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
            total += parseInt(item.qty || 1);
            const extraMeta = (item.batch_no || item.exp_date)
                ? `<br><small class="text-info">${item.batch_no ? 'Batch: ' + item.batch_no : ''} ${item.exp_date ? 'Exp: ' + item.exp_date : ''}</small>`
                : '';

            const $tr = $(`
                <tr>
                    <td>
                        <strong>${escHtml(item.name)}</strong>
                        <br><small class="text-muted">Code: ${escHtml(item.code || item.barcode)}</small>
                        ${extraMeta}
                    </td>
                    <td class="text-right">
                        <strong>₹${parseFloat(item.sell_price || item.mrp || 0).toFixed(2)}</strong>
                        <br><small class="text-muted" style="text-decoration:line-through;">₹${parseFloat(item.mrp || 0).toFixed(2)}</small>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center font-weight-bold queue-qty-input"
                            data-id="${item.id}" value="${item.qty}" min="1" max="1000"
                            style="width:75px; display:inline-block;">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-xs btn-outline-danger queue-remove-btn" data-id="${item.id}" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>`);
            $body.append($tr);

            // Populate hidden inputs for POST form
            $printInputs.append(`
                <input type="hidden" name="items[${item.id}][id]" value="${item.id}">
                <input type="hidden" name="items[${item.id}][name]" value="${escHtml(item.name)}">
                <input type="hidden" name="items[${item.id}][code]" value="${escHtml(item.code || '')}">
                <input type="hidden" name="items[${item.id}][barcode]" value="${escHtml(item.barcode || '')}">
                <input type="hidden" name="items[${item.id}][mrp]" value="${item.mrp || 0}">
                <input type="hidden" name="items[${item.id}][sell_price]" value="${item.sell_price || 0}">
                <input type="hidden" name="items[${item.id}][exp_date]" value="${item.exp_date || ''}">
                <input type="hidden" name="items[${item.id}][batch_no]" value="${item.batch_no || ''}">
                <input type="hidden" name="items[${item.id}][qty]" value="${item.qty}" id="hiddenQty_${item.id}">
            `);
        });

        $totalLabels.text(total);
        $printSubmit.prop('disabled', false);

        // Qty change
        $body.off('change', '.queue-qty-input').on('change', '.queue-qty-input', function () {
            const id = $(this).data('id');
            const newQty = parseInt($(this).val()) || 1;
            queue[id].qty = newQty;
            $('#hiddenQty_' + id).val(newQty);
            let t = Object.values(queue).reduce((s, i) => s + (parseInt(i.qty) || 1), 0);
            $totalLabels.text(t);
        });

        // Remove item
        $body.off('click', '.queue-remove-btn').on('click', '.queue-remove-btn', function () {
            delete queue[$(this).data('id')];
            renderQueue();
        });
    }

    $('#clearQueue').on('click', function () {
        queue = {};
        renderQueue();
    });

    function escHtml(str) {
        return (str || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
</script>
@stop
