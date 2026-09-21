/**
 * UrbanPOS High-Speed Retail POS Terminal Engine
 */

(function() {
    'use strict';

    // State
    const state = {
        cart: [],
        customer_id: null,
        branch_id: null,
        invoice_type: 'Retail Invoice',
        sales_type: 'Local',
        delivery_type: 'Delivered',
        bill_discount: 0,
        tender_mode: 'Cash', // Cash, UPI, Card, Split
        cash_received: 0,
        last_scan_time: 0,
        last_scan_code: '',
        held_bills: []
    };

    // Audio Feedback Generator using Web Audio API
    const sound = {
        ctx: null,
        init() {
            if (!this.ctx && (window.AudioContext || window.webkitAudioContext)) {
                this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            }
        },
        beep(freq = 1800, duration = 0.08, type = 'sine') {
            try {
                this.init();
                if (!this.ctx) return;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
                gain.gain.setValueAtTime(0.12, this.ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start();
                osc.stop(this.ctx.currentTime + duration);
            } catch (e) {
                // Ignore audio errors
            }
        },
        success() {
            this.beep(880, 0.06);
            setTimeout(() => this.beep(1760, 0.1), 70);
        },
        error() {
            this.beep(300, 0.15, 'sawtooth');
        }
    };

    // DOM Elements
    let scanInput, searchResults, cartTableBody, emptyCartNotice;
    let subtotalEl, discountEl, gstEl, roundOffEl, grandTotalEl;
    let cashReceivedInput, changeDueEl, payBtn, itemsCountBadge;

    document.addEventListener('DOMContentLoaded', () => {
        initDOMElements();
        initState();
        initCustomerLogic();
        setupEventListeners();
        setupKeyboardShortcuts();
        loadHeldBills();
        renderCart();
        initPosItemSearchModal();
        focusScanner();
    });

    function initDOMElements() {
        scanInput = document.getElementById('posScanInput');
        searchResults = document.getElementById('posSearchResults');
        cartTableBody = document.getElementById('posCartBody');
        emptyCartNotice = document.getElementById('posEmptyCart');
        subtotalEl = document.getElementById('posSubtotal');
        discountEl = document.getElementById('posDiscount');
        gstEl = document.getElementById('posGst');
        roundOffEl = document.getElementById('posRoundOff');
        grandTotalEl = document.getElementById('posGrandTotal');
        cashReceivedInput = document.getElementById('posCashReceived');
        changeDueEl = document.getElementById('posChangeDue');
        payBtn = document.getElementById('posPayBtn');
        itemsCountBadge = document.getElementById('posItemsCountBadge');
    }

    function initState() {
        state.branch_id = document.getElementById('posBranchSelect')?.value || 1;
        if (window.INITIAL_CUSTOMER && window.INITIAL_CUSTOMER.id) {
            state.customer_id = window.INITIAL_CUSTOMER.id;
        } else {
            state.customer_id = document.getElementById('posCustomerSelect')?.value || null;
        }

        if (window.EDIT_BILL) {
            state.edit_id = window.EDIT_BILL.id;
            state.invoice_type = window.EDIT_BILL.invoice_type || 'Retail Invoice';
            state.sales_type = window.EDIT_BILL.sales_type || 'B2C';
            state.delivery_type = window.EDIT_BILL.delivery_type || 'Direct';
            state.cash_received = window.EDIT_BILL.payments && window.EDIT_BILL.payments.length > 0 ? parseFloat(window.EDIT_BILL.payments[0].amount) : 0;
            if (cashReceivedInput) cashReceivedInput.value = state.cash_received;
            
            state.cart = window.EDIT_BILL.items.map(i => ({
                id: i.item_id,
                name: i.item ? i.item.name : 'Unknown Item',
                sell_price: parseFloat(i.sell_price) || 0,
                mrp: parseFloat(i.mrp) || 0,
                qty: parseFloat(i.qty) || 1,
                gst_percent: i.item && i.item.gst_tax ? parseFloat(i.item.gst_tax.igst) : 0,
                disc_percent: parseFloat(i.disc_percent) || 0,
                disc_amount: parseFloat(i.disc_amount) || 0
            }));
            
            // Re-render UI pieces on load
            setTimeout(() => {
                if (document.getElementById('posBranchSelect')) document.getElementById('posBranchSelect').value = window.EDIT_BILL.branch_id || state.branch_id;
                renderCart();
            }, 200);
        }
    }

    function setupEventListeners() {
        // Scanner Barcode / Query input
        let searchTimeout = null;
        scanInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(searchTimeout);
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => searchItems(query), 180);
            } else {
                hideSearchResults();
            }
        });

        scanInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = scanInput.value.trim();
                if (code) {
                    processBarcodeScan(code);
                }
            } else if (e.key === 'ArrowDown') {
                // Navigate search dropdown if visible
                const first = searchResults.querySelector('.pos-search-item');
                if (first) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // Click outside closes search dropdown
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#posScannerContainer')) {
                hideSearchResults();
            }
        });

        // Branch change
        const branchSelect = document.getElementById('posBranchSelect');
        if (branchSelect) {
            branchSelect.addEventListener('change', () => {
                state.branch_id = branchSelect.value;
            });
        }

        // Cash received input
        if (cashReceivedInput) {
            cashReceivedInput.addEventListener('input', () => {
                state.cash_received = parseFloat(cashReceivedInput.value) || 0;
                updateChangeDue();
            });
        }

        // Cash chips
        document.querySelectorAll('.pos-cash-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const add = chip.dataset.val;
                const total = computeTotals().grandTotal;
                if (add === 'exact') {
                    state.cash_received = total;
                } else {
                    state.cash_received = parseFloat(add);
                }
                cashReceivedInput.value = state.cash_received.toFixed(2);
                updateChangeDue();
            });
        });

        // Tender modes
        document.querySelectorAll('.pos-tender-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.pos-tender-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.tender_mode = btn.dataset.mode;
                toggleTenderControls();
            });
        });

        // Pay Button
        if (payBtn) {
            payBtn.addEventListener('click', submitSale);
        }

        // Action bar buttons
        document.getElementById('posHoldBtn')?.addEventListener('click', holdCurrentBill);
        document.getElementById('posRecallBtn')?.addEventListener('click', recallHeldBill);
        document.getElementById('posClearBtn')?.addEventListener('click', clearCart);
    }

    function toggleTenderControls() {
        const cashSection = document.getElementById('posCashSection');
        const upiSection = document.getElementById('posUpiSection');
        if (!cashSection || !upiSection) return;

        if (state.tender_mode === 'Cash') {
            cashSection.style.display = 'block';
            upiSection.style.display = 'none';
        } else if (state.tender_mode === 'UPI') {
            cashSection.style.display = 'none';
            upiSection.style.display = 'block';
            generateUpiQrCode();
        } else {
            cashSection.style.display = 'none';
            upiSection.style.display = 'none';
        }
    }

    // Barcode & Item Search
    async function processBarcodeScan(code) {
        // Double-scan guard (prevent hardware scanner bounce within 350ms)
        const now = Date.now();
        if (code === state.last_scan_code && (now - state.last_scan_time) < 350) {
            return;
        }
        state.last_scan_time = now;
        state.last_scan_code = code;

        try {
            const url = `${window.APP_URL || ''}/sales/sales-bills/lookup-item?q=${encodeURIComponent(code)}&branch_id=${state.branch_id || ''}&limit=1`;
            const resp = await fetch(url);
            const items = await resp.json();

            if (items && items.length > 0) {
                addItemToCart(items[0]);
                sound.beep(2100, 0.07);
                scanInput.value = '';
                hideSearchResults();
            } else {
                sound.error();
                showNotification('Item not found for barcode: ' + code, 'warning');
            }
        } catch (e) {
            sound.error();
            showNotification('Error querying barcode', 'danger');
        } finally {
            focusScanner();
        }
    }

    async function searchItems(query) {
        try {
            const url = `${window.APP_URL || ''}/sales/sales-bills/lookup-item?q=${encodeURIComponent(query)}&branch_id=${state.branch_id || ''}&limit=8`;
            const resp = await fetch(url);
            const items = await resp.json();
            renderSearchResults(items);
        } catch (e) {
            hideSearchResults();
        }
    }

    function renderSearchResults(items) {
        if (!items || items.length === 0) {
            searchResults.innerHTML = '<div class="p-3 text-muted text-center small">No matching items found</div>';
            searchResults.style.display = 'block';
            return;
        }

        let html = '';
        items.forEach(item => {
            const price = parseFloat(item.sell_price || item.mrp || 0).toFixed(2);
            const stock = parseFloat(item.qty || 0);
            const stockBadge = stock > 0 ? `<span class="badge badge-success">${stock} in stock</span>` : `<span class="badge badge-danger">Out of stock</span>`;
            html += `
                <div class="pos-search-item" data-item='${JSON.stringify(item).replace(/'/g, "&#39;")}'>
                    <div>
                        <strong class="text-dark">${escapeHtml(item.name)}</strong>
                        <div class="small text-muted">Code: <code>${escapeHtml(item.item_code || item.ean_upc_code || '-')}</code> &bull; ${stockBadge}</div>
                    </div>
                    <div class="text-right">
                        <strong class="text-primary" style="font-size: 1.05rem;">₹ ${price}</strong>
                    </div>
                </div>
            `;
        });

        searchResults.innerHTML = html;
        searchResults.style.display = 'block';

        searchResults.querySelectorAll('.pos-search-item').forEach(el => {
            el.addEventListener('click', () => {
                const itemData = JSON.parse(el.dataset.item);
                addItemToCart(itemData);
                sound.beep(2100, 0.07);
                scanInput.value = '';
                hideSearchResults();
                focusScanner();
            });
        });
    }

    function hideSearchResults() {
        if (searchResults) searchResults.style.display = 'none';
    }

    // Cart Management
    function addItemToCart(item) {
        const existing = state.cart.find(c => c.id === item.id);
        if (existing) {
            existing.qty += 1;
        } else {
            state.cart.push({
                id: item.id,
                name: item.name,
                code: item.item_code || item.ean_upc_code || '',
                sell_price: parseFloat(item.sell_price || item.mrp || 0),
                mrp: parseFloat(item.mrp || item.sell_price || 0),
                qty: 1,
                gst_percent: parseFloat(item.gst_percent || 0),
                disc_percent: 0,
                disc_amount: 0
            });
        }
        renderCart();
    }

    function updateItemQty(index, delta) {
        if (!state.cart[index]) return;
        state.cart[index].qty += delta;
        if (state.cart[index].qty <= 0) {
            state.cart.splice(index, 1);
        }
        renderCart();
        focusScanner();
    }

    function setItemQty(index, val) {
        if (!state.cart[index]) return;
        const q = parseFloat(val);
        if (isNaN(q) || q <= 0) {
            state.cart.splice(index, 1);
        } else {
            state.cart[index].qty = q;
        }
        renderCart();
    }

    function removeItem(index) {
        if (!state.cart[index]) return;
        state.cart.splice(index, 1);
        renderCart();
        focusScanner();
    }

    function clearCart() {
        if (state.cart.length === 0) return;
        if (confirm('Clear all items in cart?')) {
            state.cart = [];
            renderCart();
            focusScanner();
        }
    }

    // Calculations & Rendering
    function computeTotals() {
        let subtotal = 0;
        let totalTax = 0;
        let totalItems = 0;

        state.cart.forEach(item => {
            const lineTotal = item.qty * item.sell_price;
            subtotal += lineTotal;
            totalItems += item.qty;

            // Included GST calculation
            if (item.gst_percent > 0) {
                const taxPortion = lineTotal - (lineTotal / (1 + (item.gst_percent / 100)));
                totalTax += taxPortion;
            }
        });

        const discountedSubtotal = Math.max(0, subtotal - state.bill_discount);
        const roundedGrandTotal = Math.round(discountedSubtotal);
        const roundOff = roundedGrandTotal - discountedSubtotal;

        return {
            subtotal,
            discount: state.bill_discount,
            tax: totalTax,
            roundOff: roundOff,
            grandTotal: roundedGrandTotal,
            totalItems: totalItems
        };
    }

    function renderCart() {
        if (!cartTableBody) return;

        if (state.cart.length === 0) {
            cartTableBody.innerHTML = '';
            if (emptyCartNotice) emptyCartNotice.style.display = 'flex';
            if (payBtn) payBtn.disabled = true;
            updateSummaryUI(computeTotals());
            return;
        }

        if (emptyCartNotice) emptyCartNotice.style.display = 'none';
        if (payBtn) payBtn.disabled = false;

        let html = '';
        state.cart.forEach((item, idx) => {
            const lineTotal = (item.qty * item.sell_price).toFixed(2);
            html += `
                <tr>
                    <td class="text-center font-weight-bold text-muted" style="width: 35px;">${idx + 1}</td>
                    <td>
                        <div class="font-weight-bold text-dark">${escapeHtml(item.name)}</div>
                        <div class="small text-muted font-monospace">${escapeHtml(item.code)} &bull; GST ${item.gst_percent}%</div>
                    </td>
                    <td class="text-right font-weight-bold" style="width: 90px;">
                        ₹ ${item.sell_price.toFixed(2)}
                    </td>
                    <td class="text-center" style="width: 140px;">
                        <div class="pos-qty-control">
                            <button type="button" class="pos-qty-btn" onclick="window.posEngine.updateQty(${idx}, -1)">−</button>
                            <input type="text" class="pos-qty-input" value="${item.qty}" onchange="window.posEngine.setQty(${idx}, this.value)">
                            <button type="button" class="pos-qty-btn" onclick="window.posEngine.updateQty(${idx}, 1)">+</button>
                        </div>
                    </td>
                    <td class="text-right font-weight-bold text-success" style="width: 110px; font-size: 1rem;">
                        ₹ ${lineTotal}
                    </td>
                    <td class="text-center" style="width: 40px;">
                        <button type="button" class="btn btn-xs btn-outline-danger" title="Remove Item" onclick="window.posEngine.removeRow(${idx})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        cartTableBody.innerHTML = html;
        const totals = computeTotals();
        updateSummaryUI(totals);
    }

    function updateSummaryUI(totals) {
        if (subtotalEl) subtotalEl.textContent = '₹ ' + totals.subtotal.toFixed(2);
        if (discountEl) discountEl.textContent = '- ₹ ' + totals.discount.toFixed(2);
        if (gstEl) gstEl.textContent = '₹ ' + totals.tax.toFixed(2);
        if (roundOffEl) roundOffEl.textContent = (totals.roundOff >= 0 ? '+ ₹ ' : '- ₹ ') + Math.abs(totals.roundOff).toFixed(2);
        if (grandTotalEl) grandTotalEl.textContent = '₹ ' + totals.grandTotal.toFixed(2);
        if (itemsCountBadge) itemsCountBadge.textContent = `${totals.totalItems} units`;

        // If Cash mode, update change due
        updateChangeDue();

        // If UPI mode, update QR code in real time
        if (state.tender_mode === 'UPI') {
            generateUpiQrCode();
        }
    }

    function updateChangeDue() {
        if (!changeDueEl) return;
        const total = computeTotals().grandTotal;
        const rec = state.cash_received;
        const change = Math.max(0, rec - total);
        changeDueEl.textContent = '₹ ' + change.toFixed(2);
    }

    // Dynamic UPI QR Code
    function generateUpiQrCode() {
        const qrImg = document.getElementById('posUpiQrImage');
        const qrAmount = document.getElementById('posUpiQrAmount');
        if (!qrImg) return;

        const total = computeTotals().grandTotal;
        if (qrAmount) qrAmount.textContent = '₹ ' + total.toFixed(2);

        // Store VPA - active branch or default configured
        const vpa = (window.BRANCH_UPI_ID || '').trim() || (window.STORE_VPA || 'chandakinfotech@icici');
        const name = encodeURIComponent((window.BRANCH_UPI_NAME || '').trim() || (window.STORE_NAME || 'UrbanPOS'));
        const upiUrl = `upi://pay?pa=${vpa}&pn=${name}&am=${total.toFixed(2)}&cu=INR&tn=UrbanPOS%20Bill`;
        const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(upiUrl)}`;
        qrImg.src = qrApi;
    }

    // ==========================================
    // CUSTOMER MANAGEMENT & INVOICES MODAL
    // ==========================================
    let currentCustomerInvoices = [];
    let filteredCustomerInvoices = [];
    let cimCurrentPage = 1;
    const CIM_PAGE_SIZE = 7;
    let lastCustomerSearchTerm = '';
    let posPetIndex = 0;

    function addPetRow(petData = null) {
        const template = document.getElementById('pos-pet-row-template');
        if (!template) return;
        const html = template.innerHTML.replaceAll('__INDEX__', posPetIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const row = wrapper.firstElementChild;

        if (petData) {
            row.querySelector('.pos-pet-id').value = petData.id || '';
            if (petData.breed_id) row.querySelector('.pos-pet-breed').value = petData.breed_id;
            if (petData.pet_type_id) row.querySelector('.pos-pet-type').value = petData.pet_type_id;
            row.querySelector('.pos-pet-name').value = petData.name || '';
            row.querySelector('.pos-pet-gender').value = petData.gender || 'Male';
            row.querySelector('.pos-pet-age').value = petData.age || '';
            row.querySelector('.pos-pet-remarks').value = petData.remarks || '';
            if (petData.birth_date) {
                const bd = typeof petData.birth_date === 'string' ? petData.birth_date.substring(0, 10) : '';
                row.querySelector('.pos-pet-birthdate').value = bd;
            }
        }

        const container = document.getElementById('pos-pet-rows');
        if (container) {
            container.appendChild(row);
            posPetIndex++;
        }
    }

    function initCustomerLogic() {
        const $custSelect = $('#posCustomerSelect');

        // Track search term on input
        $(document).on('input keyup', '.select2-search__field', function () {
            lastCustomerSearchTerm = $.trim($(this).val() || '');
        });

        // Top "+ New Customer" button in right pane
        $('#posHeaderNewCustBtn').on('click', function (e) {
            e.preventDefault();
            openAddCustomerModalWithTerm('');
        });

        // Initialize Select2 with AJAX customer search
        if ($custSelect.length) {
            $custSelect.select2({
                theme: 'bootstrap4',
                placeholder: 'Search Customer Mobile / Name',
                allowClear: false,
                width: '100%',
                ajax: {
                    url: window.CUSTOMER_SEARCH_URL || `${window.APP_URL || ''}/sales/sales-bills/customer-search`,
                    dataType: 'json',
                    delay: 200,
                    data: function (params) {
                        lastCustomerSearchTerm = $.trim(params.term || '');
                        return { q: lastCustomerSearchTerm };
                    },
                    processResults: function (data) {
                        return { results: data.results || [] };
                    },
                    cache: true
                },
                language: {
                    noResults: function () {
                        const term = lastCustomerSearchTerm || $('.select2-search__field').val() || '';
                        const safeTerm = escapeHtml(term).replace(/"/g, '&quot;');
                        return `
                            <div class="py-2 px-1 text-center select2-no-results-box">
                                <div class="small text-muted mb-1">Customer not found</div>
                                <button type="button" class="btn btn-xs btn-primary font-weight-bold btn-select2-quick-add" onmousedown="event.preventDefault(); event.stopPropagation(); window.posEngine.triggerCreateCustomer('${safeTerm}');" onclick="event.preventDefault(); event.stopPropagation(); window.posEngine.triggerCreateCustomer('${safeTerm}');">
                                    <i class="fas fa-user-plus mr-1"></i> + Create Customer
                                </button>
                            </div>
                        `;
                    }
                },
                escapeMarkup: function (markup) {
                    return markup;
                }
            });

            // Auto-focus search input whenever select2 opens (no need to click field again)
            $custSelect.on('select2:open', function () {
                setTimeout(function () {
                    const searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.focus();
                    }
                }, 50);
            });

            // Select customer
            $custSelect.on('select2:select change', function (e) {
                const data = e.params ? e.params.data : null;
                const custId = $custSelect.val();
                if (custId) {
                    state.customer_id = custId;
                    if (data && data.name) {
                        updateSelectedCustomerUI({
                            id: custId,
                            name: data.name,
                            mobile: data.mobile || '',
                            edit_url: data.edit_url || `${window.CUSTOMER_EDIT_BASE_URL || ''}/${custId}/edit`,
                            pets: data.pets || [],
                            pets_summary: data.pets_summary || ''
                        });
                    } else {
                        fetchCustomerDetailsAndInvoices(custId);
                    }
                    loadCustomerLoyalty(custId);
                    loadCustomerInvoices(custId);
                }
            });

            // Enter key in Select2 search field when no results found -> open Add Customer Modal
            $(document).on('keydown', '.select2-search__field', function (e) {
                if (e.key === 'Enter') {
                    const hasResults = $('.select2-results__option:not(.select2-results__message)').length > 0;
                    if (!hasResults) {
                        e.preventDefault();
                        const term = lastCustomerSearchTerm || $(this).val() || '';
                        $custSelect.select2('close');
                        openAddCustomerModalWithTerm(term);
                    }
                }
            });

            // Delegated click / mousedown / touch for + Create Customer in Select2 dropdown
            $(document).on('mousedown pointerdown click', '.btn-select2-quick-add, #btnSelect2QuickAdd', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const term = lastCustomerSearchTerm || $('.select2-search__field').val() || '';
                if ($custSelect.length) {
                    $custSelect.select2('close');
                }
                openAddCustomerModalWithTerm(term);
            });
        }

        // Add Pet button handler
        $('#pos-add-pet-detail').on('click', function () {
            addPetRow();
        });

        $(document).on('click', '.pos-remove-pet-btn', function () {
            const row = $(this).closest('.pos-pet-row');
            const deleteFlag = row.find('.pos-pet-delete-flag');
            const petId = row.find('.pos-pet-id').val();
            if (petId) {
                deleteFlag.val('1');
                row.hide();
            } else {
                row.remove();
            }
        });

        // Edit Customer Button Handler (Opens Edit modal in POS terminal)
        $('#posEditCustomerBtn').on('click', function (e) {
            e.preventDefault();
            if (!state.customer_id) return;
            openCustomerEditModal(state.customer_id);
        });

        // Change customer button -> opens search and immediately focuses input cursor
        $('#posChangeCustomerBtn').on('click', function () {
            $('#posSelectedCustomerBox').hide();
            $('#posCustomerInvoicesSection').hide();
            $('#posCustomerSearchWrapper').show();
            if ($custSelect.length) {
                $custSelect.val(null).trigger('change.select2');
                $custSelect.select2('open');
                setTimeout(function () {
                    const searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.focus();
                    }
                }, 60);
            }
        });

        // View customer invoices button -> opens modal
        $('#posViewCustomerInvoicesBtn').on('click', function () {
            if (!state.customer_id) return;
            openCustomerInvoicesModal();
        });

        // Add / Edit Customer Form submission
        $('#posQuickCustomerForm').on('submit', function (e) {
            e.preventDefault();
            const custId = $('#posCustId').val();
            const isEdit = Boolean(custId);

            const formData = $(this).serialize();
            const url = isEdit 
                ? `${window.CUSTOMER_EDIT_BASE_URL || (window.APP_URL + '/master/customers')}/${custId}`
                : `${window.APP_URL || ''}/master/customers`;

            const $submitBtn = $('#posSaveCustomerBtn');
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            $('#posCustFormAlert').addClass('d-none');

            $.ajax({
                url: url,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.CSRF_TOKEN || $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                data: formData,
                success: function (resp) {
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Customer');
                    $('#posAddCustomerModal').modal('hide');

                    const customer = resp.customer || resp;
                    const newCustId = customer.id || custId;
                    const custName = customer.name || $('#posCust_name').val();
                    const custMobile = customer.mobile || $('#posCust_mobile').val();
                    const editUrl = `${window.CUSTOMER_EDIT_BASE_URL || ''}/${newCustId}/edit`;

                    // Update or append select2 option
                    const existingOpt = $('#posCustomerSelect').find(`option[value="${newCustId}"]`);
                    const optText = custMobile ? `${custName} (${custMobile})` : custName;
                    if (existingOpt.length) {
                        existingOpt.text(optText);
                    } else {
                        const newOption = new Option(optText, newCustId, true, true);
                        $('#posCustomerSelect').append(newOption);
                    }
                    $('#posCustomerSelect').val(newCustId).trigger('change.select2');

                    state.customer_id = newCustId;
                    updateSelectedCustomerUI({
                        id: newCustId,
                        name: custName,
                        mobile: custMobile,
                        edit_url: editUrl
                    });
                    loadCustomerLoyalty(newCustId);
                    loadCustomerInvoices(newCustId);
                    showNotification(`Customer "${custName}" ${isEdit ? 'updated' : 'created'} successfully!`, 'success');
                },
                error: function (xhr) {
                    $submitBtn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Customer');
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        let errorListHtml = '<ul class="mb-0 pl-3">';
                        let firstInvalidInput = null;
                        for (const [field, messages] of Object.entries(errors)) {
                            const fieldSelector = field.includes('.') 
                                ? `[name^="${field.split('.')[0]}"]` 
                                : `[name="${field}"]`;
                            
                            messages.forEach(msg => {
                                errorListHtml += `<li>${escapeHtml(msg)}</li>`;
                            });
                            if (!firstInvalidInput) {
                                firstInvalidInput = $(fieldSelector);
                            }
                        }
                        errorListHtml += '</ul>';
                        $('#posCustFormAlertText').html(errorListHtml);
                        $('#posCustFormAlert').removeClass('d-none');

                        // Switch to the tab containing the first invalid input
                        if (firstInvalidInput && firstInvalidInput.length) {
                            const pane = firstInvalidInput.closest('.tab-pane');
                            if (pane.length) {
                                const paneId = pane.attr('id');
                                $(`#posCustTabNav a[href="#${paneId}"]`).tab('show');
                            }
                            firstInvalidInput.focus();
                        }
                    } else {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) 
                            ? xhr.responseJSON.message 
                            : 'An error occurred while saving customer.';
                        $('#posCustFormAlertText').text(msg);
                        $('#posCustFormAlert').removeClass('d-none');
                    }
                }
            });
        });

        // Initialize with default customer if present
        if (window.INITIAL_CUSTOMER && window.INITIAL_CUSTOMER.id) {
            updateSelectedCustomerUI(window.INITIAL_CUSTOMER);
            loadCustomerLoyalty(window.INITIAL_CUSTOMER.id);
            loadCustomerInvoices(window.INITIAL_CUSTOMER.id);
        }
    }

    function updateSelectedCustomerUI(customer) {
        if (!customer) return;
        $('#posCustomerSearchWrapper').hide();
        $('#posSelectedCustomerBox').show();
        $('#posCustomerInvoicesSection').show();

        $('#posSelectedCustName').text(customer.name || 'Walk-in Customer');
        $('#posSelectedCustMobile span').text(customer.mobile || 'No mobile');
        $('#posEditCustomerBtn').attr('data-id', customer.id);

        // Update Pet Name(s) above total invoices
        const petSummary = customer.pets_summary || (customer.pets && customer.pets.length ? customer.pets.map(p => p.display || p.name).filter(Boolean).join(', ') : '');
        if (petSummary) {
            $('#posSelectedCustPetsText').text(petSummary);
            $('#posSelectedCustPets').show();
        } else {
            $('#posSelectedCustPets').hide();
        }
    }

    function openAddCustomerModalWithTerm(term) {
        const form = document.getElementById('posQuickCustomerForm');
        if (form) form.reset();

        $('#posCustId').val('');
        $('#posCustFormMethod').val('POST');
        $('#posCustomerModalTitle').html('<i class="fas fa-user-plus mr-2"></i> Add Customer');
        $('#posCustFormAlert').addClass('d-none');
        $('#pos-pet-rows').empty();
        posPetIndex = 0;

        // Set default values
        $('#posCust_title').val('Mr');
        $('#posCust_sales_type').val('Local');
        $('#posCust_payment_mode').val('Cash Only');
        $('#posCust_credit_limit').val('1000000');
        $('#posCust_credit_balance').val('0');
        $('#posCust_monthly_credit_balance').val('0');
        $('#posCust_credit_days').val('1000');
        $('#posCust_status').val('1');
        $('#posCust_gst_type').val('Un Register');
        $('#posCust_sms_consent').val('1');
        $('#posCust_country').val('India');
        $('#posCust_customer_type').val('RETAIL INVOICE');

        const cleanMob = (term || '').replace(/[^0-9]/g, '');
        if (cleanMob.length >= 5) {
            $('#posCust_mobile').val(cleanMob);
            $('#posCust_name').val('');
        } else {
            $('#posCust_name').val(term || '');
            $('#posCust_mobile').val('');
        }

        $('#pos-tab-general-link').tab('show');
        $('#posAddCustomerModal').modal('show');

        setTimeout(() => {
            if (cleanMob.length >= 5) {
                $('#posCust_name').focus();
            } else {
                $('#posCust_mobile').focus();
            }
        }, 350);
    }

    function openCustomerEditModal(custId) {
        if (!custId) return;
        const form = document.getElementById('posQuickCustomerForm');
        if (form) form.reset();

        $('#posCustId').val(custId);
        $('#posCustFormMethod').val('PUT');
        $('#posCustomerModalTitle').html('<i class="fas fa-user-edit mr-2"></i> Edit Customer');
        $('#posCustFormAlert').addClass('d-none');
        $('#pos-pet-rows').empty();
        posPetIndex = 0;

        $.ajax({
            url: `${window.CUSTOMER_EDIT_BASE_URL || (window.APP_URL + '/master/customers')}/${custId}/edit`,
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            success: function (resp) {
                const c = resp.customer || resp;
                $('#posCust_title').val(c.title || 'Mr');
                $('#posCust_name').val(c.name || '');
                $('#posCust_mobile').val(c.mobile || '');
                $('#posCust_customer_category_id').val(c.customer_category_id || c.category_id || '');
                $('#posCust_customer_code').val(c.customer_code || '');
                $('#posCust_sales_type').val(c.sales_type || 'Local');
                $('#posCust_payment_mode').val(c.payment_mode || 'Cash Only');
                $('#posCust_credit_limit').val(c.credit_limit !== undefined && c.credit_limit !== null ? c.credit_limit : 1000000);
                $('#posCust_credit_balance').val(c.credit_balance !== undefined && c.credit_balance !== null ? c.credit_balance : 0);
                $('#posCust_monthly_credit_balance').val(c.monthly_credit_balance !== undefined && c.monthly_credit_balance !== null ? c.monthly_credit_balance : 0);
                $('#posCust_credit_days').val(c.credit_days !== undefined && c.credit_days !== null ? c.credit_days : 1000);
                $('#posCust_branch_id').val(c.branch_id || '');
                $('#posCust_status').val(c.status ? '1' : '0');
                $('#posCust_sales_formula').val(c.sales_formula || '');
                $('#posCust_gst_type').val(c.gst_type || 'Un Register');
                $('#posCust_sms_consent').val(c.sms_consent ? '1' : '0');

                $('#posCust_address1').val(c.address1 || '');
                $('#posCust_area_id').val(c.area_id || '');
                $('#posCust_city').val(c.city || '');
                $('#posCust_state').val(c.state || '');
                $('#posCust_country').val(c.country || 'India');
                $('#posCust_postal_code').val(c.postal_code || '');
                $('#posCust_std_code').val(c.std_code || '');
                $('#posCust_phone').val(c.phone || '');
                $('#posCust_email').val(c.email || '');
                $('#posCust_remarks').val(c.remarks || '');
                $('#posCust_gst_no').val(c.gst_no || '');
                $('#posCust_aadhar_no').val(c.aadhar_no || '');
                $('#posCust_pan_no').val(c.pan_no || '');

                $('#posCust_gender').val(c.gender || '');
                $('#posCust_exempted_reason').val(c.exempted_reason || '');
                $('#posCust_customer_type').val(c.customer_type || 'RETAIL INVOICE');

                // Pets population
                const pets = resp.pets || c.pets || [];
                if (pets.length > 0) {
                    pets.forEach(pet => addPetRow(pet));
                }

                $('#pos-tab-general-link').tab('show');
                $('#posAddCustomerModal').modal('show');
            },
            error: function (err) {
                alert('Could not load customer details. Please try again.');
            }
        });
    }

    async function fetchCustomerDetailsAndInvoices(custId) {
        if (!custId) return;
        try {
            const resp = await fetch(`${window.CUSTOMER_INVOICES_URL || (window.APP_URL + '/sales/sales-bills/customer-invoices')}/${custId}`);
            const data = await resp.json();
            if (data) {
                updateSelectedCustomerUI({
                    id: custId,
                    name: data.customer_name,
                    mobile: data.customer_mobile,
                    edit_url: data.customer_edit_url || `${window.CUSTOMER_EDIT_BASE_URL || ''}/${custId}/edit`,
                    pets: data.pets || [],
                    pets_summary: data.pets_summary || ''
                });
                currentCustomerInvoices = data.invoices || [];
                filteredCustomerInvoices = [...currentCustomerInvoices];
                const count = currentCustomerInvoices.length;
                $('#posCustomerTotalInvoicesCount').text(`${count} ${count === 1 ? 'Invoice' : 'Invoices'}`);
                const petSub = data.pets_summary ? ` • 🐾 ${data.pets_summary}` : '';
                $('#cimSubtitle').text(`${data.customer_name || 'Customer'} / ${data.customer_mobile || 'No mobile'}${petSub}`);
            }
        } catch (e) {
            console.error('Failed to fetch customer details', e);
        }
    }

    async function loadCustomerInvoices(custId) {
        if (!custId) return;
        try {
            const resp = await fetch(`${window.CUSTOMER_INVOICES_URL || (window.APP_URL + '/sales/sales-bills/customer-invoices')}/${custId}`);
            const data = await resp.json();
            if (data) {
                currentCustomerInvoices = data.invoices || [];
                filteredCustomerInvoices = [...currentCustomerInvoices];
                const count = currentCustomerInvoices.length;
                $('#posCustomerTotalInvoicesCount').text(`${count} ${count === 1 ? 'Invoice' : 'Invoices'}`);
                const petSub = data.pets_summary ? ` • 🐾 ${data.pets_summary}` : '';
                $('#cimSubtitle').text(`${data.customer_name || 'Customer'} / ${data.customer_mobile || 'No mobile'}${petSub}`);
                if (data.pets_summary) {
                    $('#posSelectedCustPetsText').text(data.pets_summary);
                    $('#posSelectedCustPets').show();
                } else if (!data.pets || data.pets.length === 0) {
                    $('#posSelectedCustPets').hide();
                }
            }
        } catch (e) {
            console.error('Failed to load customer invoices', e);
        }
    }

    function openCustomerInvoicesModal() {
        $('#cimSearchInput').val('');
        filteredCustomerInvoices = [...currentCustomerInvoices];
        cimCurrentPage = 1;
        renderCustomerInvoicesTable();
        $('#posCustomerInvoicesModal').modal('show');
    }

    function renderCustomerInvoicesTable() {
        const total = filteredCustomerInvoices.length;
        $('#cimInvoiceCountLabel').text(`${total} invoice${total === 1 ? '' : 's'}`);

        if (total === 0) {
            $('#cimEmptyState').removeClass('d-none');
            $('#cimTableContainer').addClass('d-none');
            $('#cimPrevBtn').prop('disabled', true);
            $('#cimNextBtn').prop('disabled', true);
            return;
        }

        $('#cimEmptyState').addClass('d-none');
        $('#cimTableContainer').removeClass('d-none');

        const totalPages = Math.ceil(total / CIM_PAGE_SIZE) || 1;
        if (cimCurrentPage > totalPages) cimCurrentPage = totalPages;
        if (cimCurrentPage < 1) cimCurrentPage = 1;

        const start = (cimCurrentPage - 1) * CIM_PAGE_SIZE;
        const end = Math.min(start + CIM_PAGE_SIZE, total);
        const pageItems = filteredCustomerInvoices.slice(start, end);

        let rowsHtml = '';
        pageItems.forEach(inv => {
            rowsHtml += `
                <tr>
                    <td class="text-nowrap"><i class="far fa-calendar-alt text-muted mr-1"></i> ${inv.bill_date || ''}</td>
                    <td class="cim-invoice-num font-weight-bold text-dark">${inv.bill_number || ''}</td>
                    <td>${inv.items || 1}</td>
                    <td class="cim-invoice-total font-weight-bold text-dark">₹${inv.total || '0.00'}</td>
                    <td class="text-center text-nowrap">
                        <a href="${inv.view_url}" target="_blank" class="cim-action-btn" title="View Bill Receipt (Opens in new tab)">
                            <i class="far fa-eye"></i>
                        </a>
                        <a href="${inv.edit_url}" target="_blank" class="cim-action-btn" title="Edit Bill (Opens in new tab)">
                            <i class="far fa-edit"></i>
                        </a>
                        <a href="${inv.print_url}" target="_blank" class="cim-action-btn" title="Print Bill (Opens in new tab)">
                            <i class="fas fa-print"></i>
                        </a>
                    </td>
                </tr>
            `;
        });

        $('#cimTableBody').html(rowsHtml);
        $('#cimPrevBtn').prop('disabled', cimCurrentPage <= 1);
        $('#cimNextBtn').prop('disabled', cimCurrentPage >= totalPages);
    }

    // Modal Search Filter listener
    $(document).on('input', '#cimSearchInput', function () {
        const q = $.trim($(this).val()).toLowerCase();
        if (!q) {
            filteredCustomerInvoices = [...currentCustomerInvoices];
        } else {
            filteredCustomerInvoices = currentCustomerInvoices.filter(inv => {
                return (inv.bill_number && inv.bill_number.toLowerCase().includes(q)) ||
                       (inv.bill_date && inv.bill_date.toLowerCase().includes(q)) ||
                       (inv.total && inv.total.toString().toLowerCase().includes(q));
            });
        }
        cimCurrentPage = 1;
        renderCustomerInvoicesTable();
    });

    // Pagination buttons click
    $(document).on('click', '#cimPrevBtn', function () {
        if (cimCurrentPage > 1) {
            cimCurrentPage--;
            renderCustomerInvoicesTable();
        }
    });

    $(document).on('click', '#cimNextBtn', function () {
        const totalPages = Math.ceil(filteredCustomerInvoices.length / CIM_PAGE_SIZE) || 1;
        if (cimCurrentPage < totalPages) {
            cimCurrentPage++;
            renderCustomerInvoicesTable();
        }
    });

    // Customer Loyalty Load
    async function loadCustomerLoyalty(custId) {
        const badge = document.getElementById('posLoyaltyBadge');
        if (!badge || !custId) return;

        try {
            const resp = await fetch(`${window.APP_URL || ''}/sales/sales-bills/customer-loyalty/${custId}`);
            const data = await resp.json();
            if (data && data.points > 0) {
                badge.innerHTML = `<i class="fas fa-coins text-warning mr-1"></i> Loyalty: <strong>${data.points} pts</strong> (≈ ₹${data.value})`;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        } catch (e) {
            badge.style.display = 'none';
        }
    }

    // Hold & Recall Bills
    function holdCurrentBill() {
        if (state.cart.length === 0) {
            showNotification('Cart is empty. Nothing to park.', 'warning');
            return;
        }

        const bill = {
            id: Date.now(),
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            customer_id: state.customer_id,
            cart: JSON.parse(JSON.stringify(state.cart)),
            total: computeTotals().grandTotal
        };

        state.held_bills.push(bill);
        saveHeldBills();
        state.cart = [];
        renderCart();
        sound.beep(1200, 0.1);
        showNotification('Bill placed on Hold successfully', 'info');
        focusScanner();
    }

    function recallHeldBill() {
        if (state.held_bills.length === 0) {
            showNotification('No held bills in queue.', 'info');
            return;
        }

        const bill = state.held_bills.pop();
        saveHeldBills();
        state.cart = bill.cart;
        if (bill.customer_id) {
            state.customer_id = bill.customer_id;
            fetchCustomerDetailsAndInvoices(bill.customer_id);
            loadCustomerLoyalty(bill.customer_id);
        }
        renderCart();
        sound.beep(1600, 0.08);
        showNotification('Held bill recalled to cart', 'success');
        focusScanner();
    }

    function loadHeldBills() {
        try {
            const data = localStorage.getItem('urbanpos_held_bills');
            if (data) state.held_bills = JSON.parse(data);
            updateHeldBadge();
        } catch (e) {
            state.held_bills = [];
        }
    }

    function saveHeldBills() {
        try {
            localStorage.setItem('urbanpos_held_bills', JSON.stringify(state.held_bills));
            updateHeldBadge();
        } catch (e) {}
    }

    function updateHeldBadge() {
        const badge = document.getElementById('posHeldCountBadge');
        if (badge) {
            badge.textContent = state.held_bills.length;
            badge.style.display = state.held_bills.length > 0 ? 'inline-block' : 'none';
        }
    }

    // Submit Sale to Backend
    async function submitSale() {
        if (state.cart.length === 0) {
            showNotification('Please add at least one item to cart.', 'warning');
            return;
        }

        if (!state.customer_id) {
            showNotification('Please select a customer for this bill.', 'warning');
            return;
        }

        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing Bill...';

        const totals = computeTotals();

        // Build Payload matching backend expectations
        const payload = {
            _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN,
            posting_key: 'pos_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            bill_date: new Date().toISOString().slice(0, 19).replace('T', ' '),
            customer_id: state.customer_id,
            branch_id: state.branch_id,
            invoice_type: state.invoice_type,
            sales_type: state.sales_type,
            delivery_type: state.delivery_type,
            round_off: totals.roundOff,
            items: state.cart.map(item => ({
                item_id: item.id,
                qty: item.qty,
                sell_price: item.sell_price,
                mrp: item.mrp,
                disc_percent: item.disc_percent,
                disc_amount: item.disc_amount
            })),
            tenders: [
                {
                    tender_type_id: 1, // Default cash
                    tender_type_value_id: null,
                    amount: totals.grandTotal
                }
            ]
        };

        if (state.edit_id) {
            payload._method = 'PUT';
        }

        try {
            const url = state.edit_id ? `${window.APP_URL || ''}/sales/sales-bills/${state.edit_id}` : `${window.APP_URL || ''}/sales/sales-bills`;
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': payload._token
                },
                body: JSON.stringify(payload)
            });

            const result = await resp.json();

            if (resp.ok && (result.success || result.id)) {
                sound.success();
                const billId = result.id;
                showNotification(`Bill #${result.bill_number || ''} completed successfully!`, 'success');

                // Print Receipt automatically
                if (billId) {
                    window.open(`${window.APP_URL || ''}/sales/sales-bills/${billId}/receipt`, '_blank');
                }

                // Reset for next sale
                state.cart = [];
                state.cash_received = 0;
                
                if (state.edit_id) {
                    window.location.href = `${window.APP_URL || ''}/pos`;
                    return;
                }
                
                if (cashReceivedInput) cashReceivedInput.value = '';
                renderCart();
            } else {
                sound.error();
                const errMsg = result.message || (result.errors ? Object.values(result.errors).flat().join(', ') : 'Error saving bill');
                showNotification(errMsg, 'danger');
            }
        } catch (e) {
            sound.error();
            showNotification('Network / Server communication error', 'danger');
        } finally {
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Pay & Print (F12)';
            focusScanner();
        }
    }

    // Keyboard Shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // ALT+H: Hold bill
            if (e.altKey && e.code === 'KeyH') {
                e.preventDefault();
                holdCurrentBill();
            }
            // ALT+R: Recall bill
            else if (e.altKey && e.code === 'KeyR') {
                e.preventDefault();
                recallHeldBill();
            }
            // ALT+C: Cash
            else if (e.altKey && e.code === 'KeyC') {
                e.preventDefault();
                document.querySelector('[data-mode="Cash"]')?.click();
            }
            // ALT+U: UPI
            else if (e.altKey && e.code === 'KeyU') {
                e.preventDefault();
                document.querySelector('[data-mode="UPI"]')?.click();
            }
            // ALT+D: Card
            else if (e.altKey && e.code === 'KeyD') {
                e.preventDefault();
                document.querySelector('[data-mode="Card"]')?.click();
            }
            // F2: Open Item Search Modal
            else if (e.key === 'F2' || e.code === 'F2') {
                e.preventDefault();
                if (typeof window.openPosItemSearchModal === 'function') {
                    window.openPosItemSearchModal();
                }
            }
        });
    }

    function focusScanner() {
        if (scanInput) {
            scanInput.focus();
            scanInput.select();
        }
    }

    function showNotification(msg, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} shadow`;
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.zIndex = '99999';
        toast.style.minWidth = '300px';
        toast.style.textAlign = 'center';
        toast.style.fontWeight = 'bold';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // POS Item Search Modal (F2) Engine
    function initPosItemSearchModal() {
        const $modal = $('#pos-item-search-modal');
        if (!$modal.length) return;

        let debounceTimer = null;
        let itemCache = {};

        function openModal() {
            $('#pos-isl-filter-name').val('');
            $('#pos-isl-filter-code').val('');
            $('#pos-isl-filter-expiry').val('');
            $modal.modal('show');
            $modal.one('shown.bs.modal', function () {
                $('#pos-isl-filter-name').focus().select();
            });
            fetchItems();
        }

        window.openPosItemSearchModal = openModal;

        // Filter inputs with debouncing
        $('#pos-isl-filter-name, #pos-isl-filter-code, #pos-isl-filter-expiry').on('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(fetchItems, 300);
        });

        $('#pos-isl-btn-clear').on('click', function () {
            $('#pos-isl-filter-name, #pos-isl-filter-code, #pos-isl-filter-expiry').val('');
            fetchItems();
            $('#pos-isl-filter-name').focus();
        });

        // Keyboard navigation inside modal
        $modal.on('keydown', function (e) {
            let $rows = $('#pos-isl-items-body tr.isl-item-row:not(.isl-item-disabled)');
            if (!$rows.length) return;

            let $current = $rows.filter('.table-primary');
            let idx = $rows.index($current);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = (idx + 1) >= $rows.length ? 0 : idx + 1;
                $rows.removeClass('table-primary');
                let $target = $rows.eq(idx).addClass('table-primary');
                if ($target[0]) {
                    $target[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = (idx - 1) < 0 ? $rows.length - 1 : idx - 1;
                $rows.removeClass('table-primary');
                let $target = $rows.eq(idx).addClass('table-primary');
                if ($target[0]) {
                    $target[0].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                let $target = $current.length ? $current : $rows.first();
                if ($target.length) {
                    $target.trigger('click');
                }
            }
        });

        function fetchItems() {
            let branchId = state.branch_id || (document.getElementById('posBranchSelect')?.value) || 3;
            let srch = ($('#pos-isl-filter-name').val() || '').trim();
            let code = ($('#pos-isl-filter-code').val() || '').trim();
            let expiry = ($('#pos-isl-filter-expiry').val() || '').trim();

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;

            if (itemCache[cacheKey]) {
                renderItems(itemCache[cacheKey]);
                return;
            }

            $('#pos-isl-loading').removeClass('d-none');
            $('#pos-isl-no-results').addClass('d-none');
            $('#pos-isl-table-wrap').addClass('d-none');

            let url = window.ISL_URL || '/sales/sales-bills/item-list';
            let params = { branch_id: branchId, search: srch, code: code, expiry: expiry };

            $.getJSON(url, params, function (res) {
                $('#pos-isl-loading').addClass('d-none');
                let items = res.items || [];
                itemCache[cacheKey] = items;
                setTimeout(() => { delete itemCache[cacheKey]; }, 60000);
                renderItems(items);
            }).fail(function () {
                $('#pos-isl-loading').addClass('d-none');
                $('#pos-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-exclamation-triangle fa-2x text-warning"></i>' +
                    '<p class="mt-2 text-muted">Error loading items from server.</p>'
                );
            });
        }

        function renderItems(items) {
            let $tbody = $('#pos-isl-items-body');
            $tbody.empty();

            if (!items || items.length === 0) {
                $('#pos-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found matching criteria.</p>'
                );
                $('#pos-isl-table-wrap').addClass('d-none');
                $('#pos-isl-count-label').text('');
                return;
            }

            $('#pos-isl-no-results').addClass('d-none');
            $('#pos-isl-table-wrap').removeClass('d-none');
            $('#pos-isl-count-label').text(`Showing ${items.length} item(s)`);

            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = it.exp_date
                    ? `<span class="badge badge-danger px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${escapeHtml(it.code)}</span>`
                    : `<span class="text-muted">—</span>`;
                let isAllowNeg = !!(it.allow_negative_stock);
                let stockNum = parseFloat(it.qty || 0);
                let isOutOfStock = stockNum <= 0 && !isAllowNeg;
                let qtyBadge = isOutOfStock
                    ? `<span class="badge badge-danger px-2 py-1">0 (Out)</span>`
                    : (stockNum <= 0 && isAllowNeg
                        ? `<span class="badge badge-warning px-2 py-1">${stockNum} (Allow Neg)</span>`
                        : `<span class="badge badge-success px-2 py-1 font-weight-bold">${stockNum}</span>`);

                let rowClass = isOutOfStock ? 'isl-item-row isl-item-disabled text-muted bg-light' : 'isl-item-row';
                let rowStyle = isOutOfStock ? 'cursor: not-allowed; opacity: 0.6;' : 'cursor: pointer;';
                let actionBtn = isOutOfStock
                    ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock">
                        <i class="fas fa-ban mr-1"></i>Out
                       </button>`
                    : `<button type="button" class="btn btn-success btn-xs px-2 isl-btn-select font-weight-bold">
                        <i class="fas fa-cart-plus mr-1"></i>Add
                       </button>`;

                html += `
                    <tr class="${rowClass} ${idx === 0 && !isOutOfStock ? 'table-primary' : ''}" style="${rowStyle}"
                        data-item='${JSON.stringify(it).replace(/'/g, "&#39;")}'>
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">
                            ${escapeHtml(it.name)}
                            ${isOutOfStock ? '<span class="badge badge-secondary ml-1 small">Out of Stock</span>' : ''}
                        </td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-right">${qtyBadge}</td>
                        <td class="align-middle text-right font-weight-bold text-primary">₹${parseFloat(it.sell_price || 0).toFixed(2)}</td>
                        <td class="align-middle text-right text-muted">₹${parseFloat(it.mrp || 0).toFixed(2)}</td>
                        <td class="align-middle text-center">${actionBtn}</td>
                    </tr>
                `;
            });

            $tbody.html(html);

            // Click row or Add button to select item
            $tbody.find('.isl-item-row:not(.isl-item-disabled)').on('click', function (e) {
                let rawItem = $(this).attr('data-item');
                if (rawItem) {
                    let it = JSON.parse(rawItem);
                    addItemToCart(it);
                    sound.success();
                    showNotification(`Added "${it.name}" to cart`, 'success');
                    $modal.modal('hide');
                    setTimeout(focusScanner, 200);
                }
            });
        }
    }

    // ==========================================
    // POS QUICK LOCK SCREEN & INACTIVITY ENGINE
    // ==========================================
    let isPosLocked = false;
    let enteredPosPin = '';
    let posInactivityTimeout = null;
    const POS_INACTIVITY_LIMIT = 3 * 60 * 1000; // 3 minutes

    function resetPosInactivityTimer() {
        if (isPosLocked) return;
        if (posInactivityTimeout) clearTimeout(posInactivityTimeout);
        posInactivityTimeout = setTimeout(() => {
            lockPosScreen();
        }, POS_INACTIVITY_LIMIT);
    }

    ['mousemove', 'keydown', 'click', 'touchstart'].forEach(evt => {
        window.addEventListener(evt, resetPosInactivityTimer, { passive: true });
    });
    resetPosInactivityTimer();

    function lockPosScreen() {
        isPosLocked = true;
        enteredPosPin = '';
        updatePinDots();
        $('#posPinError').hide().text('');
        $('#posLockOverlay').css('display', 'flex');
        sound.pop();
    }

    function unlockPosScreen() {
        if (enteredPosPin.length !== 4) return;

        const $err = $('#posPinError');
        $err.hide().text('');

        $.ajax({
            url: window.POS_LOCK_VERIFY_URL || (window.APP_URL + '/pos/verify-pin'),
            method: 'POST',
            data: {
                pin: enteredPosPin,
                _token: window.CSRF_TOKEN
            },
            success: function (resp) {
                if (resp && resp.success) {
                    isPosLocked = false;
                    enteredPosPin = '';
                    updatePinDots();
                    $('#posLockOverlay').fadeOut(200);
                    sound.success();
                    showNotification('Terminal unlocked successfully!', 'success');
                    resetPosInactivityTimer();
                    setTimeout(focusScanner, 300);
                } else {
                    handlePinError(resp.message || 'Invalid PIN.');
                }
            },
            error: function (xhr) {
                let msg = 'Invalid PIN.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                handlePinError(msg);
            }
        });
    }

    function handlePinError(msg) {
        sound.error();
        enteredPosPin = '';
        updatePinDots();
        $('#posPinError').text(msg).show();
        const $card = $('#posLockOverlay .card');
        $card.addClass('animate__animated animate__shakeX');
        setTimeout(() => $card.removeClass('animate__animated animate__shakeX'), 600);
    }

    function updatePinDots() {
        const dots = document.querySelectorAll('#posPinDotsContainer .pos-pin-dot');
        dots.forEach((dot, idx) => {
            if (idx < enteredPosPin.length) {
                dot.style.background = '#3b82f6';
                dot.style.borderColor = '#2563eb';
            } else {
                dot.style.background = 'transparent';
                dot.style.borderColor = '#94a3b8';
            }
        });
    }

    function handlePinInputDigit(digit) {
        if (!isPosLocked) return;
        if (enteredPosPin.length < 4) {
            enteredPosPin += digit;
            updatePinDots();
            if (enteredPosPin.length === 4) {
                setTimeout(unlockPosScreen, 100);
            }
        }
    }

    function handlePinBackspace() {
        if (!isPosLocked) return;
        if (enteredPosPin.length > 0) {
            enteredPosPin = enteredPosPin.slice(0, -1);
            updatePinDots();
        }
    }

    function handlePinClear() {
        if (!isPosLocked) return;
        enteredPosPin = '';
        updatePinDots();
        $('#posPinError').hide();
    }

    $(document).on('click', '.pos-pin-btn', function () {
        const d = $(this).attr('data-digit');
        if (d !== undefined) handlePinInputDigit(d);
    });
    $('#posPinClearBtn').on('click', handlePinClear);
    $('#posPinBackspaceBtn').on('click', handlePinBackspace);

    window.addEventListener('keydown', function (e) {
        if (e.ctrlKey && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            lockPosScreen();
            return;
        }

        if (isPosLocked) {
            e.stopPropagation();
            if (e.key >= '0' && e.key <= '9') {
                e.preventDefault();
                handlePinInputDigit(e.key);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                handlePinBackspace();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                handlePinClear();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (enteredPosPin.length === 4) unlockPosScreen();
            }
        }
    }, true);

    // Expose methods to window for inline onclick handlers
    window.lockPosScreen = lockPosScreen;
    window.unlockPosScreen = unlockPosScreen;
    window.posAddItemToCart = addItemToCart;
    window.posEngine = {
        updateQty: updateItemQty,
        setQty: setItemQty,
        removeRow: removeItem,
        openAddCustomer: openAddCustomerModalWithTerm,
        triggerCreateCustomer: function (term) {
            const t = term !== undefined ? term : (lastCustomerSearchTerm || $('.select2-search__field').val() || '');
            const $custSelect = $('#posCustomerSelect');
            if ($custSelect.length) {
                $custSelect.select2('close');
            }
            openAddCustomerModalWithTerm(t);
        }
    };

})();
