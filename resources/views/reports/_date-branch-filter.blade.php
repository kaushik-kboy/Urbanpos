<form method="GET" class="form-inline mb-3">
    <label class="mr-2 font-weight-bold small text-muted">From</label>
    <div class="input-group input-group-sm mr-3">
        <input type="text" name="from" value="{{ $from }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
        <div class="input-group-append">
            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
        </div>
    </div>
    <label class="mr-2 font-weight-bold small text-muted">To</label>
    <div class="input-group input-group-sm mr-3">
        <input type="text" name="to" value="{{ $to }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
        <div class="input-group-append">
            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
        </div>
    </div>
    <label class="mr-2">Location</label>
    <select name="branch_id" class="form-control form-control-sm mr-3">
        <option value="">All Location</option>
        @foreach ($branches as $id => $name)
            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
    <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm mr-1"><i class="fas fa-undo"></i> Reset</a>
    <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print Report</button>
</form>
