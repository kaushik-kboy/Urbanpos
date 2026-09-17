@extends('adminlte::page')

@section('title', 'Loyalty Points Update')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold mb-0"><i class="fas fa-coins text-warning mr-2"></i>Loyalty Points Update</h1>
            <small class="text-muted">Adjust customer loyalty points manually with full ledger audit tracking</small>
        </div>
        <div>
            <a href="{{ route('master.loyalty-programs.index') }}" class="btn btn-outline-primary mr-2">
                <i class="fas fa-award mr-1"></i>Loyalty Programs
            </a>
            <a href="{{ route('finance.reports.customer-loyalty') }}" class="btn btn-outline-info">
                <i class="fas fa-chart-line mr-1"></i>Loyalty Report
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <x-error-summary />

    <div class="row">
        {{-- Left: Adjustment Form --}}
        <div class="col-lg-5">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header bg-light py-2">
                    <h5 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-edit text-primary mr-1"></i>Adjust Customer Points
                    </h5>
                </div>
                <form action="{{ route('master.loyalty-points.store') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="customer_id" class="font-weight-bold">Select Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-control select2" required style="width: 100%;">
                                <option value="">-- Choose Customer --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ $c->phone ?: 'No phone' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Live Customer Balance Box --}}
                        <div id="customer-info-box" class="alert alert-light border d-none py-2 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="info-cust-name" class="text-dark"></strong>
                                    <div class="small" id="info-loyalty-status"></div>
                                </div>
                                <div class="text-right">
                                    <div class="small text-muted font-weight-bold">Current Balance</div>
                                    <h4 class="mb-0 text-primary font-weight-bold"><span id="info-pts-balance">0.00</span> <small class="text-muted" style="font-size: 13px;">pts</small></h4>
                                    <div class="small text-success font-weight-bold">≈ ₹<span id="info-rupee-val">0.00</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold d-block">Action Type <span class="text-danger">*</span></label>
                            <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                <label class="btn btn-outline-success active" id="lbl-add">
                                    <input type="radio" name="direction" id="dir-add" value="Add" checked autocomplete="off">
                                    <i class="fas fa-plus-circle mr-1"></i>Add Points (+)
                                </label>
                                <label class="btn btn-outline-danger" id="lbl-deduct">
                                    <input type="radio" name="direction" id="dir-deduct" value="Deduct" autocomplete="off">
                                    <i class="fas fa-minus-circle mr-1"></i>Deduct Points (-)
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="points" class="font-weight-bold">Points <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.5" min="0.5" name="points" id="points" class="form-control text-right" placeholder="e.g. 50" value="{{ old('points') }}" required>
                                <div class="input-group-append"><span class="input-group-text font-weight-bold">pts</span></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="remarks" class="font-weight-bold">Reason / Remarks <span class="text-danger">*</span></label>
                            <textarea name="remarks" id="remarks" rows="2" class="form-control" placeholder="e.g. Promotional bonus, manual customer service compensation…" required>{{ old('remarks') }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer bg-light text-right">
                        <button type="submit" class="btn btn-primary font-weight-bold px-4">
                            <i class="fas fa-check-circle mr-1"></i>Post Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right: Recent Adjustments Log --}}
        <div class="col-lg-7">
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-history text-secondary mr-1"></i>Adjustment History
                    </h5>
                    <div class="d-flex align-items-center">
                        <form method="GET" action="{{ route('master.loyalty-points.index') }}" class="form-inline mb-0 mr-2">
                            <select name="customer_id" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                                <option value="">All Customers</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if(request('customer_id'))
                                <a href="{{ route('master.loyalty-points.index') }}" class="btn btn-xs btn-default"><i class="fas fa-times"></i></a>
                            @endif
                        </form>
                        <x-table-column-customizer table-key="master.loyalty-points" table-id="loyalty-points-table" button-class="btn btn-sm btn-light border text-secondary" />
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table id="loyalty-points-table" class="table table-hover table-sm table-striped mb-0">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th class="text-center">Type</th>
                                <th class="text-right">Points</th>
                                <th>Reason / Remarks</th>
                                <th>Adjusted By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($adjustments as $index => $row)
                                <tr>
                                    <td>{{ $adjustments->firstItem() + $index }}</td>
                                    <td>
                                        <small class="d-block font-weight-bold text-dark">{{ $row->created_at->format('d M Y') }}</small>
                                        <small class="text-muted">{{ $row->created_at->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        <strong class="text-primary">{{ $row->customer?->name }}</strong>
                                        <small class="d-block text-muted">{{ $row->customer?->phone }}</small>
                                    </td>
                                    <td class="text-center">
                                        @if($row->type === 'Adjustment_Add')
                                            <span class="badge badge-success"><i class="fas fa-plus mr-1"></i>Added</span>
                                        @else
                                            <span class="badge badge-danger"><i class="fas fa-minus mr-1"></i>Deducted</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-weight-bold {{ $row->type === 'Adjustment_Add' ? 'text-success' : 'text-danger' }}">
                                        {{ $row->type === 'Adjustment_Add' ? '+' : '-' }}{{ number_format($row->points, 2) }}
                                    </td>
                                    <td><small class="text-muted">{{ $row->remarks }}</small></td>
                                    <td><small class="badge badge-light border">{{ $row->creator?->name ?? 'System' }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No manual adjustments logged yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($adjustments->hasPages())
                    <div class="card-footer py-2">
                        {{ $adjustments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(function () {
    $('#customer_id').on('change', function () {
        var customerId = $(this).val();
        if (!customerId) {
            $('#customer-info-box').addClass('d-none');
            return;
        }

        $.ajax({
            url: "{{ url('master/loyalty-points/customer') }}/" + customerId,
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                $('#info-cust-name').text(data.customer_name);
                $('#info-pts-balance').text(Number(data.balance_points).toFixed(2));
                $('#info-rupee-val').text(Number(data.rupee_value).toFixed(2));

                if (data.enable_loyalty) {
                    $('#info-loyalty-status').html('<span class="badge badge-success"><i class="fas fa-check mr-1"></i>Loyalty Eligible</span>');
                } else {
                    $('#info-loyalty-status').html('<span class="badge badge-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Category Loyalty Inactive</span>');
                }

                $('#customer-info-box').removeClass('d-none');
            }
        });
    });

    if ($('#customer_id').val()) {
        $('#customer_id').trigger('change');
    }
});
</script>
@endpush
