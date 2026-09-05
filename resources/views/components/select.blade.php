@props(['name', 'label', 'options' => [], 'selected' => null, 'col' => 6, 'placeholder' => null])

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">{{ $label }}</label>
    <div class="col-sm-{{ $col }}">
        <select id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror">
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
            @endforeach
        </select>
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>
