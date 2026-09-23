@php $i = $item ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-taxes">Taxes</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-sales">Sales</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-category">Category</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-gst">GST</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-custom-fields"><i class="fas fa-sliders-h text-primary mr-1"></i> Custom Fields</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold text-muted text-uppercase small mb-0"><i class="fas fa-barcode mr-1 text-primary"></i> General Item Fields</h6>
            <x-form-layout-customizer
                form-key="master_items.general"
                container-id="item-general-fields-grid"
                title="Customize Item Form Layout"
            />
        </div>
        <div class="row g-2 form-fields-grid" id="item-general-fields-grid">
            <div class="field-wrapper col-md-6" data-field="ean_upc_code" data-label="EAN/UPC Code" data-default-order="1" data-core="1">
                <x-field name="ean_upc_code" label="EAN/UPC Code" :value="$i->ean_upc_code ?? ''">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-generate-barcode" title="Generate New Unique Barcode">
                        <i class="fas fa-barcode mr-1"></i> Generate
                    </button>
                </x-field>
            </div>
            <div class="field-wrapper col-md-6" data-field="name" data-label="Item Name" data-default-order="2" data-core="1">
                <x-field name="name" label="Item Name" :value="$i->name ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="alias" data-label="Alias" data-default-order="3">
                <x-field name="alias" label="Alias" :value="$i->alias ?? ''" />
            </div>
            <div class="field-wrapper col-md-6" data-field="brand_id" data-label="Brand" data-default-order="4">
                <x-select name="brand_id" label="Brand" :options="$brands" :selected="$i->brand_id ?? ''" placeholder="Select a Brand">
                    <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" data-toggle="modal" data-target="#quickBrandModal" title="Add New Brand">
                        <i class="fas fa-plus mr-1"></i> Add Brand
                    </button>
                </x-select>
            </div>
            <div class="field-wrapper col-md-6" data-field="supplier_id" data-label="Supplier" data-default-order="5">
                <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$i->supplier_id ?? ''" placeholder="Select a Supplier">
                    <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" data-toggle="modal" data-target="#quickSupplierModal" title="Add New Supplier">
                        <i class="fas fa-plus mr-1"></i> Add Supplier
                    </button>
                </x-select>
            </div>
            <div class="field-wrapper col-md-6" data-field="product_type" data-label="Product Type" data-default-order="6" data-core="1">
                @php
                    $prodTypes = isset($productTypes) && count($productTypes) > 0
                        ? $productTypes
                        : \App\Models\ProductType::where('status', true)->orderBy('name')->pluck('name', 'name');
                    if ($prodTypes->isEmpty()) {
                        $prodTypes = collect(['Standard' => 'Standard', 'Serialized' => 'Serialized', 'Service Component' => 'Service Component', 'Gift Voucher' => 'Gift Voucher']);
                    }
                @endphp
                <x-select name="product_type" label="Product Type" :options="$prodTypes" :selected="$i->product_type ?? 'Standard'" />
            </div>
            <div class="field-wrapper col-md-6" data-field="cost_price" data-label="Cost Price" data-default-order="7" data-core="1">
                <x-field name="cost_price" label="Cost Price" type="number" step="0.01" :value="$i->cost_price ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="landing_cost" data-label="Landing Cost" data-default-order="8">
                <x-field name="landing_cost" label="Landing Cost" type="number" step="0.01" :value="$i->landing_cost ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="sell_price" data-label="Sell Price" data-default-order="9" data-core="1">
                <x-field name="sell_price" label="Sell Price" type="number" step="0.01" :value="$i->sell_price ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="mrp" data-label="MRP (Maximum Retail Price)" data-default-order="10" data-core="1">
                <x-field name="mrp" label="MRP (Maximum Retail Price)" type="number" step="0.01" :value="$i->mrp ?? 0" />
            </div>
            <div class="field-wrapper col-md-6" data-field="status" data-label="Status" data-default-order="11">
                <x-bool-select name="status" label="Status" :value="$i->status ?? true" />
            </div>
            <div class="field-wrapper col-md-6" data-field="store_pickup" data-label="Store Pickup" data-default-order="12">
                <x-bool-select name="store_pickup" label="Store Pickup" :value="$i->store_pickup ?? false" true-label="Yes" false-label="No" />
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-taxes">
        <x-bool-select name="tax_inclusive" label="Tax Inclusive" :value="$i->tax_inclusive ?? false" true-label="Yes" false-label="No" />
    </div>

    <div class="tab-pane" id="tab-sales">
        <x-select name="batch_expiry_details" label="Batch/Expiry Details" :options="['Not Required' => 'Not Required', 'Optional' => 'Optional', 'Mandatory' => 'Mandatory', 'Days' => 'Days', 'Month' => 'Month']" :selected="$i->batch_expiry_details ?? 'Not Required'" />
        <x-field name="shelf_life_days" label="Shelf Life (days)" type="number" :value="$i->shelf_life_days ?? ''" />
        <x-field name="minimum_shelf_life_days" label="Minimum Shelf Life (days)" type="number" :value="$i->minimum_shelf_life_days ?? ''" />
        <x-bool-select name="allow_negative_stock" label="Allow Negative Stock" :value="$i->allow_negative_stock ?? false" true-label="Yes" false-label="No" />
    </div>

    <div class="tab-pane" id="tab-category">
        <x-select name="department_value_id" label="DEPARTMENT" :options="$departmentValues" :selected="$i->department_value_id ?? ''" placeholder="Select a value">
            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap btn-quick-cat-val" data-head-id="{{ $deptHeadId ?? '' }}" data-head-name="DEPARTMENT" data-target-select="#department_value_id" title="Add Department Value">
                <i class="fas fa-plus mr-1"></i> Add
            </button>
        </x-select>
        <x-select name="category_value_id" label="CATEGORY" :options="$categoryValues" :selected="$i->category_value_id ?? ''" placeholder="Select a value">
            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap btn-quick-cat-val" data-head-id="{{ $catHeadId ?? '' }}" data-head-name="CATEGORY" data-target-select="#category_value_id" title="Add Category Value">
                <i class="fas fa-plus mr-1"></i> Add
            </button>
        </x-select>
        <x-select name="brand_value_id" label="Brands" :options="$brandValues" :selected="$i->brand_value_id ?? ''" placeholder="Select a value">
            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap btn-quick-cat-val" data-head-id="{{ $brandHeadId ?? '' }}" data-head-name="Brands" data-target-select="#brand_value_id" title="Add Brand Value">
                <i class="fas fa-plus mr-1"></i> Add
            </button>
        </x-select>
    </div>

    <div class="tab-pane" id="tab-gst">
        <x-select name="gst_tax_id" label="GST Tax" :options="$gstTaxes" :selected="$i->gst_tax_id ?? ''" placeholder="Select a GST tax" />
        <x-field name="hsn_code" label="HSN Code" :value="$i->hsn_code ?? ''" maxlength="8" pattern="\d{4,8}" title="HSN Code must be 4 to 8 digits" placeholder="e.g. 1234 or 12345678" hint="Must be 4 to 8 digits (numeric). Format: 4 to 8 digits" id="hsn_code_input" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8); document.getElementById('hsn-len').textContent = this.value.length + '/8'; document.getElementById('hsn-len').className = (this.value.length >= 4 && this.value.length <= 8) ? 'badge badge-success ml-2' : (this.value.length === 0 ? 'badge badge-secondary ml-2' : 'badge badge-warning ml-2');" />
        <div class="form-group row mt-n2 mb-2">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <span id="hsn-len" class="badge {{ !empty($i->hsn_code) && strlen($i->hsn_code) >= 4 ? 'badge-success' : 'badge-secondary' }}">{{ strlen($i->hsn_code ?? '') }}/8</span>
            </div>
        </div>
    </div>

    <div class="tab-pane" id="tab-custom-fields">
        <div class="p-2">
            <x-custom-fields-renderer module="Item" :model="$i" :showHeader="false" colClass="col-md-6 col-12 mb-3" />
        </div>
    </div>
</div>

{{-- Quick Add Brand Modal --}}
<div class="modal fade" id="quickBrandModal" tabindex="-1" role="dialog" aria-labelledby="quickBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title font-weight-bold" id="quickBrandModalLabel">
                    <i class="fas fa-tag mr-2"></i> Quick Add Brand
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <div id="quick-brand-alert" class="alert alert-danger d-none py-2 px-3 small"></div>
                
                <div class="form-group">
                    <label for="quick_brand_name" class="font-weight-bold small">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quick_brand_name" placeholder="e.g. Bosch, Castrol, 3M" autocomplete="off">
                    <div class="invalid-feedback" id="quick_brand_name_feedback"></div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="quick_brand_prefix" class="font-weight-bold small text-muted">Prefix (Optional)</label>
                        <input type="text" class="form-control form-control-sm" id="quick_brand_prefix" placeholder="e.g. BSH">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="quick_brand_alias" class="font-weight-bold small text-muted">Alias Code (Optional)</label>
                        <input type="text" class="form-control form-control-sm" id="quick_brand_alias" placeholder="e.g. AL-01">
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btn-save-quick-brand">
                    <i class="fas fa-check mr-1"></i> Save & Select Brand
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Quick Add Supplier Modal --}}
<div class="modal fade" id="quickSupplierModal" tabindex="-1" role="dialog" aria-labelledby="quickSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title font-weight-bold" id="quickSupplierModalLabel">
                    <i class="fas fa-truck mr-2"></i> Quick Add Supplier
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <div id="quick-supplier-alert" class="alert alert-danger d-none py-2 px-3 small"></div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_name" class="font-weight-bold small">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_supplier_name" placeholder="e.g. Acme Auto Parts Pvt Ltd" autocomplete="off">
                        <div class="invalid-feedback" id="quick_supplier_name_feedback"></div>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_mobile" class="font-weight-bold small">Mobile Number (10 Digits)</label>
                        <input type="text" class="form-control" id="quick_supplier_mobile" placeholder="10-digit mobile" maxlength="10" inputmode="numeric">
                        <div class="invalid-feedback" id="quick_supplier_mobile_feedback"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_phone" class="font-weight-bold small text-muted">Phone (Landline / Office)</label>
                        <input type="text" class="form-control form-control-sm" id="quick_supplier_phone" placeholder="Office / Landline Phone">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_email" class="font-weight-bold small text-muted">Email Address</label>
                        <input type="email" class="form-control form-control-sm" id="quick_supplier_email" placeholder="supplier@example.com">
                        <div class="invalid-feedback" id="quick_supplier_email_feedback"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_gst_no" class="font-weight-bold small text-muted">GST No (15 Chars)</label>
                        <input type="text" class="form-control form-control-sm" id="quick_supplier_gst_no" maxlength="15" placeholder="e.g. 27AAPFU0939F1ZV" style="text-transform:uppercase;">
                        <div class="invalid-feedback" id="quick_supplier_gst_no_feedback"></div>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_gst_type" class="font-weight-bold small text-muted">GST Type</label>
                        <select class="form-control form-control-sm" id="quick_supplier_gst_type">
                            @php
                                $itemGstTypes = \App\Models\GstType::where('status', true)->orderBy('name')->pluck('name', 'name');
                                if ($itemGstTypes->isEmpty()) {
                                    $itemGstTypes = collect(['Regular' => 'Regular', 'Composite' => 'Composite', 'Un Register' => 'Un Register']);
                                }
                            @endphp
                            @foreach($itemGstTypes as $gtVal => $gtText)
                                <option value="{{ $gtVal }}" {{ $gtVal === 'Regular' ? 'selected' : '' }}>{{ $gtText }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_city" class="font-weight-bold small text-muted">City</label>
                        <input type="text" class="form-control form-control-sm" id="quick_supplier_city" placeholder="City">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="quick_supplier_state" class="font-weight-bold small text-muted">State</label>
                        <input type="text" class="form-control form-control-sm" id="quick_supplier_state" placeholder="State">
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label for="quick_supplier_address" class="font-weight-bold small text-muted">Address</label>
                    <input type="text" class="form-control form-control-sm" id="quick_supplier_address" placeholder="Shop / Warehouse Address">
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm font-weight-bold" id="btn-save-quick-supplier">
                    <i class="fas fa-check mr-1"></i> Save & Select Supplier
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Quick Add Category Value Modal --}}
<div class="modal fade" id="quickCatValModal" tabindex="-1" role="dialog" aria-labelledby="quickCatValModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title font-weight-bold" id="quickCatValModalLabel">
                    <i class="fas fa-tags mr-2"></i> Add <span id="quick-cat-head-title">Category Value</span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <input type="hidden" id="quick_cat_val_head_id" value="">
            <div class="modal-body py-3">
                <div id="quick-cat-val-alert" class="alert alert-danger d-none py-2 px-3 small"></div>

                <div class="form-group mb-0">
                    <label for="quick_cat_val_name" class="font-weight-bold small">Value Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quick_cat_val_name" placeholder="e.g. Engine Oil, Brake Pads, Accessories" autocomplete="off">
                    <div class="invalid-feedback" id="quick_cat_val_name_feedback"></div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info btn-sm font-weight-bold text-white" id="btn-save-quick-cat-val">
                    <i class="fas fa-check mr-1"></i> Save & Select
                </button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
$(document).ready(function () {
    // 1. Move modals directly to body so they are outside any forms and no backdrop z-index issues
    $('#quickBrandModal, #quickSupplierModal, #quickCatValModal').appendTo('body');

    // HSN length indicator
    var hsn = document.getElementById('hsn_code_input');
    var lenBadge = document.getElementById('hsn-len');
    if (hsn && lenBadge) {
        var len = hsn.value.length;
        lenBadge.textContent = len + '/8';
        lenBadge.className = len === 8 ? 'badge badge-success ml-2' : 'badge badge-secondary ml-2';
    }

    // 2. Focus first input on modal shown
    $('#quickBrandModal').on('shown.bs.modal', function () {
        $('#quick_brand_name').val('').removeClass('is-invalid').focus();
        $('#quick_brand_prefix').val('');
        $('#quick_brand_alias').val('');
        $('#quick-brand-alert').addClass('d-none').empty();
    });

    $('#quickSupplierModal').on('shown.bs.modal', function () {
        $('#quick_supplier_name').val('').removeClass('is-invalid').focus();
        $('#quickSupplierModal').find('input[type="text"], input[type="email"]').val('').removeClass('is-invalid');
        $('#quick-supplier-alert').addClass('d-none').empty();
    });

    $('#quickCatValModal').on('shown.bs.modal', function () {
        $('#quick_cat_val_name').val('').removeClass('is-invalid').focus();
        $('#quick-cat-val-alert').addClass('d-none').empty();
    });

    // 3. Clear errors on typing
    $('#quickBrandModal input').on('input', function () {
        $(this).removeClass('is-invalid');
        $('#quick-brand-alert').addClass('d-none').empty();
    });

    $('#quickSupplierModal input, #quickSupplierModal select').on('input change', function () {
        $(this).removeClass('is-invalid');
        $('#quick-supplier-alert').addClass('d-none').empty();
    });

    $('#quickCatValModal input').on('input', function () {
        $(this).removeClass('is-invalid');
        $('#quick-cat-val-alert').addClass('d-none').empty();
    });

    function notifySuccess(msg) {
        if (typeof toastr !== 'undefined') {
            toastr.success(msg);
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: msg,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            alert(msg);
        }
    }

    // Barcode Generator Button
    $('#btn-generate-barcode').on('click', function () {
        var $btn = $(this);
        var origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>');

        $.ajax({
            url: "{{ route('master.items.generate-barcode') }}",
            type: "GET",
            dataType: "json",
            success: function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res && res.barcode) {
                    $('#ean_upc_code').val(res.barcode).trigger('input');
                    notifySuccess('Unique barcode generated: ' + res.barcode);
                }
            },
            error: function () {
                $btn.prop('disabled', false).html(origHtml);
                alert('Could not generate barcode. Please try again.');
            }
        });
    });

    // Category Value Modal Trigger
    var currentCatValSelect = null;
    $(document).on('click', '.btn-quick-cat-val', function () {
        var headId = $(this).data('head-id');
        var headName = $(this).data('head-name');
        currentCatValSelect = $(this).data('target-select');

        $('#quick_cat_val_head_id').val(headId);
        $('#quick-cat-head-title').text(headName + ' Value');
        $('#quickCatValModal').modal('show');
    });

    // Save Category Value
    $('#btn-save-quick-cat-val').on('click', function () {
        var $btn = $(this);
        var $alert = $('#quick-cat-val-alert');
        var name = $.trim($('#quick_cat_val_name').val());
        var headId = $('#quick_cat_val_head_id').val();

        $('#quickCatValModal').find('.is-invalid').removeClass('is-invalid');
        $alert.addClass('d-none').empty();

        if (!name) {
            $('#quick_cat_val_name').addClass('is-invalid');
            $('#quick_cat_val_name_feedback').text('Please enter value name.');
            $('#quick_cat_val_name').focus();
            return;
        }

        var origBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('master.item-category-values.store') }}",
            type: "POST",
            data: {
                _token: '{{ csrf_token() }}',
                item_category_id: headId,
                name: name,
                status: 1,
                show_in_webstore: 0,
                sellquick_applicable: 0
            },
            dataType: "json",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (res) {
                $btn.prop('disabled', false).html(origBtnHtml);
                if (res && res.data) {
                    var valId = String(res.data.id);
                    var valName = res.data.name;

                    if (currentCatValSelect) {
                        var $select = $(currentCatValSelect);
                        if (!$select.find('option[value="' + valId + '"]').length) {
                            var opt = new Option(valName, valId, true, true);
                            $select.append(opt);
                        }
                        $select.val(valId).trigger('change');
                    }

                    $('#quickCatValModal').modal('hide');
                    $('#quick_cat_val_name').val('');
                    notifySuccess(res.message || 'Value added successfully!');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html(origBtnHtml);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errs = xhr.responseJSON.errors;
                    var messages = [];
                    for (var field in errs) {
                        if (errs.hasOwnProperty(field)) {
                            var input = $('#quickCatValModal').find('[name="' + field + '"], #quick_cat_val_' + field);
                            if (input.length) {
                                input.addClass('is-invalid');
                                $('#quick_cat_val_' + field + '_feedback').text(errs[field][0]);
                            }
                            messages.push(errs[field][0]);
                        }
                    }
                    $alert.removeClass('d-none').html(messages.join('<br>'));
                } else {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while creating the value.';
                    $alert.removeClass('d-none').text(msg);
                }
            }
        });
    });

    $('#quick_cat_val_name').on('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#btn-save-quick-cat-val').trigger('click');
        }
    });

    // Save Brand via AJAX
    $('#btn-save-quick-brand').on('click', function () {
        var $btn = $(this);
        var $modal = $('#quickBrandModal');
        var $alert = $('#quick-brand-alert');
        var name = $.trim($('#quick_brand_name').val());

        $modal.find('.is-invalid').removeClass('is-invalid');
        $alert.addClass('d-none').empty();

        if (!name) {
            $('#quick_brand_name').addClass('is-invalid');
            $('#quick_brand_name_feedback').text('Please enter Brand Name.');
            $('#quick_brand_name').focus();
            return;
        }

        var postData = {
            _token: '{{ csrf_token() }}',
            name: name,
            prefix: $.trim($('#quick_brand_prefix').val()) || null,
            alias_code: $.trim($('#quick_brand_alias').val()) || null,
            status: 1
        };

        var origBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('master.brands.store') }}",
            type: "POST",
            data: postData,
            dataType: "json",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (res) {
                $btn.prop('disabled', false).html(origBtnHtml);
                if (res && res.data) {
                    var brand = res.data;
                    var brandId = String(brand.id);
                    var brandName = brand.name;

                    var $select = $('#brand_id');
                    if (!$select.find('option[value="' + brandId + '"]').length) {
                        var opt = new Option(brandName, brandId, true, true);
                        $select.append(opt);
                    }
                    $select.val(brandId).trigger('change');

                    $('#quickBrandModal').modal('hide');
                    $('#quick_brand_name').val('');
                    $('#quick_brand_prefix').val('');
                    $('#quick_brand_alias').val('');
                    notifySuccess(res.message || 'Brand created successfully!');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html(origBtnHtml);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errs = xhr.responseJSON.errors;
                    var messages = [];
                    for (var field in errs) {
                        if (errs.hasOwnProperty(field)) {
                            var input = $('#quick_brand_' + field);
                            if (input.length) {
                                input.addClass('is-invalid');
                                $('#quick_brand_' + field + '_feedback').text(errs[field][0]);
                            }
                            messages.push(errs[field][0]);
                        }
                    }
                    $alert.removeClass('d-none').html(messages.join('<br>'));
                } else {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while creating the brand.';
                    $alert.removeClass('d-none').text(msg);
                }
            }
        });
    });

    $('#quickBrandModal input').on('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#btn-save-quick-brand').trigger('click');
        }
    });

    // Save Supplier via AJAX
    $('#btn-save-quick-supplier').on('click', function () {
        var $btn = $(this);
        var $modal = $('#quickSupplierModal');
        var $alert = $('#quick-supplier-alert');
        var name = $.trim($('#quick_supplier_name').val());

        $modal.find('.is-invalid').removeClass('is-invalid');
        $alert.addClass('d-none').empty();

        if (!name) {
            $('#quick_supplier_name').addClass('is-invalid');
            $('#quick_supplier_name_feedback').text('Please enter Supplier Name.');
            $('#quick_supplier_name').focus();
            return;
        }

        var postData = {
            _token: '{{ csrf_token() }}',
            name: name,
            mobile: $.trim($('#quick_supplier_mobile').val()) || null,
            phone: $.trim($('#quick_supplier_phone').val()) || null,
            email: $.trim($('#quick_supplier_email').val()) || null,
            gst_no: $.trim($('#quick_supplier_gst_no').val()) || null,
            gst_type: $('#quick_supplier_gst_type').val() || 'Regular',
            city: $.trim($('#quick_supplier_city').val()) || null,
            state: $.trim($('#quick_supplier_state').val()) || null,
            address: $.trim($('#quick_supplier_address').val()) || null,
            currency: 'INR',
            purchase_type: 'Local',
            purchase_mode: 'Credit',
            credit_limit: 0,
            credit_balance: 0,
            credit_days: 0,
            mail_type: 'None',
            status: 1
        };

        var origBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('master.suppliers.store') }}",
            type: "POST",
            data: postData,
            dataType: "json",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function (res) {
                $btn.prop('disabled', false).html(origBtnHtml);
                if (res && res.data) {
                    var supplier = res.data;
                    var supplierId = String(supplier.id);
                    var supplierName = supplier.name;

                    var $select = $('#supplier_id');
                    if (!$select.find('option[value="' + supplierId + '"]').length) {
                        var opt = new Option(supplierName, supplierId, true, true);
                        $select.append(opt);
                    }
                    $select.val(supplierId).trigger('change');

                    $('#quickSupplierModal').modal('hide');
                    $('#quickSupplierModal').find('input[type="text"], input[type="email"]').val('');
                    notifySuccess(res.message || 'Supplier created successfully!');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html(origBtnHtml);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    var errs = xhr.responseJSON.errors;
                    var messages = [];
                    for (var field in errs) {
                        if (errs.hasOwnProperty(field)) {
                            var input = $('#quick_supplier_' + field);
                            if (input.length) {
                                input.addClass('is-invalid');
                                $('#quick_supplier_' + field + '_feedback').text(errs[field][0]);
                            }
                            messages.push(errs[field][0]);
                        }
                    }
                    $alert.removeClass('d-none').html(messages.join('<br>'));
                } else {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while creating the supplier.';
                    $alert.removeClass('d-none').text(msg);
                }
            }
        });
    });

    $('#quickSupplierModal input').on('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#btn-save-quick-supplier').trigger('click');
        }
    });

    // Auto-detect and badge tabs containing validation errors
    var $firstInvalidTab = null;
    $('.tab-pane').each(function () {
        var $pane = $(this);
        if ($pane.find('.is-invalid, .text-danger.small:not(:empty), .invalid-feedback:not(:empty)').length > 0) {
            var tabId = $pane.attr('id');
            var $tabLink = $('a[href="#' + tabId + '"]');
            if ($tabLink.length && !$tabLink.find('.badge-danger').length) {
                $tabLink.append(' <span class="badge badge-danger">!</span>');
            }
            if (!$firstInvalidTab) {
                $firstInvalidTab = $tabLink;
            }
        }
    });
    // Client-side validation: Cost Price <= Landing Cost <= Sell Price <= MRP
    function validatePricingHierarchy() {
        var $cost = $('#cost_price');
        var $landing = $('#landing_cost');
        var $sell = $('#sell_price');
        var $mrp = $('#mrp');
        if (!$sell.length || !$mrp.length) return { isValid: true };

        var costVal = parseFloat($cost.val()) || 0;
        var landingVal = parseFloat($landing.val()) || 0;
        var sellVal = parseFloat($sell.val()) || 0;
        var mrpVal = parseFloat($mrp.val()) || 0;

        $('#pricing-hierarchy-error, #sell-price-mrp-error, #landing-cost-error, #sell-price-error').remove();
        $cost.removeClass('is-invalid');
        $landing.removeClass('is-invalid');
        $sell.removeClass('is-invalid');
        $mrp.removeClass('is-invalid');

        var isValid = true;
        var firstErrorField = null;

        // 1. Landing Cost >= Cost Price
        if (costVal > 0 && landingVal > 0 && landingVal < costVal) {
            $landing.addClass('is-invalid');
            $landing.after('<span id="landing-cost-error" class="text-danger small font-weight-bold d-block mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Landing Cost (₹' + landingVal.toFixed(2) + ') must be greater than or equal to Cost Price (₹' + costVal.toFixed(2) + ').</span>');
            isValid = false;
            if (!firstErrorField) firstErrorField = $landing;
        }

        // 2. Sell Price >= Landing Cost (or Cost Price)
        var benchmark = landingVal > 0 ? landingVal : costVal;
        if (benchmark > 0 && sellVal > 0 && sellVal < benchmark) {
            $sell.addClass('is-invalid');
            var label = landingVal > 0 ? 'Landing Cost (₹' + landingVal.toFixed(2) + ')' : 'Cost Price (₹' + costVal.toFixed(2) + ')';
            $sell.after('<span id="sell-price-error" class="text-danger small font-weight-bold d-block mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Sell Price (₹' + sellVal.toFixed(2) + ') must be greater than or equal to ' + label + '.</span>');
            isValid = false;
            if (!firstErrorField) firstErrorField = $sell;
        }

        // 3. MRP >= Sell Price
        if (mrpVal > 0 && sellVal > 0 && mrpVal < sellVal) {
            $mrp.addClass('is-invalid');
            $mrp.after('<span id="sell-price-mrp-error" class="text-danger small font-weight-bold d-block mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>MRP (₹' + mrpVal.toFixed(2) + ') must be greater than or equal to Sell Price (₹' + sellVal.toFixed(2) + ').</span>');
            isValid = false;
            if (!firstErrorField) firstErrorField = $mrp;
        }

        return { isValid: isValid, field: firstErrorField };
    }

    $('#cost_price, #landing_cost, #sell_price, #mrp').on('input change blur', function() {
        validatePricingHierarchy();
    });

    $('form').has('#sell_price').on('submit', function(e) {
        var res = validatePricingHierarchy();
        if (!res.isValid) {
            e.preventDefault();
            alert('Price Validation Error:\n\nRules: Cost Price \u2264 Landing Cost \u2264 Sell Price \u2264 MRP\n\nPlease check the pricing fields in the General tab.');
            $('a[href="#tab-general"]').tab('show');
            if (res.field) {
                res.field.focus();
            }
            return false;
        }
    });
});
</script>
@endpush
