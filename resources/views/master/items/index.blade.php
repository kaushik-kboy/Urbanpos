@extends('adminlte::page')

@section('title', 'Items')

@section('content_header')
    <h1>Item</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.items.import')" :sample-route="route('master.items.import-sample')" title="Item" />
            <a href="{{ route('master.items.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Item
            </a>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.items.index') }}" class="form-row align-items-center">
                <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="Search by name, code, or alias...">
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                    <select name="supplier_id" class="form-control form-control-sm select2" data-placeholder="All Suppliers">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $sId => $sName)
                            <option value="{{ $sId }}" {{ request('supplier_id') == $sId ? 'selected' : '' }}>{{ $sName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    @if(request()->filled('name') || request()->filled('supplier_id'))
                        <a href="{{ route('master.items.index') }}" class="btn btn-sm btn-outline-secondary ml-1">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>Alias</th>
                        <th>Sell Price</th>
                        <th>Supplier</th>
                        <th>Updated Time</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->alias }}</td>
                            <td>{{ number_format($item->sell_price, 2) }}</td>
                            <td>{{ $item->supplier?->name }}</td>
                            <td>{{ $item->updated_at->format('d-m-Y H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.items.edit', $item) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$items->perPage()" />
            {{ $items->appends(request()->query())->links() }}
        </div>
    </div>
@stop
