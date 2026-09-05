@props(['active'])

@if($active)
    <span class="badge badge-success">Active</span>
@else
    <span class="badge badge-secondary">Inactive</span>
@endif
