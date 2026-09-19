@php
    $u = $user ?? null;
    $currentRole = $u?->roles->first()?->name;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-user-shield mr-1 text-primary"></i> User Account Details</h6>
    <x-form-layout-customizer
        form-key="master_users.general"
        container-id="user-fields-grid"
        title="Customize User Form Layout"
    />
</div>

<div class="row g-2 form-fields-grid" id="user-fields-grid">
    <div class="field-wrapper col-md-6" data-field="name" data-label="Name" data-default-order="1" data-core="1">
        <x-field name="name" label="Name" :value="$u->name ?? ''" required />
    </div>
    <div class="field-wrapper col-md-6" data-field="email" data-label="Email" data-default-order="2" data-core="1">
        <x-field name="email" label="Email" type="email" :value="$u->email ?? ''" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="password" data-label="Password" data-default-order="3" data-core="1">
        <div class="form-group row">
            <label for="password" class="col-sm-3 col-form-label">
                Password @if($u) <span class="text-muted small">(blank=keep)</span> @endif
            </label>
            <div class="col-sm-9">
                <input type="password" id="password" name="password" autocomplete="new-password"
                       class="form-control @error('password') is-invalid @enderror">
                @error('password')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    <div class="field-wrapper col-md-6" data-field="password_confirmation" data-label="Confirm Password" data-default-order="4" data-core="1">
        <div class="form-group row">
            <label for="password_confirmation" class="col-sm-3 col-form-label">Confirm</label>
            <div class="col-sm-9">
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
                       class="form-control">
            </div>
        </div>
    </div>

    <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Branch" data-default-order="5">
        <x-select name="branch_id" label="Branch" :options="$branches" :selected="$u->branch_id ?? null" placeholder="All Branches (Owner-level access)" />
    </div>
    <div class="field-wrapper col-md-6" data-field="role" data-label="Role" data-default-order="6" data-core="1">
        <x-select name="role" label="Role" :options="$roles" :selected="$currentRole" placeholder="-- Select Role --" />
    </div>
</div>
