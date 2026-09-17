@extends('adminlte::page')

@section('title', 'Form Field Validations')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-check-double text-primary mr-2"></i>Form Field Validations</h1>
            <p class="text-muted small mb-0">Customize and toggle field rules (Required, Future Date Block, Readonly, Unique) and error messages per page.</p>
        </div>
    </div>
@stop

@section('content')
    <style>
        .custom-switch .custom-control-label::before {
            height: 1.5rem;
            width: 2.75rem;
            border-radius: 1rem;
        }
        .custom-switch .custom-control-label::after {
            width: calc(1.5rem - 4px);
            height: calc(1.5rem - 4px);
            border-radius: calc(1rem - (1.5rem / 2));
        }
        .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
            transform: translateX(1.25rem);
        }
        .field-row:hover {
            background-color: #f8f9fa;
        }
        .nav-pills .nav-link.active {
            background-color: #007bff !important;
            color: #fff !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .nav-pills .nav-link {
            font-weight: 600;
            color: #495057;
            padding: 0.6rem 1.2rem;
            border-radius: 0.4rem;
            margin-right: 0.4rem;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }
    </style>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Module Selector Navigation Tabs --}}
    <div class="mb-3">
        <ul class="nav nav-pills">
            @foreach($modules as $key => $mod)
                <li class="nav-item">
                    <a class="nav-link {{ $activeModule === $key ? 'active' : '' }}" href="{{ route('tools.form-validations.index', ['module' => $key]) }}">
                        <i class="{{ $mod['icon'] }} mr-1"></i> {{ $mod['name'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <div>
                <h5 class="card-title font-weight-bold mb-0">
                    <i class="{{ $modules[$activeModule]['icon'] }} text-primary mr-1"></i> {{ $modules[$activeModule]['name'] }} Rules
                </h5>
                <span class="text-muted small ml-2 d-none d-md-inline">({{ $modules[$activeModule]['description'] }})</span>
            </div>
            <div>
                <form action="{{ route('tools.form-validations.reset') }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to reset validation rules for {{ $modules[$activeModule]['name'] }} to system defaults?');">
                    @csrf
                    <input type="hidden" name="module_key" value="{{ $activeModule }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-undo mr-1"></i> Reset to Defaults
                    </button>
                </form>
            </div>
        </div>

        <form action="{{ route('tools.form-validations.update') }}" method="POST">
            @csrf
            <input type="hidden" name="module_key" value="{{ $activeModule }}">

            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 25%;">Field Name & Type</th>
                            <th style="width: 12%;" class="text-center">Required</th>
                            <th style="width: 14%;" class="text-center">Block Future Date</th>
                            <th style="width: 12%;" class="text-center">Readonly</th>
                            <th style="width: 12%;" class="text-center">Unique Check</th>
                            <th style="width: 25%;">Custom Error Alert Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fields as $field)
                            @php
                                $isDateField = in_array($field->field_type, ['date', 'datetime'], true);
                            @endphp
                            <tr class="field-row">
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $field->field_label }}</div>
                                    <code class="small text-muted">{{ $field->field_name }}</code>
                                    <span class="badge badge-secondary ml-1 font-weight-normal text-uppercase" style="font-size: 10px;">{{ $field->field_type }}</span>
                                </td>

                                {{-- Required Toggle --}}
                                <td class="text-center align-middle">
                                    <div class="custom-control custom-switch d-inline-block">
                                        <input type="checkbox" class="custom-control-input" id="req_{{ $field->id }}"
                                               name="fields[{{ $field->id }}][is_required]" value="1"
                                               {{ $field->is_required ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="req_{{ $field->id }}"></label>
                                    </div>
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
                                        <span class="text-muted small">N/A</span>
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

                                {{-- Custom Error Alert Message Input --}}
                                <td class="align-middle">
                                    <input type="text" class="form-control form-control-sm"
                                           name="fields[{{ $field->id }}][custom_error_message]"
                                           value="{{ $field->custom_error_message }}"
                                           placeholder="Enter custom error message (optional)...">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No fields configured for this module yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
