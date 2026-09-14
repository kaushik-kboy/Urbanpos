@extends('adminlte::page')

@section('title', 'Till Session')

@section('content_header')
    <h1>Till Session — {{ $tillSession->register->name }}</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-error-summary />

    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Summary</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Branch</dt><dd class="col-6">{{ $tillSession->branch->name }}</dd>
                        <dt class="col-6">Opened By</dt><dd class="col-6">{{ $tillSession->user->name }}</dd>
                        <dt class="col-6">Opened At</dt><dd class="col-6">{{ $tillSession->opened_at->format('d-m-Y H:i') }}</dd>
                        <dt class="col-6">Opening Cash</dt><dd class="col-6">{{ number_format($tillSession->opening_cash, 2) }}</dd>
                        <dt class="col-6">Status</dt>
                        <dd class="col-6">
                            @if ($tillSession->isOpen())
                                <span class="badge badge-success">Open</span>
                            @else
                                <span class="badge badge-secondary">Closed</span>
                            @endif
                        </dd>
                        @unless ($tillSession->isOpen())
                            <dt class="col-6">Expected Cash</dt><dd class="col-6">{{ number_format($tillSession->expected_cash, 2) }}</dd>
                            <dt class="col-6">Actual Cash</dt><dd class="col-6">{{ number_format($tillSession->actual_cash, 2) }}</dd>
                            <dt class="col-6">Variance</dt><dd class="col-6">{{ number_format($tillSession->variance, 2) }}</dd>
                            <dt class="col-6">Closed By</dt><dd class="col-6">{{ $tillSession->closedBy?->name }}</dd>
                        @endunless
                    </dl>
                </div>
            </div>

            @if ($tillSession->isOpen())
                <div class="card card-warning card-outline">
                    <div class="card-header"><h3 class="card-title">Record Cash In/Out</h3></div>
                    <form action="{{ route('till.sessions.cash-movements', $tillSession) }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <x-select name="type" label="Type" :options="['In' => 'Cash In', 'Out' => 'Cash Out']" col="8" />
                            <x-field name="amount" label="Amount" type="number" step="0.01" col="8" />
                            <x-field name="reason" label="Reason" col="8" />
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-warning btn-sm">Record</button>
                        </div>
                    </form>
                </div>

                <div class="card card-danger card-outline">
                    <div class="card-header"><h3 class="card-title">Close Till</h3></div>
                    <form action="{{ route('till.sessions.close', $tillSession) }}" method="POST" onsubmit="return confirm('Close this till session?')">
                        @csrf
                        <div class="card-body">
                            <x-field name="actual_cash" label="Actual Cash Counted" type="number" step="0.01" col="8" />
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger btn-sm">Close Till</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-md-8">
            <div class="card card-outline">
                <div class="card-header"><h3 class="card-title">Cash In/Out</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Type</th><th>Amount</th><th>Reason</th><th>By</th><th>When</th></tr></thead>
                        <tbody>
                            @forelse ($tillSession->cashMovements as $movement)
                                <tr>
                                    <td>{{ $movement->type }}</td>
                                    <td>{{ number_format($movement->amount, 2) }}</td>
                                    <td>{{ $movement->reason }}</td>
                                    <td>{{ $movement->user->name }}</td>
                                    <td>{{ $movement->created_at->format('d-m-Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No cash movements recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-outline">
                <div class="card-header"><h3 class="card-title">Sales Linked to This Session</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Bill No</th><th>Date</th><th>Total</th></tr></thead>
                        <tbody>
                            @forelse ($tillSession->salesBills as $bill)
                                <tr>
                                    <td>{{ $bill->bill_number }}</td>
                                    <td>{{ $bill->bill_date->format('d-m-Y') }}</td>
                                    <td>{{ number_format($bill->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No sales linked yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
