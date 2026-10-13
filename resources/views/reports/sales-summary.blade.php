@extends('adminlte::page')

@section('title', 'Daily Sales Summary (Store Wise)')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-1">
        <div>
            <h1 class="h4 font-weight-bold text-dark mb-0">
                <i class="fas fa-store-alt text-primary mr-1"></i> Daily Sales Summary (Store Wise)
            </h1>
            <small class="text-muted">Comprehensive multi-tender store-wise daily billing, tax, and tender reconciliation report</small>
        </div>
        <div>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="btn btn-sm btn-success font-weight-bold shadow-sm mr-1">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="btn btn-sm btn-outline-success font-weight-bold shadow-sm mr-1">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary font-weight-bold shadow-sm">
                <i class="fas fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
@stop

@section('content')
    {{-- KPI Cards Row --}}
    <div class="row g-2 mb-3">
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card bg-gradient-info text-white shadow-sm mb-2 mb-md-0 border-0">
                <div class="card-body p-2 text-center">
                    <div class="small font-weight-bold text-white-50 text-uppercase">Total Stores</div>
                    <div class="h5 font-weight-bold mb-0">{{ $rows->pluck('store')->unique()->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card bg-gradient-primary text-white shadow-sm mb-2 mb-md-0 border-0">
                <div class="card-body p-2 text-center">
                    <div class="small font-weight-bold text-white-50 text-uppercase">Total Bills</div>
                    <div class="h5 font-weight-bold mb-0">{{ number_format($totals->total_bills ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card bg-gradient-success text-white shadow-sm mb-2 mb-md-0 border-0">
                <div class="card-body p-2 text-center">
                    <div class="small font-weight-bold text-white-50 text-uppercase">Bill Amount</div>
                    <div class="h5 font-weight-bold mb-0">₹{{ number_format($totals->bill_amount ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card bg-gradient-warning text-white shadow-sm mb-2 mb-md-0 border-0">
                <div class="card-body p-2 text-center">
                    <div class="small font-weight-bold text-white-50 text-uppercase">Total Tax</div>
                    <div class="h5 font-weight-bold mb-0">₹{{ number_format($totals->tax ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card bg-gradient-danger text-white shadow-sm mb-2 mb-md-0 border-0">
                <div class="card-body p-2 text-center">
                    <div class="small font-weight-bold text-white-50 text-uppercase">Disc. Amount</div>
                    <div class="h5 font-weight-bold mb-0">₹{{ number_format($totals->disc_amount ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6">
            <div class="card bg-gradient-secondary text-white shadow-sm mb-2 mb-md-0 border-0">
                <div class="card-body p-2 text-center">
                    <div class="small font-weight-bold text-white-50 text-uppercase">Gross Profit</div>
                    <div class="h5 font-weight-bold mb-0">₹{{ number_format($totals->profit ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-body p-3">
            {{-- Filter Bar --}}
            <form method="GET" class="form-inline mb-3 align-items-center">
                <label class="mr-2 font-weight-bold small text-muted"><i class="far fa-calendar-alt mr-1"></i> From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm mr-3">

                <label class="mr-2 font-weight-bold small text-muted"><i class="far fa-calendar-alt mr-1"></i> To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm mr-3">

                <label class="mr-2 font-weight-bold small text-muted"><i class="fas fa-store mr-1"></i> Location</label>
                <select name="branch_id" class="form-control form-control-sm mr-3" {{ count($branches) <= 1 ? 'readonly style=pointer-events:none;background:#f4f6f9;' : '' }}>
                    @if(count($branches) > 1)
                        <option value="all" {{ (!request('branch_id') || request('branch_id') === 'all') ? 'selected' : '' }}>All Locations</option>
                    @endif
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary btn-sm font-weight-bold mr-1 shadow-sm">
                    <i class="fas fa-filter mr-1"></i> Apply Filter
                </button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold">
                    <i class="fas fa-undo mr-1"></i> Reset
                </a>
            </form>

            {{-- 28-Column Full Detail Table --}}
            <div class="table-responsive border rounded" style="max-height: 650px; overflow-x: auto; overflow-y: auto;">
                <table id="sales-summary-table" class="table table-bordered table-striped table-hover table-sm text-nowrap mb-0" style="font-size: 13px;">
                    <thead class="thead-dark position-sticky" style="top: 0; z-index: 2;">
                        <tr>
                            <th class="align-middle">Store</th>
                            <th class="align-middle">Date</th>
                            <th class="align-middle text-right bg-primary text-white">Bill amount</th>
                            <th class="align-middle text-right">Tax</th>
                            <th class="align-middle text-right">Disc. Amount</th>
                            <th class="align-middle text-right">Scheme amount</th>
                            <th class="align-middle text-right">Cash</th>
                            <th class="align-middle text-right">Card</th>
                            <th class="align-middle text-right">Cheque</th>
                            <th class="align-middle text-right">Coupon</th>
                            <th class="align-middle text-right">Wallet Amt</th>
                            <th class="align-middle text-right">Credit</th>
                            <th class="align-middle text-right">Due</th>
                            <th class="align-middle text-right">Compliment</th>
                            <th class="align-middle text-right">Approval</th>
                            <th class="align-middle text-right">Advance adjusted</th>
                            <th class="align-middle text-right">Rounded off</th>
                            <th class="align-middle text-right">Profit</th>
                            <th class="align-middle text-right">Item discount</th>
                            <th class="align-middle text-right">Bill discount</th>
                            <th class="align-middle text-right">Freight</th>
                            <th class="align-middle text-right bg-info text-white">Total bills</th>
                            <th class="align-middle text-right">GST TaxAmt</th>
                            <th class="align-middle text-right">SGST TaxAmt</th>
                            <th class="align-middle text-right">CGST TaxAmt</th>
                            <th class="align-middle text-right">IGST TaxAmt</th>
                            <th class="align-middle text-right">GST Cess Amt</th>
                            <th class="align-middle text-right">Redeemed Point</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="font-weight-bold text-dark">{{ $row->store }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->bill_date)->format('d-m-Y') }}</td>
                                <td class="text-right font-weight-bold text-primary">{{ number_format($row->bill_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->tax, 2) }}</td>
                                <td class="text-right text-danger">{{ number_format($row->disc_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->scheme_amount, 2) }}</td>
                                <td class="text-right text-success font-weight-bold">{{ number_format($row->cash, 2) }}</td>
                                <td class="text-right">{{ number_format($row->card, 2) }}</td>
                                <td class="text-right">{{ number_format($row->cheque, 2) }}</td>
                                <td class="text-right">{{ number_format($row->coupon, 2) }}</td>
                                <td class="text-right">{{ number_format($row->wallet_amt, 2) }}</td>
                                <td class="text-right text-warning font-weight-bold">{{ number_format($row->credit, 2) }}</td>
                                <td class="text-right text-danger font-weight-bold">{{ number_format($row->due, 2) }}</td>
                                <td class="text-right">{{ number_format($row->compliment, 2) }}</td>
                                <td class="text-right">{{ number_format($row->approval, 2) }}</td>
                                <td class="text-right">{{ number_format($row->advance_adjusted, 2) }}</td>
                                <td class="text-right">{{ number_format($row->rounded_off, 2) }}</td>
                                <td class="text-right text-info font-weight-bold">{{ number_format($row->profit, 2) }}</td>
                                <td class="text-right">{{ number_format($row->item_discount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->bill_discount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->freight, 2) }}</td>
                                <td class="text-right font-weight-bold">{{ $row->total_bills }}</td>
                                <td class="text-right">{{ number_format($row->gst_tax_amt, 2) }}</td>
                                <td class="text-right">{{ number_format($row->sgst_tax_amt, 2) }}</td>
                                <td class="text-right">{{ number_format($row->cgst_tax_amt, 2) }}</td>
                                <td class="text-right">{{ number_format($row->igst_tax_amt, 2) }}</td>
                                <td class="text-right">{{ number_format($row->gst_cess_amt, 2) }}</td>
                                <td class="text-right">{{ number_format($row->redeemed_point, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="28" class="text-center text-muted py-4 font-weight-bold">
                                    <i class="fas fa-info-circle mr-1 text-info"></i> No sales records found for the selected date range and store filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="thead-dark position-sticky" style="bottom: 0; z-index: 2;">
                            <tr class="font-weight-bold bg-dark text-white">
                                <td class="align-middle">Grand Total</td>
                                <td class="align-middle text-muted">{{ $rows->count() }} Days</td>
                                <td class="text-right align-middle bg-primary text-white font-weight-bold">{{ number_format($totals->bill_amount, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->tax, 2) }}</td>
                                <td class="text-right align-middle text-danger">{{ number_format($totals->disc_amount, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->scheme_amount, 2) }}</td>
                                <td class="text-right align-middle text-success font-weight-bold">{{ number_format($totals->cash, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->card, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->cheque, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->coupon, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->wallet_amt, 2) }}</td>
                                <td class="text-right align-middle text-warning font-weight-bold">{{ number_format($totals->credit, 2) }}</td>
                                <td class="text-right align-middle text-danger font-weight-bold">{{ number_format($totals->due, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->compliment, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->approval, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->advance_adjusted, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->rounded_off, 2) }}</td>
                                <td class="text-right align-middle text-info font-weight-bold">{{ number_format($totals->profit, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->item_discount, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->bill_discount, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->freight, 2) }}</td>
                                <td class="text-right align-middle bg-info text-white font-weight-bold">{{ number_format($totals->total_bills) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->gst_tax_amt, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->sgst_tax_amt, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->cgst_tax_amt, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->igst_tax_amt, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->gst_cess_amt, 2) }}</td>
                                <td class="text-right align-middle">{{ number_format($totals->redeemed_point, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@stop
