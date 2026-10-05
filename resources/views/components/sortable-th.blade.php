@props([
    'column',
    'label' => null,
    'align' => 'left',
    'dataColKey' => null,
])

@php
    $currentSort = (string) request('sort', '');
    $currentDir = strtolower((string) request('direction', ''));
    $isCurrent = ($currentSort === $column);

    // Build the 3-state toggle URL:
    // 1st click (inactive) -> asc
    // 2nd click (asc)      -> desc
    // 3rd click (desc)     -> reset to default
    $queryParams = request()->except(['page']);

    if (!$isCurrent) {
        $queryParams['sort'] = $column;
        $queryParams['direction'] = 'asc';
        $tooltip = 'Sort by ' . ($label ?? $column) . ' (Ascending)';
    } elseif ($currentDir === 'asc') {
        $queryParams['sort'] = $column;
        $queryParams['direction'] = 'desc';
        $tooltip = 'Sorted Ascending. Click for Descending';
    } else {
        unset($queryParams['sort'], $queryParams['direction']);
        $tooltip = 'Sorted Descending. Click to reset to default order';
    }

    $url = request()->url() . (!empty($queryParams) ? ('?' . http_build_query($queryParams)) : '');
    $finalColKey = $dataColKey ?? $column;
@endphp

<th {{ $attributes->merge(['class' => 'user-select-none' . ($align === 'right' ? ' text-right' : ($align === 'center' ? ' text-center' : ''))]) }}
    data-col-key="{{ $finalColKey }}">
    <a href="{{ $url }}" 
       class="text-dark text-decoration-none d-inline-flex align-items-center {{ $align === 'right' ? 'justify-content-end' : ($align === 'center' ? 'justify-content-center' : '') }}"
       title="{{ $tooltip }}"
       style="cursor: pointer;">
        <span>{{ $slot->isEmpty() ? $label : $slot }}</span>
        @if(!$isCurrent)
            <i class="fas fa-sort text-muted ml-1" style="font-size: 0.85em; opacity: 0.45;"></i>
        @elseif($currentDir === 'asc')
            <i class="fas fa-sort-up text-primary ml-1" style="font-size: 0.95em;"></i>
        @else
            <i class="fas fa-sort-down text-primary ml-1" style="font-size: 0.95em;"></i>
        @endif
    </a>
</th>
