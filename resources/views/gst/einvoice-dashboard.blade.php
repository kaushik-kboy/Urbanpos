@extends('adminlte::page')

@section('title', 'GST E-Filing & E-Invoice Hub')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-file-invoice-dollar text-primary mr-2"></i>GST E-Filing &amp; E-Invoice Integration Hub
            </h1>
            <p class="text-muted small mb-0">Government GST Returns (GSTR-1, 3B, 2, 2A, 2B, 9) &amp; Automated E-Invoice Upload (&gt; ₹{{ number_format($settings->auto_upload_threshold, 0) }})</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center flex-wrap">
            <button type="button" class="btn btn-outline-dark btn-sm shadow-sm mr-2 mb-1" data-toggle="modal" data-target="#settingsModal">
                <i class="fas fa-cog mr-1"></i> API Settings
            </button>
            <a href="{{ route('tools.eway-update') }}" class="btn btn-outline-info btn-sm shadow-sm mr-2 mb-1">
                <i class="fas fa-route mr-1"></i> E-Way Bill Dashboard
            </a>
            <a href="{{ route('sales.sales-bills.create') }}" class="btn btn-primary btn-sm shadow-sm mb-1">
                <i class="fas fa-plus mr-1"></i> New Sales Bill
            </a>
        </div>
    </div>
@stop

@section('content')
    {{-- Alerts --}}
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i>{{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Main Module Switcher: Returns vs E-Invoice Hub --}}
    <div class="card shadow-sm mb-3 border-0 bg-white">
        <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center flex-wrap">
            <div class="nav nav-pills" role="tablist">
                <a class="nav-link font-weight-bold px-4 py-2 mr-2 {{ $viewMode === 'returns' ? 'active shadow-sm' : 'text-dark bg-light' }}" 
                   href="{{ route('tools.integrations-gst', array_merge(request()->query(), ['view' => 'returns'])) }}">
                    <i class="fas fa-chart-pie mr-2"></i> GST Returns &amp; Filing (GSTR-1, 3B, 2, 2A, 2B, 9)
                </a>
                <a class="nav-link font-weight-bold px-4 py-2 {{ $viewMode === 'einvoice' ? 'active shadow-sm' : 'text-dark bg-light' }}" 
                   href="{{ route('tools.integrations-gst', array_merge(request()->query(), ['view' => 'einvoice'])) }}">
                    <i class="fas fa-bolt mr-2 text-warning"></i> E-Invoice Hub (&gt; ₹50k Auto-Upload)
                    <span class="badge badge-light ml-2 text-dark">{{ $pendingCount + $failedCount + $completedCount }}</span>
                </a>
            </div>

            {{-- Period Filter Toolbar --}}
            <form method="GET" action="{{ route('tools.integrations-gst') }}" class="form-inline mt-2 mt-md-0">
                <input type="hidden" name="view" value="{{ $viewMode }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                
                <span class="small font-weight-bold text-muted mr-2"><i class="fas fa-calendar-alt mr-1"></i> Period:</span>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control form-control-sm mr-2" style="width: 140px;">
                <span class="mr-2 text-muted">to</span>
                <input type="date" name="to_date" value="{{ $toDate }}" class="form-control form-control-sm mr-2" style="width: 140px;">
                <button type="submit" class="btn btn-secondary btn-sm px-3 shadow-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Filter
                </button>
            </form>
        </div>
    </div>

    @if ($viewMode === 'returns')
        {{-- =================================================================== --}}
        {{-- SCREEN 1: TRUEPOS GST RETURNS DASHBOARD (Exact cards from video/6.mp4) --}}
        {{-- =================================================================== --}}
        <div class="row">
            {{-- 1. GSTR-1 Card --}}
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #007bff !important;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="font-weight-bold text-primary mb-0">GSTR-1</h3>
                                <span class="badge badge-primary px-2 py-1">Outward Supplies</span>
                            </div>
                            <div class="text-muted small mb-3">
                                <i class="fas fa-file-invoice mr-1"></i> <strong>{{ number_format($gstr1Count) }} invoices</strong>
                            </div>
                            
                            <div class="p-3 bg-light rounded mb-3 border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Taxable Sale:</span>
                                    <strong class="text-dark">₹{{ number_format($gstr1Taxable, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small font-weight-bold">Tax Collected:</span>
                                    <strong class="text-primary font-weight-bold">₹{{ number_format($gstr1TaxCollected, 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <a href="{{ route('tools.gst.gstr-1.page') }}" class="btn btn-primary btn-sm font-weight-bold shadow-sm">
                                <i class="fas fa-chart-bar mr-1"></i> VIEW GSTR-1 (12 CARDS)
                            </a>
                            <a href="{{ route('reports.gst-sales-summary', ['from' => $fromDate, 'to' => $toDate]) }}" class="btn btn-light btn-sm text-muted">
                                Detailed Register <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. GSTR-3B Card --}}
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #28a745 !important;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="font-weight-bold text-success mb-0">GSTR-3B</h3>
                                <span class="badge badge-success px-2 py-1">Monthly Summary</span>
                            </div>
                            <div class="text-muted small mb-3">
                                <i class="fas fa-balance-scale mr-1"></i> <strong>Net Tax Liability Computation</strong>
                            </div>

                            <div class="p-3 bg-light rounded mb-3 border">
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span class="small font-weight-bold">Tax Payable:</span>
                                    <strong class="h6 mb-0 font-weight-bold">₹{{ number_format($gstr3bTaxPayable, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Tax Paid (ITC):</span>
                                    <strong class="text-dark">₹{{ number_format($gstr3bTaxPaid, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Tax Collected:</span>
                                    <strong class="text-dark">₹{{ number_format($gstr3bTaxCollected, 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button type="button" class="btn btn-outline-success btn-sm font-weight-bold" onclick="openGstr3bModal()">
                                <i class="fas fa-file-invoice mr-1"></i> VIEW GSTR-3B
                            </button>
                            <span class="small text-muted font-weight-bold">Auto-Computed</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. GSTR-2 Card --}}
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #17a2b8 !important;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="font-weight-bold text-info mb-0">GSTR-2</h3>
                                <span class="badge badge-info px-2 py-1">Inward Purchases</span>
                            </div>
                            <div class="text-muted small mb-3">
                                <i class="fas fa-truck mr-1"></i> <strong>{{ number_format($gstr2Count) }} invoices</strong>
                            </div>

                            <div class="p-3 bg-light rounded mb-3 border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Taxable Purchase:</span>
                                    <strong class="text-dark">₹{{ number_format($gstr2Taxable, 2) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small font-weight-bold">Tax Paid:</span>
                                    <strong class="text-info font-weight-bold">₹{{ number_format($gstr2TaxPaid, 2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <a href="{{ route('reports.purchase-detail', ['from' => $fromDate, 'to' => $toDate]) }}" class="btn btn-outline-info btn-sm font-weight-bold">
                                <i class="fas fa-list mr-1"></i> PURCHASE REGISTER
                            </a>
                            <a href="{{ route('reports.gst-purchase-summary', ['from' => $fromDate, 'to' => $toDate]) }}" class="btn btn-light btn-sm text-muted">
                                GST Summary <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. GSTR-2A Card --}}
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #ffc107 !important;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="font-weight-bold text-warning mb-0" style="color: #d39e00 !important;">GSTR-2A</h3>
                                <span class="badge badge-warning px-2 py-1">Auto-Drafted ITC</span>
                            </div>
                            <p class="text-muted small mb-3">
                                Dynamic supplier invoice statement auto-generated from suppliers' filed GSTR-1.
                            </p>

                            <div class="p-3 bg-light rounded mb-3 border text-center">
                                <span class="small text-muted d-block mb-1">Reconciliation with UrbanPOS Books</span>
                                <strong class="text-dark"><i class="fas fa-check-double text-success mr-1"></i> ITC Available</strong>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button type="button" class="btn btn-outline-warning btn-sm font-weight-bold text-dark" onclick="openUploadModal('2a')">
                                <i class="fas fa-upload mr-1"></i> UPLOAD GSTR-2A
                            </button>
                            <a href="{{ route('tools.gst.gstr-2-download', ['type' => '2A']) }}" class="btn btn-outline-secondary btn-sm font-weight-bold">
                                <i class="fas fa-download mr-1"></i> DOWNLOAD GSTR-2A
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. GSTR-2B Card --}}
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #6f42c1 !important;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="font-weight-bold mb-0" style="color: #6f42c1;">GSTR-2B</h3>
                                <span class="badge badge-light border px-2 py-1" style="color: #6f42c1;">Static ITC Statement</span>
                            </div>
                            <p class="text-muted small mb-3">
                                Fixed static ITC statement for monthly GSTR-3B auto-population and reconciliation.
                            </p>

                            <div class="p-3 bg-light rounded mb-3 border text-center">
                                <span class="small text-muted d-block mb-1">Monthly Static Snapshot</span>
                                <strong class="text-dark"><i class="fas fa-shield-alt text-primary mr-1"></i> Audit Matched</strong>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button type="button" class="btn btn-outline-purple btn-sm font-weight-bold" style="color: #6f42c1; border-color: #6f42c1;" onclick="openUploadModal('2b')">
                                <i class="fas fa-upload mr-1"></i> UPLOAD GSTR-2B
                            </button>
                            <a href="{{ route('tools.gst.gstr-2-download', ['type' => '2B']) }}" class="btn btn-outline-secondary btn-sm font-weight-bold">
                                <i class="fas fa-download mr-1"></i> DOWNLOAD GSTR-2B
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 6. GSTR-9 Card --}}
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #e83e8c !important;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="font-weight-bold mb-0" style="color: #e83e8c;">GSTR-9</h3>
                                <span class="badge badge-light border px-2 py-1" style="color: #e83e8c;">Annual Return</span>
                            </div>
                            <div class="alert alert-light border py-2 px-3 mb-3 small text-muted">
                                <i class="fas fa-info-circle mr-1 text-info"></i>
                                <strong>Note :</strong> Annual returns might take a while to generate.
                            </div>

                            <div class="p-3 bg-light rounded mb-3 border text-center">
                                <span class="small text-muted d-block mb-1">Financial Year Aggregation</span>
                                <strong class="text-dark" id="gstr9StatusText"><i class="fas fa-calendar-check text-success mr-1"></i> FY 2026-27 Ready</strong>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button type="button" class="btn btn-danger btn-sm font-weight-bold shadow-sm px-3" style="background-color: #e83e8c; border-color: #e83e8c;" onclick="syncGstr9()">
                                <i class="fas fa-sync-alt mr-1" id="gstr9SyncIcon"></i> SYNC NOW
                            </button>
                            <span class="small text-muted">Full Year Audit</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- =================================================================== --}}
        {{-- SCREEN 2: E-INVOICE HUB (Pending / Failed / Completed Tabs + Auto-Upload) --}}
        {{-- =================================================================== --}}
        <div class="card card-outline card-primary shadow-sm border-0">
            {{-- Status Tabs --}}
            <div class="card-header p-0 border-bottom-0 bg-light">
                <ul class="nav nav-tabs" id="einvoiceTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold {{ $tab === 'pending' ? 'active text-warning border-top-warning' : 'text-muted' }}" 
                           href="{{ route('tools.integrations-gst', array_merge(request()->query(), ['tab' => 'pending', 'view' => 'einvoice'])) }}">
                            <i class="fas fa-clock mr-1"></i> PENDING
                            <span class="badge badge-warning ml-2 px-2 py-1">{{ $pendingCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold {{ $tab === 'failed' ? 'active text-danger border-top-danger' : 'text-muted' }}" 
                           href="{{ route('tools.integrations-gst', array_merge(request()->query(), ['tab' => 'failed', 'view' => 'einvoice'])) }}">
                            <i class="fas fa-exclamation-circle mr-1"></i> FAILED
                            <span class="badge badge-danger ml-2 px-2 py-1">{{ $failedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold {{ $tab === 'completed' ? 'active text-success border-top-success' : 'text-muted' }}" 
                           href="{{ route('tools.integrations-gst', array_merge(request()->query(), ['tab' => 'completed', 'view' => 'einvoice'])) }}">
                            <i class="fas fa-check-circle mr-1"></i> COMPLETED
                            <span class="badge badge-success ml-2 px-2 py-1">{{ $completedCount }}</span>
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Filter Toolbar --}}
            <div class="card-body bg-white border-bottom py-3">
                <form method="GET" action="{{ route('tools.integrations-gst') }}" id="filterForm" class="row align-items-end">
                    <input type="hidden" name="view" value="einvoice">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">From Date</label>
                        <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">To Date</label>
                        <input type="date" name="to_date" value="{{ $toDate }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Invoice Type</label>
                        <select name="doc_type" class="form-control form-control-sm">
                            <option value="all" {{ $docType === 'all' ? 'selected' : '' }}>All Document Types</option>
                            <option value="INV" {{ $docType === 'INV' ? 'selected' : '' }}>INV (Tax Invoice)</option>
                            <option value="CRN" {{ $docType === 'CRN' ? 'selected' : '' }}>CRN (Credit Note)</option>
                            <option value="DBN" {{ $docType === 'DBN' ? 'selected' : '' }}>DBN (Debit Note)</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Customer / GSTIN</label>
                        <input type="text" name="customer" value="{{ $customerSearch }}" placeholder="Name, Phone, GSTIN" class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Invoice Number</label>
                        <input type="text" name="search" value="{{ $invoiceSearch }}" placeholder="Bill No..." class="form-control form-control-sm">
                    </div>
                    <div class="col-lg-2 col-md-8 col-sm-6 mb-2 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm mr-2 shadow-sm">
                            <i class="fas fa-filter mr-1"></i> Apply
                        </button>
                        <a href="{{ route('tools.integrations-gst', ['view' => 'einvoice', 'tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm mr-2">
                            Reset
                        </a>
                        @if ($tab === 'failed' || $failedCount > 0)
                            <a href="{{ route('tools.einvoice.download-errors') }}" class="btn btn-outline-danger btn-sm shadow-sm" title="Download CSV of all failed validation errors">
                                <i class="fas fa-download mr-1"></i> ERRORS
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Table Container --}}
            <div class="card-body p-0 table-responsive">
                <form id="bulkActionForm" method="POST" action="{{ route('tools.einvoice.generate-irn') }}">
                    @csrf
                    <input type="hidden" name="action_type" id="bulkActionType" value="generate">

                    <table class="table table-hover table-striped table-bordered mb-0 align-middle">
                        <thead class="thead-light" style="background-color: #f8fafc;">
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" id="selectAllCheckbox" style="transform: scale(1.15); cursor: pointer;">
                                </th>
                                <th>Customer Details</th>
                                <th>Customer GSTIN</th>
                                <th>Reference Number</th>
                                <th class="text-right">Invoice Amount</th>
                                <th>Invoice Date</th>
                                <th class="text-center">Type</th>
                                @if ($tab === 'completed')
                                    <th>Govt IRN (64-char Hash)</th>
                                    <th>Ack Details</th>
                                @elseif ($tab === 'failed')
                                    <th class="text-danger">Error Reason (Rejection)</th>
                                    <th class="text-center" style="width: 120px;">Retry Action</th>
                                @else
                                    <th class="text-center" style="width: 140px;">Status</th>
                                    <th class="text-center" style="width: 140px;">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bills as $bill)
                                <tr class="bill-row" data-id="{{ $bill->id }}" style="cursor: pointer;">
                                    <td class="text-center" onclick="event.stopPropagation();">
                                        <input type="checkbox" name="bill_ids[]" value="{{ $bill->id }}" class="bill-checkbox" style="transform: scale(1.15); cursor: pointer;">
                                    </td>
                                    <td>
                                        <strong class="text-dark">{{ $bill->customer?->name ?? 'Walk-in Customer' }}</strong>
                                        @if ($bill->customer?->phone)
                                            <span class="d-block small text-muted"><i class="fas fa-phone mr-1"></i>{{ $bill->customer->phone }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($bill->customer?->gst_no)
                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-id-card mr-1"></i>{{ $bill->customer->gst_no }}</span>
                                        @else
                                            <span class="text-muted small">URP (B2C)</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="font-weight-bold text-primary">{{ $bill->bill_number }}</span>
                                        @if ($bill->total >= $settings->auto_upload_threshold)
                                            <span class="badge badge-warning small ml-1">&gt;₹50k</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-weight-bold">
                                        ₹{{ number_format($bill->total, 2) }}
                                    </td>
                                    <td>
                                        {{ $bill->bill_date ? $bill->bill_date->format('d/m/Y') : '' }}
                                        <span class="d-block small text-muted">{{ $bill->bill_date ? $bill->bill_date->format('h:i A') : '' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-secondary">INV</span>
                                    </td>

                                    @if ($tab === 'completed')
                                        <td style="max-width: 250px;">
                                            <code class="text-success small d-block text-truncate" title="{{ $bill->irn }}">
                                                {{ $bill->irn }}
                                            </code>
                                            <span class="small text-muted"><i class="fas fa-check mr-1 text-success"></i>Signed by Govt IRP</span>
                                        </td>
                                        <td>
                                            <div class="small font-weight-bold">Ack: {{ $bill->ack_no }}</div>
                                            <div class="small text-muted">{{ $bill->ack_date ? $bill->ack_date->format('d/m/Y h:i A') : '' }}</div>
                                        </td>
                                    @elseif ($tab === 'failed')
                                        <td class="text-danger small">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <strong>{{ $bill->einvoice_error ?? 'Validation failed at portal schema check.' }}</strong>
                                        </td>
                                        <td class="text-center" onclick="event.stopPropagation();">
                                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="quickUpload({{ $bill->id }})">
                                                <i class="fas fa-redo-alt mr-1"></i> Retry
                                            </button>
                                        </td>
                                    @else
                                        <td class="text-center">
                                            @if ($bill->total >= $settings->auto_upload_threshold || !empty($bill->customer?->gst_no))
                                                <span class="badge badge-warning px-2 py-1">Ready to Push</span>
                                            @else
                                                <span class="badge badge-light border">Standard B2C</span>
                                            @endif
                                        </td>
                                        <td class="text-center" onclick="event.stopPropagation();">
                                            <button type="button" class="btn btn-primary btn-sm py-0 px-2" onclick="quickUpload({{ $bill->id }})">
                                                <i class="fas fa-paper-plane mr-1"></i> Push IRN
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 text-secondary d-block"></i>
                                        <h5>No invoices found in this view</h5>
                                        <p class="small mb-0">Change the date filter or switch tabs to view other records.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </form>
            </div>

            {{-- Bottom Action Bar --}}
            <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap py-3 border-top">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span class="font-weight-bold text-dark mr-3" id="selectionCountText">0 Invoices Selected</span>
                    
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="radioGenerateIrn" name="action_choice" class="custom-control-input" value="generate" checked>
                        <label class="custom-control-label font-weight-bold" for="radioGenerateIrn">
                            <i class="fas fa-bolt text-warning mr-1"></i> Generate IRN (Govt Portal)
                        </label>
                    </div>
                    <div class="custom-control custom-radio custom-control-inline">
                        <input type="radio" id="radioExportJson" name="action_choice" class="custom-control-input" value="json">
                        <label class="custom-control-label font-weight-bold" for="radioExportJson">
                            <i class="fas fa-file-code text-info mr-1"></i> Export as JSON (Offline)
                        </label>
                    </div>
                </div>

                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm px-4 mr-3" id="btnExecuteBulk" disabled>
                        <i class="fas fa-play mr-1"></i> GENERATE / DOWNLOAD
                    </button>
                    <div>
                        {{ $bills->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 1: GSTR-1 Breakdown Modal --}}
    <div class="modal fade" id="gstr1Modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-file-invoice mr-2"></i>GSTR-1 Outward Supplies Summary</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-white border rounded">
                                <span class="text-muted small d-block">PERIOD</span>
                                <strong id="gstr1PeriodText">--</strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-white border rounded">
                                <span class="text-muted small d-block">TOTAL SALES VALUE</span>
                                <strong class="text-primary h5 mb-0" id="gstr1TotalTurnover">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-none border mb-0">
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Section / Category</th>
                                        <th class="text-center">Invoices</th>
                                        <th class="text-right">Taxable Value</th>
                                        <th class="text-right">Tax Amount</th>
                                        <th class="text-right">Total Invoice Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>4A, 4B, 6B, 6C - B2B Invoices</strong> (Registered)</td>
                                        <td class="text-center" id="gstr1B2bCount">0</td>
                                        <td class="text-right" id="gstr1B2bTaxable">₹0.00</td>
                                        <td class="text-right font-weight-bold text-primary" id="gstr1B2bTax">₹0.00</td>
                                        <td class="text-right font-weight-bold" id="gstr1B2bTotal">₹0.00</td>
                                    </tr>
                                    <tr>
                                        <td><strong>7 - B2C Small</strong> (Retail / Walk-in &lt; 2.5L)</td>
                                        <td class="text-center" id="gstr1B2csCount">0</td>
                                        <td class="text-right" id="gstr1B2csTaxable">₹0.00</td>
                                        <td class="text-right font-weight-bold text-primary" id="gstr1B2csTax">₹0.00</td>
                                        <td class="text-right font-weight-bold" id="gstr1B2csTotal">₹0.00</td>
                                    </tr>
                                    <tr>
                                        <td><strong>5A, 5B - B2C Large</strong> (Interstate &gt; 2.5L)</td>
                                        <td class="text-center" id="gstr1B2clCount">0</td>
                                        <td class="text-right" id="gstr1B2clTaxable">₹0.00</td>
                                        <td class="text-right font-weight-bold text-primary" id="gstr1B2clTax">₹0.00</td>
                                        <td class="text-right font-weight-bold" id="gstr1B2clTotal">₹0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <a href="{{ route('reports.gst-sales-summary') }}" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-external-link-alt mr-1"></i> Open Full GSTR-1 Sales Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 2: GSTR-3B Computation Modal --}}
    <div class="modal fade" id="gstr3bModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-success text-white py-3">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-balance-scale mr-2"></i>GSTR-3B Monthly Return Summary</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    {{-- 3.1 Outward Taxable Supplies --}}
                    <div class="card shadow-none border mb-3">
                        <div class="card-header bg-white py-2 font-weight-bold text-primary">
                            3.1 Details of Outward Supplies (Liability)
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Nature of Supply</th>
                                        <th class="text-right">Total Taxable Value</th>
                                        <th class="text-right">IGST</th>
                                        <th class="text-right">CGST</th>
                                        <th class="text-right">SGST</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>(a) Outward taxable supplies (other than zero/nil/exempt)</td>
                                        <td class="text-right font-weight-bold" id="gstr3bOutwardTaxable">₹0.00</td>
                                        <td class="text-right" id="gstr3bOutwardIgst">₹0.00</td>
                                        <td class="text-right" id="gstr3bOutwardCgst">₹0.00</td>
                                        <td class="text-right" id="gstr3bOutwardSgst">₹0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 4. Eligible ITC --}}
                    <div class="card shadow-none border mb-3">
                        <div class="card-header bg-white py-2 font-weight-bold text-success">
                            4. Eligible Input Tax Credit (ITC from Purchases)
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Details</th>
                                        <th class="text-right">Integrated Tax</th>
                                        <th class="text-right">Central Tax</th>
                                        <th class="text-right">State/UT Tax</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>(A) ITC Available (All other ITC)</td>
                                        <td class="text-right" id="gstr3bItcIgst">₹0.00</td>
                                        <td class="text-right" id="gstr3bItcCgst">₹0.00</td>
                                        <td class="text-right" id="gstr3bItcSgst">₹0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 6.1 Payment of Tax --}}
                    <div class="alert alert-danger py-2 px-3 mb-0 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>6.1 Net Tax Payable in Cash:</strong>
                            <span class="small d-block text-muted">Total Output Tax minus Eligible Input Tax Credit</span>
                        </div>
                        <h4 class="font-weight-bold text-danger mb-0" id="gstr3bNetPayable">₹0.00</h4>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <a href="{{ route('reports.gst-sales-summary') }}" class="btn btn-success btn-sm font-weight-bold">
                        <i class="fas fa-file-export mr-1"></i> Export 3B Worksheet
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 3: GSTR-9 Annual Return Sync Modal --}}
    <div class="modal fade" id="gstr9Modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-calendar-alt mr-2 text-pink" style="color: #e83e8c;"></i>GSTR-9 Annual Return Summary</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="text-center mb-3">
                        <span class="badge badge-success px-3 py-1 font-weight-bold" id="gstr9FyBadge">Financial Year: 2026-27</span>
                        <div class="small text-muted mt-1" id="gstr9Period">1 Apr 2026 - 31 Mar 2027</div>
                    </div>

                    <div class="p-3 bg-white border rounded mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Annual Turnover:</span>
                            <strong class="text-dark" id="gstr9AnnualTurnover">₹0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Taxable Turnover:</span>
                            <strong class="text-dark" id="gstr9TaxableTurnover">₹0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Tax Collected (Outward):</span>
                            <strong class="text-primary" id="gstr9TaxCollected">₹0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total ITC Availed (Purchases):</span>
                            <strong class="text-success" id="gstr9ItcClaimed">₹0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <strong class="text-danger">Net Annual Tax Liability:</strong>
                            <strong class="text-danger h5 mb-0" id="gstr9NetTax">₹0.00</strong>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-check-circle mr-1"></i> Data successfully compiled across all 12 accounting months.
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 4: GSTR-2A / 2B File Upload Modal --}}
    <div class="modal fade" id="uploadGstrModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <form method="POST" action="{{ route('tools.gst.gstr-2-upload') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" id="uploadGstrType" value="2a">
                    <div class="modal-header bg-warning text-dark py-3">
                        <h5 class="modal-title font-weight-bold" id="uploadGstrTitle"><i class="fas fa-upload mr-2"></i>Upload GSTR-2A</h5>
                        <button type="button" class="close text-dark" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body p-4 bg-light">
                        <p class="small text-muted mb-3" id="uploadGstrHelpText">
                            Upload the official JSON file downloaded from the Government GST portal (gst.gov.in) to automatically reconcile with your Purchase Invoices in UrbanPOS.
                        </p>
                        <div class="form-group">
                            <label class="font-weight-bold small">Select Portal JSON File (.json)</label>
                            <input type="file" name="gstr_file" accept=".json,.zip" class="form-control-file p-2 bg-white border rounded" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                            <i class="fas fa-check-double mr-1"></i> Reconcile Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL 5: Interactive Row Click Drawer / Modal (Matching video 6.mp4 04:26) --}}
    <div class="modal fade" id="billDetailModal" tabindex="-1" role="dialog" aria-labelledby="billDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-dark text-white py-3">
                    <div>
                        <h5 class="modal-title font-weight-bold" id="billDetailModalLabel">
                            <i class="fas fa-file-invoice mr-2 text-warning"></i>Invoice Breakdown &amp; GST Details
                        </h5>
                        <span class="small text-light" id="modalBillSubtitle">Bill # Loading...</span>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">
                    {{-- Customer & Summary Grid --}}
                    <div class="row mb-3">
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="p-2 bg-white rounded border">
                                <span class="text-muted small d-block font-weight-bold">CUSTOMER</span>
                                <strong class="text-dark" id="modalCustomerName">--</strong>
                                <span class="d-block small text-muted" id="modalCustomerGstin">--</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="p-2 bg-white rounded border">
                                <span class="text-muted small d-block font-weight-bold">BILL DATE &amp; STATUS</span>
                                <strong class="text-dark" id="modalBillDate">--</strong>
                                <div id="modalStatusBadge" class="mt-1"></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="p-2 bg-white rounded border">
                                <span class="text-muted small d-block font-weight-bold">TOTAL TAXABLE VALUE</span>
                                <strong class="text-dark h5 mb-0" id="modalTaxableVal">₹0.00</strong>
                                <span class="d-block small text-muted">GST: <span id="modalTotalGst">₹0.00</span></span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="p-2 bg-white rounded border">
                                <span class="text-muted small d-block font-weight-bold">GRAND INVOICE TOTAL</span>
                                <strong class="text-success h4 font-weight-bold mb-0" id="modalGrandTotal">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    {{-- IRN Banner if completed --}}
                    <div id="modalIrnBanner" class="alert alert-success d-none shadow-sm py-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <i class="fas fa-check-circle mr-1"></i>
                                <strong>Govt IRN:</strong> <code class="text-dark font-weight-bold" id="modalIrnHash"></code>
                            </div>
                            <div class="small">
                                <strong>Ack:</strong> <span id="modalAckNo"></span> | <strong>Date:</strong> <span id="modalAckDate"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Error Banner if failed --}}
                    <div id="modalErrorBanner" class="alert alert-danger d-none shadow-sm py-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Portal Error:</strong> <span id="modalErrorText"></span>
                    </div>

                    {{-- Items Breakdown Table --}}
                    <div class="card shadow-none border mb-0">
                        <div class="card-header bg-white py-2 font-weight-bold">
                            <i class="fas fa-boxes mr-1 text-primary"></i> Line Items &amp; HSN Tax Split
                        </div>
                        <div class="card-body p-0 table-responsive" style="max-height: 280px;">
                            <table class="table table-sm table-striped table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Item Name / Description</th>
                                        <th>HSN Code</th>
                                        <th class="text-right">Qty</th>
                                        <th class="text-center">Unit</th>
                                        <th class="text-right">Sell Price</th>
                                        <th class="text-right">Taxable (₹)</th>
                                        <th class="text-center">GST %</th>
                                        <th class="text-right">CGST (₹)</th>
                                        <th class="text-right">SGST (₹)</th>
                                        <th class="text-right">IGST (₹)</th>
                                        <th class="text-right">Total (₹)</th>
                                    </tr>
                                </thead>
                                <tbody id="modalItemsTbody">
                                    {{-- Dynamically populated via AJAX --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <div id="modalActionButtons">
                        {{-- Quick single generate or view print --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 6: GST & Auto-Upload Settings Modal --}}
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content shadow-lg">
                <form method="POST" action="{{ route('tools.einvoice.settings') }}">
                    @csrf
                    <div class="modal-header bg-primary text-white py-3">
                        <h5 class="modal-title font-weight-bold" id="settingsModalLabel">
                            <i class="fas fa-sliders-h mr-2"></i>GST E-Invoice &amp; Auto-Upload Configuration
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Automated Behavior:</strong> Whenever a cashier saves a sales bill with total &gt;= threshold (default ₹50,000) or customer has a GSTIN, UrbanPOS will automatically contact Government IRP/GSP and fetch the IRN and Signed QR code in the background.
                        </div>

                        <div class="card card-outline card-warning p-3 mb-3">
                            <h6 class="font-weight-bold text-dark mb-2">Automation Rules</h6>
                            <div class="form-group custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="autoUploadSwitch" name="auto_upload_enabled" value="1" {{ $settings->auto_upload_enabled ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold" for="autoUploadSwitch">
                                    Enable Automatic Background Upload to Govt Portal
                                </label>
                            </div>
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">Automatic Upload Threshold (INR)</label>
                                <div class="input-group input-group-sm" style="max-width: 250px;">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₹</span>
                                    </div>
                                    <input type="number" step="100" min="0" name="auto_upload_threshold" value="{{ $settings->auto_upload_threshold }}" class="form-control font-weight-bold">
                                </div>
                                <small class="text-muted">Bills with total equal or greater than this will upload automatically upon billing.</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">Company GSTIN</label>
                                <input type="text" name="gstin" value="{{ $settings->gstin }}" class="form-control form-control-sm font-weight-bold text-uppercase" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">GSP Provider Integration</label>
                                <select name="gsp_provider" class="form-control form-control-sm font-weight-bold">
                                    <option value="mock" {{ $settings->gsp_provider === 'mock' ? 'selected' : '' }}>Built-in Sandbox / Mock Generator (Pre-production)</option>
                                    <option value="sandbox" {{ $settings->gsp_provider === 'sandbox' ? 'selected' : '' }}>Sandbox GSP (Official NIC Sandbox)</option>
                                    <option value="cleartax" {{ $settings->gsp_provider === 'cleartax' ? 'selected' : '' }}>ClearTax GSP API</option>
                                    <option value="masters_india" {{ $settings->gsp_provider === 'masters_india' ? 'selected' : '' }}>Masters India GSP API</option>
                                    <option value="nic_direct" {{ $settings->gsp_provider === 'nic_direct' ? 'selected' : '' }}>NIC Direct Government IRP</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">GSP Client ID</label>
                                <input type="text" name="client_id" value="{{ $settings->client_id }}" placeholder="Leave blank for test mode" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">GSP Client Secret</label>
                                <input type="password" name="client_secret" value="{{ $settings->client_secret }}" placeholder="••••••••" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">Govt Portal API Username</label>
                                <input type="text" name="username" value="{{ $settings->username }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">Govt Portal API Password</label>
                                <input type="password" name="password" value="{{ $settings->password }}" placeholder="••••••••" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="sandboxCheck" name="is_sandbox" value="1" {{ $settings->is_sandbox ? 'checked' : '' }}>
                            <label class="custom-control-label small font-weight-bold text-muted" for="sandboxCheck">
                                Use Sandbox / Testing Environment (Recommended until production keys are verified)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                            <i class="fas fa-save mr-1"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
$(document).ready(function() {
    // 1. Multi-select checkboxes & selection counter
    function updateSelectionCount() {
        const selected = $('.bill-checkbox:checked').length;
        $('#selectionCountText').text(selected + ' Invoices Selected');
        if (selected > 0) {
            $('#btnExecuteBulk').prop('disabled', false);
        } else {
            $('#btnExecuteBulk').prop('disabled', true);
        }
    }

    $('#selectAllCheckbox').on('change', function() {
        const checked = $(this).is(':checked');
        $('.bill-checkbox').prop('checked', checked);
        updateSelectionCount();
    });

    $(document).on('change', '.bill-checkbox', function() {
        updateSelectionCount();
        const total = $('.bill-checkbox').length;
        const checked = $('.bill-checkbox:checked').length;
        $('#selectAllCheckbox').prop('checked', total > 0 && total === checked);
    });

    // 2. Action Choice (Generate IRN vs Export JSON)
    $('input[name="action_choice"]').on('change', function() {
        const choice = $(this).val();
        $('#bulkActionType').val(choice);
        if (choice === 'json') {
            $('#bulkActionForm').attr('action', '{{ route("tools.einvoice.export-json") }}');
        } else {
            $('#bulkActionForm').attr('action', '{{ route("tools.einvoice.generate-irn") }}');
        }
    });

    $('#btnExecuteBulk').on('click', function() {
        const choice = $('input[name="action_choice"]:checked').val();
        const count = $('.bill-checkbox:checked').length;
        if (choice === 'generate') {
            if (!confirm('Are you sure you want to upload ' + count + ' invoice(s) to the Government E-Invoice portal?')) {
                return;
            }
        }
        $('#bulkActionForm').submit();
    });

    // 3. Row Click Drawer / Modal: Fetch item details via AJAX
    $('.bill-row').on('click', function(e) {
        const billId = $(this).data('id');
        openBillDetailsModal(billId);
    });
});

// Single Quick Upload Function
function quickUpload(billId) {
    if (!confirm('Upload this invoice to Government IRP portal now?')) {
        return;
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("tools.einvoice.generate-irn") }}';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'bill_ids[]';
    input.value = billId;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
}

// Fetch details for Modal Breakdown
function openBillDetailsModal(billId) {
    const url = '{{ url("tools/einvoice/details") }}/' + billId;
    
    $('#modalBillSubtitle').text('Loading details for Bill #' + billId + '...');
    $('#modalItemsTbody').html('<tr><td colspan="12" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Fetching bill details...</td></tr>');
    $('#modalIrnBanner').addClass('d-none');
    $('#modalErrorBanner').addClass('d-none');
    $('#billDetailModal').modal('show');

    $.getJSON(url, function(data) {
        $('#modalBillSubtitle').text('Bill Reference: ' + data.bill_number);
        $('#modalCustomerName').text(data.customer_name);
        $('#modalCustomerGstin').text('GSTIN: ' + data.customer_gstin);
        $('#modalBillDate').text(data.bill_date);
        $('#modalTaxableVal').text('₹' + Number(data.total_taxable).toFixed(2));
        $('#modalTotalGst').text('₹' + Number(data.total_gst).toFixed(2));
        $('#modalGrandTotal').text('₹' + Number(data.total).toFixed(2));

        let badgeHtml = '';
        if (data.einvoice_status === 'Completed' || data.irn) {
            badgeHtml = '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Completed (IRN Generated)</span>';
            $('#modalIrnHash').text(data.irn);
            $('#modalAckNo').text(data.ack_no || 'N/A');
            $('#modalAckDate').text(data.ack_date || 'N/A');
            $('#modalIrnBanner').removeClass('d-none');
        } else if (data.einvoice_status === 'Failed') {
            badgeHtml = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Failed</span>';
            $('#modalErrorText').text(data.einvoice_error || 'Portal rejected invoice schema');
            $('#modalErrorBanner').removeClass('d-none');
        } else {
            badgeHtml = '<span class="badge badge-warning"><i class="fas fa-clock mr-1"></i>Pending Upload</span>';
        }
        $('#modalStatusBadge').html(badgeHtml);

        let rows = '';
        if (data.items && data.items.length > 0) {
            data.items.forEach(function(item, index) {
                rows += '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td class="font-weight-bold">' + item.name + '</td>' +
                    '<td><code>' + item.hsn_code + '</code></td>' +
                    '<td class="text-right">' + item.qty + '</td>' +
                    '<td class="text-center">' + item.uom + '</td>' +
                    '<td class="text-right">₹' + Number(item.sell_price).toFixed(2) + '</td>' +
                    '<td class="text-right font-weight-bold">₹' + Number(item.taxable).toFixed(2) + '</td>' +
                    '<td class="text-center">' + item.gst_percent + '%</td>' +
                    '<td class="text-right">₹' + Number(item.cgst).toFixed(2) + '</td>' +
                    '<td class="text-right">₹' + Number(item.sgst).toFixed(2) + '</td>' +
                    '<td class="text-right">₹' + Number(item.igst).toFixed(2) + '</td>' +
                    '<td class="text-right font-weight-bold text-success">₹' + Number(item.total).toFixed(2) + '</td>' +
                '</tr>';
            });
        } else {
            rows = '<tr><td colspan="12" class="text-center text-muted">No line items found.</td></tr>';
        }
        $('#modalItemsTbody').html(rows);

        let actionBtnHtml = '';
        if (!data.irn) {
            actionBtnHtml += '<button type="button" class="btn btn-primary btn-sm font-weight-bold mr-2" onclick="quickUpload(' + data.id + ')"><i class="fas fa-cloud-upload-alt mr-1"></i> Generate IRN Now</button>';
        }
        actionBtnHtml += '<a href="{{ url("sales/sales-bills") }}/' + data.id + '" class="btn btn-outline-info btn-sm mr-2" target="_blank"><i class="fas fa-eye mr-1"></i> View Full Bill</a>';
        actionBtnHtml += '<a href="{{ url("sales/sales-bills") }}/' + data.id + '/receipt" class="btn btn-outline-dark btn-sm" target="_blank"><i class="fas fa-print mr-1"></i> Thermal Receipt</a>';
        $('#modalActionButtons').html(actionBtnHtml);
    }).fail(function() {
        $('#modalItemsTbody').html('<tr><td colspan="12" class="text-center text-danger py-3"><i class="fas fa-times-circle mr-1"></i> Failed to load bill details.</td></tr>');
    });
}

// 4. Open GSTR-1 Breakdown Modal
function openGstr1Modal() {
    $('#gstr1Modal').modal('show');
    const from = $('input[name="from_date"]').val();
    const to = $('input[name="to_date"]').val();
    $('#gstr1PeriodText').text(from + ' to ' + to);

    $.getJSON('{{ route("tools.gst.gstr-1") }}', { from_date: from, to_date: to }, function(data) {
        $('#gstr1TotalTurnover').text('₹' + Number(data.total_turnover).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2bCount').text(data.b2b.count);
        $('#gstr1B2bTaxable').text('₹' + Number(data.b2b.taxable).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2bTax').text('₹' + Number(data.b2b.tax).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2bTotal').text('₹' + Number(data.b2b.total).toLocaleString('en-IN', {minimumFractionDigits: 2}));

        $('#gstr1B2csCount').text(data.b2cs.count);
        $('#gstr1B2csTaxable').text('₹' + Number(data.b2cs.taxable).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2csTax').text('₹' + Number(data.b2cs.tax).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2csTotal').text('₹' + Number(data.b2cs.total).toLocaleString('en-IN', {minimumFractionDigits: 2}));

        $('#gstr1B2clCount').text(data.b2cl.count);
        $('#gstr1B2clTaxable').text('₹' + Number(data.b2cl.taxable).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2clTax').text('₹' + Number(data.b2cl.tax).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr1B2clTotal').text('₹' + Number(data.b2cl.total).toLocaleString('en-IN', {minimumFractionDigits: 2}));
    });
}

// 5. Open GSTR-3B Computation Modal
function openGstr3bModal() {
    $('#gstr3bModal').modal('show');
    const from = $('input[name="from_date"]').val();
    const to = $('input[name="to_date"]').val();

    $.getJSON('{{ route("tools.gst.gstr-3b") }}', { from_date: from, to_date: to }, function(data) {
        $('#gstr3bOutwardTaxable').text('₹' + Number(data.table_3_1.taxable).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr3bOutwardIgst').text('₹' + Number(data.table_3_1.igst).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr3bOutwardCgst').text('₹' + Number(data.table_3_1.cgst).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr3bOutwardSgst').text('₹' + Number(data.table_3_1.sgst).toLocaleString('en-IN', {minimumFractionDigits: 2}));

        $('#gstr3bItcIgst').text('₹' + Number(data.table_4.igst).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr3bItcCgst').text('₹' + Number(data.table_4.cgst).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr3bItcSgst').text('₹' + Number(data.table_4.sgst).toLocaleString('en-IN', {minimumFractionDigits: 2}));

        $('#gstr3bNetPayable').text('₹' + Number(data.table_6_1.tax_payable).toLocaleString('en-IN', {minimumFractionDigits: 2}));
    });
}

// 6. GSTR-9 Annual Return Sync
function syncGstr9() {
    $('#gstr9SyncIcon').addClass('fa-spin');
    $('#gstr9StatusText').html('<i class="fas fa-spinner fa-spin mr-1"></i> Syncing Annual Ledger...');

    $.post('{{ route("tools.gst.gstr-9-sync") }}', { _token: '{{ csrf_token() }}' }, function(data) {
        $('#gstr9SyncIcon').removeClass('fa-spin');
        $('#gstr9StatusText').html('<i class="fas fa-check-circle text-success mr-1"></i> ' + data.financial_year + ' Synced');
        
        $('#gstr9FyBadge').text('Financial Year: ' + data.financial_year);
        $('#gstr9Period').text(data.period);
        $('#gstr9AnnualTurnover').text('₹' + Number(data.annual_turnover).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr9TaxableTurnover').text('₹' + Number(data.annual_taxable_turnover).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr9TaxCollected').text('₹' + Number(data.annual_tax_collected).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr9ItcClaimed').text('₹' + Number(data.annual_itc_claimed).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#gstr9NetTax').text('₹' + Number(data.annual_net_tax_paid).toLocaleString('en-IN', {minimumFractionDigits: 2}));

        $('#gstr9Modal').modal('show');
    }).fail(function() {
        $('#gstr9SyncIcon').removeClass('fa-spin');
        $('#gstr9StatusText').html('<i class="fas fa-exclamation-triangle text-danger mr-1"></i> Sync Failed');
        alert('Failed to sync GSTR-9. Please check logs.');
    });
}

// 7. Open GSTR-2A / 2B Upload Modal
function openUploadModal(type) {
    const upper = type.toUpperCase();
    $('#uploadGstrType').val(type);
    $('#uploadGstrTitle').html('<i class="fas fa-upload mr-2"></i>Upload GSTR-' + upper);
    $('#uploadGstrHelpText').text('Upload the official GSTR-' + upper + ' JSON file from the Government GST portal to reconcile against purchase invoices.');
    $('#uploadGstrModal').modal('show');
}
</script>
@stop
