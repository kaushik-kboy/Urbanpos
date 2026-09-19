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
<body>

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
                    <button type="button" id="posHoldBtn" class="btn btn-outline-secondary btn-sm mr-2 font-weight-bold shadow-sm" title="Park Current Bill (F6)">
                        <i class="fas fa-pause-circle mr-1 text-warning"></i> Hold (F6)
                    </button>
                    <button type="button" id="posRecallBtn" class="btn btn-outline-info btn-sm mr-2 font-weight-bold shadow-sm" title="Recall Held Bill (F7)">
                        <i class="fas fa-play-circle mr-1"></i> Recall (F7)
                        <span id="posHeldCountBadge" class="badge badge-warning ml-1" style="display: none;">0</span>
                    </button>
                    <button type="button" id="posClearBtn" class="btn btn-outline-danger btn-sm font-weight-bold shadow-sm" title="Clear Entire Cart">
                        <i class="fas fa-trash-alt mr-1"></i> Clear Cart
                    </button>
                </div>
                <div class="text-muted small">
                    Hotkeys: <span class="badge badge-light border">F2: Scan</span> <span class="badge badge-light border">F8: Cash</span> <span class="badge badge-light border">F9: UPI</span> <span class="badge badge-light border">F12: Pay</span>
                </div>
            </footer>

        </section>

        <!-- RIGHT PANE: Customer, Totals & Tender (32%) -->
        <aside class="pos-right-pane">

            <!-- Customer Details Card -->
            <div class="pos-customer-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="font-weight-bold text-muted small text-uppercase mb-0">
                        <i class="fas fa-user mr-1 text-primary"></i> Customer
                    </label>
                    <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none font-weight-bold" data-toggle="modal" data-target="#posAddCustomerModal">
                        + New Customer
                    </button>
                </div>
                <select id="posCustomerSelect" class="form-control form-control-sm select2">
                    @foreach ($customers as $cId => $cName)
                        <option value="{{ $cId }}" @selected(($defaultCustomerId ?? '') == $cId)>{{ $cName }}</option>
                    @endforeach
                </select>
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
                    <span>Cash (F8)</span>
                </button>
                <button type="button" class="pos-tender-btn" data-mode="UPI">
                    <i class="fas fa-qrcode text-primary"></i>
                    <span>UPI / QR (F9)</span>
                </button>
                <button type="button" class="pos-tender-btn" data-mode="Card">
                    <i class="fas fa-credit-card text-info"></i>
                    <span>Card (F10)</span>
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
                <i class="fas fa-check-circle mr-2"></i> Pay & Print (F12)
            </button>

        </aside>

    </main>

</div>

<!-- Quick Add Customer Modal -->
<div class="modal fade" id="posAddCustomerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header text-white py-2" style="background-color: var(--pos-header-bg);">
                <h6 class="modal-title font-weight-bold mb-0"><i class="fas fa-user-plus mr-2"></i> Add New Customer</h6>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="posQuickCustomerForm">
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" id="quickCustName" class="form-control form-control-sm" required placeholder="e.g. Rahul Sharma">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" id="quickCustMobile" class="form-control form-control-sm" required maxlength="10" placeholder="10-digit mobile number">
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted mb-1">Email Address</label>
                        <input type="email" id="quickCustEmail" class="form-control form-control-sm" placeholder="Optional email">
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-save mr-1"></i> Save Customer</button>
                </div>
            </form>
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

    // Initialize Select2
    $(document).ready(function() {
        $('#posCustomerSelect').select2({
            theme: 'bootstrap4',
            placeholder: 'Search Customer Mobile / Name',
            allowClear: false
        });

        // Quick Add Customer Submission
        $('#posQuickCustomerForm').on('submit', function(e) {
            e.preventDefault();
            const name = $('#quickCustName').val();
            const mobile = $('#quickCustMobile').val();
            const email = $('#quickCustEmail').val();

            $.ajax({
                url: "{{ route('master.customers.store') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    name: name,
                    mobile: mobile,
                    email: email,
                    status: 1
                },
                success: function(resp) {
                    $('#posAddCustomerModal').modal('hide');
                    // Add new option and select it
                    const newOption = new Option(name + ' (' + mobile + ')', resp.id || resp.customer?.id, true, true);
                    $('#posCustomerSelect').append(newOption).trigger('change');
                    $('#posQuickCustomerForm')[0].reset();
                },
                error: function(err) {
                    alert('Error creating customer. Please ensure mobile number is unique.');
                }
            });
        });
    });
</script>

<script src="{{ asset('js/pos-terminal.js') }}?v={{ time() }}"></script>

</body>
</html>
