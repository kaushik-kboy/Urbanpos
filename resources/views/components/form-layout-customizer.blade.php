@props([
    'formKey',
    'containerId',
    'buttonClass' => 'btn btn-outline-secondary btn-xs font-weight-bold',
    'buttonText' => 'Customize Layout',
    'icon' => 'fas fa-sliders-h',
    'showIcon' => true,
    'title' => 'Customize Form Fields Layout & Priority',
])

@php
    $safeKey = \Illuminate\Support\Str::slug($formKey, '-');
    $modalId = 'form-layout-modal-' . $safeKey;
    $userId = auth()->id();
    $savedPrefs = $userId ? (\App\Models\UserFormPreference::getForUser($userId, $formKey) ?? []) : [];
@endphp

<div class="d-inline-block form-customizer-wrapper"
     id="form-customizer-wrapper-{{ $safeKey }}"
     data-form-key="{{ $formKey }}"
     data-container-id="{{ $containerId }}"
     data-saved-prefs='@json($savedPrefs)'>

    <button type="button"
            class="{{ $buttonClass }}"
            data-toggle="modal"
            data-target="#{{ $modalId }}"
            title="{{ $title }}">
        @if($showIcon) <i class="{{ $icon }} mr-1"></i> @endif
        @if(!empty($buttonText)) <span>{{ $buttonText }}</span> @endif
    </button>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg" role="document">
            <div class="modal-content shadow border-0">
                <div class="modal-header bg-light py-2">
                    <h5 class="modal-title font-weight-bold h6 mb-0 text-dark" id="{{ $modalId }}Label">
                        <i class="fas fa-sliders-h text-primary mr-1"></i> {{ $title }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Arrange field priority (up/down sequence) and width (25%, 33%, 50%, 100%). You can also hide optional fields. System-critical core fields (<i class="fas fa-lock text-secondary"></i>) cannot be hidden.
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small font-weight-bold text-muted text-uppercase">
                            <i class="fas fa-sort-amount-down text-secondary mr-1"></i> Fields Priority & Placement
                        </span>
                        <span class="small text-muted">Drag or use ▲ / ▼ to reorder</span>
                    </div>

                    <div class="list-group list-group-flush border rounded form-customizer-list" style="max-height: 420px; overflow-y: auto;">
                        {{-- Populated dynamically via JavaScript based on .field-wrapper --}}
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-reset-form-customizer" title="Restore standard default layout">
                        <i class="fas fa-undo mr-1"></i> Reset to Default
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm btn-save-form-customizer font-weight-bold shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Layout
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
    .form-customizer-list .list-group-item {
        transition: background-color 0.15s ease-in-out;
    }
    .form-customizer-list .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .form-field-hidden {
        display: none !important;
    }
    .field-wrapper {
        transition: order 0.2s ease, width 0.2s ease;
    }
</style>
@endpush

@push('js')
<script>
(function() {
    if (window.FormLayoutCustomizerEngineInitialized) return;
    window.FormLayoutCustomizerEngineInitialized = true;

    function cleanLabel(text) {
        return (text || '').replace(/\s*\*\s*$/, '').trim();
    }

    function initCustomizerWrapper($wrapper) {
        let formKey = $wrapper.data('form-key');
        let containerId = $wrapper.data('container-id');
        let $container = $('#' + containerId);
        let modalId = $wrapper.find('.modal').attr('id');
        let $modal = $('#' + modalId);
        let $list = $modal.find('.form-customizer-list');
        let rawSaved = $wrapper.attr('data-saved-prefs');
        let savedPrefs = [];

        try {
            savedPrefs = rawSaved ? JSON.parse(rawSaved) : [];
        } catch(e) {
            savedPrefs = [];
        }

        let fieldsMeta = [];

        // 1. Scan container for .field-wrapper elements
        $container.find('.field-wrapper').each(function(idx) {
            let $el = $(this);
            let fieldName = $el.data('field');
            if (!fieldName) return;

            let $label = $el.find('label').first();
            let labelText = cleanLabel($label.text()) || fieldName;
            let isCore = $el.data('core') == 1 || $label.find('.text-danger').length > 0 || $el.find('[required]').length > 0;
            let defaultOrder = parseInt($el.data('default-order') || (idx + 1), 10);

            // Determine current Bootstrap col class
            let colClass = 'col-md-4';
            if ($el.hasClass('col-md-3')) colClass = 'col-md-3';
            else if ($el.hasClass('col-md-4')) colClass = 'col-md-4';
            else if ($el.hasClass('col-md-6')) colClass = 'col-md-6';
            else if ($el.hasClass('col-md-12')) colClass = 'col-md-12';

            fieldsMeta.push({
                field: fieldName,
                label: labelText,
                isCore: isCore,
                defaultOrder: defaultOrder,
                defaultCol: colClass,
                currentCol: colClass,
                currentOrder: defaultOrder,
                visible: true,
                $el: $el
            });
        });

        // 2. Apply saved preferences if present
        if (Array.isArray(savedPrefs) && savedPrefs.length > 0) {
            let prefMap = {};
            savedPrefs.forEach(function(p) {
                prefMap[p.field] = p;
            });

            fieldsMeta.forEach(function(f) {
                if (prefMap[f.field]) {
                    f.currentOrder = parseInt(prefMap[f.field].order, 10) || f.defaultOrder;
                    if (prefMap[f.field].grid_col) {
                        f.currentCol = prefMap[f.field].grid_col;
                    }
                    if (f.isCore) {
                        f.visible = true; // Core fields can NEVER be hidden
                    } else {
                        f.visible = (prefMap[f.field].visible !== false && prefMap[f.field].visible !== 0 && prefMap[f.field].visible !== '0');
                    }
                }
            });
        }

        // Apply to DOM
        applyLayoutToDOM();

        function applyLayoutToDOM() {
            // Sort elements by currentOrder
            fieldsMeta.sort(function(a, b) {
                return a.currentOrder - b.currentOrder;
            });

            // Reorder in DOM and apply classes
            fieldsMeta.forEach(function(f, idx) {
                f.currentOrder = idx + 1;
                let $el = f.$el;

                // Re-append to container in sorted order
                $container.append($el);

                // Update col width classes
                $el.removeClass('col-md-2 col-md-3 col-md-4 col-md-6 col-md-12')
                   .addClass(f.currentCol);

                // Visibility
                if (!f.visible) {
                    $el.addClass('form-field-hidden');
                } else {
                    $el.removeClass('form-field-hidden');
                }
            });
        }

        // 3. Render Modal List
        function renderModalList() {
            $list.empty();

            fieldsMeta.sort(function(a, b) {
                return a.currentOrder - b.currentOrder;
            });

            fieldsMeta.forEach(function(f, idx) {
                let isCoreBadge = f.isCore ? '<span class="badge badge-secondary ml-2 small" title="System core field"><i class="fas fa-lock mr-1"></i>Core</span>' : '';
                let toggleDisabled = f.isCore ? 'disabled' : '';
                let isChecked = f.visible ? 'checked' : '';

                let html = `
                    <div class="list-group-item d-flex align-items-center justify-content-between p-2 f-item-row" data-field="${f.field}">
                        <div class="d-flex align-items-center" style="min-width: 220px;">
                            <span class="badge badge-light border text-muted mr-2 font-weight-bold f-seq" style="width: 28px; text-align: center;">${idx + 1}</span>
                            <div class="btn-group-vertical btn-group-xs mr-2">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 btn-move-up" title="Move Up"><i class="fas fa-chevron-up" style="font-size: 9px;"></i></button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 btn-move-down" title="Move Down"><i class="fas fa-chevron-down" style="font-size: 9px;"></i></button>
                            </div>
                            <div>
                                <span class="font-weight-bold text-dark">${f.label}</span>
                                <code class="small text-muted d-block">${f.field}</code>
                            </div>
                            ${isCoreBadge}
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <label class="small text-muted mb-0 mr-1 d-none d-sm-inline">Width:</label>
                                <select class="form-control form-control-sm d-inline-block f-col-select" style="width: 140px;">
                                    <option value="col-md-3" ${f.currentCol === 'col-md-3' ? 'selected' : ''}>25% (4/row)</option>
                                    <option value="col-md-4" ${f.currentCol === 'col-md-4' ? 'selected' : ''}>33% (3/row)</option>
                                    <option value="col-md-6" ${f.currentCol === 'col-md-6' ? 'selected' : ''}>50% (2/row)</option>
                                    <option value="col-md-12" ${f.currentCol === 'col-md-12' ? 'selected' : ''}>100% (Full row)</option>
                                </select>
                            </div>

                            <div class="custom-control custom-switch" title="${f.isCore ? 'Required by system' : 'Show or hide field'}">
                                <input type="checkbox" class="custom-control-input f-vis-toggle" id="vis_${modalId}_${f.field}" ${isChecked} ${toggleDisabled}>
                                <label class="custom-control-label" for="vis_${modalId}_${f.field}"></label>
                            </div>
                        </div>
                    </div>
                `;
                $list.append(html);
            });
        }

        // When modal opens, refresh list
        $modal.on('show.bs.modal', function() {
            renderModalList();
        });

        // Move Up
        $list.on('click', '.btn-move-up', function(e) {
            e.preventDefault();
            let $row = $(this).closest('.f-item-row');
            let $prev = $row.prev('.f-item-row');
            if ($prev.length) {
                $row.insertBefore($prev);
                updateOrderFromModalRows();
            }
        });

        // Move Down
        $list.on('click', '.btn-move-down', function(e) {
            e.preventDefault();
            let $row = $(this).closest('.f-item-row');
            let $next = $row.next('.f-item-row');
            if ($next.length) {
                $row.insertAfter($next);
                updateOrderFromModalRows();
            }
        });

        function updateOrderFromModalRows() {
            $list.find('.f-item-row').each(function(idx) {
                $(this).find('.f-seq').text(idx + 1);
            });
        }

        // Save Layout Handler
        $modal.find('.btn-save-form-customizer').on('click', function(e) {
            e.preventDefault();
            let $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving…');

            let updatedPrefs = [];
            $list.find('.f-item-row').each(function(idx) {
                let fieldName = $(this).data('field');
                let colClass = $(this).find('.f-col-select').val();
                let isVisible = $(this).find('.f-vis-toggle').is(':checked');

                updatedPrefs.push({
                    field: fieldName,
                    order: idx + 1,
                    grid_col: colClass,
                    visible: isVisible
                });

                // Update in-memory metadata
                let meta = fieldsMeta.find(m => m.field === fieldName);
                if (meta) {
                    meta.currentOrder = idx + 1;
                    meta.currentCol = colClass;
                    meta.visible = meta.isCore ? true : isVisible;
                }
            });

            // Send AJAX to store
            $.ajax({
                url: '{{ route("tools.form-preferences.store") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    form_key: formKey,
                    preferences: updatedPrefs
                },
                success: function(resp) {
                    applyLayoutToDOM();
                    $wrapper.attr('data-saved-prefs', JSON.stringify(updatedPrefs));
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Layout');
                    $modal.modal('hide');
                },
                error: function(xhr) {
                    alert('Failed to save layout preferences. Please try again.');
                    $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Layout');
                }
            });
        });

        // Reset to Default Handler
        $modal.find('.btn-reset-form-customizer').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to reset this form layout to the default system arrangement?')) {
                return;
            }

            let $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: '{{ route("tools.form-preferences.reset") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    form_key: formKey
                },
                success: function(resp) {
                    // Revert in-memory metadata
                    fieldsMeta.forEach(function(f) {
                        f.currentOrder = f.defaultOrder;
                        f.currentCol = f.defaultCol;
                        f.visible = true;
                    });
                    applyLayoutToDOM();
                    renderModalList();
                    $wrapper.attr('data-saved-prefs', '[]');
                    $btn.prop('disabled', false);
                    $modal.modal('hide');
                },
                error: function(xhr) {
                    alert('Failed to reset form layout.');
                    $btn.prop('disabled', false);
                }
            });
        });
    }

    $(document).ready(function() {
        $('.form-customizer-wrapper').each(function() {
            initCustomizerWrapper($(this));
        });
    });
})();
</script>
@endpush
@endonce
