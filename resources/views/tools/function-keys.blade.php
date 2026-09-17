@extends('adminlte::page')

@section('title', 'Function Key Mapping')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-keyboard text-primary mr-2"></i>Function Key Mapping
            </h1>
            <small class="text-muted">Tools &gt; Configuration &gt; Function Key Mapping</small>
        </div>
        <div>
            <form action="{{ route('tools.function-keys.reset') }}" method="POST" class="d-inline" onsubmit="return confirm('Reset all keyboard shortcuts back to default ERP settings?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm font-weight-bold">
                    <i class="fas fa-undo mr-1"></i> Reset to Defaults
                </button>
            </form>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <div class="bg-primary text-white rounded p-3 mr-3 shadow-sm">
                        <i class="fas fa-bolt fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="font-weight-bold mb-1">100% Mouse-Free Keyboard Operation</h5>
                        <p class="text-muted mb-0 small">
                            Configure hotkeys for high-speed counter billing and instant page switching. Changes apply immediately across all user screens without refreshing.
                        </p>
                    </div>
                </div>
                <div class="bg-light border rounded px-3 py-2 text-right">
                    <small class="text-muted d-block font-weight-bold text-uppercase">Interactive Key Detector</small>
                    <span id="key-detector" class="badge badge-dark px-3 py-2 font-weight-bold" style="font-size: 0.95rem;">
                        Press any key to test…
                    </span>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('tools.function-keys.update') }}" method="POST">
        @csrf

        <div class="card card-outline card-secondary shadow-sm">
            <div class="card-header p-2 bg-white">
                <ul class="nav nav-pills" id="shortcut-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="tab-all-link" data-toggle="pill" href="#tab-all" role="tab">
                            <i class="fas fa-list mr-1"></i> All Shortcuts ({{ $mappings->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-nav-link" data-toggle="pill" href="#tab-nav" role="tab">
                            <i class="fas fa-compass mr-1"></i> Page Navigation (Alt + Key)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-pos-link" data-toggle="pill" href="#tab-pos" role="tab">
                            <i class="fas fa-cash-register mr-1"></i> Billing &amp; Actions (F1 - F10)
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="table-shortcuts">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 70px;" class="text-center">Status</th>
                                <th style="width: 220px;">Shortcut Combination</th>
                                <th>Action / Assigned Purpose</th>
                                <th style="width: 140px;">Scope</th>
                                <th>Target Route / Function</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mappings as $mapping)
                                @php
                                    $isNav = str_starts_with($mapping->shortcut_combination, 'Alt+') || str_starts_with($mapping->shortcut_combination, 'Ctrl+');
                                    $rowCategory = $isNav ? 'nav' : 'pos';
                                @endphp
                                <tr data-category="{{ $rowCategory }}">
                                    <td class="text-center align-middle">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switch_{{ $mapping->id }}"
                                                   name="mappings[{{ $mapping->id }}][is_enabled]"
                                                   value="1"
                                                   @checked($mapping->is_enabled)>
                                            <label class="custom-control-label" for="switch_{{ $mapping->id }}"></label>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-white"><i class="fas fa-keyboard text-muted"></i></span>
                                            </div>
                                            <input type="text"
                                                   name="mappings[{{ $mapping->id }}][shortcut_combination]"
                                                   value="{{ $mapping->shortcut_combination }}"
                                                   class="form-control font-weight-bold shortcut-input text-uppercase"
                                                   list="suggested-keys"
                                                   placeholder="e.g. Alt+S, F2">
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <strong class="text-dark">{{ $mapping->action_title }}</strong>
                                        <div class="small text-muted font-monospace">Key: <code>{{ $mapping->action_key }}</code></div>
                                    </td>
                                    <td class="align-middle">
                                        @if($mapping->scope === 'global')
                                            <span class="badge badge-primary px-2 py-1"><i class="fas fa-globe mr-1"></i> Global</span>
                                        @elseif($mapping->scope === 'all_forms')
                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-edit mr-1"></i> All Forms</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">{{ ucfirst($mapping->scope) }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($mapping->target_url)
                                            <a href="{{ url($mapping->target_url) }}" target="_blank" class="text-primary font-weight-bold small">
                                                <i class="fas fa-external-link-alt mr-1"></i> /{{ $mapping->target_url }}
                                            </a>
                                        @else
                                            <span class="text-muted small font-italic">Internal Transaction Trigger</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <span class="text-muted small">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    <strong>Chrome Advice:</strong> For page jumps, use <code>Alt + [Letter]</code>. For transactional operations, use <code>F1 - F10</code>.
                </span>
                <div>
                    <a href="{{ url()->previous() }}" class="btn btn-default mr-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Save Key Mappings
                    </button>
                </div>
            </div>
        </div>
    </form>

    <datalist id="suggested-keys">
        <option value="Alt+S">Sales Bill</option>
        <option value="Alt+P">Purchase Invoice</option>
        <option value="Alt+T">Stock Transfer</option>
        <option value="Alt+C">Customer Master</option>
        <option value="Alt+I">Item Master</option>
        <option value="Alt+O">Purchase Order</option>
        <option value="Alt+Q">Sales Quotation</option>
        <option value="Alt+R">Sales Return</option>
        <option value="Ctrl+Shift+S">Alternative Sales</option>
        <option value="Ctrl+Shift+P">Alternative Purchase</option>
        <option value="F2">Item Search</option>
        <option value="F3">New Entry / Row</option>
        <option value="F4">Edit Mode</option>
        <option value="F6">Save & Tender</option>
        <option value="F7">View Records</option>
        <option value="F8">Print Slip</option>
        <option value="F9">Clear Form</option>
        <option value="F10">Close / Back</option>
    </datalist>
@stop

@push('js')
<script>
    $(document).ready(function () {
        // Tab filtering
        $('#shortcut-tabs a').on('click', function (e) {
            e.preventDefault();
            $(this).tab('show');

            let target = $(this).attr('id');
            if (target === 'tab-all-link') {
                $('#table-shortcuts tbody tr').show();
            } else if (target === 'tab-nav-link') {
                $('#table-shortcuts tbody tr').hide();
                $('#table-shortcuts tbody tr[data-category="nav"]').show();
            } else if (target === 'tab-pos-link') {
                $('#table-shortcuts tbody tr').hide();
                $('#table-shortcuts tbody tr[data-category="pos"]').show();
            }
        });

        // Interactive Key Detector box
        window.addEventListener('keydown', function (e) {
            let parts = [];
            if (e.ctrlKey) parts.push('Ctrl');
            if (e.altKey) parts.push('Alt');
            if (e.shiftKey) parts.push('Shift');
            if (e.key && !['Control', 'Alt', 'Shift', 'Meta'].includes(e.key)) {
                parts.push(e.key.length === 1 ? e.key.toUpperCase() : e.key);
            }
            if (parts.length) {
                let detected = parts.join('+');
                $('#key-detector').text('Detected: ' + detected).removeClass('badge-dark').addClass('badge-success');
            }
        });
    });
</script>
@endpush
