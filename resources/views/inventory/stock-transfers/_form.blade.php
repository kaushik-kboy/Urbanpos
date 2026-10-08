@push('css')
    <link rel="stylesheet" href="{{ asset('css/transaction-compact-layout.css') }}">
@endpush

@php
    $transfer = $stockTransfer ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($transfer?->items ?? collect());
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center">
        <h6 class="font-weight-bold text-dark mb-0 mr-2"><i class="fas fa-dolly-flatbed text-primary mr-1"></i> Transfer Details</h6>
        <span class="badge badge-primary p-1 mr-1">F2: Search</span>
        <span class="badge badge-success p-1">F6: Dispatch</span>
    </div>
    <x-form-layout-customizer
        form-key="stock_transfers.header"
        container-id="st-header-fields-grid"
        title="Customize Stock Transfer Header"
    />
</div>

<div class="row g-2 form-fields-grid tx-header-fields-grid mb-3" id="st-header-fields-grid">
    @php
        $user = auth()->user();
        $isBranchScoped = $user && $user->branch_id && !$user->hasRole('Owner') && $user->email !== 'admin@urbanpos.com';
        $selectedFromBranch = $isBranchScoped ? (int)$user->branch_id : old('from_branch_id', $transfer->from_branch_id ?? session('active_branch_id', $user?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
    @endphp
    <div class="field-wrapper col-md-4" data-field="from_branch_id" data-label="From Branch" data-default-order="1" data-core="1">
        <label for="from_branch_id" class="font-weight-bold">From Branch <span class="text-danger">*</span></label>
        <select name="from_branch_id" id="from_branch_id" class="form-control select2" required {{ $isBranchScoped ? 'style=pointer-events:none;background-color:#e9ecef; tabindex=-1' : '' }}>
            @if (!$isBranchScoped)
                <option value="">-- Select Source Branch --</option>
            @endif
            @foreach ($branches as $bId => $bName)
                @if (!$isBranchScoped || $bId == $selectedFromBranch)
                    <option value="{{ $bId }}" @selected($selectedFromBranch == $bId)>{{ $bName }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="field-wrapper col-md-4" data-field="to_branch_id" data-label="To Branch" data-default-order="2" data-core="1">
        <label for="to_branch_id" class="font-weight-bold">To Branch <span class="text-danger">*</span></label>
        <select name="to_branch_id" id="to_branch_id" class="form-control select2" required>
            <option value="">-- Select Destination Branch --</option>
            @foreach ($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('to_branch_id', $transfer->to_branch_id ?? '') == $bId)>{{ $bName }}</option>
            @endforeach
        </select>
        <div id="st-branch-error-msg" class="text-danger small font-weight-bold mt-1" style="display:none;">Source and Destination branches cannot be the same.</div>
    </div>
    <div class="field-wrapper col-md-4" data-field="transfer_date" data-label="Transfer Date" data-default-order="3" data-core="1">
        <label for="transfer_date" class="font-weight-bold">Transfer Date <span class="text-danger">*</span></label>
        <input type="text" name="transfer_date" id="transfer_date" class="form-control datepicker font-weight-bold" value="{{ old('transfer_date', optional($transfer->transfer_date ?? now())->format('d-m-Y')) }}" placeholder="DD-MM-YYYY (e.g. 10012026)" data-date-format="d-m-Y" required autocomplete="off">
    </div>
</div>

<div id="from-branch-warning" class="alert alert-warning py-2 d-none">
    <i class="fas fa-exclamation-triangle mr-1"></i> Please select a "From Branch" first so item stock availability can be verified.
</div>

<div class="card card-outline card-secondary mb-3 shadow-none border">
    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
        <h6 class="m-0 font-weight-bold text-dark">
            <i class="fas fa-dolly-flatbed mr-1"></i> Transfer Items
        </h6>
        @php
            $stItemColumns = [
                'seq'       => ['label' => 'S.No', 'default' => true],
                'code'      => ['label' => 'Code / Barcode', 'default' => true],
                'item'      => ['label' => 'Item Description', 'default' => true],
                'expiry'    => ['label' => 'Exp Dt', 'default' => true],
                'available' => ['label' => 'Available', 'default' => true],
                'qty'       => ['label' => 'Qty', 'default' => true],
                'mrp'       => ['label' => 'MRP (₹)', 'default' => true],
                'unit_cost' => ['label' => 'Unit Cost (₹)', 'default' => true],
                'amount'    => ['label' => 'Amount (₹)', 'default' => true],
                'actions'   => ['label' => 'Actions', 'default' => true],
            ];
        @endphp
        <div class="d-flex align-items-center">
            <x-table-column-customizer
                table-key="inventory.stock-transfers.items"
                table-id="items-table"
                :columns="$stItemColumns"
            />
            <button type="button" id="btn-reset-table" class="btn btn-outline-danger btn-xs px-2 mx-1 font-weight-bold" title="Reset table rows">
                <i class="fas fa-undo mr-1"></i> Reset Table
            </button>
            <button type="button" id="btn-quick-item-search" class="btn btn-outline-info btn-xs px-2 mx-1" title="Open Item Search Modal (F2)">
                <i class="fas fa-search mr-1"></i> Search Item (F2)
            </button>
            <button type="button" id="add-row" class="btn btn-primary btn-xs px-2">
                <i class="fas fa-plus mr-1"></i> Add Row
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive tx-items-scroll-container" style="overflow-x: auto; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover mb-0 table-items-dense" id="items-table" style="min-width: 1080px; font-size: 0.875rem;">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10;">
                    <tr class="text-center text-nowrap">
                        <th style="width: 45px;" data-col-key="seq">S.No</th>
                        <th style="width: 145px;" data-col-key="code">Code / Barcode</th>
                        <th style="min-width: 240px;" data-col-key="item">Item Description</th>
                        <th style="width: 150px;" data-col-key="expiry">Exp Dt</th>
                        <th style="width: 95px;" data-col-key="available">Available</th>
                        <th style="width: 95px;" data-col-key="qty">Qty</th>
                        <th style="width: 105px;" class="text-right" data-col-key="mrp">MRP (₹)</th>
                        <th style="width: 105px;" class="text-right" data-col-key="unit_cost">Unit Cost (₹)</th>
                        <th style="width: 115px;" class="text-right" data-col-key="amount">Amount (₹)</th>
                        <th style="width: 45px;" data-col-key="actions"></th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @forelse ($existingItems as $index => $line)
                        @include('inventory.stock-transfers._item-row', ['index' => $index, 'line' => $line])
                    @empty
                        @include('inventory.stock-transfers._item-row', ['index' => 0, 'line' => null])
                    @endforelse
                </tbody>
                <tfoot class="bg-light font-weight-bold" style="position: sticky; bottom: 0; z-index: 10; border-top: 2px solid #dee2e6;">
                    <tr>
                        <td colspan="6" class="text-right align-middle">Total Qty:</td>
                        <td class="text-right align-middle text-primary font-weight-bold" id="footer-total-qty">0.000</td>
                        <td colspan="2" class="text-right align-middle">Total Amount:</td>
                        <td class="text-right align-middle text-success font-weight-bold" id="footer-total-amount">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <label for="remarks" class="font-weight-bold text-muted small">Remarks</label>
        <input type="text" name="remarks" id="remarks" class="form-control form-control-sm" placeholder="Optional remarks..." value="{{ old('remarks', $transfer->remarks ?? '') }}">
    </div>
</div>

<x-custom-fields-renderer :module="'StockTransfer'" :model="$transfer ?? null" :cardStyle="true" />

<!-- ============================================================
     ITEM SEARCH MODAL — identical to Sales Bill / Purchase Invoice
     ============================================================ -->
<div class="modal fade" id="st-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="stItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow border-dark">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="stItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item for Stock Transfer
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
                            <input type="text" id="st-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="st-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="st-isl-filter-expiry" class="form-control" placeholder="Filter expiry (YYYY-MM)…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right d-flex justify-content-end align-items-center">
                        <x-table-column-customizer table-key="modal.stock-transfers.item-search" table-id="st-isl-items-table" button-class="btn btn-sm btn-outline-secondary mr-2" button-text="Columns" title="Customize Columns & Order" />
                        <button type="button" id="st-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results states -->
                <div id="st-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="st-isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="st-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="st-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;" data-col-key="seq">#</th>
                                <th data-col-key="name">Product Name</th>
                                <th class="text-center" style="width: 140px;" data-col-key="code">Code / Barcode</th>
                                <th class="text-center" style="width: 130px;" data-col-key="expiry">Expiry</th>
                                <th class="text-right" style="width: 120px;" data-col-key="qty">Available (Stock)</th>
                                <th class="text-center" style="width: 90px;" data-col-key="action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="st-isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="st-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     BATCH SELECTION MODAL — Stock Transfer Batch Picker
     ============================================================ -->
<div class="modal fade" id="st-batch-modal" tabindex="-1" role="dialog" aria-labelledby="stBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow border-primary">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold" id="stBatchModalLabel">
                    <i class="fas fa-layer-group mr-1"></i> Select Batch for Stock Transfer
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 mb-3 small font-weight-bold d-flex justify-content-between align-items-center">
                    <div>
                        Item: <span id="st-batch-modal-item-title" class="text-dark font-weight-bold"></span> | 
                        Code: <span id="st-batch-modal-item-code" class="text-dark font-weight-bold"></span>
                    </div>
                    <div id="st-batch-modal-branch-info" class="text-muted small"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="st-modal-batches-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th class="text-center" style="width: 140px;">Batch No</th>
                                <th class="text-center" style="width: 140px;">Expiry Date</th>
                                <th class="text-right" style="width: 120px;">Available Qty</th>
                                <th class="text-center" style="width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="st-modal-batches-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 justify-content-between">
                <span class="text-muted small"><i class="fas fa-keyboard mr-1"></i>Use Arrow Keys &amp; Enter to select batch</span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<template id="row-template">
    @include('inventory.stock-transfers._item-row', ['index' => '__INDEX__', 'line' => null])
</template>

@push('css')
<style>
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single { height: 31px !important; border-color: #ced4da !important; font-size: 0.875rem; width: 100% !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 29px !important; padding-left: 6px; padding-right: 18px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px !important; right: 3px; }
    .st-isl-item-disabled { cursor: not-allowed !important; opacity: 0.65; }
    .item-exp-date[readonly] {
        pointer-events: none !important;
        user-select: none !important;
    }
</style>
@endpush

@push('js')
<script>
    (function () {
        const searchItemsUrl = "{{ route('inventory.stock-transfers.search-items') }}";
        const itemByCodeUrl = "{{ route('inventory.stock-transfers.item-by-code') }}";
        const ISL_URL = "{{ route('inventory.stock-transfers.item-list') }}";

        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeTargetRow = null;
        let stDebounce = null;
        let stIslCache = {};
        let stIslLastKey = null;
        let stModalOpen = false;
        let stModalClosing = false;

        function currentFromBranch() {
            return $('#from_branch_id').val() || '';
        }

        const allFromBranchOptions = $('#from_branch_id option').map(function() {
            return { val: $(this).val(), text: $(this).text() };
        }).get();
        const allToBranchOptions = $('#to_branch_id option').map(function() {
            return { val: $(this).val(), text: $(this).text() };
        }).get();

        let syncingBranches = false;
        function syncBranchExclusion() {
            if (syncingBranches) return;
            syncingBranches = true;

            let fromVal = String($('#from_branch_id').val() || '');
            let toVal = String($('#to_branch_id').val() || '');

            // Rebuild To options excluding fromVal
            let newToHtml = '';
            allToBranchOptions.forEach(opt => {
                if (opt.val && opt.val === fromVal) return;
                let sel = (opt.val && opt.val === toVal) ? ' selected' : '';
                newToHtml += `<option value="${opt.val}"${sel}>${opt.text}</option>`;
            });
            $('#to_branch_id').html(newToHtml);

            // Rebuild From options excluding toVal
            let newFromHtml = '';
            allFromBranchOptions.forEach(opt => {
                if (opt.val && opt.val === toVal) return;
                let sel = (opt.val && opt.val === fromVal) ? ' selected' : '';
                newFromHtml += `<option value="${opt.val}"${sel}>${opt.text}</option>`;
            });
            $('#from_branch_id').html(newFromHtml);

            if ($.fn.select2) {
                try {
                    $('#to_branch_id').select2({ width: '100%' });
                    $('#from_branch_id').select2({ width: '100%' });
                } catch(e) {}
            }
            syncingBranches = false;
        }

        $(document).on('change', '#from_branch_id', function () {
            syncBranchExclusion();
        });

        $(document).on('change', '#to_branch_id', function () {
            syncBranchExclusion();
        });

        setTimeout(syncBranchExclusion, 100);

        function formatToDisplayDate(val) {
            if (!val) return '';
            val = String(val).trim();
            if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(val)) return val;
            if (/^\d{4}-\d{2}-\d{2}/.test(val)) {
                let parts = val.substring(0, 10).split('-');
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }
            if (typeof window.parseFastDate === 'function') {
                let p = window.parseFastDate(val, 'DD/MM/YYYY');
                if (p) return p;
            }
            return val;
        }

        function isExpiredDate(val) {
            if (!val) return false;
            let d = null;
            val = String(val).trim();
            if (/^\d{4}-\d{2}-\d{2}/.test(val)) {
                let parts = val.substring(0, 10).split('-');
                d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            } else if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(val)) {
                let parts = val.split('/');
                d = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
            } else if (/^\d{4}-\d{2}$/.test(val)) {
                let parts = val.split('-');
                let y = parseInt(parts[0], 10);
                let m = parseInt(parts[1], 10);
                d = new Date(y, m, 0, 23, 59, 59);
            } else if (/^\d{1,2}\/\d{4}$/.test(val)) {
                let parts = val.split('/');
                let m = parseInt(parts[0], 10);
                let y = parseInt(parts[1], 10);
                d = new Date(y, m, 0, 23, 59, 59);
            } else if (/^\d{8}$/.test(val)) {
                let dNum = parseInt(val.substring(0, 2), 10);
                let mNum = parseInt(val.substring(2, 4), 10);
                let yNum = parseInt(val.substring(4, 8), 10);
                d = new Date(yNum, mNum - 1, dNum);
            }
            if (d && !isNaN(d.getTime())) {
                let today = new Date();
                today.setHours(0, 0, 0, 0);
                return d < today;
            }
            return false;
        }

        function applyItemToRow($row, item, skipFocus = false) {
            if (!$row || !$row.length) return false;

            if (item.exp_date && isExpiredDate(item.exp_date)) {
                let formattedExp = formatToDisplayDate(item.exp_date);
                if (window.toastr) {
                    toastr.error('Product "' + (item.name || 'Selected Item') + '" has expired on ' + formattedExp + '! Transfer of expired products is not permitted.', 'Expiry Error');
                }
                return false;
            }

            const displayCode = item.item_code || item.code || item.barcode || '';
            const codeStr = displayCode ? " [" + displayCode + "]" : "";
            const optionText = (item.name || item.text || 'Item') + (item.text && item.text.indexOf('[') !== -1 ? '' : codeStr);

            $row.find('.item-code-input').val(displayCode);
            $row.find('.item-desc-input').val(optionText);
            $row.find('.item-id-input, .item-select').val(item.id);
            $row.data('item-data', item);

            const avail = parseFloat(item.available_qty !== undefined ? item.available_qty : (item.qty || 0));
            $row.find('.item-available').val(avail.toFixed(3));

            let hasBatches = false;
            if (item.batch_no && String(item.batch_no).trim() !== '' && String(item.batch_no).trim() !== '—') {
                hasBatches = true;
            } else if (item.batches && Array.isArray(item.batches) && item.batches.length > 0) {
                hasBatches = true;
            }

            let batchNo = item.batch_no || '';
            $row.find('.item-batch-no').val(batchNo);

            let $batchWrap = $row.find('.st-batch-btn-wrap');
            if (hasBatches || (item.batches && item.batches.length > 0)) {
                $batchWrap.removeClass('d-none');
                $row.find('.st-batch-badge-text, .item-batch-text').text(batchNo || 'Batch');
                $row.find('.st-btn-choose-batch').attr('title', batchNo ? ('Batch: ' + batchNo + ' (Click to change)') : 'Multiple batches available! Click to choose batch');
            } else {
                $batchWrap.addClass('d-none');
                $row.find('.st-batch-badge-text, .item-batch-text').text('');
            }

            let unitCost = parseFloat(item.unit_cost !== undefined ? item.unit_cost : (item.cost_price || 0)) || 0;
            $row.find('.item-cost').val(unitCost.toFixed(2));

            let mrpVal = parseFloat(item.mrp || 0) || 0;
            $row.find('.item-mrp').val(mrpVal > 0 ? mrpVal.toFixed(2) : '0.00');

            if (item.exp_date) {
                let formattedExp = formatToDisplayDate(item.exp_date);
                $row.find('.item-exp-date, input[name*="[exp_date]"]').val(formattedExp).attr('data-original-exp', formattedExp).data('original-exp', formattedExp);
            } else {
                $row.find('.item-exp-date, input[name*="[exp_date]"]').val('').attr('data-original-exp', '').data('original-exp', '');
            }

            recalcTotals();
            if (!skipFocus) {
                setTimeout(function() {
                    $row.find('.item-qty').focus().select();
                }, 80);
            }
            return true;
        }

        function showHintState(msg) {
            $('#st-isl-loading').addClass('d-none');
            $('#st-isl-table-wrap').addClass('d-none');
            $('#st-isl-items-body').empty();
            let $nr = $('#st-isl-no-results');
            $nr.removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + msg + '</p>'
            );
            $('#st-isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = currentFromBranch();
            if (!branchId) {
                showHintState('Please select "From Branch" first.');
                return;
            }

            let srch   = $('#st-isl-filter-name').val().trim();
            let code   = $('#st-isl-filter-code').val().trim();
            let expiry = $('#st-isl-filter-expiry').val().trim();

            if (!srch && !code && !expiry) {
                showHintState('Start typing item name, code or barcode to search\u2026');
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;
            if (stIslCache[cacheKey]) {
                if (stIslLastKey !== cacheKey) {
                    stIslLastKey = cacheKey;
                    renderItems(stIslCache[cacheKey]);
                }
                return;
            }

            stIslLastKey = cacheKey;
            let params = { branch_id: branchId, search: srch, code: code, expiry: expiry };

            $('#st-isl-loading').removeClass('d-none');
            $('#st-isl-no-results').addClass('d-none');
            $('#st-isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#st-isl-loading').addClass('d-none');
                stIslCache[cacheKey] = res.items || [];
                setTimeout(function() { delete stIslCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#st-isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        function renderItems(items) {
            let $tbody = $('#st-isl-items-body');
            $tbody.empty();

            if (!items || items.length === 0) {
                $('#st-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#st-isl-count-label').text('');
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let isExpired = it.exp_date && isExpiredDate(it.exp_date);
                let expBadge = it.exp_date
                    ? (isExpired
                        ? `<span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>EXPIRED (${formatToDisplayDate(it.exp_date)})</span>`
                        : `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${formatToDisplayDate(it.exp_date)}</span>`)
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                
                let qtyAvailable = parseFloat(it.available_qty !== undefined ? it.available_qty : (it.qty || 0));
                let isOutOfStock = qtyAvailable <= 0;
                let isBlocked = isOutOfStock || isExpired;
                let qtyClass = isOutOfStock ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                let rowClass = isBlocked ? 'st-isl-item-row st-isl-item-disabled text-muted bg-light' : 'st-isl-item-row';
                let rowStyle = isBlocked ? 'cursor: not-allowed; opacity: 0.65;' : 'cursor: pointer;';
                let actionBtn = isExpired
                    ? `<button type="button" class="btn btn-danger btn-xs px-2" disabled title="Product is Expired - Cannot transfer">
                        <i class="fas fa-ban mr-1"></i>Expired
                       </button>`
                    : (isOutOfStock
                        ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock - Cannot select">
                        <i class="fas fa-ban mr-1"></i>Out of Stock
                       </button>`
                    : `<button type="button" class="btn btn-success btn-xs px-2 st-isl-btn-select">
                        <i class="fas fa-check mr-1"></i>Select
                       </button>`);

                html += `
                    <tr class="${rowClass}" style="${rowStyle}"
                        data-idx="${idx}">
                        <td class="align-middle text-center font-weight-bold text-muted" data-col-key="seq">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark" data-col-key="name">${it.name} ${isOutOfStock ? '<span class="badge badge-secondary ml-1 small">Out of Stock</span>' : ''}</td>
                        <td class="align-middle text-center" data-col-key="code">${codeBadge}</td>
                        <td class="align-middle text-center" data-col-key="expiry">${expBadge}</td>
                        <td class="align-middle text-right ${qtyClass}" data-col-key="qty">${qtyAvailable.toFixed(3)}</td>
                        <td class="align-middle text-center" data-col-key="action">
                            ${actionBtn}
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            if (window.applyTablePreferences) {
                window.applyTablePreferences('st-isl-items-table');
            }
            $('#st-isl-table-wrap').removeClass('d-none');
            $('#st-isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');

            // Attach item data to rows
            $tbody.find('tr.st-isl-item-row').each(function () {
                let idx = $(this).data('idx');
                $(this).data('item', items[idx]);
            });

            stIslSelectedIdx = $tbody.find('tr.st-isl-item-row').not('.st-isl-item-disabled').length > 0 ? 0 : -1;
            updateStModalHighlight();
        }

        let stIslSelectedIdx = -1;
        function updateStModalHighlight() {
            let $rows = $('#st-isl-items-body tr.st-isl-item-row').not('.st-isl-item-disabled');
            $('#st-isl-items-body tr').removeClass('table-primary');
            if (stIslSelectedIdx >= 0 && stIslSelectedIdx < $rows.length) {
                let $target = $rows.eq(stIslSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#st-isl-table-wrap')[0];
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

        $('#st-isl-filter-name, #st-isl-filter-code, #st-isl-filter-expiry').on('keydown', function (e) {
            let $rows = $('#st-isl-items-body tr.st-isl-item-row').not('.st-isl-item-disabled');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                stIslSelectedIdx = Math.min(stIslSelectedIdx + 1, $rows.length - 1);
                updateStModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                stIslSelectedIdx = Math.max(stIslSelectedIdx - 1, 0);
                updateStModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (stIslSelectedIdx >= 0 && stIslSelectedIdx < $rows.length) {
                    $rows.eq(stIslSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });


        function openItemModal($row, initialQuery) {
            if (!currentFromBranch()) {
                $('#from-branch-warning').removeClass('d-none');
                $('#from_branch_id').focus();
                return;
            }
            $('#from-branch-warning').addClass('d-none');

            activeTargetRow = $row;
            initialQuery = (initialQuery || '').trim();
            $('#st-isl-filter-name').val(initialQuery);
            $('#st-isl-filter-code').val('');
            $('#st-isl-filter-expiry').val('');

            fetchItemList();
            stModalOpen = true;
            $('#st-item-search-modal').modal('show');
            $('#st-item-search-modal').one('shown.bs.modal', function () {
                $('#st-isl-filter-name').focus().select();
                if (initialQuery) fetchItemList();
            });
        }

        // Live filters debounce
        $('#st-isl-filter-name, #st-isl-filter-code, #st-isl-filter-expiry').on('input', function () {
            clearTimeout(stDebounce);
            stDebounce = setTimeout(fetchItemList, 350);
        });

        $('#st-isl-btn-clear').on('click', function () {
            $('#st-isl-filter-name, #st-isl-filter-code, #st-isl-filter-expiry').val('');
            fetchItemList();
        });

        let stCancellingRow = null;
        let stItemSelectedInModal = false;
        let stLastSelectedRow = null;

        // Clicking row or Select button picks item (unless out of stock or expired)
        $(document).on('click', '.st-isl-item-row, .st-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('st-isl-item-row') ? $(this) : $(this).closest('tr');
            let item = $row.data('item');
            if (!item) return false;

            if (item.exp_date && isExpiredDate(item.exp_date)) {
                if (window.toastr) {
                    toastr.error('Product "' + (item.name || 'Selected Item') + '" has expired on ' + formatToDisplayDate(item.exp_date) + '! Transfer of expired products is not permitted.', 'Expiry Error');
                } else {
                    console.warn('Expired item: ' + item.name);
                }
                return false;
            }

            if ($row.hasClass('st-isl-item-disabled')) {
                return false;
            }
            let avail = parseFloat(item.available_qty !== undefined ? item.available_qty : (item.qty || 0));
            if (avail <= 0) {
                if (window.toastr) {
                    toastr.warning('Product "' + (item.name || 'Selected Item') + '" has 0 available stock in this branch.', 'Out of Stock');
                } else {
                    console.warn('0 available stock: ' + item.name);
                }
                return false;
            }
            if (item && activeTargetRow) {
                let $targetRow = activeTargetRow;
                stItemSelectedInModal = true;
                stCancellingRow = null;
                stLastSelectedRow = $targetRow;
                $('#st-item-search-modal').modal('hide');

                // Query item details with all batches for current branch
                $.getJSON(itemByCodeUrl, { item_id: item.id, branch_id: currentFromBranch() }, function (res) {
                    if (res && res.found && res.item) {
                        let fullItem = res.item;
                        if (applyItemToRow($targetRow, fullItem, true) === false) {
                            return;
                        }
                        let realBatches = (fullItem.batches || []).filter(b => b && (parseFloat(b.qty || b.available_qty || 0) > 0 || (b.batch_no && String(b.batch_no).trim() !== '')));
                        if (realBatches.length > 1) {
                            showStBatchModal($targetRow, fullItem, realBatches);
                        } else {
                            setTimeout(function () {
                                $targetRow.find('.item-qty').focus().select();
                            }, 80);
                        }
                    } else {
                        applyItemToRow($targetRow, item, false);
                    }
                }).fail(function () {
                    applyItemToRow($targetRow, item, false);
                });
            }
        });

        $('#st-item-search-modal').on('show.bs.modal', function() {
            stModalOpen = true;
            stModalClosing = false;
            stItemSelectedInModal = false;
            stCancellingRow = null;
        });

        $('#st-item-search-modal').on('hide.bs.modal', function() {
            stModalOpen = false;
            stModalClosing = true;
            if (!stItemSelectedInModal && activeTargetRow && activeTargetRow.length) {
                let selectedId = activeTargetRow.find('.item-id-input, .item-select').val();
                if (!selectedId) {
                    stCancellingRow = activeTargetRow;
                }
            }
        });

        $('#st-item-search-modal').on('hidden.bs.modal', function() {
            stModalOpen = false;
            stModalClosing = true;
            setTimeout(function() { stModalClosing = false; }, 350);

            if (!stItemSelectedInModal && stCancellingRow && stCancellingRow.length) {
                let totalRows = $('#items-table tbody tr.item-row').length;
                if (totalRows > 1) {
                    stCancellingRow.remove();
                    renumberRows();
                    recalcTotals();
                } else {
                    stCancellingRow.find('.item-code-input').val('');
                    stCancellingRow.find('.item-desc-input').val('');
                }
                stCancellingRow = null;
                activeTargetRow = null;
                setTimeout(function () {
                    let $target = $('#remarks, button[type=submit], #add-row');
                    $target.first().focus();
                }, 60);
                return;
            }

            if (stItemSelectedInModal && stLastSelectedRow && stLastSelectedRow.length) {
                let $targetRow = stLastSelectedRow;
                stLastSelectedRow = null;
                setTimeout(function () {
                    $targetRow.find('.item-qty').focus().select();
                }, 60);
            }

            stItemSelectedInModal = false;
            stCancellingRow = null;
            activeTargetRow = null;
        });

        // ============================================================
        // BATCH SELECTION MODAL LOGIC (Stock Transfer)
        // ============================================================
        let stActiveBatchRow = null;
        let stActiveBatchItem = null;
        let stBatchSelectedIndex = 0;

        function showStBatchModal($row, item, batches) {
            stActiveBatchRow = $row;
            stActiveBatchItem = item;
            $('#st-batch-modal-item-title').text(item.name || 'Selected Item');
            $('#st-batch-modal-item-code').text(item.item_code || item.code || '—');
            let branchName = $('#from_branch_id option:selected').text() || 'From Branch';
            $('#st-batch-modal-branch-info').text('Branch: ' + branchName);

            let $tbody = $('#st-modal-batches-body');
            $tbody.empty();

            if (!batches || batches.length === 0) {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No batches recorded for this item. Existing stock can be transferred without batch.</td></tr>');
                $('#st-batch-modal').modal('show');
                return;
            }

            let currentBatchNo = $row.find('.item-batch-no').val() || '';
            let html = '';
            batches.forEach(function (b, idx) {
                let isExpired = b.exp_date && isExpiredDate(b.exp_date);
                let expBadge = b.exp_date
                    ? (isExpired
                        ? `<span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>EXPIRED (${formatToDisplayDate(b.exp_date)})</span>`
                        : `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${formatToDisplayDate(b.exp_date)}</span>`)
                    : `<span class="text-muted">—</span>`;
                let batchLabel = b.batch_no ? `<span class="badge badge-secondary px-2 py-1 font-weight-bold">${b.batch_no}</span>` : `<span class="badge badge-light border text-muted">No Batch</span>`;
                let qtyAvail = parseFloat(b.qty !== undefined ? b.qty : (b.available_qty || 0));
                let isSelected = (currentBatchNo && b.batch_no === currentBatchNo);
                let rowClass = 'st-batch-row ' + (isSelected ? 'table-success ' : '') + (isExpired ? 'text-muted bg-light ' : '');
                let rowStyle = isExpired ? 'cursor: not-allowed; opacity: 0.6;' : 'cursor: pointer;';
                let selectBtn = isExpired
                    ? `<button type="button" class="btn btn-xs btn-danger" disabled><i class="fas fa-ban mr-1"></i>Expired</button>`
                    : `<button type="button" class="btn btn-xs btn-success st-btn-pick-batch font-weight-bold px-2"><i class="fas fa-check mr-1"></i>Select</button>`;

                html += `
                    <tr class="${rowClass}" style="${rowStyle}" data-idx="${idx}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle text-center">${batchLabel}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${qtyAvail.toFixed(3)}</td>
                        <td class="align-middle text-center">${selectBtn}</td>
                    </tr>`;
            });

            $tbody.html(html);

            $tbody.find('tr.st-batch-row').each(function () {
                let idx = $(this).data('idx');
                $(this).data('batch', batches[idx]);
            });

            stBatchSelectedIndex = 0;
            highlightStBatchRow();
            $('#st-batch-modal').modal('show');
        }

        function highlightStBatchRow() {
            let $rows = $('#st-modal-batches-body tr.st-batch-row').filter(function () {
                let b = $(this).data('batch');
                return !b || !b.exp_date || !isExpiredDate(b.exp_date);
            });
            $('#st-modal-batches-body tr').removeClass('table-primary');
            if (stBatchSelectedIndex >= 0 && stBatchSelectedIndex < $rows.length) {
                $rows.eq(stBatchSelectedIndex).addClass('table-primary');
            }
        }

        function applyBatchToRow($row, batch) {
            let batchNo = batch.batch_no || '';
            $row.find('.item-batch-no').val(batchNo);

            let $batchWrap = $row.find('.st-batch-btn-wrap');
            $batchWrap.removeClass('d-none');
            $row.find('.st-batch-badge-text, .item-batch-text').text(batchNo || 'Batch');
            $row.find('.st-btn-choose-batch').attr('title', batchNo ? ('Batch: ' + batchNo + ' (Click to change)') : 'Click to choose batch');

            if (batch.cost_price !== undefined && parseFloat(batch.cost_price) > 0) {
                $row.find('.item-cost').val(parseFloat(batch.cost_price).toFixed(2));
            }

            if (batch.mrp !== undefined && parseFloat(batch.mrp) > 0) {
                $row.find('.item-mrp').val(parseFloat(batch.mrp).toFixed(2));
            }

            if (batch.exp_date) {
                let formatted = formatToDisplayDate(batch.exp_date);
                $row.find('.item-exp-date, input[name*="[exp_date]"]').val(formatted).attr('data-original-exp', formatted).data('original-exp', formatted);
            }

            let qtyAvail = parseFloat(batch.qty !== undefined ? batch.qty : (batch.available_qty || 0));
            $row.find('.item-available').val(qtyAvail.toFixed(3));
            recalcTotals();
        }

        $(document).on('click', '.st-batch-row, .st-btn-pick-batch', function (e) {
            e.stopPropagation();
            let $tr = $(this).hasClass('st-batch-row') ? $(this) : $(this).closest('tr');
            let batch = $tr.data('batch');
            if (!batch) return;

            if (batch.exp_date && isExpiredDate(batch.exp_date)) {
                if (window.toastr) toastr.error('This batch has expired! Transfer of expired products is not permitted.', 'Expired Batch');
                return;
            }

            if (stActiveBatchRow && stActiveBatchRow.length) {
                applyBatchToRow(stActiveBatchRow, batch);
                let $targetRow = stActiveBatchRow;
                $('#st-batch-modal').modal('hide');
                setTimeout(function () {
                    $targetRow.find('.item-qty').focus().select();
                }, 80);
            }
        });

        // Batch Modal Keyboard Navigation (ArrowUp / ArrowDown / Enter)
        $(document).on('keydown', function (e) {
            if ($('#st-batch-modal').is(':visible')) {
                let $rows = $('#st-modal-batches-body tr.st-batch-row').filter(function () {
                    let b = $(this).data('batch');
                    return !b || !b.exp_date || !isExpiredDate(b.exp_date);
                });
                if ($rows.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    stBatchSelectedIndex = Math.min(stBatchSelectedIndex + 1, $rows.length - 1);
                    highlightStBatchRow();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    stBatchSelectedIndex = Math.max(stBatchSelectedIndex - 1, 0);
                    highlightStBatchRow();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (stBatchSelectedIndex >= 0 && stBatchSelectedIndex < $rows.length) {
                        $rows.eq(stBatchSelectedIndex).trigger('click');
                    }
                }
            }
        });

        // Select / Change batch button click on table row
        $(document).on('click', '.st-btn-choose-batch', function (e) {
            e.preventDefault();
            e.stopPropagation();
            let $row = $(this).closest('tr.item-row');
            let itemId = $row.find('.item-id-input, .item-select').val();
            if (!itemId) {
                if (window.toastr) toastr.warning('Please select an item first.', 'Item Required');
                $row.find('.item-code-input').focus();
                return;
            }
            let itemData = $row.data('item-data');
            if (itemData && itemData.batches && itemData.batches.length > 0) {
                showStBatchModal($row, itemData, itemData.batches);
            } else {
                $.getJSON(itemByCodeUrl, { item_id: itemId, branch_id: currentFromBranch() }, function (res) {
                    if (res && res.found && res.item) {
                        $row.data('item-data', res.item);
                        showStBatchModal($row, res.item, res.item.batches || []);
                    } else {
                        if (window.toastr) toastr.info('No batches found for this item.', 'Batches');
                    }
                });
            }
        });

        // Safeguards to prevent Expiry Date from ever disappearing or getting wiped out on click/keydown/blur
        $(document).on('focus', '.item-exp-date[readonly]', function () {
            $(this).blur();
        });

        $(document).on('keydown', '.item-exp-date', function (e) {
            if ($(this).prop('readonly') || $(this).attr('readonly') || e.which === 8 || e.which === 46) {
                e.preventDefault();
                return false;
            }
        });

        $(document).on('input change blur', '.item-exp-date', function () {
            let $el = $(this);
            let orig = $el.attr('data-original-exp') || $el.data('original-exp');
            if (!$el.val() && orig) {
                $el.val(orig);
            }
        });

        // Tab starts from first field on page load
        setTimeout(function () {
            let $from = $('#from_branch_id');
            if ($from.length && $from.data('select2')) {
                $from.data('select2').$container.find('.select2-selection').focus();
            } else if ($from.length) {
                $from.focus();
            }
        }, 150);

        // Add Row Handler
        function addNewRow() {
            let tpl = document.getElementById('row-template');
            if (!tpl) return null;
            let html = tpl.innerHTML.replace(/__INDEX__/g, rowIndex);
            let $newRow = $(html);
            $('#items-body').append($newRow);
            rowIndex++;
            reindexSno();
            return $newRow;
        }

        $('#add-row').on('click', function (e) {
            e.preventDefault();
            let $newRow = addNewRow();
            if ($newRow) {
                setTimeout(function () {
                    $newRow.find('.item-code-input').focus();
                }, 50);
            }
        });

        // Reset Table Handler (Leaves exactly 1 empty default row)
        $('#btn-reset-table').on('click', function (e) {
            e.preventDefault();
            let tpl = document.getElementById('row-template');
            if (!tpl) return;
            let html = tpl.innerHTML.replace(/__INDEX__/g, 0);
            let $newRow = $(html);
            $('#items-body').empty().append($newRow);
            rowIndex = 1;
            reindexSno();
            recalcTotals();
            setTimeout(function () {
                $newRow.find('.item-code-input').focus();
            }, 50);
        });

        // Reset Form Handler (Clears header fields and resets table)
        $(document).on('click', '#btn-reset-form, .btn-reset-form', function (e) {
            e.preventDefault();
            $('#to_branch_id').val('').trigger('change.select2');
            $('textarea[name="remarks"]').val('');
            $('#btn-reset-table').trigger('click');
            if (window.toastr) {
                toastr.info('Stock Transfer form has been reset.');
            }
            setTimeout(function () {
                let $from = $('#from_branch_id');
                if ($from.data('select2')) {
                    $from.data('select2').$container.find('.select2-selection').focus();
                }
            }, 100);
        });

        // Row Remove Handler
        $('#items-body').on('click', '.row-remove', function (e) {
            e.preventDefault();
            if ($('#items-body tr.item-row').length > 1) {
                $(this).closest('tr.item-row').remove();
                reindexSno();
                recalcTotals();
            } else {
                let $row = $(this).closest('tr.item-row');
                $row.find('.item-code-input').val('');
                $row.find('.item-id-input, .item-select').val('');
                $row.find('.item-desc-input').val('');
                $row.find('.item-batch-no').val('');
                $row.find('.st-batch-btn-wrap').addClass('d-none');
                $row.find('.st-batch-badge-text, .item-batch-text').text('');
                $row.find('.item-exp-date').val('');
                $row.find('.item-available').val('0.000');
                $row.find('.item-qty').val('');
                $row.find('.item-cost').val('0.00');
                $row.find('.item-amount').val('0.00');
                $row.data('item-data', null);
                $row.data('last-processed-code', '');
                recalcTotals();
            }
        });

        function processStItemLookup($row, code, isDirectLookup = false) {
            code = (code || '').trim();
            if (!code) return;
            let $input = $row.find('.item-code-input');

            if (window.PosScanGuard) {
                let scanCheck = window.PosScanGuard.filterScan(code);
                if (!scanCheck.allowed) {
                    return; // Ignore duplicate bounce
                }
            }

            $.getJSON(itemByCodeUrl, { code: code, branch_id: currentFromBranch() }, function (res) {
                if (res && res.found && res.item) {
                    if (res.item.exp_date && isExpiredDate(res.item.exp_date)) {
                        if (window.toastr) {
                            toastr.error('Product "' + res.item.name + '" has expired on ' + formatToDisplayDate(res.item.exp_date) + '! Transfer of expired products is not permitted.', 'Expiry Error');
                        }
                        $row.data('last-processed-code', null);
                        $input.addClass('is-invalid border-danger');
                        setTimeout(function () { $input.focus().select(); }, 50);
                        return;
                    }
                    $row.data('last-processed-code', code || res.item.item_code || res.item.id);
                    $input.removeClass('is-invalid border-danger');
                    if (applyItemToRow($row, res.item, true) === false) {
                        return;
                    }
                    let realBatches = (res.item.batches || []).filter(b => b && (parseFloat(b.qty || b.available_qty || 0) > 0 || (b.batch_no && String(b.batch_no).trim() !== '')));
                    if (realBatches.length > 1) {
                        showStBatchModal($row, res.item, realBatches);
                    } else {
                        setTimeout(function () {
                            $row.find('.item-qty').focus().select();
                        }, 60);
                    }
                } else if (res && res.error) {
                    $row.data('last-processed-code', null);
                    $input.addClass('is-invalid border-danger');
                    if (window.toastr) {
                        toastr.error(res.error, 'Stock Transfer Error');
                    }
                    setTimeout(function () { $input.focus().select(); }, 50);
                } else {
                    $row.data('last-processed-code', null);
                    $input.addClass('is-invalid border-danger');
                    let errMsg = "Product not found for this Item Code/Barcode.";
                    if (window.toastr) {
                        toastr.warning(errMsg, 'Item Not Found');
                    }
                    setTimeout(function () { $input.focus().select(); }, 50);
                }
            }).fail(function () {
                $row.data('last-processed-code', null);
                $input.addClass('is-invalid border-danger');
                let errMsg = "Product not found for this Item Code/Barcode.";
                if (window.toastr) {
                    toastr.warning(errMsg, 'Item Not Found');
                }
                setTimeout(function () { $input.focus().select(); }, 50);
            });
        }

        function checkBranchAndOpenStModal($input) {
            let branchId = currentFromBranch();
            if (!branchId) {
                $('#from-branch-warning').removeClass('d-none');
                if (window.toastr) {
                    toastr.warning('Please select "From Branch" first.', 'Branch Required');
                }
                $('#from_branch_id').focus();
                return false;
            }
            $('#from-branch-warning').addClass('d-none');
            if (stModalOpen || stModalClosing) return false;
            let $row = $input.closest('tr.item-row');
            if ($row.find('.item-id-input, .item-select').val()) return false;
            let prefill = $.trim($input.val());
            openItemModal($row, prefill);
            return true;
        }

        // Standardized Barcode & Item Code Keydown / Tab / Enter Navigation (matching PO reference)
        $(document).off('keydown change input', '.item-code-input')
            .on('keydown', '.item-code-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr.item-row');
                    if (val) {
                        processStItemLookup($row, val, true);
                    } else {
                        checkBranchAndOpenStModal($(this));
                    }
                } else if (e.key === 'Tab' && !e.shiftKey) {
                    let val = $.trim($(this).val());
                    let $row = $(this).closest('tr.item-row');
                    if (val) {
                        e.preventDefault();
                        processStItemLookup($row, val, true);
                    } else {
                        e.preventDefault();
                        checkBranchAndOpenStModal($(this));
                    }
                } else if (e.key === 'F2') {
                    e.preventDefault();
                    checkBranchAndOpenStModal($(this));
                } else if (e.key === 'Escape') {
                    let $row = $(this).closest('tr.item-row');
                    let itemId = $row.find('.item-id-input, .item-select').val();
                    if (!itemId && $('#items-body tr.item-row').length > 1) {
                        e.preventDefault();
                        let $prevRow = $row.prev('tr.item-row');
                        $row.remove();
                        reindexSno();
                        recalcTotals();
                        if ($prevRow.length) {
                            $prevRow.find('.item-qty').focus().select();
                        }
                    }
                }
            })
            .on('change', '.item-code-input', function () {
                let val = $.trim($(this).val());
                let $row = $(this).closest('tr.item-row');
                if (!val) {
                    $row.find('.item-id-input, .item-select').val('');
                    $row.find('.item-desc-input').val('');
                    $row.find('.item-available').val('0.000');
                    $row.find('.item-batch-no').val('');
                    $row.find('.st-batch-btn-wrap').addClass('d-none');
                    $row.find('.st-batch-badge-text, .item-batch-text').text('');
                    $row.find('.item-exp-date').val('');
                    $row.find('.item-cost').val('0.00');
                    $row.find('.item-amount').val('0.00');
                    $row.data('last-processed-code', '');
                    $row.data('item-data', null);
                    recalcTotals();
                    return;
                }
                if ($row.data('last-processed-code') === val) return;
                processStItemLookup($row, val, true);
            })
            .on('input', '.item-code-input', function () {
                $(this).removeClass('is-invalid border-danger');
            });

        // Clicking on description also opens item search modal
        $(document).on('click', '.item-select, .item-desc-input', function () {
            let $row = $(this).closest('tr.item-row');
            openItemModal($row, '');
        });

        // Expiry Date input: auto-format to DD/MM/YYYY on blur or Enter/Tab, and validate not expired
        $(document).on('change blur', '.item-exp-date', function () {
            let val = $(this).val();
            let $row = $(this).closest('tr');
            let $err = $row.find('.st-exp-error');
            if (!$err.length) {
                $(this).after('<div class="st-exp-error text-danger small font-weight-bold mt-1" style="display:none;"></div>');
                $err = $row.find('.st-exp-error');
            }
            if (val) {
                let formatted = formatToDisplayDate(val);
                $(this).val(formatted);
                if (isExpiredDate(formatted)) {
                    $(this).addClass('is-invalid border-danger');
                    $err.text('Expired date (' + formatted + ') entered! Transfer of expired products is not allowed.').show();
                    $(this).val('').focus();
                } else {
                    $(this).removeClass('is-invalid border-danger');
                    $err.hide();
                }
            } else {
                $(this).removeClass('is-invalid border-danger');
                $err.hide();
            }
        });
        $(document).on('keydown', '.item-exp-date', function (e) {
            if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
                let val = $(this).val();
                let $row = $(this).closest('tr');
                let $err = $row.find('.st-exp-error');
                if (!$err.length) {
                    $(this).after('<div class="st-exp-error text-danger small font-weight-bold mt-1" style="display:none;"></div>');
                    $err = $row.find('.st-exp-error');
                }
                if (val) {
                    let formatted = formatToDisplayDate(val);
                    $(this).val(formatted);
                    if (isExpiredDate(formatted)) {
                        e.preventDefault();
                        $(this).addClass('is-invalid border-danger');
                        $err.text('Expired date (' + formatted + ') entered! Transfer of expired products is not allowed.').show();
                        $(this).val('').focus();
                        return false;
                    } else {
                        $(this).removeClass('is-invalid border-danger');
                        $err.hide();
                    }
                }
            }
        });

        function recalcTotals() {
            let totalQty = 0;
            let totalAmount = 0;
            $('#items-body tr.item-row').each(function () {
                let qty = parseFloat($(this).find('.item-qty').val()) || 0;
                let cost = parseFloat($(this).find('.item-cost').val()) || 0;
                let rowAmount = qty * cost;
                $(this).find('.item-amount').val(rowAmount.toFixed(2));
                totalQty += qty;
                totalAmount += rowAmount;
            });
            $('#footer-total-qty').text(totalQty.toFixed(3));
            $('#footer-total-amount').text(totalAmount.toFixed(2));
            $('#display-st-total-cost').text(totalAmount.toFixed(2));
            let itemCount = $('#items-body tr.item-row').filter(function () {
                return !!$(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
            }).length;
            $('#st-total-items-badge').text(itemCount + (itemCount === 1 ? ' Item' : ' Items'));
        }

        $(document).on('input change', '.item-qty, .item-cost', function () {
            recalcTotals();
        });

        function reindexSno() {
            $('#items-body tr.item-row').each(function (idx) {
                $(this).find('.row-sno').text(idx + 1);
                $(this).attr('data-row', idx);
            });
        }

        $('#items-body').on('click', '.open-item-modal', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            openItemModal($row, $row.find('.item-code-input').val());
        });

        $('#btn-quick-item-search').on('click', function () {
            let $targetRow = $('#items-body tr.item-row').last();
            openItemModal($targetRow, '');
        });

        function validateBranchSelection() {
            let fromBranch = $('#from_branch_id').val();
            let toBranch = $('#to_branch_id').val();
            let $toContainer = $('#to_branch_id').next('.select2-container').find('.select2-selection');

            if (fromBranch && toBranch && fromBranch === toBranch) {
                $toContainer.addClass('border-danger');
                $('#st-branch-error-msg').text('Source (From) Branch and Destination (To) Branch cannot be the same!').show();
                return false;
            } else {
                $toContainer.removeClass('border-danger');
                $('#st-branch-error-msg').hide();
                return true;
            }
        }

        $('#to_branch_id').on('change', function () {
            validateBranchSelection();
        });

        function validateStQty($input, showAlert = false) {
            let $row = $input.closest('tr');
            let itemId = $row.find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
            if (!itemId) return true;

            let q = parseFloat($input.val()) || 0;
            let avail = parseFloat($row.find('.item-available').val()) || 0;
            let batchNo = $row.find('.item-batch-no').val() || '';
            let itemName = $row.find('.item-desc-input, .item-select option:selected').text() || 'Selected Item';

            // Check total across rows for same item AND same batch
            let totalForBatch = 0;
            $('#items-body tr.item-row').each(function () {
                let rId = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                let rBatch = $(this).find('.item-batch-no').val() || '';
                if (rId == itemId && rBatch === batchNo) {
                    totalForBatch += parseFloat($(this).find('.item-qty').val()) || 0;
                }
            });

            if (avail > 0 && totalForBatch > avail + 0.0001) {
                $input.addClass('is-invalid border-danger text-danger');
                if (showAlert) {
                    let batchLabel = batchNo ? ` (Batch: ${batchNo})` : '';
                    let msg = `Stock is only ${avail.toFixed(3)} for ${itemName.trim()}${batchLabel}. Transfer quantity (${totalForBatch.toFixed(3)}) cannot exceed available stock!`;
                    if (window.toastr) {
                        toastr.error(msg, 'Stock Limit Exceeded');
                    } else {
                        alert(msg);
                    }
                }
                return false;
            } else if (q <= 0) {
                $input.addClass('is-invalid border-danger text-danger');
                if (showAlert) {
                    if (window.toastr) {
                        toastr.warning('Quantity must be greater than 0.', 'Quantity Required');
                    }
                }
                return false;
            } else {
                $input.removeClass('is-invalid border-danger text-danger');
                return true;
            }
        }

        $('#items-body').on('input', '.item-qty', function () {
            let $thisInput = $(this);
            let $thisRow = $thisInput.closest('tr');
            let itemId = $thisRow.find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
            let batchNo = $thisRow.find('.item-batch-no').val() || '';
            let avail = parseFloat($thisRow.find('.item-available').val()) || 0;
            let q = parseFloat($thisInput.val()) || 0;

            if (itemId && !isNaN(q)) {
                // Sum qty across ALL rows with same item + same batch (including this row)
                let totalUsed = 0;
                $('#items-body tr.item-row').each(function () {
                    let rId = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                    let rBatch = $(this).find('.item-batch-no').val() || '';
                    if (rId == itemId && rBatch === batchNo) {
                        totalUsed += parseFloat($(this).find('.item-qty').val()) || 0;
                    }
                });

                if (avail > 0 && totalUsed > avail + 0.0001) {
                    // Calculate max this row can accept
                    let usedByOthers = totalUsed - q;
                    let maxThisRow = Math.max(0, avail - usedByOthers);
                    $thisInput.val(maxThisRow.toFixed(3));
                    $thisInput.addClass('is-invalid border-danger text-danger');
                    let batchLabel = batchNo ? ` (Batch: ${batchNo})` : '';
                    if (window.toastr) {
                        toastr.error(
                            `Stock available for this batch${batchLabel} is only ${avail.toFixed(3)}. ` +
                            `Combined qty across all rows cannot exceed available stock!`,
                            'Stock Limit Exceeded'
                        );
                    }
                } else if (q <= 0 && avail > 0) {
                    $thisInput.addClass('is-invalid border-danger text-danger').attr('title', 'Quantity must be greater than 0');
                } else {
                    $thisInput.removeClass('is-invalid border-danger text-danger').attr('title', '');
                }
            }
            recalcTotals();
        });


        // Strict quantity validation and keyboard navigation: Tab or Enter advances ONLY if valid and <= available stock
        $(document).off('keydown', '.item-qty').on('keydown', '.item-qty', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                let $currentRow = $(this).closest('tr');
                let itemId = $currentRow.find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                let batchNo = $currentRow.find('.item-batch-no').val() || '';
                let q = parseFloat($(this).val()) || 0;
                let avail = parseFloat($currentRow.find('.item-available').val()) || 0;

                if (itemId) {
                    let $err = $currentRow.find('.st-qty-error');
                    if (!$err.length) {
                        $(this).after('<div class="st-qty-error text-danger small font-weight-bold mt-1" style="display:none;"></div>');
                        $err = $currentRow.find('.st-qty-error');
                    }
                    if (q <= 0) {
                        e.preventDefault();
                        e.stopPropagation();
                        $(this).addClass('is-invalid border-danger text-danger').focus();
                        $err.text('Quantity must be greater than 0.').show();
                        return false;
                    }

                    // Check total across all rows with same item+batch
                    let totalUsed = 0;
                    let $thisInput = $(this);
                    $('#items-body tr.item-row').each(function () {
                        let rId = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                        let rBatch = $(this).find('.item-batch-no').val() || '';
                        if (rId == itemId && rBatch === batchNo) {
                            totalUsed += parseFloat($(this).find('.item-qty').val()) || 0;
                        }
                    });

                    if (avail > 0 && totalUsed > avail + 0.0001) {
                        e.preventDefault();
                        e.stopPropagation();
                        let usedByOthers = totalUsed - q;
                        let maxThisRow = Math.max(0, avail - usedByOthers);
                        $(this).val(maxThisRow.toFixed(3));
                        $(this).addClass('is-invalid border-danger text-danger').focus();
                        let batchLabel = batchNo ? ` (Batch: ${batchNo})` : '';
                        $err.text(`Total qty for this item${batchLabel} (${totalUsed.toFixed(3)}) exceeds available stock (${avail.toFixed(3)}).`).show();
                        return false;
                    }
                    $err.hide();
                    $(this).removeClass('is-invalid border-danger text-danger');
                }

                // If valid, advance to next row or add row and open search modal
                e.preventDefault();
                let $nextRow = $currentRow.next('tr.item-row');
                if (!$nextRow.length) {
                    $('#add-row').trigger('click');
                    let $newRow = $('#items-table tbody tr.item-row').last();
                    setTimeout(function () {
                        $newRow.find('.item-code-input').focus();
                        openItemModal($newRow, '');
                    }, 60);
                } else {
                    $nextRow.find('.item-code-input').focus();
                }
            }
        });


        $(document).on('change', '.item-qty', function () {
            validateStQty($(this), false);
        });

        // Form Submit Handler
        $('form').on('submit', function (e) {
            let toBranch = $('#to_branch_id').val();
            if (!toBranch) {
                e.preventDefault();
                alert('Please select a destination (To) branch.');
                $('#to_branch_id').select2('open');
                return false;
            }

            if (!validateBranchSelection()) {
                e.preventDefault();
                return false;
            }

            // Remove any trailing completely empty rows if there is more than 1 row
            $('#items-body tr.item-row').each(function () {
                let id = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                if (!id && $('#items-body tr.item-row').length > 1) {
                    $(this).remove();
                }
            });
            reindexSno();

            let hasError = false;
            let validCount = 0;

            $('#items-body tr.item-row').each(function (idx) {
                let $row = $(this);
                let id = $row.find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                let $q = $row.find('.item-qty');
                let q = parseFloat($q.val()) || 0;
                let avail = parseFloat($row.find('.item-available').val()) || 0;

                if (id) {
                    if (q <= 0) {
                        $q.addClass('is-invalid border-danger text-danger');
                        let msg = `Row #${idx + 1}: Quantity must be greater than 0.`;
                        if (window.toastr) { toastr.error(msg, 'Validation Error'); } else { alert(msg); }
                        $q.focus();
                        hasError = true;
                        return false;
                    }
                    if (avail >= 0 && q > avail) {
                        $q.addClass('is-invalid border-danger text-danger');
                        let msg = `Row #${idx + 1}: Transfer quantity (${q}) exceeds available stock (${avail}).`;
                        if (window.toastr) { toastr.error(msg, 'Validation Error'); } else { alert(msg); }
                        $q.focus();
                        hasError = true;
                        return false;
                    }

                    // Strict Expiry check on submit
                    let exp = $row.find('.item-exp-date').val();
                    if (exp && isExpiredDate(exp)) {
                        $row.find('.item-exp-date').addClass('is-invalid border-danger');
                        let msg = `Row #${idx + 1}: Cannot transfer expired item (Expiry: ${exp})!`;
                        if (window.toastr) { toastr.error(msg, 'Validation Error'); } else { alert(msg); }
                        $row.find('.item-exp-date').focus();
                        hasError = true;
                        return false;
                    }

                    validCount++;
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            if (validCount === 0) {
                e.preventDefault();
                alert('Pehle item add karein. Please add at least one item before saving.');
                $('#items-body tr.item-row:first .item-code-input').focus();
                return false;
            }

            // Prune any empty rows before submission
            $('#items-body tr.item-row').each(function () {
                let id = $(this).find('.item-select, .item-id-input, select[name*="[item_id]"]').val();
                if (!id) {
                    $(this).remove();
                }
            });

            // Re-index remaining rows contiguously
            $('#items-body tr.item-row').each(function (idx) {
                let $row = $(this);
                $row.find('input, select').each(function () {
                    let name = $(this).attr('name');
                    if (name && name.startsWith('items[')) {
                        $(this).attr('name', name.replace(/items\[\w+\]/, 'items[' + idx + ']'));
                    }
                });
            });
            reindexSno();
        });

        recalcTotals();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'F2') {
                e.preventDefault();
                const $focusedInput = $(':focus');
                let $targetRow = $focusedInput.closest('tr.item-row');
                if (!$targetRow.length) $targetRow = $('#items-body tr.item-row').last();
                openItemModal($targetRow, $targetRow.find('.item-code-input').val());
            } else if (e.key === 'F6') {
                e.preventDefault();
                const form = document.getElementById('transfer-form');
                if (form) $(form).trigger('submit');
            }
        });
    })();
</script>
<script src="{{ asset('js/transaction-layout-engine.js') }}"></script>
<script>
    $(document).ready(function () {
        if (window.initTransactionCompactLayout) {
            window.initTransactionCompactLayout({
                containerSelector: '.tx-items-scroll-container',
                footerSelector: '.tx-rich-footer',
                tableSelector: '#items-table',
                minHeight: 180
            });
        }
    });
</script>
@endpush
