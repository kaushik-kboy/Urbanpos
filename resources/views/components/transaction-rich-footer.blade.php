@props([
    'totalId' => 'display-final-total',
    'totalLabel' => 'Final Total:',
    'itemsBadgeId' => 'total-items-badge',
    'initialItemsCount' => 0,
    'saveBtnId' => 'main-save-btn',
    'saveBtnText' => 'Save',
    'saveBtnIcon' => 'fas fa-check-circle',
    'cancelRoute' => '#',
    'resetBtnId' => 'btn-reset-form',
    'showShortcuts' => true,
    'showCurrency' => true,
    'customLeft' => null,
    'customRight' => null,
])

<div class="card-footer tx-rich-footer py-2 px-3 d-flex justify-content-between align-items-center flex-wrap" style="border-top: 2px solid #dee2e6;">
    {{-- Left: Live Items Badge, Total & Shortcuts --}}
    <div class="d-flex align-items-center flex-wrap">
        @if(!empty($itemsBadgeId))
            <div id="{{ $itemsBadgeId }}" class="d-inline-block mr-3">
                <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="font-size: 0.85rem;">{{ $initialItemsCount }} Items</span>
            </div>
        @endif

        @if(!empty($totalId))
            <div class="d-flex align-items-baseline mr-3">
                <span class="text-muted font-weight-bold mr-1" style="font-size: 0.88rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ $totalLabel }}</span>
                <span class="text-success font-weight-bold" style="font-size: 1.35rem; line-height: 1;">@if($showCurrency)₹@endif<span id="{{ $totalId }}">0.00</span></span>
            </div>
        @endif

        @if($showShortcuts)
            <div class="d-none d-lg-flex align-items-center text-muted pl-2 border-left" style="font-size: 0.78rem;">
                <span class="mr-2"><kbd class="bg-white text-dark border px-1 shadow-xs">F2</kbd> Search Item</span>
                <span class="mr-2"><kbd class="bg-white text-dark border px-1 shadow-xs">Tab</kbd> Next Field</span>
                <span><kbd class="bg-white text-dark border px-1 shadow-xs">Enter</kbd> Confirm</span>
            </div>
        @endif

        {{ $customLeft ?? '' }}
    </div>

    {{-- Right: Reset, Cancel, Save Actions --}}
    <div class="d-flex align-items-center">
        {{ $customRight ?? '' }}

        @if(!empty($resetBtnId))
            <button type="button" id="{{ $resetBtnId }}" class="btn btn-warning btn-sm font-weight-bold btn-reset-form mr-2 shadow-xs" title="Reset all form fields">
                <i class="fas fa-undo mr-1"></i> Reset Form
            </button>
        @endif

        @if(!empty($cancelRoute) && $cancelRoute !== '#')
            <a href="{{ $cancelRoute }}" class="btn btn-outline-secondary btn-sm font-weight-bold mr-2 shadow-xs">
                <i class="fas fa-times mr-1"></i> Cancel
            </a>
        @endif

        <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4 shadow-sm" id="{{ $saveBtnId }}">
            <i class="{{ $saveBtnIcon }} mr-1"></i> {{ $saveBtnText }}
        </button>
    </div>
</div>
