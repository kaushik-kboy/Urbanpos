@php
    $u = $user ?? null;
    $currentRole = $u?->roles->first()?->name;
@endphp

<x-field name="name" label="Name" :value="$u->name ?? ''" />
<x-field name="email" label="Email" type="email" :value="$u->email ?? ''" />

<div class="form-group row">
    <label for="password" class="col-sm-3 col-form-label">
        Password @if($u) <span class="text-muted small">(leave blank to keep current)</span> @endif
    </label>
    <div class="col-sm-6">
        <input type="password" id="password" name="password" autocomplete="new-password"
               class="form-control @error('password') is-invalid @enderror">
        @error('password')
            <span class="invalid-feedback d-block">{{ $message }}</span>
        @enderror
    </div>
</div>

<div class="form-group row">
    <label for="password_confirmation" class="col-sm-3 col-form-label">Confirm Password</label>
    <div class="col-sm-6">
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"
               class="form-control">
    </div>
</div>

<x-select name="branch_id" label="Branch" :options="$branches" :selected="$u->branch_id ?? null" placeholder="All Branches (Owner-level access)" />
<x-select name="role" label="Role" :options="$roles" :selected="$currentRole" placeholder="-- Select Role --" />
