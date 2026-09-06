@props(['options' => [10, 20, 50, 100], 'current' => 20])

<form method="GET" class="form-inline d-inline-flex align-items-center per-page-form">
    @foreach (request()->except(['per_page', 'page']) as $key => $value)
        @if (is_array($value))
            @foreach ($value as $arrayValue)
                <input type="hidden" name="{{ $key }}[]" value="{{ $arrayValue }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <label for="per_page" class="mr-2 mb-0 text-muted">Show</label>
    <select name="per_page" id="per_page" class="form-control form-control-sm" style="width: auto" onchange="this.form.submit()">
        @foreach ($options as $option)
            <option value="{{ $option }}" @selected((int) $current === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <span class="ml-2 text-muted">per page</span>
</form>
