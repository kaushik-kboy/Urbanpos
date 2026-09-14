@extends('adminlte::page')

@section('title', 'EOD / Settlement')

@section('content_header')
    <h1>EOD / Settlement</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr><th>Sales</th><td class="text-right">{{ $summary['sales_count'] }} bills / {{ number_format($summary['sales_total'], 2) }}</td></tr>
                            <tr><th>Returns</th><td class="text-right">{{ $summary['returns_count'] }} returns / {{ number_format($summary['returns_total'], 2) }}</td></tr>
                            <tr><th>Discounts</th><td class="text-right">{{ number_format($summary['discount_total'], 2) }}</td></tr>
                            <tr><th>GST</th><td class="text-right">{{ number_format($summary['gst_total'], 2) }}</td></tr>
                            <tr class="{{ $summary['till_variance_total'] == 0 ? '' : 'table-warning' }}">
                                <th>Till Variance</th><td class="text-right">{{ number_format($summary['till_variance_total'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th colspan="2">Payment Totals (by tender type)</th></tr></thead>
                        <tbody>
                            @forelse ($summary['payment_totals'] as $type => $amount)
                                <tr><th>{{ $type }}</th><td class="text-right">{{ number_format($amount, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-muted text-center">No split-tender payments recorded in this period.</td></tr>
                            @endforelse
                            @if ($summary['unattributed_total'] > 0)
                                <tr class="table-secondary">
                                    <th>Unattributed (no tender split recorded)</th>
                                    <td class="text-right">{{ number_format($summary['unattributed_total'], 2) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <h5 class="mt-3">Till Sessions in This Period</h5>
            <table class="table table-sm table-striped">
                <thead><tr><th>Register</th><th>Opened</th><th>Status</th><th class="text-right">Variance</th></tr></thead>
                <tbody>
                    @forelse ($tillSessions as $session)
                        <tr>
                            <td><a href="{{ route('till.sessions.show', $session) }}">{{ $session->register->name }}</a></td>
                            <td>{{ $session->opened_at->format('d-m-Y H:i') }}</td>
                            <td>{{ $session->status }}</td>
                            <td class="text-right">{{ is_null($session->variance) ? '—' : number_format($session->variance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No till sessions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
