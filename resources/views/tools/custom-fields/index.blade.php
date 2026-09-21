@extends('adminlte::page')

@section('title', 'Custom Fields Builder')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-sliders-h text-primary mr-2"></i> Custom Fields Builder
            </h1>
            <p class="text-muted mb-0 small">Add custom business fields to Masters, Sales, Purchase & Inventory without modifying database tables or running migrations.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm font-weight-bold" data-toggle="modal" data-target="#addFieldModal" onclick="openCreateModal('{{ $activeModule }}')">
                <i class="fas fa-plus mr-1"></i> Add Custom Field
            </button>
        </div>
    </div>
@stop

@section('content')
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Category Tabs --}}
    <div class="card card-primary card-outline card-outline-tabs shadow-sm mb-3">
        <div class="card-header p-0 border-bottom-0 bg-white">
            <ul class="nav nav-tabs" id="customFieldCategoryTabs" role="tablist">
                @foreach($categories as $catName => $catData)
                    @php
                        $catFieldCount = 0;
                        foreach(array_keys($catData['modules']) as $mKey) {
                            $catFieldCount += ($fieldsByModule->get($mKey)?->count() ?? 0);
                        }
                        $isCatActive = ($activeCategory === $catName);
                        $firstModuleInCat = array_key_first($catData['modules']);
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold {{ $isCatActive ? 'active border-top-primary' : 'text-secondary' }}"
                           href="{{ route('tools.custom-fields.index', ['tab' => $isCatActive ? $activeModule : $firstModuleInCat]) }}"
                           role="tab">
                            <i class="{{ $catData['icon'] }} mr-1"></i> {{ $catName }}
                            @if($catFieldCount > 0)
                                <span class="badge badge-primary ml-1">{{ $catFieldCount }}</span>
                            @else
                                <span class="badge badge-light border ml-1 text-muted">0</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body p-0">
            {{-- Sub-Module Pills for Active Category --}}
            <div class="p-3 bg-light border-bottom">
                <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <span class="font-weight-bold text-muted small text-uppercase mr-2">
                        <i class="fas fa-layer-group mr-1"></i> {{ $activeCategory }} Modules:
                    </span>
                    @foreach($categories[$activeCategory]['modules'] as $modKey => $modLabel)
                        @php
                            $modCount = $fieldsByModule->get($modKey)?->count() ?? 0;
                            $isModActive = ($activeModule === $modKey);
                        @endphp
                        <a href="{{ route('tools.custom-fields.index', ['tab' => $modKey]) }}"
                           class="btn btn-sm {{ $isModActive ? 'btn-primary font-weight-bold shadow-sm' : 'btn-outline-secondary bg-white' }}">
                            {{ $modLabel }}
                            <span class="badge {{ $isModActive ? 'badge-light text-primary' : 'badge-secondary' }} ml-1">
                                {{ $modCount }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Module Info & Action Header --}}
            <div class="px-3 py-2 bg-white border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <span class="font-weight-bold text-dark" style="font-size: 0.95rem;">
                        <i class="fas fa-cube text-primary mr-1"></i> {{ $allModules[$activeModule]['label'] }}
                    </span>
                    <span class="text-muted small ml-2 d-none d-md-inline">
                        (Active fields will automatically appear on {{ $allModules[$activeModule]['label'] }} forms)
                    </span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-xs font-weight-bold px-2" onclick="openCreateModal('{{ $activeModule }}')">
                    <i class="fas fa-plus mr-1"></i> Add Field
                </button>
            </div>

            {{-- Fields Table --}}
            @include('tools.custom-fields._table', [
                'fields' => $fieldsByModule->get($activeModule, collect()),
                'module' => $activeModule,
                'moduleLabel' => $allModules[$activeModule]['label']
            ])
        </div>
    </div>

    {{-- Add Field Modal --}}
    <div class="modal fade" id="addFieldModal" tabindex="-1" role="dialog" aria-labelledby="addFieldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('tools.custom-fields.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-primary text-white py-2">
                        <h5 class="modal-title font-weight-bold" id="addFieldModalLabel">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Custom Field
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Target Module <span class="text-danger">*</span></label>
                            <select name="module" id="add_module" class="form-control font-weight-bold" required>
                                @foreach($categories as $catName => $catData)
                                    <optgroup label="── {{ $catName }} ──">
                                        @foreach($catData['modules'] as $modKey => $modLabel)
                                            <option value="{{ $modKey }}" {{ $activeModule === $modKey ? 'selected' : '' }}>
                                                {{ $modLabel }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Field Label / Name <span class="text-danger">*</span></label>
                            <input type="text" name="field_name" id="add_field_name" class="form-control font-weight-bold" placeholder="e.g. Vet / Doctor Name, Delivery Slot, Bilty No, Vehicle No" required>
                            <small class="text-muted">A technical field key (slug) will automatically be generated.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label class="font-weight-bold small text-muted text-uppercase mb-1">Input Type <span class="text-danger">*</span></label>
                                <select name="field_type" id="add_field_type" class="form-control font-weight-bold" required onchange="toggleOptionsInput(this.value, 'add_options_wrapper')">
                                    <option value="text">Text (Single Line)</option>
                                    <option value="number">Number (Numeric / Decimal)</option>
                                    <option value="date">Date (Calendar Picker)</option>
                                    <option value="select">Dropdown (Select Option)</option>
                                    <option value="textarea">Textarea (Multi-line Notes)</option>
                                    <option value="checkbox">Checkbox (Switch / Boolean)</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label class="font-weight-bold small text-muted text-uppercase mb-1">Display Order</label>
                                <input type="number" name="sort_order" class="form-control" value="0" placeholder="0">
                            </div>
                        </div>

                        <div class="form-group mb-3 d-none" id="add_options_wrapper">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Dropdown Options (Comma separated) <span class="text-danger">*</span></label>
                            <textarea name="options" class="form-control" rows="2" placeholder="e.g. Morning (9AM-12PM), Afternoon (12PM-4PM), Evening (4PM-8PM)"></textarea>
                            <small class="text-muted">Separate available options with commas.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Default Value (Optional)</label>
                            <input type="text" name="default_value" class="form-control" placeholder="Default fallback value">
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="add_is_required" name="is_required" value="1">
                                    <label class="custom-control-label font-weight-bold text-dark" for="add_is_required">Mandatory Field (*)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="add_status" name="status" value="1" checked>
                                    <label class="custom-control-label font-weight-bold text-success" for="add_status">Active Immediately</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3 font-weight-bold">
                            <i class="fas fa-save mr-1"></i> Create Custom Field
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Field Modal --}}
    <div class="modal fade" id="editFieldModal" tabindex="-1" role="dialog" aria-labelledby="editFieldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form id="editFieldForm" action="" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-secondary text-white py-2">
                        <h5 class="modal-title font-weight-bold" id="editFieldModalLabel">
                            <i class="fas fa-edit mr-1"></i> Edit Custom Field
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Field Label / Name <span class="text-danger">*</span></label>
                            <input type="text" name="field_name" id="edit_field_name" class="form-control font-weight-bold" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label class="font-weight-bold small text-muted text-uppercase mb-1">Input Type <span class="text-danger">*</span></label>
                                <select name="field_type" id="edit_field_type" class="form-control font-weight-bold" required onchange="toggleOptionsInput(this.value, 'edit_options_wrapper')">
                                    <option value="text">Text (Single Line)</option>
                                    <option value="number">Number (Numeric / Decimal)</option>
                                    <option value="date">Date (Calendar Picker)</option>
                                    <option value="select">Dropdown (Select Option)</option>
                                    <option value="textarea">Textarea (Multi-line Notes)</option>
                                    <option value="checkbox">Checkbox (Switch / Boolean)</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label class="font-weight-bold small text-muted text-uppercase mb-1">Display Order</label>
                                <input type="number" name="sort_order" id="edit_sort_order" class="form-control" value="0">
                            </div>
                        </div>

                        <div class="form-group mb-3 d-none" id="edit_options_wrapper">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Dropdown Options (Comma separated)</label>
                            <textarea name="options" id="edit_options" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Default Value</label>
                            <input type="text" name="default_value" id="edit_default_value" class="form-control">
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_required" name="is_required" value="1">
                                    <label class="custom-control-label font-weight-bold text-dark" for="edit_is_required">Mandatory (*)</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="edit_status" name="status" value="1">
                                    <label class="custom-control-label font-weight-bold text-success" for="edit_status">Active Status</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm px-3 font-weight-bold">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
function openCreateModal(moduleName) {
    if (moduleName) {
        document.getElementById('add_module').value = moduleName;
    }
    $('#addFieldModal').modal('show');
}

function toggleOptionsInput(val, targetId) {
    const wrap = document.getElementById(targetId);
    if (val === 'select') {
        wrap.classList.remove('d-none');
    } else {
        wrap.classList.add('d-none');
    }
}

function openEditModal(field) {
    const form = document.getElementById('editFieldForm');
    form.action = "{{ url('tools/custom-fields') }}/" + field.id;

    document.getElementById('edit_field_name').value = field.field_name;
    document.getElementById('edit_field_type').value = field.field_type;
    document.getElementById('edit_sort_order').value = field.sort_order;
    document.getElementById('edit_default_value').value = field.default_value || '';
    document.getElementById('edit_is_required').checked = Boolean(field.is_required);
    document.getElementById('edit_status').checked = Boolean(field.status);

    if (field.field_type === 'select') {
        document.getElementById('edit_options_wrapper').classList.remove('d-none');
        document.getElementById('edit_options').value = Array.isArray(field.options) ? field.options.join(', ') : (field.options || '');
    } else {
        document.getElementById('edit_options_wrapper').classList.add('d-none');
        document.getElementById('edit_options').value = '';
    }

    $('#editFieldModal').modal('show');
}
</script>
@stop
