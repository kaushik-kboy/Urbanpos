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

    <div class="field-wrapper col-md-6" data-field="is_active" data-label="Account Status" data-default-order="7">
        <div class="form-group row">
            <label for="is_active" class="col-sm-3 col-form-label font-weight-bold">Status</label>
            <div class="col-sm-9">
                <select name="is_active" id="is_active" class="form-control font-weight-bold @error('is_active') is-invalid @enderror">
                    <option value="1" {{ old('is_active', $u?->is_active ?? true) ? 'selected' : '' }} class="text-success font-weight-bold">
                        Active (Can Login)
                    </option>
                    <option value="0" {{ old('is_active', $u?->is_active ?? true) ? '' : 'selected' }} class="text-danger font-weight-bold">
                        Inactive (Login Blocked)
                    </option>
                </select>
                <small class="text-muted d-block mt-1">If inactive, user cannot log in and active sessions are terminated.</small>
                @error('is_active')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>

    <div class="field-wrapper col-md-6" data-field="shift_timings" data-label="Shift Timings" data-default-order="8">
        <div class="form-group row">
            <label class="col-sm-3 col-form-label font-weight-bold">Shift Hours</label>
            <div class="col-sm-9">
                <div class="d-flex align-items-center">
                    <div class="input-group input-group-sm mr-2">
                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold text-success"><i class="fas fa-sign-in-alt mr-1"></i> In</span></div>
                        <input type="time" name="time_in" id="time_in" 
                               value="{{ old('time_in', $u?->time_in ? substr($u->time_in, 0, 5) : '') }}" 
                               class="form-control font-weight-bold @error('time_in') is-invalid @enderror">
                    </div>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend"><span class="input-group-text font-weight-bold text-danger"><i class="fas fa-sign-out-alt mr-1"></i> Out</span></div>
                        <input type="time" name="time_out" id="time_out" 
                               value="{{ old('time_out', $u?->time_out ? substr($u->time_out, 0, 5) : '') }}" 
                               class="form-control font-weight-bold @error('time_out') is-invalid @enderror">
                    </div>
                </div>
                <small class="text-muted d-block mt-1">
                    <i class="fas fa-clock mr-1 text-primary"></i> User can only log in during shift hours. Automatic logout triggers at Time Out. Leave blank for 24/7 access (e.g. Owner).
                </small>
                @error('time_in')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
                @error('time_out')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                @enderror
            </div>
        </div>
    </div>
</div>
