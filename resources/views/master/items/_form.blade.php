@php $i = $item ?? null; @endphp

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-general">General</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-taxes">Taxes</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-sales">Sales</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-category">Category</a></li>
    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-gst">GST</a></li>
</ul>

<div class="tab-content pt-3">
    <div class="tab-pane active" id="tab-general">
        <x-field name="ean_upc_code" label="EAN/UPC Code" :value="$i->ean_upc_code ?? ''">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-generate-barcode" title="Generate New Unique Barcode">
                <i class="fas fa-barcode mr-1"></i> Generate
            </button>
        </x-field>
        <x-field name="name" label="Item Name" :value="$i->name ?? ''" />
        <x-field name="alias" label="Alias" :value="$i->alias ?? ''" />
        <x-select name="brand_id" label="Brand" :options="$brands" :selected="$i->brand_id ?? ''" placeholder="Select a Brand">
            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" data-toggle="modal" data-target="#quickBrandModal" title="Add New Brand">
                <i class="fas fa-plus mr-1"></i> Add Brand
            </button>
        </x-select>
        <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$i->supplier_id ?? ''" placeholder="Select a Supplier">
            <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" data-toggle="modal" data-target="#quickSupplierModal" title="Add New Supplier">
                <i class="fas fa-plus mr-1"></i> Add Supplier
            </button>
        </x-select>
        <x-select name="product_type" label="Product Type" :options="['Standard' => 'Standard', 'Serialized' => 'Serialized', 'Service Component' => 'Service Component', 'Gift Voucher' => 'Gift Voucher']" :selected="$i->product_type ?? 'Standard'" />
        <x-field name="cost_price" label="Cost Price" type="number" step="0.01" :value="$i->cost_price ?? 0" />
        <x-field name="landing_cost" label="Landing Cost" type="number" step="0.01" :value="$i->landing_cost ?? 0" />
        <x-field name="sell_price" label="Sell Price" type="number" step="0.01" :value="$i->sell_price ?? 0" />
        <x-field name="mrp" label="MRP (Maximum Retail Price)" type="number" step="0.01" :value="$i->mrp ?? 0" />
        <x-bool-select name="status" label="Status" :value="$i->status ?? true" />
        <x-bool-select name="store_pickup" label="Store Pickup" :value="$i->store_pickup ?? false" true-label="Yes" false-label="No" />
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
        <x-field name="hsn_code" label="HSN Code" :value="$i->hsn_code ?? ''" maxlength="8" pattern="\d{8}" title="HSN Code must be exactly 8 digits" placeholder="e.g. 12345678" hint="Must be exactly 8 digits (numeric). Format: XXXXXXXX" id="hsn_code_input" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 8); document.getElementById('hsn-len').textContent = this.value.length + '/8'; document.getElementById('hsn-len').className = this.value.length === 8 ? 'badge badge-success ml-2' : 'badge badge-secondary ml-2';" />
        <div class="form-group row mt-n2 mb-2">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <span id="hsn-len" class="badge badge-secondary">0/8</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var hsn = document.getElementById('hsn_code_input');
    var lenBadge = document.getElementById('hsn-len');
    if (hsn && lenBadge) {
        var len = hsn.value.length;
        lenBadge.textContent = len + '/8';
        lenBadge.className = len === 8 ? 'badge badge-success ml-2' : 'badge badge-secondary ml-2';
    }
});
</script>

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
            <form id="quick-brand-form" onsubmit="return false;">
                @csrf
                <input type="hidden" name="status" value="1">
                <div class="modal-body py-3">
                    <div id="quick-brand-alert" class="alert alert-danger d-none py-2 px-3 small"></div>
                    
                    <div class="form-group">
                        <label for="quick_brand_name" class="font-weight-bold small">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_brand_name" name="name" placeholder="e.g. Bosch, Castrol, 3M" required autocomplete="off">
                        <div class="invalid-feedback" id="quick_brand_name_feedback"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="quick_brand_prefix" class="font-weight-bold small text-muted">Prefix (Optional)</label>
                            <input type="text" class="form-control form-control-sm" id="quick_brand_prefix" name="prefix" placeholder="e.g. BSH">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="quick_brand_alias" class="font-weight-bold small text-muted">Alias Code (Optional)</label>
                            <input type="text" class="form-control form-control-sm" id="quick_brand_alias" name="alias_code" placeholder="e.g. AL-01">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="btn-save-quick-brand">
                        <i class="fas fa-check mr-1"></i> Save & Select Brand
                    </button>
                </div>
            </form>
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
            <form id="quick-supplier-form" onsubmit="return false;">
                @csrf
                <input type="hidden" name="currency" value="INR">
                <input type="hidden" name="purchase_type" value="Local">
                <input type="hidden" name="purchase_mode" value="Credit">
                <input type="hidden" name="credit_limit" value="0">
                <input type="hidden" name="credit_balance" value="0">
                <input type="hidden" name="credit_days" value="0">
                <input type="hidden" name="mail_type" value="None">
                <input type="hidden" name="status" value="1">

                <div class="modal-body py-3">
                    <div id="quick-supplier-alert" class="alert alert-danger d-none py-2 px-3 small"></div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_name" class="font-weight-bold small">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="quick_supplier_name" name="name" placeholder="e.g. Acme Auto Parts Pvt Ltd" required autocomplete="off">
                            <div class="invalid-feedback" id="quick_supplier_name_feedback"></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_mobile" class="font-weight-bold small">Mobile Number (10 Digits)</label>
                            <input type="text" class="form-control" id="quick_supplier_mobile" name="mobile" placeholder="10-digit mobile" maxlength="10" inputmode="numeric">
                            <div class="invalid-feedback" id="quick_supplier_mobile_feedback"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_phone" class="font-weight-bold small text-muted">Phone (Landline / Office)</label>
                            <input type="text" class="form-control form-control-sm" id="quick_supplier_phone" name="phone" placeholder="Office / Landline Phone">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_email" class="font-weight-bold small text-muted">Email Address</label>
                            <input type="email" class="form-control form-control-sm" id="quick_supplier_email" name="email" placeholder="supplier@example.com">
                            <div class="invalid-feedback" id="quick_supplier_email_feedback"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_gst_no" class="font-weight-bold small text-muted">GST No (15 Chars)</label>
                            <input type="text" class="form-control form-control-sm" id="quick_supplier_gst_no" name="gst_no" maxlength="15" placeholder="e.g. 27AAPFU0939F1ZV" style="text-transform:uppercase;">
                            <div class="invalid-feedback" id="quick_supplier_gst_no_feedback"></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_gst_type" class="font-weight-bold small text-muted">GST Type</label>
                            <select class="form-control form-control-sm" id="quick_supplier_gst_type" name="gst_type">
                                <option value="Regular" selected>Regular</option>
                                <option value="Composite">Composite</option>
                                <option value="Un Register">Un Register</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_city" class="font-weight-bold small text-muted">City</label>
                            <input type="text" class="form-control form-control-sm" id="quick_supplier_city" name="city" placeholder="City">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="quick_supplier_state" class="font-weight-bold small text-muted">State</label>
                            <input type="text" class="form-control form-control-sm" id="quick_supplier_state" name="state" placeholder="State">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="quick_supplier_address" class="font-weight-bold small text-muted">Address</label>
                        <input type="text" class="form-control form-control-sm" id="quick_supplier_address" name="address" placeholder="Shop / Warehouse Address">
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success btn-sm font-weight-bold" id="btn-save-quick-supplier">
                        <i class="fas fa-check mr-1"></i> Save & Select Supplier
                    </button>
                </div>
            </form>
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
            <form id="quick-cat-val-form" onsubmit="return false;">
                @csrf
                <input type="hidden" name="item_category_id" id="quick_cat_val_head_id" value="">
                <input type="hidden" name="status" value="1">
                <input type="hidden" name="show_in_webstore" value="0">
                <input type="hidden" name="sellquick_applicable" value="0">
                <div class="modal-body py-3">
                    <div id="quick-cat-val-alert" class="alert alert-danger d-none py-2 px-3 small"></div>

                    <div class="form-group mb-0">
                        <label for="quick_cat_val_name" class="font-weight-bold small">Value Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quick_cat_val_name" name="name" placeholder="e.g. Engine Oil, Brake Pads, Accessories" required autocomplete="off">
                        <div class="invalid-feedback" id="quick_cat_val_name_feedback"></div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info btn-sm font-weight-bold text-white" id="btn-save-quick-cat-val">
                        <i class="fas fa-check mr-1"></i> Save & Select
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    // 1. Detach modals and append directly to body so they are outside any nested forms
    $('#quickBrandModal, #quickSupplierModal, #quickCatValModal').appendTo('body');

    // 2. Focus first input on open
    $('#quickBrandModal').on('shown.bs.modal', function () {
        $('#quick_brand_name').focus();
    });

    $('#quickSupplierModal').on('shown.bs.modal', function () {
        $('#quick_supplier_name').focus();
    });

    $('#quickCatValModal').on('shown.bs.modal', function () {
        $('#quick_cat_val_name').focus();
    });

    // 3. Clear errors when user edits inputs
    $('#quick-brand-form input').on('input', function () {
        $(this).removeClass('is-invalid');
        $('#quick-brand-alert').addClass('d-none').empty();
    });

    $('#quick-supplier-form input, #quick-supplier-form select').on('input change', function () {
        $(this).removeClass('is-invalid');
        $('#quick-supplier-alert').addClass('d-none').empty();
    });

    $('#quick-cat-val-form input').on('input', function () {
        $(this).removeClass('is-invalid');
        $('#quick-cat-val-alert').addClass('d-none').empty();
    });

    function notifySuccess(msg) {
        if (window.toastr) {
            toastr.success(msg);
        } else if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: msg,
                timer: 2000,
                showConfirmButton: false
            });
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

    // Quick Add Category Value Modal setup
    var currentCatValSelect = null;
    $(document).on('click', '.btn-quick-cat-val', function () {
        var headId = $(this).data('head-id');
        var headName = $(this).data('head-name');
        currentCatValSelect = $(this).data('target-select');

        $('#quick_cat_val_head_id').val(headId);
        $('#quick-cat-head-title').text(headName + ' Value');
        $('#quick_cat_val_name').val('').removeClass('is-invalid');
        $('#quick-cat-val-alert').addClass('d-none').empty();

        $('#quickCatValModal').modal('show');
    });

    $('#btn-save-quick-cat-val').on('click', function () {
        var $btn = $(this);
        var $form = $('#quick-cat-val-form');
        var $alert = $('#quick-cat-val-alert');
        var name = $.trim($('#quick_cat_val_name').val());

        $form.find('.is-invalid').removeClass('is-invalid');
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
            data: $form.serialize(),
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
                    $form[0].reset();
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
                            var input = $form.find('[name="' + field + '"]');
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

    $('#quick-cat-val-form input').on('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#btn-save-quick-cat-val').trigger('click');
        }
    });

    // 4. Save Brand via AJAX
    $('#btn-save-quick-brand').on('click', function () {
        var $btn = $(this);
        var $form = $('#quick-brand-form');
        var $alert = $('#quick-brand-alert');
        var name = $.trim($('#quick_brand_name').val());

        $form.find('.is-invalid').removeClass('is-invalid');
        $alert.addClass('d-none').empty();

        if (!name) {
            $('#quick_brand_name').addClass('is-invalid');
            $('#quick_brand_name_feedback').text('Please enter Brand Name.');
            $('#quick_brand_name').focus();
            return;
        }

        var origBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('master.brands.store') }}",
            type: "POST",
            data: $form.serialize(),
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
                    $form[0].reset();
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
                            var input = $form.find('[name="' + field + '"]');
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

    $('#quick-brand-form input').on('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#btn-save-quick-brand').trigger('click');
        }
    });

    // 5. Save Supplier via AJAX
    $('#btn-save-quick-supplier').on('click', function () {
        var $btn = $(this);
        var $form = $('#quick-supplier-form');
        var $alert = $('#quick-supplier-alert');
        var name = $.trim($('#quick_supplier_name').val());

        $form.find('.is-invalid').removeClass('is-invalid');
        $alert.addClass('d-none').empty();

        if (!name) {
            $('#quick_supplier_name').addClass('is-invalid');
            $('#quick_supplier_name_feedback').text('Please enter Supplier Name.');
            $('#quick_supplier_name').focus();
            return;
        }

        var origBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('master.suppliers.store') }}",
            type: "POST",
            data: $form.serialize(),
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
                    $form[0].reset();
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
                            var input = $form.find('[name="' + field + '"]');
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

    $('#quick-supplier-form input').on('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            $('#btn-save-quick-supplier').trigger('click');
        }
    });
});
</script>
