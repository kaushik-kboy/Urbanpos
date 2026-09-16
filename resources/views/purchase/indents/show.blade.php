@extends('adminlte::page')

@section('title', 'Purchase Indent ' . $purchaseIndent->indent_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark">
                <i class="fas fa-clipboard-list mr-2 text-primary"></i> Indent #{{ $purchaseIndent->indent_number }}
            </h1>
            <small class="text-muted">Raised on {{ $purchaseIndent->indent_date->format('d-m-Y') }} by {{ $purchaseIndent->requestedBy->name ?? 'Staff' }}</small>
        </div>
        <div>
            <a href="{{ route('purchase.purchase-indents.print', $purchaseIndent) }}" target="_blank" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print Slip
            </a>
            <a href="{{ route('purchase.purchase-indents.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Indents
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    @if ($purchaseIndent->status === 'Converted' && $purchaseIndent->purchaseOrder)
        <div class="alert alert-primary d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="fas fa-check-circle fa-lg mr-2"></i>
                This requisition has been converted into Purchase Order <strong>#{{ $purchaseIndent->purchaseOrder->po_number }}</strong>.
            </div>
            <a href="{{ route('purchase.purchase-orders.edit', $purchaseIndent->purchaseOrder) }}" class="btn btn-light btn-sm font-weight-bold text-primary">
                View Purchase Order <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    @elseif ($purchaseIndent->status === 'Approved')
        <div class="alert alert-success d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="fas fa-thumbs-up fa-lg mr-2"></i>
                This requisition was approved on <strong>{{ $purchaseIndent->reviewed_at?->format('d-m-Y H:i') }}</strong> by <strong>{{ $purchaseIndent->reviewedBy->name ?? 'Reviewer' }}</strong>. Ready to create Purchase Order.
            </div>
            <a href="{{ route('purchase.purchase-orders.create', ['from_indent' => $purchaseIndent->id]) }}" class="btn btn-success btn-sm font-weight-bold shadow-sm px-3">
                <i class="fas fa-cart-plus mr-1"></i> 1-Click Convert to Purchase Order
            </a>
        </div>
    @elseif ($purchaseIndent->status === 'Rejected')
        <div class="alert alert-danger mb-3">
            <i class="fas fa-times-circle fa-lg mr-2"></i>
            This requisition was rejected by <strong>{{ $purchaseIndent->reviewedBy->name ?? 'Manager' }}</strong> on {{ $purchaseIndent->reviewed_at?->format('d-m-Y H:i') }}.
            @if ($purchaseIndent->rejection_reason)
                <div class="mt-1 ml-4 font-italic">Reason: "{{ $purchaseIndent->rejection_reason }}"</div>
            @endif
        </div>
    @elseif ($purchaseIndent->status === 'Cancelled')
        <div class="alert alert-secondary mb-3">
            <i class="fas fa-ban fa-lg mr-2"></i>
            This requisition was cancelled by <strong>{{ $purchaseIndent->cancelledBy->name ?? 'Staff' }}</strong> on {{ $purchaseIndent->cancelled_at?->format('d-m-Y H:i') }}.
            @if ($purchaseIndent->cancellation_reason)
                <div class="mt-1 ml-4 font-italic">Reason: "{{ $purchaseIndent->cancellation_reason }}"</div>
            @endif
        </div>
    @endif

    <div class="row">
        {{-- Metadata card --}}
        <div class="col-md-4">
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold">Requisition Info</h6>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 40%;">Status:</td>
                            <td>
                                @php
                                    $sClass = match($purchaseIndent->status) {
                                        'Pending' => 'warning',
                                        'Approved' => 'success',
                                        'Rejected' => 'danger',
                                        'Converted' => 'primary',
                                        'Cancelled' => 'secondary',
                                        default => 'light'
                                    };
                                @endphp
                                <span class="badge badge-{{ $sClass }} px-2 py-1 font-weight-bold">{{ $purchaseIndent->status }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Priority:</td>
                            <td>
                                @php
                                    $pClass = match($purchaseIndent->priority) {
                                        'Urgent' => 'danger',
                                        'High' => 'warning',
                                        'Medium' => 'info',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge badge-{{ $pClass }} px-2 py-1">{{ $purchaseIndent->priority }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Target Branch:</td>
                            <td><strong>{{ $purchaseIndent->branch->name ?? 'N/A' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Department:</td>
                            <td>{{ $purchaseIndent->department }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Indent Date:</td>
                            <td>{{ $purchaseIndent->indent_date->format('d-m-Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Required By:</td>
                            <td>{{ $purchaseIndent->required_by_date ? $purchaseIndent->required_by_date->format('d-m-Y') : 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Requested By:</td>
                            <td>{{ $purchaseIndent->requestedBy->name ?? 'Staff' }}</td>
                        </tr>
                        @if ($purchaseIndent->reviewed_by_id)
                            <tr>
                                <td class="text-muted">Reviewed By:</td>
                                <td>{{ $purchaseIndent->reviewedBy->name ?? 'N/A' }} ({{ $purchaseIndent->reviewed_at?->format('d-m-Y') }})</td>
                            </tr>
                        @endif
                        @if ($purchaseIndent->remarks)
                            <tr>
                                <td class="text-muted align-top">Remarks:</td>
                                <td><small>{{ $purchaseIndent->remarks }}</small></td>
                            </tr>
                        @endif
                    </table>
                </div>

                {{-- Action buttons --}}
                @if ($purchaseIndent->status === 'Pending')
                    <div class="card-footer bg-light p-2 text-center">
                        <button type="button" class="btn btn-success btn-sm font-weight-bold mr-1" data-toggle="modal" data-target="#approveModal">
                            <i class="fas fa-check mr-1"></i> Review & Approve
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold mr-1" data-toggle="modal" data-target="#rejectModal">
                            <i class="fas fa-times mr-1"></i> Reject
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm cancel-indent-btn">
                            <i class="fas fa-ban mr-1"></i> Cancel
                        </button>
                    </div>
                @elseif ($purchaseIndent->status === 'Approved')
                    <div class="card-footer bg-light p-2 text-center">
                        <a href="{{ route('purchase.purchase-orders.create', ['from_indent' => $purchaseIndent->id]) }}" class="btn btn-success btn-sm btn-block font-weight-bold mb-2">
                            <i class="fas fa-cart-plus mr-1"></i> Convert to Purchase Order
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-xs cancel-indent-btn">
                            <i class="fas fa-ban mr-1"></i> Cancel Indent
                        </button>
                    </div>
                @endif
            </div>

            {{-- Non-financial guard notice --}}
            <div class="card card-outline card-secondary shadow-none border bg-light">
                <div class="card-body p-3 small text-muted">
                    <i class="fas fa-shield-alt text-info mr-1"></i> <strong>Audit Note:</strong>
                    Purchase Indents are internal requisitions. No stock movements or financial journal entries are generated.
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <div class="col-md-8">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold">Requisition Item Lines ({{ $purchaseIndent->items->count() }})</h6>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Item Code & Description</th>
                                <th class="text-center" style="width: 100px;">Branch Stock</th>
                                <th class="text-right" style="width: 110px;">Req. Qty</th>
                                <th class="text-right" style="width: 110px;">Approved</th>
                                <th class="text-right" style="width: 110px;">Est. Cost</th>
                                <th class="text-right" style="width: 120px;">Est. Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseIndent->items as $idx => $line)
                                <tr>
                                    <td class="text-center">{{ $idx + 1 }}</td>
                                    <td>
                                        <strong>{{ $line->item?->item_code ?? 'ITEM' }}</strong> - {{ $line->item?->name }}
                                        @if ($line->remarks)
                                            <div class="small text-muted font-italic">{{ $line->remarks }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light border">{{ number_format($line->current_stock, 2) }}</span>
                                    </td>
                                    <td class="text-right font-weight-bold">{{ number_format($line->requested_qty, 2) }}</td>
                                    <td class="text-right">
                                        @if ($line->approved_qty !== null)
                                            <span class="text-success font-weight-bold">{{ number_format($line->approved_qty, 2) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right">₹{{ number_format($line->estimated_cost, 2) }}</td>
                                    <td class="text-right font-weight-bold">₹{{ number_format($line->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="thead-light font-weight-bold">
                            <tr>
                                <td colspan="3" class="text-right">Totals:</td>
                                <td class="text-right">{{ number_format($purchaseIndent->total_requested_qty, 2) }}</td>
                                <td class="text-right text-success">
                                    {{ $purchaseIndent->total_approved_qty > 0 ? number_format($purchaseIndent->total_approved_qty, 2) : '—' }}
                                </td>
                                <td></td>
                                <td class="text-right text-primary">₹{{ number_format($purchaseIndent->total_estimated_amount, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Cancellation Form (Hidden) --}}
    <form action="{{ route('purchase.purchase-indents.destroy', $purchaseIndent) }}" method="POST" id="cancel-indent-form" style="display: none;">
        @csrf
        @method('DELETE')
        <input type="hidden" name="cancellation_reason" id="cancellation_reason_input">
    </form>

    {{-- Approval Modal --}}
    @if ($purchaseIndent->status === 'Pending')
        <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <form action="{{ route('purchase.purchase-indents.approve', $purchaseIndent) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-success text-white py-2">
                            <h5 class="modal-title" id="approveModalLabel"><i class="fas fa-check mr-1"></i> Approve Purchase Indent #{{ $purchaseIndent->indent_number }}</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body p-3">
                            <p class="small text-muted mb-2">Review and specify the approved quantity for each requested item line:</p>
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Item</th>
                                        <th style="width: 110px;" class="text-center">Live Stock</th>
                                        <th style="width: 110px;" class="text-right">Requested</th>
                                        <th style="width: 140px;" class="text-right">Approved Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseIndent->items as $idx => $line)
                                        <tr>
                                            <td>
                                                <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $line->id }}">
                                                <strong>{{ $line->item?->item_code ?? 'ITEM' }}</strong> - {{ $line->item?->name }}
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge badge-light border">{{ number_format($line->current_stock, 2) }}</span>
                                            </td>
                                            <td class="text-right align-middle">{{ number_format($line->requested_qty, 2) }}</td>
                                            <td>
                                                <input type="number" step="0.001" min="0" name="items[{{ $idx }}][approved_qty]"
                                                       value="{{ old("items.{$idx}.approved_qty", $line->requested_qty) }}"
                                                       class="form-control form-control-sm text-right font-weight-bold" required>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="form-group mt-3">
                                <label class="font-weight-bold small">Approval Remarks (Optional)</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="e.g. Approved for immediate vendor order."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success btn-sm font-weight-bold px-3">
                                <i class="fas fa-check mr-1"></i> Confirm & Approve Indent
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Reject Modal --}}
        <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form action="{{ route('purchase.purchase-indents.reject', $purchaseIndent) }}" method="POST">
                        @csrf
                        <div class="modal-header bg-danger text-white py-2">
                            <h5 class="modal-title" id="rejectModalLabel"><i class="fas fa-times mr-1"></i> Reject Purchase Indent #{{ $purchaseIndent->indent_number }}</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body p-3">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                                <textarea name="rejection_reason" class="form-control form-control-sm" rows="3" placeholder="Specify why this requisition is being rejected..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger btn-sm font-weight-bold px-3">
                                <i class="fas fa-times mr-1"></i> Reject Indent
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @push('js')
    <script>
        document.querySelectorAll('.cancel-indent-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const reason = prompt('Please enter the reason for cancelling this Purchase Indent:');
                if (reason === null || reason.trim() === '') return;
                document.getElementById('cancellation_reason_input').value = reason.trim();
                document.getElementById('cancel-indent-form').submit();
            });
        });
    </script>
    @endpush
@stop
