@extends('adminlte::page')

@section('title', 'WhatsApp Integration & Template Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="font-weight-bold text-dark mb-1">
                <i class="fab fa-whatsapp text-success mr-2"></i> WhatsApp Integration & Template Settings
            </h1>
            <p class="text-muted small mb-0">
                ChatOnClick Cloud API credentials, auto-send preferences, branch-wise templates, and real-time tester.
            </p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center">
            <a href="https://chatonclick.com/user/whatsapp/templates" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm shadow-sm font-weight-bold mr-2">
                <i class="fas fa-external-link-alt mr-1"></i> ChatOnClick Templates
            </a>
            <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-shopping-cart mr-1"></i> Sales Bills
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('status') }}
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

    @php
        $activeBranch = $branchId ? $branches->firstWhere('id', $branchId) : null;
    @endphp

    {{-- Branch Scope Switcher Bar --}}
    <div class="card shadow-sm border-0 mb-3 bg-white">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center flex-wrap my-1">
                    <span class="font-weight-bold mr-3 text-secondary small text-uppercase">
                        <i class="fas fa-store-alt text-success mr-1"></i> Configure WhatsApp For Branch:
                    </span>
                    @foreach($branches as $b)
                        <a href="{{ route('tools.whatsapp-settings.index', ['branch_id' => $b->id]) }}" 
                           class="btn btn-sm mr-2 mb-1 {{ $branchId == $b->id ? 'btn-success font-weight-bold shadow-sm' : 'btn-outline-secondary' }}">
                            🏪 {{ $b->name }}
                        </a>
                    @endforeach
                    <a href="{{ route('tools.whatsapp-settings.index', ['branch_id' => 'global']) }}" 
                       class="btn btn-sm mb-1 {{ empty($branchId) ? 'btn-secondary font-weight-bold shadow-sm' : 'btn-outline-secondary' }}">
                        🌐 Global Defaults
                    </a>
                </div>
                <div class="my-1">
                    <span class="badge badge-pill {{ $branchId ? 'badge-success' : 'badge-secondary' }} px-3 py-2 font-weight-bold shadow-sm">
                        <i class="fas {{ $branchId ? 'fa-store' : 'fa-globe' }} mr-1"></i>
                        Editing: {{ $activeBranch ? $activeBranch->name : 'Global Defaults' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Left Column: Settings Form --}}
        <div class="col-lg-7 col-md-12 mb-4">
            <div class="card card-outline card-success shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-sliders-h text-success mr-2"></i> Configuration for {{ $activeBranch ? $activeBranch->name : 'Global Defaults' }}
                    </h3>
                    <span class="badge badge-light border text-muted">
                        {{ $branchId ? 'Branch ID: #'.$branchId : 'Global Template' }}
                    </span>
                </div>
                <form action="{{ route('tools.whatsapp-settings.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">

                    <div class="card-body">
                        {{-- Master Toggles --}}
                        <div class="row mb-3 pb-3 border-bottom">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <div class="custom-control custom-switch custom-switch-lg">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $settings->is_active) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark pt-1" for="is_active">
                                        WhatsApp Service Active
                                    </label>
                                    <div class="text-muted small">Enable or disable outgoing WhatsApp dispatches for this branch.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="custom-control custom-switch custom-switch-lg">
                                    <input type="checkbox" class="custom-control-input" id="auto_send_on_bill" name="auto_send_on_bill" value="1" {{ old('auto_send_on_bill', $settings->auto_send_on_bill) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold text-dark pt-1" for="auto_send_on_bill">
                                        Auto-Send on Billing
                                    </label>
                                    <div class="text-muted small">Automatically message customer upon completing a bill in this branch.</div>
                                </div>
                            </div>
                        </div>

                        {{-- API Credentials --}}
                        <h6 class="font-weight-bold text-uppercase text-secondary text-xs tracking-wider mb-3">
                            <i class="fas fa-key mr-1"></i> ChatOnClick API Credentials
                        </h6>

                        <div class="form-group mb-3">
                            <label for="api_url" class="font-weight-bold text-dark">API Endpoint URL <span class="text-danger">*</span></label>
                            <input type="url" name="api_url" id="api_url" class="form-control" value="{{ old('api_url', $settings->api_url) }}" required>
                            <small class="form-text text-muted">Default: <code>https://chatonclick.com</code></small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="app_key" class="font-weight-bold text-dark">App Key (Device Key) <span class="text-danger">*</span></label>
                                    <input type="text" name="app_key" id="app_key" class="form-control font-monospace" value="{{ old('app_key', $settings->app_key) }}" placeholder="e.g. 01cbe6e8-abf4-449c-a40f-..." required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="auth_key" class="font-weight-bold text-dark">Account Auth Key <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" name="auth_key" id="auth_key" class="form-control font-monospace" value="{{ old('auth_key', $settings->auth_key) }}" placeholder="e.g. HrvnRKqlZGpJ..." required>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" id="btn-toggle-authkey">
                                                <i class="fas fa-eye" id="icon-toggle-authkey"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Template Configuration --}}
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="font-weight-bold text-uppercase text-secondary text-xs tracking-wider mb-0">
                                    <i class="fas fa-file-invoice mr-1"></i> WhatsApp Message Mode
                                </h6>
                                <span class="badge {{ !empty($settings->template_name) ? 'badge-primary' : 'badge-success' }}">
                                    {{ !empty($settings->template_name) ? 'Meta Registered Template' : 'Direct Custom Message (Recommended)' }}
                                </span>
                            </div>
                            <p class="text-muted small mb-3">
                                <strong>Direct Custom Mode (Recommended):</strong> Leave <em>Template Name</em> blank to send your customized message template below with full formatting & dynamic store emojis.
                                <br>
                                <strong>Registered Template Mode:</strong> If using a pre-approved template from ChatOnClick (e.g. <code>urban_tax_invoice</code>), specify its name.
                            </p>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group mb-2">
                                        <label for="template_name" class="font-weight-bold text-dark">
                                            Template Name <small class="text-muted">(Optional - leave blank for Custom Message)</small>
                                        </label>
                                        <input type="text" name="template_name" id="template_name" class="form-control" value="{{ old('template_name', $settings->template_name) }}" placeholder="Leave blank for direct custom message">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-2">
                                        <label for="template_lang" class="font-weight-bold text-dark">Language Code</label>
                                        <input type="text" name="template_lang" id="template_lang" class="form-control" value="{{ old('template_lang', $settings->template_lang ?: 'en') }}" placeholder="en">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Branding & Greetings Customization --}}
                        <h6 class="font-weight-bold text-uppercase text-secondary text-xs tracking-wider mb-3">
                            <i class="fas fa-paint-brush mr-1"></i> Branch Branding & Contacts
                        </h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="header_title" class="font-weight-bold text-dark">Store / Branch Title <span class="text-danger">*</span></label>
                                    <input type="text" name="header_title" id="header_title" class="form-control" value="{{ old('header_title', $settings->header_title) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="support_phone" class="font-weight-bold text-dark">Helpline / Support Phone</label>
                                    <input type="text" name="support_phone" id="support_phone" class="form-control" value="{{ old('support_phone', $settings->support_phone) }}" placeholder="e.g. 7383056626">
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="footer_message" class="font-weight-bold text-dark">Footer Greeting / Closing Wish <span class="text-danger">*</span></label>
                            <input type="text" name="footer_message" id="footer_message" class="form-control" value="{{ old('footer_message', $settings->footer_message) }}" required>
                            <small class="form-text text-muted">Example: <em>Have an Awesome Day!</em> or <em>Visit us again soon!</em></small>
                        </div>

                        {{-- Custom WhatsApp Message Template --}}
                        <div class="p-3 bg-white rounded border border-success mb-3 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="font-weight-bold text-uppercase text-dark text-xs tracking-wider mb-0">
                                    <i class="fab fa-whatsapp text-success mr-1"></i> Branch Message Template
                                </h6>
                                <span class="badge badge-success font-weight-bold">Branch Isolated</span>
                            </div>
                            <p class="text-muted small mb-2">
                                Specify the WhatsApp message format for bills created in <strong>{{ $activeBranch ? $activeBranch->name : 'Global Defaults' }}</strong>. Click any token chip to insert it:
                            </p>

                            <div class="mb-2 d-flex flex-wrap" id="token-chips">
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{customer_name}">+ {customer_name}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{bill_number}">+ {bill_number}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{date}">+ {date}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{branch_name}">+ {branch_name}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{total_items}">+ {total_items}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{total}">+ {total}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{payment_mode}">+ {payment_mode}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{bill_url}">+ {bill_url}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{store_phone}">+ {store_phone}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{header}">+ {header}</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 token-chip font-weight-bold" data-token="{footer}">+ {footer}</button>
                            </div>

                            <div class="form-group mb-1">
                                <textarea name="custom_message" id="custom_message" rows="9" class="form-control font-monospace text-sm" placeholder="Leave empty to use standard default formatted template, or enter custom branch message with placeholders...">{{ old('custom_message', $settings->custom_message) }}</textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">Use <code>*text*</code> for bold, <code>_text_</code> for italic, <code>~text~</code> for strikethrough.</small>
                                <button type="button" id="btn-load-sample-msg" class="btn btn-link btn-xs text-primary font-weight-bold p-0">
                                    <i class="fas fa-magic mr-1"></i> Fill Standard Template Text
                                </button>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white text-right py-3">
                        <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save {{ $activeBranch ? $activeBranch->name : 'Global' }} Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right Column: Live Test Console & Preview --}}
        <div class="col-lg-5 col-md-12 mb-4">
            {{-- Test Console Card --}}
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-paper-plane text-primary mr-2"></i> Live WhatsApp Tester ({{ $activeBranch ? $activeBranch->name : 'Global' }})
                    </h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Send an instant live test message using this branch's credentials and phone number.
                    </p>

                    <div class="form-group mb-3">
                        <label for="test_phone" class="font-weight-bold text-dark">Recipient Mobile Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light font-weight-bold text-muted">+91</span>
                            </div>
                            <input type="tel" id="test_phone" class="form-control font-weight-bold" placeholder="10-digit mobile (e.g. 9638455255)" maxlength="15">
                        </div>
                        <small class="text-muted">Enter any mobile number to test delivery.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="test_bill_id" class="font-weight-bold text-dark">Message Type</label>
                        <select id="test_bill_id" class="form-control">
                            <option value="">⚡ Instant Diagnostic Ping (Connection Test)</option>
                            @if(isset($recentBills) && $recentBills->isNotEmpty())
                                <optgroup label="Sample Recent Sales Bills">
                                    @foreach($recentBills as $b)
                                        <option value="{{ $b->id }}">
                                            Bill #{{ $b->bill_number }} - ₹{{ number_format((float)$b->total, 2) }} ({{ $b->customer?->name ?: 'Walk-in' }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>

                    <button type="button" id="btn-send-test" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">
                        <i class="fab fa-whatsapp mr-1"></i> Send Live WhatsApp Test
                    </button>

                    <div id="test-result-box" class="mt-3 d-none"></div>
                </div>
            </div>

            {{-- Message Preview Card --}}
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-mobile-alt text-secondary mr-2"></i> Client WhatsApp Message Preview
                    </h3>
                    <span class="badge badge-light border text-muted">Live Preview</span>
                </div>
                <div class="card-body bg-light p-3">
                    <div id="whatsapp-phone-mockup" class="bg-white p-3 rounded shadow-sm border font-monospace text-sm" style="white-space: pre-wrap; line-height: 1.5; color: #111;"></div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
<script>
$(document).ready(function() {
    // Toggle password visibility for Auth Key
    $('#btn-toggle-authkey').on('click', function() {
        var input = $('#auth_key');
        var icon = $('#icon-toggle-authkey');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Token Chips Insertion into Custom Message Textarea
    $('.token-chip').on('click', function() {
        var token = $(this).data('token');
        var textarea = $('#custom_message')[0];
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var text = textarea.value;
        textarea.value = text.substring(0, start) + token + text.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + token.length;
        textarea.focus();
        updatePreview();
    });

    // Default template text generator
    function getDefaultTemplate() {
        var header = ($('#header_title').val() || 'URBAN PETS').toUpperCase();
        var footer = $('#footer_message').val() || 'Have an Awesome Day!';
        var phone = $('#support_phone').val() || '7383056626';

        return "🐾 *" + header + "* 🐾\n" +
               "*Official Tax Invoice Receipt*\n" +
               "━━━━━━━━━━━━━━━━━━━━\n" +
               "Dear *{customer_name}*,\n" +
               "Thank you for shopping with us! Here are your bill details:\n\n" +
               "📄 *Bill No:* {bill_number}\n" +
               "📅 *Date:* {date}\n" +
               "🏪 *Branch:* {branch_name}\n" +
               "🛍️ *Total Items:* {total_items}\n" +
               "💰 *Net Payable:* ₹{total}\n" +
               "💳 *Payment:* {payment_mode}\n\n" +
               "🔗 *Click to view & download your bill slip:*\n" +
               "{bill_url}\n\n" +
               "━━━━━━━━━━━━━━━━━━━━\n" +
               "📞 *Store Helpline:* " + phone + "\n" +
               "🐾 *" + footer + "*";
    }

    // Fill sample standard template text
    $('#btn-load-sample-msg').on('click', function() {
        $('#custom_message').val(getDefaultTemplate());
        updatePreview();
    });

    // Real-time Preview synchronization
    function updatePreview() {
        var custom = $('#custom_message').val().trim();
        var header = ($('#header_title').val() || 'URBAN PETS').toUpperCase();
        var footer = $('#footer_message').val() || 'Have an Awesome Day!';
        var phone = $('#support_phone').val() || '7383056626';
        var branchName = "{{ $activeBranch ? $activeBranch->name : 'URBAN PETS - MOTERA' }}";

        var template = custom || getDefaultTemplate();

        var replacements = {
            '{customer_name}': 'Ankit Patel',
            '{bill_number}': 'SB-2026-0009',
            '{date}': '{{ now()->format("d-m-Y h:i A") }}',
            '{branch_name}': branchName,
            '{total_items}': '2',
            '{total}': '560.00',
            '{payment_mode}': 'Cash',
            '{bill_url}': 'https://pos.ramdevcar.shop/receipt/v/9/532a2d2472ce0f78',
            '{store_phone}': phone,
            '{header}': header,
            '{footer}': footer
        };

        var rendered = template;
        for (var key in replacements) {
            rendered = rendered.split(key).join(replacements[key]);
        }

        $('#whatsapp-phone-mockup').text(rendered);
    }

    $('#header_title, #footer_message, #support_phone, #custom_message').on('input', updatePreview);
    updatePreview();

    // Send Test Message via AJAX
    $('#btn-send-test').on('click', function() {
        var phone = $('#test_phone').val().trim();
        var billId = $('#test_bill_id').val();
        var branchId = "{{ $branchId }}";
        var resultBox = $('#test-result-box');
        var btn = $(this);

        if (!phone) {
            alert('Please enter a valid mobile number to receive the test message.');
            $('#test_phone').focus();
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sending via ChatOnClick...');
        resultBox.addClass('d-none').removeClass('alert alert-success alert-danger alert-warning');

        $.ajax({
            url: "{{ route('tools.whatsapp-settings.test') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                phone: phone,
                bill_id: billId,
                branch_id: branchId
            },
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fab fa-whatsapp mr-1"></i> Send Live WhatsApp Test');
                resultBox.removeClass('d-none').addClass('alert alert-success')
                    .html('<strong><i class="fas fa-check-circle mr-1"></i> Success!</strong> ' + 
                          (res.message || 'Test WhatsApp message dispatched successfully.') + 
                          (res.wamid ? '<br><small class="text-muted">Message ID: ' + res.wamid + '</small>' : ''));
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fab fa-whatsapp mr-1"></i> Send Live WhatsApp Test');
                var err = 'Failed to send WhatsApp message.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    err = xhr.responseJSON.message;
                }
                resultBox.removeClass('d-none').addClass('alert alert-danger')
                    .html('<strong><i class="fas fa-times-circle mr-1"></i> Dispatch Error:</strong> ' + err);
            }
        });
    });
});
</script>
@endpush
