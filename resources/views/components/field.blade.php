@props(['name', 'label', 'type' => 'text', 'value' => null, 'col' => 6, 'step' => null])

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">{{ $label }}</label>
    <div class="col-sm-{{ $col }}">
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            @if($step) step="{{ $step }}" @endif
            class="form-control @error($name) is-invalid @enderror"
            value="{{ old($name, $value) }}"
        >
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
