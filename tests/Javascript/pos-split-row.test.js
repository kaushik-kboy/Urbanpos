import { describe, it, expect, beforeEach } from 'vitest';

describe('POS Terminal & Sales Bill Row Splitting and Multi-Discount Engine', () => {
    let cart = [];

    function recalculateRow(item) {
        const qtyNum = parseFloat(item.qty) || 0;
        const base = Math.max(0, qtyNum * item.sell_price);
        if (item.disc_percent > 100) item.disc_percent = 100;
        if (item.disc_percent < 0) item.disc_percent = 0;

        if (item.disc_percent > 0 && base > 0) {
            item.disc_amount = Math.min(base, Math.round((base * item.disc_percent / 100) * 100) / 100);
        } else {
            item.disc_amount = 0;
        }

        item.net_amount = Math.max(0, base - (item.disc_amount || 0));
        return item;
    }

    function getTotalCartQtyForItem(itemId, excludeIdx = -1) {
        return cart.reduce((sum, item, idx) => {
            if (idx !== excludeIdx && item.id === itemId) {
                return sum + (parseFloat(item.qty) || 0);
            }
            return sum;
        }, 0);
    }

    function splitRow(index) {
        const item = cart[index];
        if (!item) return false;

        const currentQty = parseFloat(item.qty) || 0;
        const stock = item.stock !== undefined ? parseFloat(item.stock) : null;
        const newRowIdx = index + 1;

        if (currentQty <= 1) {
            const totalRequested = getTotalCartQtyForItem(item.id) + 1;
            if (stock !== null && totalRequested > stock) {
                return { success: false, error: 'Insufficient stock' };
            }

            const newItem = JSON.parse(JSON.stringify(item));
            newItem.qty = 1;
            newItem.disc_percent = 0;
            newItem.disc_amount = 0;
            recalculateRow(newItem);
            cart.splice(newRowIdx, 0, newItem);
            return { success: true, newRowIdx };
        }

        item.qty = currentQty - 1;
        recalculateRow(item);

        const newItem = JSON.parse(JSON.stringify(item));
        newItem.qty = 1;
        newItem.disc_percent = 0;
        newItem.disc_amount = 0;
        recalculateRow(newItem);
        cart.splice(newRowIdx, 0, newItem);

        return { success: true, newRowIdx };
    }

    function matchCartRow(scannedItem) {
        return cart.findIndex(c => {
            if (c.id !== scannedItem.id) return false;
            if ((c.batch_no || '') !== (scannedItem.batch_no || '')) return false;
            if (Math.abs((c.sell_price || 0) - (scannedItem.sell_price || 0)) > 0.01) return false;

            // Smart non-merge: different discount rates stay in separate lines
            const cDiscPct = parseFloat(c.disc_percent || 0);
            const itemDiscPct = parseFloat(scannedItem.disc_percent || 0);
            if (Math.abs(cDiscPct - itemDiscPct) > 0.01) {
                return false;
            }

            return true;
        });
    }

    beforeEach(() => {
        cart = [];
    });

    it('Scenario 1: Buy 2 Get 1 Free (B2G1) - splits 3 units into 2 paid and 1 free (100% off)', () => {
        // Cashier scans Item A (Sell Price: ₹100, Qty: 3)
        const itemA = { id: 101, name: 'Royal Canin Mini', sell_price: 100, mrp: 100, qty: 3, disc_percent: 0, stock: 10 };
        recalculateRow(itemA);
        cart.push(itemA);

        expect(cart.length).toBe(1);
        expect(cart[0].qty).toBe(3);
        expect(cart[0].net_amount).toBe(300);

        // Cashier clicks Split Row to separate 1 free unit
        const result = splitRow(0);
        expect(result.success).toBe(true);
        expect(cart.length).toBe(2);

        // Row 1: Paid 2 units
        expect(cart[0].qty).toBe(2);
        expect(cart[0].disc_percent).toBe(0);
        expect(cart[0].net_amount).toBe(200);

        // Row 2: Free 1 unit (100% discount)
        expect(cart[1].qty).toBe(1);
        cart[1].disc_percent = 100;
        recalculateRow(cart[1]);

        expect(cart[1].net_amount).toBe(0);

        // Total bill amount = ₹200 for 3 units
        const billTotal = cart.reduce((sum, item) => sum + item.net_amount, 0);
        const totalUnits = cart.reduce((sum, item) => sum + item.qty, 0);
        expect(billTotal).toBe(200);
        expect(totalUnits).toBe(3);
    });

    it('Scenario 2: Same item on same bill with 20% discount and 30% discount', () => {
        // Customer has 2 units of Item A. Cashier adds Qty 2 with 20% discount
        const itemA = { id: 101, name: 'Dog Shampoo 200ml', sell_price: 200, mrp: 200, qty: 2, disc_percent: 20, stock: 5 };
        recalculateRow(itemA);
        cart.push(itemA);

        // Cashier wants the second unit at 30% discount because packaging is damaged
        const result = splitRow(0);
        expect(result.success).toBe(true);
        expect(cart.length).toBe(2);

        // Row 1: 1 unit at 20% discount
        expect(cart[0].qty).toBe(1);
        expect(cart[0].disc_percent).toBe(20);
        expect(cart[0].net_amount).toBe(160); // 200 - 40 = 160

        // Row 2: Set to 30% discount
        expect(cart[1].qty).toBe(1);
        cart[1].disc_percent = 30;
        recalculateRow(cart[1]);
        expect(cart[1].net_amount).toBe(140); // 200 - 60 = 140

        // Total bill = 160 + 140 = ₹300 (Stock deducts 2 units)
        const billTotal = cart.reduce((sum, item) => sum + item.net_amount, 0);
        expect(billTotal).toBe(300);
    });

    it('Scenario 3: Smart non-merge prevents regular scan from overwriting custom discounted row', () => {
        // Row 1 already in cart with 20% discount
        const row1 = { id: 101, name: 'Cat Food', sell_price: 150, mrp: 150, qty: 1, disc_percent: 20, stock: 10 };
        recalculateRow(row1);
        cart.push(row1);

        // Fresh scan of same item off shelf (disc_percent = 0)
        const scannedItem = { id: 101, name: 'Cat Food', sell_price: 150, mrp: 150, disc_percent: 0 };
        const matchIdx = matchCartRow(scannedItem);

        // Should NOT match row 1 because row 1 has 20% discount and scan has 0%
        expect(matchIdx).toBe(-1);

        // Instead creates a new separate row
        const newRow = JSON.parse(JSON.stringify(scannedItem));
        newRow.qty = 1;
        recalculateRow(newRow);
        cart.push(newRow);

        expect(cart.length).toBe(2);
        expect(cart[0].disc_percent).toBe(20);
        expect(cart[1].disc_percent).toBe(0);
    });

    it('Scenario 4: Stock validation prevents splitting/duplicating beyond available stock', () => {
        // Stock is only 1 unit
        const itemA = { id: 101, name: 'Limited Collar', sell_price: 500, mrp: 500, qty: 1, disc_percent: 0, stock: 1 };
        recalculateRow(itemA);
        cart.push(itemA);

        // Trying to duplicate when stock is only 1 should fail
        const result = splitRow(0);
        expect(result.success).toBe(false);
        expect(result.error).toBe('Insufficient stock');
        expect(cart.length).toBe(1);
    });
});
