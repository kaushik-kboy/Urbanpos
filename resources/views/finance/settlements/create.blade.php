@extends('adminlte::page')

@section('title', 'New Credit Settlement')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0 text-dark">
            <i class="fas fa-hand-holding-usd mr-2 text-success"></i>New Billwise Credit Settlement
        </h1>
        <a href="{{ route('finance.settlements.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Settlements
        </a>
    </div>
@stop

@section('content')
    <div class="card card-outline {{ $settlementType === 'Customer' ? 'card-success' : 'card-primary' }} mb-3">
        <div class="card-header p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link {{ $settlementType === 'Customer' ? 'active bg-success font-weight-bold' : '' }}" href="{{ route('finance.settlements.create', ['type' => 'Customer']) }}">
                        <i class="fas fa-arrow-down mr-1"></i> Customer Receipt (Settle Debtors)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $settlementType === 'Supplier' ? 'active bg-primary font-weight-bold' : '' }}" href="{{ route('finance.settlements.create', ['type' => 'Supplier']) }}">
                        <i class="fas fa-arrow-up mr-1"></i> Supplier Payment (Settle Creditors)
                    </a>
                </li>
            </ul>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle mr-1"></i> Validation Errors</h5>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <form action="{{ route('finance.settlements.store') }}" method="POST" id="settlement-form">
        @csrf
        <input type="hidden" name="settlement_type" id="settlement-type" value="{{ $settlementType }}">

        <div class="card card-primary card-outline shadow-sm mb-3">
            <div class="card-header py-2">
                <h5 class="card-title font-weight-bold mb-0 text-primary">
                    <i class="fas fa-file-invoice-dollar mr-1"></i> Settlement Header & Payment Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>
                            {{ $settlementType === 'Customer' ? 'Customer (Sundry Debtor)' : 'Supplier (Sundry Creditor)' }}
                            <span class="text-danger">*</span>
                        </label>
                        @if($settlementType === 'Customer')
                            <select name="customer_id" id="party-select" class="form-control form-control-sm select2" required>
                                <option value="">Select a Customer</option>
                                @foreach($customers as $id => $name)
                                    <option value="{{ $id }}" @selected(old('customer_id') == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        @else
                            <select name="supplier_id" id="party-select" class="form-control form-control-sm select2" required>
                                <option value="">Select a Supplier</option>
                                @foreach($suppliers as $id => $name)
                                    <option value="{{ $id }}" @selected(old('supplier_id') == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" id="branch-select" class="form-control form-control-sm select2" required>
                            @foreach($branches as $id => $name)
                                <option value="{{ $id }}" @selected(old('branch_id', session('active_branch_id', 3)) == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 form-group">
                        <label>Settlement Date <span class="text-danger">*</span></label>
                        <input type="date" name="settlement_date" class="form-control form-control-sm" value="{{ old('settlement_date', now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Total Payment Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold">₹</span>
                            </div>
                            <input type="number" step="0.01" min="0.01" name="total_amount" id="total-payment-amount" class="form-control form-control-sm text-right font-weight-bold text-success text-lg" placeholder="0.00" value="{{ old('total_amount') }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Payment Mode <span class="text-danger">*</span></label>
                        <select name="payment_mode" class="form-control form-control-sm" required>
                            @foreach(['Cash', 'Bank Transfer', 'Cheque', 'UPI', 'Card'] as $mode)
                                <option value="{{ $mode }}" @selected(old('payment_mode', 'Cash') === $mode)>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label>Deposit To / Pay From Ledger <span class="text-danger">*</span></label>
                        <select name="bank_ledger_id" class="form-control form-control-sm select2" required>
                            <option value="">Select Cash or Bank Account</option>
                            @foreach($bankLedgers as $id => $name)
                                <option value="{{ $id }}" @selected(old('bank_ledger_id') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 form-group">
                        <label>Ref / Cheque / UTR No</label>
                        <input type="text" name="reference_no" class="form-control form-control-sm" placeholder="Optional reference" value="{{ old('reference_no') }}">
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Remarks</label>
                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Optional notes" value="{{ old('remarks') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary shadow-sm mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h5 class="card-title font-weight-bold mb-0 text-dark">
                    <i class="fas fa-list-ol mr-1 text-primary"></i> Outstanding Invoices & Allocation
                </h5>
                <div>
                    <button type="button" class="btn btn-xs btn-primary font-weight-bold mr-1" id="btn-auto-allocate">
                        <i class="fas fa-bolt mr-1"></i> Auto-Allocate (FIFO)
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-clear-allocate">
                        <i class="fas fa-eraser mr-1"></i> Clear
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="bills-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 mb-0">Loading pending bills...</p>
                </div>
                <div id="bills-placeholder" class="text-center py-4 text-muted">
                    <i class="fas fa-arrow-up mr-1 text-info"></i> Please select a {{ $settlementType }} above to discover outstanding bills.
                </div>
                <div class="table-responsive d-none" id="bills-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="bills-table">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Bill / Invoice No</th>
                                <th>Date</th>
                                <th class="text-center">Age (Days)</th>
                                <th class="text-right">Bill Total</th>
                                <th class="text-right">Already Paid</th>
                                <th class="text-right text-primary">Balance Due</th>
                                <th style="width: 150px;" class="text-right">Settling Amount (₹)</th>
                                <th style="width: 130px;" class="text-right">Cash Disc (₹)</th>
                                <th style="width: 70px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="bills-body">
                            {{-- Injected via JS --}}
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td colspan="6" class="text-right align-middle">Totals:</td>
                                <td class="text-right align-middle text-primary" id="footer-total-balance">₹0.00</td>
                                <td class="text-right align-middle text-success font-weight-bold" id="footer-total-allocated">₹0.00</td>
                                <td class="text-right align-middle text-danger font-weight-bold" id="footer-total-discount">₹0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer py-2 bg-light d-none" id="allocation-summary-bar">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted mr-2">Payment Entered:</span>
                        <strong class="text-dark" id="bar-payment">₹0.00</strong>
                        <span class="mx-2 text-muted">|</span>
                        <span class="text-muted mr-2">Total Allocated:</span>
                        <strong class="text-success" id="bar-allocated">₹0.00</strong>
                    </div>
                    <div>
                        <span class="text-muted mr-2">Unallocated Balance:</span>
                        <strong class="text-danger" id="bar-unallocated">₹0.00</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-right mb-4">
            <a href="{{ route('finance.settlements.index') }}" class="btn btn-default mr-2">Cancel</a>
            <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm" id="btn-save-settlement">
                <i class="fas fa-check-circle mr-1"></i> Post & Settle Bills
            </button>
        </div>
    </form>
@stop

@push('js')
<script>
$(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    let pendingBills = [];

    $('#party-select, #branch-select').on('change', function() {
        loadUnpaidBills();
    });

    function loadUnpaidBills() {
        let partyId = $('#party-select').val();
        let branchId = $('#branch-select').val();
        let type = $('#settlement-type').val();

        if (!partyId) {
            $('#bills-table-wrap').addClass('d-none');
            $('#allocation-summary-bar').addClass('d-none');
            $('#bills-placeholder').removeClass('d-none');
            return;
        }

        $('#bills-placeholder').addClass('d-none');
        $('#bills-loading').removeClass('d-none');
        $('#bills-table-wrap').addClass('d-none');
        $('#allocation-summary-bar').addClass('d-none');

        $.getJSON('{{ route("finance.settlements.unpaid-bills") }}', {
            type: type,
            party_id: partyId,
            branch_id: branchId
        }, function(res) {
            $('#bills-loading').addClass('d-none');
            pendingBills = res.bills || [];

            if (pendingBills.length === 0) {
                $('#bills-placeholder').html('<i class="fas fa-check-circle text-success mr-1"></i> No outstanding / unpaid bills found for this party.').removeClass('d-none');
                return;
            }

            renderBillsTable(pendingBills);
            $('#bills-table-wrap').removeClass('d-none');
            $('#allocation-summary-bar').removeClass('d-none');
        });
    }

    function renderBillsTable(bills) {
        let $tbody = $('#bills-body');
        $tbody.empty();

        bills.forEach(function(b, idx) {
            let tr = `
                <tr data-index="${idx}">
                    <td class="text-center font-weight-bold align-middle">${idx + 1}</td>
                    <td class="font-weight-bold align-middle">
                        ${b.bill_number}
                        ${b.supplier_inv_no ? '<br><small class="text-muted">Sup Inv: ' + b.supplier_inv_no + '</small>' : ''}
                        <input type="hidden" name="allocations[${idx}][bill_id]" value="${b.id}">
                        <input type="hidden" name="allocations[${idx}][bill_amount]" value="${b.total_amount}">
                    </td>
                    <td class="align-middle">${b.bill_date_formatted}</td>
                    <td class="text-center align-middle">
                        <span class="badge ${b.days_overdue > 60 ? 'badge-danger' : (b.days_overdue > 30 ? 'badge-warning' : 'badge-light border')}">
                            ${b.days_overdue} days
                        </span>
                    </td>
                    <td class="text-right align-middle">₹${parseFloat(b.total_amount).toFixed(2)}</td>
                    <td class="text-right align-middle text-muted">₹${parseFloat(b.paid_amount).toFixed(2)}</td>
                    <td class="text-right align-middle font-weight-bold text-primary bill-due" data-due="${b.balance_due}">
                        ₹${parseFloat(b.balance_due).toFixed(2)}
                    </td>
                    <td class="text-right">
                        <input type="number" step="0.01" min="0" max="${b.balance_due}"
                               name="allocations[${idx}][settled_amount]"
                               class="form-control form-control-sm text-right font-weight-bold text-success input-settled"
                               placeholder="0.00" value="">
                    </td>
                    <td class="text-right">
                        <input type="number" step="0.01" min="0" max="${b.balance_due}"
                               name="allocations[${idx}][discount_amount]"
                               class="form-control form-control-sm text-right text-danger input-discount"
                               placeholder="0.00" value="">
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-xs btn-outline-primary btn-full-settle" title="Settle Full Balance">
                            Full
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(tr);
        });

        recalcAllocations();
    }

    $(document).on('input', '.input-settled, .input-discount, #total-payment-amount', function() {
        recalcAllocations();
    });

    $(document).on('click', '.btn-full-settle', function() {
        let $row = $(this).closest('tr');
        let due = parseFloat($row.find('.bill-due').data('due')) || 0;
        let disc = parseFloat($row.find('.input-discount').val()) || 0;
        let netSettle = Math.max(0, due - disc);
        $row.find('.input-settled').val(netSettle.toFixed(2));
        recalcAllocations();
    });

    $('#btn-clear-allocate').on('click', function() {
        $('.input-settled').val('');
        $('.input-discount').val('');
        recalcAllocations();
    });

    $('#btn-auto-allocate').on('click', function() {
        let totalPayment = parseFloat($('#total-payment-amount').val()) || 0;
        if (totalPayment <= 0) {
            alert('Please enter Total Payment Amount first.');
            $('#total-payment-amount').focus();
            return;
        }

        let remaining = totalPayment;

        $('#bills-body tr').each(function() {
            let $row = $(this);
            let due = parseFloat($row.find('.bill-due').data('due')) || 0;
            let disc = parseFloat($row.find('.input-discount').val()) || 0;
            let effectiveDue = Math.max(0, due - disc);

            if (remaining <= 0) {
                $row.find('.input-settled').val('');
            } else if (remaining >= effectiveDue) {
                $row.find('.input-settled').val(effectiveDue.toFixed(2));
                remaining -= effectiveDue;
            } else {
                $row.find('.input-settled').val(remaining.toFixed(2));
                remaining = 0;
            }
        });

        recalcAllocations();
    });

    function recalcAllocations() {
        let totDue = 0;
        let totAllocated = 0;
        let totDisc = 0;

        $('#bills-body tr').each(function() {
            let due = parseFloat($(this).find('.bill-due').data('due')) || 0;
            let settled = parseFloat($(this).find('.input-settled').val()) || 0;
            let disc = parseFloat($(this).find('.input-discount').val()) || 0;

            totDue += due;
            totAllocated += settled;
            totDisc += disc;
        });

        let enteredPayment = parseFloat($('#total-payment-amount').val()) || 0;
        let unallocated = enteredPayment - totAllocated;

        $('#footer-total-balance').text('₹' + totDue.toFixed(2));
        $('#footer-total-allocated').text('₹' + totAllocated.toFixed(2));
        $('#footer-total-discount').text('₹' + totDisc.toFixed(2));

        $('#bar-payment').text('₹' + enteredPayment.toFixed(2));
        $('#bar-allocated').text('₹' + totAllocated.toFixed(2));
        $('#bar-unallocated').text('₹' + Math.abs(unallocated).toFixed(2));

        if (Math.abs(unallocated) < 0.05) {
            $('#bar-unallocated').removeClass('text-danger text-warning').addClass('text-success').text('₹0.00 (Fully Allocated)');
        } else if (unallocated > 0) {
            $('#bar-unallocated').removeClass('text-success text-warning').addClass('text-danger').text('₹' + unallocated.toFixed(2) + ' Remaining');
        } else {
            $('#bar-unallocated').removeClass('text-success text-danger').addClass('text-warning').text('₹' + Math.abs(unallocated).toFixed(2) + ' Over-allocated');
        }
    }
});
</script>
@endpush
