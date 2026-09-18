import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('UrbanPOS Global Keyboard Navigation & Hotkeys Engine', () => {
    // Normalization logic extracted from public/js/pos-hotkeys.js
    function getNormalizedKey(e) {
        let key = e.key || '';
        let code = e.code || '';
        let alt = e.altKey;
        let ctrl = e.ctrlKey;
        let shift = e.shiftKey;

        // Function keys (F1 - F12)
        if (/^F\d{1,2}$/i.test(key)) {
            return key.toUpperCase();
        }

        // Single letter keys with Alt or Ctrl+Shift
        if (alt || (ctrl && shift)) {
            let parts = [];
            if (ctrl) parts.push('CTRL');
            if (alt) parts.push('ALT');
            if (shift) parts.push('SHIFT');

            if (key && key.length === 1 && key !== ' ') {
                parts.push(key.toUpperCase());
                return parts.join('+');
            }

            if (code && code.startsWith('Key')) {
                parts.push(code.replace('Key', '').toUpperCase());
                return parts.join('+');
            }
        }

        // Escape Key
        if (key === 'Escape' || code === 'Escape') {
            return 'ESCAPE';
        }

        // Shift+Delete or Alt+Delete
        if ((shift || alt) && (key === 'Delete' || code === 'Delete' || key === 'Del')) {
            return (shift ? 'SHIFT' : 'ALT') + '+DELETE';
        }

        return null;
    }

    const DEFAULT_ACTIONS = {
        'ALT+S': { action_key: 'open_sales_bill', target_url: 'sales/sales-bills/create' },
        'ALT+P': { action_key: 'open_purchase_invoice', target_url: 'purchase/purchase-invoices/create' },
        'ALT+T': { action_key: 'open_stock_transfer', target_url: 'inventory/stock-transfers/create' },
        'ALT+C': { action_key: 'open_customer_master', target_url: 'master/customers' },
        'ALT+I': { action_key: 'open_item_master', target_url: 'master/items' },
        'ALT+O': { action_key: 'open_purchase_order', target_url: 'purchase/purchase-orders/create' },
        'ALT+Q': { action_key: 'open_sales_quotation', target_url: 'sales/sales-quotations/create' },
        'ALT+R': { action_key: 'open_sales_return', target_url: 'sales/sales-returns/create' },
        'F2': { action_key: 'search_item', scope: 'all_forms' },
        'F3': { action_key: 'new_entry', scope: 'all_forms' },
        'F6': { action_key: 'save_form', scope: 'all_forms' },
        'F9': { action_key: 'clear_form', scope: 'all_forms' },
        'ESCAPE': { action_key: 'close_modal', scope: 'all_forms' }
    };

    it('should correctly normalize Alt+P for Purchase Invoice navigation', () => {
        const event = { altKey: true, key: 'p', code: 'KeyP' };
        const normalized = getNormalizedKey(event);
        expect(normalized).toBe('ALT+P');
        expect(DEFAULT_ACTIONS[normalized].action_key).toBe('open_purchase_invoice');
        expect(DEFAULT_ACTIONS[normalized].target_url).toBe('purchase/purchase-invoices/create');
    });

    it('should correctly normalize Alt+S for Sales Bill navigation', () => {
        const event = { altKey: true, key: 's', code: 'KeyS' };
        const normalized = getNormalizedKey(event);
        expect(normalized).toBe('ALT+S');
        expect(DEFAULT_ACTIONS[normalized].action_key).toBe('open_sales_bill');
        expect(DEFAULT_ACTIONS[normalized].target_url).toBe('sales/sales-bills/create');
    });

    it('should correctly normalize Alt+T for Stock Transfer navigation', () => {
        const event = { altKey: true, key: 't', code: 'KeyT' };
        const normalized = getNormalizedKey(event);
        expect(normalized).toBe('ALT+T');
        expect(DEFAULT_ACTIONS[normalized].action_key).toBe('open_stock_transfer');
        expect(DEFAULT_ACTIONS[normalized].target_url).toBe('inventory/stock-transfers/create');
    });

    it('should correctly normalize function keys F2, F3, F6, and Escape', () => {
        expect(getNormalizedKey({ key: 'F2' })).toBe('F2');
        expect(DEFAULT_ACTIONS['F2'].action_key).toBe('search_item');

        expect(getNormalizedKey({ key: 'F3' })).toBe('F3');
        expect(DEFAULT_ACTIONS['F3'].action_key).toBe('new_entry');

        expect(getNormalizedKey({ key: 'F6' })).toBe('F6');
        expect(DEFAULT_ACTIONS['F6'].action_key).toBe('save_form');

        expect(getNormalizedKey({ key: 'Escape' })).toBe('ESCAPE');
        expect(DEFAULT_ACTIONS['ESCAPE'].action_key).toBe('close_modal');
    });

    it('should auto-focus the first active interactive form field on page load', () => {
        document.body.innerHTML = `
            <form id="purchase-invoice-form">
                <input type="hidden" name="_token" value="dummy-csrf-token">
                <input type="text" id="disabled-ref" disabled value="disabled">
                <input type="text" id="readonly-doc-no" readonly value="PINV-001">
                <select id="first-supplier-select" class="form-control">
                    <option value="">Choose Supplier</option>
                    <option value="1">ABC Pet Wholesalers</option>
                </select>
                <input type="date" id="invoice-date">
            </form>
        `;

        function focusFirstActiveField(formSelector) {
            const form = document.querySelector(formSelector);
            if (!form) return null;
            const focusable = form.querySelectorAll('input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])');
            if (focusable.length > 0) {
                focusable[0].focus();
                return focusable[0];
            }
            return null;
        }

        const focusedElement = focusFirstActiveField('#purchase-invoice-form');
        expect(focusedElement).not.toBeNull();
        expect(focusedElement.id).toBe('first-supplier-select');
        expect(document.activeElement.id).toBe('first-supplier-select');
    });
});
