@props([
    'module' => 'Customer',
    'model' => null,
    'colClass' => 'col-md-4 col-sm-6 col-12 mb-3',
    'showHeader' => true,
    'headerTitle' => 'Additional Custom Attributes',
    'cardStyle' => false,
])

@php
    $customFields = \App\Models\CustomFieldDefinition::forModule($module)->active()->get();
@endphp

@if($customFields->isNotEmpty())
    @if($cardStyle)
        <div class="card card-outline card-secondary mb-3 shadow-none border">
            @if($showHeader)
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title font-weight-bold text-dark mb-0" style="font-size: 0.95rem;">
                        <i class="fas fa-sliders-h text-primary mr-1"></i> {{ $headerTitle }}
                    </h5>
                    <a href="{{ route('tools.custom-fields.index', ['tab' => $module]) }}" target="_blank" class="badge badge-light border text-muted" title="Manage Custom Fields in Settings">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            @endif
            <div class="card-body py-3">
                <div class="row">
    @else
        @if($showHeader)
            <div class="col-12 mb-2 mt-2">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                    <h6 class="font-weight-bold text-dark mb-0" style="font-size: 0.9rem;">
                        <i class="fas fa-sliders-h text-primary mr-1"></i> {{ $headerTitle }}
                    </h6>
                    <a href="{{ route('tools.custom-fields.index', ['tab' => $module]) }}" target="_blank" class="badge badge-light border text-muted" style="font-size: 0.75rem;" title="Manage Custom Fields">
                        <i class="fas fa-cog mr-1"></i> Configure Fields
                    </a>
                </div>
            </div>
        @endif
        <div class="col-12 p-0">
            <div class="row px-2">
    @endif

    @foreach($customFields as $field)
        @php
            $currentVal = old('custom_fields.' . $field->field_key, $model?->getCustomFieldValue($field->field_key) ?? $field->default_value);
        @endphp
        <div class="{{ $colClass }}">
            @if($field->field_type === 'checkbox')
                <div class="custom-control custom-switch mt-4 pt-1">
                    <input type="checkbox"
                           class="custom-control-input"
                           id="cf_{{ $module }}_{{ $field->field_key }}"
                           name="custom_fields[{{ $field->field_key }}]"
                           value="1"
                           {{ $currentVal ? 'checked' : '' }}>
                    <label class="custom-control-label font-weight-semibold text-dark" for="cf_{{ $module }}_{{ $field->field_key }}">
                        {{ $field->field_name }}
                        @if($field->is_required) <span class="text-danger">*</span> @endif
                    </label>
                </div>
            @else
                <label class="font-weight-semibold text-dark mb-1" style="font-size: 0.85rem;" for="cf_{{ $module }}_{{ $field->field_key }}">
                    {{ $field->field_name }}
                    @if($field->is_required) <span class="text-danger font-weight-bold">*</span> @endif
                </label>

                @if($field->field_type === 'select')
                    <select class="form-control form-control-sm @error('custom_fields.' . $field->field_key) is-invalid @enderror"
                            id="cf_{{ $module }}_{{ $field->field_key }}"
                            name="custom_fields[{{ $field->field_key }}]"
                            {{ $field->is_required ? 'required' : '' }}>
                        <option value="">-- Select {{ $field->field_name }} --</option>
                        @foreach($field->options ?? [] as $opt)
                            <option value="{{ $opt }}" {{ (string)$currentVal === (string)$opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                @elseif($field->field_type === 'textarea')
                    <textarea class="form-control form-control-sm @error('custom_fields.' . $field->field_key) is-invalid @enderror"
                              id="cf_{{ $module }}_{{ $field->field_key }}"
                              name="custom_fields[{{ $field->field_key }}]"
                              rows="2"
                              placeholder="Enter {{ strtolower($field->field_name) }}"
                              {{ $field->is_required ? 'required' : '' }}>{{ $currentVal }}</textarea>
                @elseif($field->field_type === 'date')
                    <input type="date"
                           class="form-control form-control-sm @error('custom_fields.' . $field->field_key) is-invalid @enderror"
                           id="cf_{{ $module }}_{{ $field->field_key }}"
                           name="custom_fields[{{ $field->field_key }}]"
                           value="{{ $currentVal }}"
                           {{ $field->is_required ? 'required' : '' }}>
                @elseif($field->field_type === 'number')
                    <input type="number"
                           step="any"
                           class="form-control form-control-sm @error('custom_fields.' . $field->field_key) is-invalid @enderror"
                           id="cf_{{ $module }}_{{ $field->field_key }}"
                           name="custom_fields[{{ $field->field_key }}]"
                           value="{{ $currentVal }}"
                           placeholder="Enter {{ strtolower($field->field_name) }}"
                           {{ $field->is_required ? 'required' : '' }}>
                @else
                    <input type="text"
                           class="form-control form-control-sm @error('custom_fields.' . $field->field_key) is-invalid @enderror"
                           id="cf_{{ $module }}_{{ $field->field_key }}"
                           name="custom_fields[{{ $field->field_key }}]"
                           value="{{ $currentVal }}"
                           placeholder="Enter {{ strtolower($field->field_name) }}"
                           {{ $field->is_required ? 'required' : '' }}>
                @endif

                @error('custom_fields.' . $field->field_key)
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            @endif
        </div>
    @endforeach

    @if($cardStyle)
                </div>
            </div>
        </div>
    @else
            </div>
        </div>
    @endif
@endif
