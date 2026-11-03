@extends('adminlte::page')

@section('title', 'Stock & Expiry Verification — Review Queue')

@section('content_header')
    <h1><i class="fas fa-clipboard-check text-primary mr-2"></i>Stock & Expiry Verification — Review Queue</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <form method="GET" class="form-inline">
                <label class="mr-2 font-weight-bold">Location:</label>
                <select name="branch_id" class="form-control form-control-sm mr-3">
                    <option value="">All Locations</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) request('branch_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <label class="mr-2 font-weight-bold">Type:</label>
                <select name="type" class="form-control form-control-sm mr-3">
                    <option value="">All</option>
                    <option value="stock_count" @selected(request('type') === 'stock_count')>Stock Count</option>
                    <option value="expiry_check" @selected(request('type') === 'expiry_check')>Expiry Check</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Filter</button>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Product</th>
                            <th>Batch</th>
                            <th>Physical Qty</th>
                            <th>System Qty</th>
                            <th>Variance</th>
                            <th>Submitted By</th>
                            <th>Submitted At</th>
                            <th class="text-right" style="width: 280px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($observations['data'] as $o)
                            <tr>
                                <td>{{ $o['id'] }}</td>
                                <td>
                                    @if ($o['type'] === 'stock_count')
                                        <span class="badge badge-secondary">Stock Count</span>
                                    @else
                                        <span class="badge badge-info">Expiry Check</span>
                                    @endif
                                </td>
                                <td>{{ $o['item_name'] }} <small class="text-muted">({{ $o['item_code'] }})</small></td>
                                <td>{{ $o['batch_no'] ?: '-' }}</td>
                                <td>{{ $o['physical_qty'] ?? '-' }}</td>
                                <td>{{ $o['system_qty_snapshot'] ?? '-' }}</td>
                                <td>
                                    @if ($o['variance'] === null)
                                        -
                                    @elseif ($o['variance'] > 0)
                                        <span class="text-success font-weight-bold">+{{ $o['variance'] }}</span>
                                    @elseif ($o['variance'] < 0)
                                        <span class="text-danger font-weight-bold">{{ $o['variance'] }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>{{ $o['submitted_by_name'] }}</td>
                                <td><small>{{ \Illuminate\Support\Carbon::parse($o['created_at'])->format('d-m-Y H:i') }}</small></td>
                                <td class="text-right">
                                    <a href="{{ route('inventory.stock-verification.show', $o['id']) }}" class="btn btn-xs btn-outline-info mr-1">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>

                                    @if ($o['type'] === 'stock_count')
                                        <button type="button" class="btn btn-xs btn-success" onclick="openApproveModal('{{ route('inventory.stock-verification.approve', $o['id']) }}', '{{ addslashes($o['item_name']) }}', '{{ $o['item_code'] }}', '{{ $o['physical_qty'] ?? 0 }}', '{{ $o['system_qty_snapshot'] ?? 0 }}', '{{ $o['variance'] ?? 0 }}')">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                    @else
                                        <form action="{{ route('inventory.stock-verification.approve', $o['id']) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this expiry check? This records the review only — it will NOT change stock quantity.')">
                                            @csrf
                                            <button class="btn btn-xs btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('inventory.stock-verification.request-recount', $o['id']) }}" method="POST" class="d-inline" onsubmit="return promptRemarks(this)">
                                        @csrf
                                        <input type="hidden" name="remarks" value="">
                                        <button type="button" class="btn btn-xs btn-warning" onclick="promptRemarks(this.closest('form'))"><i class="fas fa-redo mr-1"></i> Recount</button>
                                    </form>

                                    <form action="{{ route('inventory.stock-verification.reject', $o['id']) }}" method="POST" class="d-inline" onsubmit="return promptRemarks(this)">
                                        @csrf
                                        <input type="hidden" name="remarks" value="">
                                        <button type="button" class="btn btn-xs btn-danger" onclick="promptRemarks(this.closest('form'))"><i class="fas fa-times mr-1"></i> Reject</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No pending observations.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">
            <small class="text-muted">
                Page {{ $observations['current_page'] ?? 1 }} of {{ $observations['last_page'] ?? 1 }}
                ({{ $observations['total'] ?? count($observations['data']) }} total)
            </small>
            @if (($observations['current_page'] ?? 1) > 1)
                <a href="{{ request()->fullUrlWithQuery(['page' => $observations['current_page'] - 1]) }}" class="btn btn-sm btn-light">&laquo; Prev</a>
            @endif
            @if (($observations['current_page'] ?? 1) < ($observations['last_page'] ?? 1))
                <a href="{{ request()->fullUrlWithQuery(['page' => $observations['current_page'] + 1]) }}" class="btn btn-sm btn-light">Next &raquo;</a>
            @endif
        </div>
    </div>

    <!-- Modal for Approve Confirmation -->
    <div class="modal fade" id="approveStockModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="approveModalForm" method="POST" action="">
                    @csrf
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="approveModalLabel"><i class="fas fa-check-circle mr-1"></i> Confirm Stock Count Approval</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="text-muted mb-0">Product:</label>
                            <h5 id="modalItemName" class="font-weight-bold text-dark mb-0"></h5>
                            <small id="modalItemCode" class="text-muted"></small>
                        </div>
                        <div class="row text-center my-3 p-2 bg-light rounded">
                            <div class="col-4">
                                <small class="text-muted d-block">Physical Count</small>
                                <strong id="modalPhysicalQty" class="h5 font-weight-bold text-primary">0</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">System Qty</small>
                                <strong id="modalSystemQty" class="h5 font-weight-bold text-secondary">0</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">Variance</small>
                                <strong id="modalVariance" class="h5 font-weight-bold">0</strong>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modalApprovedQty" class="font-weight-bold">Approved Target Quantity <span class="text-danger">*</span></label>
                            <input type="number" step="any" class="form-control form-control-lg text-center font-weight-bold" id="modalApprovedQty" name="approved_qty" required>
                            <small class="form-text text-muted">This quantity will be applied to the system inventory ledger upon approval.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success font-weight-bold"><i class="fas fa-check mr-1"></i> Confirm & Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        function promptRemarks(form) {
            const remarks = prompt('Remarks (required):');
            if (!remarks) { return false; }
            form.querySelector('input[name="remarks"]').value = remarks;
            form.submit();
            return false;
        }

        function openApproveModal(actionUrl, itemName, itemCode, physicalQty, systemQty, variance) {
            const form = document.getElementById('approveModalForm');
            form.action = actionUrl;
            document.getElementById('modalItemName').innerText = itemName;
            document.getElementById('modalItemCode').innerText = '(' + itemCode + ')';
            document.getElementById('modalPhysicalQty').innerText = physicalQty;
            document.getElementById('modalSystemQty').innerText = systemQty;

            const varElem = document.getElementById('modalVariance');
            const numVar = parseFloat(variance) || 0;
            varElem.innerText = (numVar > 0 ? '+' : '') + numVar;
            varElem.className = 'h5 font-weight-bold ' + (numVar > 0 ? 'text-success' : (numVar < 0 ? 'text-danger' : 'text-muted'));

            // Default approved qty to physical qty (or system qty if 0)
            document.getElementById('modalApprovedQty').value = physicalQty;

            $('#approveStockModal').modal('show');
        }
    </script>
@stop
