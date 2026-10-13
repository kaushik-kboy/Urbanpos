@props(['name', 'label', 'value' => true, 'col' => 6, 'trueLabel' => 'Active', 'falseLabel' => 'Inactive', 'placeholder' => null])

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">
        {{ $label }}
        @if($attributes->has('required')) <span class="text-danger">*</span> @endif
    </label>
    <div class="col-sm-{{ $col }}">
        @php
            $raw = old($name, $placeholder !== null ? $value : $value);
            $hasValue = $raw !== null && $raw !== '';
            $current = $hasValue ? ((string)$raw === '1' || $raw === true ? '1' : '0') : '';
        @endphp
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'form-control ' . ($errors->has($name) ? 'is-invalid' : '')]) }}>
            @if($placeholder !== null || !$hasValue)
                <option value="" @selected(!$hasValue)>{{ $placeholder ?: 'Select ' . $label }}</option>
            @endif
            <option value="1" @selected($hasValue && $current === '1')>{{ $trueLabel }}</option>
            <option value="0" @selected($hasValue && $current === '0')>{{ $falseLabel }}</option>
        </select>
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
