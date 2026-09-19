@extends('adminlte::page')

@section('title', 'E-Way Bill Generation & Update')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-road text-primary mr-2"></i>E-Way Bill Management & Portal Export
            </h1>
            <small class="text-muted">GST E-Way Bill compliance (NIC Schema 1.0.0621 for ewaybillgst.gov.in)</small>
        </div>
        <div>
            <button type="button" class="btn btn-warning shadow-sm font-weight-bold" id="btnBulkExport" disabled>
                <i class="fas fa-file-export mr-1"></i> Bulk Download JSON (<span id="selectedCount">0</span>)
            </button>
            <a href="https://ewaybillgst.gov.in" target="_blank" class="btn btn-outline-info shadow-sm ml-2 font-weight-bold">
                <i class="fas fa-external-link-alt mr-1"></i> Open Gov. Portal
            </a>
        </div>
    </div>
@stop

@section('content')
    {{-- KPI Metric Widgets --}}
    <div class="row mb-3">
        <div class="col-xl-3 col-sm-6 mb-2">
            <div class="info-box bg-white shadow-sm border-left border-primary" style="border-left-width: 4px !important;">
                <span class="info-box-icon text-primary"><i class="fas fa-file-invoice"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Eligible Consignments</span>
                    <span class="info-box-number font-weight-bold h4 mb-0">{{ number_format($totalEligibleCount) }}</span>
                    <small class="text-muted">> ₹50,000 / Goods with Transport</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-2">
            <div class="info-box bg-white shadow-sm border-left border-success" style="border-left-width: 4px !important;">
                <span class="info-box-icon text-success"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">E-Way Bills Active</span>
                    <span class="info-box-number font-weight-bold h4 mb-0 text-success">{{ number_format($generatedCount) }}</span>
                    <small class="text-success font-weight-bold">EWB Number Recorded</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-2">
            <div class="info-box bg-white shadow-sm border-left border-warning" style="border-left-width: 4px !important;">
                <span class="info-box-icon text-warning"><i class="fas fa-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Pending Generation</span>
                    <span class="info-box-number font-weight-bold h4 mb-0 text-warning">{{ number_format($pendingCount) }}</span>
                    <small class="text-muted">Awaiting Portal Upload</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-2">
            <div class="info-box bg-white shadow-sm border-left border-info" style="border-left-width: 4px !important;">
                <span class="info-box-icon text-info"><i class="fas fa-rupee-sign"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Movement Value</span>
                    <span class="info-box-number font-weight-bold h4 mb-0 text-info">₹{{ number_format($totalConsignValue, 2) }}</span>
                    <small class="text-muted">Outward Logistics Value</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h6 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-filter mr-1 text-secondary"></i> Filter Consignments</h6>
        </div>
        <div class="card-body py-2">
            <form method="GET" action="{{ route('tools.eway-update') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small text-muted mb-1 font-weight-bold">E-Way Bill Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Consignments</option>
                        <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending E-Way Bill</option>
                        <option value="Generated" {{ request('status') == 'Generated' ? 'selected' : '' }}>E-Way Bill Generated</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small text-muted mb-1 font-weight-bold">From Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="from_date" class="form-control form-control-sm datepicker" value="{{ request('from_date') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small text-muted mb-1 font-weight-bold">To Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="to_date" class="form-control form-control-sm datepicker" value="{{ request('to_date') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small text-muted mb-1 font-weight-bold">Search (Doc / Customer / Vehicle)</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Bill No, Customer, Vehicle, EWB..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-12 mb-2 d-flex">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill mr-1 shadow-sm font-weight-bold">
                        <i class="fas fa-search mr-1"></i> Filter
                    </button>
                    <a href="{{ route('tools.eway-update') }}" class="btn btn-outline-secondary btn-sm shadow-sm" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Consignments Table --}}
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="selectAllCheckbox">
                <label class="custom-control-label font-weight-bold text-dark" for="selectAllCheckbox">
                    Select All Visible Bills (<span id="totalVisibleCount">{{ $bills->count() }}</span>)
                </label>
            </div>
            <div class="small text-muted">
                Showing {{ $bills->firstItem() ?? 0 }} to {{ $bills->lastItem() ?? 0 }} of {{ $bills->total() }} entries
            </div>
        </div>

        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="ewayTable">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Doc No & Date</th>
                        <th>Customer / Destination</th>
                        <th>Invoice Value</th>
                        <th>Vehicle & Transporter</th>
                        <th>E-Way Bill Number</th>
                        <th>Validity Till</th>
                        <th>Status</th>
                        <th class="text-right" style="min-width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="bill-checkbox" value="{{ $bill->id }}" onchange="updateSelectedCount()">
                            </td>
                            <td>
                                <a href="{{ route('sales.sales-bills.show', $bill) }}" class="font-weight-bold text-primary">
                                    {{ $bill->bill_number }}
                                </a>
                                <small class="text-muted d-block">{{ $bill->bill_date ? $bill->bill_date->format('d-m-Y') : '—' }}</small>
                            </td>
                            <td>
                                <strong>{{ $bill->customer?->name ?? 'Walk-in Customer' }}</strong>
                                @if ($bill->customer?->gst_no)
                                    <small class="badge badge-info d-block mt-1 font-weight-bold" style="width: fit-content;">GST: {{ $bill->customer->gst_no }}</small>
                                @else
                                    <small class="text-muted d-block">Unregistered (URP)</small>
                                @endif
                                @if ($bill->customer?->city)
                                    <small class="text-muted"><i class="fas fa-map-marker-alt text-danger mr-1"></i>{{ $bill->customer->city }}</small>
                                @endif
                            </td>
                            <td>
                                <strong class="text-dark font-weight-bold h6 mb-0">₹{{ number_format($bill->total, 2) }}</strong>
                                <small class="text-muted d-block">GST: ₹{{ number_format($bill->total_gst, 2) }}</small>
                            </td>
                            <td>
                                @if ($bill->vehicle_no)
                                    <span class="badge badge-secondary px-2 py-1 font-weight-bold text-uppercase">
                                        <i class="fas fa-truck mr-1"></i> {{ $bill->vehicle_no }}
                                    </span>
                                @else
                                    <span class="text-muted small">No Vehicle</span>
                                @endif
                                @if ($bill->transporter_name)
                                    <small class="text-muted d-block">{{ $bill->transporter_name }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($bill->hasEwayBill())
                                    <strong class="text-success font-weight-bold font-monospace" style="letter-spacing: 0.5px;">
                                        <i class="fas fa-check-circle text-success mr-1"></i>{{ $bill->eway_bill_no }}
                                    </strong>
                                @else
                                    <span class="badge badge-warning text-dark font-weight-bold">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Not Generated
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($bill->eway_valid_until)
                                    <small class="font-weight-bold text-info">
                                        {{ $bill->eway_valid_until->format('d-m-Y h:i A') }}
                                    </small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($bill->hasEwayBill())
                                    <span class="badge badge-success px-2 py-1 font-weight-bold">Active</span>
                                @elseif ($bill->requiresEwayBill())
                                    <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold">Pending</span>
                                @else
                                    <span class="badge badge-light border text-muted px-2 py-1">Optional</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('sales.sales-bills.eway-json', $bill) }}" class="btn btn-outline-warning" title="Download NIC JSON for Portal">
                                        <i class="fas fa-file-download mr-1"></i> JSON
                                    </a>
                                    <button type="button" class="btn btn-outline-primary btn-open-update" 
                                        data-id="{{ $bill->id }}"
                                        data-billno="{{ $bill->bill_number }}"
                                        data-ewayno="{{ $bill->eway_bill_no }}"
                                        data-validuntil="{{ $bill->eway_valid_until ? $bill->eway_valid_until->format('Y-m-d\TH:i') : '' }}"
                                        data-vehicleno="{{ $bill->vehicle_no }}"
                                        data-transporter="{{ $bill->transporter_name }}"
                                        data-updateurl="{{ route('sales.sales-bills.eway-update', $bill) }}"
                                        title="Update EWB No. & Transport">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <a href="{{ route('sales.sales-bills.show', $bill) }}" class="btn btn-outline-secondary" title="View Bill">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-truck-moving fa-3x mb-3 text-secondary"></i>
                                <h5>No Eligible Consignments Found</h5>
                                <p class="small mb-0">Sales bills with value > ₹50,000 or with transport details will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bills->hasPages())
            <div class="card-footer bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Showing {{ $bills->firstItem() }} to {{ $bills->lastItem() }} of {{ $bills->total() }} entries</span>
                    {{ $bills->links('pagination::bootstrap-4') }}
                </div>
            </div>
        @endif
    </div>

    {{-- Hidden Bulk Export Form --}}
    <form id="bulkExportForm" action="{{ route('tools.eway-bulk-json') }}" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="selected_bills" id="selectedBillsInput">
    </form>

    {{-- Quick Update Modal --}}
    <div class="modal fade" id="quickEwayModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="quickUpdateForm" method="POST" action="">
                    @csrf
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-edit mr-2 text-warning"></i> Update E-Way Bill: <span id="modalDocNo"></span>
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-dark">12-Digit E-Way Bill Number</label>
                            <input type="text" name="eway_bill_no" id="modalEwayNo" class="form-control" placeholder="e.g. 101234567890" maxlength="25">
                            <small class="text-muted">Generated from government portal</small>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-dark">Valid Until (Expiry Date)</label>
                            <input type="datetime-local" name="eway_valid_until" id="modalValidUntil" class="form-control">
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-dark">Vehicle Number</label>
                            <input type="text" name="vehicle_no" id="modalVehicleNo" class="form-control text-uppercase" placeholder="e.g. MH 12 AB 1234">
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-weight-bold small text-dark">Transporter Name</label>
                            <input type="text" name="transporter_name" id="modalTransporter" class="form-control" placeholder="e.g. SafeXpress Logistics">
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm font-weight-bold px-3">
                            <i class="fas fa-save mr-1"></i> Save Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.bill-checkbox:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').innerText = count;
        
        const btnBulk = document.getElementById('btnBulkExport');
        btnBulk.disabled = (count === 0);
    }

    document.getElementById('selectAllCheckbox')?.addEventListener('change', function () {
        const checked = this.checked;
        document.querySelectorAll('.bill-checkbox').forEach(cb => {
            cb.checked = checked;
        });
        updateSelectedCount();
    });

    document.getElementById('btnBulkExport')?.addEventListener('click', function () {
        const selectedIds = Array.from(document.querySelectorAll('.bill-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            alert('Please select at least one bill to download bulk JSON.');
            return;
        }

        document.getElementById('selectedBillsInput').value = selectedIds.join(',');
        document.getElementById('bulkExportForm').submit();
    });

    // Quick Update Modal handler
    document.querySelectorAll('.btn-open-update').forEach(btn => {
        btn.addEventListener('click', function () {
            const form = document.getElementById('quickUpdateForm');
            form.action = this.dataset.updateurl;
            document.getElementById('modalDocNo').innerText = this.dataset.billno;
            document.getElementById('modalEwayNo').value = this.dataset.ewayno || '';
            document.getElementById('modalValidUntil').value = this.dataset.validuntil || '';
            document.getElementById('modalVehicleNo').value = this.dataset.vehicleno || '';
            document.getElementById('modalTransporter').value = this.dataset.transporter || '';
            $('#quickEwayModal').modal('show');
        });
    });
</script>
@stop
