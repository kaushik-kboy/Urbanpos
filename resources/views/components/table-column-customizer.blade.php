@props([
    'tableKey',
    'tableId',
    'buttonClass' => 'btn btn-outline-secondary btn-sm',
    'buttonText' => 'Columns',
    'showIcon' => true,
])

@php
    $safeKey = \Illuminate\Support\Str::slug($tableKey, '-');
    $modalId = 'table-col-modal-' . $safeKey;
    $userId = auth()->id();
    $savedPrefs = $userId ? (\App\Models\UserTablePreference::getForUser($userId, $tableKey) ?? []) : [];
@endphp

<div class="d-inline-block table-customizer-wrapper" 
     id="customizer-wrapper-{{ $safeKey }}"
     data-table-key="{{ $tableKey }}"
     data-table-id="{{ $tableId }}"
     data-saved-prefs='@json($savedPrefs)'>

    <button type="button" 
            class="{{ $buttonClass }}" 
            data-toggle="modal" 
            data-target="#{{ $modalId }}" 
            title="Customize Visible Columns & Sequence">
        @if($showIcon) <i class="fas fa-columns text-primary mr-1"></i> @endif
        <span>{{ $buttonText }}</span>
    </button>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title font-weight-bold h6 mb-0" id="{{ $modalId }}Label">
                        <i class="fas fa-sliders-h text-primary mr-1"></i> Customize Columns & Order
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Tick columns to show/hide. Change the <strong>Order</strong> numbers (or use arrows) to arrange columns left-to-right. Settings persist across all devices.
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small font-weight-bold text-muted text-uppercase">Available Columns</span>
                        <div>
                            <button type="button" class="btn btn-xs btn-link p-0 mr-2 text-decoration-none btn-select-all">Select All</button>
                            <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none btn-deselect-all text-muted">Deselect All</button>
                        </div>
                    </div>

                    <div class="list-group list-group-flush border rounded col-customizer-list" style="max-height: 360px; overflow-y: auto;">
                        {{-- Items populated dynamically via JavaScript based on table <th> --}}
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-reset-customizer" title="Restore default columns">
                        <i class="fas fa-undo mr-1"></i> Reset to Default
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm btn-save-customizer">
                            <i class="fas fa-save mr-1"></i> Save View
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('css')
<style>
    .col-customizer-list .list-group-item {
        transition: background-color 0.15s ease-in-out;
    }
    .col-customizer-list .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .table-col-hidden {
        display: none !important;
    }
</style>
@endpush

@push('js')
<script>
(function() {
    if (window.TableCustomizerEngineInitialized) return;
    window.TableCustomizerEngineInitialized = true;

    function slugify(text) {
        return text.toString().toLowerCase().trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-');
    }

    function initTableCustomizer(wrapper) {
        const tableKey = wrapper.getAttribute('data-table-key');
        const tableId = wrapper.getAttribute('data-table-id');
        let savedPrefs = [];
        try {
            savedPrefs = JSON.parse(wrapper.getAttribute('data-saved-prefs') || '[]');
        } catch (e) {
            savedPrefs = [];
        }

        const table = document.getElementById(tableId) || document.querySelector(`table[data-customizer-id="${tableId}"]`) || wrapper.closest('.card')?.querySelector('table');
        if (!table) return;

        const thead = table.querySelector('thead');
        if (!thead) return;

        const headerRow = thead.querySelector('tr');
        if (!headerRow) return;

        const thList = Array.from(headerRow.children).filter(el => el.tagName === 'TH');
        if (thList.length === 0) return;

        // 1. Tag each original TH with a unique data-col-key and original order index
        const colDefinitions = [];
        thList.forEach((th, idx) => {
            let colKey = th.getAttribute('data-col-key');
            if (!colKey) {
                const label = th.innerText.trim();
                colKey = label ? slugify(label) : ('col-' + idx);
                // Ensure uniqueness
                if (colDefinitions.some(c => c.key === colKey)) {
                    colKey = colKey + '-' + idx;
                }
                th.setAttribute('data-col-key', colKey);
            }
            th.setAttribute('data-orig-idx', idx);

            const isActionCol = th.innerText.trim().toLowerCase() === 'actions' || th.hasAttribute('data-no-hide');
            colDefinitions.push({
                key: colKey,
                label: th.innerText.trim() || ('Column ' + (idx + 1)),
                origIndex: idx,
                canHide: !isActionCol
            });
        });

        // 2. Tag each TD in tbody and tfoot with corresponding data-col-key
        function tagBodyCells() {
            const rows = table.querySelectorAll('tbody tr, tfoot tr');
            rows.forEach(tr => {
                // If it's an empty row with colspan, skip tagging
                if (tr.children.length === 1 && tr.children[0].hasAttribute('colspan')) return;

                Array.from(tr.children).forEach((td, idx) => {
                    const correspondingTh = thList[idx];
                    if (correspondingTh) {
                        td.setAttribute('data-col-key', correspondingTh.getAttribute('data-col-key'));
                    }
                });
            });
        }
        tagBodyCells();

        // 3. Apply Column Preferences to DOM
        function applyPreferences(prefs) {
            if (!prefs || prefs.length === 0) {
                // Reset to original order and show all
                colDefinitions.forEach(c => {
                    const th = headerRow.querySelector(`th[data-col-key="${c.key}"]`);
                    if (th) th.classList.remove('table-col-hidden');
                    table.querySelectorAll(`td[data-col-key="${c.key}"]`).forEach(td => {
                        td.classList.remove('table-col-hidden');
                    });
                });

                // Sort THs back by origIndex
                const sortedThs = Array.from(headerRow.children).sort((a, b) => {
                    return parseInt(a.getAttribute('data-orig-idx') || 0) - parseInt(b.getAttribute('data-orig-idx') || 0);
                });
                sortedThs.forEach(th => headerRow.appendChild(th));

                // Sort TDs in rows back
                table.querySelectorAll('tbody tr, tfoot tr').forEach(tr => {
                    if (tr.children.length === 1 && tr.children[0].hasAttribute('colspan')) {
                        tr.children[0].setAttribute('colspan', colDefinitions.length);
                        return;
                    }
                    const sortedTds = Array.from(tr.children).sort((a, b) => {
                        const keyA = a.getAttribute('data-col-key');
                        const keyB = b.getAttribute('data-col-key');
                        const defA = colDefinitions.find(c => c.key === keyA);
                        const defB = colDefinitions.find(c => c.key === keyB);
                        return (defA ? defA.origIndex : 0) - (defB ? defB.origIndex : 0);
                    });
                    sortedTds.forEach(td => tr.appendChild(td));
                });
                return;
            }

            // Create a sorted list based on prefs order
            const prefMap = new Map();
            prefs.forEach(p => prefMap.set(p.key, p));

            const orderedCols = [...colDefinitions].sort((a, b) => {
                const orderA = prefMap.has(a.key) ? prefMap.get(a.key).order : (a.origIndex + 1);
                const orderB = prefMap.has(b.key) ? prefMap.get(b.key).order : (b.origIndex + 1);
                return orderA - orderB;
            });

            // Reorder TH in header
            orderedCols.forEach(col => {
                const th = headerRow.querySelector(`th[data-col-key="${col.key}"]`);
                if (th) {
                    headerRow.appendChild(th);
                    const isVisible = prefMap.has(col.key) ? prefMap.get(col.key).visible : true;
                    if (isVisible) {
                        th.classList.remove('table-col-hidden');
                    } else {
                        th.classList.add('table-col-hidden');
                    }
                }
            });

            // Reorder TDs in all rows
            let visibleCount = 0;
            orderedCols.forEach(col => {
                const isVisible = prefMap.has(col.key) ? prefMap.get(col.key).visible : true;
                if (isVisible) visibleCount++;
            });

            table.querySelectorAll('tbody tr, tfoot tr').forEach(tr => {
                if (tr.children.length === 1 && tr.children[0].hasAttribute('colspan')) {
                    tr.children[0].setAttribute('colspan', visibleCount > 0 ? visibleCount : 1);
                    return;
                }

                orderedCols.forEach(col => {
                    const td = tr.querySelector(`td[data-col-key="${col.key}"]`);
                    if (td) {
                        tr.appendChild(td);
                        const isVisible = prefMap.has(col.key) ? prefMap.get(col.key).visible : true;
                        if (isVisible) {
                            td.classList.remove('table-col-hidden');
                        } else {
                            td.classList.add('table-col-hidden');
                        }
                    }
                });
            });
        }

        // Apply saved preferences immediately on page load
        if (savedPrefs && savedPrefs.length > 0) {
            applyPreferences(savedPrefs);
        }

        // 4. Populate Modal List when Modal is opened
        const modal = wrapper.querySelector('.modal');
        const listContainer = wrapper.querySelector('.col-customizer-list');

        function buildModalList() {
            listContainer.innerHTML = '';
            const currentPrefsMap = new Map();
            if (savedPrefs && savedPrefs.length > 0) {
                savedPrefs.forEach(p => currentPrefsMap.set(p.key, p));
            }

            const activeSortedCols = [...colDefinitions].sort((a, b) => {
                const orderA = currentPrefsMap.has(a.key) ? currentPrefsMap.get(a.key).order : (a.origIndex + 1);
                const orderB = currentPrefsMap.has(b.key) ? currentPrefsMap.get(b.key).order : (b.origIndex + 1);
                return orderA - orderB;
            });

            activeSortedCols.forEach((col, idx) => {
                const isVisible = currentPrefsMap.has(col.key) ? currentPrefsMap.get(col.key).visible : true;
                const orderVal = currentPrefsMap.has(col.key) ? currentPrefsMap.get(col.key).order : (idx + 1);

                const item = document.createElement('div');
                item.className = 'list-group-item d-flex align-items-center justify-content-between p-2';
                item.setAttribute('data-key', col.key);

                item.innerHTML = `
                    <div class="custom-control custom-checkbox mr-2">
                        <input type="checkbox" class="custom-control-input col-toggle" id="chk-${tableKey}-${col.key}" 
                               ${isVisible ? 'checked' : ''} ${!col.canHide ? 'disabled checked' : ''}>
                        <label class="custom-control-label font-weight-normal text-dark" for="chk-${tableKey}-${col.key}">
                            ${col.label} ${!col.canHide ? '<span class="badge badge-light ml-1">Fixed</span>' : ''}
                        </label>
                    </div>
                    <div class="d-flex align-items-center">
                        <label class="small text-muted mb-0 mr-1">Order:</label>
                        <input type="number" class="form-control form-control-sm col-order text-center" 
                               style="width: 55px;" min="1" max="99" value="${orderVal}">
                        <div class="btn-group-vertical ml-1">
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-order-up" style="padding: 1px 4px; line-height: 1;"><i class="fas fa-chevron-up"></i></button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-order-down" style="padding: 1px 4px; line-height: 1;"><i class="fas fa-chevron-down"></i></button>
                        </div>
                    </div>
                `;
                listContainer.appendChild(item);
            });

            attachItemEvents();
        }

        function attachItemEvents() {
            const items = Array.from(listContainer.querySelectorAll('.list-group-item'));
            items.forEach(item => {
                const upBtn = item.querySelector('.btn-order-up');
                const downBtn = item.querySelector('.btn-order-down');

                upBtn.onclick = function() {
                    const prev = item.previousElementSibling;
                    if (prev) {
                        listContainer.insertBefore(item, prev);
                        recalculateOrderInputs();
                    }
                };

                downBtn.onclick = function() {
                    const next = item.nextElementSibling;
                    if (next) {
                        listContainer.insertBefore(next, item);
                        recalculateOrderInputs();
                    }
                };
            });
        }

        function recalculateOrderInputs() {
            const items = listContainer.querySelectorAll('.list-group-item');
            items.forEach((item, i) => {
                const input = item.querySelector('.col-order');
                if (input) input.value = (i + 1);
            });
        }

        // On Modal Show, rebuild list
        $(modal).on('show.bs.modal', function() {
            buildModalList();
        });

        // Select All / Deselect All
        const btnSelectAll = wrapper.querySelector('.btn-select-all');
        const btnDeselectAll = wrapper.querySelector('.btn-deselect-all');

        if (btnSelectAll) {
            btnSelectAll.onclick = function() {
                listContainer.querySelectorAll('.col-toggle:not(:disabled)').forEach(chk => chk.checked = true);
            };
        }

        if (btnDeselectAll) {
            btnDeselectAll.onclick = function() {
                listContainer.querySelectorAll('.col-toggle:not(:disabled)').forEach(chk => chk.checked = false);
            };
        }

        // Save Preferences Button
        const btnSave = wrapper.querySelector('.btn-save-customizer');
        if (btnSave) {
            btnSave.onclick = function() {
                const items = Array.from(listContainer.querySelectorAll('.list-group-item'));
                const newPrefs = items.map(item => {
                    const key = item.getAttribute('data-key');
                    const visible = item.querySelector('.col-toggle').checked;
                    const order = parseInt(item.querySelector('.col-order').value || '1', 10);
                    return { key, order, visible };
                });

                // Apply immediately to current DOM
                savedPrefs = newPrefs;
                applyPreferences(newPrefs);
                $(modal).modal('hide');

                // Save to database asynchronously
                btnSave.disabled = true;
                const originalHtml = btnSave.innerHTML;
                btnSave.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

                fetch('{{ route('tools.table-preferences.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        table_key: tableKey,
                        preferences: newPrefs
                    })
                })
                .then(r => r.json())
                .then(data => {
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalHtml;
                    if (data.status === 'success') {
                        if (window.toastr) {
                            toastr.success('Column layout saved permanently across devices!');
                        }
                    }
                })
                .catch(err => {
                    console.error('Error saving table preferences:', err);
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalHtml;
                });
            };
        }

        // Reset Preferences Button
        const btnReset = wrapper.querySelector('.btn-reset-customizer');
        if (btnReset) {
            btnReset.onclick = function() {
                if (!confirm('Are you sure you want to reset this table to default layout?')) return;

                savedPrefs = [];
                applyPreferences([]);
                $(modal).modal('hide');

                fetch('{{ route('tools.table-preferences.reset') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        table_key: tableKey
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (window.toastr) {
                        toastr.info('Table columns reset to default.');
                    }
                })
                .catch(err => console.error('Error resetting table preferences:', err));
            };
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.table-customizer-wrapper').forEach(wrapper => {
            initTableCustomizer(wrapper);
        });
    });
})();
</script>
@endpush
@endonce
