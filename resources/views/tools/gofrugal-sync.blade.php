@extends('adminlte::page')

@section('title', 'GoFrugal TruePOS Data Sync Manager')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-sync-alt text-primary mr-2"></i>GoFrugal TruePOS Sync Manager</h1>
            <small class="text-muted">Automated Credentials-Based Data Synchronization with UrbanPOS</small>
        </div>
        <div>
            <span class="badge badge-success px-3 py-2"><i class="fas fa-link mr-1"></i>Portal: urbanpets.true-pos.com</span>
        </div>
    </div>
@stop

@section('content')
<div class="container-fluid">
    {{-- Status Overview & Date Filter --}}
    <div class="card card-outline card-primary shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('tools.gofrugal-sync') }}" class="row align-items-end">
                <div class="col-md-3">
                    <label class="font-weight-bold text-secondary">Target Sync Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                        </div>
                        <input type="date" name="date" id="targetDate" class="form-control" value="{{ $selectedDate }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-search mr-1"></i> Check Status
                    </button>
                </div>
                <div class="col-md-7 text-right">
                    <button type="button" class="btn btn-success px-4 py-2 font-weight-bold shadow-sm" id="btnStartSync">
                        <i class="fas fa-bolt mr-1"></i> Start Automated Sync Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Live Database Audit Cards for Selected Date --}}
    <h5 class="font-weight-bold text-secondary mb-3"><i class="fas fa-database mr-2"></i>UrbanPOS Database State for {{ \Carbon\Carbon::parse($selectedDate)->format('d M, Y') }}</h5>
    <div class="row">
        {{-- Closing Stock --}}
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-info"><i class="fas fa-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Closing Stock</span>
                    <span class="info-box-number">{{ number_format($stats['closing_stocks_count']) }} <small>SKUs</small></span>
                    <span class="text-muted text-xs">₹{{ number_format($stats['closing_stocks_val'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Daily Sales --}}
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-success"><i class="fas fa-cash-register"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Daily Sales Summary</span>
                    <span class="info-box-number">{{ number_format($stats['daily_sales_bills']) }} <small>Bills</small></span>
                    <span class="text-success font-weight-bold text-xs">₹{{ number_format($stats['daily_sales_total'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Purchase GRNs --}}
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-warning"><i class="fas fa-file-invoice"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Purchase Invoices</span>
                    <span class="info-box-number">{{ number_format($stats['purchases_count']) }} <small>GRNs</small></span>
                    <span class="text-muted text-xs">₹{{ number_format($stats['purchases_amount'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Stock Transfers --}}
        <div class="col-md-3 col-sm-6 col-12">
            <div class="info-box shadow-sm">
                <span class="info-box-icon bg-primary"><i class="fas fa-dolly"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Stock Transfers</span>
                    <span class="info-box-number">{{ number_format($stats['transfers_count']) }} <small>Batches</small></span>
                    <span class="text-muted text-xs">₹{{ number_format($stats['transfers_val'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Exported CSV Storage Files for Selected Date --}}
    <div class="row mt-3">
        <div class="col-md-6">
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-folder-open mr-2 text-warning"></i>Downloaded CSV Reports ({{ count($availableFiles) }} Files)</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Report Filename</th>
                                <th>Size</th>
                                <th>Exported Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($availableFiles as $f)
                                <tr>
                                    <td><i class="fas fa-file-csv text-success mr-2"></i>{{ $f['name'] }}</td>
                                    <td><span class="badge badge-light border">{{ number_format($f['size'] / 1024, 1) }} KB</span></td>
                                    <td class="text-muted text-sm">{{ $f['modified'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No CSV files downloaded yet for {{ $selectedDate }}. Click "Start Automated Sync Now" to fetch them.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-outline card-info shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cogs mr-2 text-info"></i>Execution Console & Details</h3>
                </div>
                <div class="card-body">
                    <div class="callout callout-info mb-3">
                        <h6 class="font-weight-bold">How Credentials-Based Automation Works:</h6>
                        <ul class="mb-0 text-sm pl-3">
                            <li>Logs into TruePOS (<code>urbanpets.true-pos.com</code>) using configured credentials.</li>
                            <li>Automatically handles session concurrency and redirects SmartReport requests.</li>
                            <li>Downloads Closing Stock, Sales (110150/110116), Purchases (110120), and Transfers (110282).</li>
                            <li>Auto-registers new SKUs and performs transactional idempotent database upserts.</li>
                        </ul>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="chkSkipExtract">
                        <label class="custom-control-label" for="chkSkipExtract">Skip Headless Extraction (Re-import existing local CSVs only)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Sync Progress Modal / Terminal --}}
<div class="modal fade" id="syncModal" data-backdrop="static" data-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fas fa-sync-alt fa-spin mr-2 text-info"></i>GoFrugal Sync in Progress...</h5>
            </div>
            <div class="modal-body p-3">
                <div id="syncProgressText" class="mb-2 text-warning font-weight-bold">Connecting to GoFrugal TruePOS portal...</div>
                <div class="progress mb-3" style="height: 6px;">
                    <div id="syncProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 100%"></div>
                </div>
                <pre id="syncTerminalLog" class="p-3 text-light bg-black rounded" style="max-height: 380px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; line-height: 1.4;">Starting synchronization task...</pre>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" id="btnCloseModal" data-dismiss="modal" disabled>Close</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnStart = document.getElementById('btnStartSync');
    const targetDateInp = document.getElementById('targetDate');
    const chkSkip = document.getElementById('chkSkipExtract');
    const logBox = document.getElementById('syncTerminalLog');
    const progressText = document.getElementById('syncProgressText');
    const btnClose = document.getElementById('btnCloseModal');

    btnStart.addEventListener('click', function() {
        const dateVal = targetDateInp.value;
        if (!dateVal) {
            alert('Please select a date to sync.');
            return;
        }

        if (!confirm(`Are you sure you want to run GoFrugal sync for date ${dateVal}?`)) {
            return;
        }

        $('#syncModal').modal('show');
        btnClose.disabled = true;
        logBox.textContent = `[${new Date().toLocaleTimeString()}] Triggering sync process for date ${dateVal}...\n`;
        progressText.textContent = 'Processing request... This may take 1-2 minutes.';

        fetch("{{ route('tools.gofrugal-sync.trigger') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                date: dateVal,
                skip_extract: chkSkip.checked,
                modules: 'all'
            })
        })
        .then(res => res.json())
        .then(data => {
            btnClose.disabled = false;
            if (data.success) {
                progressText.textContent = 'Sync completed successfully!';
                progressText.className = 'mb-2 text-success font-weight-bold';
                logBox.textContent += "\n" + (data.log || "Process finished with exit code 0.");
            } else {
                progressText.textContent = 'Sync finished with warnings/errors.';
                progressText.className = 'mb-2 text-danger font-weight-bold';
                logBox.textContent += "\n" + (data.log || "An error occurred.");
            }
        })
        .catch(err => {
            btnClose.disabled = false;
            progressText.textContent = 'Network or server error during sync.';
            progressText.className = 'mb-2 text-danger font-weight-bold';
            logBox.textContent += `\nError: ${err.message}`;
        });
    });

    btnClose.addEventListener('click', function() {
        window.location.reload();
    });
});
</script>
@stop
