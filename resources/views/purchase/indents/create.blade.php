@extends('adminlte::page')

@section('title', 'Raise Purchase Indent')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-clipboard-list mr-2 text-primary"></i> Raise Purchase Indent</h1>
            <small class="text-muted">Internal store & departmental inventory requisition</small>
        </div>
        <a href="{{ route('purchase.purchase-indents.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Indents
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <form action="{{ route('purchase.purchase-indents.store') }}" method="POST" id="indent-form" novalidate>
            @csrf
            <div class="card-body">
                <x-error-summary />

                <div class="d-flex justify-content-end mb-2">
                    <x-form-layout-customizer
                        form-key="purchase_indents.header"
                        container-id="indent-header-fields-grid"
                        title="Customize Purchase Indent Header"
                    />
                </div>

                <div class="row mb-3 form-fields-grid" id="indent-header-fields-grid">
                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="branch_id" data-label="Target Branch" data-default-order="1" data-core="1">
                        <label class="font-weight-bold">Target Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" id="branch_id" class="form-control form-control-sm" required>
                            @foreach ($branches as $bId => $bName)
                                <option value="{{ $bId }}" @selected(old('branch_id') == $bId)>{{ $bName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-wrapper col-md-2 col-sm-6 mb-3" data-field="indent_date" data-label="Indent Date" data-default-order="2" data-core="1">
                        <label class="font-weight-bold">Indent Date <span class="text-danger">*</span></label>
                        <input type="text" name="indent_date" id="indent_date" class="form-control form-control-sm datepicker"
                               value="{{ old('indent_date', now()->format('Y-m-d')) }}" placeholder="DD/MM/YYYY or YYYY-MM-DD" required autocomplete="off">
                    </div>

                    <div class="field-wrapper col-md-2 col-sm-6 mb-3" data-field="required_by_date" data-label="Required By Date" data-default-order="3">
                        <label class="font-weight-bold">Required By Date</label>
                        <input type="text" name="required_by_date" id="required_by_date" class="form-control form-control-sm datepicker"
                               value="{{ old('required_by_date', now()->addDays(3)->format('Y-m-d')) }}" placeholder="DD/MM/YYYY or YYYY-MM-DD" autocomplete="off">
                    </div>

                    <div class="field-wrapper col-md-3 col-sm-6 mb-3" data-field="department" data-label="Department" data-default-order="4" data-core="1">
                        <label class="font-weight-bold">Department <span class="text-danger">*</span></label>
                        <select name="department" id="department" class="form-control form-control-sm" required>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}" @selected(old('department', 'Store / Retail') == $dept)>{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-wrapper col-md-2 col-sm-6 mb-3" data-field="priority" data-label="Priority" data-default-order="5" data-core="1">
                        <label class="font-weight-bold">Priority <span class="text-danger">*</span></label>
                        <select name="priority" id="priority" class="form-control form-control-sm" required>
                            @foreach ($priorities as $pri)
                                <option value="{{ $pri }}" @selected(old('priority', 'Medium') == $pri)>{{ $pri }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-wrapper col-md-12 mb-2" data-field="remarks" data-label="General Remarks" data-default-order="6">
                        <label class="font-weight-bold">General Remarks / Requisition Reason</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="e.g. Stock replenishment for upcoming weekend promotion">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="card card-outline card-secondary mb-3 shadow-none border">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fas fa-boxes mr-1 text-primary"></i> Requisition Items
                        </h6>
                        <button type="button" id="btn-add-row" class="btn btn-primary btn-xs px-2">
                            <i class="fas fa-plus mr-1"></i> Add Item Line
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0" id="indent-items-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th style="width: 140px;">Code / Barcode <span class="text-danger">*</span></th>
                                        <th style="min-width: 240px;">Item Description</th>
                                        <th style="width: 110px;" class="text-center">Branch Stock</th>
                                        <th style="width: 120px;">Req. Qty <span class="text-danger">*</span></th>
                                        <th style="width: 120px;">Est. Unit Cost (₹)</th>
                                        <th style="width: 130px;" class="text-right">Est. Total (₹)</th>
                                        <th>Reason / Note</th>
                                        <th style="width: 45px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="indent-items-body">
                                    {{-- Dynamically populated --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <x-custom-fields-renderer :module="'PurchaseIndent'" :model="null" :cardStyle="true" />

                {{-- Summary Footer --}}
                <div class="row justify-content-end">
                    <div class="col-md-5 col-lg-4">
                        <div class="card bg-light border shadow-none mb-0">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Items:</span>
                                    <strong id="summary-total-items">0</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Requested Qty:</span>
                                    <strong id="summary-total-qty">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-2">
                                    <span class="font-weight-bold">Est. Total Amount:</span>
                                    <strong class="text-primary font-weight-bold" style="font-size: 1.15rem;" id="summary-total-amount">₹0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                <a href="{{ route('purchase.purchase-indents.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                    <i class="fas fa-paper-plane mr-1"></i> Submit Requisition for Approval
                </button>
            </div>
        </form>
    </div>

    {{-- Row Template --}}
    <template id="indent-row-template">
        <tr class="indent-row" data-index="__INDEX__">
            <td class="text-center align-middle row-number">__NUM__</td>
            <td style="min-width: 130px;">
                <input type="text" class="form-control form-control-sm indent-item-code font-weight-bold" placeholder="Code / Barcode" autocomplete="off" title="Enter item code, or click to search">
            </td>
            <td style="min-width: 220px;">
                <input type="text" class="form-control form-control-sm indent-item-desc bg-light font-weight-bold text-truncate" readonly tabindex="-1" placeholder="Product Description (auto-filled)">
                <input type="hidden" name="items[__INDEX__][item_id]" class="indent-item-select">
            </td>
            <td class="text-center align-middle">
                <span class="badge badge-light border stock-badge font-weight-normal px-2 py-1">0.00</span>
            </td>
            <td>
                <input type="number" step="0.001" min="0" name="items[__INDEX__][requested_qty]" class="form-control form-control-sm text-right qty-input" placeholder="0.00">
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[__INDEX__][estimated_cost]" class="form-control form-control-sm text-right cost-input" placeholder="0.00">
            </td>
            <td class="text-right align-middle font-weight-bold line-total">
                ₹0.00
            </td>
            <td>
                <input type="text" name="items[__INDEX__][remarks]" class="form-control form-control-sm" placeholder="Optional line note">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-outline-danger btn-xs remove-row-btn" title="Remove line">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    </template>

    <!-- Item Search Popup Modal -->
    <div class="modal fade" id="indent-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="indentItemSearchLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title" id="indentItemSearchLabel">
                        <i class="fas fa-search mr-2"></i>Select Item for Requisition
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" id="indent-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                </div>
                                <input type="text" id="indent-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-2 text-right">
                            <button type="button" id="indent-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times mr-1"></i>Clear
                            </button>
                        </div>
                    </div>
                    <div id="indent-isl-loading" class="text-center py-4 d-none">
                        <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Loading items…</p>
                    </div>
                    <div id="indent-isl-no-results" class="text-center py-4 d-none">
                        <i class="fas fa-inbox fa-2x text-muted"></i>
                        <p class="mt-2 text-muted">No items found.</p>
                    </div>
                    <div class="table-responsive" id="indent-isl-table-wrap" style="max-height: 420px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-hover mb-0" id="indent-isl-items-table">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th class="text-center" style="width: 40px;">#</th>
                                    <th>Product Name</th>
                                    <th class="text-center" style="width: 130px;">Code</th>
                                    <th class="text-right" style="width: 100px;">Cost Price</th>
                                    <th class="text-right" style="width: 100px;">Sell Price</th>
                                    <th class="text-right" style="width: 100px;">MRP</th>
                                    <th class="text-right" style="width: 90px;">Stock</th>
                                    <th class="text-center" style="width: 80px;">Select</th>
                                </tr>
                            </thead>
                            <tbody id="indent-isl-items-body"></tbody>
                        </table>
                    </div>
                    <small class="text-muted mt-2 d-block" id="indent-isl-count-label"></small>
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
            let rowIndex = 0;
            let activeSearchRow = null;
            let islDebounce = null;
            let islCache = {};
            let islLastKey = null;
            let islModalOpen = false;
            let islModalClosing = false;
            let islSelectedIdx = -1;

            const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';
            const LOOKUP_URL = '{{ route("purchase.purchase-invoices.lookup-item") }}';
            const STOCK_URL = '{{ route("purchase.purchase-indents.item-stock") }}';

            const tbody = document.getElementById('indent-items-body');
            const template = document.getElementById('indent-row-template').innerHTML;
            const branchSelect = document.getElementById('branch_id');

            function addRow(prefillItem = null) {
                const html = template
                    .replaceAll('__INDEX__', rowIndex)
                    .replaceAll('__NUM__', tbody.children.length + 1);
                
                const $newRow = $(html);
                $('#indent-items-body').append($newRow);

                if (prefillItem && prefillItem.id) {
                    populateRow($newRow, prefillItem);
                }

                rowIndex++;
                reindexRows();
                calculateTotals();
                return $newRow;
            }

            function reindexRows() {
                $('#indent-items-body tr.indent-row').each(function (i) {
                    $(this).find('.row-number').text(i + 1);
                });
            }

            function fetchLiveStock(itemId, branchId, $badge) {
                if (!itemId) {
                    $badge.text('0.00').attr('class', 'badge badge-light border stock-badge font-weight-normal px-2 py-1');
                    return;
                }
                $.getJSON(STOCK_URL, { item_id: itemId, branch_id: branchId }, function (data) {
                    const stock = parseFloat(data.current_stock) || 0;
                    $badge.text(stock.toFixed(2));
                    if (stock <= 0) {
                        $badge.attr('class', 'badge badge-danger px-2 py-1');
                    } else if (stock <= 5) {
                        $badge.attr('class', 'badge badge-warning px-2 py-1');
                    } else {
                        $badge.attr('class', 'badge badge-success px-2 py-1');
                    }
                }).fail(function () {
                    $badge.text('0.00').attr('class', 'badge badge-light border stock-badge font-weight-normal px-2 py-1');
                });
            }

            function populateRow($row, data) {
                if (!data || !data.id) return;
                const codeVal = data.item_code || data.code || data.ean_upc_code || '';
                $row.find('.indent-item-code').val(codeVal);
                $row.find('.indent-item-desc').val(data.name + (codeVal ? ' [' + codeVal + ']' : ''));
                $row.find('.indent-item-select').val(data.id);

                if (parseFloat(data.cost_price) > 0) {
                    $row.find('.cost-input').val(parseFloat(data.cost_price).toFixed(2));
                }

                const branchId = $('#branch_id').val() || '';
                fetchLiveStock(data.id, branchId, $row.find('.stock-badge'));

                calculateRowTotal($row);
                setTimeout(function () {
                    $row.find('.qty-input').focus().select();
                }, 50);
            }

            function calculateRowTotal($row) {
                const qty = parseFloat($row.find('.qty-input').val()) || 0;
                const cost = parseFloat($row.find('.cost-input').val()) || 0;
                const total = Math.round(qty * cost * 100) / 100;
                $row.find('.line-total').text('₹' + total.toFixed(2));
                calculateTotals();
            }

            function calculateTotals() {
                let totalItems = 0;
                let totalQty = 0;
                let totalAmount = 0;

                $('#indent-items-body tr.indent-row').each(function () {
                    const $r = $(this);
                    const itemId = $r.find('.indent-item-select').val();
                    const qty = parseFloat($r.find('.qty-input').val()) || 0;
                    const cost = parseFloat($r.find('.cost-input').val()) || 0;

                    if (itemId && qty > 0) {
                        totalItems++;
                        totalQty += qty;
                        totalAmount += (qty * cost);
                    }
                });

                $('#summary-total-items').text(totalItems);
                $('#summary-total-qty').text(totalQty.toFixed(2));
                $('#summary-total-amount').text('₹' + (Math.round(totalAmount * 100) / 100).toFixed(2));
            }

            /* ================================================================
               ITEM SEARCH MODAL (Shared with Purchase module)
               ================================================================ */
            function fetchItemList() {
                let nameQ = $.trim($('#indent-isl-filter-name').val());
                let codeQ = $.trim($('#indent-isl-filter-code').val());
                let branchId = $('#branch_id').val() || '';
                let cacheKey = branchId + '|' + nameQ + '|' + codeQ;

                if (cacheKey === islLastKey && islCache[cacheKey]) {
                    renderItemList(islCache[cacheKey]);
                    return;
                }

                $('#indent-isl-loading').removeClass('d-none');
                $('#indent-isl-no-results').addClass('d-none');
                $('#indent-isl-table-wrap').addClass('d-none');

                $.ajax({
                    url: ISL_URL,
                    data: { name: nameQ, code: codeQ, branch_id: branchId },
                    dataType: 'json',
                    success: function (items) {
                        islCache[cacheKey] = items;
                        islLastKey = cacheKey;
                        $('#indent-isl-loading').addClass('d-none');
                        renderItemList(items);
                    },
                    error: function () {
                        $('#indent-isl-loading').addClass('d-none');
                        $('#indent-isl-no-results').removeClass('d-none');
                    }
                });
            }

            function renderItemList(items) {
                let $tbody = $('#indent-isl-items-body');
                $tbody.empty();

                if (!items || items.length === 0) {
                    $('#indent-isl-no-results').removeClass('d-none');
                    $('#indent-isl-table-wrap').addClass('d-none');
                    $('#indent-isl-count-label').text('');
                    islSelectedIdx = -1;
                    return;
                }

                $('#indent-isl-no-results').addClass('d-none');
                $('#indent-isl-table-wrap').removeClass('d-none');
                $('#indent-isl-count-label').text('Showing ' + items.length + ' item(s)');

                items.forEach(function (itm, i) {
                    let cost = parseFloat(itm.cost_price || 0).toFixed(2);
                    let sell = parseFloat(itm.sell_price || 0).toFixed(2);
                    let mrp = parseFloat(itm.mrp || 0).toFixed(2);
                    let stock = Math.round(parseFloat(itm.stock || 0));
                    let code = itm.item_code || itm.code || '';

                    let row = '<tr class="indent-isl-item-row" style="cursor: pointer;" ' +
                        'data-id="' + itm.id + '" ' +
                        'data-name="' + $('<div>').text(itm.name).html() + '" ' +
                        'data-code="' + $('<div>').text(code).html() + '" ' +
                        'data-cost="' + cost + '" ' +
                        'data-sell="' + sell + '" ' +
                        'data-mrp="' + mrp + '" ' +
                        'data-stock="' + stock + '">' +
                        '<td class="text-center align-middle">' + (i + 1) + '</td>' +
                        '<td class="align-middle font-weight-bold">' + $('<div>').text(itm.name).html() + '</td>' +
                        '<td class="text-center align-middle font-weight-bold text-monospace">' + code + '</td>' +
                        '<td class="text-right align-middle">₹' + cost + '</td>' +
                        '<td class="text-right align-middle">₹' + sell + '</td>' +
                        '<td class="text-right align-middle">₹' + mrp + '</td>' +
                        '<td class="text-right align-middle font-weight-bold text-info">' + stock + '</td>' +
                        '<td class="text-center align-middle">' +
                            '<button type="button" class="btn btn-primary btn-xs px-2 indent-isl-btn-select">Select</button>' +
                        '</td>' +
                    '</tr>';
                    $tbody.append(row);
                });

                islSelectedIdx = items.length > 0 ? 0 : -1;
                updateModalHighlight();
            }

            function updateModalHighlight() {
                let $rows = $('#indent-isl-items-body tr.indent-isl-item-row');
                $rows.removeClass('table-primary');
                if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                    let $target = $rows.eq(islSelectedIdx);
                    $target.addClass('table-primary');
                    let container = $('#indent-isl-table-wrap')[0];
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

            $('#indent-isl-filter-name, #indent-isl-filter-code').on('input', function () {
                clearTimeout(islDebounce);
                islDebounce = setTimeout(fetchItemList, 250);
            });

            $('#indent-isl-btn-clear').on('click', function () {
                $('#indent-isl-filter-name').val('');
                $('#indent-isl-filter-code').val('');
                fetchItemList();
                $('#indent-isl-filter-name').focus();
            });

            $('#indent-isl-filter-name, #indent-isl-filter-code').on('keydown', function (e) {
                let $rows = $('#indent-isl-items-body tr.indent-isl-item-row');
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

            // Open modal on Code/Barcode field: Tab/Enter/F2 ONLY — mouse click does NOT open modal
            let indentMouseDown = false;
            $(document).on('mousedown', '.indent-item-code', function () {
                indentMouseDown = true;
            });

            function checkAndOpenIndentModal($input) {
                if (islModalOpen || islModalClosing) return;
                let $row = $input.closest('tr');
                if ($row.find('.indent-item-select').val()) return;
                // Block if a previous row has no item yet
                let $prevEmpty = null;
                $('#indent-items-body tr.indent-row').each(function () {
                    if ($(this).is($row)) return false; // reached current row
                    if (!$(this).find('.indent-item-select').val()) {
                        $prevEmpty = $(this);
                        return false;
                    }
                });
                if ($prevEmpty) {
                    $prevEmpty.find('.indent-item-code').focus();
                    return;
                }
                activeSearchRow = $row;
                let prefill = $.trim($input.val());
                $('#indent-isl-filter-name').val(prefill);
                $('#indent-isl-filter-code').val('');
                fetchItemList();
                islModalOpen = true;
                $('#indent-item-search-modal').modal('show');
                $('#indent-item-search-modal').one('shown.bs.modal', function () {
                    $('#indent-isl-filter-name').focus().select();
                    if (prefill) fetchItemList();
                });
            }

            $(document).off('click focus keydown', '.indent-item-code')
                .on('focus', '.indent-item-code', function () {
                    if (indentMouseDown) {
                        indentMouseDown = false;
                        return; // mouse click - do not open modal
                    }
                    // Focused by Tab / keyboard navigation
                    checkAndOpenIndentModal($(this));
                })
                .on('keydown', '.indent-item-code', function (e) {
                    if (e.key === 'Enter' || e.key === 'F2') {
                        e.preventDefault();
                        checkAndOpenIndentModal($(this));
                    }
                })
                .on('click', '.indent-item-code', function () {
                    indentMouseDown = false;
                });

            $('#indent-item-search-modal').on('show.bs.modal', function () {
                islModalOpen = true;
                islModalClosing = false;
            });

            $('#indent-item-search-modal').on('hide.bs.modal', function () {
                islModalOpen = false;
                islModalClosing = true;
            });

            $('#indent-item-search-modal').on('hidden.bs.modal', function () {
                islModalOpen = false;
                islModalClosing = true;
                setTimeout(function () { islModalClosing = false; }, 350);
            });

            // Selecting row in modal
            $(document).on('click', '.indent-isl-item-row, .indent-isl-btn-select', function (e) {
                e.stopPropagation();
                let $tr = $(this).hasClass('indent-isl-item-row') ? $(this) : $(this).closest('tr');
                let itemData = {
                    id: $tr.data('id'),
                    name: $tr.data('name'),
                    code: $tr.data('code'),
                    cost_price: $tr.data('cost'),
                    sell_price: $tr.data('sell'),
                    mrp: $tr.data('mrp'),
                    stock: $tr.data('stock')
                };

                if (!activeSearchRow || !itemData.id) return;
                let $targetRow = activeSearchRow;
                populateRow($targetRow, itemData);
                $('#indent-item-search-modal').modal('hide');
                setTimeout(function () {
                    $targetRow.find('.qty-input').focus().select();
                }, 120);
            });

            // Direct barcode / code entry on Enter or blur
            $(document).on('change blur keydown', '.indent-item-code', function (e) {
                if (e.type === 'keydown' && e.key === 'F2') {
                    e.preventDefault();
                    $(this).trigger('click');
                    return;
                }
                if (e.type === 'keydown' && e.key !== 'Enter') return;
                if (e.type === 'keydown' && e.key === 'Enter') e.preventDefault();

                let $input = $(this);
                let $row = $input.closest('tr');
                let query = $.trim($input.val());
                if (!query) {
                    if (e.type === 'keydown' && e.key === 'Enter') {
                        $input.trigger('click');
                    }
                    return;
                }

                let branchId = $('#branch_id').val() || '';
                $.getJSON(LOOKUP_URL, { query: query, branch_id: branchId }, function (data) {
                    if (data && data.id) {
                        populateRow($row, data);
                    } else {
                        // Open modal with prefilled search
                        $input.trigger('click');
                    }
                }).fail(function () {
                    $input.trigger('click');
                });
            });

            // Row calculation events
            $(document).on('input', '.qty-input, .cost-input', function () {
                calculateRowTotal($(this).closest('tr'));
            });

            // Remove row button
            $(document).on('click', '.remove-row-btn', function () {
                if ($('#indent-items-body tr.indent-row').length <= 1) {
                    alert('An indent must have at least one line item.');
                    return;
                }
                $(this).closest('tr').remove();
                reindexRows();
                calculateTotals();
            });

            // Add row button
            $('#btn-add-row').on('click', function () {
                let $newRow = addRow();
                setTimeout(function () {
                    $newRow.find('.indent-item-code').focus();
                }, 80);
            });

            // Branch change refreshes all stocks
            $('#branch_id').on('change', function () {
                const branchId = $(this).val();
                islCache = {};
                islLastKey = null;
                $('#indent-items-body tr.indent-row').each(function () {
                    const $r = $(this);
                    const itemId = $r.find('.indent-item-select').val();
                    if (itemId) {
                        fetchLiveStock(itemId, branchId, $r.find('.stock-badge'));
                    }
                });
            });

            // Form submit validation and empty row pruning
            $('#indent-form').on('submit', function (e) {
                let branchId = $('#branch_id').val();
                if (!branchId) {
                    e.preventDefault();
                    alert('Please select a target branch.');
                    $('#branch_id').focus();
                    return false;
                }

                let validRows = 0;
                let hasError = false;

                $('#indent-items-body tr.indent-row').each(function () {
                    let $row = $(this);
                    let itemId = $row.find('.indent-item-select').val();
                    let itemCode = ($row.find('.indent-item-code').val() || '').trim();
                    let qty = parseFloat($row.find('.qty-input').val()) || 0;

                    if (!itemId && !itemCode) {
                        return; // skip blank row
                    }

                    if (!itemId && itemCode) {
                        e.preventDefault();
                        hasError = true;
                        alert('Please select a valid item for code: ' + itemCode);
                        $row.find('.indent-item-code').focus();
                        return false;
                    }

                    if (qty <= 0) {
                        e.preventDefault();
                        hasError = true;
                        let desc = $row.find('.indent-item-desc').val() || 'selected item';
                        alert('Please enter a valid requested quantity greater than 0 for: ' + desc);
                        $row.find('.qty-input').focus();
                        return false;
                    }

                    validRows++;
                });

                if (hasError) return false;

                if (validRows === 0) {
                    e.preventDefault();
                    alert('Pehle item add karein. Please add at least one item before saving.');
                    $('#indent-items-body tr.indent-row:first .indent-item-code').focus();
                    return false;
                }

                // Prune empty rows before submit
                $('#indent-items-body tr.indent-row').each(function () {
                    let $row = $(this);
                    let itemId = $row.find('.indent-item-select').val();
                    if (!itemId) {
                        $row.remove();
                    }
                });

                // Re-index remaining rows contiguously
                reindexRows();
            });

            // Initialize default row and autofocus first header field (branch_id)
            addRow();
            setTimeout(function () {
                let $branch = $('#branch_id');
                if ($branch.length) $branch.focus();
            }, 150);
        });
    </script>
    @endpush
@stop
