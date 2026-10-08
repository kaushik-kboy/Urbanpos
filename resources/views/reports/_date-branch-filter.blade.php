<form method="GET" class="form-inline mb-3">
    <label class="mr-2">From</label>
    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm mr-3">
    <label class="mr-2">To</label>
    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm mr-3">
    <label class="mr-2">Location</label>
    <select name="branch_id" class="form-control form-control-sm mr-3" {{ count($branches) <= 1 ? 'readonly style=pointer-events:none;background:#f4f6f9;' : '' }}>
        @if(count($branches) > 1)
            <option value="all" {{ (!request('branch_id') || request('branch_id') === 'all') ? 'selected' : '' }}>All Locations</option>
        @endif
        @foreach ($branches as $id => $name)
            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
    <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm mr-1"><i class="fas fa-undo"></i> Reset</a>
    <button type="submit" name="export" value="excel" class="btn btn-success btn-sm font-weight-bold mr-1 shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</button>
    <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print Report</button>
</form>
