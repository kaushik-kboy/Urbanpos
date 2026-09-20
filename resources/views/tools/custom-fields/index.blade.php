@extends('adminlte::page')

@section('title', 'Custom Fields Builder')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-sliders-h text-primary mr-2"></i> Custom Fields Builder
            </h1>
            <p class="text-muted mb-0 small">Add custom business fields to Customers and Items without modifying database tables or running migrations.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm font-weight-bold" data-toggle="modal" data-target="#addFieldModal" onclick="openCreateModal('{{ $activeTab }}')">
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

    <div class="card card-primary card-outline card-outline-tabs shadow-sm">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" id="customFieldTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold {{ $activeTab === 'Customer' ? 'active' : '' }}" id="tab-customer" data-toggle="pill" href="#customer-content" role="tab" aria-controls="customer-content" aria-selected="{{ $activeTab === 'Customer' ? 'true' : 'false' }}">
                        <i class="fas fa-users text-info mr-1"></i> Customer Fields
                        <span class="badge badge-secondary ml-1">{{ $customerFields->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold {{ $activeTab === 'Item' ? 'active' : '' }}" id="tab-item" data-toggle="pill" href="#item-content" role="tab" aria-controls="item-content" aria-selected="{{ $activeTab === 'Item' ? 'true' : 'false' }}">
                        <i class="fas fa-boxes text-warning mr-1"></i> Item Master Fields
                        <span class="badge badge-secondary ml-1">{{ $itemFields->count() }}</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="customFieldTabsContent">
                {{-- Customer Fields Tab --}}
                <div class="tab-pane fade {{ $activeTab === 'Customer' ? 'show active' : '' }}" id="customer-content" role="tabpanel" aria-labelledby="tab-customer">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="font-weight-bold text-dark"><i class="fas fa-paw text-primary mr-1"></i> Pet & Customer Attributes</span>
                            <span class="text-muted small ml-2">(e.g. Pet Microchip Number, Dog Breed, Age, Vaccination Date)</span>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-xs font-weight-bold px-2" onclick="openCreateModal('Customer')">
                            <i class="fas fa-plus mr-1"></i> Add Customer Field
                        </button>
                    </div>

                    @include('tools.custom-fields._table', ['fields' => $customerFields, 'module' => 'Customer'])
                </div>

                {{-- Item Fields Tab --}}
                <div class="tab-pane fade {{ $activeTab === 'Item' ? 'show active' : '' }}" id="item-content" role="tabpanel" aria-labelledby="tab-item">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="font-weight-bold text-dark"><i class="fas fa-warehouse text-warning mr-1"></i> Item & Inventory Attributes</span>
                            <span class="text-muted small ml-2">(e.g. Rack / Shelf Location, Bin Number, Batch Expiry, Origin Country)</span>
                        </div>
                        <button type="button" class="btn btn-outline-warning btn-xs font-weight-bold px-2 text-dark" onclick="openCreateModal('Item')">
                            <i class="fas fa-plus mr-1"></i> Add Item Field
                        </button>
                    </div>

                    @include('tools.custom-fields._table', ['fields' => $itemFields, 'module' => 'Item'])
                </div>
            </div>
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
                            <select name="module" id="add_module" class="form-control" required>
                                <option value="Customer">Customer (Clients / Pet Owners)</option>
                                <option value="Item">Item (Products / Inventory)</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold small text-muted text-uppercase mb-1">Field Label / Name <span class="text-danger">*</span></label>
                            <input type="text" name="field_name" id="add_field_name" class="form-control font-weight-bold" placeholder="e.g. Pet Microchip Number, Shelf Rack Location" required>
                            <small class="text-muted">A technical field key (slug) will automatically be assigned.</small>
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
                            <textarea name="options" class="form-control" rows="2" placeholder="e.g. German Shepherd, Golden Retriever, Labrador, Persian Cat"></textarea>
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
    document.getElementById('add_module').value = moduleName;
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
