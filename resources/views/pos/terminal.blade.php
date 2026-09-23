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
    <link rel="stylesheet" href="{{ asset('css/urbanpets-theme.css') }}?v={{ file_exists(public_path('css/urbanpets-theme.css')) ? filemtime(public_path('css/urbanpets-theme.css')) : '1.0' }}">
    <!-- Custom POS Terminal Styles -->
    <link rel="stylesheet" href="{{ asset('css/pos-terminal.css') }}?v={{ file_exists(public_path('css/pos-terminal.css')) ? filemtime(public_path('css/pos-terminal.css')) : '1.0' }}">
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
            <button type="button" class="btn btn-warning btn-sm mr-2 font-weight-bold shadow-none" id="btnPosLockScreen" onclick="if(window.lockPosScreen) window.lockPosScreen();" title="Lock Screen (Ctrl + L)">
                <i class="fas fa-lock mr-1"></i> Lock (Ctrl+L)
            </button>
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
                <div class="pos-scanner-input-group d-flex align-items-center">
                    <div class="pos-scanner-field-wrapper position-relative flex-grow-1">
                        <i class="fas fa-barcode pos-scanner-icon"></i>
                        <input type="text" id="posScanInput" class="pos-scanner-input w-100" placeholder="Scan Barcode or Search Item Name / SKU / Code... (Click or Press F2 for Item List)" autocomplete="off" autofocus>
                        <span class="pos-scanner-tag"><i class="fas fa-bolt text-warning mr-1"></i>Auto Scan</span>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm px-3 ml-2 font-weight-bold shadow-sm text-nowrap d-flex align-items-center" id="pos-btn-item-lookup" onclick="if(window.openPosItemSearchModal) window.openPosItemSearchModal();" title="Open Item List Popup (F2)" style="border-radius: 8px; height: 48px; font-size: 0.95rem;">
                        <i class="fas fa-search-plus mr-1"></i> Item List (F2)
                    </button>
                </div>
                <!-- Live Search Suggestions Dropdown -->
                <div id="posSearchResults" class="pos-search-results"></div>
            </div>

            <!-- Cart Table List -->
            <div class="pos-cart-container">
                <table class="pos-cart-table table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 35px;">#</th>
                            <th class="text-center" style="width: 65px;">Code</th>
                            <th>Description</th>
                            <th class="text-center" style="width: 100px;">Exp Date</th>
                            <th class="text-center" style="width: 75px;">Qty</th>
                            <th class="text-right" style="width: 80px;">Sell</th>
                            <th class="text-right" style="width: 80px;">MRP</th>
                            <th class="text-center" style="width: 75px;">Dis %</th>
                            <th class="text-center" style="width: 80px;">Dis Amt</th>
                            <th class="text-right" style="width: 95px;">Net Amount</th>
                            <th class="text-center" style="width: 35px;"></th>
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
                </div>

                <!-- Customer Search Select (ALWAYS VISIBLE as in Sales Bill) -->
                <div id="posCustomerSearchWrapper" class="mb-2">
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
                            <button type="button" id="posEditCustomerBtn" class="btn btn-sm btn-outline-primary p-1 shadow-none" title="Edit Customer Details" style="width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-edit" style="font-size: 0.95rem;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Customer Pet Details (Above Invoices & Favorites) -->
                    @php
                        $defaultPetSummary = $defaultCustomer && $defaultCustomer->pets ? $defaultCustomer->pets->map(fn($p) => $p->name ? ($p->breed?->name ? "{$p->name} ({$p->breed->name})" : ($p->petType?->name ? "{$p->name} ({$p->petType->name})" : $p->name)) : ($p->breed?->name ?? ($p->petType?->name ?? 'Pet')))->filter()->implode(', ') : '';
                    @endphp
                    <div id="posSelectedCustPets" class="pos-selected-cust-pets mt-2 pt-2 border-top" style="{{ $defaultPetSummary ? '' : 'display: none;' }}">
                        <div class="d-flex align-items-center text-dark small mb-1">
                            <i class="fas fa-paw text-warning mr-2" style="font-size: 0.95rem;"></i>
                            <span class="font-weight-600 text-muted mr-1">Pet & Breed:</span>
                            <span class="font-weight-bold text-dark text-truncate" id="posSelectedCustPetsText">
                                {{ $defaultPetSummary }}
                            </span>
                        </div>
                        <div id="posSelectedCustPetsTableContainer" class="mt-1" style="{{ $defaultCustomer && $defaultCustomer->pets && $defaultCustomer->pets->count() > 0 ? '' : 'display: none;' }}">
                            <table class="table table-xs table-bordered table-striped mb-0 text-dark small" id="posCustPetsMiniTable" style="font-size: 0.78rem;">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="py-1 px-2">Pet Name</th>
                                        <th class="py-1 px-2">Breed Type</th>
                                        <th class="py-1 px-2">Type</th>
                                    </tr>
                                </thead>
                                <tbody id="posCustPetsTableBody">
                                    @if($defaultCustomer && $defaultCustomer->pets)
                                        @foreach($defaultCustomer->pets as $pet)
                                            <tr>
                                                <td class="py-1 px-2 font-weight-bold">{{ $pet->name ?: '—' }}</td>
                                                <td class="py-1 px-2 text-primary">{{ $pet->breed?->name ?: '—' }}</td>
                                                <td class="py-1 px-2 text-muted">{{ $pet->petType?->name ?: 'Pet' }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Customer Quick Actions: Invoices & Favorites -->
                <div id="posCustomerInvoicesSection" class="pos-cust-invoices-section mt-2" style="{{ $defaultCustomer ? '' : 'display: none;' }}">
                    <div class="row g-1">
                        <div class="col-6 pr-1">
                            <button type="button" id="posViewCustomerInvoicesBtn" class="pos-total-invoices-btn w-100 d-flex justify-content-between align-items-center py-1 px-2" title="Click to view customer invoices">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-invoice text-info mr-1" style="font-size: 0.95rem;"></i>
                                    <span class="font-weight-600 text-dark small">Invoices</span>
                                </div>
                                <span class="badge badge-light border font-weight-bold text-dark px-1" id="posCustomerTotalInvoicesCount">0</span>
                            </button>
                        </div>
                        <div class="col-6 pl-1">
                            <button type="button" id="posViewCustomerFavoritesBtn" class="pos-total-invoices-btn w-100 d-flex justify-content-between align-items-center py-1 px-2 border-warning" title="Click to view favorite purchased products">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-star text-warning mr-1" style="font-size: 0.95rem;"></i>
                                    <span class="font-weight-600 text-dark small">Favorites</span>
                                </div>
                                <span class="badge badge-warning text-dark font-weight-bold px-1" id="posCustomerFavoritesCount">⭐ Top</span>
                            </button>
                        </div>
                    </div>
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
                <button type="button" class="pos-tender-btn" data-mode="Credit" id="posTenderCreditBtn">
                    <i class="fas fa-hand-holding-usd text-danger"></i>
                    <span>Credit (Alt+E)</span>
                </button>
                <button type="button" class="pos-tender-btn" data-mode="Split" id="posTenderSplitBtn">
                    <i class="fas fa-layer-group text-warning"></i>
                    <span>Split (Alt+S)</span>
                </button>
            </div>

            <!-- Credit Tender Controls -->
            <div id="posCreditSection" style="display: none;" class="p-3 bg-light rounded border mb-2 text-center">
                <div class="text-danger font-weight-bold mb-1"><i class="fas fa-hand-holding-usd mr-1"></i> Customer Credit Sale</div>
                <div class="small text-muted">The bill amount will be debited to customer's account balance.</div>
            </div>

            <!-- Cash Tender Controls -->
            <div id="posCashSection">
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

            <!-- Split Payment Summary Section (Visible when Split is active) -->
            <div id="posSplitSection" style="display: none;" class="p-3 bg-light rounded border mb-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold text-dark mb-0 small text-uppercase"><i class="fas fa-layer-group mr-1 text-warning"></i> Split Breakdown</h6>
                    <button type="button" class="btn btn-xs btn-outline-primary font-weight-bold" id="posEditSplitBtn">
                        <i class="fas fa-edit mr-1"></i> Edit Split
                    </button>
                </div>
                <div class="small">
                    <div class="d-flex justify-content-between mb-1" id="posSplitRowCash" style="display: none;">
                        <span class="text-muted"><i class="fas fa-money-bill-wave text-success mr-1"></i> Cash:</span>
                        <strong class="text-dark" id="posSplitDispCash">₹ 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1" id="posSplitRowCard" style="display: none;">
                        <span class="text-muted"><i class="fas fa-credit-card text-info mr-1"></i> Card:</span>
                        <strong class="text-dark" id="posSplitDispCard">₹ 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1" id="posSplitRowWallet" style="display: none;">
                        <span class="text-muted"><i class="fas fa-qrcode text-primary mr-1"></i> <span id="posSplitDispWalletType">UPI</span>:</span>
                        <strong class="text-dark" id="posSplitDispWallet">₹ 0.00</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1" id="posSplitRowCredit" style="display: none;">
                        <span class="text-muted"><i class="fas fa-hand-holding-usd text-danger mr-1"></i> Due/Credit:</span>
                        <strong class="text-danger" id="posSplitDispCredit">₹ 0.00</strong>
                    </div>
                    <div class="border-top pt-1 mt-1 d-flex justify-content-between font-weight-bold">
                        <span>Total Split:</span>
                        <span class="text-success" id="posSplitDispTotal">₹ 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Save Action Buttons (Task 5: Save, Save & WhatsApp, Save & Print, Cancel) -->
            <div class="pos-save-actions-wrap mt-2">
                <div class="d-flex mb-2" style="gap: 6px;">
                    <button type="button" id="posBtnSaveOnly" class="btn btn-success font-weight-bold flex-fill py-2 shadow-sm" disabled title="Save Bill without print">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                    <button type="button" id="posBtnSaveWhatsApp" class="btn text-white font-weight-bold flex-fill py-2 shadow-sm" style="background-color: #25D366; border-color: #25D366;" disabled title="Save & Send WhatsApp invoice">
                        <i class="fab fa-whatsapp mr-1"></i> Save & WhatsApp
                    </button>
                </div>
                <button type="button" id="posPayBtn" class="pos-pay-btn mb-2" disabled>
                    <i class="fas fa-print mr-2"></i> Save & Print (F6)
                </button>
                <button type="button" id="posBtnCancelTender" class="btn btn-outline-secondary btn-block btn-sm font-weight-bold py-1" title="Cancel / Reset tender">
                    <i class="fas fa-times mr-1"></i> Cancel
                </button>
            </div>

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
                        <li class="nav-item">
                            <a class="nav-link" id="pos-tab-custom-fields-link" data-toggle="tab" href="#pos-tab-custom-fields" role="tab">
                                <i class="fas fa-sliders-h mr-1" style="color: #6f42c1;"></i> Custom Fields
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
                                        @php
                                            $posGstTypes = \App\Models\GstType::where('status', true)->orderBy('name')->pluck('name', 'name');
                                            if ($posGstTypes->isEmpty()) {
                                                $posGstTypes = collect(['Un Register' => 'Un Register', 'Regular' => 'Regular', 'Composite' => 'Composite']);
                                            }
                                        @endphp
                                        @foreach($posGstTypes as $gtVal => $gtText)
                                            <option value="{{ $gtVal }}" {{ $gtVal === 'Un Register' ? 'selected' : '' }}>{{ $gtText }}</option>
                                        @endforeach
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
                                    <label class="font-weight-600 small mb-1">State</label>
                                    <select name="state" id="posCust_state" class="form-control form-control-sm">
                                        <option value="">-- Select State --</option>
                                        <optgroup label="⭐ Top States">
                                            <option value="Gujarat">Gujarat</option>
                                            <option value="Rajasthan">Rajasthan</option>
                                            <option value="Maharashtra">Maharashtra</option>
                                        </optgroup>
                                        <optgroup label="Other States & UTs">
                                            @foreach(\App\Helpers\IndianStates::states() as $stVal => $stLabel)
                                                @if(!in_array($stVal, ['Gujarat', 'Rajasthan', 'Maharashtra']))
                                                    <option value="{{ $stVal }}">{{ $stLabel }}</option>
                                                @endif
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="font-weight-600 small mb-1">City</label>
                                    <select name="city" id="posCust_city" class="form-control form-control-sm">
                                        <option value="">-- Select City --</option>
                                    </select>
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

                        <!-- 5. Custom Fields Tab -->
                        <div class="tab-pane fade" id="pos-tab-custom-fields" role="tabpanel">
                            <div class="row px-2">
                                <x-custom-fields-renderer module="Customer" :showHeader="false" colClass="col-md-6 mb-3" />
                            </div>
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

<!-- Customer Favorite Products Modal -->
<div class="modal fade" id="posCustomerFavoritesModal" tabindex="-1" role="dialog" aria-labelledby="posCustomerFavoritesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 840px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="modal-title font-weight-bold mb-0 text-white" id="posCustomerFavoritesModalLabel">
                        <i class="fas fa-star text-warning mr-2"></i>Favorite Products Purchased
                    </h5>
                    <small class="text-light" id="cfmSubtitle">Customer's top purchased items (Click "+ Add" to add to current bill)</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="outline: none;">
                    <span aria-hidden="true" style="font-size: 1.5rem;">&times;</span>
                </button>
            </div>
            
            <div class="modal-body p-3">
                <!-- Search Filter in Favorites -->
                <div class="input-group input-group-sm mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" id="cfmSearchInput" class="form-control" placeholder="Filter favorite items by name or code..." autocomplete="off">
                </div>

                <!-- Loading State -->
                <div id="cfmLoadingState" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-warning"></i>
                    <p class="mt-2 text-muted small">Loading favorite products...</p>
                </div>

                <!-- Empty State -->
                <div id="cfmEmptyState" class="text-center py-4 d-none">
                    <i class="fas fa-shopping-basket fa-2x text-muted mb-2"></i>
                    <p class="text-muted small mb-0">No purchase history found for this customer.</p>
                </div>

                <!-- Favorites Table -->
                <div class="table-responsive" id="cfmTableContainer" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="cfmTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Product Name</th>
                                <th style="width: 120px;" class="text-center">Code</th>
                                <th style="width: 100px;" class="text-right">Price</th>
                                <th style="width: 90px;" class="text-center">Stock</th>
                                <th style="width: 130px;" class="text-center bg-warning text-dark font-weight-bold">Purchased Qty</th>
                                <th style="width: 90px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cfmTableBody">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-0 px-4 pb-3 pt-0 justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Item Search & Selection Modal (F2) -->
<div class="modal fade" id="pos-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="posItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-2 px-3 align-items-center">
                <h5 class="modal-title font-weight-bold mb-0" id="posItemSearchLabel" style="font-size: 1.1rem;">
                    <i class="fas fa-boxes mr-2 text-warning"></i>Select Item (F2) — Real-time Stock Lookup
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3 bg-light">
                <!-- Filters -->
                <div class="row mb-3 bg-white p-2 rounded shadow-sm mx-0 align-items-center">
                    <div class="col-md-5 mb-1 mb-md-0">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light text-primary font-weight-bold"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="pos-isl-filter-name" class="form-control form-control-sm font-weight-bold" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3 mb-1 mb-md-0">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="pos-isl-filter-code" class="form-control form-control-sm" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 mb-1 mb-md-0">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="pos-isl-filter-expiry" class="form-control form-control-sm" placeholder="Expiry (YYYY-MM)" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="pos-isl-btn-clear" class="btn btn-sm btn-outline-secondary font-weight-bold px-3">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results states -->
                <div id="pos-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted font-weight-bold">Loading items from branch inventory…</p>
                </div>
                <div id="pos-isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted font-weight-bold">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive bg-white rounded shadow-sm" id="pos-isl-table-wrap" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="pos-isl-items-table">
                        <thead class="bg-dark text-white sticky-top" style="z-index: 2;">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 130px;">Code / Barcode</th>
                                <th class="text-center" style="width: 140px;">Expiry (Purchase Se)</th>
                                <th class="text-right" style="width: 100px;">Qty (Stock)</th>
                                <th class="text-right" style="width: 100px;">Sell Price</th>
                                <th class="text-right" style="width: 100px;">MRP</th>
                                <th class="text-center" style="width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="pos-isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                    <small class="text-muted font-weight-bold" id="pos-isl-count-label"></small>
                    <small class="text-muted"><kbd>↑</kbd> <kbd>↓</kbd> to Navigate &bull; <kbd>Enter</kbd> to Select &bull; <kbd>Esc</kbd> to Close</small>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-white justify-content-between">
                <span class="text-muted small"><i class="fas fa-info-circle mr-1 text-primary"></i>Active Branch: <strong class="text-dark">{{ $branch->name ?? 'Default Branch' }}</strong></span>
                <button type="button" class="btn btn-secondary btn-sm px-3 font-weight-bold" data-dismiss="modal">Close (Esc)</button>
            </div>
        </div>
    </div>
</div>

<!-- POS Split Payment / Multi-Tender Modal -->
<div class="modal fade" id="posSplitModal" tabindex="-1" role="dialog" aria-labelledby="posSplitModalTitle" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
        <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header py-3 px-4 text-white" style="background-color: var(--pos-header-bg, #1e293b);">
                <div class="d-flex align-items-center">
                    <i class="fas fa-layer-group text-warning mr-2" style="font-size: 1.2rem;"></i>
                    <h5 class="modal-title font-weight-bold mb-0" id="posSplitModalTitle">Split Payment (Multi-Tender)</h5>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <!-- Bill Amount Banner -->
                <div class="d-flex justify-content-between align-items-center p-3 mb-3 bg-white rounded border shadow-sm">
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold">Bill Total</span>
                        <h3 class="font-weight-bold text-dark mb-0" id="posSplitBillTotal">₹ 0.00</h3>
                    </div>
                    <div class="text-right">
                        <span class="text-muted small text-uppercase font-weight-bold">Remaining</span>
                        <h4 class="font-weight-bold mb-0 text-danger" id="posSplitRemaining">₹ 0.00</h4>
                    </div>
                </div>

                <!-- Split Input Rows -->
                <div class="bg-white p-3 rounded border mb-3 shadow-sm">
                    <!-- Cash Input -->
                    <div class="form-group row mb-2 align-items-center">
                        <label for="posSplitCashInput" class="col-sm-4 col-form-label font-weight-bold text-dark small mb-0">
                            <i class="fas fa-money-bill-wave text-success mr-1"></i> Cash (Alt+A)
                        </label>
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text font-weight-bold">₹</span></div>
                                <input type="number" step="any" min="0" id="posSplitCashInput" class="form-control font-weight-bold text-right split-input" placeholder="0.00" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-split-fill" type="button" data-target="#posSplitCashInput" title="Fill Remaining Balance">Max</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Input -->
                    <div class="form-group row mb-2 align-items-center">
                        <label for="posSplitCardInput" class="col-sm-4 col-form-label font-weight-bold text-dark small mb-0">
                            <i class="fas fa-credit-card text-info mr-1"></i> Card (Alt+C)
                        </label>
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text font-weight-bold">₹</span></div>
                                <input type="number" step="any" min="0" id="posSplitCardInput" class="form-control font-weight-bold text-right split-input" placeholder="0.00" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-split-fill" type="button" data-target="#posSplitCardInput" title="Fill Remaining Balance">Max</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- UPI / Wallet Input -->
                    <div class="form-group row mb-2 align-items-center">
                        <label for="posSplitWalletInput" class="col-sm-4 col-form-label font-weight-bold text-dark small mb-0">
                            <i class="fas fa-qrcode text-primary mr-1"></i> UPI / Wallet (Alt+W)
                        </label>
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm mb-1">
                                <div class="input-group-prepend"><span class="input-group-text font-weight-bold">₹</span></div>
                                <input type="number" step="any" min="0" id="posSplitWalletInput" class="form-control font-weight-bold text-right split-input" placeholder="0.00" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-split-fill" type="button" data-target="#posSplitWalletInput" title="Fill Remaining Balance">Max</button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="small text-muted mr-2 font-weight-600">Type:</span>
                                <select id="posSplitWalletType" class="form-control form-control-sm py-0" style="height: 28px; font-size: 0.82rem;">
                                    <option value="GPAY" selected>Google Pay (GPAY)</option>
                                    <option value="PHONEPE">PhonePe</option>
                                    <option value="PAYTM">Paytm</option>
                                    <option value="PINELAB">Pine Labs</option>
                                    <option value="BHARATPE">BharatPe</option>
                                    <option value="OTHER">Other UPI</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Credit / Due Input -->
                    <div class="form-group row mb-0 align-items-center">
                        <label for="posSplitCreditInput" class="col-sm-4 col-form-label font-weight-bold text-dark small mb-0">
                            <i class="fas fa-hand-holding-usd text-danger mr-1"></i> Credit / Due
                        </label>
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text font-weight-bold">₹</span></div>
                                <input type="number" step="any" min="0" id="posSplitCreditInput" class="form-control font-weight-bold text-right split-input" placeholder="0.00" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary btn-split-fill" type="button" data-target="#posSplitCreditInput" title="Fill Remaining Balance">Max</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Notice -->
                <div id="posSplitAlert" class="alert alert-danger py-2 px-3 mb-0 small font-weight-bold d-none">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <span id="posSplitAlertText"></span>
                </div>
            </div>

            <div class="modal-footer py-2 px-4 bg-white d-flex justify-content-between">
                <button type="button" class="btn btn-secondary font-weight-bold btn-sm" data-dismiss="modal">
                    Cancel (Esc)
                </button>
                <button type="button" id="posSplitConfirmBtn" class="btn btn-success font-weight-bold px-4 btn-sm">
                    <i class="fas fa-check-circle mr-1"></i> Confirm Split (Enter)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen POS Quick Lock Screen Overlay -->
<div id="posLockOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.94); backdrop-filter: blur(14px); z-index: 999999; align-items: center; justify-content: center; flex-direction: column;">
    <div class="card shadow-lg border-0 text-center" style="width: 360px; border-radius: 16px; background: #ffffff; padding: 28px 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55);">
        <div class="mb-3">
            <div style="width: 70px; height: 70px; margin: 0 auto; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.4);">
                <i class="fas fa-lock"></i>
            </div>
        </div>
        <h4 class="font-weight-bold text-dark mb-1">Terminal Locked</h4>
        <p class="text-muted small mb-3">Cashier: <strong class="text-dark">{{ auth()->user()->name }}</strong></p>

        <!-- PIN Display Dots -->
        <div class="d-flex justify-content-center align-items-center mb-3" id="posPinDotsContainer">
            <span class="pos-pin-dot mr-2" style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid #94a3b8; display: inline-block;"></span>
            <span class="pos-pin-dot mr-2" style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid #94a3b8; display: inline-block;"></span>
            <span class="pos-pin-dot mr-2" style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid #94a3b8; display: inline-block;"></span>
            <span class="pos-pin-dot" style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid #94a3b8; display: inline-block;"></span>
        </div>

        <div id="posPinError" class="alert alert-danger py-1 px-2 small font-weight-bold mb-3" style="display: none;"></div>

        <!-- Numeric Keypad Grid -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="1">1</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="2">2</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="3">3</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="4">4</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="5">5</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="6">6</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="7">7</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="8">8</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="9">9</button>
            <button type="button" class="btn btn-outline-danger font-weight-bold py-2" style="font-size: 14px; border-radius: 10px;" id="posPinClearBtn">Clear</button>
            <button type="button" class="btn btn-light font-weight-bold pos-pin-btn py-2" style="font-size: 20px; border-radius: 10px;" data-digit="0">0</button>
            <button type="button" class="btn btn-outline-secondary font-weight-bold py-2" style="font-size: 16px; border-radius: 10px;" id="posPinBackspaceBtn"><i class="fas fa-backspace"></i></button>
        </div>

        <div class="small text-muted">
            <span>Enter 4-digit PIN to unlock. (Default: <code>0000</code>)</span>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    window.APP_URL = (function() {
        var url = "{{ url('/') }}";
        if (window.location.protocol === 'https:' && url.indexOf('http:') === 0) {
            url = url.replace(/^http:/, 'https:');
        }
        try {
            var u = new URL(url);
            if (u.hostname !== window.location.hostname) {
                return window.location.origin + u.pathname.replace(/\/+$/, '');
            }
        } catch (e) {}
        return url.replace(/\/+$/, '');
    })();
    window.CSRF_TOKEN = "{{ csrf_token() }}";
    window.STORE_NAME = "{{ config('app.name', 'UrbanPOS') }}";
    window.POS_LOCK_VERIFY_URL = "{{ route('pos.verify-pin') }}";
    window.BRANCH_UPI_ID = "{{ $branch->upi_id ?? '' }}";
    window.BRANCH_UPI_NAME = "{{ $branch->upi_payee_name ?? ($branch->name ?? 'UrbanPOS') }}";
    window.ISL_URL = "{{ route('sales.sales-bills.item-list') }}";
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
    window.TENDER_TYPES = {!! json_encode($tenderTypes ?? []) !!};

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

<script src="{{ asset('js/indian-states-cities.js') }}"></script>
<script>
$(document).ready(function () {
    if (typeof initIndianStateCity === 'function') {
        initIndianStateCity('#posCust_state', '#posCust_city', '', '');
    }
});
</script>
<script src="{{ asset('js/pos-hotkeys.js') }}?v={{ file_exists(public_path('js/pos-hotkeys.js')) ? filemtime(public_path('js/pos-hotkeys.js')) : '1.0' }}"></script>
<script src="{{ asset('js/pos-terminal.js') }}?v={{ file_exists(public_path('js/pos-terminal.js')) ? filemtime(public_path('js/pos-terminal.js')) : '1.0' }}"></script>
</body>
</html>
