@extends('adminlte::page')

@section('title', 'Day Book')

@section('content_header')
    <h1>Day Book</h1>
@stop

@section('content')
    <div class="row mb-3">
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-light shadow-sm border">
                <span class="info-box-icon bg-info"><i class="fas fa-receipt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Vouchers</span>
                    <span class="info-box-number font-weight-bold" style="font-size: 1.4rem;">{{ number_format($entries->count()) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-light shadow-sm border">
                <span class="info-box-icon bg-primary"><i class="fas fa-arrow-down"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Debit</span>
                    <span class="info-box-number text-primary font-weight-bold" style="font-size: 1.4rem;">₹{{ number_format($totalDebit ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-light shadow-sm border">
                <span class="info-box-icon bg-success"><i class="fas fa-arrow-up"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Credit</span>
                    <span class="info-box-number text-success font-weight-bold" style="font-size: 1.4rem;">₹{{ number_format($totalCredit ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" action="{{ route('finance.reports.day-book') }}" class="row align-items-end mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Voucher No, Ledger, Narration...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="from" value="{{ $from }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="to" value="{{ $to }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Location</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Locations</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Voucher Type</label>
                    <select name="voucher_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($voucherTypes as $vt)
                            <option value="{{ $vt }}" {{ request('voucher_type') == $vt ? 'selected' : '' }}>{{ $vt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('finance.reports.day-book') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>

            @forelse ($entries as $entry)
                <div class="mb-3 border rounded shadow-sm bg-white p-3">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <div>
                            <span class="badge badge-info mr-2">{{ $entry->voucher_type }}</span>
                            <strong>{{ $entry->voucher_number }}</strong>
                            <span class="text-muted ml-2">({{ $entry->voucher_date->format('d-m-Y') }})</span>
                        </div>
                        <span class="badge badge-light border"><i class="fas fa-map-marker-alt"></i> {{ $entry->branch?->name ?? 'Global' }}</span>
                    </div>
                    <table class="table table-sm table-hover mb-1">
                        <thead class="thead-light">
                            <tr>
                                <th>Particulars / Ledger</th>
                                <th class="text-right" style="width: 25%;">Debit (₹)</th>
                                <th class="text-right" style="width: 25%;">Credit (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entry->lines as $line)
                                <tr>
                                    <td>{{ $line->ledger?->name }}</td>
                                    <td class="text-right font-weight-bold text-primary">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                                    <td class="text-right font-weight-bold text-success">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold bg-light">
                                <td class="text-right text-muted small">Voucher Total:</td>
                                <td class="text-right text-primary">{{ number_format($entry->lines->sum('debit'), 2) }}</td>
                                <td class="text-right text-success">{{ number_format($entry->lines->sum('credit'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                    @if ($entry->narration)
                        <div class="text-muted small mt-2 bg-light p-2 rounded"><i class="fas fa-comment-dots text-secondary"></i> {{ $entry->narration }}</div>
                    @endif
                </div>
            @empty
                <p class="text-center text-muted py-5"><i class="fas fa-info-circle fa-2x mb-2 d-block"></i>No transactions found in this period.</p>
            @endforelse
        </div>
    </div>
@stop
