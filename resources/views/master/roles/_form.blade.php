@php
    $r = $role ?? null;
    $assignedSet = collect($assignedPermissions ?? [])->flip();
    $isProtected = $isProtected ?? false;
@endphp

<div class="row">
    <!-- Role Name Input Header -->
    <div class="col-md-6 mb-3">
        <label for="role-name" class="font-weight-bold text-dark">
            Role Name <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-user-tag text-primary"></i></span>
            </div>
            <input type="text" id="role-name" name="name" 
                   class="form-control font-weight-bold @error('name') is-invalid @enderror" 
                   value="{{ old('name', $r?->name) }}" 
                   placeholder="e.g. Accountant, Supervisor, Salesman" 
                   {{ $isProtected ? 'readonly' : 'required' }} autofocus>
            @error('name')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        @if($isProtected)
            <small class="text-muted"><i class="fas fa-lock mr-1 text-warning"></i> Core system role names are protected and cannot be renamed.</small>
        @else
            <small class="text-muted">Enter a clear, descriptive title for this staff access role.</small>
        @endif
    </div>

    <!-- Quick Actions & Stats Toolbar -->
    <div class="col-md-6 mb-3">
        <label class="font-weight-bold text-dark d-block">Quick Selection Controls</label>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-success btn-sm font-weight-bold mr-2 mb-1" id="btn-perm-select-all">
                <i class="fas fa-check-double mr-1"></i> Select All (Global)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold mr-2 mb-1" id="btn-perm-deselect-all">
                <i class="fas fa-times mr-1"></i> Deselect All
            </button>
            <span class="badge badge-primary px-3 py-2 font-weight-bold mb-1 shadow-sm" style="font-size: 0.9rem;" id="perm-selected-counter">
                0 Selected
            </span>
        </div>
        <!-- Live Filter Search Box -->
        <div class="mt-2">
            <div class="input-group input-group-sm">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-filter text-muted"></i></span></div>
                <input type="text" id="perm-search-filter" class="form-control" placeholder="Quick filter modules (e.g. sales, stock, invoice, voucher)...">
            </div>
        </div>
    </div>
</div>

<hr class="my-3">

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="font-weight-bold text-dark mb-0">
        <i class="fas fa-th-list text-primary mr-2"></i> Permissions Matrix
    </h5>
    <span class="small text-muted">
        Tick checkboxes to grant specific permissions to this role.
    </span>
</div>

<!-- Permission Groups Accordions / Cards -->
<div class="row" id="permission-groups-container">
    @foreach ($permissionGroups as $groupKey => $group)
        <div class="col-12 mb-4 perm-group-wrapper" data-group-key="{{ $groupKey }}">
            <div class="card card-outline card-secondary shadow-sm mb-0">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                    <div class="d-flex align-items-center">
                        <i class="{{ $group['icon'] }} fa-lg mr-2"></i>
                        <h6 class="font-weight-bold text-dark mb-0 perm-group-title" style="font-size: 1.05rem;">
                            {{ $group['label'] }}
                        </h6>
                    </div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold btn-group-toggle-all mr-1" data-group="{{ $groupKey }}">
                            <i class="fas fa-check mr-1"></i> Toggle All
                        </button>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 perm-table">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 35%;" class="pl-3">Module / Entity</th>
                                    <th>Available Actions &amp; Permissions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['modules'] as $moduleKey => $module)
                                    <tr class="perm-module-row" data-module-name="{{ strtolower($module['label']) }} {{ strtolower($moduleKey) }}">
                                        <td class="align-middle pl-3 font-weight-bold text-dark">
                                            <span class="d-block">{{ $module['label'] }}</span>
                                            <code class="small text-muted font-weight-normal">{{ $moduleKey }}</code>
                                        </td>
                                        <td class="align-middle py-2">
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                @foreach ($module['actions'] as $actionKey => $actionLabel)
                                                    @php
                                                        $permName = "{$moduleKey}.{$actionKey}";
                                                        $isChecked = old("permissions") 
                                                            ? in_array($permName, old("permissions", [])) 
                                                            : $assignedSet->has($permName);
                                                    @endphp
                                                    <div class="custom-control custom-checkbox mr-4 my-1">
                                                        <input type="checkbox" 
                                                               class="custom-control-input perm-checkbox perm-group-{{ $groupKey }}" 
                                                               id="perm_{{ Str::slug($permName, '_') }}" 
                                                               name="permissions[]" 
                                                               value="{{ $permName }}"
                                                               {{ $isChecked ? 'checked' : '' }}>
                                                        <label class="custom-control-label font-weight-normal cursor-pointer text-dark" for="perm_{{ Str::slug($permName, '_') }}">
                                                            @if($actionKey === 'create')
                                                                <span class="badge badge-success px-1 py-0 mr-1"><i class="fas fa-plus"></i></span>
                                                            @elseif($actionKey === 'edit' || $actionKey === 'apply')
                                                                <span class="badge badge-info px-1 py-0 mr-1"><i class="fas fa-pen"></i></span>
                                                            @elseif($actionKey === 'cancel' || $actionKey === 'reject')
                                                                <span class="badge badge-danger px-1 py-0 mr-1"><i class="fas fa-trash"></i></span>
                                                            @elseif($actionKey === 'approve' || $actionKey === 'receive')
                                                                <span class="badge badge-primary px-1 py-0 mr-1"><i class="fas fa-check"></i></span>
                                                            @elseif($actionKey === 'lock' || $actionKey === 'close')
                                                                <span class="badge badge-warning px-1 py-0 mr-1"><i class="fas fa-lock"></i></span>
                                                            @elseif($actionKey === 'open' || $actionKey === 'reopen')
                                                                <span class="badge badge-success px-1 py-0 mr-1"><i class="fas fa-unlock"></i></span>
                                                            @endif
                                                            {{ $actionLabel }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@push('js')
<script>
$(document).ready(function () {
    function updateCounter() {
        const total = $('.perm-checkbox').length;
        const checked = $('.perm-checkbox:checked').length;
        $('#perm-selected-counter').text(`${checked} of ${total} Selected`);
    }

    // Checkbox change updates counter
    $(document).on('change', '.perm-checkbox', function () {
        updateCounter();
    });

    // Global Select All
    $('#btn-perm-select-all').on('click', function () {
        $('.perm-checkbox:visible').prop('checked', true);
        updateCounter();
    });

    // Global Deselect All
    $('#btn-perm-deselect-all').on('click', function () {
        $('.perm-checkbox:visible').prop('checked', false);
        updateCounter();
    });

    // Module Group Toggle All
    $('.btn-group-toggle-all').on('click', function () {
        const group = $(this).data('group');
        const $boxes = $(`.perm-group-${group}:visible`);
        const allChecked = $boxes.filter(':checked').length === $boxes.length;
        $boxes.prop('checked', !allChecked);
        updateCounter();
    });

    // Real-time Search Filter
    $('#perm-search-filter').on('input', function () {
        const q = $.trim($(this).val()).toLowerCase();
        if (!q) {
            $('.perm-module-row').show();
            $('.perm-group-wrapper').show();
            return;
        }

        $('.perm-group-wrapper').each(function () {
            const $group = $(this);
            let anyVisibleInGroup = false;

            $group.find('.perm-module-row').each(function () {
                const $row = $(this);
                const text = ($row.data('module-name') || '') + ' ' + $row.text().toLowerCase();
                if (text.includes(q)) {
                    $row.show();
                    anyVisibleInGroup = true;
                } else {
                    $row.hide();
                }
            });

            if (anyVisibleInGroup) {
                $group.show();
            } else {
                $group.hide();
            }
        });
    });

    updateCounter();
});
</script>
@endpush
