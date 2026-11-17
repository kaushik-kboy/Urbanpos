@extends('adminlte::page')

@section('title', $title ?? 'Report')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div class="mb-2 mb-md-0">
            <h1 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-chart-line text-primary mr-2"></i> {{ $title ?? 'Report' }}
            </h1>
            <p class="text-muted small mb-0 mt-1">
                {{ $subtitle ?? 'UrbanPOS SmartReport Analytics Engine &bull; Auto-synchronized with live store database' }}
            </p>
        </div>
        <div class="d-flex align-items-center">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm mr-2">
                <i class="fas fa-th-large mr-1"></i> Reports Center
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- Top KPI Cards commented out as per requirement (no direct click/detail destination)
    @if (!empty($kpis) && is_array($kpis))
        <div class="row mb-3">
            @foreach ($kpis as $kpi)
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-2">
                        <span class="info-box-icon bg-{{ $kpi['color'] ?? 'primary' }} elevation-1">
                            <i class="{{ $kpi['icon'] ?? 'fas fa-chart-bar' }}"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted text-uppercase font-weight-bold" style="font-size: 0.75rem;">
                                {{ $kpi['label'] }}
                            </span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.15rem;">
                                {{ $kpi['value'] }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    --}}

    {{-- Filter Card --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ url()->current() }}" class="form-row align-items-end" id="genericReportFilterForm">
                @if ($hasDateFilter ?? true)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">From Date</label>
                        <input type="date" name="from" id="filter_from" class="form-control form-control-sm" value="{{ $from ?? now()->subDays(60)->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">To Date</label>
                        <input type="date" name="to" id="filter_to" class="form-control form-control-sm" value="{{ $to ?? now()->format('Y-m-d') }}">
                    </div>
                @endif

                @if ($hasBranchFilter ?? true)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Branch / Location</label>
                        <select name="branch_id" id="filter_branch_id" class="form-control form-control-sm" {{ $branches->count() <= 1 ? 'readonly style=pointer-events:none;background:#f4f6f9;' : '' }}>
                            @if($branches->count() > 1)
                                <option value="all" @selected(empty($branchId) || request('branch_id') === 'all')>All Branches / Locations</option>
                            @endif
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" @selected(!empty($branchId) && (string)$branchId === (string)$id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($hasBrandFilter ?? false)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Brand</label>
                        <select name="brand_id" id="filter_brand_id" class="form-control form-control-sm select2" data-placeholder="All Brands">
                            <option value="">All Brands</option>
                            @if (isset($brands))
                                @foreach ($brands as $bId => $bName)
                                    <option value="{{ $bId }}" @selected((string)request('brand_id') === (string)$bId)>{{ $bName }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                @endif

                @if ($hasSupplierFilter ?? false)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Supplier</label>
                        <select name="supplier_id" id="filter_supplier_id" class="form-control form-control-sm select2" data-placeholder="All Suppliers">
                            <option value="">All Suppliers</option>
                            @if (isset($suppliers))
                                @foreach ($suppliers as $sId => $sName)
                                    <option value="{{ $sId }}" @selected((string)request('supplier_id') === (string)$sId)>{{ $sName }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                @endif

                @if ($hasRiskStatusFilter ?? false)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Risk Status</label>
                        <select name="risk_status" id="risk_status_select" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="expired" @selected(request('risk_status') === 'expired')>🔴 Expired (&lt; 0 Days)</option>
                            <option value="under_30" @selected(request('risk_status') === 'under_30')>🟠 &lt;30 Days (0–30 Days)</option>
                            <option value="31_60" @selected(request('risk_status') === '31_60')>🟡 31–60 Days</option>
                            <option value="61_90" @selected(request('risk_status') === '61_90')>🔵 61–90 Days</option>
                            <option value="above_90" @selected(request('risk_status') === 'above_90')>🟢 90+ Days / Fresh</option>
                            <option value="fresh" @selected(request('risk_status') === 'fresh')>🟢 Fresh Stock</option>
                            <option value="custom" @selected(request('risk_status') === 'custom')>⚙️ Custom Days Range</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0 {{ request('risk_status') === 'custom' ? '' : 'd-none' }}" id="custom_days_wrapper">
                        <label class="small font-weight-bold text-muted mb-1">Days Range (Min - Max)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="min_days" class="form-control" placeholder="Min d" value="{{ request('min_days') }}">
                            <input type="number" name="max_days" class="form-control" placeholder="Max d" value="{{ request('max_days') }}">
                        </div>
                    </div>
                @endif

                @if ($hasVarianceTypeFilter ?? false)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Variance Type</label>
                        <select name="variance_type" class="form-control form-control-sm">
                            <option value="">All Types</option>
                            <option value="excess" @selected(request('variance_type') === 'excess')>🟢 Excess (Physical &gt; System)</option>
                            <option value="shortage" @selected(request('variance_type') === 'shortage')>🔴 Shortage (Physical &lt; System)</option>
                            <option value="no_diff" @selected(request('variance_type') === 'no_diff')>⚪ No Difference (Matched)</option>
                        </select>
                    </div>
                @endif

                @if ($hasStatusFilter ?? false)
                    <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                        <label class="small font-weight-bold text-muted mb-1">Status</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="Pending" @selected(request('status') === 'Pending')>🟡 Pending</option>
                            <option value="Approved" @selected(request('status') === 'Approved')>🟢 Approved</option>
                            <option value="Rejected" @selected(request('status') === 'Rejected')>🔴 Rejected</option>
                        </select>
                    </div>
                @endif

                <div class="col-md-2 col-sm-6 mb-2 mb-md-0 flex-grow-1">
                    <label class="small font-weight-bold text-muted mb-1">Search Keywords</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, code, ref..." value="{{ $search ?? '' }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 d-flex mb-2 mb-md-0">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill mr-1 shadow-sm font-weight-bold">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                    <button type="submit" name="export" value="excel" class="btn btn-success btn-sm font-weight-bold shadow-sm mr-1">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </button>
                    <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm shadow-sm" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Report Data Card --}}
    <div class="card card-outline card-secondary shadow-sm">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-table mr-1 text-primary"></i> Data Records
                @if (isset($rows) && method_exists($rows, 'total'))
                    <span class="badge badge-light border ml-1 font-weight-normal">{{ number_format($rows->total()) }} total</span>
                @endif
            </h5>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary mr-2 shadow-sm" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
                @if(($module ?? null) === 'gst-sales-taxwise')
                    {{-- Real server-side .xlsx export: one bill = one row, full
                         GST rate breakup as columns, all matching bills (not just
                         this page's 50) — see docs/GST-SALES-TAXWISE-EXPORT.md --}}
                    <a href="{{ route('reports.gst-sales-taxwise.export', ['from' => $from ?? null, 'to' => $to ?? null, 'branch_id' => $branchId ?? null, 'search' => $search ?? null]) }}"
                       class="btn btn-sm btn-success mr-2 shadow-sm font-weight-bold">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel (GST Taxwise)
                    </a>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}"
                       class="btn btn-sm btn-success mr-2 shadow-sm font-weight-bold">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </a>
                @endif
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="btn btn-sm btn-outline-success mr-2 shadow-sm font-weight-bold" title="Download all records as CSV">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </a>
                <x-table-column-customizer :table-key="'reports.generic.' . ($module ?? $slug ?? 'default')" table-id="reportDataTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0" id="reportDataTable">
                    <thead class="bg-light">
                        <tr>
                            @if (!empty($columns) && is_array($columns))
                                @foreach ($columns as $cIdx => $col)
                                    <th class="{{ $column_alignments[$cIdx] ?? 'text-left' }} py-2 text-uppercase text-secondary" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                        {{ $col }}
                                    </th>
                                @endforeach
                            @else
                                <th class="text-center">#</th>
                                <th>Particulars</th>
                                <th>Details</th>
                                <th class="text-right">Amount</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @if (isset($rows) && count($rows) > 0)
                            @foreach ($rows as $index => $row)
                                <tr>
                                    <td class="text-center font-weight-bold text-muted" style="width: 50px;">
                                        @if (method_exists($rows, 'firstItem'))
                                            {{ $rows->firstItem() + $index }}
                                        @else
                                            {{ $index + 1 }}
                                        @endif
                                    </td>
                                    @if (isset($row['cells']) && is_array($row['cells']))
                                        @foreach ($row['cells'] as $cIdx => $cell)
                                            <td class="{{ $column_alignments[$cIdx + 1] ?? 'text-left' }}">
                                                {!! $cell !!}
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ !empty($columns) ? count($columns) : 4 }}" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <h5 class="font-weight-bold text-dark">No Records Found</h5>
                                    <p class="small text-muted mb-2">There are currently no transactions matching your selected criteria in this period.</p>
                                    @if ($hasDateFilter ?? true)
                                        <span class="badge badge-light border px-2 py-1">Period: {{ date('d M Y', strtotime($from ?? now())) }} - {{ date('d M Y', strtotime($to ?? now())) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                    @if (isset($footer_totals) && isset($rows) && count($rows) > 0)
                        <tfoot class="bg-light font-weight-bold border-top">
                            {{-- Page Total --}}
                            <tr class="table-secondary">
                                <td colspan="8" class="text-right text-dark font-weight-bold">
                                    <i class="fas fa-file-alt text-secondary mr-1"></i> Page Total:
                                </td>
                                <td class="text-right text-dark">{{ number_format($footer_totals['page']['sys_qty']) }}</td>
                                <td class="text-right text-primary">{{ number_format($footer_totals['page']['phys_qty']) }}</td>
                                <td class="text-right {{ $footer_totals['page']['delta_qty'] < 0 ? 'text-danger' : ($footer_totals['page']['delta_qty'] > 0 ? 'text-success' : 'text-muted') }}">
                                    {{ ($footer_totals['page']['delta_qty'] > 0 ? '+' : '') . number_format($footer_totals['page']['delta_qty']) }}
                                </td>
                                <td class="text-right text-dark">₹ {{ number_format($footer_totals['page']['inventory_cost'], 2) }}</td>
                                <td class="text-right {{ $footer_totals['page']['variance_val'] < 0 ? 'text-danger' : ($footer_totals['page']['variance_val'] > 0 ? 'text-success' : 'text-muted') }}">
                                    {{ ($footer_totals['page']['variance_val'] > 0 ? '+' : '') . '₹ ' . number_format($footer_totals['page']['variance_val'], 2) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                            {{-- Filtered Grand Total --}}
                            <tr class="bg-white border-top">
                                <td colspan="8" class="text-right text-dark font-weight-bold" style="font-size: 0.95rem;">
                                    <i class="fas fa-calculator text-primary mr-1"></i> Filtered Period Grand Total:
                                </td>
                                <td class="text-right text-dark font-weight-bold" style="font-size: 0.95rem;">{{ number_format($footer_totals['grand']['sys_qty']) }}</td>
                                <td class="text-right text-primary font-weight-bold" style="font-size: 0.95rem;">{{ number_format($footer_totals['grand']['phys_qty']) }}</td>
                                <td class="text-right font-weight-bold {{ $footer_totals['grand']['delta_qty'] < 0 ? 'text-danger' : ($footer_totals['grand']['delta_qty'] > 0 ? 'text-success' : 'text-muted') }}" style="font-size: 0.95rem;">
                                    {{ ($footer_totals['grand']['delta_qty'] > 0 ? '+' : '') . number_format($footer_totals['grand']['delta_qty']) }}
                                </td>
                                <td class="text-right text-dark font-weight-bold" style="font-size: 0.95rem;">₹ {{ number_format($footer_totals['grand']['inventory_cost'], 2) }}</td>
                                <td class="text-right font-weight-bold {{ $footer_totals['grand']['variance_val'] < 0 ? 'text-danger' : ($footer_totals['grand']['variance_val'] > 0 ? 'text-success' : 'text-muted') }}" style="font-size: 0.95rem;">
                                    {{ ($footer_totals['grand']['variance_val'] > 0 ? '+' : '') . '₹ ' . number_format($footer_totals['grand']['variance_val'], 2) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="card-footer bg-light py-2 d-flex flex-wrap justify-content-between align-items-center">
            <div class="small text-muted mb-2 mb-md-0">
                @if (isset($rows) && method_exists($rows, 'total'))
                    Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} entries
                @else
                    Report generated on {{ now()->format('d M Y, h:i A') }}
                @endif
            </div>
            <div>
                @if (isset($rows) && method_exists($rows, 'links'))
                    {{ $rows->links('pagination::bootstrap-4') }}
                @endif
            </div>
        </div>
    </div>

    {{-- Task 4: Stock Statement Transaction Drilldown Modal --}}
    <div class="modal fade" id="stockStatementModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title font-weight-bold mb-0">
                        <i class="fas fa-boxes text-warning mr-2"></i>
                        <span id="ssModalItemName">Stock Transactions</span> &bull;
                        <span id="ssModalFlowTitle" class="badge badge-info ml-1">Detail</span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center">
                        <div class="small mb-2 mb-md-0">
                            <strong>Period:</strong> <span id="ssModalPeriod" class="badge badge-light border px-2 py-1 mr-2">-</span>
                            <strong>Item Code:</strong> <code id="ssModalItemCode" class="mr-2">-</code>
                            <strong>Brand:</strong> <span id="ssModalBrand" class="text-muted mr-2">-</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center mb-2 mb-md-0">
                            <label class="small font-weight-bold text-muted mb-0 mr-2"><i class="fas fa-filter text-primary mr-1"></i> Trans Type:</label>
                            <select id="ssModalTransTypeFilter" class="form-control form-control-sm font-weight-bold mr-2" style="width: 210px;">
                                <option value="all">All Transaction Types</option>
                            </select>
                            <div class="input-group input-group-sm" style="width: 170px;">
                                <input type="text" id="ssModalSearchInput" class="form-control" placeholder="Search bill, party...">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="font-weight-bold" id="ssModalNetBadge">
                            Total Net Flow: <span class="badge badge-primary px-2 py-1" id="ssModalNetQty">0</span>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 520px;">
                        <table class="table table-sm table-striped table-hover mb-0" id="ssDrilldownTable">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="text-center" style="width: 45px;">#</th>
                                    <th>Date</th>
                                    <th>Document No</th>
                                    <th>Transaction Type</th>
                                    <th class="text-right">Quantity</th>
                                    <th>Party / Particulars</th>
                                    <th>Branch / Store</th>
                                </tr>
                            </thead>
                            <tbody id="ssDrilldownTbody">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-spinner fa-spin mr-1"></i> Loading transactions...
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-light font-weight-bold" id="ssDrilldownTfoot" style="display:none;">
                                <tr>
                                    <td colspan="4" class="text-right">Period Total Quantity:</td>
                                    <td class="text-right" id="ssTfootTotalQty">0</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
    $(function() {
        // Task 1: Brand ⇄ Supplier dynamic bidirectional Select2 linking
        var brandSupplierMap = @json($brandSupplierMap ?? []);
        var supplierBrandMap = @json($supplierBrandMap ?? []);

        var $brandSelect = $('#filter_brand_id');
        var $supplierSelect = $('#filter_supplier_id');

        if ($brandSelect.length && $supplierSelect.length && (Object.keys(brandSupplierMap).length > 0 || Object.keys(supplierBrandMap).length > 0)) {
            // Cache all master options from initial DOM
            var allBrands = [];
            $brandSelect.find('option').each(function() {
                var val = $(this).val();
                if (val !== '') {
                    allBrands.push({ id: String(val), text: $(this).text() });
                }
            });

            var allSuppliers = [];
            $supplierSelect.find('option').each(function() {
                var val = $(this).val();
                if (val !== '') {
                    allSuppliers.push({ id: String(val), text: $(this).text() });
                }
            });

            function updateSupplierDropdown(selectedBrandId, keepCurrent) {
                var currentSupplier = keepCurrent ? String($supplierSelect.val() || '') : '';
                var targetSuppliers = allSuppliers;

                if (selectedBrandId && brandSupplierMap[selectedBrandId]) {
                    var allowedSet = new Set(brandSupplierMap[selectedBrandId].map(String));
                    targetSuppliers = allSuppliers.filter(function(s) {
                        return allowedSet.has(s.id);
                    });
                }

                $supplierSelect.empty();
                $supplierSelect.append(new Option('All Suppliers', '', true, currentSupplier === ''));

                var hasMatch = false;
                targetSuppliers.forEach(function(s) {
                    var isSelected = (currentSupplier === s.id);
                    if (isSelected) hasMatch = true;
                    $supplierSelect.append(new Option(s.text, s.id, isSelected, isSelected));
                });

                if (!hasMatch && currentSupplier !== '') {
                    $supplierSelect.val('');
                }
                $supplierSelect.trigger('change.select2');
            }

            function updateBrandDropdown(selectedSupplierId, keepCurrent) {
                var currentBrand = keepCurrent ? String($brandSelect.val() || '') : '';
                var targetBrands = allBrands;

                if (selectedSupplierId && supplierBrandMap[selectedSupplierId]) {
                    var allowedSet = new Set(supplierBrandMap[selectedSupplierId].map(String));
                    targetBrands = allBrands.filter(function(b) {
                        return allowedSet.has(b.id);
                    });
                }

                $brandSelect.empty();
                $brandSelect.append(new Option('All Brands', '', true, currentBrand === ''));

                var hasMatch = false;
                targetBrands.forEach(function(b) {
                    var isSelected = (currentBrand === b.id);
                    if (isSelected) hasMatch = true;
                    $brandSelect.append(new Option(b.text, b.id, isSelected, isSelected));
                });

                if (!hasMatch && currentBrand !== '') {
                    $brandSelect.val('');
                }
                $brandSelect.trigger('change.select2');
            }

            var isUpdating = false;

            $brandSelect.on('change', function(e) {
                if (isUpdating) return;
                isUpdating = true;
                var bId = String($(this).val() || '');
                if (bId) {
                    updateSupplierDropdown(bId, true);
                } else {
                    var sId = String($supplierSelect.val() || '');
                    if (sId) {
                        updateBrandDropdown(sId, true);
                    } else {
                        updateSupplierDropdown('', true);
                        updateBrandDropdown('', true);
                    }
                }
                isUpdating = false;
            });

            $supplierSelect.on('change', function(e) {
                if (isUpdating) return;
                isUpdating = true;
                var sId = String($(this).val() || '');
                if (sId) {
                    updateBrandDropdown(sId, true);
                } else {
                    var bId = String($brandSelect.val() || '');
                    if (bId) {
                        updateSupplierDropdown(bId, true);
                    } else {
                        updateSupplierDropdown('', true);
                        updateBrandDropdown('', true);
                    }
                }
                isUpdating = false;
            });

            // Initialize state on page load based on current selections
            var initBrand = String($brandSelect.val() || '');
            var initSupplier = String($supplierSelect.val() || '');
            if (initBrand && !initSupplier) {
                updateSupplierDropdown(initBrand, true);
            } else if (initSupplier && !initBrand) {
                updateBrandDropdown(initSupplier, true);
            }
        }

        // Toggle custom days input when risk status is 'custom'
        $('#risk_status_select').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#custom_days_wrapper').removeClass('d-none');
            } else {
                $('#custom_days_wrapper').addClass('d-none');
            }
        });

        var currentStockRecords = [];

        function renderStockDrilldownRows(filterType, searchText) {
            filterType = filterType || 'all';
            searchText = (searchText || '').toLowerCase().trim();

            var filtered = currentStockRecords.filter(function(r) {
                var matchType = true;
                if (filterType === 'inward_all') {
                    matchType = (r.qty > 0);
                } else if (filterType === 'outward_all') {
                    matchType = (r.qty < 0);
                } else if (filterType !== 'all') {
                    matchType = (r.trans_type === filterType);
                }

                var matchSearch = true;
                if (searchText) {
                    var docNo = (r.doc_no || '').toLowerCase();
                    var party = (r.party || '').toLowerCase();
                    var branch = (r.branch || '').toLowerCase();
                    var transType = (r.trans_type || '').toLowerCase();
                    matchSearch = docNo.includes(searchText) || party.includes(searchText) || branch.includes(searchText) || transType.includes(searchText);
                }

                return matchType && matchSearch;
            });

            if (filtered.length === 0) {
                $('#ssDrilldownTbody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-info-circle mr-1"></i> No transactions found matching selected filter.</td></tr>');
                $('#ssTfootTotalQty').text('0');
                $('#ssModalNetQty').text('0');
                return;
            }

            var html = '';
            var totalQty = 0;
            filtered.forEach(function(r, idx) {
                totalQty += parseFloat(r.qty) || 0;
                html += '<tr>' +
                    '<td class="text-center text-muted font-weight-bold">' + (idx + 1) + '</td>' +
                    '<td>' + (r.date || '-') + '</td>' +
                    '<td><strong class="text-dark">' + (r.doc_no || '-') + '</strong></td>' +
                    '<td><span class="badge badge-light border">' + (r.trans_type || '-') + '</span></td>' +
                    '<td class="text-right ' + (r.class || '') + '">' + (r.qty_formatted || r.qty) + '</td>' +
                    '<td>' + (r.party || '-') + '</td>' +
                    '<td><small class="text-muted">' + (r.branch || '-') + '</small></td>' +
                    '</tr>';
            });

            $('#ssDrilldownTbody').html(html);
            var netFormatted = (totalQty > 0 ? '+' : '') + totalQty.toLocaleString();
            $('#ssTfootTotalQty').text(netFormatted);
            $('#ssModalNetQty').text(netFormatted);
            $('#ssDrilldownTfoot').show();
        }

        $(document).on('change', '#ssModalTransTypeFilter', function() {
            renderStockDrilldownRows($(this).val(), $('#ssModalSearchInput').val());
        });

        $(document).on('input', '#ssModalSearchInput', function() {
            renderStockDrilldownRows($('#ssModalTransTypeFilter').val(), $(this).val());
        });

        // Task 4: Stock Statement Quantity Drilldown Click
        $(document).on('click', '.btn-stock-drilldown', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var itemId = $btn.data('item-id');
            var branchId = $btn.data('branch-id') || $('#filter_branch_id').val() || 'all';
            var flow = $btn.data('flow') || 'all';
            var itemName = $btn.data('item-name') || 'Item';
            var from = $('#filter_from').val() || '';
            var to = $('#filter_to').val() || '';

            $('#ssModalItemName').text(itemName);
            $('#ssModalFlowTitle').text(flow.toUpperCase());
            $('#ssDrilldownTbody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Loading underlying transactions...</td></tr>');
            $('#ssDrilldownTfoot').hide();
            $('#stockStatementModal').modal('show');

            $.ajax({
                url: '{{ route("reports.stock-statement.drilldown") }}',
                method: 'GET',
                data: {
                    item_id: itemId,
                    branch_id: branchId,
                    flow: flow,
                    from: from,
                    to: to
                },
                dataType: 'json',
                success: function(resp) {
                    if (!resp.success) {
                        $('#ssDrilldownTbody').html('<tr><td colspan="7" class="text-center py-4 text-danger">' + (resp.message || 'Error loading transactions') + '</td></tr>');
                        return;
                    }

                    $('#ssModalItemCode').text(resp.item_code || '-');
                    $('#ssModalBrand').text(resp.brand || '-');
                    $('#ssModalPeriod').text(resp.period || '-');
                    $('#ssModalFlowTitle').text(resp.flow_title || flow.toUpperCase());
                    $('#ssModalNetQty').text(resp.net_qty > 0 ? ('+' + resp.net_qty) : resp.net_qty);

                    currentStockRecords = resp.records || [];

                    // Populate Transaction Type filter dropdown dynamically
                    var $filter = $('#ssModalTransTypeFilter');
                    $filter.empty();
                    $filter.append(new Option('All Transaction Types (' + currentStockRecords.length + ')', 'all', true, true));

                    var typeCounts = {};
                    var inwardCount = 0;
                    var outwardCount = 0;

                    currentStockRecords.forEach(function(r) {
                        var t = r.trans_type || 'Other';
                        typeCounts[t] = (typeCounts[t] || 0) + 1;
                        if (r.qty > 0) inwardCount++;
                        if (r.qty < 0) outwardCount++;
                    });

                    if (inwardCount > 0 && outwardCount > 0) {
                        $filter.append(new Option('🟢 All Inward (+) (' + inwardCount + ')', 'inward_all'));
                        $filter.append(new Option('🔴 All Outward (-) (' + outwardCount + ')', 'outward_all'));
                    }

                    Object.keys(typeCounts).sort().forEach(function(t) {
                        $filter.append(new Option(t + ' (' + typeCounts[t] + ')', t));
                    });

                    $('#ssModalSearchInput').val('');
                    renderStockDrilldownRows('all', '');
                },
                error: function(xhr) {
                    $('#ssDrilldownTbody').html('<tr><td colspan="7" class="text-center py-4 text-danger">Server communication error. Please try again.</td></tr>');
                }
            });
        });
    });
    </script>
@stop

@section('css')
    <style>
        .select2-container .select2-selection--single {
            height: 31px !important;
            border: 1px solid #ced4da !important;
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered,
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 29px !important;
            font-size: 0.875rem !important;
            padding-left: 8px !important;
        }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow,
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px !important;
        }
        .card-header::after {
            display: none !important;
        }
        @media print {
            .main-sidebar, .main-header, .card-header .card-tools, .card-body form, .card-footer, .breadcrumb, .btn {
                display: none !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
                background-color: white !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
        }
    </style>
@stop
