@props(['name', 'label', 'type' => 'text', 'value' => null, 'col' => 6, 'step' => null, 'required' => false, 'hint' => null, 'addon' => null])

@php
    $computedValue = old($name, $value);
    if ($type === 'date' && (is_null($computedValue) || $computedValue === '')) {
        $computedValue = now()->format('Y-m-d');
    }
    $hasAddon = !empty($addon) || (isset($slot) && !empty((string) $slot));
@endphp

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">
        {{ $label }}
        @if($required || $attributes->has('required')) <span class="text-danger">*</span> @endif
    </label>
    <div class="col-sm-{{ $col }}">
        @if($type === 'date')
            <div class="input-group urbanpos-date-group" data-date-field-wrapper="true">
                <input
                    type="text"
                    id="{{ $name }}"
                    name="{{ $name }}"
                    @if($required || $attributes->has('required')) required @endif
                    {{ $attributes->merge(['class' => 'form-control datepicker ' . ($errors->has($name) ? 'is-invalid' : '')]) }}
                    value="{{ $computedValue }}"
                    placeholder="DD-MM-YYYY (e.g. 10042026)"
                    autocomplete="off"
                    data-date-field="true"
                >
                <div class="input-group-append">
                    @if($hasAddon)
                        {!! $addon !!}{{ $slot }}
                    @else
                        <button type="button" class="btn btn-outline-secondary btn-date-mode-toggle d-flex align-items-center py-1 px-2" style="border-color: #ced4da; background-color: #f8f9fa;">
                            <span class="date-mode-label mr-2"><i class="fas fa-keyboard text-primary mr-1"></i><span class="small font-weight-bold text-dark">Manual</span></span>
                            <span class="btn-open-datepicker text-muted mr-2" title="Click to Open Calendar Picker"><i class="fas fa-calendar-alt"></i></span>
                            <span class="btn-date-settings-modal text-secondary" title="Configure Date Format & Entry Mode"><i class="fas fa-cog"></i></span>
                        </button>
                    @endif
                </div>
            </div>
        @elseif($hasAddon)
            <div class="input-group">
                <input
                    type="{{ $type }}"
                    id="{{ $name }}"
                    name="{{ $name }}"
                    @if($step) step="{{ $step }}" @endif
                    @if($required || $attributes->has('required')) required @endif
                    {{ $attributes->merge(['class' => 'form-control ' . ($errors->has($name) ? 'is-invalid' : '')]) }}
                    value="{{ $computedValue }}"
                    autocomplete="off"
                >
                <div class="input-group-append">
                    {!! $addon !!}{{ $slot }}
                </div>
            </div>
        @else
            <input
                type="{{ $type }}"
                id="{{ $name }}"
                name="{{ $name }}"
                @if($step) step="{{ $step }}" @endif
                @if($required || $attributes->has('required')) required @endif
                {{ $attributes->merge(['class' => 'form-control ' . ($errors->has($name) ? 'is-invalid' : '')]) }}
                value="{{ $computedValue }}"
                autocomplete="off"
            >
        @endif
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
        @if($hint)
            <small class="form-text text-muted"><i class="fas fa-info-circle mr-1"></i>{{ $hint }}</small>
        @endif
    </div>
</div>

