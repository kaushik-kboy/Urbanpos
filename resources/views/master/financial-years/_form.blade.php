@php
    $fy = $financialYear ?? null;
@endphp

<x-field name="name" label="Name" :value="$fy->name ?? ''" />
<x-field name="start_date" label="Start Date" type="date" :value="optional($fy?->start_date)->toDateString()" />
<x-field name="end_date" label="End Date" type="date" :value="optional($fy?->end_date)->toDateString()" />
