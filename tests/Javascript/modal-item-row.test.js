import { describe, it, expect, beforeEach } from 'vitest';

describe('Item Lookup Modal & Empty Row Auto-Delete Lifecycle', () => {
    let itemsBody;
    let activeSearchRow = null;
    let islModalOpen = false;
    let itemSelectedInModal = false;
    let cancellingRow = null;

    beforeEach(() => {
        document.body.innerHTML = `
            <table id="invoice-items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Disc Amt</th>
                        <th>Net Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    <tr class="item-row" data-row-index="1">
                        <td class="row-no">1</td>
                        <td>
                            <input type="hidden" class="item-id" value="101">
                            <input type="text" class="item-code" value="PET-DOG-01">
                        </td>
                        <td><input type="text" class="item-desc" value="Premium Dog Kibble"></td>
                        <td><input type="number" class="item-qty" value="2"></td>
                        <td><input type="number" class="item-rate" value="500"></td>
                        <td><input type="number" class="item-disc-amt" value="50"></td>
                        <td class="item-net">950.00</td>
                        <td><button type="button" class="btn-remove-row">X</button></td>
                    </tr>
                </tbody>
            </table>
            <div id="item-search-modal" class="modal">
                <div class="modal-content">
                    <button type="button" id="btn-modal-close" class="close">&times;</button>
                    <button type="button" id="btn-modal-cancel" class="btn btn-secondary">Cancel</button>
                    <table id="lookup-table">
                        <tbody>
                            <tr class="lookup-item" data-id="202" data-code="CAT-COLLAR" data-desc="Cat Velvet Collar" data-rate="250">
                                <td>CAT-COLLAR</td>
                                <td>Cat Velvet Collar</td>
                                <td>250.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="summary-section">
                <input type="number" id="freight-amount" value="0.00">
                <input type="number" id="round-off" value="0.00">
                <button type="button" id="btn-save-bill">Save & Tender</button>
            </div>
        `;

        itemsBody = document.getElementById('items-body');
        activeSearchRow = null;
        islModalOpen = false;
        itemSelectedInModal = false;
        cancellingRow = null;
    });

    function addNewRow() {
        const nextIndex = itemsBody.querySelectorAll('tr.item-row').length + 1;
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.setAttribute('data-row-index', nextIndex);
        tr.innerHTML = `
            <td class="row-no">${nextIndex}</td>
            <td>
                <input type="hidden" class="item-id" value="">
                <input type="text" class="item-code" value="">
            </td>
            <td><input type="text" class="item-desc" value=""></td>
            <td><input type="number" class="item-qty" value="1"></td>
            <td><input type="number" class="item-rate" value="0"></td>
            <td><input type="number" class="item-disc-amt" value="0"></td>
            <td class="item-net">0.00</td>
            <td><button type="button" class="btn-remove-row">X</button></td>
        `;
        itemsBody.appendChild(tr);
        return tr;
    }

    function openModalForRow(row) {
        activeSearchRow = row;
        islModalOpen = true;
        itemSelectedInModal = false;
        cancellingRow = null;
    }

    function closeModal(selectedItemData = null) {
        if (selectedItemData && activeSearchRow) {
            itemSelectedInModal = true;
            activeSearchRow.querySelector('.item-id').value = selectedItemData.id;
            activeSearchRow.querySelector('.item-code').value = selectedItemData.code;
            activeSearchRow.querySelector('.item-desc').value = selectedItemData.desc;
            activeSearchRow.querySelector('.item-rate').value = selectedItemData.rate;
            activeSearchRow.querySelector('.item-qty').focus();
        } else {
            // Cancelled without selection
            itemSelectedInModal = false;
            if (activeSearchRow) {
                const itemId = activeSearchRow.querySelector('.item-id').value;
                if (!itemId) {
                    cancellingRow = activeSearchRow;
                }
            }
        }

        // On modal hidden
        islModalOpen = false;
        if (!itemSelectedInModal && cancellingRow) {
            const totalRows = itemsBody.querySelectorAll('tr.item-row').length;
            if (totalRows > 1) {
                cancellingRow.remove();
            } else {
                cancellingRow.querySelector('.item-code').value = '';
                cancellingRow.querySelector('.item-desc').value = '';
            }
            cancellingRow = null;
        }
        activeSearchRow = null;
    }

    it('should automatically delete newly added empty row if item modal is cancelled/closed', () => {
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(1);

        // Step 1: User adds new row
        const newRow = addNewRow();
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(2);

        // Step 2: Item lookup modal opens for new row
        openModalForRow(newRow);
        expect(islModalOpen).toBe(true);

        // Step 3: User hits Escape or Cancel button without selecting an item
        closeModal(null);

        // Step 4: The empty row must be completely removed from DOM!
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(1);
        expect(document.querySelector('tr[data-row-index="2"]')).toBeNull();
    });

    it('should preserve row and populate all fields when item is selected from lookup modal', () => {
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(1);

        const newRow = addNewRow();
        openModalForRow(newRow);

        // User clicks/selects item from lookup modal
        const selectedData = { id: '202', code: 'CAT-COLLAR', desc: 'Cat Velvet Collar', rate: '250' };
        closeModal(selectedData);

        // Row should be preserved with populated values
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(2);
        expect(newRow.querySelector('.item-id').value).toBe('202');
        expect(newRow.querySelector('.item-code').value).toBe('CAT-COLLAR');
        expect(newRow.querySelector('.item-desc').value).toBe('Cat Velvet Collar');
        expect(newRow.querySelector('.item-rate').value).toBe('250');
    });

    it('should not delete the only row if cancelled, but clear empty values instead', () => {
        // Clear all rows to simulate fresh bill with 1 empty row
        itemsBody.innerHTML = '';
        const singleRow = addNewRow();
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(1);

        openModalForRow(singleRow);
        closeModal(null); // Cancelled

        // 1 row remains available for entry
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(1);
        expect(singleRow.querySelector('.item-code').value).toBe('');
    });

    it('should handle Tab navigation sequence from Disc Amt to Freight or New Row', () => {
        const rows = itemsBody.querySelectorAll('tr.item-row');
        const discInput = rows[0].querySelector('.item-disc-amt');
        const freightInput = document.getElementById('freight-amount');

        // Function simulating Enter / Tab on Disc Amt
        function handleDiscTab(currentInput, isLastRow, createNewRow = false) {
            if (isLastRow && createNewRow) {
                const tr = addNewRow();
                openModalForRow(tr);
                return tr;
            } else {
                freightInput.focus();
                return freightInput;
            }
        }

        // 1. Moving to freight
        const target = handleDiscTab(discInput, true, false);
        expect(target.id).toBe('freight-amount');
        expect(document.activeElement.id).toBe('freight-amount');

        // 2. Creating new row on Tab if requested
        const newTarget = handleDiscTab(discInput, true, true);
        expect(newTarget.classList.contains('item-row')).toBe(true);
        expect(itemsBody.querySelectorAll('tr.item-row').length).toBe(2);
        expect(islModalOpen).toBe(true);
    });
});
