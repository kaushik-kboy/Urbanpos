@extends('adminlte::page')

@section('title', 'Form Field Validations')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-check-double text-primary mr-2"></i>Form Field Validations</h1>
            <p class="text-muted small mb-0">Customize and toggle field rules (Required, Future Date Block, Readonly, Unique) and custom alert messages level-wise across all modules.</p>
        </div>
    </div>
@stop

@section('content')
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Category Groups Bar & Quick Jump Selector --}}
    <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center">
        <div class="btn-group mb-2 mb-md-0 shadow-sm" role="group">
            @foreach($moduleGroups as $gKey => $group)
                <a href="{{ route('tools.form-validations.index', ['group' => $gKey]) }}"
                   class="btn {{ $activeGroup === $gKey ? 'btn-primary font-weight-bold' : 'btn-outline-secondary bg-white' }} px-3 py-2">
                    <i class="{{ $group['icon'] }} mr-1"></i> {{ $group['name'] }}
                    <span class="badge badge-light border ml-1">{{ count($group['modules']) }}</span>
                </a>
            @endforeach
        </div>

        <div class="d-flex align-items-center bg-white p-2 rounded border shadow-sm">
            <label class="mr-2 mb-0 small font-weight-bold text-muted text-nowrap"><i class="fas fa-search mr-1"></i> Jump to Page:</label>
            <select class="form-control form-control-sm" style="min-width: 230px;" onchange="if(this.value) window.location.href=this.value;">
                @foreach($moduleGroups as $gKey => $group)
                    <optgroup label="── {{ $group['name'] }} ──">
                        @foreach($group['modules'] as $mKey => $mod)
                            <option value="{{ route('tools.form-validations.index', ['group' => $gKey, 'module' => $mKey]) }}" @selected($activeModule === $mKey)>
                                {{ $mod['name'] }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Standard AdminLTE Card with Outline Tabs for Active Category's Pages --}}
    <div class="card card-primary card-outline card-outline-tabs shadow-sm mb-4">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="page-tabs" role="tablist">
                @foreach($moduleGroups[$activeGroup]['modules'] as $mKey => $mod)
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold {{ $activeModule === $mKey ? 'active' : '' }}"
                           href="{{ route('tools.form-validations.index', ['group' => $activeGroup, 'module' => $mKey]) }}">
                            <i class="{{ $mod['icon'] }} mr-1"></i> {{ $mod['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body border-bottom bg-light py-2 px-3 d-flex flex-wrap justify-content-between align-items-center">
            <div class="my-1">
                <span class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                    <i class="{{ $moduleGroups[$activeGroup]['modules'][$activeModule]['icon'] }} text-primary mr-1"></i> {{ $moduleGroups[$activeGroup]['modules'][$activeModule]['name'] }} Rules
                </span>
                <span class="text-muted small ml-2 d-none d-md-inline">({{ $moduleGroups[$activeGroup]['modules'][$activeModule]['description'] }})</span>
            </div>
            <div class="d-flex align-items-center my-1">
                <div class="input-group input-group-sm mr-2" style="max-width: 250px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" id="field-search-input" class="form-control" placeholder="Search field name / label..." autocomplete="off">
                </div>
                <form action="{{ route('tools.form-validations.reset') }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Are you sure you want to reset validation rules for {{ $moduleGroups[$activeGroup]['modules'][$activeModule]['name'] }} to system defaults?');">
                    @csrf
                    <input type="hidden" name="module_key" value="{{ $activeModule }}">
                    <input type="hidden" name="group" value="{{ $activeGroup }}">
                    <input type="hidden" name="active_section" id="reset-active-section" value="{{ $activeSection }}">
                    <button type="submit" class="btn btn-xs btn-outline-danger font-weight-bold mr-2">
                        <i class="fas fa-undo mr-1"></i> Reset Defaults
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-primary font-weight-bold shadow-sm" onclick="document.getElementById('validations-form').submit();">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
            </div>
        </div>

        {{-- Level / Section Pills Navigation --}}
        @if($sections->count() > 1)
            <div class="bg-white px-3 pt-3 pb-2 border-bottom">
                <div class="d-flex align-items-center flex-wrap">
                    <span class="text-muted small font-weight-bold mr-3 mb-2 text-uppercase" style="letter-spacing: 0.05em;">
                        <i class="fas fa-layer-group text-primary mr-1"></i> Sections / Tabs:
                    </span>
                    <ul class="nav nav-pills mb-2" id="section-pills-nav">
                        <li class="nav-item mr-1">
                            <a class="nav-link btn-sm font-weight-bold section-pill {{ $activeSection === 'all' ? 'active' : '' }}"
                               href="#all" data-section-target="all">
                                <i class="fas fa-th-list mr-1"></i> All Fields
                                <span class="badge {{ $activeSection === 'all' ? 'badge-light text-primary' : 'badge-secondary' }} ml-1">{{ $fields->count() }}</span>
                            </a>
                        </li>
                        @foreach($sections as $secName => $secFields)
                            @php $secSlug = Str::slug($secName); @endphp
                            <li class="nav-item mr-1">
                                <a class="nav-link btn-sm font-weight-bold section-pill {{ $activeSection === $secSlug ? 'active' : '' }}"
                                   href="#{{ $secSlug }}" data-section-target="{{ $secSlug }}">
                                    <i class="{{ $sectionIcons[$secName] ?? 'fas fa-tag' }} mr-1"></i> {{ $secName }}
                                    <span class="badge {{ $activeSection === $secSlug ? 'badge-light text-primary' : 'badge-secondary' }} ml-1">{{ $secFields->count() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('tools.form-validations.update') }}" method="POST" id="validations-form">
            @csrf
            <input type="hidden" name="module_key" value="{{ $activeModule }}">
            <input type="hidden" name="group" value="{{ $activeGroup }}">
            <input type="hidden" name="active_section" id="form-active-section" value="{{ $activeSection }}">

            <div class="card-body p-3">
                @forelse($sections as $secName => $secFields)
                    @php $secSlug = Str::slug($secName); @endphp
                    <div class="card card-outline card-secondary mb-3 shadow-none border section-card" data-section="{{ $secSlug }}">
                        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-dark">
                                <i class="{{ $sectionIcons[$secName] ?? 'fas fa-layer-group' }} text-primary mr-1"></i> {{ $secName }}
                            </h6>
                            <span class="badge badge-secondary px-2 py-1">{{ $secFields->count() }} fields</span>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0 field-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 25%;">Field Details</th>
                                        <th style="width: 12%;" class="text-center">Required</th>
                                        <th style="width: 14%;" class="text-center">Block Future Date</th>
                                        <th style="width: 12%;" class="text-center">Readonly</th>
                                        <th style="width: 12%;" class="text-center">Unique Check</th>
                                        <th style="width: 25%;">Custom Error Alert Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($secFields as $field)
                                        @php
                                            $isDateField = in_array($field->field_type, ['date', 'datetime'], true);
                                            $isProtectedCore = in_array($field->field_name, [
                                                'name', 'branch_id', 'supplier_id', 'customer_id', 'from_branch_id', 'to_branch_id',
                                                'bill_number', 'invoice_number', 'po_number', 'item_code', 'code'
                                            ], true);
                                        @endphp
                                        <tr class="field-row" data-field-name="{{ strtolower($field->field_name) }}" data-field-label="{{ strtolower($field->field_label) }}">
                                            <td>
                                                <div class="font-weight-bold text-dark field-title">{{ $field->field_label }}</div>
                                                <code class="small text-muted">{{ $field->field_name }}</code>
                                                <span class="badge badge-light border ml-1 font-weight-normal text-uppercase" style="font-size: 10px;">{{ $field->field_type }}</span>
                                            </td>

                                            {{-- Required Toggle --}}
                                            <td class="text-center align-middle">
                                                @if($isProtectedCore)
                                                    <div class="custom-control custom-switch d-inline-block" title="System-critical identifier (Required by Database Schema)">
                                                        <input type="checkbox" class="custom-control-input" id="req_{{ $field->id }}"
                                                               name="fields[{{ $field->id }}][is_required]" value="1"
                                                               checked disabled>
                                                        <input type="hidden" name="fields[{{ $field->id }}][is_required]" value="1">
                                                        <label class="custom-control-label" for="req_{{ $field->id }}"></label>
                                                    </div>
                                                    <br><span class="badge badge-secondary" style="font-size: 9px;"><i class="fas fa-lock mr-1"></i>Core</span>
                                                @else
                                                    <div class="custom-control custom-switch d-inline-block">
                                                        <input type="checkbox" class="custom-control-input" id="req_{{ $field->id }}"
                                                               name="fields[{{ $field->id }}][is_required]" value="1"
                                                               {{ $field->is_required ? 'checked' : '' }}>
                                                        <label class="custom-control-label" for="req_{{ $field->id }}"></label>
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- Block Future Date Toggle --}}
                                            <td class="text-center align-middle">
                                                @if($isDateField)
                                                    <div class="custom-control custom-switch d-inline-block">
                                                        <input type="checkbox" class="custom-control-input" id="bfd_{{ $field->id }}"
                                                               name="fields[{{ $field->id }}][block_future_date]" value="1"
                                                               {{ $field->block_future_date ? 'checked' : '' }}>
                                                        <label class="custom-control-label" for="bfd_{{ $field->id }}"></label>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>

                                            {{-- Readonly Toggle --}}
                                            <td class="text-center align-middle">
                                                <div class="custom-control custom-switch d-inline-block">
                                                    <input type="checkbox" class="custom-control-input" id="ro_{{ $field->id }}"
                                                           name="fields[{{ $field->id }}][is_readonly]" value="1"
                                                           {{ $field->is_readonly ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="ro_{{ $field->id }}"></label>
                                                </div>
                                            </td>

                                            {{-- Unique Check Toggle --}}
                                            <td class="text-center align-middle">
                                                <div class="custom-control custom-switch d-inline-block">
                                                    <input type="checkbox" class="custom-control-input" id="uniq_{{ $field->id }}"
                                                           name="fields[{{ $field->id }}][is_unique]" value="1"
                                                           {{ $field->is_unique ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="uniq_{{ $field->id }}"></label>
                                                </div>
                                            </td>

                                            {{-- Custom Error Message Input --}}
                                            <td class="align-middle">
                                                <input type="text" class="form-control form-control-sm"
                                                       name="fields[{{ $field->id }}][custom_error_message]"
                                                       value="{{ $field->custom_error_message }}"
                                                       placeholder="Enter custom error message...">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-boxes fa-3x text-secondary mb-2 d-block"></i>
                        No fields configured for this module yet.
                    </div>
                @endforelse
            </div>

            <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
                <div class="small text-muted">
                    <i class="fas fa-info-circle text-info mr-1"></i> Toggled rules take effect immediately on forms and caching is automatically refreshed.
                </div>
                <div>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
@stop

@push('js')
<script>
$(document).ready(function () {
    function activateSection(target) {
        if (!target) target = 'all';
        target = target.replace('#', '').toLowerCase();

        // Update pills active state
        $('.section-pill').removeClass('active');
        let $activePill = $('.section-pill[data-section-target="' + target + '"]');
        if (!$activePill.length) {
            target = 'all';
            $activePill = $('.section-pill[data-section-target="all"]');
        }
        $activePill.addClass('active');

        // Update pill badges classes
        $('.section-pill').each(function () {
            let $b = $(this).find('.badge');
            if ($(this).hasClass('active')) {
                $b.removeClass('badge-secondary').addClass('badge-light text-primary');
            } else {
                $b.removeClass('badge-light text-primary').addClass('badge-secondary');
            }
        });

        // Show/hide section cards
        if (target === 'all') {
            $('.section-card').show();
        } else {
            $('.section-card').hide();
            $('.section-card[data-section="' + target + '"]').show();
        }

        // Store active section in form hidden inputs
        $('#form-active-section').val(target);
        $('#reset-active-section').val(target);
    }

    // Handle section pill clicks
    $('.section-pill').on('click', function (e) {
        e.preventDefault();
        let target = $(this).data('section-target');
        history.replaceState(null, null, '#' + target);
        activateSection(target);
    });

    // Check initial section from URL hash or server-provided activeSection
    let initialHash = window.location.hash ? window.location.hash.substring(1) : '';
    let serverSection = "{{ $activeSection }}";
    let startingSection = initialHash || serverSection || 'all';
    activateSection(startingSection);

    // Live Instant Search Filter
    $('#field-search-input').on('input', function () {
        let query = $.trim($(this).val()).toLowerCase();
        if (query.length === 0) {
            // Restore current section filter
            let currentTarget = $('.section-pill.active').data('section-target') || 'all';
            activateSection(currentTarget);
            $('.field-row').show();
            return;
        }

        // When searching, make all section cards visible so search covers everything
        $('.section-card').show();
        $('.section-card').each(function () {
            let $card = $(this);
            let matchingRows = 0;
            $card.find('.field-row').each(function () {
                let name = $(this).data('field-name') || '';
                let label = $(this).data('field-label') || '';
                if (name.indexOf(query) !== -1 || label.indexOf(query) !== -1) {
                    $(this).show();
                    matchingRows++;
                } else {
                    $(this).hide();
                }
            });

            if (matchingRows > 0) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    });
});
</script>
@endpush
