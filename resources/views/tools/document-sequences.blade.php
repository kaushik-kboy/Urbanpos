@extends('adminlte::page')

@section('title', 'Document Sequences & Bill Numbering (बिल नंबरिंग रूल्स)')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-sort-numeric-up-alt text-primary mr-2"></i> Document Sequences <span class="text-muted" style="font-size: 18px; font-weight: normal;">(बिल नंबरिंग रूल्स)</span>
            </h1>
            <p class="text-muted small mb-0">
                Configure voucher prefixes, Indian financial year tokens ({FY}), branch codes, starting numbers, and auto-reset rules.
            </p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center">
            <form action="{{ route('tools.document-sequences.index') }}" method="GET" class="form-inline mr-2">
                <label class="mr-2 font-weight-bold small text-secondary">Branch Scope:</label>
                <select name="branch_id" class="form-control form-control-sm font-weight-bold shadow-sm" onchange="this.form.submit()">
                    <option value="" {{ empty($branchId) ? 'selected' : '' }}>🌐 Global (All Outlets)</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>
                            🏪 {{ $b->name }} ({{ $b->code ?: 'BR-'.$b->id }})
                        </option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-arrow-left mr-1"></i> Sales Bills
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Token Cheat Sheet & Fast Insert Bar --}}
    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center flex-wrap justify-content-between">
                <div class="d-flex align-items-center flex-wrap mb-1 mb-md-0">
                    <span class="font-weight-bold small text-dark mr-2">
                        <i class="fas fa-magic text-warning mr-1"></i> Dynamic Tokens (Click to insert):
                    </span>
                    <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold mr-1 mb-1 token-chip" data-token="{YEAR}">
                        {YEAR} <small class="text-muted">({{ now()->format('Y') }})</small>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold mr-1 mb-1 token-chip" data-token="{YY}">
                        {YY} <small class="text-muted">({{ now()->format('y') }})</small>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-success font-weight-bold mr-1 mb-1 token-chip" data-token="{FY}">
                        {FY} <small class="text-muted">({{ ((int)now()->format('m') >= 4 ? now()->format('y').'-'.((int)now()->format('y')+1) : ((int)now()->format('y')-1).'-'.now()->format('y')) }})</small>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-success font-weight-bold mr-1 mb-1 token-chip" data-token="{FY_NUM}">
                        {FY_NUM} <small class="text-muted">({{ ((int)now()->format('m') >= 4 ? now()->format('y').((int)now()->format('y')+1) : ((int)now()->format('y')-1).now()->format('y')) }})</small>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-info font-weight-bold mr-1 mb-1 token-chip" data-token="{BRANCH}">
                        {BRANCH} <small class="text-muted">(Code)</small>
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold mr-1 mb-1 token-chip" data-token="{MONTH}">
                        {MONTH} <small class="text-muted">({{ now()->format('m') }})</small>
                    </button>
                </div>
                <div class="small text-muted">
                    <i class="fas fa-info-circle mr-1 text-info"></i> Tokens auto-resolve when bills are created.
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Pills by Module --}}
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <span class="font-weight-bold text-secondary small mr-2 text-uppercase">Filter Module:</span>
        <button type="button" class="btn btn-sm btn-dark module-filter-btn active mr-1 mb-1 font-weight-bold" data-module="all">
            All Documents ({{ count($sequences) }})
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary module-filter-btn mr-1 mb-1 font-weight-bold" data-module="Sales">
            Sales
        </button>
        <button type="button" class="btn btn-sm btn-outline-success module-filter-btn mr-1 mb-1 font-weight-bold" data-module="Purchase">
            Purchase
        </button>
        <button type="button" class="btn btn-sm btn-outline-warning module-filter-btn mr-1 mb-1 font-weight-bold" data-module="Inventory">
            Inventory
        </button>
        <button type="button" class="btn btn-sm btn-outline-info module-filter-btn mr-1 mb-1 font-weight-bold" data-module="Finance">
            Finance
        </button>
    </div>

    {{-- Sequences Cards Grid --}}
    <div class="row" id="sequences-container">
        @foreach($sequences as $key => $seq)
            <div class="col-xl-6 col-lg-6 col-md-12 mb-4 sequence-card-col" data-module="{{ $seq['module'] }}" data-title="{{ strtolower($seq['document_title']) }}">
                <div class="card shadow-sm border-0 h-100 sequence-card" id="card-{{ $key }}">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <div>
                            <span class="badge badge-{{ $seq['module'] === 'Sales' ? 'primary' : ($seq['module'] === 'Purchase' ? 'success' : ($seq['module'] === 'Inventory' ? 'warning text-dark' : 'info')) }} text-uppercase font-weight-bold mr-1">
                                {{ $seq['module'] }}
                            </span>
                            <span class="font-weight-bold text-dark" style="font-size: 15px;">
                                {{ $seq['document_title'] }}
                            </span>
                        </div>
                        <div>
                            <span class="badge badge-light border px-2 py-1 font-weight-bold text-monospace" style="font-size: 12px;" id="preview-badge-{{ $key }}">
                                {{ $seq['preview'] }}
                            </span>
                        </div>
                    </div>

                    <form class="sequence-form" data-key="{{ $key }}" action="{{ route('tools.document-sequences.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="document_type" value="{{ $key }}">
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">

                        <div class="card-body">
                            <div class="row">
                                {{-- Prefix Template --}}
                                <div class="col-md-7 mb-3">
                                    <label class="font-weight-bold text-secondary small text-uppercase mb-1">
                                        Prefix Pattern <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           name="prefix" 
                                           class="form-control font-weight-bold text-monospace sequence-prefix-input" 
                                           value="{{ $seq['prefix'] }}" 
                                           required 
                                           placeholder="e.g. SB-{YEAR}- or MOT-{FY}-"
                                           data-key="{{ $key }}">
                                    <small class="text-muted">Use {YEAR}, {YY}, {FY}, {FY_NUM}, {FY_LONG}, {BRANCH}, {MONTH}</small>
                                </div>

                                {{-- Starting / Next Number --}}
                                <div class="col-md-5 mb-3">
                                    <label class="font-weight-bold text-secondary small text-uppercase mb-1">
                                        Starting Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" 
                                           name="starting_number" 
                                           class="form-control font-weight-bold sequence-start-input" 
                                           value="{{ $seq['starting_number'] }}" 
                                           min="1" 
                                           required
                                           data-key="{{ $key }}">
                                    <small class="text-muted">Starts counter from here</small>
                                </div>
                            </div>

                            <div class="row">
                                {{-- Padding Length --}}
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold text-secondary small text-uppercase mb-1">Zero Padding (Digits)</label>
                                    <select name="padding_zeros" class="form-control sequence-padding-input" data-key="{{ $key }}">
                                        <option value="3" {{ $seq['padding_zeros'] == 3 ? 'selected' : '' }}>3 digits (e.g. 001)</option>
                                        <option value="4" {{ $seq['padding_zeros'] == 4 ? 'selected' : '' }}>4 digits (e.g. 0001)</option>
                                        <option value="5" {{ $seq['padding_zeros'] == 5 ? 'selected' : '' }}>5 digits (e.g. 00001)</option>
                                        <option value="6" {{ $seq['padding_zeros'] == 6 ? 'selected' : '' }}>6 digits (e.g. 000001)</option>
                                    </select>
                                </div>

                                {{-- Reset Counter Frequency --}}
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-bold text-secondary small text-uppercase mb-1">Reset Counter Rule</label>
                                    <select name="reset_frequency" class="form-control font-weight-bold sequence-reset-input" data-key="{{ $key }}">
                                        <option value="financial_year" {{ $seq['reset_frequency'] === 'financial_year' ? 'selected' : '' }}>
                                            📅 Financial Year (1st April)
                                        </option>
                                        <option value="yearly" {{ $seq['reset_frequency'] === 'yearly' ? 'selected' : '' }}>
                                            🗓️ Calendar Year (1st Jan)
                                        </option>
                                        <option value="monthly" {{ $seq['reset_frequency'] === 'monthly' ? 'selected' : '' }}>
                                            📆 Monthly (1st of month)
                                        </option>
                                        <option value="never" {{ $seq['reset_frequency'] === 'never' ? 'selected' : '' }}>
                                            ♾️ Never (Continuous)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            {{-- Current Status & Live Summary --}}
                            <div class="bg-light rounded p-2 d-flex justify-content-between align-items-center mb-0 border">
                                <div class="small">
                                    <span class="text-muted">Current Counter:</span> 
                                    <strong class="text-dark">{{ $seq['last_number'] }}</strong>
                                    @if($seq['last_number'] > 0)
                                        <span class="text-muted ml-2">({{ $seq['last_number'] }} documents issued)</span>
                                    @endif
                                </div>
                                <div>
                                    <span class="badge badge-success font-weight-bold" id="status-badge-{{ $key }}">Active</span>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
                            <div>
                                @if($seq['id'] && $seq['last_number'] > 0)
                                    <button type="button" class="btn btn-outline-danger btn-xs font-weight-bold btn-reset-seq" data-id="{{ $seq['id'] }}" data-title="{{ $seq['document_title'] }}">
                                        <i class="fas fa-undo mr-1"></i> Reset Counter
                                    </button>
                                @endif
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3 btn-save-seq">
                                <i class="fas fa-save mr-1"></i> Save Rule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@stop

@section('css')
<style>
    .token-chip {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .token-chip:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }
    .sequence-card {
        border-radius: 8px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .sequence-card:hover {
        box-shadow: 0 6px 18px rgba(0,0,0,0.08) !important;
    }
</style>
@stop

@section('js')
<script>
    let activePrefixInput = null;

    // Keep track of the last focused prefix input so clicking a token inserts it
    document.querySelectorAll('.sequence-prefix-input').forEach(input => {
        input.addEventListener('focus', function() {
            activePrefixInput = this;
        });
        input.addEventListener('input', function() {
            updateLivePreview(this.dataset.key);
        });
    });

    document.querySelectorAll('.sequence-start-input, .sequence-padding-input').forEach(input => {
        input.addEventListener('input', function() {
            updateLivePreview(this.dataset.key);
        });
        input.addEventListener('change', function() {
            updateLivePreview(this.dataset.key);
        });
    });

    // Token Chips Click to Insert
    document.querySelectorAll('.token-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            const token = this.dataset.token;
            if (!activePrefixInput) {
                // Focus first prefix input if none focused
                activePrefixInput = document.querySelector('.sequence-prefix-input');
            }
            if (activePrefixInput) {
                const start = activePrefixInput.selectionStart || activePrefixInput.value.length;
                const end = activePrefixInput.selectionEnd || activePrefixInput.value.length;
                const val = activePrefixInput.value;
                activePrefixInput.value = val.substring(0, start) + token + val.substring(end);
                activePrefixInput.focus();
                activePrefixInput.setSelectionRange(start + token.length, start + token.length);
                updateLivePreview(activePrefixInput.dataset.key);
            }
        });
    });

    // Compute client-side preview in real time
    function updateLivePreview(key) {
        const card = document.getElementById('card-' + key);
        if (!card) return;

        let prefix = card.querySelector('.sequence-prefix-input').value;
        const start = parseInt(card.querySelector('.sequence-start-input').value) || 1;
        const padding = parseInt(card.querySelector('.sequence-padding-input').value) || 4;

        const now = new Date();
        const year = now.getFullYear();
        const yy = String(year).slice(-2);
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const monthNum = now.getMonth() + 1;
        
        // Indian Financial Year
        let fy = '';
        if (monthNum >= 4) {
            fy = yy + '-' + String(year + 1).slice(-2);
        } else {
            fy = String(year - 1).slice(-2) + '-' + yy;
        }

        // Replace tokens
        let resolved = prefix
            .replace(/\{YEAR\}/gi, year)
            .replace(/\{YY\}/gi, yy)
            .replace(/\{FY\}/gi, fy)
            .replace(/\{BRANCH\}/gi, 'MOT')
            .replace(/\{MONTH\}/gi, month);

        let numStr = String(start).padStart(padding, '0');
        const badge = document.getElementById('preview-badge-' + key);
        if (badge) {
            badge.textContent = resolved + numStr;
            badge.classList.add('badge-warning');
            setTimeout(() => badge.classList.remove('badge-warning'), 300);
        }
    }

    // Module Filter Buttons
    document.querySelectorAll('.module-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.module-filter-btn').forEach(b => {
                b.classList.remove('active', 'btn-dark', 'btn-primary', 'btn-success', 'btn-warning', 'btn-info');
                b.classList.add('btn-outline-' + (b.dataset.module === 'Sales' ? 'primary' : (b.dataset.module === 'Purchase' ? 'success' : (b.dataset.module === 'Inventory' ? 'warning' : (b.dataset.module === 'Finance' ? 'info' : 'dark')))));
            });

            this.classList.add('active');
            const targetMod = this.dataset.module;

            document.querySelectorAll('.sequence-card-col').forEach(col => {
                if (targetMod === 'all' || col.dataset.module === targetMod) {
                    col.style.display = 'block';
                } else {
                    col.style.display = 'none';
                }
            });
        });
    });

    // AJAX Form Submission for Instant Feedback
    document.querySelectorAll('.sequence-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-save-seq');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check mr-1"></i> Saved!';
                setTimeout(() => btn.innerHTML = origHtml, 2000);

                if (data.preview) {
                    const badge = document.getElementById('preview-badge-' + form.dataset.key);
                    if (badge) badge.textContent = data.preview;
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                alert('Error saving sequence: ' + err.message);
            });
        });
    });

    // Reset Counter Action
    document.querySelectorAll('.btn-reset-seq').forEach(btn => {
        btn.addEventListener('click', function() {
            const seqId = this.dataset.id;
            const title = this.dataset.title;
            if (!confirm(`Are you sure you want to reset the sequence counter for "${title}" to starting number?`)) {
                return;
            }

            fetch(`/tools/document-sequences/${seqId}/reset`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                alert('✅ ' + data.message);
                window.location.reload();
            })
            .catch(err => {
                alert('Error resetting counter: ' + err.message);
            });
        });
    });
</script>
@stop
