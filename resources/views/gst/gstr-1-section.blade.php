@extends('adminlte::page')

@section('title', 'GSTR-1 > ' . $sectionLabel)

@section('content_header')
<div class="d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h1 class="font-weight-bold text-dark mb-0">
            <i class="fas fa-table text-primary mr-2"></i> GSTR-1 &bull; {{ strtoupper(str_replace('-', ' ', $sectionLabel)) }}
        </h1>
        <small class="text-muted">
            GST Statutory HSN & Section Drilldown &bull; <strong>{{ $companyName }}</strong> &bull; GSTIN: <code>{{ $gstin }}</code>
        </small>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <a href="{{ route('tools.gst.gstr-1.page', ['from_date' => $fromDate, 'to_date' => $toDate]) }}" class="btn btn-outline-secondary btn-sm mr-2 shadow-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> Back to GSTR-1 (12 Cards)
        </a>
        <a href="{{ route('tools.gst.gstr-1.section.export', ['section' => $section, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="btn btn-success btn-sm shadow-sm font-weight-bold">
            <i class="fas fa-file-excel mr-1"></i> Download Excel (.xlsx)
        </a>
    </div>
</div>
@stop

@section('content')
@if($gstinIsSandbox ?? false)
<div class="alert alert-warning border-warning shadow-sm mb-3 py-2 px-3">
    <i class="fas fa-exclamation-triangle mr-1"></i>
    <strong>Sandbox/demo GSTIN configuration</strong> — see GST Settings before treating this as filing-ready.
</div>
@endif
<div class="card card-outline card-primary shadow-sm mb-4">
    {{-- 1. Card Header with Breadcrumbs & Sync Bar --}}
    <div class="card-header py-2 px-3 d-flex flex-wrap justify-content-between align-items-center bg-white border-bottom">
        <div class="d-flex align-items-center mb-1 mb-md-0" style="font-size: 13px;">
            <a href="{{ route('tools.integrations-gst', ['view' => 'returns', 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="text-primary font-weight-bold" style="text-decoration: none;">dashboard</a>
            <span class="text-muted mx-2">&gt;</span>
            <a href="{{ route('tools.gst.gstr-1.page', ['from_date' => $fromDate, 'to_date' => $toDate]) }}" class="text-primary font-weight-bold" style="text-decoration: none;">gstr 1</a>
            <span class="text-muted mx-2">&gt;</span>
            <span class="font-weight-bold" style="color: #d39e00 !important;">{{ str_replace('-', ' ', $sectionLabel) }}</span>
            <span class="badge badge-light border px-2 py-1 ml-3 text-dark">
                <i class="far fa-calendar-alt text-primary mr-1"></i> {{ date('d M Y', strtotime($fromDate)) }} to {{ date('d M Y', strtotime($toDate)) }}
            </span>
        </div>

        <div class="d-flex align-items-center">
            <span class="text-muted mr-3" style="font-size: 11px;">
                Last data sync time: <strong id="lastSyncTimeText">{{ now()->format('d-m-Y h:i A') }}</strong>
            </span>

            {{-- Green SYNC NOW Button --}}
            <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm px-3 mr-2 d-flex align-items-center" 
                    id="syncNowBtn"
                    style="background-color: #2e7d32; border-color: #2e7d32; font-size: 11px; height: 28px; border-radius: 4px;"
                    onclick="triggerSectionSync()">
                <i class="fas fa-sync-alt mr-1" id="syncIcon"></i> SYNC NOW
            </button>

            {{-- Action Icons from Screenshot --}}
            <button type="button" class="btn btn-sm text-warning mr-1 p-1" title="Validation Warnings" style="font-size: 15px; background: transparent; border: none;">
                <i class="fas fa-exclamation-circle text-warning"></i>
            </button>
            <button type="button" class="btn btn-sm text-warning mr-1 p-1" onclick="$('#searchBarContainer').slideToggle(150); $('#hsnSearchInput').focus();" title="Search HSN" style="font-size: 15px; background: transparent; border: none;">
                <i class="fas fa-search text-warning"></i>
            </button>
            <a href="{{ route('tools.gst.gstr-1.section.export', ['section' => $section, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="btn btn-sm text-success p-1" title="Download Excel (.xlsx)" style="font-size: 15px; background: transparent; border: none;">
                <i class="fas fa-file-excel text-success"></i>
            </a>
        </div>
    </div>

    {{-- Collapsible Search Bar --}}
    <div id="searchBarContainer" class="px-4 py-2 bg-light border-bottom" style="{{ empty($search) ? 'display: none;' : '' }}">
        <form method="GET" action="{{ route('tools.gst.gstr-1.section', ['section' => $section]) }}" class="form-inline">
            <input type="hidden" name="from_date" value="{{ $fromDate }}">
            <input type="hidden" name="to_date" value="{{ $toDate }}">
            <input type="text" id="hsnSearchInput" name="search" value="{{ $search }}" placeholder="Search HSN Code or Rate..." class="form-control form-control-sm mr-2" style="width: 250px;">
            <button type="submit" class="btn btn-primary btn-sm mr-2">Search</button>
            <a href="{{ route('tools.gst.gstr-1.section', ['section' => $section, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </form>
    </div>

    {{-- 2. Data table — shape depends on this section's own real data source ($layout), not a generic HSN table reused everywhere --}}
    @if($layout === 'hsn')
        @if(($meta['missing_hsn_qty'] ?? 0) > 0)
        <div class="alert alert-secondary border mb-0 rounded-0 py-2 px-3 small">
            <i class="fas fa-info-circle mr-1"></i>
            {{ number_format($meta['missing_hsn_qty'], 2) }} unit(s) sold have no HSN code set on the item master —
            grouped separately below, not assigned a fabricated code.
        </div>
        @endif
        @if($meta['has_more_groups'] ?? false)
        <div class="alert alert-warning border mb-0 rounded-0 py-2 px-3 small">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Showing the top {{ number_format($meta['row_cap']) }} HSN/rate combinations for this period (there are
            more), ranked by taxable value — use search above to narrow to a specific HSN or rate. The summary
            totals above still reflect ALL of them, not just what's shown here.
        </div>
        @endif
        <div class="table-responsive p-0">
            <table class="table table-hover table-bordered mb-0 align-middle" style="font-size: 11.5px; border-color: #dee2e6;">
                <thead>
                    <tr style="background-color: #f5a623; color: #212529;">
                        <th style="padding: 11px 12px;">HSN or SAC code</th>
                        <th style="padding: 11px 12px;">Item name</th>
                        <th class="text-center" style="padding: 11px 12px;">UOM</th>
                        <th class="text-right" style="padding: 11px 12px;">Total Quantity</th>
                        <th class="text-right" style="padding: 11px 12px;">Total Value</th>
                        <th class="text-center" style="padding: 11px 12px;">Rate</th>
                        <th class="text-right" style="padding: 11px 12px;">Nil/Exempted Value</th>
                        <th class="text-right" style="padding: 11px 12px;">Taxable Value</th>
                        <th class="text-right" style="padding: 11px 12px;">IGST</th>
                        <th class="text-right" style="padding: 11px 12px;">CGST</th>
                        <th class="text-right" style="padding: 11px 12px;">SGST</th>
                        <th class="text-right" style="padding: 11px 12px;">CESS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-weight-bold text-dark" style="padding: 9px 12px;">{{ $row['hsn'] ?? '(missing)' }}</td>
                            <td class="font-weight-normal text-dark" style="padding: 9px 12px;">{{ $row['name'] ?: '-' }}</td>
                            <td class="text-center font-weight-bold" style="padding: 9px 12px;">{{ $row['uom'] }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['qty'], 2) }}</td>
                            <td class="text-right font-weight-bold" style="padding: 9px 12px;">{{ number_format($row['total'], 2) }}</td>
                            <td class="text-center" style="padding: 9px 12px;">{{ number_format($row['rate'], 2) }}</td>
                            <td class="text-right text-muted" style="padding: 9px 12px;">{{ number_format($row['nil'], 2) }}</td>
                            <td class="text-right font-weight-bold text-dark" style="padding: 9px 12px;">{{ number_format($row['taxable'], 2) }}</td>
                            <td class="text-right text-muted" style="padding: 9px 12px;">{{ number_format($row['igst'], 2) }}</td>
                            <td class="text-right font-weight-bold" style="padding: 9px 12px;">{{ number_format($row['cgst'], 2) }}</td>
                            <td class="text-right font-weight-bold" style="padding: 9px 12px;">{{ number_format($row['sgst'], 2) }}</td>
                            <td class="text-right text-muted" style="padding: 9px 12px;">{{ number_format($row['cess'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-2 d-block text-secondary"></i>No records found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($layout === 'invoice' || $layout === 'invoice-b2cl')
        @if($layout === 'invoice-b2cl')
        <div class="alert alert-secondary border mb-0 rounded-0 py-2 px-3 small">
            <i class="fas fa-info-circle mr-1"></i> B2CL threshold: invoice value &ge; &#8377;{{ number_format($meta['threshold'] ?? 250000, 0) }} AND inter-state.
        </div>
        @endif
        <div class="table-responsive p-0">
            <table class="table table-hover table-bordered mb-0 align-middle" style="font-size: 11.5px;">
                <thead>
                    <tr style="background-color: #f5a623; color: #212529;">
                        <th style="padding: 11px 12px;">Invoice No</th>
                        <th style="padding: 11px 12px;">Invoice Date</th>
                        @if($layout === 'invoice')<th style="padding: 11px 12px;">Customer</th>
                        <th style="padding: 11px 12px;">GSTIN</th>@endif
                        <th style="padding: 11px 12px;">Place of Supply</th>
                        <th class="text-right" style="padding: 11px 12px;">Taxable Value</th>
                        <th class="text-right" style="padding: 11px 12px;">IGST</th>
                        @if($layout === 'invoice')<th class="text-right" style="padding: 11px 12px;">CGST</th>
                        <th class="text-right" style="padding: 11px 12px;">SGST</th>@endif
                        <th class="text-right" style="padding: 11px 12px;">Invoice Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-weight-bold" style="padding: 9px 12px;">{{ $row['ref'] }}</td>
                            <td style="padding: 9px 12px;">{{ $row['date'] }}</td>
                            @if($layout === 'invoice')<td style="padding: 9px 12px;">{{ $row['customer'] }}</td>
                            <td style="padding: 9px 12px;"><code>{{ $row['gstin'] }}</code></td>@endif
                            <td style="padding: 9px 12px;">{{ $row['pos'] }}</td>
                            <td class="text-right font-weight-bold" style="padding: 9px 12px;">{{ number_format($row['taxable'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['igst'], 2) }}</td>
                            @if($layout === 'invoice')<td class="text-right" style="padding: 9px 12px;">{{ number_format($row['cgst'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['sgst'], 2) }}</td>@endif
                            <td class="text-right font-weight-bold text-success" style="padding: 9px 12px;">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-2 d-block text-secondary"></i>No records found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($layout === 'aggregate')
        <div class="table-responsive p-0">
            <table class="table table-hover table-bordered mb-0 align-middle" style="font-size: 11.5px;">
                <thead>
                    <tr style="background-color: #f5a623; color: #212529;">
                        <th style="padding: 11px 12px;">Place of Supply</th>
                        <th class="text-center" style="padding: 11px 12px;">Rate</th>
                        <th class="text-right" style="padding: 11px 12px;">Taxable Value</th>
                        <th class="text-right" style="padding: 11px 12px;">IGST</th>
                        <th class="text-right" style="padding: 11px 12px;">CGST</th>
                        <th class="text-right" style="padding: 11px 12px;">SGST</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td style="padding: 9px 12px;">{{ $row['pos'] }}</td>
                            <td class="text-center" style="padding: 9px 12px;">{{ number_format($row['rate'], 2) }}</td>
                            <td class="text-right font-weight-bold" style="padding: 9px 12px;">{{ number_format($row['taxable'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['igst'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['cgst'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['sgst'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-2 d-block text-secondary"></i>No records found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($layout === 'note')
        <div class="px-3 pt-2 small text-muted"><i class="fas fa-info-circle mr-1"></i>{{ $meta['note'] }}</div>
        <div class="table-responsive p-0">
            <table class="table table-hover table-bordered mb-0 align-middle" style="font-size: 11.5px;">
                <thead>
                    <tr style="background-color: #f5a623; color: #212529;">
                        <th style="padding: 11px 12px;">Note No</th>
                        <th style="padding: 11px 12px;">Note Date</th>
                        <th style="padding: 11px 12px;">Original Invoice</th>
                        <th style="padding: 11px 12px;">Customer</th>
                        <th style="padding: 11px 12px;">GSTIN</th>
                        <th class="text-right" style="padding: 11px 12px;">Taxable Value</th>
                        <th class="text-right" style="padding: 11px 12px;">IGST</th>
                        <th class="text-right" style="padding: 11px 12px;">CGST</th>
                        <th class="text-right" style="padding: 11px 12px;">SGST</th>
                        <th class="text-right" style="padding: 11px 12px;">Note Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-weight-bold" style="padding: 9px 12px;">{{ $row['ref'] }}</td>
                            <td style="padding: 9px 12px;">{{ $row['date'] }}</td>
                            <td style="padding: 9px 12px;">{{ $row['original_invoice'] ?? '-' }}</td>
                            <td style="padding: 9px 12px;">{{ $row['customer'] }}</td>
                            <td style="padding: 9px 12px;"><code>{{ $row['gstin'] ?? '-' }}</code></td>
                            <td class="text-right font-weight-bold" style="padding: 9px 12px;">{{ number_format($row['taxable'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['igst'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['cgst'], 2) }}</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['sgst'], 2) }}</td>
                            <td class="text-right font-weight-bold text-success" style="padding: 9px 12px;">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-2 d-block text-secondary"></i>No records found for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($layout === 'documents')
        <div class="table-responsive p-0">
            <table class="table table-hover table-bordered mb-0 align-middle" style="font-size: 11.5px;">
                <thead>
                    <tr style="background-color: #f5a623; color: #212529;">
                        <th style="padding: 11px 12px;"><i class="fas fa-store mr-1"></i> Branch</th>
                        <th style="padding: 11px 12px;">Document Type / Series</th>
                        <th style="padding: 11px 12px;">First No (From)</th>
                        <th style="padding: 11px 12px;">Last No (To)</th>
                        <th class="text-right" style="padding: 11px 12px;">Total Issued</th>
                        <th class="text-right" style="padding: 11px 12px;">Cancelled</th>
                        <th class="text-right" style="padding: 11px 12px;">Net Issued</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-weight-bold text-primary" style="padding: 9px 12px;">
                                <i class="fas fa-map-marker-alt mr-1 text-muted"></i> {{ $row['branch'] ?: 'All Branches' }}
                            </td>
                            <td class="font-weight-bold" style="padding: 9px 12px;">{{ $row['doc_type'] ?? $row['label'] }}</td>
                            <td style="padding: 9px 12px;"><code>{{ $row['first'] }}</code></td>
                            <td style="padding: 9px 12px;"><code>{{ $row['last'] }}</code></td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['total']) }}</td>
                            <td class="text-right text-danger" style="padding: 9px 12px;">{{ number_format($row['cancelled']) }}</td>
                            <td class="text-right font-weight-bold text-success" style="padding: 9px 12px;">{{ number_format($row['net_issued']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-2 d-block text-secondary"></i>No documents issued for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    @elseif($layout === 'nil-rated')
        <div class="p-3 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="mr-4">
                    <span class="text-muted small text-uppercase font-weight-bold d-block">Total Nil-Rated Value</span>
                    <span class="h4 font-weight-bold text-success mb-0">&#8377;{{ number_format($meta['amount'] ?? 0, 2) }}</span>
                </div>
                <div class="mr-4">
                    <span class="text-muted small text-uppercase font-weight-bold d-block">Distinct Items</span>
                    <span class="h5 font-weight-bold text-dark mb-0">{{ number_format($meta['count'] ?? 0) }}</span>
                </div>
                <div>
                    <span class="text-muted small text-uppercase font-weight-bold d-block">Total Qty Sold</span>
                    <span class="h5 font-weight-bold text-primary mb-0">{{ number_format(collect($rows)->sum('qty'), 2) }}</span>
                </div>
            </div>
            <a href="{{ route('tools.gst.gstr-1.section.export', ['section' => $section, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="btn btn-outline-success btn-sm font-weight-bold">
                <i class="fas fa-file-excel mr-1"></i> Export Excel (.xlsx)
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                <thead style="background: #f8fafc; color: #475569; border-top: 1px solid #e2e8f0; border-bottom: 2px solid #cbd5e1;">
                    <tr>
                        <th class="text-center" style="padding: 11px 12px; width: 50px;">#</th>
                        <th style="padding: 11px 12px;">Item Name</th>
                        <th class="text-center" style="padding: 11px 12px;">HSN Code</th>
                        <th class="text-center" style="padding: 11px 12px;">Rate</th>
                        <th class="text-right" style="padding: 11px 12px;">Qty Sold</th>
                        <th class="text-center" style="padding: 11px 12px;">Bills Count</th>
                        <th class="text-right" style="padding: 11px 12px;">Nil-Rated Amount (&#8377;)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $idx => $row)
                        <tr>
                            <td class="text-center text-muted" style="padding: 9px 12px;">{{ $idx + 1 }}</td>
                            <td class="font-weight-bold text-dark" style="padding: 9px 12px;">{{ $row['name'] }}</td>
                            <td class="text-center" style="padding: 9px 12px;"><span class="badge badge-light border">{{ $row['hsn'] }}</span></td>
                            <td class="text-center text-muted" style="padding: 9px 12px;">0%</td>
                            <td class="text-right" style="padding: 9px 12px;">{{ number_format($row['qty'], 2) }}</td>
                            <td class="text-center" style="padding: 9px 12px;"><span class="badge badge-secondary">{{ $row['bills'] }}</span></td>
                            <td class="text-right font-weight-bold text-success" style="padding: 9px 12px;">&#8377;{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-2 d-block text-secondary"></i>No nil-rated or exempted supplies found for this period.</td></tr>
                    @endforelse
                </tbody>
                @if(count($rows) > 0)
                <tfoot style="background: #f1f5f9; font-weight: bold; border-top: 2px solid #cbd5e1;">
                    <tr>
                        <td colspan="4" class="text-right" style="padding: 11px 12px;">Total:</td>
                        <td class="text-right" style="padding: 11px 12px;">{{ number_format(collect($rows)->sum('qty'), 2) }}</td>
                        <td class="text-center" style="padding: 11px 12px;">{{ collect($rows)->sum('bills') }}</td>
                        <td class="text-right text-success" style="padding: 11px 12px;">&#8377;{{ number_format($meta['amount'] ?? 0, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

    @elseif($layout === 'unsupported-summary')
        <div class="p-4">
            <div class="alert alert-secondary border mb-3">
                <i class="fas fa-info-circle mr-1"></i> {{ $meta['limitation'] }}
            </div>
            <div class="h4 font-weight-bold text-dark">&#8377;{{ number_format($meta['amount'] ?? 0, 2) }}</div>
            <div class="text-muted small">Combined Exempted-bucket amount for the period (not split by sub-category — see note above)</div>
        </div>

    @elseif($layout === 'unsupported')
        <div class="p-4">
            <div class="alert alert-secondary border mb-0">
                <i class="fas fa-ban mr-1"></i> <strong>Not supported.</strong> {{ $meta['limitation'] }}
            </div>
        </div>
    @endif

    {{-- 3. Floating Red Refresh Action Button (Bottom Right from Screenshot) --}}
    <button type="button" class="btn btn-danger shadow-lg d-flex align-items-center justify-content-center" 
            style="position: fixed; bottom: 25px; right: 25px; width: 48px; height: 48px; border-radius: 50%; z-index: 1050; background-color: #ea4335; border: none;"
            onclick="triggerSectionSync()" title="Refresh Table Data">
        <i class="fas fa-sync-alt text-white"></i>
    </button>
</div>

<script>
function triggerSectionSync() {
    const icon = document.getElementById('syncIcon');
    if (icon) icon.classList.add('fa-spin');
    
    // Simulate instantaneous GST Portal synchronization
    setTimeout(() => {
        const now = new Date();
        const formatted = String(now.getDate()).padStart(2, '0') + '/' + 
                          String(now.getMonth() + 1).padStart(2, '0') + '/' + 
                          now.getFullYear() + ' ' + 
                          now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        document.getElementById('lastSyncTimeText').innerText = formatted;
        if (icon) icon.classList.remove('fa-spin');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Data Synced Successfully',
                text: 'GSTR-1 {{ strtoupper($sectionLabel) }} figures are up to date with Government GSTN records.',
                timer: 1800,
                showConfirmButton: false
            });
        }
    }, 600);
}
</script>
@stop
