@extends('adminlte::page')

@section('title', 'Raise Purchase Indent')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-clipboard-list mr-2 text-primary"></i> Raise Purchase Indent</h1>
            <small class="text-muted">Internal store & departmental inventory requisition</small>
        </div>
        <a href="{{ route('purchase.purchase-indents.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Indents
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <form action="{{ route('purchase.purchase-indents.store') }}" method="POST" id="indent-form">
            @csrf
            <div class="card-body">
                <x-error-summary />

                <div class="row mb-3">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Target Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" id="branch_id" class="form-control form-control-sm" required>
                            @foreach ($branches as $bId => $bName)
                                <option value="{{ $bId }}" @selected(old('branch_id') == $bId)>{{ $bName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-3">
                        <label class="font-weight-bold">Indent Date <span class="text-danger">*</span></label>
                        <input type="date" name="indent_date" id="indent_date" class="form-control form-control-sm"
                               value="{{ old('indent_date', now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-3">
                        <label class="font-weight-bold">Required By Date</label>
                        <input type="date" name="required_by_date" id="required_by_date" class="form-control form-control-sm"
                               value="{{ old('required_by_date', now()->addDays(3)->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-3 col-sm-6 mb-3">
                        <label class="font-weight-bold">Department <span class="text-danger">*</span></label>
                        <select name="department" id="department" class="form-control form-control-sm" required>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}" @selected(old('department', 'Store / Retail') == $dept)>{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6 mb-3">
                        <label class="font-weight-bold">Priority <span class="text-danger">*</span></label>
                        <select name="priority" id="priority" class="form-control form-control-sm" required>
                            @foreach ($priorities as $pri)
                                <option value="{{ $pri }}" @selected(old('priority', 'Medium') == $pri)>{{ $pri }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-12 mb-2">
                        <label class="font-weight-bold">General Remarks / Requisition Reason</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="e.g. Stock replenishment for upcoming weekend promotion">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="card card-outline card-secondary mb-3 shadow-none border">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                        <h6 class="m-0 font-weight-bold text-dark">
                            <i class="fas fa-boxes mr-1 text-primary"></i> Requisition Items
                        </h6>
                        <button type="button" id="btn-add-row" class="btn btn-primary btn-xs px-2">
                            <i class="fas fa-plus mr-1"></i> Add Item Line
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0" id="indent-items-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th style="min-width: 260px;">Item / Product <span class="text-danger">*</span></th>
                                        <th style="width: 120px;" class="text-center">Branch Stock</th>
                                        <th style="width: 130px;">Req. Qty <span class="text-danger">*</span></th>
                                        <th style="width: 130px;">Est. Unit Cost (₹)</th>
                                        <th style="width: 140px;" class="text-right">Est. Total (₹)</th>
                                        <th>Reason / Note</th>
                                        <th style="width: 50px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="indent-items-body">
                                    {{-- Dynamically populated --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Summary Footer --}}
                <div class="row justify-content-end">
                    <div class="col-md-5 col-lg-4">
                        <div class="card bg-light border shadow-none mb-0">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Items:</span>
                                    <strong id="summary-total-items">0</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Requested Qty:</span>
                                    <strong id="summary-total-qty">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-2">
                                    <span class="font-weight-bold">Est. Total Amount:</span>
                                    <strong class="text-primary font-weight-bold" style="font-size: 1.15rem;" id="summary-total-amount">₹0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center">
                <a href="{{ route('purchase.purchase-indents.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                    <i class="fas fa-paper-plane mr-1"></i> Submit Requisition for Approval
                </button>
            </div>
        </form>
    </div>

    {{-- Row Template --}}
    <template id="indent-row-template">
        <tr class="indent-row" data-index="__INDEX__">
            <td class="text-center align-middle row-number">__NUM__</td>
            <td>
                <select name="items[__INDEX__][item_id]" class="form-control form-control-sm item-select" required>
                    <option value="">-- Choose Item --</option>
                    @foreach ($items as $itm)
                        <option value="{{ $itm->id }}" data-cost="{{ $itm->cost_price }}" data-code="{{ $itm->item_code }}">
                            {{ $itm->item_code }} - {{ $itm->name }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td class="text-center align-middle">
                <span class="badge badge-light border stock-badge font-weight-normal px-2 py-1">0.00</span>
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="items[__INDEX__][requested_qty]" class="form-control form-control-sm text-right qty-input" placeholder="0.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[__INDEX__][estimated_cost]" class="form-control form-control-sm text-right cost-input" placeholder="0.00">
            </td>
            <td class="text-right align-middle font-weight-bold line-total">
                ₹0.00
            </td>
            <td>
                <input type="text" name="items[__INDEX__][remarks]" class="form-control form-control-sm" placeholder="Optional line note">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-outline-danger btn-xs remove-row-btn" title="Remove line">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    </template>

    @push('js')
    <script>
        (function() {
            let rowIndex = 0;
            const tbody = document.getElementById('indent-items-body');
            const template = document.getElementById('indent-row-template').innerHTML;
            const branchSelect = document.getElementById('branch_id');

            function addRow(prefillItemId = null, prefillQty = 1) {
                const html = template
                    .replaceAll('__INDEX__', rowIndex)
                    .replaceAll('__NUM__', tbody.children.length + 1);
                
                const tr = document.createElement('tbody');
                tr.innerHTML = html;
                const newRow = tr.firstElementChild;
                tbody.appendChild(newRow);

                if (prefillItemId) {
                    const select = newRow.querySelector('.item-select');
                    select.value = prefillItemId;
                    newRow.querySelector('.qty-input').value = prefillQty;
                    onItemChanged(newRow);
                }

                rowIndex++;
                reindexRows();
                calculateTotals();
            }

            function reindexRows() {
                const rows = tbody.querySelectorAll('.indent-row');
                rows.forEach((row, i) => {
                    const numCell = row.querySelector('.row-number');
                    if (numCell) numCell.textContent = i + 1;
                });
            }

            function onItemChanged(row) {
                const select = row.querySelector('.item-select');
                const itemId = select.value;
                const branchId = branchSelect.value;
                const stockBadge = row.querySelector('.stock-badge');
                const costInput = row.querySelector('.cost-input');

                if (!itemId) {
                    stockBadge.textContent = '0.00';
                    stockBadge.className = 'badge badge-light border stock-badge font-weight-normal px-2 py-1';
                    costInput.value = '';
                    calculateRowTotal(row);
                    return;
                }

                const opt = select.selectedOptions[0];
                const defaultCost = opt ? opt.getAttribute('data-cost') : 0;
                if (!costInput.value || parseFloat(costInput.value) <= 0) {
                    costInput.value = defaultCost ? parseFloat(defaultCost).toFixed(2) : '0.00';
                }

                // AJAX fetch live stock
                fetch(`{{ route('purchase.purchase-indents.item-stock') }}?item_id=${itemId}&branch_id=${branchId}`)
                    .then(res => res.json())
                    .then(data => {
                        const stock = parseFloat(data.current_stock) || 0;
                        stockBadge.textContent = stock.toFixed(2);
                        if (stock <= 0) {
                            stockBadge.className = 'badge badge-danger px-2 py-1';
                        } else if (stock <= 5) {
                            stockBadge.className = 'badge badge-warning px-2 py-1';
                        } else {
                            stockBadge.className = 'badge badge-success px-2 py-1';
                        }
                    })
                    .catch(() => {
                        stockBadge.textContent = '0.00';
                    });

                calculateRowTotal(row);
            }

            function calculateRowTotal(row) {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
                const total = Math.round(qty * cost * 100) / 100;
                row.querySelector('.line-total').textContent = '₹' + total.toFixed(2);
                calculateTotals();
            }

            function calculateTotals() {
                let totalItems = 0;
                let totalQty = 0;
                let totalAmount = 0;

                tbody.querySelectorAll('.indent-row').forEach(row => {
                    const itemId = row.querySelector('.item-select').value;
                    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;

                    if (itemId && qty > 0) {
                        totalItems++;
                        totalQty += qty;
                        totalAmount += (qty * cost);
                    }
                });

                document.getElementById('summary-total-items').textContent = totalItems;
                document.getElementById('summary-total-qty').textContent = totalQty.toFixed(2);
                document.getElementById('summary-total-amount').textContent = '₹' + (Math.round(totalAmount * 100) / 100).toFixed(2);
            }

            // Events
            document.getElementById('btn-add-row').addEventListener('click', () => addRow());

            tbody.addEventListener('change', function(e) {
                const row = e.target.closest('.indent-row');
                if (!row) return;
                if (e.target.classList.contains('item-select')) {
                    onItemChanged(row);
                }
            });

            tbody.addEventListener('input', function(e) {
                const row = e.target.closest('.indent-row');
                if (!row) return;
                if (e.target.classList.contains('qty-input') || e.target.classList.contains('cost-input')) {
                    calculateRowTotal(row);
                }
            });

            tbody.addEventListener('click', function(e) {
                const btn = e.target.closest('.remove-row-btn');
                if (!btn) return;
                const row = btn.closest('.indent-row');
                if (tbody.children.length <= 1) {
                    alert('An indent must have at least one line item.');
                    return;
                }
                row.remove();
                reindexRows();
                calculateTotals();
            });

            branchSelect.addEventListener('change', function() {
                tbody.querySelectorAll('.indent-row').forEach(row => {
                    onItemChanged(row);
                });
            });

            // Initial rows
            addRow();
            addRow();
        })();
    </script>
    @endpush
@stop
