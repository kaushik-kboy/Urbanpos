@extends('adminlte::page')

@section('title', 'Trading and Profit & Loss Statement')

@section('content_header')
    <h1>Trading and Profit & Loss Statement</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('finance.reports.profit-loss') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="from" value="{{ $from }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="to" value="{{ $to }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('finance.reports.profit-loss') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row mb-3">
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-gradient-info">
                <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Net Sales</span>
                    <span class="info-box-number" style="font-size: 1.5rem;">₹{{ number_format($netSales, 2) }}</span>
                    <span class="progress-description text-white-50">Gross: ₹{{ number_format($grossSales, 2) }} | Return: ₹{{ number_format($salesReturn, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box {{ $grossProfit >= 0 ? 'bg-gradient-primary' : 'bg-gradient-danger' }}">
                <span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Gross Profit</span>
                    <span class="info-box-number" style="font-size: 1.5rem;">₹{{ number_format($grossProfit, 2) }}</span>
                    <span class="progress-description text-white-50">Margin: {{ $netSales > 0 ? number_format(($grossProfit / $netSales) * 100, 1) : 0 }}%</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box {{ $netProfit >= 0 ? 'bg-gradient-success' : 'bg-gradient-danger' }}">
                <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Net Profit / (Loss)</span>
                    <span class="info-box-number" style="font-size: 1.5rem;">₹{{ number_format($netProfit, 2) }}</span>
                    <span class="progress-description text-white-50">After Incomes & Expenses</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Financial Tables: Trading Account & P&L -->
    <div class="row">
        <!-- Trading Account (Debit vs Credit) -->
        <div class="col-md-6 mb-3">
            <div class="card card-primary card-outline h-100">
                <div class="card-header font-weight-bold bg-light">
                    <i class="fas fa-balance-scale"></i> Trading Account (Cost of Sales)
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Particulars</th>
                                <th class="text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Gross Purchases</td>
                                <td class="text-right font-weight-bold">{{ number_format($grossPurchase, 2) }}</td>
                            </tr>
                            <tr class="bg-light font-weight-bold">
                                <td>Total Cost of Goods Sold (COGS)</td>
                                <td class="text-right">{{ number_format($grossPurchase, 2) }}</td>
                            </tr>
                            <tr class="{{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                <td><strong>Gross Profit c/d</strong></td>
                                <td class="text-right">₹{{ number_format($grossProfit, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Trading Account (Revenue) -->
        <div class="col-md-6 mb-3">
            <div class="card card-primary card-outline h-100">
                <div class="card-header font-weight-bold bg-light">
                    <i class="fas fa-receipt"></i> Trading Account (Sales Revenue)
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Particulars</th>
                                <th class="text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Gross Sales</td>
                                <td class="text-right">{{ number_format($grossSales, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Less: Sales Return</td>
                                <td class="text-right text-danger">({{ number_format($salesReturn, 2) }})</td>
                            </tr>
                            <tr class="bg-light font-weight-bold text-primary">
                                <td>Net Sales Revenue</td>
                                <td class="text-right">₹{{ number_format($netSales, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Profit & Loss Account -->
    <div class="row">
        <!-- Expenses -->
        <div class="col-md-6 mb-3">
            <div class="card card-secondary card-outline h-100">
                <div class="card-header font-weight-bold bg-light">
                    <i class="fas fa-file-invoice-dollar"></i> Indirect & Operating Expenses
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Expense Ledger</th>
                                <th>Group</th>
                                <th class="text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenseLedgers as $exp)
                                <tr>
                                    <td>{{ $exp->name }}</td>
                                    <td><small class="text-muted">{{ $exp->group }}</small></td>
                                    <td class="text-right font-weight-bold">{{ number_format($exp->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No indirect expense entries.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td colspan="2">Total Indirect Expenses</td>
                                <td class="text-right text-danger">₹{{ number_format($totalExpenses, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Incomes & Net Profit -->
        <div class="col-md-6 mb-3">
            <div class="card card-secondary card-outline h-100">
                <div class="card-header font-weight-bold bg-light">
                    <i class="fas fa-hand-holding-usd"></i> Incomes & Net Profit
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Particulars</th>
                                <th>Group</th>
                                <th class="text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Gross Profit b/d</td>
                                <td><small class="text-muted">Trading A/c</small></td>
                                <td class="text-right font-weight-bold">{{ number_format($grossProfit, 2) }}</td>
                            </tr>
                            @forelse ($incomeLedgers as $inc)
                                <tr>
                                    <td>{{ $inc->name }}</td>
                                    <td><small class="text-muted">{{ $inc->group }}</small></td>
                                    <td class="text-right font-weight-bold">{{ number_format($inc->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-2">No indirect income entries.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr class="{{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}" style="font-size: 1.15rem;">
                                <td colspan="2"><strong>Net Profit / (Net Loss)</strong></td>
                                <td class="text-right"><strong>₹{{ number_format($netProfit, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
