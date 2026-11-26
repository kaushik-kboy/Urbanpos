@extends('adminlte::page')

@section('title', 'Customers')

@push('css')
<style>
.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    flex-shrink: 0;
}
.info-stat-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    border: 1.5px solid #e2e8f0 !important;
    background-color: #ffffff;
    border-radius: 10px;
    cursor: pointer;
}
.info-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.08) !important;
    border-color: #94a3b8 !important;
}
.info-stat-card.card-active {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12) !important;
}
.stat-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    letter-spacing: -0.01em;
}
.stat-value {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
}
</style>
@endpush

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="font-weight-bold" style="font-size: 1.6rem; color: #0f172a;"><i class="fas fa-users mr-2 text-primary"></i> Customer</h1>
        <div>
            @if(!empty($filter))
                <a href="{{ route('master.customers.index') }}" class="btn btn-sm btn-outline-danger font-weight-bold">
                    <i class="fas fa-times-circle mr-1"></i> Clear Filter ({{ ucwords(str_replace('_', ' ', $filter)) }})
                </a>
            @endif
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    {{-- ═════════════════════════════════════════════════════════════════════════ --}}
    {{-- TOP PET & CUSTOMER INSIGHTS STAT CARDS (MATCHING REFERENCE LAYOUT)        --}}
    {{-- ═════════════════════════════════════════════════════════════════════════ --}}
    <div class="row mb-2">
        {{-- Row 1: General Stats & Birthdays --}}
        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index') }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ empty($filter) ? 'card-active border-primary' : '' }}" style="{{ empty($filter) ? 'border-color: #007bff !important;' : '' }}">
                    <div class="stat-icon-wrapper bg-primary d-flex align-items-center justify-content-center text-white mr-3">
                        <i class="fas fa-users fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Total Customers</div>
                        <div class="stat-value">{{ number_format($stats['total_customers'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'today_birthdays'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'today_birthdays' ? 'card-active' : '' }}" style="{{ $filter === 'today_birthdays' ? 'border-color: #0ea5e9 !important; background: #f0f9ff !important;' : '' }}">
                    <div class="stat-icon-wrapper d-flex align-items-center justify-content-center text-white mr-3" style="background-color: #0ea5e9;">
                        <i class="fas fa-birthday-cake fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Today's Birthdays</div>
                        <div class="stat-value">{{ number_format($stats['today_birthdays'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'month_birthdays'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'month_birthdays' ? 'card-active' : '' }}" style="{{ $filter === 'month_birthdays' ? 'border-color: #28a745 !important; background: #f0fdf4 !important;' : '' }}">
                    <div class="stat-icon-wrapper bg-success d-flex align-items-center justify-content-center text-white mr-3">
                        <i class="fas fa-calendar-alt fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Birthdays This Month</div>
                        <div class="stat-value">{{ number_format($stats['month_birthdays'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'total_pets'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'total_pets' ? 'card-active' : '' }}" style="{{ $filter === 'total_pets' ? 'border-color: #f59e0b !important; background: #fffbeb !important;' : '' }}">
                    <div class="stat-icon-wrapper d-flex align-items-center justify-content-center text-white mr-3" style="background-color: #f59e0b;">
                        <i class="fas fa-paw fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Total Pets</div>
                        <div class="stat-value">{{ number_format($stats['total_pets'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Row 2: Age / Pet Lifecycle Stages --}}
        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'baby'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'baby' ? 'card-active' : '' }}" style="{{ $filter === 'baby' ? 'border-color: #ec4899 !important; background: #fdf2f8 !important;' : '' }}">
                    <div class="stat-icon-wrapper d-flex align-items-center justify-content-center text-white mr-3" style="background-color: #ec4899;">
                        <i class="fas fa-paw fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Baby (0 - 3M)</div>
                        <div class="stat-value">{{ number_format($stats['baby'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'puppy'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'puppy' ? 'card-active' : '' }}" style="{{ $filter === 'puppy' ? 'border-color: #06b6d4 !important; background: #ecfeff !important;' : '' }}">
                    <div class="stat-icon-wrapper d-flex align-items-center justify-content-center text-white mr-3" style="background-color: #06b6d4;">
                        <i class="fas fa-paw fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Puppy (3 - 15M)</div>
                        <div class="stat-value">{{ number_format($stats['puppy'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'adult'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'adult' ? 'card-active' : '' }}" style="{{ $filter === 'adult' ? 'border-color: #28a745 !important; background: #f0fdf4 !important;' : '' }}">
                    <div class="stat-icon-wrapper bg-success d-flex align-items-center justify-content-center text-white mr-3">
                        <i class="fas fa-paw fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Adult (15M - 7Y)</div>
                        <div class="stat-value">{{ number_format($stats['adult'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-lg-3 col-sm-6 col-12 mb-3">
            <a href="{{ route('master.customers.index', array_merge(request()->except('filter', 'page'), ['filter' => 'senior'])) }}" class="text-decoration-none">
                <div class="info-stat-card d-flex align-items-center p-3 shadow-sm {{ $filter === 'senior' ? 'card-active' : '' }}" style="{{ $filter === 'senior' ? 'border-color: #007bff !important; background: #eff6ff !important;' : '' }}">
                    <div class="stat-icon-wrapper bg-primary d-flex align-items-center justify-content-center text-white mr-3">
                        <i class="fas fa-paw fa-lg"></i>
                    </div>
                    <div>
                        <div class="stat-title">Senior (&gt;7Y)</div>
                        <div class="stat-value">{{ number_format($stats['senior'] ?? 0) }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    @if(!empty($filter))
        <div class="alert alert-info py-2 px-3 d-flex justify-content-between align-items-center mb-3 shadow-sm" style="border-radius: 8px;">
            <div>
                <i class="fas fa-filter mr-1"></i> Showing customers filtered by: <strong>{{ ucwords(str_replace('_', ' ', $filter)) }}</strong> (<strong>{{ $customers->total() }}</strong> customers matched)
            </div>
            <a href="{{ route('master.customers.index') }}" class="btn btn-xs btn-outline-dark font-weight-bold">
                Show All Customers
            </a>
        </div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-users mr-1"></i> Customer List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.customers.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Customer
                </a>
                <x-table-column-customizer table-key="master.customers" table-id="customers-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.customers.import')" :sample-route="route('master.customers.import-sample')" title="Customer" />
            </div>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.customers.index') }}" class="row align-items-end">
                @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                @if(request('direction'))
                    <input type="hidden" name="direction" value="{{ request('direction') }}">
                @endif
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, Code, Phone...">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Category</label>
                    <select name="category_id" class="form-control form-control-sm">
                        <option value="">All Categories</option>
                        @foreach ($categories as $cId => $cName)
                            <option value="{{ $cId }}" {{ request('category_id') == $cId ? 'selected' : '' }}>{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="{{ route('master.customers.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="customers-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <x-sortable-th column="id" label="Id" style="width: 70px;" />
                        <x-sortable-th column="name" label="Name" />
                        <x-sortable-th column="customer_code" label="Customer Id" />
                        <x-sortable-th column="category" label="Category" />
                        <x-sortable-th column="status" label="Status" />
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->customer_code }}</td>
                            <td>{{ $customer->category?->name }}</td>
                            <td><x-status-badge :active="$customer->status" /></td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('reports.smart-analytics', ['customer_id' => $customer->id]) }}" class="btn btn-xs btn-outline-info mr-1" title="View Purchase History (360° Analytics)"><i class="fas fa-chart-line"></i></a>
                                <a href="{{ route('master.customers.edit', $customer) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$customers->perPage()" />
            {{ $customers->appends(request()->query())->links() }}
        </div>
    </div>
@stop
