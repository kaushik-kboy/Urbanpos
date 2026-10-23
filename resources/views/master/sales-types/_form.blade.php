<div class="form-group mb-3">
    <label for="branch_id" class="font-weight-bold">Branch (Location) <span class="text-danger">*</span></label>
    <select name="branch_id" id="branch_id" class="form-control select2" required>
        <option value="">-- Select Branch --</option>
        @if(isset($branches))
            @foreach($branches as $bId => $bName)
                <option value="{{ $bId }}" @selected(old('branch_id', $salesType->branch_id ?? '') == $bId)>{{ $bName }}</option>
            @endforeach
        @endif
    </select>
    <small class="form-text text-muted">Branch select karein jahan yeh sales bill type use hoga (e.g., Motera: Retail, Home, Spa | Satellite: B2B, IPC, Home, Retail).</small>
</div>

<x-field name="name" label="Sales Bill Type Name" :value="$salesType->name ?? ''" required placeholder="e.g. Retail, Home, Spa, B2B, IPC" />
<x-field name="code" label="Short Code" :value="$salesType->code ?? ''" placeholder="e.g. RET, HOM, SPA, B2B, IPC" />
<x-field name="description" label="Description" :value="$salesType->description ?? ''" placeholder="Optional description..." />
<x-bool-select name="status" label="Status" :value="$salesType->status ?? true" />
