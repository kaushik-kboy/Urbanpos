@props(['name', 'label', 'type' => 'text', 'value' => null, 'col' => 6, 'step' => null, 'required' => false, 'hint' => null])

@php
    $computedValue = old($name, $value);
    if ($type === 'date' && (is_null($computedValue) || $computedValue === '')) {
        $computedValue = now()->format('Y-m-d');
    }
@endphp

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">
        {{ $label }}
        @if($required || $attributes->has('required')) <span class="text-danger">*</span> @endif
    </label>
    <div class="col-sm-{{ $col }}">
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
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
        @if($hint)
            <small class="form-text text-muted"><i class="fas fa-info-circle mr-1"></i>{{ $hint }}</small>
        @endif
    </div>
</div>

