@extends('adminlte::page')

@section('title', 'Smart Replenishment Studio (V1)')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-brain text-info mr-2"></i> Smart Replenishment Studio <span class="badge badge-primary font-weight-normal text-xs ml-1">V1</span>
            </h1>
            <small class="text-muted">Demand Velocity &bull; Cover Days &bull; Inter-Branch Matchmaking &bull; 1-Click PO</small>
        </div>
        <div class="d-flex align-items-center mt-2 mt-md-0">
            <span class="badge badge-warning px-3 py-2 mr-2 font-weight-bold shadow-sm" style="font-size: 0.82rem;">
                <i class="fas fa-flask mr-1"></i> Ramdev Staging Test Mode
            </span>
            <button type="button" class="btn btn-outline-secondary mr-2 shadow-sm" data-toggle="modal" data-target="#settingsModal">
                <i class="fas fa-sliders-h mr-1"></i> Rules & Settings
            </button>
            <button type="button" class="btn btn-primary shadow-sm" id="btnSyncLive">
                <i class="fas fa-sync-alt mr-1" id="syncIcon"></i> <span id="syncText">Sync Live Stock</span>
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- Alerts Bar --}}
    <div id="statusAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert">
        <i class="fas fa-check-circle mr-1"></i> <span id="statusAlertMessage"></span>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    {{-- Top KPI Cards --}}
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-white shadow-sm border border-danger">
                <div class="inner">
                    <h3 class="text-danger font-weight-bold" id="kpiCriticalCount">{{ number_format($summary['critical_count'] ?? 0) }}</h3>
                    <p class="text-muted mb-0 font-weight-bold text-uppercase text-xs">Critical Stock (0-3 Days)</p>
                    <small class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Immediate action required</small>
                </div>
                <div class="icon">
                    <i class="fas fa-skull-crossbones text-danger" style="opacity: 0.15;"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-white shadow-sm border border-warning">
                <div class="inner">
                    <h3 class="text-warning font-weight-bold" id="kpiLowCount">{{ number_format($summary['low_count'] ?? 0) }}</h3>
                    <p class="text-muted mb-0 font-weight-bold text-uppercase text-xs">Low Stock (4-10 Days)</p>
                    <small class="text-warning font-weight-bold"><i class="fas fa-clock mr-1"></i> Approaching buffer limit</small>
                </div>
                <div class="icon">
                    <i class="fas fa-hourglass-half text-warning" style="opacity: 0.15;"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-white shadow-sm border border-success">
                <div class="inner">
                    <h3 class="text-success font-weight-bold" id="kpiTransferCount">{{ number_format($summary['transfer_opportunities_count'] ?? 0) }}</h3>
                    <p class="text-muted mb-0 font-weight-bold text-uppercase text-xs">Transfer Matches</p>
                    <small class="text-success font-weight-bold"><i class="fas fa-random mr-1"></i> Surplus &rarr; Deficit match</small>
                </div>
                <div class="icon">
                    <i class="fas fa-people-carry text-success" style="opacity: 0.15;"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-6">
            <div class="small-box bg-white shadow-sm border border-info">
                <div class="inner">
                    <h3 class="text-info font-weight-bold" id="kpiPoValue">₹ {{ number_format($summary['suggested_po_value'] ?? 0, 2) }}</h3>
                    <p class="text-muted mb-0 font-weight-bold text-uppercase text-xs">Suggested PO Value</p>
                    <small class="text-info font-weight-bold"><i class="fas fa-boxes mr-1"></i> {{ number_format($summary['suggested_po_units'] ?? 0) }} units across suppliers</small>
                </div>
                <div class="icon">
                    <i class="fas fa-shopping-cart text-info" style="opacity: 0.15;"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Visual Analytics Charts --}}
    <div class="row">
        <div class="col-md-6">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header py-2">
                    <h3 class="card-title font-weight-bold text-sm">
                        <i class="fas fa-chart-pie mr-1 text-primary"></i> Stock Health Condition
                    </h3>
                </div>
                <div class="card-body p-2" style="height: 190px; position: relative;">
                    <canvas id="stockHealthChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-outline card-success shadow-sm">
                <div class="card-header py-2">
                    <h3 class="card-title font-weight-bold text-sm">
                        <i class="fas fa-tachometer-alt mr-1 text-success"></i> Sales Movement Velocity (30 Days)
                    </h3>
                </div>
                <div class="card-body p-2" style="height: 190px; position: relative;">
                    <canvas id="salesVelocityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Workspace: Table + Side Action Panels --}}
    <div class="row">
        {{-- Left 8 Cols: Stock Analysis Data Table --}}
        <div class="col-lg-8">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header py-2 bg-light">
                    <h3 class="card-title font-weight-bold text-sm">
                        <i class="fas fa-list-alt text-primary mr-1"></i> Stock Replenishment Analysis
                    </h3>
                    <div class="card-tools d-flex align-items-center">
                        <span class="badge badge-info" id="totalFilteredRows">Loading...</span>
                        <button type="button" class="btn btn-outline-success btn-xs font-weight-bold shadow-sm ml-2" id="btnExportExcel" title="Export current filtered view to Excel spreadsheet">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </button>
                    </div>
                </div>

                {{-- Filter Toolbar --}}
                <div class="card-body bg-light border-bottom py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-6 mb-2">
                            <label class="text-xs text-muted font-weight-bold text-uppercase mb-1">Branch</label>
                            <select id="filterBranch" class="form-control form-control-sm">
                                <option value="all">All Branches</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" {{ $activeBranchId == $b->id ? 'selected' : '' }}>
                                        {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="text-xs text-muted font-weight-bold text-uppercase mb-1">Stock Status</label>
                            <select id="filterStatus" class="form-control form-control-sm">
                                <option value="all">All Statuses</option>
                                <option value="needs_action" selected>🚨 Needs Action</option>
                                <option value="Critical">🔴 Critical (0-3 Days)</option>
                                <option value="Low">🟡 Low (4-10 Days)</option>
                                <option value="Healthy">🟢 Healthy (11-30 Days)</option>
                                <option value="Overstock">🔵 Overstock (>30 Days)</option>
                                <option value="Dead Stock">⚪ Dead Stock</option>
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="text-xs text-muted font-weight-bold text-uppercase mb-1">Supplier</label>
                            <select id="filterSupplier" class="form-control form-control-sm">
                                <option value="all">All Suppliers</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 col-6 mb-2">
                            <label class="text-xs text-muted font-weight-bold text-uppercase mb-1">Search Item / SKU</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="SKU, Name, Barcode...">
                                <div class="input-group-append">
                                    <button class="btn btn-default" type="button" id="btnFilterSearch">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Table Container --}}
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped table-bordered text-sm mb-0" id="replenishmentTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Item Name (SKU)</th>
                                <th>Branch Stock</th>
                                <th class="text-center">30D Sales</th>
                                <th class="text-center">Avg/Day</th>
                                <th class="text-center">Cover Days</th>
                                <th class="text-center">Target Days</th>
                                <th class="text-center">Suggested Qty</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" style="width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Loading replenishment metrics...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Table Pagination Footer --}}
                <div class="card-footer py-2 bg-light d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="paginationInfo">Showing 0 of 0</small>
                    <ul class="pagination pagination-sm m-0" id="paginationControls"></ul>
                </div>
            </div>
        </div>

        {{-- Right 4 Cols: Quick Actions (Matchmaker & PO Grouping) --}}
        <div class="col-lg-4">
            {{-- Inter-Branch Transfer Matcher Widget --}}
            <div class="card card-outline card-success shadow-sm mb-3">
                <div class="card-header py-2">
                    <h3 class="card-title font-weight-bold text-sm">
                        <i class="fas fa-random text-success mr-1"></i> Smart Branch Transfers
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('inventory.replenishment.transfers') }}" class="btn btn-tool text-xs">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-2" id="transferWidgetBody">
                    @forelse($transferRecommendations->take(4) as $match)
                        <div class="p-2 mb-2 rounded border border-success bg-light text-xs">
                            <div class="d-flex justify-content-between font-weight-bold text-dark">
                                <span>{{ \Illuminate\Support\Str::limit($match->item_name, 25) }}</span>
                                <span class="badge badge-success px-2 py-1">Suggest: {{ $match->suggested_transfer_qty }} Qty</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted mt-1">
                                <span><i class="fas fa-arrow-circle-up text-info mr-1"></i> {{ $match->source_branch_name }} ({{ (int)$match->source_stock }})</span>
                                <i class="fas fa-long-arrow-alt-right text-success mx-1"></i>
                                <span><i class="fas fa-arrow-circle-down text-danger mr-1"></i> {{ $match->dest_branch_name }} ({{ (int)$match->dest_stock }})</span>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline-success btn-block mt-2 font-weight-bold" onclick="triggerQuickTransfer({{ $match->item_id }}, {{ $match->source_branch_id }}, {{ $match->dest_branch_id }}, {{ $match->suggested_transfer_qty }}, '{{ addslashes($match->item_name) }}')">
                                <i class="fas fa-dolly mr-1"></i> Dispatch Transfer
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted text-xs">
                            <i class="fas fa-check-circle text-success mb-1" style="font-size: 1.5rem;"></i><br>
                            All active stores are currently well balanced!
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Supplier-wise Order Recommendations Widget --}}
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h3 class="card-title font-weight-bold text-sm">
                        <i class="fas fa-truck text-primary mr-1"></i> Supplier Order Recommendations
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('inventory.replenishment.supplier-orders') }}" class="btn btn-tool text-xs">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-2" id="supplierWidgetBody">
                    @forelse($supplierOrders->take(5) as $order)
                        <div class="p-2 mb-2 rounded border bg-light d-flex justify-content-between align-items-center text-xs">
                            <div>
                                <strong class="text-dark">{{ \Illuminate\Support\Str::limit($order->supplier_name, 22) }}</strong>
                                <div class="text-muted">{{ $order->items_count }} items &bull; {{ $order->total_units }} units</div>
                            </div>
                            <div class="text-right">
                                <div class="font-weight-bold text-primary">₹ {{ number_format($order->estimated_value, 2) }}</div>
                                <button type="button" class="btn btn-xs btn-primary mt-1" onclick="openSupplierPoModal({{ $order->supplier_id ?? 'null' }}, '{{ addslashes($order->supplier_name) }}', {{ $order->items_count }}, '₹ {{ number_format($order->estimated_value, 2) }}', {{ json_encode($order->items->map(fn($m) => ['item_id' => $m->item_id, 'name' => $m->item->name ?? 'SKU #'.$m->item_id, 'qty' => $m->suggested_qty, 'cost' => (float)($m->item->cost_price ?? 0)])) }})">
                                    <i class="fas fa-file-invoice mr-1"></i> Review PO
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted text-xs">
                            <i class="fas fa-clipboard-check text-info mb-1" style="font-size: 1.5rem;"></i><br>
                            No pending supplier purchase orders needed right now.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Rules & Settings Modal --}}
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow">
                <div class="modal-header py-2 bg-light">
                    <h5 class="modal-title font-weight-bold text-sm" id="settingsModalLabel">
                        <i class="fas fa-sliders-h text-primary mr-1"></i> Replenishment Rules Configuration
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="settingsForm">
                    @csrf
                    <div class="modal-body p-3">
                        <h6 class="font-weight-bold text-xs text-uppercase text-muted mb-2">1. Sales Movement Target Cover Days</h6>
                        <p class="text-muted text-xs mb-3">Defines target buffer days maintained based on average daily velocity.</p>

                        @foreach($salesRules as $rule)
                            <div class="form-group row mb-2 align-items-center">
                                <label class="col-7 col-form-label text-xs">
                                    <strong>{{ $rule->rule_name }}</strong>
                                    <span class="text-muted">
                                        ({{ $rule->min_daily_avg }}{{ $rule->max_daily_avg ? ' - ' . $rule->max_daily_avg : '+' }} units/day)
                                    </span>
                                </label>
                                <div class="col-5">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="sales_rules[{{ $rule->id }}]" class="form-control text-center font-weight-bold" value="{{ $rule->target_cover_days }}" min="1" max="180">
                                        <div class="input-group-append">
                                            <span class="input-group-text text-xs">Days</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <hr class="my-3">
                        <h6 class="font-weight-bold text-xs text-uppercase text-muted mb-2">2. Stock Condition Statuses</h6>
                        <div class="row g-2">
                            <div class="col-6 mb-2">
                                <div class="p-2 rounded bg-danger text-white text-xs font-weight-bold text-center">
                                    Critical: 0 - 3 Days
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="p-2 rounded bg-warning text-dark text-xs font-weight-bold text-center">
                                    Low: 4 - 10 Days
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="p-2 rounded bg-success text-white text-xs font-weight-bold text-center">
                                    Healthy: 11 - 30 Days
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="p-2 rounded bg-info text-white text-xs font-weight-bold text-center">
                                    Overstock: > 30 Days
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer py-2 bg-light">
                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                            <i class="fas fa-save mr-1"></i> Save Changes & Recalculate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Supplier Review & 1-Click PO Modal --}}
    <div class="modal fade" id="poModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow">
                <div class="modal-header py-2 bg-light">
                    <h5 class="modal-title font-weight-bold text-sm" id="poModalTitle">
                        <i class="fas fa-file-invoice text-primary mr-1"></i> Purchase Order Recommendation
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3 text-sm">
                    <div class="bg-light p-2 rounded mb-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Vendor:</span> <strong id="modalVendorName" class="text-dark">-</strong><br>
                            <span class="text-muted">Target Store:</span> <strong id="modalBranchName" class="text-dark">Current Branch</strong>
                        </div>
                        <div class="text-right">
                            <span class="text-muted">Estimated Amount:</span><br>
                            <span class="font-weight-bold text-primary" id="modalEstimatedVal" style="font-size:1.1rem;">-</span>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-bordered text-xs mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Item SKU & Name</th>
                                    <th class="text-right">Unit Cost</th>
                                    <th class="text-center" style="width: 120px;">Order Qty</th>
                                    <th class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="poModalItemsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btnConfirmGeneratePo">
                        <i class="fas fa-check mr-1"></i> Confirm & Generate 1-Click PO
                    </button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    let stockHealthChartInstance = null;
    let salesVelocityChartInstance = null;
    let currentPage = 1;
    let currentModalSupplierId = null;
    let currentModalItems = [];

    $(document).ready(function() {
        initCharts();
        loadTableData(1);

        // Filter events
        $('#filterBranch, #filterStatus, #filterSupplier').on('change', function() {
            loadTableData(1);
        });

        $('#btnFilterSearch').on('click', function() {
            loadTableData(1);
        });

        $('#filterSearch').on('keypress', function(e) {
            if (e.which === 13) {
                loadTableData(1);
            }
        });

        // 1-Click Excel Export Trigger
        $('#btnExportExcel').on('click', function() {
            const params = new URLSearchParams({
                branch_id: $('#filterBranch').val(),
                status: $('#filterStatus').val(),
                supplier_id: $('#filterSupplier').val(),
                search: $('#filterSearch').val()
            });
            window.location.href = "{{ route('inventory.replenishment.export') }}?" + params.toString();
        });

        // Sync Trigger
        $('#btnSyncLive').on('click', function() {
            const $btn = $(this);
            const branchId = $('#filterBranch').val();
            $('#syncIcon').addClass('fa-spin');
            $('#syncText').text('Calculating...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ route('inventory.replenishment.sync') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    branch_id: branchId
                },
                success: function(res) {
                    $('#statusAlertMessage').text(res.message);
                    $('#statusAlert').removeClass('d-none');
                    if (res.summary) {
                        updateKpis(res.summary);
                        updateCharts(res.summary);
                    }
                    loadTableData(currentPage);
                },
                error: function(err) {
                    alert('Sync failed. Please check system logs.');
                },
                complete: function() {
                    $('#syncIcon').removeClass('fa-spin');
                    $('#syncText').text('Sync Live Stock');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Settings Save
        $('#settingsForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                url: "{{ route('inventory.replenishment.settings.update') }}",
                type: 'POST',
                data: formData,
                success: function(res) {
                    $('#settingsModal').modal('hide');
                    $('#statusAlertMessage').text(res.message);
                    $('#statusAlert').removeClass('d-none');
                    $('#btnSyncLive').trigger('click');
                },
                error: function() {
                    alert('Failed to update rules settings.');
                }
            });
        });

        // Confirm PO generation from modal
        $('#btnConfirmGeneratePo').on('click', function() {
            if (!currentModalSupplierId || currentModalItems.length === 0) {
                alert('No valid supplier or items to order.');
                return;
            }

            const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};
            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Creating PO...');

            const itemsPayload = [];
            $('.modal-item-qty').each(function() {
                const itemId = $(this).data('item-id');
                const qty = parseInt($(this).val()) || 0;
                if (qty > 0) {
                    itemsPayload.push({ item_id: itemId, qty: qty });
                }
            });

            if (itemsPayload.length === 0) {
                alert('Please enter at least one quantity greater than zero.');
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Confirm & Generate 1-Click PO');
                return;
            }

            $.ajax({
                url: "{{ route('inventory.replenishment.generate-po') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    supplier_id: currentModalSupplierId,
                    branch_id: branchId,
                    items: itemsPayload
                },
                success: function(res) {
                    $('#poModal').modal('hide');
                    $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-dark" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Order</a>`);
                    $('#statusAlert').removeClass('d-none');
                    loadTableData(currentPage);
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to generate Purchase Order.';
                    alert(msg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Confirm & Generate 1-Click PO');
                }
            });
        });
    });

    function initCharts() {
        const initialStatus = @json($summary['status_chart'] ?? []);
        const initialVelocity = @json($summary['velocity_chart'] ?? []);

        // Donut Chart: Stock Health
        const ctxHealth = document.getElementById('stockHealthChart').getContext('2d');
        stockHealthChartInstance = new Chart(ctxHealth, {
            type: 'doughnut',
            data: {
                labels: ['Critical (0-3d)', 'Low (4-10d)', 'Healthy (11-30d)', 'Overstock (>30d)', 'Dead Stock'],
                datasets: [{
                    data: [
                        initialStatus['Critical'] || 0,
                        initialStatus['Low'] || 0,
                        initialStatus['Healthy'] || 0,
                        initialStatus['Overstock'] || 0,
                        initialStatus['Dead Stock'] || 0
                    ],
                    backgroundColor: ['#dc3545', '#ffc107', '#28a745', '#17a2b8', '#6c757d'],
                    borderWidth: 1.5,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } }
                }
            }
        });

        // Bar Chart: Velocity
        const ctxVelocity = document.getElementById('salesVelocityChart').getContext('2d');
        salesVelocityChartInstance = new Chart(ctxVelocity, {
            type: 'bar',
            data: {
                labels: ['Highest (20+)', 'Fast (10-20)', 'Normal (5-10)', 'Slow (1-5)', 'Dead (<1)'],
                datasets: [{
                    label: 'SKUs',
                    data: [
                        initialVelocity['Highest Moving'] || 0,
                        initialVelocity['Fast Moving'] || 0,
                        initialVelocity['Normal Moving'] || 0,
                        initialVelocity['Slow Moving'] || 0,
                        initialVelocity['Dead Stock'] || 0
                    ],
                    backgroundColor: ['#6f42c1', '#007bff', '#28a745', '#fd7e14', '#adb5bd'],
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { font: { size: 9.5 } } },
                    x: { ticks: { font: { size: 9.5 } } }
                }
            }
        });
    }

    function updateKpis(summary) {
        $('#kpiCriticalCount').text(Number(summary.critical_count || 0).toLocaleString());
        $('#kpiLowCount').text(Number(summary.low_count || 0).toLocaleString());
        $('#kpiTransferCount').text(Number(summary.transfer_opportunities_count || 0).toLocaleString());
        $('#kpiPoValue').text('₹ ' + Number(summary.suggested_po_value || 0).toLocaleString(undefined, {minimumFractionDigits: 2}));
    }

    function updateCharts(summary) {
        if (stockHealthChartInstance && summary.status_chart) {
            stockHealthChartInstance.data.datasets[0].data = [
                summary.status_chart['Critical'] || 0,
                summary.status_chart['Low'] || 0,
                summary.status_chart['Healthy'] || 0,
                summary.status_chart['Overstock'] || 0,
                summary.status_chart['Dead Stock'] || 0
            ];
            stockHealthChartInstance.update();
        }

        if (salesVelocityChartInstance && summary.velocity_chart) {
            salesVelocityChartInstance.data.datasets[0].data = [
                summary.velocity_chart['Highest Moving'] || 0,
                summary.velocity_chart['Fast Moving'] || 0,
                summary.velocity_chart['Normal Moving'] || 0,
                summary.velocity_chart['Slow Moving'] || 0,
                summary.velocity_chart['Dead Stock'] || 0
            ];
            salesVelocityChartInstance.update();
        }
    }

    function loadTableData(page = 1) {
        currentPage = page;
        $('#tableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Loading data...</td></tr>');

        $.ajax({
            url: "{{ route('inventory.replenishment.data') }}",
            type: 'GET',
            data: {
                branch_id: $('#filterBranch').val(),
                status: $('#filterStatus').val(),
                supplier_id: $('#filterSupplier').val(),
                search: $('#filterSearch').val(),
                page: page
            },
            success: function(res) {
                renderTable(res.data);
                renderPagination(res);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="9" class="text-center py-3 text-danger"><i class="fas fa-exclamation-circle mr-1"></i> Failed to load replenishment data.</td></tr>');
            }
        });
    }

    function renderTable(rows) {
        if (!rows || rows.length === 0) {
            $('#tableBody').html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-box-open mr-1"></i> No items found matching the selected filter criteria.</td></tr>');
            return;
        }

        let html = '';
        rows.forEach(function(row) {
            const item = row.item || {};
            const itemName = item.name || 'Unnamed Item #' + row.item_id;
            const itemCode = item.item_code || '-';
            const brand = (item.brand && item.brand.name) ? item.brand.name : '-';
            const curStock = Number(row.current_stock || 0).toFixed(0);
            const othStock = Number(row.other_branch_stock || 0).toFixed(0);
            const sales30d = Number(row.sales_30d || 0).toFixed(0);
            const avgDay = Number(row.daily_avg_sales || 0).toFixed(2);
            const coverDays = Number(row.cover_days || 0).toFixed(1);
            const targetDays = row.target_cover_days || 0;
            const suggestedQty = Number(row.suggested_qty || 0);
            const status = row.stock_status || 'Healthy';
            const supplierId = row.primary_supplier_id || (item.supplier_id || null);

            let statusBadgeClass = 'badge-secondary';
            if (status === 'Critical') statusBadgeClass = 'badge-danger';
            else if (status === 'Low') statusBadgeClass = 'badge-warning';
            else if (status === 'Healthy') statusBadgeClass = 'badge-success';
            else if (status === 'Overstock') statusBadgeClass = 'badge-info';

            let coverDaysColor = 'text-dark';
            if (status === 'Critical') coverDaysColor = 'text-danger font-weight-bold';
            else if (status === 'Low') coverDaysColor = 'text-warning font-weight-bold';

            html += `
                <tr>
                    <td>
                        <div class="font-weight-bold text-dark">${escapeHtml(itemName)}</div>
                        <small class="text-muted">SKU: ${escapeHtml(itemCode)} &bull; ${escapeHtml(brand)}</small>
                    </td>
                    <td>
                        <span class="font-weight-bold">${curStock}</span>
                        ${othStock > 0 ? `<br><small class="text-muted"><i class="fas fa-warehouse mr-1 text-xs"></i> Other: ${othStock}</small>` : ''}
                    </td>
                    <td class="text-center">${sales30d}</td>
                    <td class="text-center">${avgDay}</td>
                    <td class="text-center ${coverDaysColor}">${coverDays} d</td>
                    <td class="text-center text-muted">${targetDays} d</td>
                    <td class="text-center">
                        ${suggestedQty > 0 
                            ? `<span class="badge badge-danger px-2 py-1" style="font-size:0.9rem;">${suggestedQty}</span>` 
                            : `<span class="text-muted">0</span>`
                        }
                    </td>
                    <td class="text-center">
                        <span class="badge ${statusBadgeClass} px-2 py-1">${status}</span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-xs">
                            ${suggestedQty > 0 && supplierId
                                ? `<button type="button" class="btn btn-primary btn-xs mr-1 shadow-sm font-weight-bold" onclick="triggerSingleItemPo(${row.item_id}, ${supplierId}, ${suggestedQty}, '${escapeJs(itemName)}')" title="Generate PO for this item">
                                     <i class="fas fa-cart-plus mr-1"></i> Order
                                   </button>` 
                                : ''
                            }
                            ${othStock > 0 && (status === 'Critical' || status === 'Low')
                                ? `<a href="{{ route('inventory.replenishment.transfers') }}" class="btn btn-outline-success btn-xs shadow-sm font-weight-bold" title="View available transfer sources">
                                     <i class="fas fa-random mr-1"></i> Transfer
                                   </a>` 
                                : ''
                            }
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
    }

    function renderPagination(res) {
        $('#totalFilteredRows').text(res.total + ' Items Found');
        $('#paginationInfo').text(`Showing page ${res.current_page} of ${res.last_page} (${res.total} total items)`);

        let html = '';
        if (res.last_page > 1) {
            html += `<li class="page-item ${res.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTableData(${res.current_page - 1})">&laquo;</a>
                     </li>`;

            let start = Math.max(1, res.current_page - 2);
            let end = Math.min(res.last_page, res.current_page + 2);

            for (let i = start; i <= end; i++) {
                html += `<li class="page-item ${i === res.current_page ? 'active' : ''}">
                            <a class="page-link" href="javascript:void(0)" onclick="loadTableData(${i})">${i}</a>
                         </li>`;
            }

            html += `<li class="page-item ${res.current_page === res.last_page ? 'disabled' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="loadTableData(${res.current_page + 1})">&raquo;</a>
                     </li>`;
        }

        $('#paginationControls').html(html);
    }

    function triggerSingleItemPo(itemId, supplierId, qty, itemName) {
        if (!confirm(`Generate 1-Click Purchase Order for ${itemName} (${qty} units)?`)) {
            return;
        }

        const branchId = $('#filterBranch').val() !== 'all' ? $('#filterBranch').val() : {{ $activeBranchId }};

        $.ajax({
            url: "{{ route('inventory.replenishment.generate-po') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                supplier_id: supplierId,
                branch_id: branchId,
                items: [{ item_id: itemId, qty: qty }]
            },
            success: function(res) {
                $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-dark" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Order</a>`);
                $('#statusAlert').removeClass('d-none');
                loadTableData(currentPage);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error generating Purchase Order.');
            }
        });
    }

    function triggerQuickTransfer(itemId, fromBranchId, toBranchId, qty, itemName) {
        if (!confirm(`Dispatch ${qty} units of ${itemName} from source store?`)) {
            return;
        }

        $.ajax({
            url: "{{ route('inventory.replenishment.create-transfer') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                from_branch_id: fromBranchId,
                to_branch_id: toBranchId,
                items: [{ item_id: itemId, qty: qty }]
            },
            success: function(res) {
                $('#statusAlertMessage').html(`${res.message} <a href="${res.redirect_url}" class="font-weight-bold ml-2 text-dark" target="_blank"><i class="fas fa-external-link-alt mr-1"></i> View Transfer</a>`);
                $('#statusAlert').removeClass('d-none');
                loadTableData(currentPage);
            },
            error: function(xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error creating Stock Transfer.');
            }
        });
    }

    function openSupplierPoModal(supplierId, supplierName, count, val, items) {
        currentModalSupplierId = supplierId;
        currentModalItems = items || [];

        $('#poModalTitle').html(`<i class="fas fa-truck text-primary mr-1"></i> PO Recommendation for ${supplierName}`);
        $('#modalVendorName').text(supplierName);
        $('#modalEstimatedVal').text(val);

        let rowsHtml = '';
        currentModalItems.forEach(function(item) {
            rowsHtml += `
                <tr>
                    <td class="align-middle"><strong>${escapeHtml(item.name)}</strong></td>
                    <td class="text-right align-middle">₹ ${Number(item.cost || 0).toFixed(2)}</td>
                    <td class="text-center align-middle">
                        <input type="number" class="form-control form-control-sm text-center font-weight-bold modal-item-qty" data-item-id="${item.item_id}" value="${item.qty}" min="1">
                    </td>
                    <td class="text-right align-middle font-weight-bold">₹ ${(item.qty * (item.cost || 0)).toFixed(2)}</td>
                </tr>
            `;
        });

        $('#poModalItemsTableBody').html(rowsHtml);
        $('#poModal').modal('show');
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeJs(text) {
        if (!text) return '';
        return String(text).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>
@stop
