@extends(request('is_iframe') ? 'layouts.iframe' : 'adminlte::page')

@section('title', 'Sales Bills')

@section('content_header')
    @if(!request('is_iframe'))
        <h1>Sales Bills</h1>
    @endif
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('auto_print_url'))
        <script>
            window.open("{{ session('auto_print_url') }}", "_blank", "width=900,height=700");
        </script>
    @endif
    @if (session('auto_whatsapp_url'))
        <script>
            window.open("{{ session('auto_whatsapp_url') }}", "_blank");
        </script>
    @endif

    <div class="@if(!request('is_iframe')) card card-default mb-3 shadow-none border @else mb-2 @endif">
        <div class="@if(!request('is_iframe')) card-body p-3 @endif">
            <form method="GET" action="{{ route('sales.sales-bills.index') }}" class="row align-items-end">
                @if(request('is_iframe'))
                    <input type="hidden" name="is_iframe" value="1">
                    @if(request()->has('mode'))
                        <input type="hidden" name="mode" value="{{ request('mode') }}">
                    @endif
                @endif
                @if(request('is_iframe'))
                    <div class="col-md-5 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Search Filter</label>
                        <div class="input-group input-group-sm">
                            <select name="search_column" class="form-control form-control-sm" style="max-width: 100px;">
                                <option value="all" @selected(request('search_column') == 'all')>All</option>
                                <option value="bill_number" @selected(request('search_column') == 'bill_number')>Bill No</option>
                                <option value="customer_name" @selected(request('search_column') == 'customer_name')>Customer</option>
                                <option value="mobile" @selected(request('search_column') == 'mobile')>Mobile</option>
                                <option value="amount" @selected(request('search_column') == 'amount')>Amount</option>
                            </select>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                                <a href="{{ route('sales.sales-bills.index', ['is_iframe' => 1, 'mode' => request('mode')]) }}" class="btn btn-secondary"><i class="fas fa-undo"></i></a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Search</label>
                        <div class="input-group input-group-sm">
                            <select name="search_column" class="form-control form-control-sm" style="max-width: 100px;">
                                <option value="all" @selected(request('search_column') == 'all')>All</option>
                                <option value="bill_number" @selected(request('search_column') == 'bill_number')>Bill No</option>
                                <option value="customer_name" @selected(request('search_column') == 'customer_name')>Customer</option>
                                <option value="mobile" @selected(request('search_column') == 'mobile')>Mobile</option>
                                <option value="amount" @selected(request('search_column') == 'amount')>Amount</option>
                            </select>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search bills..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">From Date</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="date_from" class="form-control form-control-sm datepicker" value="{{ request('date_from', now()->format('Y-m-d')) }}" placeholder="YYYY-MM-DD" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">To Date</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="date_to" class="form-control form-control-sm datepicker" value="{{ request('date_to', now()->format('Y-m-d')) }}" placeholder="YYYY-MM-DD" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Branch</label>
                        <select name="branch_id" class="form-control form-control-sm">
                            <option value="all">All Branches</option>
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" @selected(request('branch_id', session('active_branch_id', auth()->user()?->branch_id)) == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Customer</label>
                        <select name="customer_id" class="form-control form-control-sm">
                            <option value="">All Customers</option>
                            @foreach ($customers as $id => $name)
                                <option value="{{ $id }}" @selected(request('customer_id') == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1 col-sm-6 mb-2">
                        <label class="small font-weight-bold mb-1">Type</label>
                        <select name="invoice_type" class="form-control form-control-sm">
                            <option value="">All Types</option>
                            @foreach ($invoiceTypes as $type)
                                <option value="{{ $type }}" @selected(request('invoice_type') == $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 mt-1">
                        <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="fas fa-filter mr-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm ml-1 px-3">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        @if(!request('is_iframe'))
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Sales Bills List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('sales.sales-bills.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Sales Bill
                </a>
                <x-table-column-customizer table-key="sales.sales-bills" table-id="salesBillsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        @endif
        <div class="card-body p-0">
            <table class="table table-striped mb-0" id="salesBillsTable">
                <thead>
                    <tr class="text-nowrap">
                        <th>Bill No</th>
                        <th>Bill Date</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Branch</th>
                        <th>Invoice Type</th>
                        <th>Payment Mode</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesBills as $bill)
                        <tr class="text-nowrap">
                            <td class="font-weight-bold">
                                @if(request('mode') === 'edit')
                                    <a href="{{ request('is_iframe') ? route('pos.terminal', ['edit_id' => $bill->id]) : route('sales.sales-bills.edit', $bill) }}" @if(request('is_iframe')) target="_parent" @endif>{{ $bill->bill_number }}</a>
                                @else
                                    <a href="{{ route('sales.sales-bills.show', $bill) }}" @if(request('is_iframe')) target="_parent" @endif>{{ $bill->bill_number }}</a>
                                @endif
                            </td>
                            <td>{{ $bill->bill_date ? $bill->bill_date->format('d-m-Y h:i A') : '' }}</td>
                            <td>{{ $bill->customer?->name }}</td>
                            <td>{{ $bill->customer?->mobile }}</td>
                            <td>{{ $bill->branch?->name }}</td>
                            <td>{{ $bill->invoice_type }}</td>
                            <td>
                                @php
                                    $payMode = $bill->payment_type;
                                    if (!$payMode && $bill->relationLoaded('payments') && $bill->payments->count()) {
                                        $payMode = $bill->payments->map(fn($p) => $p->tenderType?->name)->filter()->unique()->implode(', ');
                                    }
                                    $payMode = $payMode ?: 'Cash';
                                @endphp
                                <span class="badge badge-info px-2 py-1">{{ $payMode }}</span>
                            </td>
                            <td class="font-weight-bold text-success">₹{{ number_format($bill->total, 2) }}</td>
                            <td class="text-right text-nowrap">
                                @if(!request('is_iframe'))
                                <a href="{{ route('sales.sales-returns.create', ['customer_id' => $bill->customer_id, 'sales_bill_id' => $bill->id]) }}" class="btn btn-xs btn-outline-warning mr-1" title="Sales Return for this Bill">
                                    <i class="fas fa-undo"></i>
                                </a>
                                <button type="button" class="btn btn-xs btn-outline-success btn-whatsapp-index mr-1" data-url="{{ route('sales.sales-bills.send-whatsapp', $bill) }}" data-phone="{{ $bill->customer?->phone ?: $bill->customer?->mobile }}" title="Send WhatsApp Bill to Client" onclick="sendWhatsAppFromIndex(this)">
                                    <i class="fab fa-whatsapp"></i>
                                </button>
                                <a href="{{ route('sales.sales-bills.receipt', $bill) }}" target="_blank" class="btn btn-xs btn-outline-success" title="Thermal Receipt (80mm)">
                                    <i class="fas fa-receipt"></i>
                                </a>
                                @endif
                                
                                @if(!request('is_iframe') || request('mode') === 'view' || !request()->has('mode'))
                                <a href="{{ route('sales.sales-bills.show', $bill) }}" class="btn btn-xs btn-outline-info" title="View Details" @if(request('is_iframe')) target="_parent" @endif>
                                    <i class="fas fa-eye"></i>
                                </a>
                                @endif

                                @if(!request('is_iframe') || request('mode') === 'edit')
                                <a href="{{ request('is_iframe') ? route('pos.terminal', ['edit_id' => $bill->id]) : route('sales.sales-bills.edit', $bill) }}" class="btn btn-xs btn-outline-secondary" title="Edit" @if(request('is_iframe')) target="_parent" @endif>
                                    <i class="fas fa-pen"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No sales bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $salesBills->links() }}</div>
    </div>
@stop

@section('js')
<script>
function sendWhatsAppFromIndex(btn) {
    const url = btn.getAttribute('data-url');
    if (!url) return;
    let phone = btn.getAttribute('data-phone') || '';
    if (!phone || phone.trim().length < 10) {
        phone = prompt('Customer has no mobile number saved. Enter 10-digit WhatsApp number:');
        if (!phone) return;
    }

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ phone: phone })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('⚠️ ' + (data.error || 'Failed to dispatch WhatsApp message'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('Network error: ' + err.message);
    });
}
</script>
@stop
