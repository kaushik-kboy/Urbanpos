@extends('adminlte::page')

@section('title', 'Damage / Wastage Stock Report')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 font-weight-bold text-dark"><i class="fas fa-dumpster-fire text-danger mr-2"></i> Damage / Wastage Stock Report</h1>
        <div>
            <a href="{{ route('inventory.damage-stocks.create') }}" class="btn btn-danger btn-sm shadow-sm font-weight-bold mr-1">
                <i class="fas fa-plus mr-1"></i> New Damage Entry
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-th-large mr-1"></i> Reports Center
            </a>
        </div>
    </div>
@stop

@section('content')
    {{-- KPI Summary Cards --}}
    <div class="row mb-3">
        <div class="col-md-4 col-sm-6 mb-2">
            <div class="info-box shadow-sm mb-0">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-rupee-sign"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase font-weight-bold small">Total Loss Value</span>
                    <span class="info-box-number text-danger font-weight-bold" style="font-size: 1.3rem;">
                        ₹ {{ number_format($totalLostAmount, 2) }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-2">
            <div class="info-box shadow-sm mb-0">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase font-weight-bold small">Total Damaged / Written-Off Qty</span>
                    <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.3rem;">
                        {{ number_format($totalDamagedQty) }} Units
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12 mb-2">
            <div class="info-box shadow-sm mb-0">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-clipboard-list"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase font-weight-bold small">Incident Entries</span>
                    <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.3rem;">
                        {{ number_format($totalEntries) }} Entries
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card card-default mb-3 shadow-sm border-0">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.damage-stock-summary') }}" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Search Keywords</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Entry number, item, remarks...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm" {{ count($branches) <= 1 ? 'readonly style=pointer-events:none;background:#f4f6f9;' : '' }}>
                        @if(count($branches) > 1)
                            <option value="all" {{ (!request('branch_id') || request('branch_id') === 'all') ? 'selected' : '' }}>All Branches</option>
                        @endif
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Wastage Type</label>
                    <select name="wastage_type" class="form-control form-control-sm">
                        <option value="">All Wastage Types</option>
                        @foreach ($wastageTypes as $wt)
                            <option value="{{ $wt }}" @selected(request('wastage_type') === $wt)>{{ ucfirst($wt) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $sId => $sName)
                            <option value="{{ $sId }}" @selected((string)request('supplier_id') === (string)$sId)>{{ $sName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 d-flex justify-content-end mt-1">
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold shadow-sm mr-1"><i class="fas fa-filter mr-1"></i> Apply Filter</button>
                    <a href="{{ route('reports.damage-stock-summary') }}" class="btn btn-outline-secondary btn-sm shadow-sm mr-1"><i class="fas fa-undo mr-1"></i> Reset</a>
                    <button type="submit" name="export" value="excel" class="btn btn-success btn-sm font-weight-bold shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <h3 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-table mr-1 text-primary"></i> Damaged / Wastage Incident Records</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2 shadow-sm"><i class="fas fa-print mr-1"></i> Print</button>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="btn btn-sm btn-success mr-2 font-weight-bold shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                <button type="button" onclick="exportTableToCSV('damageStockTable', 'damage-stock-summary-report')" class="btn btn-sm btn-outline-success mr-2 shadow-sm"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.damage-stock-summary" table-id="damageStockTable" button-class="btn btn-sm btn-light border text-secondary shadow-sm" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" id="damageStockTable">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center" style="width: 45px;">#</th>
                        <th>Entry Number</th>
                        <th>Entry Date</th>
                        <th>Branch</th>
                        <th>Wastage Type</th>
                        <th>Created By</th>
                        <th>Approved By</th>
                        <th class="text-right">Total Damaged Qty</th>
                        <th class="text-right">Total Loss Cost</th>
                        <th>Remarks</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $pageQty = 0;
                        $pageCost = 0;
                    @endphp
                    @forelse ($damageStocks as $idx => $ds)
                        @php
                            $pageQty += (float) $ds->total_qty;
                            $pageCost += (float) $ds->total_cost;
                        @endphp
                        <tr>
                            <td class="text-center text-muted font-weight-bold">{{ $damageStocks->firstItem() + $idx }}</td>
                            <td>
                                <button type="button" class="btn btn-link btn-sm p-0 font-weight-bold text-primary btn-damage-detail" data-id="{{ $ds->id }}" data-num="{{ $ds->damage_number }}" title="Click to view line item details">
                                    <i class="fas fa-eye fa-xs mr-1"></i>{{ $ds->damage_number }}
                                </button>
                            </td>
                            <td>{{ $ds->entry_date ? $ds->entry_date->format('d-m-Y') : '-' }}</td>
                            <td>{{ $ds->branch?->name ?: '-' }}</td>
                            <td><span class="badge badge-light border">{{ ucfirst($ds->wastage_type ?: 'Damage') }}</span></td>
                            <td><small class="font-weight-bold text-dark"><i class="fas fa-user-edit text-muted mr-1"></i>{{ $ds->creator?->name ?? 'Admin' }}</small></td>
                            <td><small class="font-weight-bold text-dark"><i class="fas fa-user-check text-muted mr-1"></i>{{ $ds->approver?->name ?? 'Admin' }}</small></td>
                            <td class="text-right font-weight-bold text-danger">{{ number_format($ds->total_qty, 0) }}</td>
                            <td class="text-right font-weight-bold text-danger">₹ {{ number_format($ds->total_cost, 2) }}</td>
                            <td><small>{{ $ds->remarks ?: '-' }}</small></td>
                            <td class="text-center">
                                <span class="badge badge-{{ $ds->status === 'Posted' ? 'success' : 'secondary' }}">
                                    {{ $ds->status }}
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-info font-weight-bold btn-damage-detail" data-id="{{ $ds->id }}" data-num="{{ $ds->damage_number }}" title="View Item Details">
                                    <i class="fas fa-list mr-1"></i> Items
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-4"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i> No damage stock entries found in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($damageStocks->isNotEmpty())
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="7" class="text-right">Page Total:</td>
                            <td class="text-right text-danger">{{ number_format($pageQty, 0) }}</td>
                            <td class="text-right text-danger">₹ {{ number_format($pageCost, 2) }}</td>
                            <td colspan="3"></td>
                        </tr>
                        <tr class="bg-white border-top">
                            <td colspan="7" class="text-right text-dark">Filtered Period Grand Total:</td>
                            <td class="text-right text-danger" style="font-size: 1.05rem;">{{ number_format($totalDamagedQty, 0) }}</td>
                            <td class="text-right text-danger" style="font-size: 1.05rem;">₹ {{ number_format($totalLostAmount, 2) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center py-2 bg-light">
            <span class="text-muted small">Showing {{ $damageStocks->firstItem() ?? 0 }} to {{ $damageStocks->lastItem() ?? 0 }} of {{ $damageStocks->total() }} entries</span>
            {{ $damageStocks->links('pagination::bootstrap-4') }}
        </div>
    </div>

    {{-- Task 9: Damage Items Line Item Modal --}}
    <div class="modal fade" id="damageItemsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title font-weight-bold mb-0">
                        <i class="fas fa-dumpster-fire text-danger mr-2"></i> Damage Entry Items: <span id="dmgModalNumber" class="text-warning"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center">
                        <div class="small">
                            <strong>Date:</strong> <span id="dmgModalDate" class="badge badge-light border mr-2">-</span>
                            <strong>Branch:</strong> <span id="dmgModalBranch" class="mr-2">-</span>
                            <strong>Type:</strong> <span id="dmgModalType" class="badge badge-warning text-dark mr-2">-</span>
                            <strong>Remarks:</strong> <span id="dmgModalRemarks" class="text-muted">-</span>
                        </div>
                        <div>
                            <span class="font-weight-bold text-danger">Total Loss: <span id="dmgModalTotalCost" style="font-size: 1.15rem;">₹ 0.00</span></span>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="text-center" style="width: 45px;">#</th>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th class="text-center">Expiry Date</th>
                                    <th class="text-right">Damaged Qty</th>
                                    <th class="text-right">Unit Cost (₹)</th>
                                    <th class="text-right">Total Loss Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody id="dmgModalTbody">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-spinner fa-spin mr-1"></i> Loading line items...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                    <div>
                        <a href="#" id="dmgModalFullLink" class="btn btn-outline-primary btn-sm font-weight-bold" target="_blank">
                            <i class="fas fa-external-link-alt mr-1"></i> Open Full Document Page
                        </a>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
$(function() {
    $(document).on('click', '.btn-damage-detail', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var num = $(this).data('num');

        $('#dmgModalNumber').text(num);
        $('#dmgModalTbody').html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Loading line items...</td></tr>');
        $('#dmgModalFullLink').attr('href', '{{ url("inventory/damage-stocks") }}/' + id);
        $('#damageItemsModal').modal('show');

        $.ajax({
            url: '{{ url("inventory/damage-stocks") }}/' + id,
            method: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(resp) {
                $('#dmgModalDate').text(resp.entry_date || '-');
                $('#dmgModalBranch').text(resp.branch_name || '-');
                $('#dmgModalType').text(resp.wastage_type || 'Damage');
                $('#dmgModalRemarks').text(resp.remarks || '-');
                $('#dmgModalTotalCost').text('₹ ' + (resp.total_cost || '0.00'));

                var items = resp.items || [];
                if (items.length === 0) {
                    $('#dmgModalTbody').html('<tr><td colspan="7" class="text-center py-4 text-muted">No line items found.</td></tr>');
                    return;
                }

                var html = '';
                items.forEach(function(it, idx) {
                    html += '<tr>' +
                        '<td class="text-center text-muted font-weight-bold">' + (idx + 1) + '</td>' +
                        '<td><code>' + (it.code || '-') + '</code></td>' +
                        '<td><strong>' + (it.name || '-') + '</strong></td>' +
                        '<td class="text-center">' + (it.exp_date || '-') + '</td>' +
                        '<td class="text-right font-weight-bold text-danger">' + (it.qty || 0) + '</td>' +
                        '<td class="text-right">₹ ' + (it.cost_price || 0) + '</td>' +
                        '<td class="text-right font-weight-bold text-danger">₹ ' + (it.net_amount || 0) + '</td>' +
                        '</tr>';
                });
                $('#dmgModalTbody').html(html);
            },
            error: function(xhr) {
                $('#dmgModalTbody').html('<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load item details.</td></tr>');
            }
        });
    });
});
</script>
@stop
