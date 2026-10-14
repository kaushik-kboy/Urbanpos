import { describe, it, expect, beforeEach } from 'vitest';

describe('POS Batch Matching & Multi-Price Distinction (Task: Same Expiry, Unique MRP)', () => {
    let state;

    function getTotalCartQtyForItem(itemIdOrItem, excludeIdx = -1, batchNo = null, expDate = null, mrp = null) {
        let itemId = itemIdOrItem;
        let bNo = batchNo;
        let exp = expDate;
        let itemMrp = mrp;

        if (itemIdOrItem && typeof itemIdOrItem === 'object') {
            itemId = parseInt(itemIdOrItem.id || itemIdOrItem.item_id);
            if (bNo === null && itemIdOrItem.batch_no !== undefined) bNo = itemIdOrItem.batch_no;
            if (exp === null && itemIdOrItem.exp_date !== undefined) exp = itemIdOrItem.exp_date;
            if (itemMrp === null && (itemIdOrItem.mrp !== undefined || itemIdOrItem.sell_price !== undefined)) {
                itemMrp = itemIdOrItem.mrp || itemIdOrItem.sell_price;
            }
        }

        const expStr = exp ? (typeof exp === 'string' ? exp.substring(0, 10) : '') : '';
        const mrpNum = itemMrp !== null && itemMrp !== undefined ? parseFloat(itemMrp) : null;

        let total = 0;
        state.cart.forEach((c, idx) => {
            if (idx !== excludeIdx && c.id === itemId) {
                if (bNo !== null && (c.batch_no || '') !== (bNo || '')) {
                    return;
                }
                if (expStr && (c.exp_date ? c.exp_date.substring(0, 10) : '') !== expStr) {
                    return;
                }
                if (mrpNum !== null && !isNaN(mrpNum) && mrpNum > 0) {
                    const cMrp = parseFloat(c.mrp || c.sell_price || 0);
                    if (cMrp > 0 && Math.abs(cMrp - mrpNum) > 0.01) {
                        return;
                    }
                }
                total += parseFloat(c.qty) || 0;
            }
        });
        return total;
    }

    function addItemToCart(item) {
        if (!item) return false;
        const itemId = parseInt(item.id || item.item_id);
        if (isNaN(itemId)) return false;

        const batchNo = item.batch_no || '';
        const expDate = item.exp_date ? (typeof item.exp_date === 'string' ? item.exp_date.substring(0, 10) : '') : '';
        const itemMrp = parseFloat(item.mrp || 0);
        const itemSell = parseFloat(item.sell_price || item.unit_price || 0);

        const existingIdx = state.cart.findIndex(c => {
            if (c.id !== itemId) return false;
            if ((c.batch_no || '') !== batchNo) return false;
            if ((c.exp_date || '') !== expDate) return false;

            const cMrp = parseFloat(c.mrp || 0);
            if ((itemMrp > 0 || cMrp > 0) && Math.abs(cMrp - itemMrp) > 0.01) {
                return false;
            }

            const cSell = parseFloat(c.sell_price || c.unit_price || 0);
            if ((itemSell > 0 || cSell > 0) && Math.abs(cSell - itemSell) > 0.01) {
                return false;
            }

            return true;
        });

        if (existingIdx !== -1) {
            state.cart[existingIdx].qty = (parseFloat(state.cart[existingIdx].qty) || 0) + 1;
            return true;
        } else {
            const newItem = {
                id: itemId,
                name: item.name || 'Item',
                batch_no: batchNo,
                exp_date: expDate,
                sell_price: itemSell,
                mrp: itemMrp,
                qty: 1
            };
            state.cart.push(newItem);
            return true;
        }
    }

    beforeEach(() => {
        state = {
            cart: []
        };
    });

    it('creates distinct cart lines when items share the same expiry date but have different MRP / sell prices', () => {
        // Line 1: Item 5 with Expiry 2029-05-18 and MRP 1300
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 1300,
            sell_price: 1300
        });

        expect(state.cart.length).toBe(1);
        expect(state.cart[0].sell_price).toBe(1300);
        expect(state.cart[0].qty).toBe(1);

        // Add 2 more of the 1300 batch
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 1300,
            sell_price: 1300
        });
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 1300,
            sell_price: 1300
        });

        expect(state.cart.length).toBe(1);
        expect(state.cart[0].qty).toBe(3);

        // Now user selects the 2000 Rs batch with SAME expiry date (2029-05-18)
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 2000,
            sell_price: 2000
        });

        // MUST create a new line, NOT merge into 1300 line!
        expect(state.cart.length).toBe(2);
        expect(state.cart[0].sell_price).toBe(1300);
        expect(state.cart[0].qty).toBe(3);

        expect(state.cart[1].sell_price).toBe(2000);
        expect(state.cart[1].mrp).toBe(2000);
        expect(state.cart[1].qty).toBe(1);
        expect(state.cart[1].exp_date).toBe('2029-05-18');
    });

    it('correctly increments the matching batch row when adding the 2000 Rs batch again', () => {
        // Initial 1300 Rs line
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 1300,
            sell_price: 1300
        });

        // 2000 Rs line
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 2000,
            sell_price: 2000
        });

        // Second 2000 Rs addition
        addItemToCart({
            id: 5,
            name: 'item-5',
            batch_no: '',
            exp_date: '2029-05-18',
            mrp: 2000,
            sell_price: 2000
        });

        expect(state.cart.length).toBe(2);
        expect(state.cart[0].sell_price).toBe(1300);
        expect(state.cart[0].qty).toBe(1); // 1300 line unchanged

        expect(state.cart[1].sell_price).toBe(2000);
        expect(state.cart[1].qty).toBe(2); // 2000 line incremented to 2
    });

    it('calculates total cart quantities per-batch without cross-batch stock contamination', () => {
        state.cart = [
            { id: 5, batch_no: '', exp_date: '2029-05-18', mrp: 1300, sell_price: 1300, qty: 3 },
            { id: 5, batch_no: '', exp_date: '2029-05-18', mrp: 2000, sell_price: 2000, qty: 2 }
        ];

        // Checking stock usage for the 1300 batch
        const qty1300 = getTotalCartQtyForItem(5, -1, '', '2029-05-18', 1300);
        expect(qty1300).toBe(3);

        // Checking stock usage for the 2000 batch
        const qty2000 = getTotalCartQtyForItem(5, -1, '', '2029-05-18', 2000);
        expect(qty2000).toBe(2);

        // Checking stock usage when passing item object
        const itemObj = { id: 5, batch_no: '', exp_date: '2029-05-18', mrp: 2000 };
        expect(getTotalCartQtyForItem(itemObj, -1)).toBe(2);
    });
});
