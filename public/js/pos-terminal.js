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
        setupEventListeners();
        setupKeyboardShortcuts();
        loadHeldBills();
        renderCart();
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
        state.customer_id = document.getElementById('posCustomerSelect')?.value || null;
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

        // Customer selection change
        const custSelect = document.getElementById('posCustomerSelect');
        if (custSelect) {
            custSelect.addEventListener('change', () => {
                state.customer_id = custSelect.value;
                loadCustomerLoyalty(state.customer_id);
            });
        }

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

        // Store VPA - default or configured
        const vpa = window.STORE_VPA || 'chandakinfotech@icici';
        const name = encodeURIComponent(window.STORE_NAME || 'UrbanPOS');
        const upiUrl = `upi://pay?pa=${vpa}&pn=${name}&am=${total}&cu=INR`;
        const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(upiUrl)}`;
        qrImg.src = qrApi;
    }

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
            const sel = document.getElementById('posCustomerSelect');
            if (sel) sel.value = bill.customer_id;
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

        try {
            const resp = await fetch(`${window.APP_URL || ''}/sales/sales-bills`, {
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
            // F2: Focus scanner
            if (e.key === 'F2') {
                e.preventDefault();
                focusScanner();
            }
            // F6: Hold bill
            else if (e.key === 'F6') {
                e.preventDefault();
                holdCurrentBill();
            }
            // F7: Recall bill
            else if (e.key === 'F7') {
                e.preventDefault();
                recallHeldBill();
            }
            // F8: Cash
            else if (e.key === 'F8') {
                e.preventDefault();
                document.querySelector('[data-mode="Cash"]')?.click();
            }
            // F9: UPI
            else if (e.key === 'F9') {
                e.preventDefault();
                document.querySelector('[data-mode="UPI"]')?.click();
            }
            // F10: Card
            else if (e.key === 'F10') {
                e.preventDefault();
                document.querySelector('[data-mode="Card"]')?.click();
            }
            // F12: Submit
            else if (e.key === 'F12') {
                e.preventDefault();
                submitSale();
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

    // Expose methods to window for inline onclick handlers
    window.posEngine = {
        updateQty: updateItemQty,
        setQty: setItemQty,
        removeRow: removeItem
    };

})();
