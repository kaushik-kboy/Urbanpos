@extends('adminlte::page')

@section('title', 'GSTR-3B Monthly Return')

@section('content_header')
<div class="d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h1 class="font-weight-bold text-dark mb-0">
            <i class="fas fa-balance-scale text-primary mr-2"></i> GSTR-3B: Monthly Return & ITC Offset
        </h1>
        <small class="text-muted">
            Statutory Self-Assessed Summary Return &bull; <strong>{{ $companyName }}</strong> &bull; GSTIN: <code>{{ $gstin }}</code>
        </small>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <a href="{{ route('tools.gst.gstr-3b.export', request()->all()) }}" class="btn btn-success btn-sm shadow-sm font-weight-bold mr-2">
            <i class="fas fa-file-csv mr-1"></i> Download GSTR-3B CSV
        </a>
        <a href="{{ route('tools.gst.gstr-1.page') }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold mr-2">
            <i class="fas fa-file-invoice-dollar mr-1"></i> GSTR-1 (Sales)
        </a>
        <a href="{{ route('tools.gst.gstr-2.page') }}" class="btn btn-info btn-sm shadow-sm font-weight-bold mr-2">
            <i class="fas fa-shopping-cart mr-1"></i> GSTR-2 (Purchases)
        </a>
        <a href="{{ route('tools.integrations-gst', ['view' => 'returns']) }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> GST Dashboard
        </a>
    </div>
</div>
@stop

@section('content')
<div class="container-fluid px-0">

    {{-- Filter Toolbar --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('tools.gst.gstr-3b.page') }}" class="form-inline d-flex justify-content-between flex-wrap">
                <div class="d-flex align-items-center my-1">
                    <span class="font-weight-bold text-dark mr-2 small"><i class="far fa-calendar-alt text-primary mr-1"></i> Return Period:</span>
                    <input type="date" name="from_date" class="form-control form-control-sm mr-2 font-weight-bold" value="{{ $fromDate }}">
                    <span class="mr-2 text-muted">to</span>
                    <input type="date" name="to_date" class="form-control form-control-sm mr-2 font-weight-bold" value="{{ $toDate }}">
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                        <i class="fas fa-filter mr-1"></i> Compute
                    </button>
                </div>
                <div class="d-flex align-items-center my-1">
                    <span class="badge badge-light border px-2 py-1 mr-2 text-dark">
                        <i class="fas fa-receipt mr-1 text-primary"></i> {{ $sales_count }} Sales Bills
                    </span>
                    <span class="badge badge-light border px-2 py-1 mr-2 text-dark">
                        <i class="fas fa-truck mr-1 text-success"></i> {{ $pur_count }} Purchase Invoices
                    </span>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI Metric Summary Cards --}}
    <div class="row">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient-danger text-white shadow-sm mb-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small font-weight-bold" style="letter-spacing: 0.5px;">1. Output Tax Liability</div>
                            <div class="h3 font-weight-bold mb-0">₹{{ number_format($table_6_1['total']['output_tax'], 2) }}</div>
                            <small class="text-white-50">Table 3.1 Total Tax Collected</small>
                        </div>
                        <i class="fas fa-hand-holding-usd fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient-success text-white shadow-sm mb-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small font-weight-bold" style="letter-spacing: 0.5px;">2. Net Eligible ITC</div>
                            <div class="h3 font-weight-bold mb-0">₹{{ number_format($table_4['net_itc']['total'], 2) }}</div>
                            <small class="text-white-50">Table 4(C) Input Tax Credit</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient-warning text-dark shadow-sm mb-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small font-weight-bold" style="letter-spacing: 0.5px;">3. Net Cash Tax Payable</div>
                            <div class="h3 font-weight-bold mb-0 text-dark">₹{{ number_format($table_6_1['total']['net_cash_payable'], 2) }}</div>
                            <small class="text-dark">Cash required to pay challan</small>
                        </div>
                        <i class="fas fa-wallet fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient-info text-white shadow-sm mb-0">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-uppercase small font-weight-bold" style="letter-spacing: 0.5px;">4. ITC Credit Balance</div>
                            <div class="h3 font-weight-bold mb-0">₹{{ number_format($table_6_1['total']['itc_carry_forward'], 2) }}</div>
                            <small class="text-white-50">Carried forward to next month</small>
                        </div>
                        <i class="fas fa-piggy-bank fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLE 3.1: OUTWARD SUPPLIES --}}
    <div class="card card-outline card-danger shadow-sm mb-3">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <span class="badge badge-danger mr-1">3.1</span> Details of Outward Supplies and inward supplies liable to reverse charge
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead class="thead-dark small text-center">
                        <tr>
                            <th class="text-left">Nature of Supplies</th>
                            <th style="width: 15%;">Total Taxable Value (₹)</th>
                            <th style="width: 14%;">Integrated Tax (₹)</th>
                            <th style="width: 14%;">Central Tax (₹)</th>
                            <th style="width: 14%;">State/UT Tax (₹)</th>
                            <th style="width: 10%;">Cess (₹)</th>
                            <th style="width: 14%;">Total Tax (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="small font-weight-bold">
                        @foreach($table_3_1 as $key => $row)
                        <tr class="{{ $key === 'a' ? 'table-warning' : '' }}">
                            <td>{{ $row['desc'] }}</td>
                            <td class="text-right">{{ number_format($row['taxable'], 2) }}</td>
                            <td class="text-right text-info">{{ number_format($row['igst'], 2) }}</td>
                            <td class="text-right text-primary">{{ number_format($row['cgst'], 2) }}</td>
                            <td class="text-right text-success">{{ number_format($row['sgst'], 2) }}</td>
                            <td class="text-right text-muted">{{ number_format($row['cess'], 2) }}</td>
                            <td class="text-right text-danger font-weight-bold">{{ number_format($row['total_tax'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TABLE 3.2: INTER-STATE SUPPLIES TO UNREGISTERED --}}
    @if(!empty($table_3_2))
    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header py-2 bg-light">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <span class="badge badge-secondary mr-1">3.2</span> Inter-State supplies made to unregistered persons
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light small text-center">
                        <tr>
                            <th>Place of Supply (State Code)</th>
                            <th class="text-right" style="width: 25%;">Total Taxable Value (₹)</th>
                            <th class="text-right" style="width: 25%;">Amount of Integrated Tax (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($table_3_2 as $isRow)
                        <tr>
                            <td>State Code: <strong>{{ $isRow['pos'] }}</strong></td>
                            <td class="text-right font-weight-bold">{{ number_format($isRow['taxable_value'], 2) }}</td>
                            <td class="text-right font-weight-bold text-info">{{ number_format($isRow['igst'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- TABLE 4: ELIGIBLE INPUT TAX CREDIT (ITC) --}}
    <div class="card card-outline card-success shadow-sm mb-3">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <span class="badge badge-success mr-1">4</span> Eligible Input Tax Credit (ITC)
            </h5>
            <small class="text-muted">Derived from Purchase Invoices & Purchase Returns</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead class="thead-dark small text-center">
                        <tr>
                            <th class="text-left">Details</th>
                            <th style="width: 18%;">Integrated Tax (₹)</th>
                            <th style="width: 18%;">Central Tax (₹)</th>
                            <th style="width: 18%;">State/UT Tax (₹)</th>
                            <th style="width: 14%;">Cess (₹)</th>
                            <th style="width: 18%;">Total ITC (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <tr class="bg-light font-weight-bold">
                            <td colspan="6"><strong>(A) ITC Available (whether in full or part)</strong></td>
                        </tr>
                        @foreach($table_4['available'] as $aKey => $aRow)
                        <tr class="{{ $aKey === 'all_other' ? 'table-success font-weight-bold' : '' }}">
                            <td class="pl-4">
                                {{ $aRow['desc'] }}
                                @if(isset($aRow['inv_count']))
                                    <span class="badge badge-success ml-1">{{ $aRow['inv_count'] }} Invoices</span>
                                @endif
                            </td>
                            <td class="text-right text-info">{{ number_format($aRow['igst'], 2) }}</td>
                            <td class="text-right text-primary">{{ number_format($aRow['cgst'], 2) }}</td>
                            <td class="text-right text-success">{{ number_format($aRow['sgst'], 2) }}</td>
                            <td class="text-right text-muted">{{ number_format($aRow['cess'], 2) }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($aRow['total_itc'] ?? ($aRow['igst'] + $aRow['cgst'] + $aRow['sgst']), 2) }}</td>
                        </tr>
                        @endforeach

                        <tr class="bg-light font-weight-bold">
                            <td colspan="6"><strong>(B) ITC Reversed</strong></td>
                        </tr>
                        @foreach($table_4['reversed'] as $bKey => $bRow)
                        <tr class="{{ $bKey === 'others' && ($bRow['total'] ?? 0) > 0 ? 'table-warning font-weight-bold' : '' }}">
                            <td class="pl-4">
                                {{ $bRow['desc'] }}
                                @if(isset($bRow['ret_count']) && $bRow['ret_count'] > 0)
                                    <span class="badge badge-warning ml-1">{{ $bRow['ret_count'] }} Debit Notes</span>
                                @endif
                            </td>
                            <td class="text-right text-danger">{{ number_format($bRow['igst'], 2) }}</td>
                            <td class="text-right text-danger">{{ number_format($bRow['cgst'], 2) }}</td>
                            <td class="text-right text-danger">{{ number_format($bRow['sgst'], 2) }}</td>
                            <td class="text-right text-muted">0.00</td>
                            <td class="text-right text-danger font-weight-bold">{{ number_format($bRow['total'] ?? ($bRow['igst'] + $bRow['cgst'] + $bRow['sgst']), 2) }}</td>
                        </tr>
                        @endforeach

                        <tr class="table-primary font-weight-bold" style="font-size: 13px;">
                            <td><strong>{{ $table_4['net_itc']['desc'] }}</strong></td>
                            <td class="text-right text-info font-weight-bold">{{ number_format($table_4['net_itc']['igst'], 2) }}</td>
                            <td class="text-right text-primary font-weight-bold">{{ number_format($table_4['net_itc']['cgst'], 2) }}</td>
                            <td class="text-right text-success font-weight-bold">{{ number_format($table_4['net_itc']['sgst'], 2) }}</td>
                            <td class="text-right text-muted">0.00</td>
                            <td class="text-right font-weight-bold text-dark">{{ number_format($table_4['net_itc']['total'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- TABLE 6.1: PAYMENT OF TAX & SET-OFF MATRIX --}}
    <div class="card card-outline card-warning shadow-sm mb-4">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <span class="badge badge-warning text-dark mr-1">6.1</span> Payment of Tax & Liability Offset Matrix
            </h5>
            <small class="text-muted">Statutory GST Rules: IGST offset against IGST first; CGST/SGST against respective credits</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead class="thead-dark small text-center">
                        <tr>
                            <th class="text-left">Description</th>
                            <th style="width: 20%;">Total Tax Payable (₹)</th>
                            <th style="width: 25%;">Paid Through ITC (₹)</th>
                            <th style="width: 25%;">Tax Paid in Cash (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="small font-weight-bold">
                        <tr>
                            <td>Integrated Tax (IGST)</td>
                            <td class="text-right text-info">{{ number_format($table_6_1['igst']['payable'], 2) }}</td>
                            <td class="text-right text-success">{{ number_format($table_6_1['igst']['paid_itc'], 2) }}</td>
                            <td class="text-right text-danger">{{ number_format($table_6_1['igst']['paid_cash'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Central Tax (CGST)</td>
                            <td class="text-right text-primary">{{ number_format($table_6_1['cgst']['payable'], 2) }}</td>
                            <td class="text-right text-success">{{ number_format($table_6_1['cgst']['paid_itc'], 2) }}</td>
                            <td class="text-right text-danger">{{ number_format($table_6_1['cgst']['paid_cash'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>State/UT Tax (SGST)</td>
                            <td class="text-right text-success">{{ number_format($table_6_1['sgst']['payable'], 2) }}</td>
                            <td class="text-right text-success">{{ number_format($table_6_1['sgst']['paid_itc'], 2) }}</td>
                            <td class="text-right text-danger">{{ number_format($table_6_1['sgst']['paid_cash'], 2) }}</td>
                        </tr>
                        <tr class="table-warning font-weight-bold" style="font-size: 14px;">
                            <td><strong>TOTAL LIABILITY</strong></td>
                            <td class="text-right text-dark font-weight-bold">₹{{ number_format($table_6_1['total']['output_tax'], 2) }}</td>
                            <td class="text-right text-success font-weight-bold">₹{{ number_format($table_6_1['total']['itc_utilized'], 2) }}</td>
                            <td class="text-right text-danger font-weight-bold">₹{{ number_format($table_6_1['total']['net_cash_payable'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-2">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <span class="small font-weight-bold text-dark">
                    <i class="fas fa-info-circle text-primary mr-1"></i> Net Cash to Pay via GST Portal Challan: 
                    <span class="badge badge-danger p-2 ml-1" style="font-size: 14px;">₹{{ number_format($table_6_1['total']['net_cash_payable'], 2) }}</span>
                </span>
                <span class="small font-weight-bold text-dark">
                    ITC Credit Balance to Carry Forward: 
                    <span class="badge badge-info p-2 ml-1" style="font-size: 14px;">₹{{ number_format($table_6_1['total']['itc_carry_forward'], 2) }}</span>
                </span>
            </div>
        </div>
    </div>

</div>
@stop
