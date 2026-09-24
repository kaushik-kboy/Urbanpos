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
                        <button type="button" class="btn btn-outline-secondary btn-open-datepicker py-1 px-2" title="Click to Open Calendar Picker" style="border-color: #ced4da; background-color: #f8f9fa;">
                            <i class="fas fa-calendar-alt text-primary" style="pointer-events: none;"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-date-settings-modal py-1 px-2" data-toggle="modal" data-target="#urbanpos-date-settings-modal" title="Date Settings: Hath se likhna / Calendar / Formats" style="border-color: #ced4da; background-color: #e9ecef;">
                            <i class="fas fa-cog text-dark" style="pointer-events: none;"></i>
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

