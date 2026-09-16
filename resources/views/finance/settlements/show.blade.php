@extends('adminlte::page')

@section('title', 'Settlement #' . $settlement->settlement_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-hand-holding-usd text-success mr-2"></i>Settlement: {{ $settlement->settlement_number }}
                <span class="badge {{ $settlement->status === 'Active' ? 'badge-success' : 'badge-danger' }} ml-2" style="font-size: 0.6em; vertical-align: middle;">
                    {{ $settlement->status }}
                </span>
            </h1>
        </div>
        <div>
            <button type="button" class="btn btn-outline-dark btn-sm mr-1" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print Receipt
            </button>
            @if($settlement->status === 'Active')
                <form action="{{ route('finance.settlements.destroy', $settlement) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this settlement? Accounting voucher will be reversed.')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="reason" value="User cancelled from show page">
                    <button class="btn btn-outline-danger btn-sm mr-1"><i class="fas fa-ban mr-1"></i> Cancel Settlement</button>
                </form>
            @endif
            <a href="{{ route('finance.settlements.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="card card-outline {{ $settlement->settlement_type === 'Customer' ? 'card-success' : 'card-primary' }} shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0">
                        <i class="fas fa-user mr-2"></i>
                        {{ $settlement->settlement_type === 'Customer' ? 'Customer Details (Debtor)' : 'Supplier Details (Creditor)' }}
                    </h5>
                </div>
                <div class="card-body p-3">
                    @php
                        $party = $settlement->settlement_type === 'Customer' ? $settlement->customer : $settlement->supplier;
                    @endphp
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Name:</td>
                            <td class="font-weight-bold">{{ $party?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Phone:</td>
                            <td>{{ $party?->phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email:</td>
                            <td>{{ $party?->email ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">GSTIN:</td>
                            <td>{{ $party?->gstin ?: ($party?->gst_no ?: '-') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-outline card-info shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0 text-info">
                        <i class="fas fa-receipt mr-2"></i>Payment & Voucher Metadata
                    </h5>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Settlement Date:</td>
                            <td class="font-weight-bold">{{ $settlement->settlement_date?->format('d-M-Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Payment Mode:</td>
                            <td><span class="badge badge-light border">{{ $settlement->payment_mode }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Account Ledger:</td>
                            <td class="font-weight-bold">{{ $settlement->bankLedger?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Reference / UTR:</td>
                            <td>{{ $settlement->reference_no ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Journal Entry:</td>
                            <td>
                                @if($settlement->journalEntry)
                                    <span class="badge badge-primary">{{ $settlement->journalEntry->voucher_number }}</span>
                                    <small class="text-muted">({{ $settlement->journalEntry->voucher_type }})</small>
                                @else
                                    <span class="text-muted">None</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header py-2">
            <h5 class="card-title font-weight-bold mb-0 text-dark">
                <i class="fas fa-list-check mr-2 text-primary"></i>Settled Bills & Invoices Breakdown
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" style="width: 40px;">#</th>
                            <th>Bill / Invoice No</th>
                            <th>Bill Date</th>
                            <th class="text-right">Original Bill Amount</th>
                            <th class="text-right text-success">Amount Settled</th>
                            <th class="text-right text-danger">Cash Discount</th>
                            <th class="text-right font-weight-bold">Total Credit Given</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settlement->items as $idx => $line)
                            @php
                                $bill = $line->billable;
                                $billNumber = $bill ? ($bill->bill_number ?? ($bill->invoice_number ?? 'Bill #'.$line->billable_id)) : 'Bill #'.$line->billable_id;
                                $billDate = $bill ? ($bill->bill_date ?? ($bill->invoice_date ?? null)) : null;
                            @endphp
                            <tr>
                                <td class="text-center font-weight-bold">{{ $idx + 1 }}</td>
                                <td class="font-weight-bold">{{ $billNumber }}</td>
                                <td>{{ $billDate ? $billDate->format('d-m-Y') : '-' }}</td>
                                <td class="text-right">₹{{ number_format($line->bill_amount, 2) }}</td>
                                <td class="text-right font-weight-bold text-success">₹{{ number_format($line->settled_amount, 2) }}</td>
                                <td class="text-right text-danger">{{ (float)$line->discount_amount > 0 ? '₹'.number_format($line->discount_amount, 2) : '-' }}</td>
                                <td class="text-right font-weight-bold text-dark">₹{{ number_format($line->settled_amount + $line->discount_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="4" class="text-right">Total Settled:</td>
                            <td class="text-right text-success text-lg font-weight-bold">₹{{ number_format($settlement->total_amount, 2) }}</td>
                            <td class="text-right text-danger font-weight-bold">
                                {{ (float)$settlement->discount_amount > 0 ? '₹'.number_format($settlement->discount_amount, 2) : '₹0.00' }}
                            </td>
                            <td class="text-right text-lg text-primary font-weight-bold">
                                ₹{{ number_format($settlement->total_amount + $settlement->discount_amount, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($settlement->journalEntry && $settlement->journalEntry->lines->isNotEmpty())
        <div class="card card-outline card-light shadow-sm mb-3">
            <div class="card-header py-2">
                <h6 class="card-title font-weight-bold mb-0 text-muted">
                    <i class="fas fa-book mr-2"></i>Posted Accounting Journal Voucher ({{ $settlement->journalEntry->voucher_number }})
                </h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Ledger Account</th>
                            <th>Ledger Group</th>
                            <th class="text-right">Debit (₹)</th>
                            <th class="text-right">Credit (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($settlement->journalEntry->lines as $jLine)
                            <tr>
                                <td class="font-weight-bold">{{ $jLine->ledger?->name }}</td>
                                <td class="text-muted">{{ $jLine->ledger?->ledger_group }}</td>
                                <td class="text-right">{{ (float)$jLine->debit > 0 ? '₹'.number_format($jLine->debit, 2) : '-' }}</td>
                                <td class="text-right">{{ (float)$jLine->credit > 0 ? '₹'.number_format($jLine->credit, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@stop
