@if (session('import_result'))
    @php $result = session('import_result'); @endphp
    <div class="alert {{ $result['created'] || $result['updated'] ? 'alert-success' : 'alert-warning' }}">
        <strong>Import finished:</strong>
        {{ $result['created'] }} created, {{ $result['updated'] }} updated, {{ $result['skipped'] }} skipped.
        @if (!empty($result['errors']))
            <ul class="mb-0 mt-2 pl-3">
                @foreach ($result['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
                @if ($result['error_count'] > count($result['errors']))
                    <li>...and {{ $result['error_count'] - count($result['errors']) }} more.</li>
                @endif
            </ul>
        @endif
    </div>
@endif
