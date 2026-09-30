@extends('adminlte::page')

@section('title', 'Universal Bulk Modifier')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark">
                <i class="fas fa-layer-group text-primary mr-2"></i>Universal Bulk Operations Studio
            </h1>
            <p class="text-muted small mb-0">Multi-module & Any-Table batch modifier for Products, Customers, Suppliers, Operations, Masters & All Database Tables.</p>
        </div>
        <div>
            <a href="{{ url('reports/view/gst-tax-change-audit') }}" class="btn btn-outline-info btn-sm mr-2 shadow-sm">
                <i class="fas fa-history mr-1"></i> Tax Change Audit Report
            </a>
            <a href="{{ url('reports/audit-logs') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-clipboard-list mr-1"></i> System Audit Logs
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Module Category Navigation & Switcher -->
    <div class="card card-outline card-primary shadow-sm mb-4">
        <div class="card-header p-2 bg-white">
            <ul class="nav nav-tabs border-bottom-0" id="moduleGroupTabs" role="tablist">
                @php
                    $activeGroup = $isDynamicTableMode ? 'Dynamic Table Mode' : ($currentDef['group'] ?? 'Catalog & Inventory');
                @endphp
                @foreach($groupedModules as $grpName => $grpItems)
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold {{ $activeGroup === $grpName ? 'active text-primary' : 'text-secondary' }}" 
                           id="tab-{{ Str::slug($grpName) }}" data-toggle="tab" href="#group-{{ Str::slug($grpName) }}" role="tab">
                            {{ $grpName }}
                        </a>
                    </li>
                @endforeach
                <li class="nav-item">
                    <a class="nav-link font-weight-bold {{ $isDynamicTableMode ? 'active text-warning' : 'text-secondary' }}" 
                       id="tab-dynamic-tables" data-toggle="tab" href="#group-dynamic-tables" role="tab">
                        <i class="fas fa-database mr-1"></i> All Database Tables (Any Table)
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-3 bg-light border-bottom">
            <div class="tab-content" id="moduleGroupTabsContent">
                @foreach($groupedModules as $grpName => $grpItems)
                    <div class="tab-pane fade {{ $activeGroup === $grpName ? 'show active' : '' }}" id="group-{{ Str::slug($grpName) }}" role="tabpanel">
                        <div class="d-flex flex-wrap" style="gap: 8px;">
                            @foreach($grpItems as $mKey => $mDef)
                                <a href="{{ route('tools.bulk-updater.index', ['module' => $mKey]) }}" 
                                   class="btn btn-sm {{ $selectedModule === $mKey && !$isDynamicTableMode ? 'btn-primary font-weight-bold shadow-sm' : 'btn-white border text-dark' }}">
                                    <i class="{{ $mDef['icon'] }} mr-1"></i> {{ $mDef['title'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <!-- Dynamic Table Mode Explorer Pane -->
                <div class="tab-pane fade {{ $isDynamicTableMode ? 'show active' : '' }}" id="group-dynamic-tables" role="tabpanel">
                    <form method="GET" action="{{ route('tools.bulk-updater.index') }}" class="form-inline">
                        <input type="hidden" name="module" value="dynamic_table">
                        <label class="mr-2 font-weight-bold text-dark">
                            <i class="fas fa-table mr-1 text-warning"></i> Select Target Database Table:
                        </label>
                        <select name="table" class="form-control form-control-sm select2 mr-2" style="min-width: 280px;" onchange="this.form.submit()">
                            @foreach($allTables as $tName)
                                <option value="{{ $tName }}" {{ $selectedTable === $tName ? 'selected' : '' }}>
                                    {{ $tName }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-warning btn-sm font-weight-bold shadow-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Load Table Schema
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-footer py-2 px-3 bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="fas fa-info-circle text-info mr-1"></i> 
                    <strong>Active Target:</strong> {{ $currentDef['title'] }} 
                    <span class="badge badge-secondary ml-1">{{ $currentDef['table'] ?? $selectedTable }}</span> — 
                    {{ $currentDef['description'] }}
                </small>
            </div>
        </div>
    </div>

    <form id="bulkUpdateForm" action="{{ route('tools.bulk-updater.execute') }}" method="POST">
        @csrf
        <input type="hidden" name="module" id="selectedModuleInput" value="{{ $selectedModule }}">
        @if($isDynamicTableMode)
            <input type="hidden" name="dynamic_table" id="dynamicTableInput" value="{{ $selectedTable }}">
        @endif

        <div class="row">
            <!-- STEP 1: Filter Conditions -->
            <div class="col-lg-6">
                <div class="card card-outline card-info shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title font-weight-bold mb-0">
                            <span class="badge badge-info mr-2">Step 1</span> Filter Target Records (Who to update?)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Specify conditions to isolate the records you wish to modify in bulk. Leave blank to match all records.</p>
                        
                        <div id="filterFieldsContainer">
                            @if(!$isDynamicTableMode)
                                @foreach($currentDef['filterable_fields'] as $fKey => $fMeta)
                                    <div class="form-group row mb-2">
                                        <label class="col-sm-5 col-form-label font-weight-bold text-muted small text-right">
                                            {{ $fMeta['label'] }}:
                                        </label>
                                        <div class="col-sm-7">
                                            @if($fMeta['type'] === 'relation')
                                                @php
                                                    $relData = $lookups[$fMeta['relation_table']] ?? [];
                                                    $relLabel = $fMeta['relation_label'] ?? 'name';
                                                @endphp
                                                <select name="filters[{{ $fKey }}]" class="form-control form-control-sm select2 filter-input">
                                                    <option value="">-- All / Any --</option>
                                                    @foreach($relData as $rRow)
                                                        <option value="{{ $rRow->id }}">{{ $rRow->{$relLabel} }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($fMeta['type'] === 'select')
                                                <select name="filters[{{ $fKey }}]" class="form-control form-control-sm filter-input">
                                                    <option value="">-- All / Any --</option>
                                                    @foreach($fMeta['options'] as $opt)
                                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($fMeta['type'] === 'boolean')
                                                <select name="filters[{{ $fKey }}]" class="form-control form-control-sm filter-input">
                                                    <option value="">-- All / Any --</option>
                                                    <option value="1">Yes / Active / Enabled</option>
                                                    <option value="0">No / Inactive / Disabled</option>
                                                </select>
                                            @else
                                                <input type="text" name="filters[{{ $fKey }}]" class="form-control form-control-sm filter-input" placeholder="Filter by {{ $fMeta['label'] }}...">
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <!-- Dynamic Table Filters Builder -->
                                <div class="alert alert-light border small text-muted mb-3">
                                    <i class="fas fa-columns text-primary mr-1"></i> Add filter criteria on any column in <code>{{ $selectedTable }}</code>:
                                </div>
                                <div id="dynamicFilterRows">
                                    <div class="form-row align-items-center mb-2 dynamic-filter-row">
                                        <div class="col-5">
                                            <select class="form-control form-control-sm dynamic-col-select" id="dynFilterCol0">
                                                <option value="">-- Choose Column --</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" class="form-control form-control-sm dynamic-val-input" placeholder="Filter value...">
                                        </div>
                                        <div class="col-1 text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-filter" style="display: none;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-xs mt-2" id="btnAddDynamicFilter">
                                    <i class="fas fa-plus mr-1"></i> Add Another Filter Condition
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top text-right">
                        <button type="button" id="btnPreview" class="btn btn-info font-weight-bold shadow-sm">
                            <i class="fas fa-search mr-1"></i> Preview Matched Records
                        </button>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Update Action -->
            <div class="col-lg-6">
                <div class="card card-outline card-success shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title font-weight-bold mb-0">
                            <span class="badge badge-success mr-2">Step 2</span> Choose Target Action (What to update?)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Select the attribute and new value that will be assigned to all matched records.</p>
                        
                        <div class="form-group">
                            <label class="font-weight-bold text-dark">Attribute / Column To Update <span class="text-danger">*</span></label>
                            @if(!$isDynamicTableMode)
                                <select name="target_field" id="targetFieldSelect" class="form-control form-control-sm font-weight-bold" required>
                                    <option value="">-- Select Field to Modify --</option>
                                    @foreach($currentDef['updatable_fields'] as $uKey => $uMeta)
                                        <option value="{{ $uKey }}" data-type="{{ $uMeta['type'] }}" 
                                                data-relation="{{ $uMeta['relation_table'] ?? '' }}"
                                                data-label="{{ $uMeta['relation_label'] ?? '' }}"
                                                data-options="{{ isset($uMeta['options']) ? json_encode($uMeta['options']) : '' }}">
                                            {{ $uMeta['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <select name="target_field" id="targetFieldSelect" class="form-control form-control-sm font-weight-bold" required>
                                    <option value="">-- Select Column to Update --</option>
                                </select>
                            @endif
                        </div>

                        <!-- Dynamic Value Container -->
                        <div id="targetValueWrapper" class="mt-3 p-3 bg-light rounded border" style="display: none;">
                            <label id="targetValueLabel" class="font-weight-bold text-dark mb-1">New Value</label>
                            
                            <!-- Math mode for price fields -->
                            <div id="mathModeContainer" class="mb-2" style="display: none;">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="math_mode" id="mathModeFixed" value="fixed" checked>
                                    <label class="form-check-label font-weight-bold" for="mathModeFixed">Set Fixed Value</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="math_mode" id="mathModePercent" value="percent">
                                    <label class="form-check-label font-weight-bold" for="mathModePercent">Adjust by Percentage (+/- %)</label>
                                </div>
                            </div>

                            <div id="dynamicInputSlot">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>

                        <!-- Matched Summary Box -->
                        <div id="matchedSummaryBox" class="mt-4 p-3 rounded border border-info bg-white" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small">Matched Target Records:</span>
                                    <h4 class="font-weight-bold text-info mb-0" id="matchedCountText">0 Records</h4>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-info btn-sm" id="btnTogglePreviewTable">
                                        <i class="fas fa-table mr-1"></i> View Sample Rows
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top text-right">
                        <button type="button" id="btnOpenConfirmModal" class="btn btn-success font-weight-bold shadow-sm" disabled>
                            <i class="fas fa-check-double mr-1"></i> Apply Bulk Update
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sample Records Preview Table -->
        <div class="card card-outline card-secondary shadow-sm mt-4" id="sampleTableCard" style="display: none;">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <h6 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-list mr-1"></i> Matched Records Preview (Showing first 20 rows)
                </h6>
                <button type="button" class="btn btn-tool" id="btnClosePreviewTable">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Name / Primary Identifier</th>
                                <th>Code / Secondary Field</th>
                            </tr>
                        </thead>
                        <tbody id="sampleTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Safety Confirmation Modal -->
        <div class="modal fade" id="confirmBulkModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-warning py-3">
                        <h5 class="modal-title font-weight-bold text-dark">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Bulk Operation
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-4 text-center">
                        <i class="fas fa-database text-warning mb-3" style="font-size: 50px;"></i>
                        <h5 class="font-weight-bold text-dark mb-2">Are you absolutely sure?</h5>
                        <p class="text-muted mb-3" id="confirmModalSummaryText">
                            You are about to modify records across the database.
                        </p>
                        <div class="alert alert-light border small text-left text-muted mb-0">
                            <i class="fas fa-shield-alt text-success mr-1"></i> This batch operation will execute in a secure database transaction and will be permanently recorded in the system regulatory audit logs.
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success font-weight-bold" id="btnSubmitExecute">
                            <i class="fas fa-check mr-1"></i> Confirm & Execute Update
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@section('js')
<script>
$(document).ready(function() {
    const lookups = @json($lookups);
    const isDynamic = @json($isDynamicTableMode);
    const dynamicTable = @json($selectedTable);
    let tableColumns = [];
    let currentMatchedCount = 0;

    // Load columns dynamically if in Dynamic Table Mode
    if (isDynamic) {
        $.get("{{ route('tools.bulk-updater.table-columns') }}", { table: dynamicTable }, function(res) {
            if (res.success) {
                tableColumns = res.columns;
                populateDynamicColumns();
            }
        });
    }

    function populateDynamicColumns() {
        const $targetSelect = $('#targetFieldSelect');
        $targetSelect.empty().append('<option value="">-- Select Column to Update --</option>');
        
        $('.dynamic-col-select').each(function() {
            const currentVal = $(this).val();
            $(this).empty().append('<option value="">-- Choose Column --</option>');
            tableColumns.forEach(col => {
                $(this).append(`<option value="${col}">${col}</option>`);
            });
            if (currentVal) $(this).val(currentVal);
        });

        tableColumns.forEach(col => {
            if (col !== 'id') {
                $targetSelect.append(`<option value="${col}" data-type="string">${col}</option>`);
            }
        });
    }

    // Add Dynamic Filter Row
    $('#btnAddDynamicFilter').on('click', function() {
        const rowId = $('.dynamic-filter-row').length;
        let colOptions = '<option value="">-- Choose Column --</option>';
        tableColumns.forEach(col => {
            colOptions += `<option value="${col}">${col}</option>`;
        });

        const newRow = $(`
            <div class="form-row align-items-center mb-2 dynamic-filter-row">
                <div class="col-5">
                    <select class="form-control form-control-sm dynamic-col-select" id="dynFilterCol${rowId}">
                        ${colOptions}
                    </select>
                </div>
                <div class="col-6">
                    <input type="text" class="form-control form-control-sm dynamic-val-input" placeholder="Filter value...">
                </div>
                <div class="col-1 text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-filter">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `);
        $('#dynamicFilterRows').append(newRow);
    });

    $(document).on('click', '.btn-remove-filter', function() {
        $(this).closest('.dynamic-filter-row').remove();
    });

    // Target Field Selection Handler
    $('#targetFieldSelect').on('change', function() {
        const selectedOpt = $(this).find('option:selected');
        const fieldKey = $(this).val();

        if (!fieldKey) {
            $('#targetValueWrapper').hide();
            $('#btnOpenConfirmModal').prop('disabled', true);
            return;
        }

        const type = selectedOpt.data('type') || 'string';
        const relation = selectedOpt.data('relation');
        const label = selectedOpt.data('label') || 'name';
        const rawOptions = selectedOpt.data('options');

        $('#targetValueLabel').text('New Value for: ' + selectedOpt.text().trim());
        $('#targetValueWrapper').show();
        $('#mathModeContainer').hide();

        let html = '';

        if (type === 'relation' && lookups[relation]) {
            const list = lookups[relation] || [];
            html = `<select name="target_value" class="form-control form-control-sm select2" required style="width: 100%;">
                        <option value="">-- Choose New Value --</option>`;
            list.forEach(item => {
                const text = item[label] || item.name || item.value;
                html += `<option value="${item.id}">${text}</option>`;
            });
            html += `</select>`;
        } else if (type === 'select' && rawOptions) {
            let opts = [];
            try { opts = typeof rawOptions === 'string' ? JSON.parse(rawOptions) : rawOptions; } catch(e) {}
            html = `<select name="target_value" class="form-control form-control-sm" required>
                        <option value="">-- Choose Option --</option>`;
            opts.forEach(opt => {
                html += `<option value="${opt}">${opt}</option>`;
            });
            html += `</select>`;
        } else if (type === 'boolean') {
            html = `<select name="target_value" class="form-control form-control-sm" required>
                        <option value="1">Active / Yes / Enabled (1)</option>
                        <option value="0">Inactive / No / Disabled (0)</option>
                    </select>`;
        } else if (type === 'math_number') {
            $('#mathModeContainer').show();
            html = `<div id="mathInputSlot">
                        <input type="number" step="0.01" name="target_value" class="form-control form-control-sm" placeholder="Enter new price (₹)..." required>
                    </div>`;
        } else if (type === 'number') {
            html = `<input type="number" step="0.01" name="target_value" class="form-control form-control-sm" placeholder="Enter numeric value..." required>`;
        } else {
            html = `<input type="text" name="target_value" class="form-control form-control-sm" placeholder="Enter new value..." required>`;
        }

        $('#dynamicInputSlot').html(html);
        if ($('#dynamicInputSlot select.select2').length) {
            $('#dynamicInputSlot select.select2').select2({ theme: 'bootstrap4' });
        }

        checkReadyToExecute();
    });

    // Math mode radio switch
    $('input[name="math_mode"]').on('change', function() {
        if ($(this).val() === 'percent') {
            $('#dynamicInputSlot').html(`
                <div class="input-group input-group-sm">
                    <input type="number" step="0.01" name="math_percent" class="form-control" placeholder="e.g. 10 for +10% or -5 for -5%" required>
                    <div class="input-group-append">
                        <span class="input-group-text font-weight-bold">% Adjust</span>
                    </div>
                </div>
            `);
        } else {
            $('#dynamicInputSlot').html(`
                <input type="number" step="0.01" name="target_value" class="form-control form-control-sm" placeholder="Enter new price (₹)..." required>
            `);
        }
    });

    function getCollectedFilters() {
        const filters = {};
        if (isDynamic) {
            $('.dynamic-filter-row').each(function() {
                const col = $(this).find('.dynamic-col-select').val();
                const val = $(this).find('.dynamic-val-input').val();
                if (col && val !== '') {
                    filters[col] = val;
                }
            });
        } else {
            $('.filter-input').each(function() {
                const name = $(this).attr('name');
                if (!name) return;
                const match = name.match(/filters\[(.*?)\]/);
                if (match && match[1]) {
                    const val = $(this).val();
                    if (val !== null && val !== '') {
                        filters[match[1]] = val;
                    }
                }
            });
        }
        return filters;
    }

    // Preview button click
    $('#btnPreview').on('click', function() {
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Matching records...').prop('disabled', true);

        const filters = getCollectedFilters();
        const payload = {
            _token: "{{ csrf_token() }}",
            module: $('#selectedModuleInput').val(),
            filters: filters,
            dynamic_table: isDynamic ? dynamicTable : null
        };

        $.ajax({
            url: "{{ route('tools.bulk-updater.preview') }}",
            method: "POST",
            data: payload,
            success: function(res) {
                if (res.success) {
                    currentMatchedCount = res.total_count;
                    $('#matchedCountText').text(res.total_count + ' Records');
                    $('#matchedSummaryBox').slideDown();

                    // Render sample rows
                    let rowsHtml = '';
                    if (res.sample_rows && res.sample_rows.length > 0) {
                        res.sample_rows.forEach(r => {
                            rowsHtml += `<tr>
                                <td><span class="badge badge-light border">#${r.id}</span></td>
                                <td class="font-weight-bold text-dark">${r.label}</td>
                                <td class="text-muted small">${r.code}</td>
                            </tr>`;
                        });
                    } else {
                        rowsHtml = `<tr><td colspan="3" class="text-center text-muted py-3">No records matched the filter criteria.</td></tr>`;
                    }
                    $('#sampleTableBody').html(rowsHtml);

                    checkReadyToExecute();
                }
            },
            error: function(xhr) {
                alert('Preview error: ' + (xhr.responseJSON?.message || 'Could not fetch records.'));
            },
            complete: function() {
                $btn.html(originalHtml).prop('disabled', false);
            }
        });
    });

    // Toggle Preview Table
    $('#btnTogglePreviewTable').on('click', function() {
        $('#sampleTableCard').slideToggle();
    });
    $('#btnClosePreviewTable').on('click', function() {
        $('#sampleTableCard').slideUp();
    });

    function checkReadyToExecute() {
        const hasField = $('#targetFieldSelect').val() !== '';
        if (currentMatchedCount > 0 && hasField) {
            $('#btnOpenConfirmModal').prop('disabled', false);
        } else {
            $('#btnOpenConfirmModal').prop('disabled', true);
        }
    }

    // Open Confirmation Modal
    $('#btnOpenConfirmModal').on('click', function() {
        const fieldName = $('#targetFieldSelect option:selected').text().trim();
        const targetName = isDynamic ? `table '${dynamicTable}'` : 'selected module';
        $('#confirmModalSummaryText').html(
            `You are about to modify <strong>${currentMatchedCount} records</strong> in ${targetName}.<br>` +
            `Target attribute: <span class="badge badge-primary">${fieldName}</span>`
        );
        $('#confirmBulkModal').modal('show');
    });

    // Prevent duplicate submission on submit
    $('#bulkUpdateForm').on('submit', function() {
        // Collect dynamic filters into hidden inputs before submitting
        if (isDynamic) {
            const filters = getCollectedFilters();
            for (const [col, val] of Object.entries(filters)) {
                $('<input>').attr({
                    type: 'hidden',
                    name: `filters[${col}]`,
                    value: val
                }).appendTo('#bulkUpdateForm');
            }
        }
        $('#btnSubmitExecute').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Executing...');
    });
});
</script>
@stop
