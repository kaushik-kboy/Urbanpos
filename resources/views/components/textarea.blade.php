@props(['name', 'label', 'value' => null, 'col' => 9, 'rows' => 3])

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">{{ $label }}</label>
    <div class="col-sm-{{ $col }}">
        <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" class="form-control @error($name) is-invalid @enderror">{{ old($name, $value) }}</textarea>
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
