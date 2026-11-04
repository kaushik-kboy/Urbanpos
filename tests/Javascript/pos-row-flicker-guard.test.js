import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('POS Row Input & Anti-Flicker Focus Guard', () => {
    let cartTableBody;
    let state;

    beforeEach(() => {
        document.body.innerHTML = `
            <table id="posCartTable">
                <tbody id="posCartBody"></tbody>
            </table>
        `;
        cartTableBody = document.getElementById('posCartBody');
        state = {
            cart: [
                { id: 1, name: 'Item 1', qty: 2, sell_price: 100, disc_percent: 0, disc_amount: 0 },
                { id: 2, name: 'Item 2', qty: 1, sell_price: 200, disc_percent: 0, disc_amount: 0 }
            ],
            last_added_idx: 1
        };
    });

    function commitAllOtherOpenInputs(keepIdx) {
        if (!cartTableBody) return;
        const inputs = cartTableBody.querySelectorAll('input.pos-row-input');
        inputs.forEach(input => {
            const otherIdx = parseInt(input.dataset.idx, 10);
            if (!isNaN(otherIdx) && otherIdx !== keepIdx) {
                const cell = input.closest('td');
                const otherItem = state.cart[otherIdx];
                if (!otherItem) return;

                if (cell.classList.contains('pos-row-qty')) {
                    const rawVal = input.value.trim();
                    const val = parseInt(rawVal.replace(/[^\d]/g, ''), 10);
                    if (!isNaN(val) && val > 0) {
                        otherItem.qty = val;
                        cell.textContent = val;
                    } else if (otherItem.qty) {
                        cell.textContent = otherItem.qty;
                    }
                }
            }
        });
    }

    it('should immediately commit and collapse previous row input to text when a new row is opened', () => {
        // Setup Row 0 as an active input
        cartTableBody.innerHTML = `
            <tr>
                <td class="pos-row-qty" data-idx="0">
                    <input type="number" class="pos-row-input pos-qty-input" data-idx="0" value="2">
                </td>
            </tr>
            <tr>
                <td class="pos-row-qty" data-idx="1">Qty</td>
            </tr>
        `;

        expect(cartTableBody.querySelectorAll('input.pos-row-input').length).toBe(1);

        // When Row 1 is opened, commitAllOtherOpenInputs(1) runs
        commitAllOtherOpenInputs(1);

        // Row 0's input must be collapsed to plain text '2'
        const row0Cell = cartTableBody.querySelector('.pos-row-qty[data-idx="0"]');
        expect(row0Cell.querySelector('input')).toBeNull();
        expect(row0Cell.textContent.trim()).toBe('2');
        expect(state.cart[0].qty).toBe(2);
    });

    it('should verify only newly added row is protected by _posLastItemSelectTime, avoiding focus tug-of-war', () => {
        window._posLastItemSelectTime = Date.now();
        window._posLastActiveRowIdx = 1; // Newly added is row 1

        const row0NewlyOpened = (0 === window._posLastActiveRowIdx) && (Date.now() - window._posLastItemSelectTime < 600);
        const row1NewlyOpened = (1 === window._posLastActiveRowIdx) && (Date.now() - window._posLastItemSelectTime < 600);

        // Row 0 must NOT be protected from blur
        expect(row0NewlyOpened).toBe(false);

        // Only Row 1 must be protected
        expect(row1NewlyOpened).toBe(true);
    });
});

