@props(['active'])

@if($active)
    <span class="badge-pill-modern badge-pill-success"><span class="pulse-dot"></span> Active</span>
@else
    <span class="badge-pill-modern badge-pill-secondary"><span class="dot-muted"></span> Inactive</span>
@endif
