@extends('adminlte::page')

@section('title', 'Print Designer — ' . ($supportedTypes[$docType]['label'] ?? 'Receipt'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="font-weight-bold text-dark mb-1">
                <i class="{{ $supportedTypes[$docType]['icon'] ?? 'fas fa-print' }} text-warning mr-2"></i> {{ $supportedTypes[$docType]['label'] ?? 'Receipt' }} Print Designer <span class="text-muted" style="font-size: 18px; font-weight: normal;">(प्रिंट कस्टमाइज़र)</span>
            </h1>
            <p class="text-muted small mb-0">
                Customize store headers, multi-line address, HSN visibility, return policies & paper width for <strong>{{ $supportedTypes[$docType]['label'] ?? 'Receipt' }}</strong>.
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <button type="button" class="btn btn-outline-primary btn-sm shadow-sm font-weight-bold mr-2" onclick="printSampleReceipt()">
                <i class="fas fa-print mr-1"></i> Print Sample Receipt
            </button>
            @if($docType === 'stock_transfer')
                <a href="{{ route('inventory.stock-transfers.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Stock Transfers
                </a>
            @elseif($docType === 'purchase_invoice')
                <a href="{{ route('purchase.purchase-invoices.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Purchase Invoices
                </a>
            @else
                <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Bills
                </a>
            @endif
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i> <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-1 pl-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- ── Document Type Tab Switcher & Branch Selector ─────────────────── --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center flex-wrap mr-3">
                    <span class="font-weight-bold small text-dark mr-3">
                        <i class="fas fa-layer-group text-warning mr-1"></i> Print Settings For:
                    </span>
                    @foreach($supportedTypes as $typeKey => $typeMeta)
                        <a href="{{ route('tools.receipt-designer.index', array_filter(['doc' => $typeKey, 'branch_id' => $selectedBranchId])) }}"
                           class="btn btn-sm mr-2 mb-1 font-weight-bold {{ $typeKey === $docType ? 'btn-' . $typeMeta['color'] : 'btn-outline-' . $typeMeta['color'] }}"
                           title="{{ $typeMeta['label'] }}">
                            <i class="{{ $typeMeta['icon'] }} mr-1"></i>
                            {{ $typeMeta['label'] }}
                            @if($typeKey === $docType)
                                <i class="fas fa-check ml-1" style="font-size:10px;"></i>
                            @endif
                        </a>
                    @endforeach
                </div>

                {{-- Branch is globally managed from the top navigation bar --}}
            </div>
        </div>
    </div>

    <form id="receipt-designer-form" action="{{ route('tools.receipt-designer.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="document_type" value="{{ $docType }}">
        <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
        <div class="row">
            {{-- Left Column: Settings Customizer --}}
            <div class="col-lg-7 col-md-12 mb-4">

                {{-- 0. Primary Print Mode & Format Engine --}}
                <div class="card card-outline card-primary shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark mb-0">
                                <i class="fas fa-print text-primary mr-2"></i> Print Format & Engine (प्रिंट फॉर्मेट)
                            </h3>
                            <span class="badge badge-primary px-2 py-1 font-weight-bold">Dual Format Engine</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <label class="font-weight-bold text-secondary small text-uppercase mb-2">Select Default Print Format (डिफ़ॉल्ट प्रिंट मोड चुनें)</label>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-3 h-100 bg-light" style="cursor: pointer;" onclick="document.getElementById('format_thermal').checked = true; document.getElementById('format_thermal').dispatchEvent(new Event('change'));">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="format_thermal" name="invoice_format" value="thermal" class="custom-control-input" {{ old('invoice_format', $settings->invoice_format ?? 'thermal') === 'thermal' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-dark" for="format_thermal">
                                            🧾 Thermal Roll Slip
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Compact 80mm / 58mm / 102mm roll slips for counter thermal POS receipt printers.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-3 h-100 bg-light" style="cursor: pointer;" onclick="document.getElementById('format_a4_gst').checked = true; document.getElementById('format_a4_gst').dispatchEvent(new Event('change'));">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="format_a4_gst" name="invoice_format" value="a4_gst" class="custom-control-input" {{ old('invoice_format', $settings->invoice_format ?? 'thermal') === 'a4_gst' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-primary" for="format_a4_gst">
                                            📄 A4 / A5 GST Tax Invoice
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Full-sheet professional GST Tax Invoice with modular header positioning and compliance.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modular A4 GST Header & Layout Styling Engine --}}
                <div id="card_a4_gst_styling" class="card card-outline card-indigo shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark mb-0">
                                <i class="fas fa-th-large text-indigo mr-2"></i> A4 GST Header Positioning & Brand Styling (हेडर लेआउट)
                            </h3>
                            <span class="badge badge-info px-2 py-1">4 Flexible Header Styles</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <label class="font-weight-bold text-secondary small text-uppercase mb-2">Header Layout Style (कंपनी हेडर और लोगो की स्थिति चुनें)</label>
                        <div class="row">
                            {{-- Layout 1: logo_left_address_below --}}
                            <div class="col-md-6 mb-3">
                                <div id="card_layout_logo_left_address_below" class="border rounded p-3 h-100 header-layout-choice {{ old('header_layout', $settings->header_layout) === 'logo_left_address_below' ? 'border-primary bg-light shadow-sm' : '' }}" style="cursor: pointer;" onclick="chooseHeaderLayout('logo_left_address_below')">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="layout_logo_left_address_below" name="header_layout" value="logo_left_address_below" class="custom-control-input" {{ old('header_layout', $settings->header_layout) === 'logo_left_address_below' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-dark" for="layout_logo_left_address_below">
                                            🏢 Logo Left + Address Below
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <strong>Image 1 Style (Corporate GST):</strong> Logo top-left, address below logo on left. Invoice metadata grid neatly aligned on the right.
                                    </div>
                                </div>
                            </div>

                            {{-- Layout 2: centered --}}
                            <div class="col-md-6 mb-3">
                                <div id="card_layout_centered" class="border rounded p-3 h-100 header-layout-choice {{ old('header_layout', $settings->header_layout) === 'centered' ? 'border-primary bg-light shadow-sm' : '' }}" style="cursor: pointer;" onclick="chooseHeaderLayout('centered')">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="layout_centered" name="header_layout" value="centered" class="custom-control-input" {{ old('header_layout', $settings->header_layout) === 'centered' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-dark" for="layout_centered">
                                            🎯 All Centered Minimalist
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <strong>Image 2 Style (Clean B&W):</strong> Store logo and address centered, Support QR code top-right, clean centered Tax Invoice pill.
                                    </div>
                                </div>
                            </div>

                            {{-- Layout 3: logo_left_address_right --}}
                            <div class="col-md-6 mb-3">
                                <div id="card_layout_logo_left_address_right" class="border rounded p-3 h-100 header-layout-choice {{ old('header_layout', $settings->header_layout) === 'logo_left_address_right' ? 'border-primary bg-light shadow-sm' : '' }}" style="cursor: pointer;" onclick="chooseHeaderLayout('logo_left_address_right')">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="layout_logo_left_address_right" name="header_layout" value="logo_left_address_right" class="custom-control-input" {{ old('header_layout', $settings->header_layout) === 'logo_left_address_right' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-dark" for="layout_logo_left_address_right">
                                            ↔️ Logo Left + Address Right
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <strong>Image 3 Style (Split Accent):</strong> Logo on left, Registered office & contacts on right, full-width colored Tax Invoice banner.
                                    </div>
                                </div>
                            </div>

                            {{-- Layout 4: logo_right_address_left --}}
                            <div class="col-md-6 mb-3">
                                <div id="card_layout_logo_right_address_left" class="border rounded p-3 h-100 header-layout-choice {{ old('header_layout', $settings->header_layout) === 'logo_right_address_left' ? 'border-primary bg-light shadow-sm' : '' }}" style="cursor: pointer;" onclick="chooseHeaderLayout('logo_right_address_left')">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="layout_logo_right_address_left" name="header_layout" value="logo_right_address_left" class="custom-control-input" {{ old('header_layout', $settings->header_layout) === 'logo_right_address_left' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-dark" for="layout_logo_right_address_left">
                                            🔄 Address Left + Logo Right
                                        </label>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <strong>Inverted Split:</strong> Company details on left, Logo on right, invoice metadata cleanly separated below.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Accent Color Selector --}}
                        <div class="mb-3 pt-2 border-top">
                            <label class="font-weight-bold text-secondary small text-uppercase">Brand Accent Color (थीम व हाइलाइट रंग)</label>
                            <div class="d-flex align-items-center flex-wrap">
                                <input type="color" id="input_accent_color_picker" class="form-control mr-2" style="width: 48px; height: 38px; padding: 2px; cursor: pointer;" value="{{ old('accent_color', $settings->getAccentColor()) }}">
                                <input type="text" name="accent_color" id="input_accent_color" class="form-control mr-3" style="width: 110px;" value="{{ old('accent_color', $settings->getAccentColor()) }}" placeholder="#1e40af">
                                <div class="d-flex align-items-center flex-wrap mt-2 mt-sm-0">
                                    <span class="small text-muted mr-2">Presets:</span>
                                    <button type="button" class="btn btn-sm mr-1 mb-1 shadow-sm" style="background:#1e40af; color:#fff; width:26px; height:26px; border-radius:50%; padding:0;" title="Navy Blue (#1e40af)" onclick="pickAccentColor('#1e40af')"></button>
                                    <button type="button" class="btn btn-sm mr-1 mb-1 shadow-sm" style="background:#1f2937; color:#fff; width:26px; height:26px; border-radius:50%; padding:0;" title="Slate (#1f2937)" onclick="pickAccentColor('#1f2937')"></button>
                                    <button type="button" class="btn btn-sm mr-1 mb-1 shadow-sm" style="background:#047857; color:#fff; width:26px; height:26px; border-radius:50%; padding:0;" title="Emerald (#047857)" onclick="pickAccentColor('#047857')"></button>
                                    <button type="button" class="btn btn-sm mr-1 mb-1 shadow-sm" style="background:#ea580c; color:#fff; width:26px; height:26px; border-radius:50%; padding:0;" title="Warm Amber (#ea580c)" onclick="pickAccentColor('#ea580c')"></button>
                                    <button type="button" class="btn btn-sm mr-1 mb-1 shadow-sm" style="background:#7c3aed; color:#fff; width:26px; height:26px; border-radius:50%; padding:0;" title="Royal Violet (#7c3aed)" onclick="pickAccentColor('#7c3aed')"></button>
                                    <button type="button" class="btn btn-sm mr-1 mb-1 shadow-sm" style="background:#dc2626; color:#fff; width:26px; height:26px; border-radius:50%; padding:0;" title="Crimson (#dc2626)" onclick="pickAccentColor('#dc2626')"></button>
                                </div>
                            </div>
                            <small class="text-muted">Highlights table headers, invoice title badges, and border accents.</small>
                        </div>

                        {{-- Modular Block Toggles --}}
                        <div class="row pt-2 border-top">
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_ship_to" name="show_ship_to" value="1" {{ old('show_ship_to', $settings->show_ship_to) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_ship_to">
                                        🚚 Show "Ship To / Consignee" Card
                                    </label>
                                    <div class="small text-muted pl-4">Displays delivery address alongside Bill To customer card.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_tax_summary_table" name="show_tax_summary_table" value="1" {{ old('show_tax_summary_table', $settings->show_tax_summary_table) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_tax_summary_table">
                                        📊 GST Rate-wise Breakup Table
                                    </label>
                                    <div class="small text-muted pl-4">Separate table for 0%, 5%, 12%, 18% CGST/SGST amounts.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_payment_details" name="show_payment_details" value="1" {{ old('show_payment_details', $settings->show_payment_details) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_payment_details">
                                        💳 Payment Card & UPI QR Code
                                    </label>
                                    <div class="small text-muted pl-4">Shows payment mode, txn reference, and UPI scan-to-pay QR.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_signature_box" name="show_signature_box" value="1" {{ old('show_signature_box', $settings->show_signature_box) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_signature_box">
                                        ✍️ Authorised Signatory Box
                                    </label>
                                    <div class="small text-muted pl-4">Official stamp and signature declaration box at bottom-right.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_support_qr" name="show_support_qr" value="1" {{ old('show_support_qr', $settings->show_support_qr) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_support_qr">
                                        📱 Support & Help QR Code
                                    </label>
                                    <div class="small text-muted pl-4">Prints quick support QR in invoice header (Image 2 style).</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Support QR URL / WhatsApp Link</label>
                                <input type="text" name="support_qr_payload" id="input_support_qr_payload" class="form-control form-control-sm" value="{{ old('support_qr_payload', $settings->support_qr_payload) }}" placeholder="e.g. https://wa.me/917383056626">
                            </div>
                        </div>

                        {{-- Statutory Compliance & Terms --}}
                        <div class="row pt-2 border-top">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">A4 Statutory & Compliance Notes</label>
                                <textarea name="compliance_notes" id="input_compliance_notes" class="form-control" rows="2" placeholder="e.g. Whether tax is payable on reverse charge: NO | Certified that the particulars given above are true and correct.">{{ old('compliance_notes', $settings->compliance_notes) }}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Terms & Conditions (नियम और शर्तें)</label>
                                <textarea name="terms_conditions" id="input_terms_conditions" class="form-control" rows="2" placeholder="1. Goods once sold will not be taken back without original bill.&#10;2. Subject to Ahmedabad jurisdiction only.">{{ old('terms_conditions', $settings->terms_conditions) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 1. Store Header & Branding --}}
                <div class="card card-outline card-warning shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-store text-warning mr-2"></i> 1. Store Header & Branding
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Store Name / Header Title <span class="text-danger">*</span></label>
                                <input type="text" name="store_name" id="input_store_name" class="form-control" value="{{ old('store_name', $settings->store_name) }}" required placeholder="e.g. URBAN PETS">
                                <small class="text-muted">Displays in bold capital letters at the top of every receipt.</small>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Tagline / Sub-heading</label>
                                <input type="text" name="tagline" id="input_tagline" class="form-control" value="{{ old('tagline', $settings->tagline) }}" placeholder="e.g. Complete Pet Care & Supplies">
                            </div>
                        </div>

                        <div class="row align-items-center">
                            <div class="col-md-4 mb-2">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_logo" name="show_logo" value="1" {{ old('show_logo', $settings->show_logo) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_logo">Print Store Logo</label>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="font-weight-bold text-secondary small text-uppercase mb-0">Upload Logo Image</label>
                                <input type="file" name="logo_file" id="input_logo_file" class="form-control-file form-control-sm mt-1" accept="image/*">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="font-weight-bold text-secondary small text-uppercase mb-0">Logo Width (<span id="logo_width_val">{{ $settings->logo_width ?? 120 }}</span>px)</label>
                                <input type="range" class="custom-range mt-1" min="60" max="220" step="10" name="logo_width" id="input_logo_width" value="{{ old('logo_width', $settings->logo_width ?? 120) }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Store Address & Contact Numbers --}}
                <div class="card card-outline card-info shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-map-marked-alt text-info mr-2"></i> 2. Store Address & Contact Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="font-weight-bold text-secondary small text-uppercase">Multi-Line Store Address</label>
                            <textarea name="header_address" id="input_header_address" class="form-control" rows="2" placeholder="e.g. Shop 4 & 5, Rivera Arcade, Near Motera Stadium, Ahmedabad - 380005">{{ old('header_address', $settings->header_address) }}</textarea>
                            <small class="text-muted">Leave blank to automatically use the active branch address.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Primary Phone</label>
                                <input type="text" name="phone" id="input_phone" class="form-control" value="{{ old('phone', $settings->phone) }}" placeholder="7383056626">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Alternate Phone</label>
                                <input type="text" name="phone_alt" id="input_phone_alt" class="form-control" value="{{ old('phone_alt', $settings->phone_alt) }}" placeholder="Optional">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Email Address</label>
                                <input type="email" name="email" id="input_email" class="form-control" value="{{ old('email', $settings->email) }}" placeholder="support@urbanpets.in">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">GSTIN / Tax ID</label>
                                <input type="text" name="gstin" id="input_gstin" class="form-control text-uppercase" value="{{ old('gstin', $settings->gstin) }}" placeholder="24AAAAA0000A1Z5">
                                <small class="text-muted">Leave blank for branch GST.</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Thermal Receipt Feature Toggles --}}
                <div class="card card-outline card-success shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-toggle-on text-success mr-2"></i> 3. Receipt Content Toggles (दिखाएं / छुपाएं)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_customer_pet_name" name="show_customer_pet_name" value="1" {{ old('show_customer_pet_name', $settings->show_customer_pet_name) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_customer_pet_name">
                                        🐾 Customer Pet Name
                                    </label>
                                    <div class="small text-muted pl-4">Prints pet name and breed under customer name (e.g. <em>Pet: Bruno</em>).</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_hsn_code" name="show_hsn_code" value="1" {{ old('show_hsn_code', $settings->show_hsn_code) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_hsn_code">
                                        🏷️ Print HSN / SAC Code
                                    </label>
                                    <div class="small text-muted pl-4">Shows item HSN code next to tax percent in item description.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_tax_breakup" name="show_tax_breakup" value="1" {{ old('show_tax_breakup', $settings->show_tax_breakup) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_tax_breakup">
                                        📊 Tax Breakup Summary
                                    </label>
                                    <div class="small text-muted pl-4">Displays CGST & SGST / IGST tax split in totals section.</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_discount" name="show_discount" value="1" {{ old('show_discount', $settings->show_discount) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_discount">
                                        🏷️ Line-Item Discount Note
                                    </label>
                                    <div class="small text-muted pl-4">Shows discount line below item (e.g. <em>Disc: -₹50</em>).</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="input_show_barcode" name="show_barcode" value="1" {{ old('show_barcode', $settings->show_barcode) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold" for="input_show_barcode">
                                        ║▌ Print Barcode at Bottom
                                    </label>
                                    <div class="small text-muted pl-4">Allows barcode scanner to scan the receipt for quick lookup/returns.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4. Dynamic UPI Payment QR Code --}}
                <div class="card card-outline card-primary shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold text-dark mb-0">
                                <i class="fas fa-qrcode text-primary mr-2"></i> 4. Dynamic UPI Payment QR Code (पेमेंट क्यूआर कोड)
                            </h3>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="input_show_upi_qr" name="show_upi_qr" value="1" {{ old('show_upi_qr', $settings->show_upi_qr) ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold text-primary" for="input_show_upi_qr">Enable UPI QR</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            Prints a dynamic QR Code encoded with your store VPA and exact bill amount. Customers scan with GPay, PhonePe, or Paytm for instant payment!
                        </p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">UPI VPA ID <span class="text-danger">*</span></label>
                                <input type="text" name="upi_id" id="input_upi_id" class="form-control font-weight-bold" value="{{ old('upi_id', $settings->upi_id) }}" placeholder="e.g. 7383056626@okbizaxis">
                                <small class="text-muted">Bank VPA (Virtual Payment Address) to receive funds.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">UPI Payee Display Name</label>
                                <input type="text" name="upi_payee_name" id="input_upi_payee_name" class="form-control" value="{{ old('upi_payee_name', $settings->upi_payee_name) }}" placeholder="e.g. Urban Pets">
                                <small class="text-muted">Business name seen by customer on their UPI app.</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 5. Paper Geometry & Width Selector --}}
                <div class="card card-outline card-secondary shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-scroll text-secondary mr-2"></i> 5. Paper Width & Font Size
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Receipt Paper Size</label>
                                <select name="paper_size" id="input_paper_size" class="form-control font-weight-bold">
                                    <option value="80mm" {{ old('paper_size', $settings->paper_size) === '80mm' ? 'selected' : '' }}>80mm (Standard 3-Inch Thermal Roll)</option>
                                    <option value="58mm" {{ old('paper_size', $settings->paper_size) === '58mm' ? 'selected' : '' }}>58mm (Small 2-Inch Compact Thermal Roll)</option>
                                    <option value="102mm" {{ old('paper_size', $settings->paper_size) === '102mm' ? 'selected' : '' }}>102mm / 4-Inch (TSC TE244 Thermal Roll / Wide Slip)</option>
                                    <option value="a4" {{ old('paper_size', $settings->paper_size) === 'a4' ? 'selected' : '' }}>A4 (Full Sheet Office / Laser)</option>
                                    <option value="a5" {{ old('paper_size', $settings->paper_size) === 'a5' ? 'selected' : '' }}>A5 (Half Sheet Voucher Slip)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Base Font Size</label>
                                <select name="font_size" id="input_font_size" class="form-control">
                                    <option value="small" {{ old('font_size', $settings->font_size) === 'small' ? 'selected' : '' }}>Small (10.5px - Compact print)</option>
                                    <option value="normal" {{ old('font_size', $settings->font_size) === 'normal' ? 'selected' : '' }}>Normal (12px - Recommended default)</option>
                                    <option value="large" {{ old('font_size', $settings->font_size) === 'large' ? 'selected' : '' }}>Large (13.5px - High readability)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 6. Footer Terms & Return Policy --}}
                <div class="card card-outline card-dark shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-file-contract text-dark mr-2"></i> 6. Footer Return Policy & Closing Note
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="font-weight-bold text-secondary small text-uppercase">Return & Exchange Policy Lines (रिटर्न पॉलिसी)</label>
                            <textarea name="footer_policy" id="input_footer_policy" class="form-control" rows="3" placeholder="e.g. Exchange valid within 7 days with original bill.&#10;No return on opened treats or frozen pet food.">{{ old('footer_policy', $settings->footer_policy) }}</textarea>
                            <small class="text-muted">Each new line will be printed neatly in small font above the barcode.</small>
                        </div>
                        <div class="mb-3">
                            <label class="font-weight-bold text-secondary small text-uppercase">Closing Greeting Note</label>
                            <textarea name="footer_note" id="input_footer_note" class="form-control" rows="2" placeholder="e.g. Thank you for shopping at Urban Pets!&#10;*** Have an Awesome Day! ***">{{ old('footer_note', $settings->footer_note) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Save Button Toolbar --}}
                <div class="card shadow-sm border-0 sticky-bottom bg-white p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary font-weight-bold" onclick="resetToDefaults()">
                            <i class="fas fa-undo mr-1"></i> Reset Defaults
                        </button>
                        <div>
                            <button type="button" class="btn btn-outline-primary font-weight-bold mr-2" onclick="printSampleReceipt()">
                                <i class="fas fa-eye mr-1"></i> Test Print
                            </button>
                            <button type="submit" id="btn-save-receipt-settings" class="btn btn-success font-weight-bold px-4 shadow">
                                <i class="fas fa-save mr-1"></i> Save Receipt Settings
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Live Interactive Thermal / A4 GST Preview --}}
            <div class="col-lg-5 col-md-12 mb-4">
                <div style="position: sticky; top: 15px;">
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" id="btn_toggle_thermal_preview" class="btn {{ $settings->isA4GstInvoice() ? 'btn-outline-light' : 'btn-warning' }} font-weight-bold" onclick="switchPreviewMode('thermal')">
                                    <i class="fas fa-receipt mr-1"></i> Thermal Slip
                                </button>
                                <button type="button" id="btn_toggle_a4_preview" class="btn {{ $settings->isA4GstInvoice() ? 'btn-warning' : 'btn-outline-light' }} font-weight-bold" onclick="switchPreviewMode('a4')">
                                    <i class="fas fa-file-invoice mr-1"></i> A4 GST Invoice
                                </button>
                            </div>
                            <span id="preview-size-badge" class="badge badge-warning text-uppercase font-weight-bold">
                                {{ $settings->isA4GstInvoice() ? 'A4 Sheet' : ($settings->paper_size ?: '80mm Roll') }}
                            </span>
                        </div>
                    </div>

                    {{-- Live Receipt Preview Container --}}
                    <div class="preview-scroll-wrapper" style="max-height: 85vh; overflow-y: auto; background: #525659; padding: 20px 10px; border-radius: 8px; box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);">
                        <div id="thermal-preview-container" style="{{ $settings->isA4GstInvoice() ? 'display: none;' : '' }}">
                        <div id="receipt-preview-box" class="receipt-paper" style="margin: 0 auto; background: #fff; padding: 12px 10px; border-radius: 2px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); font-family: 'Courier New', Courier, monospace; color: #000; transition: width 0.3s ease;">
                            
                            {{-- Store Logo (Preview) --}}
                            <div id="prev_logo_container" class="text-center mb-2" style="{{ $settings->show_logo ? '' : 'display: none;' }}">
                                <img id="prev_logo_img" src="{{ $settings->logo_path ?: 'https://placehold.co/120x60?text=LOGO' }}" alt="Store Logo" style="max-width: {{ $settings->logo_width ?? 120 }}px; height: auto;">
                            </div>

                            {{-- Store Header --}}
                            <div class="text-center">
                                <div id="prev_store_name" style="font-size: 16px; font-weight: 900; letter-spacing: 0.5px; text-transform: uppercase;">
                                    {{ $settings->store_name ?: 'URBAN PETS' }}
                                </div>
                                <div id="prev_tagline" style="font-size: 11px; font-weight: bold; color: #444; {{ $settings->tagline ? '' : 'display: none;' }}">
                                    {{ $settings->tagline }}
                                </div>
                                <div id="prev_address" style="font-size: 11px; line-height: 1.25; margin-top: 2px;">
                                    {!! nl2br(e($settings->header_address ?: ($sampleBill?->branch?->address ?: 'Rivera Arcade, Motera, Ahmedabad'))) !!}
                                </div>
                                <div id="prev_phone" style="font-size: 11px;">
                                    Tel: {{ $settings->phone ?: '7383056626' }}
                                    <span id="prev_phone_alt" style="{{ $settings->phone_alt ? '' : 'display: none;' }}"> / {{ $settings->phone_alt }}</span>
                                </div>
                                <div id="prev_gstin" style="font-size: 11px; font-weight: bold;">
                                    GSTIN: {{ $settings->gstin ?: ($sampleBill?->branch?->gst_number ?: '24AABCU1234F1Z5') }}
                                </div>
                            </div>

                            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                            @if($docType === 'stock_transfer')
                                <div class="text-center font-weight-bold text-uppercase" style="font-size: 12px; letter-spacing: 1px;">
                                    STOCK TRANSFER NOTE (स्टॉक ट्रांसफर चालान)
                                </div>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Stock Transfer Meta --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.3;">
                                    <tr>
                                        <td style="font-weight: bold;">Transfer #: {{ $sampleTransfer?->transfer_number ?: 'ST-2026-0001' }}</td>
                                        <td style="text-align: right;">Date: {{ $sampleTransfer?->transfer_date ? $sampleTransfer->transfer_date->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">From: <strong>{{ $sampleTransfer?->fromBranch?->name ?: 'Central Warehouse (Main)' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">To: <strong>{{ $sampleTransfer?->toBranch?->name ?: 'City Outlet Branch' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Status: <span class="badge badge-success">{{ $sampleTransfer?->status ?: 'Completed' }}</span></td>
                                        <td style="text-align: right;">Mode: Delivery Van</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Transfer Itemized Table --}}
                                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                    <thead>
                                        <tr style="border-bottom: 1px dashed #000;">
                                            <th style="text-align: left; padding: 4px 0;">Item Description</th>
                                            <th style="text-align: right; padding: 4px 0; width: 45px;">Qty</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Cost</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($sampleTransfer && $sampleTransfer->items->isNotEmpty())
                                            @foreach($sampleTransfer->items->take(3) as $idx => $tItem)
                                                <tr>
                                                    <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                                        {{ $idx + 1 }}. {{ $tItem->item?->name ?? 'Sample Item' }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #444; font-size: 10px;">
                                                        [{{ $tItem->item?->item_code ?? 'ITM-01' }}]
                                                        <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: {{ $tItem->item?->hsn_code ?? '23091000' }})</span>
                                                    </td>
                                                    <td style="text-align: right; font-weight: bold;">{{ number_format($tItem->qty, 3) }}</td>
                                                    <td style="text-align: right;">₹{{ number_format($tItem->unit_cost, 2) }}</td>
                                                    <td style="text-align: right; font-weight: bold;">₹{{ number_format($tItem->qty * $tItem->unit_cost, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="4" style="font-weight: bold; padding-top: 4px;">1. Royal Canin Maxi Puppy 4kg</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #444; font-size: 10px;">
                                                    [RC-MP-04] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23091000)</span>
                                                </td>
                                                <td style="text-align: right; font-weight: bold;">5.000</td>
                                                <td style="text-align: right;">₹1,950.00</td>
                                                <td style="text-align: right; font-weight: bold;">₹9,750.00</td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" style="font-weight: bold; padding-top: 4px;">2. Pedigree Adult Meat & Rice 3kg</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #444; font-size: 10px;">
                                                    [PED-AD-03] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23099090)</span>
                                                </td>
                                                <td style="text-align: right; font-weight: bold;">10.000</td>
                                                <td style="text-align: right;">₹450.00</td>
                                                <td style="text-align: right; font-weight: bold;">₹4,500.00</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Transfer Totals --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.4;">
                                    <tr>
                                        <td>Total Items / Transferred Qty:</td>
                                        <td style="text-align: right; font-weight: bold;">{{ $sampleTransfer ? $sampleTransfer->items->count() : '2' }} / {{ $sampleTransfer ? number_format($sampleTransfer->items->sum('qty'), 3) : '15.000' }}</td>
                                    </tr>
                                    <tr id="prev_tax_split_cgst" style="{{ $settings->show_tax_breakup ? '' : 'display: none;' }}">
                                        <td colspan="2" style="font-size: 10px; color: #555;">[Inter-branch transfer under Section 25]</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                <table style="width: 100%; font-size: 14px; font-weight: 900;">
                                    <tr>
                                        <td>TOTAL VALUE:</td>
                                        <td style="text-align: right;">₹{{ $sampleTransfer ? number_format($sampleTransfer->total_value, 2) : '14,250.00' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                {{-- Signatures for Stock Transfer --}}
                                <div style="margin-top: 20px; font-size: 10px; display: flex; justify-content: space-between;">
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Dispatched By (Sender)
                                    </div>
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Received By (Receiver)
                                    </div>
                                </div>
                            @elseif($docType === 'purchase_invoice')
                                <div class="text-center font-weight-bold text-uppercase" style="font-size: 12px; letter-spacing: 1px;">
                                    PURCHASE INVOICE / GOODS INWARD (खरीद बिल)
                                </div>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Purchase Meta --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.3;">
                                    <tr>
                                        <td style="font-weight: bold;">Invoice #: {{ $samplePurchase?->invoice_number ?: 'PI-2026-0012' }}</td>
                                        <td style="text-align: right;">Date: {{ $samplePurchase?->invoice_date ? $samplePurchase->invoice_date->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">Supplier: <strong>{{ $samplePurchase?->supplier?->name ?: 'Mars Petcare India Pvt Ltd' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">Supplier GST: <strong>{{ $samplePurchase?->supplier?->gst_number ?: '24AABCM9988Z1Z2' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Branch: {{ $samplePurchase?->branch?->name ?: 'Main Store' }}</td>
                                        <td style="text-align: right;">Type: Regular Tax Inv</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Purchase Itemized Table --}}
                                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                    <thead>
                                        <tr style="border-bottom: 1px dashed #000;">
                                            <th style="text-align: left; padding: 4px 0;">Item Description</th>
                                            <th style="text-align: right; padding: 4px 0; width: 45px;">Qty</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Cost</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($samplePurchase && $samplePurchase->items->isNotEmpty())
                                            @foreach($samplePurchase->items->take(3) as $idx => $pItem)
                                                <tr>
                                                    <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                                        {{ $idx + 1 }}. {{ $pItem->item?->name ?? 'Sample Item' }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #444; font-size: 10px;">
                                                        [{{ $pItem->item?->item_code ?? 'ITM-01' }}]
                                                        <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: {{ $pItem->item?->hsn_code ?? '23091000' }})</span>
                                                    </td>
                                                    <td style="text-align: right; font-weight: bold;">{{ number_format($pItem->qty, 3) }}</td>
                                                    <td style="text-align: right;">₹{{ number_format($pItem->cost_price, 2) }}</td>
                                                    <td style="text-align: right; font-weight: bold;">₹{{ number_format($pItem->total_amount ?? ($pItem->qty * $pItem->cost_price), 2) }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="4" style="font-weight: bold; padding-top: 4px;">1. Royal Canin Maxi Puppy 4kg</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #444; font-size: 10px;">
                                                    [RC-MP-04] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23091000)</span> (GST 18%)
                                                </td>
                                                <td style="text-align: right; font-weight: bold;">10.000</td>
                                                <td style="text-align: right;">₹1,800.00</td>
                                                <td style="text-align: right; font-weight: bold;">₹18,000.00</td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" style="font-weight: bold; padding-top: 4px;">2. Pedigree Gravy Meat 100g Pouch</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #444; font-size: 10px;">
                                                    [PED-GRV-100] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23099090)</span> (GST 18%)
                                                </td>
                                                <td style="text-align: right; font-weight: bold;">50.000</td>
                                                <td style="text-align: right;">₹32.00</td>
                                                <td style="text-align: right; font-weight: bold;">₹1,600.00</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Purchase Totals --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.4;">
                                    <tr>
                                        <td>Total Items / Qty:</td>
                                        <td style="text-align: right; font-weight: bold;">{{ $samplePurchase ? $samplePurchase->items->count() : '2' }} / {{ $samplePurchase ? number_format($samplePurchase->items->sum('qty'), 3) : '60.000' }}</td>
                                    </tr>
                                    <tr id="prev_taxable_row">
                                        <td>Taxable Subtotal:</td>
                                        <td style="text-align: right;">₹{{ $samplePurchase ? number_format($samplePurchase->taxable_amount ?? ($samplePurchase->total_amount * 0.84), 2) : '16,610.17' }}</td>
                                    </tr>
                                    <tr id="prev_tax_split_cgst" style="{{ $settings->show_tax_breakup ? '' : 'display: none;' }}">
                                        <td>CGST:</td>
                                        <td style="text-align: right;">₹{{ $samplePurchase ? number_format(($samplePurchase->total_gst ?? 2989.83) / 2, 2) : '1,494.91' }}</td>
                                    </tr>
                                    <tr id="prev_tax_split_sgst" style="{{ $settings->show_tax_breakup ? '' : 'display: none;' }}">
                                        <td>SGST:</td>
                                        <td style="text-align: right;">₹{{ $samplePurchase ? number_format(($samplePurchase->total_gst ?? 2989.83) / 2, 2) : '1,494.91' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                <table style="width: 100%; font-size: 14px; font-weight: 900;">
                                    <tr>
                                        <td>NET INVOICE PAYABLE:</td>
                                        <td style="text-align: right;">₹{{ $samplePurchase ? number_format($samplePurchase->total_amount ?? 19600.00, 2) : '19,600.00' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                {{-- Signatures for Purchase --}}
                                <div style="margin-top: 20px; font-size: 10px; display: flex; justify-content: space-between;">
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Goods Received By
                                    </div>
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Authorized Signatory
                                    </div>
                                </div>
                            @elseif($docType === 'sales_return')
                                <div class="text-center font-weight-bold text-uppercase text-danger" style="font-size: 12px; letter-spacing: 1px;">
                                    SALES RETURN / CREDIT NOTE (बिक्री वापसी रसीद)
                                </div>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Sales Return Meta --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.3;">
                                    <tr>
                                        <td style="font-weight: bold;">Return #: {{ $sampleSalesReturn?->return_number ?: 'SR-2026-0004' }}</td>
                                        <td style="text-align: right;">Date: {{ $sampleSalesReturn?->return_date ? $sampleSalesReturn->return_date->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">Orig Bill: <strong>{{ $sampleSalesReturn?->salesBill?->bill_number ?: 'SB-2026-0009' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            Customer: <strong>{{ $sampleSalesReturn?->customer?->name ?: 'Walking Customer' }}</strong>
                                            @if($sampleSalesReturn?->customer?->phone) ({{ $sampleSalesReturn->customer->phone }}) @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Refund Mode: <span class="badge badge-warning">{{ $sampleSalesReturn?->return_mode ?: 'Cash Refund' }}</span></td>
                                        <td style="text-align: right;">Branch: {{ $sampleSalesReturn?->branch?->name ?: 'Main Branch' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Sales Return Item Table --}}
                                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                    <thead>
                                        <tr style="border-bottom: 1px dashed #000;">
                                            <th style="text-align: left; padding: 4px 0;">Returned Item</th>
                                            <th style="text-align: right; padding: 4px 0; width: 45px;">Qty</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Rate</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Refund</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($sampleSalesReturn && $sampleSalesReturn->items->isNotEmpty())
                                            @foreach($sampleSalesReturn->items->take(3) as $idx => $srItem)
                                                <tr>
                                                    <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                                        {{ $idx + 1 }}. {{ $srItem->item?->name ?? 'Returned Item' }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #444; font-size: 10px;">
                                                        [{{ $srItem->item?->item_code ?? 'ITM-01' }}]
                                                        <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: {{ $srItem->item?->hsn_code ?? '23091000' }})</span>
                                                    </td>
                                                    <td style="text-align: right; font-weight: bold;">{{ number_format($srItem->qty ?? $srItem->quantity, 2) }}</td>
                                                    <td style="text-align: right;">₹{{ number_format($srItem->unit_price ?? $srItem->rate, 2) }}</td>
                                                    <td style="text-align: right; font-weight: bold;">₹{{ number_format(($srItem->qty ?? $srItem->quantity) * ($srItem->unit_price ?? $srItem->rate), 2) }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="4" style="font-weight: bold; padding-top: 4px;">1. Drools Adult Dog Food 3kg</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #444; font-size: 10px;">
                                                    [DRL-AD-03] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23091000)</span>
                                                </td>
                                                <td style="text-align: right; font-weight: bold;">1.00</td>
                                                <td style="text-align: right;">₹850.00</td>
                                                <td style="text-align: right; font-weight: bold;">₹850.00</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                <table style="width: 100%; font-size: 14px; font-weight: 900;">
                                    <tr>
                                        <td>NET REFUND AMOUNT:</td>
                                        <td style="text-align: right; color: #b91c1c;">₹{{ $sampleSalesReturn ? number_format($sampleSalesReturn->total, 2) : '850.00' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                {{-- Signatures for Sales Return --}}
                                <div style="margin-top: 20px; font-size: 10px; display: flex; justify-content: space-between;">
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Customer Signature
                                    </div>
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Store Manager
                                    </div>
                                </div>
                            @elseif($docType === 'purchase_return')
                                <div class="text-center font-weight-bold text-uppercase text-secondary" style="font-size: 12px; letter-spacing: 1px;">
                                    PURCHASE RETURN / DEBIT NOTE (खरीद वापसी चालान)
                                </div>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Purchase Return Meta --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.3;">
                                    <tr>
                                        <td style="font-weight: bold;">Return #: {{ $samplePurchaseReturn?->return_number ?: 'PR-2026-0002' }}</td>
                                        <td style="text-align: right;">Date: {{ $samplePurchaseReturn?->return_date ? $samplePurchaseReturn->return_date->format('d/m/Y') : now()->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">Return To: <strong>{{ $samplePurchaseReturn?->supplier?->name ?: 'Royal Canin India Pvt Ltd' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">Supplier GST: <strong>{{ $samplePurchaseReturn?->supplier?->gst_number ?: '24AABCR1234Q1Z9' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Orig Inv: {{ $samplePurchaseReturn?->purchaseInvoice?->invoice_number ?: 'PI-2026-0012' }}</td>
                                        <td style="text-align: right;">Branch: {{ $samplePurchaseReturn?->branch?->name ?: 'Main Store' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Purchase Return Item Table --}}
                                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                    <thead>
                                        <tr style="border-bottom: 1px dashed #000;">
                                            <th style="text-align: left; padding: 4px 0;">Item Description</th>
                                            <th style="text-align: right; padding: 4px 0; width: 45px;">Qty</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Cost</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($samplePurchaseReturn && $samplePurchaseReturn->items->isNotEmpty())
                                            @foreach($samplePurchaseReturn->items->take(3) as $idx => $prItem)
                                                <tr>
                                                    <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                                        {{ $idx + 1 }}. {{ $prItem->item?->name ?? 'Returned Item' }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #444; font-size: 10px;">
                                                        [{{ $prItem->item?->item_code ?? 'ITM-01' }}]
                                                        <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: {{ $prItem->item?->hsn_code ?? '23091000' }})</span>
                                                    </td>
                                                    <td style="text-align: right; font-weight: bold;">{{ number_format($prItem->qty, 3) }}</td>
                                                    <td style="text-align: right;">₹{{ number_format($prItem->cost_price, 2) }}</td>
                                                    <td style="text-align: right; font-weight: bold;">₹{{ number_format($prItem->total_amount ?? ($prItem->qty * $prItem->cost_price), 2) }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="4" style="font-weight: bold; padding-top: 4px;">1. Pedigree Meat & Rice 3kg (Damaged)</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #444; font-size: 10px;">
                                                    [PED-AD-03] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23091000)</span>
                                                </td>
                                                <td style="text-align: right; font-weight: bold;">2.000</td>
                                                <td style="text-align: right;">₹420.00</td>
                                                <td style="text-align: right; font-weight: bold;">₹840.00</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                <table style="width: 100%; font-size: 14px; font-weight: 900;">
                                    <tr>
                                        <td>TOTAL DEBIT AMOUNT:</td>
                                        <td style="text-align: right;">₹{{ $samplePurchaseReturn ? number_format($samplePurchaseReturn->total, 2) : '840.00' }}</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                {{-- Signatures for Purchase Return --}}
                                <div style="margin-top: 20px; font-size: 10px; display: flex; justify-content: space-between;">
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Supplier Agent / Courier
                                    </div>
                                    <div style="width: 48%; border-top: 1px solid #333; text-align: center; padding-top: 4px;">
                                        Store Manager
                                    </div>
                                </div>
                            @else
                                <div class="text-center font-weight-bold text-uppercase" style="font-size: 12px; letter-spacing: 1px;">
                                    TAX INVOICE (कर चालान)
                                </div>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Bill Meta --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.3;">
                                    <tr>
                                        <td style="font-weight: bold;">Bill No: {{ $sampleBill?->bill_number ?: 'SB-2026-0009' }}</td>
                                        <td style="text-align: right;">Date: {{ now()->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td>Time: {{ now()->format('h:i A') }}</td>
                                        <td style="text-align: right;">POS Counter</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            Cust: <strong>{{ $sampleBill?->customer?->name ?: 'Ankit Sharma' }}</strong> ({{ $sampleBill?->customer?->phone ?: '9898012345' }})
                                            <div id="prev_pet_row" style="color: #2c3e50; font-weight: bold; {{ $settings->show_customer_pet_name ? '' : 'display: none;' }}">
                                                🐾 Pet: {{ $sampleBill?->customer?->pets?->first()?->name ?: 'Bruno (Golden Retriever)' }}
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Itemized Table --}}
                                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                                    <thead>
                                        <tr style="border-bottom: 1px dashed #000;">
                                            <th style="text-align: left; padding: 4px 0;">Item Description</th>
                                            <th style="text-align: right; padding: 4px 0; width: 45px;">Qty</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Rate</th>
                                            <th style="text-align: right; padding: 4px 0; width: 55px;">Net</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- Sample Item 1 --}}
                                        <tr>
                                            <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                                1. Royal Canin Maxi Puppy 4kg
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="color: #444; font-size: 10px;">
                                                [RC-MP-04] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23091000)</span> (GST 18%)
                                            </td>
                                            <td style="text-align: right; font-weight: bold;">1.000</td>
                                            <td style="text-align: right;">₹2,450.00</td>
                                            <td style="text-align: right; font-weight: bold;">₹2,450.00</td>
                                        </tr>
                                        <tr id="prev_disc_sample_1" style="{{ $settings->show_discount ? '' : 'display: none;' }}">
                                            <td colspan="4" style="text-align: right; font-size: 10px; color: #666; font-style: italic;">
                                                Disc: -₹150.00 (6.12%)
                                            </td>
                                        </tr>

                                        {{-- Sample Item 2 --}}
                                        <tr>
                                            <td colspan="4" style="font-weight: bold; padding-top: 4px;">
                                                2. Gnawlers Calcium Milk Bones
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="color: #444; font-size: 10px;">
                                                [GNW-CMB] <span class="prev_hsn_tag" style="{{ $settings->show_hsn_code ? '' : 'display: none;' }}">(HSN: 23099090)</span> (GST 12%)
                                            </td>
                                            <td style="text-align: right; font-weight: bold;">2.000</td>
                                            <td style="text-align: right;">₹180.00</td>
                                            <td style="text-align: right; font-weight: bold;">₹360.00</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                                {{-- Totals --}}
                                <table style="width: 100%; font-size: 11px; line-height: 1.4;">
                                    <tr>
                                        <td>Total Items / Qty:</td>
                                        <td style="text-align: right; font-weight: bold;">2 / 3.000</td>
                                    </tr>
                                    <tr id="prev_taxable_row">
                                        <td>Taxable Subtotal:</td>
                                        <td style="text-align: right;">₹2,298.30</td>
                                    </tr>
                                    <tr id="prev_tax_split_cgst" style="{{ $settings->show_tax_breakup ? '' : 'display: none;' }}">
                                        <td>CGST:</td>
                                        <td style="text-align: right;">₹180.85</td>
                                    </tr>
                                    <tr id="prev_tax_split_sgst" style="{{ $settings->show_tax_breakup ? '' : 'display: none;' }}">
                                        <td>SGST:</td>
                                        <td style="text-align: right;">₹180.85</td>
                                    </tr>
                                    <tr>
                                        <td>Round Off:</td>
                                        <td style="text-align: right;">₹0.00</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                <table style="width: 100%; font-size: 15px; font-weight: 900;">
                                    <tr>
                                        <td>GRAND TOTAL:</td>
                                        <td style="text-align: right;">₹2,660.00</td>
                                    </tr>
                                </table>

                                <div style="border-top: 1px double #000; border-bottom: 1px double #000; height: 4px; margin: 6px 0;"></div>

                                {{-- Payment Row --}}
                                <table style="width: 100%; font-size: 11px; margin-bottom: 4px;">
                                    <tr>
                                        <td style="font-weight: bold;">Paid by:</td>
                                        <td style="text-align: right; font-weight: bold;">Cash / UPI</td>
                                    </tr>
                                </table>
                            @endif

                            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                            {{-- Dynamic UPI QR Preview (When docType is sales_bill) --}}
                            @if($docType === 'sales_bill')
                            <div id="prev_upi_container" class="text-center my-2" style="{{ $settings->show_upi_qr ? '' : 'display: none;' }}">
                                <div style="font-weight: bold; font-size: 11px; margin-bottom: 3px;">
                                    <i class="fas fa-qrcode mr-1"></i> SCAN TO PAY VIA UPI
                                </div>
                                <img id="prev_upi_qr_img" src="{{ $settings->getUpiQrUrl(2660.00, $sampleBill?->bill_number ?: 'SB-2026-0009') }}" alt="UPI QR Code" style="width: 115px; height: 115px; border: 1px solid #ddd; padding: 2px; background: #fff;">
                                <div id="prev_upi_vpa_text" style="font-size: 10px; font-weight: bold; margin-top: 2px; letter-spacing: 0.5px;">
                                    UPI: {{ $settings->upi_id ?: '7383056626@okbizaxis' }}
                                </div>
                                <div style="font-size: 9px; color: #555;">(GPay, PhonePe, Paytm, BHIM)</div>
                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                            </div>
                            @endif

                            {{-- Barcode Section --}}
                            <div id="prev_barcode_container" class="text-center my-2" style="{{ $settings->show_barcode ? '' : 'display: none;' }}">
                                <div style="display: inline-block; height: 32px; width: 170px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
                                <div style="font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-top: 2px;">
                                    * {{ $docType === 'stock_transfer' ? ($sampleTransfer?->transfer_number ?: 'ST-2026-0001') : ($docType === 'purchase_invoice' ? ($samplePurchase?->invoice_number ?: 'PI-2026-0012') : ($sampleBill?->bill_number ?: 'SB-2026-0009')) }} *
                                </div>
                            </div>

                            {{-- Footer Policy & Note --}}
                            <div class="text-center" style="font-size: 10px; margin-top: 6px; line-height: 1.35;">
                                <div id="prev_footer_policy" style="white-space: pre-line; color: #333; margin-bottom: 4px;">
                                    {{ $settings->footer_policy }}
                                </div>
                                <div id="prev_footer_note" class="font-weight-bold" style="white-space: pre-line;">
                                    {{ $settings->footer_note }}
                                </div>
                            </div>

                        </div>
                        </div>{{-- End thermal-preview-container --}}

                        {{-- A4 GST Invoice Preview Container --}}
                        <div id="a4-preview-container" style="{{ $settings->isA4GstInvoice() ? '' : 'display: none;' }}">
                            <div id="a4-receipt-preview-box" class="a4-gst-invoice-root" style="--accent: {{ $settings->getAccentColor() }}; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #1e293b; line-height: 1.35; font-size: 11px; background: #fff; max-width: 680px; margin: 0 auto; padding: 14px 18px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.35);">
                                
                                {{-- ── 1. MODULAR HEADER PREVIEW ──────────────────────────────── --}}
                                
                                {{-- LAYOUT 1: logo_left_address_below (Image 1 Style) --}}
                                <div id="prev_a4_hdr_logo_left_address_below" class="prev-a4-header" style="{{ $settings->getHeaderLayout() === 'logo_left_address_below' ? '' : 'display: none;' }}; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 2px solid var(--accent);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px;">
                                        <div style="flex: 1 1 58%;">
                                            <div class="prev_a4_logo_box" style="{{ $settings->show_logo ? '' : 'display: none;' }}; margin-bottom: 6px;">
                                                <img class="prev_a4_logo_img" src="{{ $settings->logo_path ?: 'https://placehold.co/120x60?text=LOGO' }}" alt="Logo" style="max-width: {{ $settings->logo_width ?? 120 }}px; height: auto;">
                                            </div>
                                            <div class="prev_a4_store_name" style="font-size: 16px; font-weight: 800; color: #0f172a; text-transform: uppercase;">{{ $settings->store_name ?: 'URBAN PETS' }}</div>
                                            <div class="prev_a4_tagline" style="font-size: 10.5px; font-weight: 600; color: #64748b; {{ $settings->tagline ? '' : 'display: none;' }}">{{ $settings->tagline }}</div>
                                            <div class="prev_a4_address" style="font-size: 10.5px; color: #334155; margin-top: 2px;">{!! nl2br(e($settings->header_address ?: 'Shop 4 & 5, Rivera Arcade, Motera, Ahmedabad - 380005')) !!}</div>
                                            <div style="font-size: 10.5px; color: #334155;">
                                                <span class="prev_a4_phone">Tel: {{ $settings->phone ?: '7383056626' }}</span>
                                                <span class="prev_a4_phone_alt" style="{{ $settings->phone_alt ? '' : 'display: none;' }}"> / {{ $settings->phone_alt }}</span>
                                                <span class="prev_a4_email_wrap" style="{{ $settings->email ? '' : 'display: none;' }}"> | <span class="prev_a4_email">{{ $settings->email }}</span></span>
                                            </div>
                                            <div class="prev_a4_gstin" style="font-size: 11px; font-weight: 700; color: var(--accent); margin-top: 2px;">GSTIN: {{ $settings->gstin ?: '24AABCU1234F1Z5' }}</div>
                                        </div>
                                        <div style="flex: 0 0 40%; text-align: right;">
                                            <div style="display: inline-block; background: var(--accent); color: #fff; padding: 4px 14px; font-weight: 800; font-size: 13px; letter-spacing: 1px; border-radius: 3px; text-transform: uppercase; margin-bottom: 6px;">
                                                TAX INVOICE
                                            </div>
                                            <table style="width: 100%; font-size: 10.5px; border-collapse: collapse; text-align: left; border: 1px solid #cbd5e1;">
                                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                                    <td style="padding: 2px 6px; font-weight: bold; width: 45%;">Invoice No:</td>
                                                    <td style="padding: 2px 6px; font-weight: bold; color: var(--accent);">{{ $sampleBill?->bill_number ?: 'SB-2026-0009' }}</td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                                    <td style="padding: 2px 6px; font-weight: bold;">Date:</td>
                                                    <td style="padding: 2px 6px;">{{ now()->format('d/m/Y') }}</td>
                                                </tr>
                                                <tr style="background: #f8fafc;">
                                                    <td style="padding: 2px 6px; font-weight: bold;">Place of Supply:</td>
                                                    <td style="padding: 2px 6px;">24 - Gujarat</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                {{-- LAYOUT 2: centered (Image 2 Style) --}}
                                <div id="prev_a4_hdr_centered" class="prev-a4-header" style="{{ $settings->getHeaderLayout() === 'centered' ? '' : 'display: none;' }}; position: relative; text-align: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #0f172a;">
                                    <div class="prev_a4_support_qr" style="position: absolute; right: 0; top: 0; text-align: center; {{ $settings->show_support_qr ? '' : 'display: none;' }};">
                                        <img class="prev_a4_support_qr_img" src="{{ $settings->getSupportQrUrl() }}" alt="Support QR" style="width: 50px; height: 50px; border: 1px solid #cbd5e1; border-radius: 3px;">
                                        <div style="font-size: 8px; font-weight: bold; color: #475569;">Help / QR</div>
                                    </div>
                                    <div class="prev_a4_logo_box" style="{{ $settings->show_logo ? '' : 'display: none;' }}; margin-bottom: 4px;">
                                        <img class="prev_a4_logo_img" src="{{ $settings->logo_path ?: 'https://placehold.co/120x60?text=LOGO' }}" alt="Logo" style="max-width: {{ $settings->logo_width ?? 120 }}px; height: auto;">
                                    </div>
                                    <div class="prev_a4_store_name" style="font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase;">{{ $settings->store_name ?: 'URBAN PETS' }}</div>
                                    <div class="prev_a4_tagline" style="font-size: 10.5px; font-weight: 600; color: #64748b; {{ $settings->tagline ? '' : 'display: none;' }}">{{ $settings->tagline }}</div>
                                    <div class="prev_a4_address" style="font-size: 10.5px; color: #334155; margin-top: 1px;">{!! nl2br(e($settings->header_address ?: 'Shop 4 & 5, Rivera Arcade, Motera, Ahmedabad - 380005')) !!}</div>
                                    <div style="font-size: 10.5px; color: #334155;">
                                        <span class="prev_a4_phone">Tel: {{ $settings->phone ?: '7383056626' }}</span>
                                        <span class="prev_a4_phone_alt" style="{{ $settings->phone_alt ? '' : 'display: none;' }}"> / {{ $settings->phone_alt }}</span>
                                        <span class="prev_a4_email_wrap" style="{{ $settings->email ? '' : 'display: none;' }}"> | <span class="prev_a4_email">{{ $settings->email }}</span></span>
                                    </div>
                                    <div class="prev_a4_gstin" style="font-size: 11px; font-weight: 700; color: var(--accent); margin-top: 2px;">GSTIN: {{ $settings->gstin ?: '24AABCU1234F1Z5' }}</div>
                                    <div style="margin-top: 6px;">
                                        <span style="display: inline-block; background: #0f172a; color: #fff; padding: 2px 14px; font-weight: 700; font-size: 11.5px; letter-spacing: 1px; border-radius: 12px; text-transform: uppercase;">
                                            TAX INVOICE
                                        </span>
                                    </div>
                                </div>

                                {{-- LAYOUT 3: logo_left_address_right (Image 3 Style) --}}
                                <div id="prev_a4_hdr_logo_left_address_right" class="prev-a4-header" style="{{ $settings->getHeaderLayout() === 'logo_left_address_right' ? '' : 'display: none;' }}; margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <div style="flex: 1;">
                                            <div class="prev_a4_logo_box" style="{{ $settings->show_logo ? '' : 'display: none;' }}; margin-bottom: 4px;">
                                                <img class="prev_a4_logo_img" src="{{ $settings->logo_path ?: 'https://placehold.co/120x60?text=LOGO' }}" alt="Logo" style="max-width: {{ $settings->logo_width ?? 120 }}px; height: auto;">
                                            </div>
                                            <div class="prev_a4_store_name" style="font-size: 16px; font-weight: 800; color: var(--accent); text-transform: uppercase;">{{ $settings->store_name ?: 'URBAN PETS' }}</div>
                                            <div class="prev_a4_tagline" style="font-size: 10px; font-weight: 600; color: #64748b; {{ $settings->tagline ? '' : 'display: none;' }}">{{ $settings->tagline }}</div>
                                        </div>
                                        <div style="flex: 1; text-align: right; font-size: 10px; color: #334155; line-height: 1.3;">
                                            <div style="font-weight: 700; color: #0f172a; text-transform: uppercase;">Registered Office</div>
                                            <div class="prev_a4_address">{!! nl2br(e($settings->header_address ?: 'Shop 4 & 5, Rivera Arcade, Motera, Ahmedabad - 380005')) !!}</div>
                                            <div>
                                                <span class="prev_a4_phone">Phone: {{ $settings->phone ?: '7383056626' }}</span>
                                                <span class="prev_a4_phone_alt" style="{{ $settings->phone_alt ? '' : 'display: none;' }}"> / {{ $settings->phone_alt }}</span>
                                            </div>
                                            <div class="prev_a4_gstin" style="font-weight: 700; color: var(--accent);">GSTIN: {{ $settings->gstin ?: '24AABCU1234F1Z5' }}</div>
                                        </div>
                                    </div>
                                    <div style="background: var(--accent); color: #fff; text-align: center; padding: 4px 0; font-weight: 800; font-size: 12px; letter-spacing: 1.5px; border-radius: 2px; text-transform: uppercase;">
                                        TAX INVOICE
                                    </div>
                                </div>

                                {{-- LAYOUT 4: logo_right_address_left --}}
                                <div id="prev_a4_hdr_logo_right_address_left" class="prev-a4-header" style="{{ $settings->getHeaderLayout() === 'logo_right_address_left' ? '' : 'display: none;' }}; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid var(--accent);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div style="flex: 1; font-size: 10.5px; color: #334155;">
                                            <div class="prev_a4_store_name" style="font-size: 16px; font-weight: 800; color: #0f172a; text-transform: uppercase;">{{ $settings->store_name ?: 'URBAN PETS' }}</div>
                                            <div class="prev_a4_tagline" style="font-size: 10px; font-weight: 600; color: #64748b; {{ $settings->tagline ? '' : 'display: none;' }}">{{ $settings->tagline }}</div>
                                            <div class="prev_a4_address" style="margin-top: 2px;">{!! nl2br(e($settings->header_address ?: 'Shop 4 & 5, Rivera Arcade, Motera, Ahmedabad - 380005')) !!}</div>
                                            <div>
                                                <span class="prev_a4_phone">Tel: {{ $settings->phone ?: '7383056626' }}</span>
                                                <span class="prev_a4_phone_alt" style="{{ $settings->phone_alt ? '' : 'display: none;' }}"> / {{ $settings->phone_alt }}</span>
                                            </div>
                                            <div class="prev_a4_gstin" style="font-weight: 700; color: var(--accent);">GSTIN: {{ $settings->gstin ?: '24AABCU1234F1Z5' }}</div>
                                        </div>
                                        <div style="flex: 0 0 160px; text-align: right;">
                                            <div class="prev_a4_logo_box" style="{{ $settings->show_logo ? '' : 'display: none;' }}; margin-bottom: 4px;">
                                                <img class="prev_a4_logo_img" src="{{ $settings->logo_path ?: 'https://placehold.co/120x60?text=LOGO' }}" alt="Logo" style="max-width: {{ $settings->logo_width ?? 120 }}px; height: auto;">
                                            </div>
                                            <div style="display: inline-block; background: var(--accent); color: #fff; padding: 3px 10px; font-weight: 800; font-size: 11px; letter-spacing: 1px; border-radius: 3px; text-transform: uppercase;">
                                                TAX INVOICE
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ── 2. BILL TO, SHIP TO & INVOICE DETAILS CARDS ──────────────── --}}
                                <div style="display: flex; gap: 10px; margin-bottom: 12px; align-items: stretch;">
                                    {{-- Bill To Card --}}
                                    <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 10px; background: #f8fafc;">
                                        <div style="font-weight: 700; font-size: 10.5px; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px; text-transform: uppercase;">
                                            Details of Receiver | Bill To:
                                        </div>
                                        <div style="font-size: 10.5px; line-height: 1.45;">
                                            <div>Name: <strong>{{ $sampleBill?->customer?->name ?: 'Ankit Sharma' }}</strong></div>
                                            <div>Mobile: {{ $sampleBill?->customer?->phone ?: '9898012345' }}</div>
                                            <div>Address: Satellite, Ahmedabad, Gujarat - 380015</div>
                                            <div>GSTIN: <span style="font-weight: bold; color: #475569;">Unregistered</span></div>
                                        </div>
                                    </div>

                                    {{-- Ship To Card (Only for logo_left_address_below if enabled) --}}
                                    <div id="prev_a4_ship_to_card" style="{{ ($settings->show_ship_to && $settings->getHeaderLayout() === 'logo_left_address_below') ? '' : 'display: none;' }}; flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 10px; background: #f8fafc;">
                                        <div style="font-weight: 700; font-size: 10.5px; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px; text-transform: uppercase;">
                                            Details of Consignee | Ship To:
                                        </div>
                                        <div style="font-size: 10.5px; line-height: 1.45;">
                                            <div>Name: <strong>{{ $sampleBill?->customer?->name ?: 'Ankit Sharma' }}</strong></div>
                                            <div>Mobile: {{ $sampleBill?->customer?->phone ?: '9898012345' }}</div>
                                            <div>Address: Satellite, Ahmedabad, Gujarat - 380015</div>
                                            <div>State: 24 - Gujarat</div>
                                        </div>
                                    </div>

                                    {{-- Invoice Details Card (Visible when header is logo_left_address_right, centered, or logo_right_address_left) --}}
                                    <div id="prev_a4_invoice_meta_card" class="prev-a4-meta-box" style="{{ $settings->getHeaderLayout() !== 'logo_left_address_below' ? '' : 'display: none;' }}; flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 10px; background: #f8fafc;">
                                        <div style="font-weight: 700; font-size: 10.5px; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 4px; text-transform: uppercase;">
                                            Invoice Details:
                                        </div>
                                        <div style="font-size: 10.5px; line-height: 1.45;">
                                            <table style="width: 100%;">
                                                <tr><td style="width: 95px; color: #64748b;">Invoice No:</td><td><strong>{{ $sampleBill?->bill_number ?: 'EINV-TEST-1791271882' }}</strong></td></tr>
                                                <tr><td style="color: #64748b;">Date:</td><td>{{ now()->format('d/m/Y') }}</td></tr>
                                                <tr><td style="color: #64748b;">Place of Supply:</td><td>24 - Gujarat</td></tr>
                                                <tr><td style="color: #64748b;">Reverse Charge:</td><td>No</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                {{-- ── 3. ITEMS TABLE ─────────────────────────────────────────────── --}}
                                <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1px solid #cbd5e1; margin-bottom: 10px;">
                                    <thead>
                                        <tr style="background: var(--accent); color: #fff;">
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 28px; text-align: center;">#</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; text-align: left;">Item Description</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 55px; text-align: center;">HSN</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 45px; text-align: right;">Qty</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 55px; text-align: right;">Rate</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 45px; text-align: right;">Disc</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 55px; text-align: right;">Taxable</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 40px; text-align: center;">GST%</th>
                                            <th style="padding: 4px; border: 1px solid #cbd5e1; width: 65px; text-align: right;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: center;">1</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; font-weight: 600;">Royal Canin Maxi Puppy 4kg</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: center; color: #64748b;">23091000</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">1.00</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">₹2,450.00</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right; color: #dc2626;">₹150.00</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">₹1,949.15</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: center;">18%</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right; font-weight: 700;">₹2,300.00</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: center;">2</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; font-weight: 600;">Gnawlers Calcium Milk Bones</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: center; color: #64748b;">23099090</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">2.00</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">₹180.00</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">₹0.00</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right;">₹321.43</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: center;">12%</td>
                                            <td style="padding: 4px; border: 1px solid #e2e8f0; text-align: right; font-weight: 700;">₹360.00</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background: #f1f5f9; font-weight: 700;">
                                            <td colspan="3" style="padding: 4px; border: 1px solid #cbd5e1; text-align: right;">Total:</td>
                                            <td style="padding: 4px; border: 1px solid #cbd5e1; text-align: right;">3.00</td>
                                            <td colspan="2" style="padding: 4px; border: 1px solid #cbd5e1;"></td>
                                            <td style="padding: 4px; border: 1px solid #cbd5e1; text-align: right;">₹2,270.58</td>
                                            <td style="padding: 4px; border: 1px solid #cbd5e1;"></td>
                                            <td style="padding: 4px; border: 1px solid #cbd5e1; text-align: right;">₹2,660.00</td>
                                        </tr>
                                    </tfoot>
                                </table>

                                {{-- ── 4. TAX SUMMARY & TOTALS SECTION ────────────────────────────── --}}
                                <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start;">
                                    <div id="prev_a4_tax_summary_box" style="{{ $settings->show_tax_summary_table ? '' : 'display: none;' }}; flex: 1; border: 1px solid #cbd5e1; border-radius: 3px;">
                                        <div style="background: #f1f5f9; font-weight: 700; font-size: 10px; padding: 3px 6px; border-bottom: 1px solid #cbd5e1; text-transform: uppercase;">
                                            Rate-wise GST Summary (कर विवरण)
                                        </div>
                                        <table style="width: 100%; font-size: 9.5px; border-collapse: collapse; text-align: right;">
                                            <tr style="background: #f8fafc; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                                                <th style="padding: 2px 4px; text-align: left;">Rate</th>
                                                <th style="padding: 2px 4px;">Taxable</th>
                                                <th style="padding: 2px 4px;">CGST</th>
                                                <th style="padding: 2px 4px;">SGST</th>
                                                <th style="padding: 2px 4px;">Total Tax</th>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 2px 4px; text-align: left; font-weight: bold;">12%</td>
                                                <td style="padding: 2px 4px;">₹321.43</td>
                                                <td style="padding: 2px 4px;">₹19.29</td>
                                                <td style="padding: 2px 4px;">₹19.29</td>
                                                <td style="padding: 2px 4px; font-weight: bold;">₹38.57</td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 2px 4px; text-align: left; font-weight: bold;">18%</td>
                                                <td style="padding: 2px 4px;">₹1,949.15</td>
                                                <td style="padding: 2px 4px;">₹175.42</td>
                                                <td style="padding: 2px 4px;">₹175.42</td>
                                                <td style="padding: 2px 4px; font-weight: bold;">₹350.85</td>
                                            </tr>
                                            <tr style="background: #f1f5f9; font-weight: bold;">
                                                <td style="padding: 2px 4px; text-align: left;">Total</td>
                                                <td style="padding: 2px 4px;">₹2,270.58</td>
                                                <td style="padding: 2px 4px;">₹194.71</td>
                                                <td style="padding: 2px 4px;">₹194.71</td>
                                                <td style="padding: 2px 4px;">₹389.42</td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style="flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; font-size: 10.5px;">
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 3px 6px;">Taxable Amount:</td>
                                                <td style="padding: 3px 6px; text-align: right; font-weight: 600;">₹2,270.58</td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 3px 6px;">Total GST (CGST+SGST):</td>
                                                <td style="padding: 3px 6px; text-align: right; font-weight: 600;">₹389.42</td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 3px 6px;">Discount:</td>
                                                <td style="padding: 3px 6px; text-align: right; color: #dc2626;">-₹150.00</td>
                                            </tr>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 3px 6px;">Round Off:</td>
                                                <td style="padding: 3px 6px; text-align: right;">₹0.00</td>
                                            </tr>
                                            <tr style="background: var(--accent); color: #fff; font-size: 12px; font-weight: 900;">
                                                <td style="padding: 4px 6px;">GRAND TOTAL:</td>
                                                <td style="padding: 4px 6px; text-align: right;">₹2,660.00</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                {{-- Amount in words --}}
                                <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 3px; padding: 4px 8px; font-size: 10px; margin-bottom: 10px;">
                                    <strong>Amount in Words:</strong> Rupees Two Thousand Six Hundred Sixty Only
                                </div>

                                {{-- ── 5. PAYMENT & SIGNATURE SECTION ─────────────────────────────── --}}
                                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                    <div id="prev_a4_payment_box" style="{{ $settings->show_payment_details ? '' : 'display: none;' }}; flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 8px; font-size: 10px; background: #fafafa;">
                                        <div style="font-weight: 700; color: var(--accent); border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; margin-bottom: 4px;">
                                            💳 PAYMENT & UPI DETAILS
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <div>Mode: <strong>Cash / UPI</strong></div>
                                                <div style="color: #64748b;">Ref: {{ $sampleBill?->bill_number ?: 'SB-2026-0009' }}</div>
                                                <div class="prev_a4_upi_vpa_text" style="font-weight: 600; color: #0f172a; margin-top: 2px;">
                                                    UPI: {{ $settings->upi_id ?: '7383056626@okbizaxis' }}
                                                </div>
                                            </div>
                                            <div>
                                                <img class="prev_a4_upi_qr_img" src="{{ $settings->getUpiQrUrl(2660.00, $sampleBill?->bill_number ?: 'SB-2026-0009') }}" alt="UPI QR" style="width: 58px; height: 58px; border: 1px solid #cbd5e1; border-radius: 3px; background: #fff;">
                                            </div>
                                        </div>
                                    </div>

                                    <div id="prev_a4_sign_box" style="{{ $settings->show_signature_box ? '' : 'display: none;' }}; flex: 1; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 8px; text-align: right; display: flex; flex-direction: column; justify-content: space-between; min-height: 80px; background: #fafafa;">
                                        <div style="font-size: 10px; font-weight: 700; color: #334155;">
                                            For, <span class="prev_a4_store_name">{{ $settings->store_name ?: 'URBAN PETS' }}</span>
                                        </div>
                                        <div style="font-size: 9.5px; color: #64748b; border-top: 1px dashed #94a3b8; padding-top: 2px; margin-top: 30px;">
                                            Authorised Signatory
                                        </div>
                                    </div>
                                </div>

                                {{-- ── 6. COMPLIANCE NOTES & TERMS ────────────────────────────────── --}}
                                <div id="prev_a4_compliance_box" style="font-size: 9px; color: #475569; margin-bottom: 4px; line-height: 1.3; border-top: 1px solid #e2e8f0; padding-top: 4px;">
                                    <div id="prev_a4_compliance_text">{!! nl2br(e($settings->compliance_notes ?: 'Whether tax is payable on reverse charge basis: NO | Certified that the particulars given above are true and correct.')) !!}</div>
                                </div>

                                <div id="prev_a4_terms_box" style="font-size: 9px; color: #64748b; line-height: 1.3; border-top: 1px dashed #cbd5e1; padding-top: 4px;">
                                    <div style="font-weight: 700; color: #334155; margin-bottom: 2px;">Terms & Conditions:</div>
                                    <div id="prev_a4_terms_text">{!! nl2br(e($settings->terms_conditions ?: "1. Goods once sold will not be taken back without original bill.\n2. Subject to local jurisdiction only.")) !!}</div>
                                </div>

                            </div>
                        </div>{{-- End a4-preview-container --}}
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@section('css')
<style>
    .custom-range::-webkit-slider-thumb {
        background: #ffc107;
    }
    .preview-paper-80mm {
        width: 80mm !important;
        max-width: 320px !important;
    }
    .preview-paper-58mm {
        width: 58mm !important;
        max-width: 240px !important;
    }
    .preview-paper-102mm {
        width: 102mm !important;
        max-width: 410px !important;
    }
    .preview-paper-a4 {
        width: 100% !important;
        max-width: 580px !important;
    }
    .preview-paper-a5 {
        width: 100% !important;
        max-width: 440px !important;
    }
    @media print {
        body * {
            visibility: hidden;
        }
        #receipt-preview-box, #receipt-preview-box * {
            visibility: visible;
        }
        #receipt-preview-box {
            position: absolute;
            left: 0;
            top: 0;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
    }
</style>
@stop

@section('js')
<script>
    const previewBox = document.getElementById('receipt-preview-box');
    const a4PreviewBox = document.getElementById('a4-receipt-preview-box');
    const previewSizeBadge = document.getElementById('preview-size-badge');
    let currentPreviewMode = '{{ $settings->isA4GstInvoice() ? "a4" : "thermal" }}';

    function switchPreviewMode(mode) {
        currentPreviewMode = mode;
        const thermalContainer = document.getElementById('thermal-preview-container');
        const a4Container = document.getElementById('a4-preview-container');
        const btnThermal = document.getElementById('btn_toggle_thermal_preview');
        const btnA4 = document.getElementById('btn_toggle_a4_preview');

        if (mode === 'a4') {
            if (thermalContainer) thermalContainer.style.display = 'none';
            if (a4Container) a4Container.style.display = 'block';
            if (btnThermal) { btnThermal.className = 'btn btn-outline-light font-weight-bold'; }
            if (btnA4) { btnA4.className = 'btn btn-warning font-weight-bold'; }
            if (previewSizeBadge) previewSizeBadge.textContent = 'A4 Sheet';
        } else {
            if (thermalContainer) thermalContainer.style.display = 'block';
            if (a4Container) a4Container.style.display = 'none';
            if (btnThermal) { btnThermal.className = 'btn btn-warning font-weight-bold'; }
            if (btnA4) { btnA4.className = 'btn btn-outline-light font-weight-bold'; }
            updatePreviewLayout();
        }
    }

    function updatePreviewLayout() {
        if (currentPreviewMode === 'a4') {
            if (previewSizeBadge) previewSizeBadge.textContent = 'A4 Sheet';
            return;
        }
        const paperSize = document.getElementById('input_paper_size').value;
        const fontSize = document.getElementById('input_font_size').value;

        // Apply width class
        previewBox.classList.remove('preview-paper-80mm', 'preview-paper-58mm', 'preview-paper-102mm', 'preview-paper-a4', 'preview-paper-a5');
        if (paperSize === '58mm') {
            previewBox.classList.add('preview-paper-58mm');
            previewSizeBadge.textContent = '58mm Roll';
        } else if (paperSize === '102mm') {
            previewBox.classList.add('preview-paper-102mm');
            previewSizeBadge.textContent = '102mm (TSC 4")';
        } else if (paperSize === 'a4') {
            previewBox.classList.add('preview-paper-a4');
            previewSizeBadge.textContent = 'A4 Sheet';
        } else if (paperSize === 'a5') {
            previewBox.classList.add('preview-paper-a5');
            previewSizeBadge.textContent = 'A5 Sheet';
        } else {
            previewBox.classList.add('preview-paper-80mm');
            previewSizeBadge.textContent = '80mm Roll';
        }

        // Apply font size
        if (fontSize === 'small') {
            previewBox.style.fontSize = '10.5px';
        } else if (fontSize === 'large') {
            previewBox.style.fontSize = '13.5px';
        } else {
            previewBox.style.fontSize = '12px';
        }
    }

    // Header layout selection
    function chooseHeaderLayout(layout) {
        const radio = document.getElementById('layout_' + layout);
        if (radio) {
            radio.checked = true;
        }
        document.querySelectorAll('.header-layout-choice').forEach(card => {
            card.classList.remove('border-primary', 'bg-light', 'shadow-sm');
        });
        const activeCard = document.getElementById('card_layout_' + layout);
        if (activeCard) {
            activeCard.classList.add('border-primary', 'bg-light', 'shadow-sm');
        }

        // Toggle A4 headers
        document.querySelectorAll('.prev-a4-header').forEach(hdr => {
            hdr.style.display = 'none';
        });
        const activeA4Hdr = document.getElementById('prev_a4_hdr_' + layout);
        if (activeA4Hdr) {
            activeA4Hdr.style.display = 'block';
        }

        // Toggle invoice meta card & ship to card beside Bill To
        const metaCard = document.getElementById('prev_a4_invoice_meta_card');
        const shipToCard = document.getElementById('prev_a4_ship_to_card');
        const showShipTo = document.getElementById('input_show_ship_to')?.checked;
        if (layout === 'logo_left_address_below') {
            if (metaCard) metaCard.style.display = 'none';
            if (shipToCard) shipToCard.style.display = showShipTo ? 'block' : 'none';
        } else {
            // For logo_left_address_right (Image 3 Style), Invoice Details replaces Ship To / Consignee card
            if (metaCard) metaCard.style.display = 'block';
            if (shipToCard) shipToCard.style.display = 'none';
        }

        // Auto switch to A4 preview to show layout
        switchPreviewMode('a4');
    }

    // Accent color picker
    function pickAccentColor(hex) {
        document.getElementById('input_accent_color').value = hex;
        document.getElementById('input_accent_color_picker').value = hex;
        applyAccentColor(hex);
    }

    function applyAccentColor(hex) {
        if (!hex) return;
        if (a4PreviewBox) {
            a4PreviewBox.style.setProperty('--accent', hex);
        }
    }

    document.getElementById('input_accent_color')?.addEventListener('input', function() {
        applyAccentColor(this.value);
        const picker = document.getElementById('input_accent_color_picker');
        if (picker && /^#[0-9A-F]{6}$/i.test(this.value)) {
            picker.value = this.value;
        }
    });

    document.getElementById('input_accent_color_picker')?.addEventListener('input', function() {
        document.getElementById('input_accent_color').value = this.value;
        applyAccentColor(this.value);
    });

    // Invoice format radio change
    document.querySelectorAll('input[name="invoice_format"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                if (this.value === 'a4_gst') {
                    switchPreviewMode('a4');
                } else {
                    switchPreviewMode('thermal');
                }
            }
        });
    });

    // Header layout radio change directly
    document.querySelectorAll('input[name="header_layout"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                chooseHeaderLayout(this.value);
            }
        });
    });

    // Synchronized real-time updates across Thermal and A4 previews
    document.getElementById('input_store_name').addEventListener('input', function() {
        const val = this.value || 'URBAN PETS';
        document.getElementById('prev_store_name').textContent = val;
        document.querySelectorAll('.prev_a4_store_name').forEach(el => el.textContent = val);
    });

    document.getElementById('input_tagline').addEventListener('input', function() {
        const el = document.getElementById('prev_tagline');
        el.textContent = this.value;
        el.style.display = this.value.trim() ? 'block' : 'none';
        document.querySelectorAll('.prev_a4_tagline').forEach(a4El => {
            a4El.textContent = this.value;
            a4El.style.display = this.value.trim() ? 'block' : 'none';
        });
    });

    document.getElementById('input_header_address').addEventListener('input', function() {
        const val = this.value || 'Rivera Arcade, Motera, Ahmedabad';
        document.getElementById('prev_address').innerText = val;
        document.querySelectorAll('.prev_a4_address').forEach(a4El => a4El.innerText = val);
    });

    document.getElementById('input_phone').addEventListener('input', function() {
        const val = this.value || '7383056626';
        document.getElementById('prev_phone').innerHTML = 'Tel: ' + val;
        document.querySelectorAll('.prev_a4_phone').forEach(a4El => a4El.textContent = 'Tel: ' + val);
    });

    document.getElementById('input_phone_alt').addEventListener('input', function() {
        const el = document.getElementById('prev_phone_alt');
        const show = !!this.value.trim();
        if (show) {
            el.textContent = ' / ' + this.value.trim();
            el.style.display = 'inline';
        } else {
            el.style.display = 'none';
        }
        document.querySelectorAll('.prev_a4_phone_alt').forEach(a4El => {
            a4El.textContent = ' / ' + this.value.trim();
            a4El.style.display = show ? 'inline' : 'none';
        });
    });

    document.getElementById('input_email')?.addEventListener('input', function() {
        const show = !!this.value.trim();
        document.querySelectorAll('.prev_a4_email').forEach(a4El => a4El.textContent = this.value.trim());
        document.querySelectorAll('.prev_a4_email_wrap').forEach(wrap => wrap.style.display = show ? 'inline' : 'none');
    });

    document.getElementById('input_gstin').addEventListener('input', function() {
        const val = (this.value || '24AABCU1234F1Z5').toUpperCase();
        document.getElementById('prev_gstin').textContent = 'GSTIN: ' + val;
        document.querySelectorAll('.prev_a4_gstin').forEach(a4El => a4El.textContent = 'GSTIN: ' + val);
    });

    // Logo toggles & sizing
    document.getElementById('input_show_logo').addEventListener('change', function() {
        document.getElementById('prev_logo_container').style.display = this.checked ? 'block' : 'none';
        document.querySelectorAll('.prev_a4_logo_box').forEach(box => box.style.display = this.checked ? 'block' : 'none');
    });

    document.getElementById('input_logo_width').addEventListener('input', function() {
        document.getElementById('logo_width_val').textContent = this.value;
        document.getElementById('prev_logo_img').style.maxWidth = this.value + 'px';
        document.querySelectorAll('.prev_a4_logo_img').forEach(img => img.style.maxWidth = this.value + 'px');
    });

    document.getElementById('input_logo_file').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('prev_logo_img').src = ev.target.result;
                document.querySelectorAll('.prev_a4_logo_img').forEach(img => img.src = ev.target.result);
                document.getElementById('input_show_logo').checked = true;
                document.getElementById('prev_logo_container').style.display = 'block';
                document.querySelectorAll('.prev_a4_logo_box').forEach(box => box.style.display = 'block');
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    // Modular A4 toggles
    document.getElementById('input_show_ship_to')?.addEventListener('change', function() {
        const layout = document.querySelector('input[name="header_layout"]:checked')?.value || 'logo_left_address_below';
        const card = document.getElementById('prev_a4_ship_to_card');
        if (card) {
            card.style.display = (this.checked && layout === 'logo_left_address_below') ? 'block' : 'none';
        }
    });

    document.getElementById('input_show_tax_summary_table')?.addEventListener('change', function() {
        const box = document.getElementById('prev_a4_tax_summary_box');
        if (box) box.style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_show_payment_details')?.addEventListener('change', function() {
        const box = document.getElementById('prev_a4_payment_box');
        if (box) box.style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_show_signature_box')?.addEventListener('change', function() {
        const box = document.getElementById('prev_a4_sign_box');
        if (box) box.style.display = this.checked ? 'flex' : 'none';
    });

    document.getElementById('input_show_support_qr')?.addEventListener('change', function() {
        document.querySelectorAll('.prev_a4_support_qr').forEach(el => el.style.display = this.checked ? 'block' : 'none');
    });

    document.getElementById('input_support_qr_payload')?.addEventListener('input', function() {
        const payload = encodeURIComponent(this.value.trim() || 'https://wa.me/917383056626');
        const url = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=4&data=${payload}`;
        document.querySelectorAll('.prev_a4_support_qr_img').forEach(img => img.src = url);
    });

    document.getElementById('input_compliance_notes')?.addEventListener('input', function() {
        const textEl = document.getElementById('prev_a4_compliance_text');
        if (textEl) textEl.innerText = this.value || 'Whether tax is payable on reverse charge basis: NO | Certified that the particulars given above are true and correct.';
    });

    document.getElementById('input_terms_conditions')?.addEventListener('input', function() {
        const textEl = document.getElementById('prev_a4_terms_text');
        if (textEl) textEl.innerText = this.value || '1. Goods once sold will not be taken back without original bill.\n2. Subject to local jurisdiction only.';
    });

    // Thermal features
    document.getElementById('input_show_customer_pet_name')?.addEventListener('change', function() {
        const petRow = document.getElementById('prev_pet_row');
        if (petRow) petRow.style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_show_hsn_code')?.addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.prev_hsn_tag').forEach(el => {
            el.style.display = checked ? 'inline' : 'none';
        });
    });

    document.getElementById('input_show_tax_breakup')?.addEventListener('change', function() {
        const display = this.checked ? 'table-row' : 'none';
        const cgstRow = document.getElementById('prev_tax_split_cgst');
        const sgstRow = document.getElementById('prev_tax_split_sgst');
        if (cgstRow) cgstRow.style.display = display;
        if (sgstRow) sgstRow.style.display = display;
    });

    document.getElementById('input_show_discount')?.addEventListener('change', function() {
        const discRow = document.getElementById('prev_disc_sample_1');
        if (discRow) discRow.style.display = this.checked ? 'table-row' : 'none';
    });

    document.getElementById('input_show_barcode')?.addEventListener('change', function() {
        const barBox = document.getElementById('prev_barcode_container');
        if (barBox) barBox.style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_show_upi_qr')?.addEventListener('change', function() {
        const upiBox = document.getElementById('prev_upi_container');
        if (upiBox) upiBox.style.display = this.checked ? 'block' : 'none';
    });

    function updateUpiQr() {
        const upiBox = document.getElementById('prev_upi_container');
        const prevUpiVpa = document.getElementById('prev_upi_vpa_text');
        const prevUpiImg = document.getElementById('prev_upi_qr_img');
        const upiId = document.getElementById('input_upi_id')?.value.trim() || '7383056626@okbizaxis';
        const payeeName = document.getElementById('input_upi_payee_name')?.value.trim() || document.getElementById('input_store_name')?.value.trim() || 'Urban Pets';

        if (prevUpiVpa) prevUpiVpa.textContent = 'UPI: ' + upiId;
        const upiString = `upi://pay?pa=${encodeURIComponent(upiId)}&pn=${encodeURIComponent(payeeName)}&am=2660.00&tr=SB-2026-0009&tn=Bill%20SB-2026-0009&cu=INR`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=4&data=${encodeURIComponent(upiString)}`;
        if (prevUpiImg) prevUpiImg.src = qrUrl;

        // Also update A4 UPI QR and VPA
        document.querySelectorAll('.prev_a4_upi_vpa_text').forEach(el => el.textContent = 'UPI: ' + upiId);
        document.querySelectorAll('.prev_a4_upi_qr_img').forEach(img => img.src = qrUrl);
    }

    document.getElementById('input_upi_id')?.addEventListener('input', updateUpiQr);
    document.getElementById('input_upi_payee_name')?.addEventListener('input', updateUpiQr);

    document.getElementById('input_paper_size').addEventListener('change', updatePreviewLayout);
    document.getElementById('input_font_size').addEventListener('change', updatePreviewLayout);

    document.getElementById('input_footer_policy').addEventListener('input', function() {
        document.getElementById('prev_footer_policy').innerText = this.value;
    });

    document.getElementById('input_footer_note').addEventListener('input', function() {
        document.getElementById('prev_footer_note').innerText = this.value;
    });

    function resetToDefaults() {
        if (!confirm('Reset all receipt customizations to factory defaults?')) return;
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
        const setCheck = (id, val) => { const el = document.getElementById(id); if (el) el.checked = val; };
        const trigger = (id, ev = 'input') => { document.getElementById(id)?.dispatchEvent(new Event(ev)); };

        setVal('input_store_name', 'URBAN PETS');
        setVal('input_tagline', 'Complete Pet Care & Supplies');
        setCheck('input_show_logo', false);
        setVal('input_header_address', '');
        setVal('input_phone', '7383056626');
        setVal('input_phone_alt', '');
        setVal('input_email', '');
        setVal('input_gstin', '');
        setCheck('input_show_customer_pet_name', true);
        setCheck('input_show_hsn_code', true);
        setCheck('input_show_tax_breakup', true);
        setCheck('input_show_discount', true);
        setCheck('input_show_upi_qr', true);
        setVal('input_upi_id', '7383056626@okbizaxis');
        setVal('input_upi_payee_name', 'Urban Pets');
        setCheck('input_show_barcode', true);
        setVal('input_paper_size', '{{ $docType === "stock_transfer" ? "a4" : "80mm" }}');
        setVal('input_font_size', 'normal');
        setVal('input_footer_policy', "{{ $docType === 'stock_transfer' ? 'Goods dispatched in sound condition.\nPlease inspect all items upon receipt.' : 'Exchange valid within 7 days with original bill.\nNo return on opened treats, medicines or frozen food.' }}");
        setVal('input_footer_note', "{{ $docType === 'stock_transfer' ? 'Internal Transfer Note — Not For Commercial Sale' : 'Thank you for shopping at Urban Pets!\n*** Have an Awesome Day! ***' }}");

        // A4 Defaults
        chooseHeaderLayout('logo_left_address_below');
        pickAccentColor('#1e40af');
        setCheck('input_show_ship_to', true);
        setCheck('input_show_tax_summary_table', true);
        setCheck('input_show_payment_details', true);
        setCheck('input_show_signature_box', true);
        setCheck('input_show_support_qr', true);

        // Trigger updates
        trigger('input_store_name');
        trigger('input_tagline');
        trigger('input_phone');
        trigger('input_phone_alt');
        trigger('input_email');
        trigger('input_show_customer_pet_name', 'change');
        trigger('input_show_hsn_code', 'change');
        trigger('input_show_tax_breakup', 'change');
        trigger('input_show_discount', 'change');
        trigger('input_show_barcode', 'change');
        trigger('input_show_upi_qr', 'change');
        trigger('input_paper_size', 'change');
        trigger('input_footer_policy');
        trigger('input_footer_note');
    }

    function printSampleReceipt() {
        window.print();
    }

    // AJAX Save handler with clear feedback
    if (window.jQuery) {
        window.jQuery('#receipt-designer-form').on('submit', function (e) {
            e.preventDefault();
            var form = this;
            var $form = window.jQuery(form);
            var $btn = $form.find('#btn-save-receipt-settings');
            var origHtml = $btn.html();

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving Profile...');

            var formData = new FormData(form);

            window.jQuery.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                success: function (res) {
                    $btn.removeClass('btn-success').addClass('btn-info').html('<i class="fas fa-check mr-1"></i> Saved Successfully!');
                    setTimeout(function () {
                        $btn.prop('disabled', false).removeClass('btn-info').addClass('btn-success').html(origHtml);
                    }, 2000);

                    var msg = (res && res.message) ? res.message : 'Print profile settings saved successfully!';
                    if (window.toastr) {
                        window.toastr.success(msg, 'Print Profile Saved');
                    } else {
                        alert(msg);
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).html(origHtml);
                    var errMsg = 'Failed to save print profile. Please check required fields.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            var errArr = [];
                            window.jQuery.each(xhr.responseJSON.errors, function (k, v) {
                                errArr.push(Array.isArray(v) ? v[0] : v);
                            });
                            errMsg = errArr.join('<br>');
                        } else if (xhr.responseJSON.message) {
                            errMsg = xhr.responseJSON.message;
                        }
                    }
                    if (window.toastr) {
                        window.toastr.error(errMsg, 'Save Error');
                    } else {
                        alert(errMsg.replace(/<br>/g, '\n'));
                    }
                }
            });
        });
    }

    // Initialize layout on page load
    updatePreviewLayout();
</script>
@stop
