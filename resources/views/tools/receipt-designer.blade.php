@extends('adminlte::page')

@section('title', 'Invoice Print Designer (बिल प्रिंट कस्टमाइज़र)')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-print text-warning mr-2"></i> Invoice Print Designer <span class="text-muted" style="font-size: 18px; font-weight: normal;">(बिल प्रिंट कस्टमाइज़र)</span>
            </h1>
            <p class="text-muted small mb-0">
                Customize store headers, multi-line address, HSN visibility, UPI payment QR code, return policies & paper width without any developer dependency.
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <button type="button" class="btn btn-outline-primary btn-sm shadow-sm font-weight-bold mr-2" onclick="printSampleReceipt()">
                <i class="fas fa-print mr-1"></i> Print Sample Receipt
            </button>
            <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-arrow-left mr-1"></i> Back to Bills
            </a>
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

    <form id="receipt-designer-form" action="{{ route('tools.receipt-designer.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            {{-- Left Column: Settings Customizer --}}
            <div class="col-lg-7 col-md-12 mb-4">
                
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
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Primary Phone / Helpline</label>
                                <input type="text" name="phone" id="input_phone" class="form-control" value="{{ old('phone', $settings->phone) }}" placeholder="7383056626">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">Alternate Phone</label>
                                <input type="text" name="phone_alt" id="input_phone_alt" class="form-control" value="{{ old('phone_alt', $settings->phone_alt) }}" placeholder="Optional second number">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold text-secondary small text-uppercase">GSTIN / Tax ID</label>
                                <input type="text" name="gstin" id="input_gstin" class="form-control text-uppercase" value="{{ old('gstin', $settings->gstin) }}" placeholder="24AAAAA0000A1Z5">
                                <small class="text-muted">Leave blank to use branch GST.</small>
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
                            <button type="submit" class="btn btn-success font-weight-bold px-4 shadow">
                                <i class="fas fa-save mr-1"></i> Save Receipt Settings
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Live Interactive Thermal Receipt Preview --}}
            <div class="col-lg-5 col-md-12 mb-4">
                <div style="position: sticky; top: 15px;">
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="font-weight-bold small">
                                <i class="fas fa-receipt mr-1 text-warning"></i> LIVE RECEIPT PREVIEW
                            </span>
                            <span id="preview-size-badge" class="badge badge-warning text-uppercase font-weight-bold">
                                80mm Roll
                            </span>
                        </div>
                    </div>

                    {{-- Live Receipt Preview Container --}}
                    <div class="preview-scroll-wrapper" style="max-height: 85vh; overflow-y: auto; background: #525659; padding: 20px 10px; border-radius: 8px; box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);">
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

                            <div class="text-center font-weight-bold text-uppercase" style="font-size: 12px; letter-spacing: 1px;">
                                TAX INVOICE
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

                            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

                            {{-- Dynamic UPI QR Preview --}}
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

                            {{-- Barcode Section --}}
                            <div id="prev_barcode_container" class="text-center my-2" style="{{ $settings->show_barcode ? '' : 'display: none;' }}">
                                <div style="display: inline-block; height: 32px; width: 170px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
                                <div style="font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-top: 2px;">
                                    * {{ $sampleBill?->bill_number ?: 'SB-2026-0009' }} *
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
    const previewSizeBadge = document.getElementById('preview-size-badge');

    function updatePreviewLayout() {
        const paperSize = document.getElementById('input_paper_size').value;
        const fontSize = document.getElementById('input_font_size').value;

        // Apply width class
        previewBox.classList.remove('preview-paper-80mm', 'preview-paper-58mm', 'preview-paper-a4', 'preview-paper-a5');
        if (paperSize === '58mm') {
            previewBox.classList.add('preview-paper-58mm');
            previewSizeBadge.textContent = '58mm Roll';
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

    // Attach real-time input event listeners for live dynamic preview
    document.getElementById('input_store_name').addEventListener('input', function() {
        document.getElementById('prev_store_name').textContent = this.value || 'URBAN PETS';
    });

    document.getElementById('input_tagline').addEventListener('input', function() {
        const el = document.getElementById('prev_tagline');
        el.textContent = this.value;
        el.style.display = this.value.trim() ? 'block' : 'none';
    });

    document.getElementById('input_header_address').addEventListener('input', function() {
        document.getElementById('prev_address').innerText = this.value || 'Rivera Arcade, Motera, Ahmedabad';
    });

    document.getElementById('input_phone').addEventListener('input', function() {
        document.getElementById('prev_phone').innerHTML = 'Tel: ' + (this.value || '7383056626');
    });

    document.getElementById('input_phone_alt').addEventListener('input', function() {
        const el = document.getElementById('prev_phone_alt');
        if (this.value.trim()) {
            el.textContent = ' / ' + this.value.trim();
            el.style.display = 'inline';
        } else {
            el.style.display = 'none';
        }
    });

    document.getElementById('input_gstin').addEventListener('input', function() {
        document.getElementById('prev_gstin').textContent = 'GSTIN: ' + (this.value || '24AABCU1234F1Z5').toUpperCase();
    });

    // Toggles
    document.getElementById('input_show_logo').addEventListener('change', function() {
        document.getElementById('prev_logo_container').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_logo_width').addEventListener('input', function() {
        document.getElementById('logo_width_val').textContent = this.value;
        document.getElementById('prev_logo_img').style.maxWidth = this.value + 'px';
    });

    // File preview for logo
    document.getElementById('input_logo_file').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('prev_logo_img').src = ev.target.result;
                document.getElementById('input_show_logo').checked = true;
                document.getElementById('prev_logo_container').style.display = 'block';
            };
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    document.getElementById('input_show_customer_pet_name').addEventListener('change', function() {
        document.getElementById('prev_pet_row').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_show_hsn_code').addEventListener('change', function() {
        const checked = this.checked;
        document.querySelectorAll('.prev_hsn_tag').forEach(el => {
            el.style.display = checked ? 'inline' : 'none';
        });
    });

    document.getElementById('input_show_tax_breakup').addEventListener('change', function() {
        const display = this.checked ? 'table-row' : 'none';
        document.getElementById('prev_tax_split_cgst').style.display = display;
        document.getElementById('prev_tax_split_sgst').style.display = display;
    });

    document.getElementById('input_show_discount').addEventListener('change', function() {
        document.getElementById('prev_disc_sample_1').style.display = this.checked ? 'table-row' : 'none';
    });

    document.getElementById('input_show_barcode').addEventListener('change', function() {
        document.getElementById('prev_barcode_container').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('input_show_upi_qr').addEventListener('change', function() {
        document.getElementById('prev_upi_container').style.display = this.checked ? 'block' : 'none';
    });

    function updateUpiQr() {
        const upiId = document.getElementById('input_upi_id').value.trim() || '7383056626@okbizaxis';
        const payeeName = document.getElementById('input_upi_payee_name').value.trim() || document.getElementById('input_store_name').value.trim() || 'Urban Pets';
        document.getElementById('prev_upi_vpa_text').textContent = 'UPI: ' + upiId;
        
        const upiString = `upi://pay?pa=${encodeURIComponent(upiId)}&pn=${encodeURIComponent(payeeName)}&am=2660.00&tr=SB-2026-0009&tn=Bill%20SB-2026-0009&cu=INR`;
        document.getElementById('prev_upi_qr_img').src = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=4&data=${encodeURIComponent(upiString)}`;
    }

    document.getElementById('input_upi_id').addEventListener('input', updateUpiQr);
    document.getElementById('input_upi_payee_name').addEventListener('input', updateUpiQr);

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
        document.getElementById('input_store_name').value = 'URBAN PETS';
        document.getElementById('input_tagline').value = 'Complete Pet Care & Supplies';
        document.getElementById('input_show_logo').checked = false;
        document.getElementById('input_header_address').value = '';
        document.getElementById('input_phone').value = '7383056626';
        document.getElementById('input_phone_alt').value = '';
        document.getElementById('input_gstin').value = '';
        document.getElementById('input_show_customer_pet_name').checked = true;
        document.getElementById('input_show_hsn_code').checked = true;
        document.getElementById('input_show_tax_breakup').checked = true;
        document.getElementById('input_show_discount').checked = true;
        document.getElementById('input_show_upi_qr').checked = true;
        document.getElementById('input_upi_id').value = '7383056626@okbizaxis';
        document.getElementById('input_upi_payee_name').value = 'Urban Pets';
        document.getElementById('input_show_barcode').checked = true;
        document.getElementById('input_paper_size').value = '80mm';
        document.getElementById('input_font_size').value = 'normal';
        document.getElementById('input_footer_policy').value = "Exchange valid within 7 days with original bill.\nNo return on opened treats, medicines or frozen food.";
        document.getElementById('input_footer_note').value = "Thank you for shopping at Urban Pets!\n*** Have an Awesome Day! ***";

        // Trigger updates
        document.getElementById('input_store_name').dispatchEvent(new Event('input'));
        document.getElementById('input_tagline').dispatchEvent(new Event('input'));
        document.getElementById('input_phone').dispatchEvent(new Event('input'));
        document.getElementById('input_phone_alt').dispatchEvent(new Event('input'));
        document.getElementById('input_show_customer_pet_name').dispatchEvent(new Event('change'));
        document.getElementById('input_show_hsn_code').dispatchEvent(new Event('change'));
        document.getElementById('input_show_tax_breakup').dispatchEvent(new Event('change'));
        document.getElementById('input_show_discount').dispatchEvent(new Event('change'));
        document.getElementById('input_show_barcode').dispatchEvent(new Event('change'));
        document.getElementById('input_show_upi_qr').dispatchEvent(new Event('change'));
        document.getElementById('input_paper_size').dispatchEvent(new Event('change'));
        document.getElementById('input_footer_policy').dispatchEvent(new Event('input'));
        document.getElementById('input_footer_note').dispatchEvent(new Event('input'));
    }

    function printSampleReceipt() {
        window.print();
    }

    // Initialize layout on page load
    updatePreviewLayout();
</script>
@stop
