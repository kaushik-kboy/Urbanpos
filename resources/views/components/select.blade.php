@props(['name', 'label', 'options' => [], 'selected' => null, 'col' => 6, 'placeholder' => null, 'addon' => null, 'required' => false])

@php
    $isRequired = $required || $attributes->has('required');
    if (in_array($name, ['branch_id', 'from_branch_id']) && (empty($selected) || $selected === '')) {
        $selected = session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1));
    }
    $hasAddon = !empty($addon) || (isset($slot) && !empty((string) $slot));
@endphp

<div class="form-group row">
    <label for="{{ $name }}" class="col-sm-3 col-form-label">
        {{ $label }}
        @if($isRequired) <span class="text-danger">*</span> @endif
    </label>
    <div class="col-sm-{{ $col }}">
        @if($hasAddon)
            <div class="d-flex align-items-center">
                <div class="flex-grow-1 mr-2" style="min-width: 0;">
                    <select id="{{ $name }}" name="{{ $name }}" class="form-control select2 @error($name) is-invalid @enderror" @if($placeholder) data-placeholder="{{ $placeholder }}" @endif @if($isRequired) required @endif {{ $attributes->except('required') }}>
                        @if($placeholder)
                            <option value="">{{ $placeholder }}</option>
                        @endif
                        @foreach ($options as $value => $text)
                            <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-shrink-0">
                    {!! $addon !!}{{ $slot }}
                </div>
            </div>
        @else
            <select id="{{ $name }}" name="{{ $name }}" class="form-control select2 @error($name) is-invalid @enderror" @if($placeholder) data-placeholder="{{ $placeholder }}" @endif @if($isRequired) required @endif {{ $attributes->except('required') }}>
                @if($placeholder)
                    <option value="">{{ $placeholder }}</option>
                @endif
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $text }}</option>
                @endforeach
            </select>
        @endif
        @error($name)
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

