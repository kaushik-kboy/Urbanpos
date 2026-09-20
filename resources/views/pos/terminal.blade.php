<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS Terminal - UrbanPOS</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome & Bootstrap 4 via CDN/Vendor -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">

    <!-- UrbanPets UI 2.0 Design Tokens & Theme -->
    <link rel="stylesheet" href="{{ asset('css/urbanpets-theme.css') }}?v={{ time() }}">
    <!-- Custom POS Terminal Styles -->
    <link rel="stylesheet" href="{{ asset('css/pos-terminal.css') }}?v={{ time() }}">
</head>
<body class="pos-terminal-body">

<div class="pos-wrapper">

    <!-- Top Slim Navigation Header -->
    <header class="pos-navbar">
        <div class="d-flex align-items-center">
            <div class="pos-brand mr-4">
                <i class="fas fa-cash-register mr-2"></i>UrbanPOS <span class="badge badge-success ml-2 font-weight-normal small">Terminal</span>
            </div>
            <div class="pos-meta-item d-none d-md-inline-flex">
                <i class="fas fa-store text-info mr-1"></i>
                <select id="posBranchSelect" class="bg-transparent border-0 text-white font-weight-bold ml-1" style="outline:none; cursor:pointer;">
                    @foreach ($branches as $bId => $bName)
                        <option value="{{ $bId }}" class="text-dark">{{ $bName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pos-meta-item d-none d-sm-inline-flex">
                <i class="fas fa-user-circle text-warning mr-1"></i>
                <span>{{ auth()->user()->name }}</span>
            </div>
            @if(isset($activeTillSession))
                <div class="pos-meta-item">
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-unlock-alt mr-1"></i> Till: Open</span>
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center">
            <div id="posClock" class="pos-meta-item font-weight-bold d-none d-lg-inline-flex">
                00:00:00
            </div>
            <button type="button" class="btn btn-outline-light btn-sm mr-2 shadow-none" onclick="toggleFullscreen()" title="Toggle Fullscreen (F11)">
                <i class="fas fa-expand"></i>
            </button>
            <a href="{{ route('home') }}" class="btn btn-danger btn-sm font-weight-bold" title="Exit POS Counter">
                <i class="fas fa-sign-out-alt mr-1"></i> Exit Admin
            </a>
        </div>
    </header>

    <!-- Main 2-Pane Kiosk Layout -->
    <main class="pos-workspace">

        <!-- LEFT PANE: Scanner & Cart List (68%) -->
        <section class="pos-left-pane">
            
            <!-- Barcode Scanner Bar -->
            <div class="pos-scan-bar" id="posScannerContainer">
                <div class="pos-scanner-input-group">
                    <i class="fas fa-barcode pos-scanner-icon"></i>
                    <input type="text" id="posScanInput" class="pos-scanner-input" placeholder="Scan Barcode or Search Item Name / SKU / Code... (Press F2)" autocomplete="off" autofocus>
                    <span class="pos-scanner-tag"><i class="fas fa-bolt text-warning mr-1"></i>Auto Scan</span>
                </div>
                <!-- Live Search Suggestions Dropdown -->
                <div id="posSearchResults" class="pos-search-results"></div>
            </div>

            <!-- Cart Table List -->
            <div class="pos-cart-container">
                <table class="pos-cart-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 35px;">#</th>
                            <th>Item Particulars</th>
                            <th class="text-right" style="width: 90px;">Rate</th>
                            <th class="text-center" style="width: 140px;">Quantity</th>
                            <th class="text-right" style="width: 110px;">Amount</th>
                            <th class="text-center" style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="posCartBody">
                        <!-- Populated dynamically via JS -->
                    </tbody>
                </table>

                <!-- Empty Cart State Notice -->
                <div id="posEmptyCart" class="pos-empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h5 class="font-weight-bold text-dark mb-1">Cart is Empty</h5>
                    <p class="small text-muted mb-0">Use barcode scanner or search items above to begin adding products.</p>
                </div>
            </div>

            <!-- Bottom Quick Action Bar -->
            <footer class="pos-action-bar">
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-outline-primary btn-sm mr-2 font-weight-bold shadow-sm" onclick="if(window.posTriggerAction) window.posTriggerAction('new_entry');" title="Create New Record (F3)">
                        <i class="fas fa-plus mr-1"></i> New (F3)
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold shadow-sm" onclick="if(window.posTriggerAction) window.posTriggerAction('edit_entry');" title="Edit Record (F4)">
                        <i class="fas fa-edit mr-1"></i> Edit (F4)
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm mr-2 font-weight-bold shadow-sm" onclick="if(window.posTriggerAction) window.posTriggerAction('save_form');" title="Save Bill / Tender (F6)">
                        <i class="fas fa-save mr-1"></i> Save (F6)
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm mr-2 font-weight-bold shadow-sm" style="color: #6C3BE8; border-color: #6C3BE8;" onclick="if(window.posTriggerAction) window.posTriggerAction('view_records');" title="View Records / List (F7)">
                        <i class="fas fa-list mr-1"></i> View (F7)
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm mr-2 font-weight-bold shadow-sm" onclick="if(window.posTriggerAction) window.posTriggerAction('print_form');" title="Print Slip (F8)">
                        <i class="fas fa-print mr-1"></i> Print (F8)
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm mr-2 font-weight-bold shadow-sm" onclick="if(window.posTriggerAction) window.posTriggerAction('close_modal');" title="Close / Back (F10)">
                        <i class="fas fa-times mr-1"></i> Close (F10)
                    </button>
                    <div class="border-right mx-2" style="height: 24px;"></div>
                    <button type="button" id="posHoldBtn" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold shadow-sm" title="Park Current Bill (Alt+H)">
                        <i class="fas fa-pause-circle mr-1 text-warning"></i> Hold (Alt+H)
                    </button>
                    <button type="button" id="posRecallBtn" class="btn btn-outline-info btn-sm mr-2 font-weight-bold shadow-sm" title="Recall Held Bill (Alt+R)">
                        <i class="fas fa-play-circle mr-1"></i> Recall (Alt+R)
                        <span id="posHeldCountBadge" class="badge badge-warning ml-1" style="display: none;">0</span>
                    </button>
                    <button type="button" id="posClearBtn" class="btn btn-outline-danger btn-sm font-weight-bold shadow-sm" title="Clear Entire Cart (F9)">
                        <i class="fas fa-trash-alt mr-1"></i> Clear Cart (F9)
                    </button>
                </div>
            </footer>

        </section>

        <!-- RIGHT PANE: Customer, Totals & Tender (32%) -->
        <aside class="pos-right-pane">

            <!-- Customer Details Card -->
            <div class="pos-customer-card" id="posCustomerCard">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="font-weight-bold text-muted small text-uppercase mb-0">
                        <i class="fas fa-user mr-1 text-primary"></i> Customer
                    </label>
                    <button type="button" id="posHeaderNewCustBtn" class="btn btn-link btn-xs p-0 text-decoration-none font-weight-bold">
                        + New Customer
                    </button>
                </div>

                <!-- Customer Search Select (visible when choosing/changing customer) -->
                <div id="posCustomerSearchWrapper" class="mb-2" style="{{ $defaultCustomer ? 'display: none;' : '' }}">
                    <select id="posCustomerSelect" class="form-control form-control-sm select2">
                        @if($defaultCustomer)
                            <option value="{{ $defaultCustomer->id }}" selected>{{ $defaultCustomer->mobile ? "{$defaultCustomer->name} ({$defaultCustomer->mobile})" : $defaultCustomer->name }}</option>
                        @endif
                    </select>
                </div>

                <!-- Selected Customer Profile Card -->
                <div id="posSelectedCustomerBox" class="pos-selected-cust-box" style="{{ $defaultCustomer ? '' : 'display: none;' }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="pos-selected-cust-info">
                            <div class="pos-selected-cust-name font-weight-bold text-dark text-truncate" id="posSelectedCustName">
                                {{ $defaultCustomer?->name ?? 'Select Customer' }}
                            </div>
                            <div class="pos-selected-cust-mobile text-muted small mt-1" id="posSelectedCustMobile">
                                <i class="fas fa-phone-alt text-success mr-1"></i>
                                <span>{{ $defaultCustomer?->mobile ?: 'No mobile' }}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <button type="button" id="posEditCustomerBtn" class="pos-cust-btn text-primary mr-1" title="Edit Customer">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" id="posChangeCustomerBtn" class="pos-cust-btn text-secondary" title="Search / Change Customer">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Customer Pet Details (Above Invoices) -->
                    @php
                        $defaultPetSummary = $defaultCustomer && $defaultCustomer->pets ? $defaultCustomer->pets->map(fn($p) => $p->name ? ($p->petType ? "{$p->name} ({$p->petType->name})" : $p->name) : ($p->petType?->name ?? 'Pet'))->filter()->implode(', ') : '';
                    @endphp
                    <div id="posSelectedCustPets" class="pos-selected-cust-pets mt-2 pt-2 border-top" style="{{ $defaultPetSummary ? '' : 'display: none;' }}">
                        <div class="d-flex align-items-center text-dark small">
                            <i class="fas fa-paw text-warning mr-2" style="font-size: 0.95rem;"></i>
                            <span class="font-weight-600 text-muted mr-1">Pet:</span>
                            <span class="font-weight-bold text-dark text-truncate" id="posSelectedCustPetsText">
                                {{ $defaultPetSummary }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Total Invoices Button / Section -->
                <div id="posCustomerInvoicesSection" class="pos-cust-invoices-section mt-2" style="{{ $defaultCustomer ? '' : 'display: none;' }}">
                    <button type="button" id="posViewCustomerInvoicesBtn" class="pos-total-invoices-btn w-100 d-flex justify-content-between align-items-center" title="Click to view customer invoices">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-invoice text-info mr-2" style="font-size: 1.05rem;"></i>
                            <span class="font-weight-600 text-dark small">Total Invoices</span>
                        </div>
                        <span class="badge badge-light border font-weight-bold text-dark px-2 py-1" id="posCustomerTotalInvoicesCount">0</span>
                    </button>
                </div>

                <div id="posLoyaltyBadge" class="badge badge-light border px-2 py-1 mt-2 text-dark font-weight-bold w-100 text-left" style="display: none;">
                    <i class="fas fa-coins text-warning mr-1"></i> Loyalty: 0 pts
                </div>
            </div>

            <!-- Financial Summary Box -->
            <div class="pos-summary-card">
                <div class="pos-summary-line">
                    <span>Total Quantity</span>
                    <span id="posItemsCountBadge" class="font-weight-bold text-dark">0 units</span>
                </div>
                <div class="pos-summary-line">
                    <span>Subtotal</span>
                    <span id="posSubtotal" class="font-weight-bold text-dark">₹ 0.00</span>
                </div>
                <div class="pos-summary-line">
                    <span>Discount</span>
                    <span id="posDiscount" class="text-danger">- ₹ 0.00</span>
                </div>
                <div class="pos-summary-line">
                    <span>Tax (GST Included)</span>
                    <span id="posGst" class="text-muted">₹ 0.00</span>
                </div>
                <div class="pos-summary-line">
                    <span>Round Off</span>
                    <span id="posRoundOff" class="text-muted">+ ₹ 0.00</span>
                </div>
            </div>

            <!-- Grand Total LED Display Card -->
            <div class="pos-total-display">
                <div class="pos-total-label">Total Amount Payable</div>
                <div id="posGrandTotal" class="pos-total-amount">₹ 0.00</div>
            </div>

            <!-- Tender Selector Pills -->
            <div class="pos-tender-grid">
                <button type="button" class="pos-tender-btn active" data-mode="Cash">
                    <i class="fas fa-money-bill-wave text-success"></i>
                    <span>Cash (Alt+C)</span>
                </button>
                <button type="button" class="pos-tender-btn" data-mode="UPI">
                    <i class="fas fa-qrcode text-primary"></i>
                    <span>UPI (Alt+U)</span>
                </button>
                <button type="button" class="pos-tender-btn" data-mode="Card">
                    <i class="fas fa-credit-card text-info"></i>
                    <span>Card (Alt+D)</span>
                </button>
            </div>

            <!-- Cash Tender Controls -->
            <div id="posCashSection">
                <div class="pos-cash-chips">
                    <button type="button" class="pos-cash-chip" data-val="exact">Exact</button>
                    <button type="button" class="pos-cash-chip" data-val="100">+100</button>
                    <button type="button" class="pos-cash-chip" data-val="200">+200</button>
                    <button type="button" class="pos-cash-chip" data-val="500">+500</button>
                    <button type="button" class="pos-cash-chip" data-val="2000">+2000</button>
                </div>
                <div class="form-group mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small font-weight-bold text-muted mb-0">Cash Tendered (₹)</label>
                        <span class="small font-weight-bold text-success">Change Due: <span id="posChangeDue">₹ 0.00</span></span>
                    </div>
                    <input type="number" id="posCashReceived" class="form-control form-control-lg font-weight-bold text-right" placeholder="Enter Cash Received">
                </div>
            </div>

            <!-- Dynamic UPI QR Code Section -->
            <div id="posUpiSection" style="display: none;" class="text-center p-3 bg-light rounded border mb-2">
                <h6 class="font-weight-bold text-dark mb-1"><i class="fas fa-qrcode mr-1 text-primary"></i> Scan UPI to Pay</h6>
                <div class="font-weight-bold text-success h5 mb-2" id="posUpiQrAmount">₹ 0.00</div>
                <img id="posUpiQrImage" src="" alt="UPI QR" style="width: 150px; height: 150px; border: 2px solid #cbd5e1; border-radius: 8px; background: white; padding: 4px;" class="mb-2">
                <div class="small text-muted">Supports PhonePe, Google Pay, Paytm & Any UPI App</div>
            </div>

            <!-- Big Pay Button -->
            <button type="button" id="posPayBtn" class="pos-pay-btn" disabled>
                <i class="fas fa-check-circle mr-2"></i> Pay & Print (F6)
            </button>

        </aside>

    </main>

</div>

<!-- Customer Create / Edit Modal with Full 4 Tabs & Master Fields -->
<div class="modal fade" id="posAddCustomerModal" tabindex="-1" role="dialog" aria-labelledby="posCustomerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header py-3 px-4 text-white" style="background-color: var(--pos-header-bg);">
                <h5 class="modal-title font-weight-bold mb-0" id="posCustomerModalTitle">
                    <i class="fas fa-user-plus mr-2"></i> Add Customer
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="posQuickCustomerForm" autocomplete="off">
                <input type="hidden" id="posCustId" name="id" value="">
                <input type="hidden" id="posCustFormMethod" name="_method" value="POST">

                <div class="modal-body p-4">
                    <!-- Error Alert -->
                    <div id="posCustFormAlert" class="alert alert-danger d-none py-2 px-3 mb-3">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span id="posCustFormAlertText"></span>
                    </div>

                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs font-weight-bold mb-3" id="posCustTabNav" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="pos-tab-general-link" data-toggle="tab" href="#pos-tab-general" role="tab">
                                <i class="fas fa-user mr-1 text-primary"></i> General
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="pos-tab-contact-link" data-toggle="tab" href="#pos-tab-contact" role="tab">
                                <i class="fas fa-address-book mr-1 text-success"></i> Contact Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="pos-tab-others-link" data-toggle="tab" href="#pos-tab-others" role="tab">
                                <i class="fas fa-sliders-h mr-1 text-info"></i> Others
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="pos-tab-pets-link" data-toggle="tab" href="#pos-tab-pets" role="tab">
                                <i class="fas fa-paw mr-1 text-warning"></i> Pet Details
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Contents -->
                    <div class="tab-content pt-2" id="posCustTabContent">
                        <!-- 1. General Tab -->
                        <div class="tab-pane fade show active" id="pos-tab-general" role="tabpanel">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-600 small mb-1">Title</label>
                                    <select name="title" id="posCust_title" class="form-control form-control-sm">
                                        <option value="Mr" selected>Mr</option>
                                        <option value="Ms">Ms</option>
                                        <option value="Mrs">Mrs</option>
                                        <option value="M/s">M/s</option>
                                        <option value="Dr">Dr</option>
                                    </select>
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="font-weight-600 small mb-1">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="posCust_name" class="form-control form-control-sm" required placeholder="Customer Name">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Mobile Number <span class="text-danger">*</span></label>
                                    <input type="text" name="mobile" id="posCust_mobile" class="form-control form-control-sm" required maxlength="10" placeholder="10-digit mobile">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Category</label>
                                    <select name="customer_category_id" id="posCust_customer_category_id" class="form-control form-control-sm">
                                        <option value="">Select a category</option>
                                        @if(isset($customerCategories))
                                            @foreach($customerCategories as $catId => $catName)
                                                <option value="{{ $catId }}">{{ $catName }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Customer Id / Code</label>
                                    <input type="text" name="customer_code" id="posCust_customer_code" class="form-control form-control-sm" placeholder="Auto / Custom Code">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Sales Type</label>
                                    <select name="sales_type" id="posCust_sales_type" class="form-control form-control-sm">
                                        <option value="Local" selected>Local</option>
                                        <option value="Interstate">Interstate</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Payment Mode</label>
                                    <select name="payment_mode" id="posCust_payment_mode" class="form-control form-control-sm">
                                        <option value="Cash Only" selected>Cash Only</option>
                                        <option value="No Credit">No Credit</option>
                                        <option value="Credit Only">Credit Only</option>
                                        <option value="Both Cash and Credit">Both Cash and Credit</option>
                                        <option value="Cash on Delivery">Cash on Delivery</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Credit Limit (₹)</label>
                                    <input type="number" step="0.01" name="credit_limit" id="posCust_credit_limit" class="form-control form-control-sm" value="1000000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Credit Balance (₹)</label>
                                    <input type="number" step="0.01" name="credit_balance" id="posCust_credit_balance" class="form-control form-control-sm" value="0">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Monthly Credit Balance (₹)</label>
                                    <input type="number" step="0.01" name="monthly_credit_balance" id="posCust_monthly_credit_balance" class="form-control form-control-sm" value="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Credit Days</label>
                                    <input type="number" name="credit_days" id="posCust_credit_days" class="form-control form-control-sm" value="1000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Branch</label>
                                    <select name="branch_id" id="posCust_branch_id" class="form-control form-control-sm">
                                        <option value="">GLOBAL</option>
                                        @if(isset($branches))
                                            @foreach($branches as $bId => $bName)
                                                <option value="{{ $bId }}">{{ $bName }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-600 small mb-1">Status</label>
                                    <select name="status" id="posCust_status" class="form-control form-control-sm">
                                        <option value="1" selected>Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-600 small mb-1">GST Type</label>
                                    <select name="gst_type" id="posCust_gst_type" class="form-control form-control-sm">
                                        <option value="Un Register" selected>Un Register</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Composite">Composite</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-600 small mb-1">SMS Consent</label>
                                    <select name="sms_consent" id="posCust_sms_consent" class="form-control form-control-sm">
                                        <option value="1" selected>Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="font-weight-600 small mb-1">Sales Formula</label>
                                    <input type="text" name="sales_formula" id="posCust_sales_formula" class="form-control form-control-sm" placeholder="Optional">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Contact Details Tab -->
                        <div class="tab-pane fade" id="pos-tab-contact" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-600 small mb-1">Address 1</label>
                                    <input type="text" name="address1" id="posCust_address1" class="form-control form-control-sm" placeholder="Street / House address">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-600 small mb-1">Area</label>
                                    <select name="area_id" id="posCust_area_id" class="form-control form-control-sm">
                                        <option value="">Select an area</option>
                                        @if(isset($areas))
                                            @foreach($areas as $aId => $aName)
                                                <option value="{{ $aId }}">{{ $aName }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">City</label>
                                    <input type="text" name="city" id="posCust_city" class="form-control form-control-sm" placeholder="City">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">State</label>
                                    <input type="text" name="state" id="posCust_state" class="form-control form-control-sm" placeholder="State">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Country</label>
                                    <input type="text" name="country" id="posCust_country" class="form-control form-control-sm" value="India">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Postal Code</label>
                                    <input type="text" name="postal_code" id="posCust_postal_code" class="form-control form-control-sm" placeholder="Pincode">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">STD Code</label>
                                    <input type="text" name="std_code" id="posCust_std_code" class="form-control form-control-sm" placeholder="STD Code">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Landline / Phone</label>
                                    <input type="text" name="phone" id="posCust_phone" class="form-control form-control-sm" placeholder="Phone">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-600 small mb-1">Email</label>
                                    <input type="email" name="email" id="posCust_email" class="form-control form-control-sm" placeholder="email@example.com">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-600 small mb-1">GSTIN</label>
                                    <input type="text" name="gst_no" id="posCust_gst_no" class="form-control form-control-sm" maxlength="15" placeholder="e.g. 22AAAAA0000A1Z5" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 15);">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-600 small mb-1">Aadhar No</label>
                                    <input type="text" name="aadhar_no" id="posCust_aadhar_no" class="form-control form-control-sm" placeholder="Aadhar number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-600 small mb-1">PAN No</label>
                                    <input type="text" name="pan_no" id="posCust_pan_no" class="form-control form-control-sm" placeholder="PAN number">
                                </div>

                                <div class="col-md-12 mb-2">
                                    <label class="font-weight-600 small mb-1">Remarks</label>
                                    <textarea name="remarks" id="posCust_remarks" class="form-control form-control-sm" rows="2" placeholder="Additional customer notes..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Others Tab -->
                        <div class="tab-pane fade" id="pos-tab-others" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Gender</label>
                                    <select name="gender" id="posCust_gender" class="form-control form-control-sm">
                                        <option value="">Select</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Customer Type <span class="text-danger">*</span></label>
                                    <select name="customer_type" id="posCust_customer_type" class="form-control form-control-sm" required>
                                        <option value="RETAIL INVOICE" selected>RETAIL INVOICE</option>
                                        <option value="TAX INVOICE">TAX INVOICE</option>
                                        <option value="EXEMPTED">EXEMPTED</option>
                                        <option value="E-COMMERCE">E-COMMERCE</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">Exempted Reason</label>
                                    <select name="exempted_reason" id="posCust_exempted_reason" class="form-control form-control-sm">
                                        <option value="">Select</option>
                                        <option value="Other exemption">Other exemption</option>
                                        <option value="SEZ-Exempt">SEZ-Exempt</option>
                                        <option value="SEZ-LUT">SEZ-LUT</option>
                                        <option value="BOND">BOND</option>
                                        <option value="SEZ-Taxable">SEZ-Taxable</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Pet Details Tab -->
                        <div class="tab-pane fade" id="pos-tab-pets" role="tabpanel">
                            <div id="pos-pet-rows">
                                <!-- Dynamic Pet Rows -->
                            </div>
                            <button type="button" id="pos-add-pet-detail" class="btn btn-sm btn-outline-primary font-weight-bold">
                                <i class="fas fa-plus-circle mr-1"></i> Add Pet Detail
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer py-2 px-4 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-4" id="posSaveCustomerBtn">
                        <i class="fas fa-save mr-1"></i> Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Sales Bills List Modal -->
<div class="modal fade" id="posListModal" tabindex="-1" role="dialog" aria-labelledby="posListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; height: 80vh; display: flex; flex-direction: column;">
            <div class="modal-header py-3 px-4 bg-light border-bottom">
                <h5 class="modal-title font-weight-bold text-dark mb-0" id="posListModalLabel">
                    <i class="fas fa-list text-primary mr-2"></i> Sales Bills List
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close" style="outline: none;">
                    <span aria-hidden="true"><i class="fas fa-times"></i></span>
                </button>
            </div>
            <div class="modal-body p-0" style="flex: 1; overflow: hidden; position: relative;">
                <div id="posListLoader" style="position: absolute; top:0; left:0; width:100%; height:100%; display:none; justify-content:center; align-items:center; background: rgba(255,255,255,0.8); z-index: 10;">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <iframe id="posListIframe" src="" style="width: 100%; height: 100%; border: none;" onload="document.getElementById('posListLoader').style.display='none';"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Pet Row Template for Dynamic Adding in POS Modal -->
<template id="pos-pet-row-template">
    <div class="pos-pet-row border rounded p-3 mb-3 bg-light position-relative">
        <input type="hidden" name="pets[__INDEX__][id]" class="pos-pet-id" value="">
        <input type="hidden" name="pets[__INDEX__][_delete]" class="pos-pet-delete-flag" value="0">
        
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="font-weight-600 small mb-1">Breed</label>
                <select name="pets[__INDEX__][breed_id]" class="form-control form-control-sm pos-pet-breed">
                    <option value="">NA</option>
                    @if(isset($breeds))
                        @foreach ($breeds as $bId => $bName)
                            <option value="{{ $bId }}">{{ $bName }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-600 small mb-1">Pet Type</label>
                <select name="pets[__INDEX__][pet_type_id]" class="form-control form-control-sm pos-pet-type">
                    <option value="">NA</option>
                    @if(isset($petTypes))
                        @foreach ($petTypes as $ptId => $ptName)
                            <option value="{{ $ptId }}">{{ $ptName }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-600 small mb-1">Name</label>
                <input type="text" name="pets[__INDEX__][name]" class="form-control form-control-sm pos-pet-name" placeholder="Pet Name">
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-600 small mb-1">Gender</label>
                <select name="pets[__INDEX__][gender]" class="form-control form-control-sm pos-pet-gender">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-600 small mb-1">Age</label>
                <input type="text" name="pets[__INDEX__][age]" class="form-control form-control-sm pos-pet-age" placeholder="e.g. 2 yrs">
            </div>
            <div class="col-md-3 mb-2">
                <label class="font-weight-600 small mb-1">Birth Date</label>
                <input type="date" name="pets[__INDEX__][birth_date]" class="form-control form-control-sm pos-pet-birthdate">
            </div>
            <div class="col-md-6 mb-2">
                <label class="font-weight-600 small mb-1">Remarks</label>
                <textarea name="pets[__INDEX__][remarks]" rows="1" class="form-control form-control-sm pos-pet-remarks" placeholder="Notes, allergy, etc."></textarea>
            </div>
        </div>
        <div class="text-right mt-1">
            <button type="button" class="btn btn-xs btn-outline-danger pos-remove-pet-btn">
                <i class="fas fa-trash mr-1"></i> Remove Pet
            </button>
        </div>
    </div>
</template>

<!-- Customer Invoices History Modal (Matching Screenshot 2) -->
<div class="modal fade" id="posCustomerInvoicesModal" tabindex="-1" role="dialog" aria-labelledby="posCustomerInvoicesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 760px;">
        <div class="modal-content cim-modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-0 pb-1 pt-4 px-4 d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="font-weight-bold text-dark mb-1" id="posCustomerInvoicesModalLabel" style="font-size: 1.35rem; letter-spacing: -0.2px;">Customer invoices</h4>
                    <div class="text-muted font-weight-500 small" id="cimSubtitle">Select a customer</div>
                </div>
                <button type="button" class="close text-muted" data-dismiss="modal" aria-label="Close" style="outline: none;">
                    <span aria-hidden="true" style="font-size: 1.5rem;">&times;</span>
                </button>
            </div>
            
            <div class="modal-body px-4 pt-2 pb-3">
                <!-- Search Box -->
                <div class="cim-search-container mb-3">
                    <div class="cim-search-input-group">
                        <i class="fas fa-search cim-search-icon"></i>
                        <input type="text" id="cimSearchInput" class="cim-search-input" placeholder="Search date, invoice number, or amount" autocomplete="off">
                    </div>
                </div>

                <!-- Loading State -->
                <div id="cimLoadingState" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted small">Loading customer invoices...</p>
                </div>

                <!-- Empty State -->
                <div id="cimEmptyState" class="text-center py-4 d-none">
                    <i class="fas fa-receipt fa-2x text-muted mb-2"></i>
                    <p class="text-muted small mb-0">No invoices found for this customer.</p>
                </div>

                <!-- Invoices Table -->
                <div class="cim-table-responsive" id="cimTableContainer">
                    <table class="cim-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cimTableBody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination & Summary Row -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <div id="cimInvoiceCountLabel" class="font-weight-bold text-dark small">0 invoices</div>
                    <div class="d-flex align-items-center">
                        <button type="button" id="cimPrevBtn" class="cim-page-btn mr-2" disabled>Prev</button>
                        <button type="button" id="cimNextBtn" class="cim-page-btn" disabled>Next</button>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-0 justify-content-end">
                <button type="button" class="btn cim-close-btn" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    window.APP_URL = "{{ url('/') }}";
    window.CSRF_TOKEN = "{{ csrf_token() }}";
    window.STORE_NAME = "{{ config('app.name', 'UrbanPOS') }}";
    window.CUSTOMER_SEARCH_URL = "{{ route('sales.sales-bills.customer-search') }}";
    window.CUSTOMER_INVOICES_URL = "{{ url('sales/sales-bills/customer-invoices') }}";
    window.CUSTOMER_EDIT_BASE_URL = "{{ url('master/customers') }}";
    window.INITIAL_CUSTOMER = {!! json_encode($defaultCustomer ? [
        'id' => $defaultCustomer->id,
        'name' => $defaultCustomer->name,
        'mobile' => $defaultCustomer->mobile ?? '',
        'edit_url' => route('master.customers.edit', $defaultCustomer),
        'pets_summary' => $defaultPetSummary,
    ] : null) !!};
    window.EDIT_BILL = {!! isset($editBill) ? json_encode($editBill) : 'null' !!};

    // Digital Clock
    function updateClock() {
        const el = document.getElementById('posClock');
        if (el) {
            const now = new Date();
            el.innerHTML = '<i class="fas fa-clock mr-1 text-info"></i> ' + now.toLocaleTimeString();
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Fullscreen Toggle
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            }
        }
    }
</script>

<script src="{{ asset('js/pos-hotkeys.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/pos-terminal.js') }}?v={{ time() }}"></script>
</body>
</html>
