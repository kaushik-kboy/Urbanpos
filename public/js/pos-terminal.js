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
        split_payments: {
            cash: 0,
            card: 0,
            wallet: 0,
            credit: 0,
            wallet_type: 'GPAY'
        },
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
                code: i.item ? (i.item.item_code || i.item.ean_upc_code || '') : '',
                exp_date: i.exp_date ? (typeof i.exp_date === 'string' ? i.exp_date.substring(0, 10) : '') : '',
                sell_price: parseFloat(i.sell_price) || 0,
                mrp: parseFloat(i.mrp) || 0,
                qty: parseFloat(i.qty) || 1,
                gst_percent: i.item && i.item.gst_tax ? parseFloat(i.item.gst_tax.percentage || i.item.gst_tax.igst || 0) : 0,
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

        // Tab & Enter on scanInput opens item modal or scans (Task 5)
        scanInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = scanInput.value.trim();
                if (code) {
                    processBarcodeScan(code);
                } else {
                    if (typeof window.openPosItemSearchModal === 'function') {
                        window.openPosItemSearchModal();
                    }
                }
            } else if (e.key === 'Tab' && !e.shiftKey) {
                e.preventDefault();
                const code = scanInput.value.trim();
                if (typeof window.openPosItemSearchModal === 'function') {
                    window.openPosItemSearchModal(code);
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

        // Tender modes
        document.querySelectorAll('.pos-tender-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.pos-tender-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.tender_mode = btn.dataset.mode;
                toggleTenderControls();
            });
        });

        // 4 Save Action Buttons (Task 5: Save, Save & WhatsApp, Save & Print, Cancel)
        if (payBtn) {
            payBtn.addEventListener('click', () => submitSale('print'));
        }
        document.getElementById('posBtnSaveOnly')?.addEventListener('click', () => submitSale('save'));
        document.getElementById('posBtnSaveWhatsApp')?.addEventListener('click', () => submitSale('whatsapp'));
        document.getElementById('posBtnCancelTender')?.addEventListener('click', function () {
            state.tender_mode = 'Cash';
            state.cash_received = 0;
            state.split_payments = { cash: 0, card: 0, wallet: 0, credit: 0, wallet_type: 'GPAY' };
            $('#posSplitRowCash, #posSplitRowCard, #posSplitRowWallet, #posSplitRowCredit').hide();
            $('#posSplitDispTotal').text('₹ 0.00');
            $('#posSplitSection').hide();
            document.querySelectorAll('.pos-tender-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.mode === 'Cash');
            });
            toggleTenderControls();
            if (cashReceivedInput) cashReceivedInput.value = '';
            showNotification('Tender selection reset to default Cash', 'info');
        });

        // Action bar buttons
        document.getElementById('posHoldBtn')?.addEventListener('click', holdCurrentBill);
        document.getElementById('posRecallBtn')?.addEventListener('click', recallHeldBill);
        document.getElementById('posClearBtn')?.addEventListener('click', clearCart);

        // Split modal handlers
        $(document).on('input', '.split-input', function () {
            $('#posSplitAlert').addClass('d-none');
            recalculateSplitRemaining();
        });

        $(document).on('click', '.btn-split-fill', function () {
            const targetSelector = $(this).data('target');
            const $target = $(targetSelector);
            if (!$target.length) return;

            const totals = computeTotals();
            const billTotal = totals.grandTotal;

            let otherSum = 0;
            $('.split-input').each(function () {
                if (this !== $target[0]) {
                    otherSum += (parseFloat($(this).val()) || 0);
                }
            });

            const maxForThis = Math.max(0, Math.round((billTotal - otherSum) * 100) / 100);
            $target.val(maxForThis > 0 ? maxForThis.toFixed(2) : '0.00');
            recalculateSplitRemaining();
            $target.focus().select();
        });

        $('#posEditSplitBtn').on('click', function () {
            openSplitPaymentModal();
        });

        $('#posSplitConfirmBtn').on('click', function () {
            const res = recalculateSplitRemaining();
            if (res.tendered <= 0) {
                $('#posSplitAlert').removeClass('d-none');
                $('#posSplitAlertText').text('Please enter at least one payment amount.');
                return;
            }

            if (res.remaining > 0) {
                if (res.credit === 0) {
                    $('#posSplitCreditInput').val(res.remaining.toFixed(2));
                    const updated = recalculateSplitRemaining();
                    if (updated.remaining === 0) {
                        showNotification('Remaining balance assigned to Customer Credit / Due.', 'info');
                    }
                } else {
                    $('#posSplitAlert').removeClass('d-none');
                    $('#posSplitAlertText').text('Total payment (₹' + res.tendered.toFixed(2) + ') does not equal bill total (₹' + res.billTotal.toFixed(2) + '). Please balance remaining ₹' + res.remaining.toFixed(2));
                    return;
                }
            }

            if (res.remaining < 0) {
                $('#posSplitAlert').removeClass('d-none');
                $('#posSplitAlertText').text('Total payments (₹' + res.tendered.toFixed(2) + ') cannot exceed bill total (₹' + res.billTotal.toFixed(2) + ').');
                return;
            }

            // Save to state
            state.split_payments = {
                cash: parseFloat($('#posSplitCashInput').val()) || 0,
                card: parseFloat($('#posSplitCardInput').val()) || 0,
                wallet: parseFloat($('#posSplitWalletInput').val()) || 0,
                credit: parseFloat($('#posSplitCreditInput').val()) || 0,
                wallet_type: $('#posSplitWalletType').val() || 'GPAY'
            };

            updateSplitPreviewUI();
            $('#posSplitModal').modal('hide');
            showNotification('Split payment breakdown applied!', 'success');
            sound.beep(1760, 0.08);

            if (payBtn) payBtn.focus();
        });

        $('#posSplitModal').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#posSplitConfirmBtn').trigger('click');
            }
        });

        // Delegated Cart Row Inputs (Real-time calculation without DOM re-render)
        $(document).on('input change', '.pos-qty-input', function () {
            const idx = parseInt($(this).data('idx'));
            if (isNaN(idx) || !state.cart[idx]) return;
            const val = parseFloat($(this).val());
            const payBtns = $('#posPayBtn, #posBtnSaveOnly, #posBtnSaveWhatsApp');

            if (isNaN(val) || val <= 0) {
                $(this).addClass('is-invalid border-danger text-danger').attr('title', 'Quantity must be greater than 0');
                payBtns.prop('disabled', true);
                return;
            } else {
                $(this).removeClass('is-invalid border-danger text-danger').attr('title', '');
                let allValid = true;
                $('.pos-qty-input').each(function () {
                    let q = parseFloat($(this).val());
                    if (isNaN(q) || q <= 0) allValid = false;
                });
                if (allValid && state.cart.length > 0) {
                    payBtns.prop('disabled', false);
                }
            }

            state.cart[idx].qty = val;
            recalculateRow(idx, 'qty');
        });

        $(document).on('input change', '.pos-disc-percent', function () {
            const idx = parseInt($(this).data('idx'));
            if (isNaN(idx) || !state.cart[idx]) return;
            const val = parseFloat($(this).val()) || 0;
            state.cart[idx].disc_percent = val;
            recalculateRow(idx, 'percent');
        });

        $(document).on('input change', '.pos-disc-amount', function () {
            const idx = parseInt($(this).data('idx'));
            if (isNaN(idx) || !state.cart[idx]) return;
            const val = parseFloat($(this).val()) || 0;
            state.cart[idx].disc_amount = val;
            recalculateRow(idx, 'amount');
        });

        // Fast POS keyboard flow (Task 5: Qty -> Dis % -> Dis Amt -> Scanner)
        $(document).on('keydown', '.pos-qty-input', function (e) {
            const idx = $(this).data('idx');
            if (e.key === 'Enter') {
                e.preventDefault();
                const discPct = cartTableBody ? cartTableBody.querySelector(`.pos-disc-percent[data-idx="${idx}"]`) : null;
                if (discPct) {
                    discPct.focus();
                    discPct.select();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                focusScanner();
            }
        });

        $(document).on('keydown', '.pos-disc-percent', function (e) {
            const idx = $(this).data('idx');
            if (e.key === 'Enter') {
                e.preventDefault();
                const discAmt = cartTableBody ? cartTableBody.querySelector(`.pos-disc-amount[data-idx="${idx}"]`) : null;
                if (discAmt) {
                    discAmt.focus();
                    discAmt.select();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                focusScanner();
            }
        });

        $(document).on('keydown', '.pos-disc-amount', function (e) {
            if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
                e.preventDefault();
                focusScanner();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                focusScanner();
            }
        });
    }

    function toggleTenderControls() {
        const cashSection = document.getElementById('posCashSection');
        const upiSection = document.getElementById('posUpiSection');
        const splitSection = document.getElementById('posSplitSection');
        if (!cashSection || !upiSection) return;

        if (state.tender_mode === 'Cash') {
            cashSection.style.display = 'block';
            upiSection.style.display = 'none';
            if (splitSection) splitSection.style.display = 'none';
        } else if (state.tender_mode === 'UPI') {
            cashSection.style.display = 'none';
            upiSection.style.display = 'block';
            if (splitSection) splitSection.style.display = 'none';
            generateUpiQrCode();
        } else if (state.tender_mode === 'Split') {
            cashSection.style.display = 'none';
            upiSection.style.display = 'none';
            if (splitSection) splitSection.style.display = 'block';
            openSplitPaymentModal();
        } else {
            cashSection.style.display = 'none';
            upiSection.style.display = 'none';
            if (splitSection) splitSection.style.display = 'none';
        }
    }

    function openSplitPaymentModal() {
        if (state.cart.length === 0) {
            showNotification('Please add items to cart before configuring Split Payment.', 'warning');
            document.querySelector('[data-mode="Cash"]')?.click();
            return;
        }

        const totals = computeTotals();
        const billTotal = totals.grandTotal;

        $('#posSplitBillTotal').text('₹ ' + billTotal.toFixed(2));
        $('#posSplitAlert').addClass('d-none');

        const sp = state.split_payments || {};
        const cashVal = sp.cash > 0 ? sp.cash : '';
        const cardVal = sp.card > 0 ? sp.card : '';
        const walletVal = sp.wallet > 0 ? sp.wallet : '';
        const creditVal = sp.credit > 0 ? sp.credit : '';

        $('#posSplitCashInput').val(cashVal);
        $('#posSplitCardInput').val(cardVal);
        $('#posSplitWalletInput').val(walletVal);
        $('#posSplitCreditInput').val(creditVal);
        if (sp.wallet_type) {
            $('#posSplitWalletType').val(sp.wallet_type);
        }

        recalculateSplitRemaining();

        $('#posSplitModal').modal('show');
        setTimeout(() => {
            $('#posSplitCashInput').focus().select();
        }, 300);
    }

    function recalculateSplitRemaining() {
        const totals = computeTotals();
        const billTotal = totals.grandTotal;

        const cash = parseFloat($('#posSplitCashInput').val()) || 0;
        const card = parseFloat($('#posSplitCardInput').val()) || 0;
        const wallet = parseFloat($('#posSplitWalletInput').val()) || 0;
        const credit = parseFloat($('#posSplitCreditInput').val()) || 0;

        const tendered = Math.round((cash + card + wallet + credit) * 100) / 100;
        const remaining = Math.round((billTotal - tendered) * 100) / 100;

        const $remEl = $('#posSplitRemaining');
        if (remaining === 0) {
            $remEl.removeClass('text-danger text-warning').addClass('text-success').text('₹ 0.00 (Balanced)');
        } else if (remaining > 0) {
            $remEl.removeClass('text-success text-warning').addClass('text-danger').text('₹ ' + remaining.toFixed(2) + ' Due');
        } else {
            $remEl.removeClass('text-success text-danger').addClass('text-warning').text('₹ ' + Math.abs(remaining).toFixed(2) + ' Excess');
        }

        return { billTotal, tendered, remaining, cash, card, wallet, credit };
    }

    function updateSplitPreviewUI() {
        const sp = state.split_payments || {};
        const cash = parseFloat(sp.cash) || 0;
        const card = parseFloat(sp.card) || 0;
        const wallet = parseFloat(sp.wallet) || 0;
        const credit = parseFloat(sp.credit) || 0;
        const walletType = sp.wallet_type || 'UPI';

        const total = Math.round((cash + card + wallet + credit) * 100) / 100;

        if (cash > 0) {
            $('#posSplitRowCash').show();
            $('#posSplitDispCash').text('₹ ' + cash.toFixed(2));
        } else {
            $('#posSplitRowCash').hide();
        }

        if (card > 0) {
            $('#posSplitRowCard').show();
            $('#posSplitDispCard').text('₹ ' + card.toFixed(2));
        } else {
            $('#posSplitRowCard').hide();
        }

        if (wallet > 0) {
            $('#posSplitRowWallet').show();
            $('#posSplitDispWalletType').text(walletType);
            $('#posSplitDispWallet').text('₹ ' + wallet.toFixed(2));
        } else {
            $('#posSplitRowWallet').hide();
        }

        if (credit > 0) {
            $('#posSplitRowCredit').show();
            $('#posSplitDispCredit').text('₹ ' + credit.toFixed(2));
        } else {
            $('#posSplitRowCredit').hide();
        }

        $('#posSplitDispTotal').text('₹ ' + total.toFixed(2));
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
            const url = `${window.APP_URL || ''}/sales/sales-bills/lookup-item?q=${encodeURIComponent(code)}&query=${encodeURIComponent(code)}&branch_id=${state.branch_id || ''}&limit=1`;
            const resp = await fetch(url);
            const data = await resp.json();

            let itemToAdd = null;
            if (data && data.found && data.item) {
                itemToAdd = data.item;
                if (data.batches && data.batches.length > 0) {
                    itemToAdd.exp_date = data.batches[0].exp_date;
                    if (data.batches[0].sell_price > 0) itemToAdd.sell_price = data.batches[0].sell_price;
                    if (data.batches[0].mrp > 0) itemToAdd.mrp = data.batches[0].mrp;
                } else if (data.item.exp_date) {
                    itemToAdd.exp_date = data.item.exp_date;
                }
            } else if (Array.isArray(data) && data.length > 0) {
                itemToAdd = data[0];
            } else if (data && data.items && data.items.length > 0) {
                itemToAdd = data.items[0];
            }

            if (itemToAdd) {
                addItemToCart(itemToAdd);
                sound.beep(2100, 0.07);
                scanInput.value = '';
                hideSearchResults();
            } else {
                sound.error();
                showNotification('Item not found for barcode / code: ' + code, 'warning');
                if (typeof window.openPosItemSearchModal === 'function') {
                    window.openPosItemSearchModal(code);
                }
            }
        } catch (e) {
            sound.error();
            showNotification('Error querying barcode', 'danger');
        }
    }

    async function searchItems(query) {
        try {
            const url = `${window.APP_URL || ''}/sales/sales-bills/item-list?search=${encodeURIComponent(query)}&branch_id=${state.branch_id || ''}`;
            const resp = await fetch(url);
            const data = await resp.json();
            const items = data.items || (Array.isArray(data) ? data : []);
            renderSearchResults(items);
        } catch (e) {
            hideSearchResults();
        }
    }

    function renderSearchResults(items) {
        if (!items || items.length === 0) {
            searchResults.innerHTML = '<div class="p-3 text-muted text-center small">No matching items found (Press Enter or Tab to search item catalog)</div>';
            searchResults.style.display = 'block';
            return;
        }

        let html = '';
        items.slice(0, 10).forEach(item => {
            const price = parseFloat(item.sell_price || item.mrp || 0).toFixed(2);
            const stock = parseFloat(item.qty || 0);
            const stockBadge = stock > 0 ? `<span class="badge badge-success">${stock} in stock</span>` : `<span class="badge badge-danger">0 in stock</span>`;
            const expBadge = item.exp_date ? `<span class="badge badge-info ml-1"><i class="far fa-calendar-alt mr-1"></i>${item.exp_date}</span>` : '';
            html += `
                <div class="pos-search-item" data-item='${JSON.stringify(item).replace(/'/g, "&#39;")}'>
                    <div>
                        <strong class="text-dark">${escapeHtml(item.name)}</strong>
                        <div class="small text-muted">ID: <code>${item.id}</code> &bull; Code: <code>${escapeHtml(item.item_code || item.code || '-')}</code> &bull; ${stockBadge} ${expBadge}</div>
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
            });
        });
    }

    function hideSearchResults() {
        if (searchResults) searchResults.style.display = 'none';
    }

    // Cart Management
    function addItemToCart(item) {
        const itemId = parseInt(item.id || item.item_id);
        const expDate = item.exp_date ? (typeof item.exp_date === 'string' ? item.exp_date.substring(0, 10) : '') : '';

        // Prevent selling expired items (Task 2)
        if (expDate) {
            const todayStr = new Date().toISOString().substring(0, 10);
            if (expDate < todayStr) {
                alert(`Cannot add expired item: "${item.name || item.productname || 'Item'}" (Expired on ${expDate}). Selling expired products is strictly prohibited.`);
                if (typeof sound !== 'undefined' && sound.error) sound.error();
                return;
            }
        }

        const existingIdx = state.cart.findIndex(c => c.id === itemId && (c.exp_date || '') === expDate);
        let targetIdx = -1;

        if (existingIdx !== -1) {
            state.cart[existingIdx].qty += 1;
            recalculateRow(existingIdx, 'qty');
            targetIdx = existingIdx;
        } else {
            const newItem = {
                id: itemId,
                name: item.name || item.productname || 'Unknown Item',
                code: item.item_code || item.code || '',
                exp_date: expDate,
                sell_price: parseFloat(item.sell_price || item.mrp || 0),
                mrp: parseFloat(item.mrp || item.sell_price || 0),
                qty: 1,
                gst_percent: parseFloat(item.gst_percent || 0),
                disc_percent: parseFloat(item.disc_percent || 0),
                disc_amount: parseFloat(item.disc_amount || 0)
            };
            state.cart.push(newItem);
            targetIdx = state.cart.length - 1;
            renderCart();
        }

        // Auto-focus Qty field of the added/selected item and select text (Task 5)
        setTimeout(() => {
            if (cartTableBody) {
                const qtyInput = cartTableBody.querySelector(`input.pos-qty-input[data-idx="${targetIdx}"]`);
                if (qtyInput) {
                    qtyInput.focus();
                    qtyInput.select();
                }
            }
        }, 70);
    }

    function recalculateRow(idx, source) {
        const item = state.cart[idx];
        if (!item) return;

        const base = item.qty * item.sell_price;

        if (source === 'percent') {
            if (base > 0 && item.disc_percent > 0) {
                item.disc_amount = Math.round((base * item.disc_percent / 100) * 100) / 100;
            } else {
                item.disc_amount = 0;
            }
            const amtInput = cartTableBody ? cartTableBody.querySelector(`.pos-disc-amount[data-idx="${idx}"]`) : null;
            if (amtInput) amtInput.value = item.disc_amount > 0 ? item.disc_amount.toFixed(2) : '';
        } else if (source === 'amount') {
            if (base > 0 && item.disc_amount > 0) {
                item.disc_percent = Math.round(((item.disc_amount / base) * 100) * 100) / 100;
            } else {
                item.disc_percent = 0;
            }
            const pctInput = cartTableBody ? cartTableBody.querySelector(`.pos-disc-percent[data-idx="${idx}"]`) : null;
            if (pctInput) pctInput.value = item.disc_percent > 0 ? item.disc_percent : '';
        } else { // qty changed
            if (item.disc_percent > 0 && base > 0) {
                item.disc_amount = Math.round((base * item.disc_percent / 100) * 100) / 100;
                const amtInput = cartTableBody ? cartTableBody.querySelector(`.pos-disc-amount[data-idx="${idx}"]`) : null;
                if (amtInput) amtInput.value = item.disc_amount > 0 ? item.disc_amount.toFixed(2) : '';
            } else if (item.disc_amount > 0 && base > 0) {
                item.disc_percent = Math.round(((item.disc_amount / base) * 100) * 100) / 100;
                const pctInput = cartTableBody ? cartTableBody.querySelector(`.pos-disc-percent[data-idx="${idx}"]`) : null;
                if (pctInput) pctInput.value = item.disc_percent > 0 ? item.disc_percent : '';
            }
        }

        const net = Math.max(0, base - (item.disc_amount || 0));
        const netEl = cartTableBody ? cartTableBody.querySelector(`.pos-row-net[data-idx="${idx}"]`) : null;
        if (netEl) netEl.textContent = '₹ ' + net.toFixed(2);

        updateSummaryUI(computeTotals());
    }

    function updateItemQty(index, delta) {
        if (!state.cart[index]) return;
        state.cart[index].qty += delta;
        if (state.cart[index].qty <= 0) {
            state.cart.splice(index, 1);
            renderCart();
        } else {
            recalculateRow(index, 'qty');
        }
    }

    function setItemQty(index, val) {
        if (!state.cart[index]) return;
        const q = parseFloat(val);
        if (isNaN(q) || q <= 0) {
            state.cart.splice(index, 1);
            renderCart();
        } else {
            state.cart[index].qty = q;
            recalculateRow(index, 'qty');
        }
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
        let totalDiscount = state.bill_discount || 0;

        state.cart.forEach(item => {
            const base = item.qty * item.sell_price;
            const lineDisc = item.disc_amount || 0;
            const lineNet = Math.max(0, base - lineDisc);

            subtotal += base;
            totalDiscount += lineDisc;
            totalItems += item.qty;

            // Included GST calculation
            if (item.gst_percent > 0) {
                const taxPortion = lineNet - (lineNet / (1 + (item.gst_percent / 100)));
                totalTax += taxPortion;
            }
        });

        const discountedSubtotal = Math.max(0, subtotal - totalDiscount);
        const roundedGrandTotal = Math.round(discountedSubtotal);
        const roundOff = roundedGrandTotal - discountedSubtotal;

        return {
            subtotal,
            discount: totalDiscount,
            tax: totalTax,
            roundOff: roundOff,
            grandTotal: roundedGrandTotal,
            totalItems: totalItems
        };
    }

    function renderCart() {
        if (!cartTableBody) return;

        const btnSaveOnly = document.getElementById('posBtnSaveOnly');
        const btnSaveWhatsApp = document.getElementById('posBtnSaveWhatsApp');

        if (state.cart.length === 0) {
            cartTableBody.innerHTML = '';
            if (emptyCartNotice) emptyCartNotice.style.display = 'flex';
            if (payBtn) payBtn.disabled = true;
            if (btnSaveOnly) btnSaveOnly.disabled = true;
            if (btnSaveWhatsApp) btnSaveWhatsApp.disabled = true;
            updateSummaryUI(computeTotals());
            return;
        }

        if (emptyCartNotice) emptyCartNotice.style.display = 'none';
        if (payBtn) payBtn.disabled = false;
        if (btnSaveOnly) btnSaveOnly.disabled = false;
        if (btnSaveWhatsApp) btnSaveWhatsApp.disabled = false;

        let html = '';
        state.cart.forEach((item, idx) => {
            const base = item.qty * item.sell_price;
            const net = Math.max(0, base - (item.disc_amount || 0));
            const expDisplay = item.exp_date ? item.exp_date : '—';
            const discPctVal = item.disc_percent > 0 ? item.disc_percent : '';
            const discAmtVal = item.disc_amount > 0 ? parseFloat(item.disc_amount).toFixed(2) : '';

            html += `
                <tr>
                    <td class="text-center font-weight-bold text-muted" style="width: 35px;">${idx + 1}</td>
                    <td class="text-center font-weight-bold" style="width: 65px;">
                        <span class="badge badge-light border font-monospace text-dark">${item.id}</span>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark text-truncate" style="max-width: 280px;" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                    </td>
                    <td class="text-center small ${item.exp_date ? 'font-weight-bold text-dark' : 'text-muted'}" style="width: 100px;">
                        ${expDisplay}
                    </td>
                    <td class="text-center" style="width: 75px;">
                        <input type="number" step="any" min="1" class="pos-row-input pos-qty-input font-weight-bold text-center" value="${item.qty}" data-idx="${idx}" data-field="qty">
                    </td>
                    <td class="text-right font-weight-bold text-dark" style="width: 80px;">
                        ₹ ${item.sell_price.toFixed(2)}
                    </td>
                    <td class="text-right text-muted small" style="width: 80px;">
                        ₹ ${item.mrp.toFixed(2)}
                    </td>
                    <td class="text-center" style="width: 75px;">
                        <input type="number" step="any" min="0" max="100" class="pos-row-input pos-disc-percent text-right" placeholder="0" value="${discPctVal}" data-idx="${idx}" data-field="disc_percent">
                    </td>
                    <td class="text-center" style="width: 80px;">
                        <input type="number" step="0.01" min="0" class="pos-row-input pos-disc-amount text-right" placeholder="0.00" value="${discAmtVal}" data-idx="${idx}" data-field="disc_amount">
                    </td>
                    <td class="text-right font-weight-bold text-success pos-row-net" data-idx="${idx}" style="width: 95px; font-size: 0.95rem;">
                        ₹ ${net.toFixed(2)}
                    </td>
                    <td class="text-center" style="width: 35px;">
                        <button type="button" class="btn btn-xs btn-outline-danger shadow-none" title="Remove Item" onclick="window.posEngine.removeRow(${idx})">
                            <i class="fas fa-times"></i>
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

        // If Split mode, update preview breakdown
        if (state.tender_mode === 'Split') {
            updateSplitPreviewUI();
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
                placeholder: '-- Search Customer by Name or Mobile --',
                allowClear: true,
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
                templateResult: function (item) {
                    if (item.loading) return item.text;
                    if (!item.id) return item.text;
                    let mob = item.mobile ? `<span class="badge badge-light border text-muted ml-2"><i class="fas fa-phone mr-1 text-success"></i>${escapeHtml(item.mobile)}</span>` : '';
                    return $(`<div class="d-flex justify-content-between align-items-center py-1">
                        <span><strong>${escapeHtml(item.name || item.text)}</strong>${mob}</span>
                        <small class="text-muted">${item.pets_summary ? '<i class="fas fa-paw text-warning mr-1"></i>' + escapeHtml(item.pets_summary) : ''}</small>
                    </div>`);
                },
                templateSelection: function (item) {
                    if (!item.id) return item.text || '-- Search Customer by Name or Mobile --';
                    return item.name ? (item.mobile ? `${item.name} (${item.mobile})` : item.name) : (item.text || '-- Search Customer by Name or Mobile --');
                },
                language: {
                    noResults: function () {
                        const term = lastCustomerSearchTerm || $('.select2-search__field').val() || '';
                        const safeTerm = escapeHtml(term).replace(/"/g, '&quot;');
                        return `
                            <div class="py-2 px-1 text-center select2-no-results-box">
                                <div class="small text-muted mb-1">Customer not found</div>
                                <button type="button" class="btn btn-xs btn-primary font-weight-bold btn-select2-quick-add" onmousedown="event.preventDefault(); event.stopPropagation(); window.posEngine.triggerCreateCustomer('${safeTerm}');" onclick="event.preventDefault(); event.stopPropagation(); window.posEngine.triggerCreateCustomer('${safeTerm}');">
                                    <i class="fas fa-user-plus mr-1"></i> + Add Customer
                                </button>
                            </div>
                        `;
                    }
                },
                escapeMarkup: function (markup) {
                    return markup;
                }
            });

            // Clear customer selection
            $custSelect.on('select2:clear', function () {
                state.customer_id = null;
                $('#posSelectedCustomerBox').hide();
                $('#posCustomerInvoicesSection').hide();
                $('#posLoyaltyBadge').hide();
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
        $('#posCustomerSearchWrapper').show();
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

    // Submit Sale to Backend (Task 5: save, whatsapp, print)
    async function submitSale(action = 'print') {
        if (state.cart.length === 0) {
            showNotification('Please add at least one item to cart.', 'warning');
            return;
        }

        const invalidItem = state.cart.find(item => !item.qty || item.qty <= 0);
        if (invalidItem) {
            showNotification(`Item "${invalidItem.name}" has invalid quantity. Quantity must be greater than 0.`, 'danger');
            const badInput = cartTableBody ? cartTableBody.querySelector(`input.pos-qty-input[value="${invalidItem.qty}"]`) : null;
            if (badInput) {
                badInput.classList.add('is-invalid', 'border-danger');
                badInput.focus();
            }
            return;
        }

        if (!state.customer_id) {
            showNotification('Please select a customer for this bill.', 'warning');
            return;
        }

        const btnSaveOnly = document.getElementById('posBtnSaveOnly');
        const btnSaveWhatsApp = document.getElementById('posBtnSaveWhatsApp');

        if (payBtn) {
            payBtn.disabled = true;
            payBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        }
        if (btnSaveOnly) btnSaveOnly.disabled = true;
        if (btnSaveWhatsApp) btnSaveWhatsApp.disabled = true;

        const totals = computeTotals();

        // Build payments array matching backend expectations
        let payments = [];
        const tenderTypes = window.TENDER_TYPES || [];
        const cashTender = tenderTypes.find(t => (t.name && t.name.toLowerCase() === 'cash') || t.type === 'Cash') || tenderTypes[0] || { id: 1 };
        const cardTender = tenderTypes.find(t => (t.name && t.name.toLowerCase() === 'card') || t.type === 'Card') || cashTender;
        const walletTender = tenderTypes.find(t => (t.name && (t.name.toLowerCase() === 'wallet' || t.name.toLowerCase() === 'upi')) || t.type === 'Wallet') || cashTender;
        const creditTender = tenderTypes.find(t => (t.name && t.name.toLowerCase() === 'credit') || t.type === 'Credit') || cashTender;

        if (state.tender_mode === 'Split') {
            const sp = state.split_payments || {};
            const cashAmt = parseFloat(sp.cash) || 0;
            const cardAmt = parseFloat(sp.card) || 0;
            const walletAmt = parseFloat(sp.wallet) || 0;
            const creditAmt = parseFloat(sp.credit) || 0;
            const splitSum = Math.round((cashAmt + cardAmt + walletAmt + creditAmt) * 100) / 100;

            if (splitSum <= 0 || Math.abs(splitSum - totals.grandTotal) > 0.05) {
                openSplitPaymentModal();
                showNotification('Please configure and balance split payment amounts before submitting.', 'warning');
                payBtn.disabled = false;
                payBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Pay & Print (F6)';
                return;
            }

            let walletValueId = null;
            if (walletTender && walletTender.values && walletTender.values.length) {
                const matchedVal = walletTender.values.find(v => v.name.toUpperCase() === (sp.wallet_type || 'GPAY').toUpperCase());
                if (matchedVal) walletValueId = matchedVal.id;
            }

            if (cashAmt > 0) payments.push({ tender_type_id: cashTender.id, amount: cashAmt });
            if (cardAmt > 0) payments.push({ tender_type_id: cardTender.id, amount: cardAmt });
            if (walletAmt > 0) {
                const wPayload = { tender_type_id: walletTender.id, amount: walletAmt };
                if (walletValueId) wPayload.tender_type_value_id = walletValueId;
                payments.push(wPayload);
            }
            if (creditAmt > 0) payments.push({ tender_type_id: creditTender.id, amount: creditAmt });

            // Balance out rounding difference if any
            let sumP = payments.reduce((acc, p) => acc + p.amount, 0);
            sumP = Math.round(sumP * 100) / 100;
            let diff = Math.round((totals.grandTotal - sumP) * 100) / 100;
            if (Math.abs(diff) <= 0.05 && diff !== 0 && payments.length > 0) {
                payments[0].amount = Math.round((payments[0].amount + diff) * 100) / 100;
            }
        } else if (state.tender_mode === 'Card') {
            payments.push({ tender_type_id: cardTender.id, amount: totals.grandTotal });
        } else if (state.tender_mode === 'UPI') {
            let walletValueId = null;
            if (walletTender && walletTender.values && walletTender.values.length) {
                const gpay = walletTender.values.find(v => v.name.toUpperCase() === 'GPAY');
                if (gpay) walletValueId = gpay.id;
            }
            const p = { tender_type_id: walletTender.id, amount: totals.grandTotal };
            if (walletValueId) p.tender_type_value_id = walletValueId;
            payments.push(p);
        } else {
            // Cash
            payments.push({ tender_type_id: cashTender.id, amount: totals.grandTotal });
        }

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
            payment_type: state.tender_mode,
            round_off: totals.roundOff,
            items: state.cart.map(item => ({
                item_id: item.id,
                qty: item.qty,
                sell_price: item.sell_price,
                mrp: item.mrp,
                disc_percent: item.disc_percent,
                disc_amount: item.disc_amount
            })),
            payments: payments,
            tenders: payments
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

                // Action Handling: Print, WhatsApp, or Save
                if (action === 'print' && billId) {
                    window.open(`${window.APP_URL || ''}/sales/sales-bills/${billId}/receipt`, '_blank');
                } else if (action === 'whatsapp' && billId) {
                    let custMobile = (state.customer && state.customer.mobile) ? state.customer.mobile : (window.INITIAL_CUSTOMER?.mobile || '');
                    custMobile = (custMobile || '').replace(/\D/g, '');
                    if (custMobile) {
                        if (custMobile.length === 10) custMobile = '91' + custMobile;
                        const billNo = result.bill_number || billId;
                        const receiptUrl = `${window.APP_URL || ''}/sales/sales-bills/${billId}/receipt`;
                        const custName = (state.customer ? state.customer.name : '') || 'Customer';
                        const text = encodeURIComponent(`Hello ${custName}, thank you for your purchase! Your invoice #${billNo} for Rs. ${totals.grandTotal.toFixed(2)} is ready: ${receiptUrl}`);
                        window.open(`https://api.whatsapp.com/send?phone=${custMobile}&text=${text}`, '_blank');
                    } else {
                        showNotification(`Bill #${result.bill_number || ''} saved! Customer has no mobile number for WhatsApp.`, 'info');
                    }
                }

                // Reset for next sale
                state.cart = [];
                state.cash_received = 0;
                state.split_payments = { cash: 0, card: 0, wallet: 0, credit: 0, wallet_type: 'GPAY' };
                $('#posSplitRowCash, #posSplitRowCard, #posSplitRowWallet, #posSplitRowCredit').hide();
                $('#posSplitDispTotal').text('₹ 0.00');
                
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
            if (payBtn) {
                payBtn.disabled = state.cart.length === 0;
                payBtn.innerHTML = '<i class="fas fa-print mr-2"></i> Save & Print (F6)';
            }
            if (btnSaveOnly) btnSaveOnly.disabled = state.cart.length === 0;
            if (btnSaveWhatsApp) btnSaveWhatsApp.disabled = state.cart.length === 0;
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
            // ALT+S: Split
            else if (e.altKey && (e.code === 'KeyS' || e.key === 's' || e.key === 'S')) {
                e.preventDefault();
                document.querySelector('[data-mode="Split"]')?.click();
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
            const todayStr = new Date().toISOString().substring(0, 10);
            items.forEach(function (it, idx) {
                let itExpStr = it.exp_date ? it.exp_date.toString().substring(0, 10) : '';
                let isExpired = itExpStr && (itExpStr < todayStr);

                let expBadge = it.exp_date
                    ? (isExpired
                        ? `<span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>EXPIRED (${itExpStr})</span>`
                        : `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`)
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

                let isBlocked = isOutOfStock || isExpired;
                let rowClass = isBlocked ? 'isl-item-row isl-item-disabled text-muted bg-light' : 'isl-item-row';
                let rowStyle = isBlocked ? 'cursor: not-allowed; opacity: 0.6;' : 'cursor: pointer;';
                let actionBtn = isExpired
                    ? `<button type="button" class="btn btn-danger btn-xs px-2" disabled title="Expired Item - Cannot sell">
                        <i class="fas fa-ban mr-1"></i>Expired
                       </button>`
                    : (isOutOfStock
                        ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock">
                            <i class="fas fa-ban mr-1"></i>Out
                           </button>`
                        : `<button type="button" class="btn btn-success btn-xs px-2 isl-btn-select font-weight-bold">
                            <i class="fas fa-cart-plus mr-1"></i>Add
                           </button>`);

                html += `
                    <tr class="${rowClass} ${idx === 0 && !isBlocked ? 'table-primary' : ''}" style="${rowStyle}"
                        data-item='${JSON.stringify(it).replace(/'/g, "&#39;")}'>
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">
                            ${escapeHtml(it.name)}
                            ${isExpired ? '<span class="badge badge-danger ml-1 small">EXPIRED</span>' : (isOutOfStock ? '<span class="badge badge-secondary ml-1 small">Out of Stock</span>' : '')}
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
