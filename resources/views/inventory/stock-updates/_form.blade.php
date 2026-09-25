@php
    $entry = $stockUpdate ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($entry?->items ?? collect());
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0 font-weight-bold"><i class="fas fa-file-alt mr-1 text-primary"></i> General Information</h5>
    <x-form-layout-customizer
        form-key="stock_updates.header"
        container-id="su-header-fields-grid"
        title="Customize Stock Update Header"
    />
</div>

<div class="row g-2 form-fields-grid mb-3" id="su-header-fields-grid">
    <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Location" data-default-order="1" data-core="1">
        <x-select name="branch_id" label="Location" :options="$branches" :selected="old('branch_id', $entry->branch_id ?? '')" placeholder="Select a branch" required />
    </div>
    <div class="field-wrapper col-md-6" data-field="entry_date" data-label="Date" data-default-order="2" data-core="1">
        <x-field name="entry_date" label="Date" type="date" :value="old('entry_date', optional($entry->entry_date ?? now())->format('Y-m-d'))" required />
    </div>
</div>

<hr>
@php
    $suItemColumns = [
        'code'          => ['label' => 'Code / Barcode', 'default' => true],
        'item'          => ['label' => 'Item Description', 'default' => true],
        'expiry'        => ['label' => 'Exp Dt', 'default' => true],
        'qty'           => ['label' => 'Qty (physical)', 'default' => true],
        'current_stock' => ['label' => 'Current Stock', 'default' => true],
        'sell_price'    => ['label' => 'Sell Price', 'default' => true],
        'mrp'           => ['label' => 'MRP', 'default' => true],
        'actions'       => ['label' => 'Actions', 'default' => true],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0 font-weight-bold"><i class="fas fa-boxes mr-1 text-primary"></i> Physical Count Items</h5>
        <p class="text-muted small mb-0">Enter the physically counted Qty. Current Stock is read from the system at the moment you Save, and the difference is posted as a +/- adjustment.</p>
    </div>
    <div class="d-flex align-items-center">
        <x-table-column-customizer
            table-key="inventory.stock-updates.items"
            table-id="items-table"
            :columns="$suItemColumns"
        />
        <button type="button" id="add-row" class="btn btn-outline-primary btn-sm font-weight-bold ml-2">
            <i class="fas fa-plus-circle mr-1"></i> Add Item Line
        </button>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover table-items-dense" id="items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 170px;" data-col-key="code">Code / Barcode <span class="text-danger">*</span></th>
                <th style="min-width: 220px;" data-col-key="item">Item Description</th>
                <th style="width: 130px;" data-col-key="expiry">Exp Dt</th>
                <th style="width: 110px;" class="text-right" data-col-key="qty">Qty (physical) <span class="text-danger">*</span></th>
                <th style="width: 100px;" class="text-right" data-col-key="current_stock">Current Stock</th>
                <th style="width: 100px;" class="text-right" data-col-key="sell_price">Sell Price</th>
                <th style="width: 100px;" class="text-right" data-col-key="mrp">MRP</th>
                <th style="width: 40px;" class="text-center" data-col-key="actions"></th>
            </tr>
        </thead>
        <tbody id="items-body">
            @forelse ($existingItems as $index => $line)
                @include('inventory.stock-updates._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('inventory.stock-updates._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
    </table>
</div>

<hr>
<x-textarea name="remarks" label="Remarks" :value="old('remarks', $entry->remarks ?? '')" />

<x-custom-fields-renderer :module="'StockUpdate'" :model="$entry ?? null" :cardStyle="true" />

<template id="row-template">
    @include('inventory.stock-updates._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

{{-- ================================================================ --}}
{{-- ITEM SEARCH MODAL (Shared standard popup)                        --}}
{{-- ================================================================ --}}
<div class="modal fade" id="su-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="suItemSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold" id="suItemSearchModalLabel">
                    <i class="fas fa-search mr-2"></i> Select Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-font text-muted"></i></span>
                            </div>
                            <input type="text" id="su-isl-filter-name" class="form-control" placeholder="Search by item name..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-barcode text-muted"></i></span>
                            </div>
                            <input type="text" id="su-isl-filter-code" class="form-control font-weight-bold" placeholder="Filter by Code / Barcode..." autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="button" id="su-isl-btn-clear" class="btn btn-outline-secondary btn-block">
                            <i class="fas fa-times mr-1"></i> Clear
                        </button>
                    </div>
                </div>

                <div id="su-isl-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted mb-0">Searching products…</p>
                </div>

                <div id="su-isl-no-results" class="text-center py-4 text-muted">
                    <i class="fas fa-keyboard fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Start typing to search items…</p>
                </div>

                <div id="su-isl-table-wrap" class="table-responsive d-none" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-sm table-striped mb-0">
                        <thead class="thead-dark sticky-top">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th>Item Name</th>
                                <th style="width: 140px;" class="text-center">Code / Barcode</th>
                                <th style="width: 90px;" class="text-center">Current Stock</th>
                                <th style="width: 100px;" class="text-right">Sell Price</th>
                                <th style="width: 100px;" class="text-right">MRP</th>
                                <th style="width: 90px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="su-isl-items-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 justify-content-between bg-light">
                <span class="text-muted small" id="su-isl-count-label"></span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
    (function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};
        const SU_ISL_URL = "{{ route('sales.sales-bills.item-list') }}";
        const SU_LOOKUP_URL = "{{ route('sales.sales-bills.lookup-item') }}";

        let suActiveSearchRow = null;
        let suModalOpen = false;
        let suModalClosing = false;
        let suIslDebounce = null;

        document.getElementById('add-row')?.addEventListener('click', function () {
            const html = document.getElementById('row-template').innerHTML.replaceAll('__INDEX__', rowIndex);
            const tbody = document.getElementById('items-body');
            const wrapper = document.createElement('tbody');
            wrapper.innerHTML = html;
            tbody.appendChild(wrapper.firstElementChild);
            rowIndex++;
        });

        document.getElementById('items-body')?.addEventListener('click', function (e) {
            const btn = e.target.closest('.su-row-remove, .row-remove');
            if (!btn) return;
            const rows = document.querySelectorAll('#items-body tr');
            if (rows.length <= 1) {
                alert('At least one item row is required.');
                return;
            }
            btn.closest('tr').remove();
        });

        let suCancellingRow = null;
        let suItemSelectedInModal = false;
        let suIslSelectedIdx = -1;

        $(document).off('keydown', '.su-physical-qty').on('keydown', '.su-physical-qty', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if ($nextRow.length) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $nextRow.find('.su-item-code').focus();
                    }
                } else {
                    e.preventDefault();
                    $('#su-add-row').trigger('click');
                    let $newRow = $('#items-body tr').last();
                    setTimeout(function () {
                        $newRow.find('.su-item-code').focus();
                        $newRow.find('.su-item-code').trigger($.Event('keydown', { key: 'Enter' }));
                    }, 60);
                }
            }
        });

        /* ----------------------------------------------------------------
           ITEM SEARCH MODAL (Triggered on Enter or F2 ONLY — Click & Focus disabled)
           ---------------------------------------------------------------- */
        let suMouseDown = false;
        $(document).on('mousedown', '.su-item-code', function () {
            suMouseDown = true;
        });

        function checkAndOpenSuModal($input) {
            if (suModalOpen || suModalClosing) return false;
            let $row = $input.closest('tr');
            if ($row.find('.su-item-select').val()) return false;
            suActiveSearchRow = $row;
            let prefill = $.trim($input.val());
            $('#su-isl-filter-name').val(prefill);
            $('#su-isl-filter-code').val('');
            fetchSuItemList();
            suModalOpen = true;
            $('#su-item-search-modal').modal('show');
            $('#su-item-search-modal').one('shown.bs.modal', function () {
                $('#su-isl-filter-name').focus().select();
            });
            return true;
        }

        // Tab or Enter/F2 opens modal. Mouse click DOES NOT open modal.
        $(document).off('click focus keydown', '.su-item-code')
            .on('focus', '.su-item-code', function () {
                if (suMouseDown) {
                    suMouseDown = false;
                    return; // Focused by mouse click - do not open modal!
                }
                // Focused by Tab / Keyboard navigation!
                checkAndOpenSuModal($(this));
            })
            .on('keydown', '.su-item-code', function (e) {
                if (e.key === 'Enter' || e.key === 'F2') {
                    e.preventDefault();
                    checkAndOpenSuModal($(this));
                }
            })
            .on('click', '.su-item-code', function () {
                suMouseDown = false;
            });

        // Tab starts from first field (branch_id) on page load
        setTimeout(function () {
            let $first = $('select[name="branch_id"]');
            if ($first.length && $first.data('select2')) {
                $first.data('select2').$container.find('.select2-selection').focus();
            } else if ($first.length) {
                $first.focus();
            }
        }, 150);

        $(document).on('click', '.su-search-btn', function (e) {
            e.preventDefault();
            suActiveSearchRow = $(this).closest('tr');
            let prefill = $.trim(suActiveSearchRow.find('.su-item-code').val());
            $('#su-isl-filter-name').val(prefill);
            $('#su-isl-filter-code').val('');
            fetchSuItemList();
            suModalOpen = true;
            $('#su-item-search-modal').modal('show');
            $('#su-item-search-modal').one('shown.bs.modal', function () {
                $('#su-isl-filter-name').focus().select();
            });
        });

        $('#su-item-search-modal').on('show.bs.modal', function () {
            suModalOpen = true;
            suModalClosing = false;
            suItemSelectedInModal = false;
            suCancellingRow = null;
        });

        $('#su-item-search-modal').on('hide.bs.modal', function () {
            suModalOpen = false;
            suModalClosing = true;
            if (!suItemSelectedInModal && suActiveSearchRow && suActiveSearchRow.length) {
                let selectedId = suActiveSearchRow.find('.su-item-id').val();
                if (!selectedId) {
                    suCancellingRow = suActiveSearchRow;
                }
            }
        });

        $('#su-item-search-modal').on('hidden.bs.modal', function () {
            suModalOpen = false;
            suModalClosing = true;
            setTimeout(function () { suModalClosing = false; }, 350);

            if (!suItemSelectedInModal && suCancellingRow && suCancellingRow.length) {
                let totalRows = $('#items-body tr').length;
                if (totalRows > 1) {
                    suCancellingRow.remove();
                } else {
                    suCancellingRow.find('.su-item-code').val('');
                    suCancellingRow.find('.su-item-desc').val('');
                }
                suCancellingRow = null;
                suActiveSearchRow = null;
                setTimeout(function () {
                    let $target = $('#su-add-row, #remarks, button[type=submit]');
                    $target.first().focus();
                }, 60);
                return;
            }

            suItemSelectedInModal = false;
            suCancellingRow = null;
            suActiveSearchRow = null;
        });

        $('#su-isl-filter-name, #su-isl-filter-code').on('input', function () {
            clearTimeout(suIslDebounce);
            suIslDebounce = setTimeout(fetchSuItemList, 300);
        });

        $('#su-isl-btn-clear').on('click', function () {
            $('#su-isl-filter-name, #su-isl-filter-code').val('');
            fetchSuItemList();
        });

        function fetchSuItemList() {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let srch = $.trim($('#su-isl-filter-name').val());
            let code = $.trim($('#su-isl-filter-code').val());

            if (!srch && !code) {
                $('#su-isl-loading').addClass('d-none');
                $('#su-isl-table-wrap').addClass('d-none');
                $('#su-isl-items-body').empty();
                $('#su-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-keyboard fa-2x text-muted"></i><p class="mt-2 text-muted">Start typing to search items…</p>'
                );
                $('#su-isl-count-label').text('');
                return;
            }

            $('#su-isl-loading').removeClass('d-none');
            $('#su-isl-no-results').addClass('d-none');
            $('#su-isl-table-wrap').addClass('d-none');

            $.getJSON(SU_ISL_URL, { branch_id: branchId, search: srch, code: code }, function (res) {
                $('#su-isl-loading').addClass('d-none');
                let items = res.items || [];
                let $tbody = $('#su-isl-items-body').empty();

                if (items.length === 0) {
                    $('#su-isl-no-results').removeClass('d-none').html(
                        '<i class="fas fa-inbox fa-2x text-muted"></i><p class="mt-2 text-muted">No items found.</p>'
                    );
                    $('#su-isl-count-label').text('');
                    return;
                }

                let html = '';
                items.forEach(function (it, idx) {
                    let codeBadge = it.code ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>` : '—';
                    let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                    let mrpDisplay = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';
                    let stockClass = it.qty <= 0 ? 'text-danger' : 'text-primary font-weight-bold';

                    html += `
                        <tr class="su-isl-item-row" style="cursor:pointer;"
                            data-id="${it.id}"
                            data-code="${it.code || ''}"
                            data-name="${it.name}"
                            data-qty="${it.qty || 0}"
                            data-sell="${it.sell_price || 0}"
                            data-mrp="${it.mrp || 0}">
                            <td class="align-middle text-center text-muted">${idx + 1}</td>
                            <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                            <td class="align-middle text-center">${codeBadge}</td>
                            <td class="align-middle text-center ${stockClass}">${parseFloat(it.qty || 0).toFixed(3)}</td>
                            <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                            <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                            <td class="align-middle text-center">
                                <button type="button" class="btn btn-success btn-xs px-2 su-isl-btn-select">
                                    <i class="fas fa-check mr-1"></i>Select
                                </button>
                            </td>
                        </tr>`;
                });

                $tbody.html(html);
                $('#su-isl-table-wrap').removeClass('d-none');
                $('#su-isl-count-label').text(items.length + ' item(s) found');
                suIslSelectedIdx = items.length > 0 ? 0 : -1;
                updateSuModalHighlight();
            }).fail(function () {
                $('#su-isl-loading').addClass('d-none');
            });
        }

        function updateSuModalHighlight() {
            let $rows = $('#su-isl-items-body tr.su-isl-item-row');
            $rows.removeClass('table-primary');
            if (suIslSelectedIdx >= 0 && suIslSelectedIdx < $rows.length) {
                let $target = $rows.eq(suIslSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#su-isl-table-wrap')[0];
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

        $('#su-isl-filter-name, #su-isl-filter-code').on('keydown', function (e) {
            let $rows = $('#su-isl-items-body tr.su-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                suIslSelectedIdx = Math.min(suIslSelectedIdx + 1, $rows.length - 1);
                updateSuModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                suIslSelectedIdx = Math.max(suIslSelectedIdx - 1, 0);
                updateSuModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (suIslSelectedIdx >= 0 && suIslSelectedIdx < $rows.length) {
                    $rows.eq(suIslSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });

        $(document).on('click', '.su-isl-item-row, .su-isl-btn-select', function (e) {
            e.stopPropagation();
            let $tr = $(this).hasClass('su-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemData = {
                id: $tr.data('id'),
                name: $tr.data('name'),
                code: $tr.data('code'),
                qty: $tr.data('qty'),
                sell_price: $tr.data('sell'),
                mrp: $tr.data('mrp')
            };

            if (!suActiveSearchRow || !itemData.id) return;
            suItemSelectedInModal = true;
            suCancellingRow = null;

            let $row = suActiveSearchRow;
            $row.find('.su-item-code').val(itemData.code);
            $row.find('.su-item-desc').val(itemData.name);
            $row.find('.su-item-id').val(itemData.id);
            $row.find('.su-current-stock').text(parseFloat(itemData.qty || 0).toFixed(3));

            if (parseFloat(itemData.sell_price) > 0) {
                $row.find('.su-sell-price').val(parseFloat(itemData.sell_price).toFixed(2));
            }
            if (parseFloat(itemData.mrp) > 0) {
                $row.find('.su-mrp').val(parseFloat(itemData.mrp).toFixed(2));
            }

            $('#su-item-search-modal').modal('hide');

            setTimeout(function () {
                $row.find('.su-physical-qty').focus().select();
            }, 100);
        });

        // Direct Code typing and Enter/Blur lookup
        $(document).on('keydown blur', '.su-item-code', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter') return;
            if (e.type === 'keydown' && e.key === 'Enter') e.preventDefault();

            let $input = $(this);
            let query = $.trim($input.val());
            let $row = $input.closest('tr');
            if (!query) return;

            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            $.getJSON(SU_LOOKUP_URL, { query: query, branch_id: branchId }, function (item) {
                if (item && item.id) {
                    $row.find('.su-item-code').val(item.code || query);
                    $row.find('.su-item-desc').val(item.name);
                    $row.find('.su-item-id').val(item.id);
                    $row.find('.su-current-stock').text(parseFloat(item.qty || 0).toFixed(3));

                    if (parseFloat(item.sell_price) > 0) {
                        $row.find('.su-sell-price').val(parseFloat(item.sell_price).toFixed(2));
                    }
                    if (parseFloat(item.mrp) > 0) {
                        $row.find('.su-mrp').val(parseFloat(item.mrp).toFixed(2));
                    }
                    $row.find('.su-physical-qty').focus().select();
                }
            });
        });

        // Form submit validation and empty row pruning
        $('#stock-update-form').on('submit', function (e) {
            let branchId = $('select[name="branch_id"]').val();
            if (!branchId) {
                e.preventDefault();
                alert('Please select a branch.');
                $('select[name="branch_id"]').focus();
                return false;
            }

            let validRows = 0;
            let hasError = false;

            $('#items-body tr.su-item-row').each(function () {
                let $row = $(this);
                let itemId = $row.find('.su-item-id').val();
                let itemCode = ($row.find('.su-item-code').val() || '').trim();
                let qtyVal = $row.find('.su-physical-qty').val();

                if (!itemId && !itemCode) {
                    return; // skip completely blank row
                }

                if (!itemId && itemCode) {
                    e.preventDefault();
                    hasError = true;
                    alert('Please select a valid item for code: ' + itemCode);
                    $row.find('.su-item-code').focus();
                    return false;
                }

                if (qtyVal === '' || isNaN(parseFloat(qtyVal)) || parseFloat(qtyVal) < 0) {
                    e.preventDefault();
                    hasError = true;
                    let desc = $row.find('.su-item-desc').val() || 'selected item';
                    alert('Please enter a valid physical quantity for: ' + desc);
                    $row.find('.su-physical-qty').focus();
                    return false;
                }

                validRows++;
            });

            if (hasError) return false;

            if (validRows === 0) {
                e.preventDefault();
                alert('Pehle item add karein. Please add at least one item before saving.');
                $('#items-body tr.su-item-row:first .su-item-code').focus();
                return false;
            }

            // Prune empty rows before submit
            $('#items-body tr.su-item-row').each(function () {
                let $row = $(this);
                let itemId = $row.find('.su-item-id').val();
                if (!itemId) {
                    $row.remove();
                }
            });

            // Re-index remaining rows contiguously
            $('#items-body tr.su-item-row').each(function (idx) {
                let $row = $(this);
                $row.find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.startsWith('items[')) {
                        $(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + idx + ']'));
                    }
                });
            });
        });

        // Keyboard shortcuts: F4 Edit, F6 Save, F7 View, F8 Print, F9 Clear, F10 Close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F4') {
                e.preventDefault();
                const firstInput = document.querySelector('#items-body input');
                if (firstInput) firstInput.focus();
            } else if (e.key === 'F6') {
                e.preventDefault();
                $('#stock-update-form').trigger('submit');
            } else if (e.key === 'F7') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.stock-updates.index') }}";
            } else if (e.key === 'F8') {
                e.preventDefault();
                window.print();
            } else if (e.key === 'F9') {
                e.preventDefault();
                const form = document.querySelector('form');
                if (form) form.reset();
            } else if (e.key === 'F10') {
                e.preventDefault();
                window.location.href = "{{ route('inventory.stock-updates.index') }}";
            }
        });
    })();
</script>
@endpush
