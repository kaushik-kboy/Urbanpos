import { describe, it, expect, beforeEach } from 'vitest';

describe('Dynamic Column Visibility & Priority Sequence Engine', () => {
    let table;
    let customizerWrapper;

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="customizer-wrapper-customers" 
                 class="table-customizer-wrapper"
                 data-table-key="customer_master_list"
                 data-table-id="customers-table"
                 data-saved-prefs='{"columns":[{"key":"name","visible":true,"order":1},{"key":"phone","visible":true,"order":2},{"key":"city","visible":false,"order":3},{"key":"credit_limit","visible":true,"order":4}]}'>
                
                <button type="button" id="btn-open-customizer" data-target="#table-col-modal-customers">
                    <i class="fas fa-cog"></i>
                </button>

                <div id="table-col-modal-customers" class="modal">
                    <div class="col-customizer-list">
                        <div class="customizer-item" data-col-key="name">
                            <input type="checkbox" class="col-toggle" checked>
                            <input type="number" class="col-order" value="1">
                        </div>
                        <div class="customizer-item" data-col-key="phone">
                            <input type="checkbox" class="col-toggle" checked>
                            <input type="number" class="col-order" value="2">
                        </div>
                        <div class="customizer-item" data-col-key="city">
                            <input type="checkbox" class="col-toggle">
                            <input type="number" class="col-order" value="3">
                        </div>
                        <div class="customizer-item" data-col-key="credit_limit">
                            <input type="checkbox" class="col-toggle" checked>
                            <input type="number" class="col-order" value="4">
                        </div>
                    </div>
                    <button type="button" id="btn-save-prefs">Save Preferences</button>
                    <button type="button" id="btn-reset-prefs">Reset Defaults</button>
                </div>
            </div>

            <table id="customers-table">
                <thead>
                    <tr class="header-row">
                        <th data-col-key="name">Customer Name</th>
                        <th data-col-key="phone">Phone</th>
                        <th data-col-key="city">City</th>
                        <th data-col-key="credit_limit">Credit Limit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="data-row">
                        <td data-col-key="name">Premchand Traders</td>
                        <td data-col-key="phone">9876543210</td>
                        <td data-col-key="city">Ahmedabad</td>
                        <td data-col-key="credit_limit">50,000.00</td>
                    </tr>
                </tbody>
            </table>
        `;

        table = document.getElementById('customers-table');
        customizerWrapper = document.getElementById('customizer-wrapper-customers');
    });

    function applyColumnPreferences(prefs) {
        prefs.columns.forEach(col => {
            const th = table.querySelector(`th[data-col-key="${col.key}"]`);
            const tds = table.querySelectorAll(`td[data-col-key="${col.key}"]`);

            if (th) {
                if (col.visible) {
                    th.style.display = '';
                    th.classList.remove('col-hidden');
                } else {
                    th.style.display = 'none';
                    th.classList.add('col-hidden');
                }
            }

            tds.forEach(td => {
                if (col.visible) {
                    td.style.display = '';
                    td.classList.remove('col-hidden');
                } else {
                    td.style.display = 'none';
                    td.classList.add('col-hidden');
                }
            });
        });
    }

    function reorderColumns(orderArray) {
        // orderArray: array of keys in order, e.g. ['phone', 'name', 'credit_limit', 'city']
        const headerRow = table.querySelector('thead tr');
        const rows = table.querySelectorAll('tbody tr');

        orderArray.forEach(key => {
            const th = headerRow.querySelector(`th[data-col-key="${key}"]`);
            if (th) headerRow.appendChild(th);
        });

        rows.forEach(row => {
            orderArray.forEach(key => {
                const td = row.querySelector(`td[data-col-key="${key}"]`);
                if (td) row.appendChild(td);
            });
        });
    }

    it('should correctly hide unchecked columns in both header and body rows', () => {
        const initialSavedPrefs = JSON.parse(customizerWrapper.getAttribute('data-saved-prefs'));
        applyColumnPreferences(initialSavedPrefs);

        // 'city' column is set to visible: false in preferences
        const cityTh = table.querySelector('th[data-col-key="city"]');
        const cityTd = table.querySelector('td[data-col-key="city"]');
        expect(cityTh.style.display).toBe('none');
        expect(cityTh.classList.contains('col-hidden')).toBe(true);
        expect(cityTd.style.display).toBe('none');
        expect(cityTd.classList.contains('col-hidden')).toBe(true);

        // 'name' column is set to visible: true
        const nameTh = table.querySelector('th[data-col-key="name"]');
        const nameTd = table.querySelector('td[data-col-key="name"]');
        expect(nameTh.style.display).toBe('');
        expect(nameTd.style.display).toBe('');
    });

    it('should dynamically reorder columns left-to-right when priority sequence changes', () => {
        // Initial order: Name, Phone, City, Credit Limit
        let currentThs = Array.from(table.querySelectorAll('thead th')).map(th => th.getAttribute('data-col-key'));
        expect(currentThs).toEqual(['name', 'phone', 'city', 'credit_limit']);

        // User changes order: Phone first, then Name, then Credit Limit, then City
        const newOrder = ['phone', 'name', 'credit_limit', 'city'];
        reorderColumns(newOrder);

        const reorderedThs = Array.from(table.querySelectorAll('thead th')).map(th => th.getAttribute('data-col-key'));
        expect(reorderedThs).toEqual(['phone', 'name', 'credit_limit', 'city']);

        const reorderedTds = Array.from(table.querySelectorAll('tbody tr:first-child td')).map(td => td.getAttribute('data-col-key'));
        expect(reorderedTds).toEqual(['phone', 'name', 'credit_limit', 'city']);
    });

    it('should package preferences into valid JSON payload for AJAX persistence', () => {
        const customizerItems = customizerWrapper.querySelectorAll('.customizer-item');
        const payload = {
            table_key: customizerWrapper.getAttribute('data-table-key'),
            columns: []
        };

        customizerItems.forEach(item => {
            payload.columns.push({
                key: item.getAttribute('data-col-key'),
                visible: item.querySelector('.col-toggle').checked,
                order: parseInt(item.querySelector('.col-order').value, 10)
            });
        });

        expect(payload.table_key).toBe('customer_master_list');
        expect(payload.columns.length).toBe(4);
        expect(payload.columns[0]).toEqual({ key: 'name', visible: true, order: 1 });
        expect(payload.columns[2]).toEqual({ key: 'city', visible: false, order: 3 });
    });
});
