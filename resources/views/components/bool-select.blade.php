@props(['name', 'label', 'value' => true, 'col' => 6, 'trueLabel' => 'Active', 'falseLabel' => 'Inactive'])

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">{{ $label }}</label>
    <div class="col-sm-{{ $col }}">
        @php $current = old($name, $value) ? '1' : '0'; @endphp
        <select id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror">
            <option value="1" @selected($current === '1')>{{ $trueLabel }}</option>
            <option value="0" @selected($current === '0')>{{ $falseLabel }}</option>
        </select>
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
