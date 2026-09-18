@extends('adminlte::page')

@section('title', 'GSTR-1 Outward Supplies')

@section('content_header')
<div class="d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h1 class="font-weight-bold text-dark mb-0">
            <i class="fas fa-file-invoice-dollar text-primary mr-2"></i> GSTR-1: Outward Supplies
        </h1>
        <small class="text-muted">
            GST Statutory Return &bull; <strong>{{ $companyName }}</strong> &bull; GSTIN: <code>{{ $gstin }}</code>
        </small>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <a href="{{ route('tools.integrations-gst', ['view' => 'returns']) }}" class="btn btn-outline-secondary btn-sm mr-2 shadow-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> GST Dashboard
        </a>
        <a href="{{ route('reports.gst-sales-summary') }}" class="btn btn-outline-success btn-sm shadow-sm font-weight-bold">
            <i class="fas fa-file-excel mr-1"></i> Full GST Register
        </a>
    </div>
</div>
@stop

@section('content')
<div class="gstr1-native-wrapper pb-4">
    
    {{-- 1. UrbanPOS Native Filter Toolbar Card --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <form method="GET" action="{{ route('tools.gst.gstr-1.page') }}" id="periodForm" class="form-inline mb-2 mb-md-0">
                    <label class="font-weight-bold mr-2 text-dark small">
                        <i class="far fa-calendar-alt text-primary mr-1"></i> Return Period:
                    </label>
                    <select name="period" class="form-control form-control-sm font-weight-bold mr-3" onchange="document.getElementById('periodForm').submit();" style="min-width: 170px;">
                        <option value="Aug 2026 - 2027" {{ $selectedPeriod == 'Aug 2026 - 2027' ? 'selected' : '' }}>Aug 2026 - 2027</option>
                        <option value="Sep 2026 - 2027" {{ $selectedPeriod == 'Sep 2026 - 2027' ? 'selected' : '' }}>Sep 2026 - 2027</option>
                        <option value="Jul 2026 - 2027" {{ $selectedPeriod == 'Jul 2026 - 2027' ? 'selected' : '' }}>Jul 2026 - 2027</option>
                        <option value="FY 2026 - 2027" {{ $selectedPeriod == 'FY 2026 - 2027' ? 'selected' : '' }}>FY 2026 - 2027</option>
                    </select>

                    <span class="badge badge-light border px-2 py-1 mr-2 text-dark">
                        <i class="fas fa-store mr-1 text-secondary"></i> {{ $companyName }}
                    </span>
                    <span class="badge badge-primary px-2 py-1 font-weight-bold">
                        GSTIN: {{ $gstin }}
                    </span>
                </form>

                <div class="d-flex align-items-center small text-muted">
                    <span class="mr-3">
                        <i class="fas fa-sync text-success mr-1"></i> Last Sync: <strong>{{ now()->format('d/m/Y h:i A') }}</strong>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-primary shadow-sm px-2 py-1" onclick="window.location.reload();" title="Refresh Live Calculations">
                        <i class="fas fa-redo-alt mr-1"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. 12 Statutory GSTR-1 Cards Grid --}}
    <div class="row">
            {{-- ROW 1 --}}
            {{-- Card 1: HSN B2B Summary --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'b2b-hsn']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>HSN B2B Summary</span>
                        <span></span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($hsnB2bTaxable, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Taxable Value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($hsnB2bTax, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: HSN B2C Summary --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'b2c-hsn']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>HSN B2C Summary</span>
                        <span></span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($hsnB2cTaxable, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Taxable Value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($hsnB2cTax, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: B2B Outward Supplies --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'b2b']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>B2B Outward Supplies</span>
                        <span class="small font-weight-bold">{{ $b2bCount }} invoices</span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($b2bTaxable, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Taxable value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($b2bTax, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW 2 --}}
            {{-- Card 4: B2CL Outward Supplies --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'b2cl']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>B2CL Outward Supplies</span>
                        <span class="small font-weight-bold">{{ $b2clCount }} Invoices</span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $b2clTaxable > 0 ? '₹' . number_format($b2clTaxable, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Taxable value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $b2clTax > 0 ? '₹' . number_format($b2clTax, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 5: Exported Supplies --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'exp']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Exported Supplies</span>
                        <span class="small font-weight-bold">{{ $exportCount }} Invoices</span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $exportTaxable > 0 ? '₹' . number_format($exportTaxable, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Taxable value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $exportTax > 0 ? '₹' . number_format($exportTax, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 6: B2CS Outward Supplies --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'b2cs']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>B2CS Outward Supplies</span>
                        <span></span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($b2csTaxable, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Taxable value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($b2csTax, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW 3 --}}
            {{-- Card 7: Credit/Debit notes (Reg) --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'cdnr']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Credit/Debit notes (Reg)</span>
                        <span class="small font-weight-bold">{{ $cdnrCount }} notes</span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $cdnrValue > 0 ? '₹' . number_format($cdnrValue, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Return value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $cdnrTax > 0 ? '₹' . number_format($cdnrTax, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax amount</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 8: Credit/Debit notes (UnReg) --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'cdnur']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Credit/Debit notes (UnReg)</span>
                        <span class="small font-weight-bold">{{ $cdnurCount }} notes</span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $cdnurValue > 0 ? '₹' . number_format($cdnurValue, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Return value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $cdnurTax > 0 ? '₹' . number_format($cdnurTax, 2) : '-' }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax amount</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 9: Nil Rated Supplies --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'nil']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Nil Rated Supplies</span>
                        <span></span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($nilRatedAmount, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Nil Rated Amount</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">₹{{ number_format($exemptedAmount, 2) }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Exempted Amount</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW 4 --}}
            {{-- Card 10: Advance Received --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'adv-rec']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Advance Received</span>
                        <span></span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">-</div>
                                <div class="text-muted small" style="font-size: 11px;">Received Taxable Value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">-</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax Collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 11: Advance Adjusted --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'adv-adj']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Advance Adjusted</span>
                        <span></span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">-</div>
                                <div class="text-muted small" style="font-size: 11px;">Adjusted Taxable Value</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">-</div>
                                <div class="text-muted small" style="font-size: 11px;">Tax Collected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 12: Document Issued --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card shadow-sm border h-100 gstr1-card" onclick="window.location.href='{{ route('tools.gst.gstr-1.section', ['section' => 'doc-issued']) }}';">
                    <div class="card-header py-2 px-3 font-weight-bold text-dark d-flex justify-content-between align-items-center" style="background-color: #ffc107; font-size: 13px;">
                        <span>Document Issued</span>
                        <span class="small font-weight-bold">Total Invoices {{ $docIssuedTotal }}</span>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row text-center align-items-center" style="min-height: 70px;">
                            <div class="col-6 border-right">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $docIssuedTotal }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Net Issued</div>
                            </div>
                            <div class="col-6">
                                <div class="font-weight-bold text-dark h5 mb-1">{{ $cancelledCount }}</div>
                                <div class="text-muted small" style="font-size: 11px;">Cancelled Invoice</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- Detail Modal when clicking any card --}}
<div class="modal fade" id="gstr1CardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header py-2 text-dark font-weight-bold" style="background-color: #ffc107;">
                <h5 class="modal-title font-weight-bold" id="cardModalTitle">Section Details</h5>
                <button type="button" class="close text-dark" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <div class="d-flex justify-content-between mb-2 small text-muted">
                    <span>Tax Period: <strong>{{ $selectedPeriod }}</strong></span>
                    <span>Company: <strong>{{ $companyName }}</strong></span>
                </div>
                <div class="card shadow-none border mb-0">
                    <div class="card-body p-0 table-responsive" style="max-height: 350px;">
                        <table class="table table-sm table-striped table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Ref / Invoice No</th>
                                    <th>Customer Details</th>
                                    <th>GSTIN</th>
                                    <th class="text-right">Taxable Value</th>
                                    <th class="text-right">Tax Collected</th>
                                    <th class="text-right">Invoice Total</th>
                                </tr>
                            </thead>
                            <tbody id="cardModalTbody">
                                {{-- Dynamically populated --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <a href="{{ route('reports.gst-sales-summary') }}" class="btn btn-primary btn-sm font-weight-bold">
                    <i class="fas fa-external-link-alt mr-1"></i> Full GST Sales Register
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.gstr1-card {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    border-radius: 4px;
    overflow: hidden;
}
.gstr1-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.12) !important;
}
</style>

<script>
function openBreakdownModal(sectionTitle) {
    $('#cardModalTitle').text(sectionTitle + ' Details');
    $('#cardModalTbody').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Loading invoice records...</td></tr>');
    $('#gstr1CardModal').modal('show');

    // Fetch live data or populate summary rows
    $.getJSON('{{ route("tools.gst.gstr-1") }}', { from_date: '{{ $fromDate }}', to_date: '{{ $toDate }}' }, function(data) {
        let rows = '';
        if (sectionTitle.includes('B2B') && data.b2b.count > 0) {
            rows += '<tr>' +
                '<td>1</td>' +
                '<td class="font-weight-bold text-primary">B2B Invoices (' + data.b2b.count + ' Items)</td>' +
                '<td>Registered B2B Business Customers</td>' +
                '<td><code>' + '{{ $gstin }}' + '</code></td>' +
                '<td class="text-right font-weight-bold">₹' + Number(data.b2b.taxable).toFixed(2) + '</td>' +
                '<td class="text-right text-primary font-weight-bold">₹' + Number(data.b2b.tax).toFixed(2) + '</td>' +
                '<td class="text-right font-weight-bold text-success">₹' + Number(data.b2b.total).toFixed(2) + '</td>' +
            '</tr>';
        } else if (sectionTitle.includes('B2C') || sectionTitle.includes('HSN')) {
            rows += '<tr>' +
                '<td>1</td>' +
                '<td class="font-weight-bold text-primary">Retail B2C Supplies (' + data.b2cs.count + ' Invoices)</td>' +
                '<td>Walk-in / Retail Store Customers</td>' +
                '<td><span class="text-muted small">URP (B2C)</span></td>' +
                '<td class="text-right font-weight-bold">₹' + Number(data.b2cs.taxable).toFixed(2) + '</td>' +
                '<td class="text-right text-primary font-weight-bold">₹' + Number(data.b2cs.tax).toFixed(2) + '</td>' +
                '<td class="text-right font-weight-bold text-success">₹' + Number(data.b2cs.total).toFixed(2) + '</td>' +
            '</tr>';
        } else {
            rows += '<tr>' +
                '<td>1</td>' +
                '<td class="font-weight-bold text-dark">' + sectionTitle + '</td>' +
                '<td>Consolidated Period Records</td>' +
                '<td>-</td>' +
                '<td class="text-right font-weight-bold">₹' + Number(data.total_turnover || 0).toFixed(2) + '</td>' +
                '<td class="text-right text-primary font-weight-bold">₹0.00</td>' +
                '<td class="text-right font-weight-bold text-success">₹' + Number(data.total_turnover || 0).toFixed(2) + '</td>' +
            '</tr>';
        }
        $('#cardModalTbody').html(rows);
    });
}
</script>
@stop
