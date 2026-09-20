{{-- Dashboard Customizer Modal --}}
<div class="modal fade" id="dashboardCustomizerModal" tabindex="-1" role="dialog" aria-labelledby="dashboardCustomizerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-gradient-dark text-white py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="mr-3 bg-white text-dark rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;">
                        <i class="fas fa-sliders-h fa-lg text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="dashboardCustomizerModalLabel">
                            Customize Your Dashboard
                        </h5>
                        <small class="text-white-50">Personalize your fast-action buttons and visible widgets (Role-Protected)</small>
                    </div>
                </div>
                <button type="button" class="close text-white opacity-75" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="dashboardCustomizerForm" action="{{ route('user.dashboard-preferences.save') }}" method="POST">
                @csrf
                <div class="modal-body p-4 bg-light">
                    {{-- Nav Tabs --}}
                    <ul class="nav nav-pills nav-fill mb-3 bg-white p-1 rounded border shadow-xs" id="dashboardCustomizerTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold py-2" id="tab-shortcuts-tab" data-toggle="pill" href="#tab-shortcuts" role="tab">
                                <i class="fas fa-bolt text-warning mr-1"></i> Quick Action Shortcuts
                                <span class="badge badge-primary ml-1" id="selectedShortcutsBadge">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold py-2" id="tab-widgets-tab" data-toggle="pill" href="#tab-widgets" role="tab">
                                <i class="fas fa-chart-pie text-info mr-1"></i> Dashboard Widgets & KPIs
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content pt-2" id="dashboardCustomizerTabContent">
                        {{-- Tab 1: Shortcuts --}}
                        <div class="tab-pane fade show active" id="tab-shortcuts" role="tabpanel">
                            {{-- Search & Module Filter Header --}}
                            <div class="row mb-2 align-items-center">
                                <div class="col-md-5 mb-2 mb-md-0">
                                    <div class="input-group input-group-sm shadow-xs">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                                        </div>
                                        <input type="text" id="shortcutSearchInput" class="form-control border-left-0" placeholder="Search menu (e.g. return, bill, order)...">
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="d-flex flex-wrap justify-content-md-end" id="moduleFilterPills" style="gap: 4px;">
                                        <button type="button" class="btn btn-xs btn-primary filter-pill active" data-module="ALL">All</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary filter-pill" data-module="Sales">Sales</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary filter-pill" data-module="Purchase">Purchase</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary filter-pill" data-module="Inventory">Inventory</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary filter-pill" data-module="Finance">Finance</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary filter-pill" data-module="POS">POS</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary filter-pill" data-module="Master">Master</button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                                <span class="text-xs text-uppercase font-weight-bold text-muted">
                                    <i class="fas fa-info-circle mr-1"></i> Check to enable / uncheck to hide buttons
                                </span>
                                <small class="text-muted">Use <i class="fas fa-arrow-up"></i> <i class="fas fa-arrow-down"></i> to re-order</small>
                            </div>

                            <div class="list-group shadow-xs rounded" id="shortcutsSortableList" style="max-height: 380px; overflow-y: auto;">
                                @php
                                    $activeKeys = $activeShortcuts->pluck('key')->all();
                                    // Order accessible shortcuts: active ones first in their current order, then remaining
                                    $orderedAccessible = collect();
                                    foreach ($activeKeys as $ak) {
                                        if ($accessibleShortcuts->has($ak)) {
                                            $orderedAccessible->put($ak, $accessibleShortcuts->get($ak));
                                        }
                                    }
                                    foreach ($accessibleShortcuts as $k => $item) {
                                        if (!$orderedAccessible->has($k)) {
                                            $orderedAccessible->put($k, $item);
                                        }
                                    }
                                @endphp

                                @foreach($orderedAccessible as $key => $shortcut)
                                    @php
                                        $isChecked = in_array($key, $activeKeys, true);
                                        $moduleName = $shortcut['module'] ?? 'App';
                                    @endphp
                                    <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-2 shortcut-item-row {{ $isChecked ? 'bg-white' : 'bg-light text-muted' }}" data-key="{{ $key }}" data-module="{{ $moduleName }}" data-title="{{ strtolower($shortcut['title'] . ' ' . $shortcut['description']) }}">
                                        <div class="custom-control custom-checkbox d-flex align-items-center flex-grow-1 mr-2">
                                            <input type="checkbox" name="shortcuts[]" value="{{ $key }}" class="custom-control-input shortcut-checkbox" id="sc_chk_{{ $key }}" {{ $isChecked ? 'checked' : '' }}>
                                            <label class="custom-control-label d-flex align-items-center w-100 cursor-pointer mb-0 pl-2" for="sc_chk_{{ $key }}">
                                                <div class="icon-circle mr-2 d-flex align-items-center justify-content-center rounded" style="width: 34px; height: 34px; background-color: #f1f5f9;">
                                                    <i class="{{ $shortcut['icon'] }} text-primary"></i>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <div class="d-flex align-items-center">
                                                        <span class="font-weight-bold text-dark mr-2">{{ $shortcut['title'] }}</span>
                                                        <span class="badge badge-secondary text-xs">{{ $moduleName }}</span>
                                                    </div>
                                                    <small class="text-muted">{{ $shortcut['description'] ?? '' }}</small>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-light btn-sm border btn-move-up" title="Move Up" onclick="moveShortcutRow(this, -1)">
                                                <i class="fas fa-arrow-up text-secondary"></i>
                                            </button>
                                            <button type="button" class="btn btn-light btn-sm border btn-move-down" title="Move Down" onclick="moveShortcutRow(this, 1)">
                                                <i class="fas fa-arrow-down text-secondary"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tab 2: Widgets --}}
                        <div class="tab-pane fade" id="tab-widgets" role="tabpanel">
                            <div class="alert alert-info py-2 px-3 mb-3 text-sm">
                                <i class="fas fa-lightbulb mr-1"></i> Disabling widgets you do not need makes your dashboard load significantly faster!
                            </div>

                            <div class="row">
                                @foreach($allWidgets as $widgetKey => $widget)
                                    @php
                                        $widgetActive = $activeWidgets[$widgetKey] ?? true;
                                    @endphp
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border shadow-xs mb-0">
                                            <div class="card-body p-3">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" name="widgets[]" value="{{ $widgetKey }}" class="custom-control-input" id="widget_chk_{{ $widgetKey }}" {{ $widgetActive ? 'checked' : '' }}>
                                                    <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="widget_chk_{{ $widgetKey }}">
                                                        {{ $widget['title'] }}
                                                    </label>
                                                </div>
                                                <p class="text-muted text-xs mb-0 mt-1 pl-4">
                                                    {{ $widget['description'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white py-2 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnResetDashboardDefaults">
                        <i class="fas fa-undo-alt mr-1"></i> Reset to Role Defaults
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm mr-1" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 font-weight-bold" id="btnSaveDashboardPreferences">
                            <i class="fas fa-save mr-1"></i> Save Preferences
                        </button>
                    </div>
                </div>
            </form>

            <form id="dashboardResetForm" action="{{ route('user.dashboard-preferences.reset') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    </div>
</div>

<script>
let currentModuleFilter = 'ALL';

function updateShortcutsCountBadge() {
    const checkedCount = document.querySelectorAll('#shortcutsSortableList .shortcut-checkbox:checked').length;
    const badge = document.getElementById('selectedShortcutsBadge');
    if (badge) {
        badge.textContent = checkedCount;
    }
}

function moveShortcutRow(button, direction) {
    const row = button.closest('.shortcut-item-row');
    if (!row) return;

    if (direction === -1 && row.previousElementSibling) {
        row.parentNode.insertBefore(row, row.previousElementSibling);
    } else if (direction === 1 && row.nextElementSibling) {
        row.parentNode.insertBefore(row.nextElementSibling, row);
    }
}

function filterShortcuts() {
    const searchEl = document.getElementById('shortcutSearchInput');
    const query = (searchEl ? searchEl.value : '').toLowerCase().trim();
    document.querySelectorAll('#shortcutsSortableList .shortcut-item-row').forEach(function(row) {
        const title = row.getAttribute('data-title') || '';
        const module = row.getAttribute('data-module') || '';
        const matchesQuery = !query || title.includes(query) || module.toLowerCase().includes(query);
        const matchesModule = currentModuleFilter === 'ALL' || module === currentModuleFilter;
        row.style.setProperty('display', (matchesQuery && matchesModule) ? 'flex' : 'none', 'important');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateShortcutsCountBadge();

    // Search input live filter
    const searchInput = document.getElementById('shortcutSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filterShortcuts);
    }

    // Module pills filter
    document.querySelectorAll('#moduleFilterPills .filter-pill').forEach(function(pill) {
        pill.addEventListener('click', function() {
            document.querySelectorAll('#moduleFilterPills .filter-pill').forEach(function(p) {
                p.classList.remove('btn-primary', 'active');
                p.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary', 'active');
            currentModuleFilter = this.getAttribute('data-module');
            filterShortcuts();
        });
    });

    // Checkbox styling update on toggle
    document.querySelectorAll('#shortcutsSortableList .shortcut-checkbox').forEach(function(chk) {
        chk.addEventListener('change', function() {
            const row = this.closest('.shortcut-item-row');
            if (this.checked) {
                row.classList.remove('bg-light', 'text-muted');
                row.classList.add('bg-white');
            } else {
                row.classList.add('bg-light', 'text-muted');
                row.classList.remove('bg-white');
            }
            updateShortcutsCountBadge();
        });
    });

    // Reset button confirmation
    const btnReset = document.getElementById('btnResetDashboardDefaults');
    if (btnReset) {
        btnReset.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset your dashboard shortcuts and widgets to the default template for your role?')) {
                document.getElementById('dashboardResetForm').submit();
            }
        });
    }
});
</script>
