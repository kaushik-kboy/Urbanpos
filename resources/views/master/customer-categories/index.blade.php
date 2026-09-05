@extends('adminlte::page')

@section('title', 'Customer Categories')

@section('content_header')
    <h1>Customer Category</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.customer-categories.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Customer Category
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Business Type</th>
                        <th>Discount %</th>
                        <th>Loyalty</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customerCategories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->business_type }}</td>
                            <td>{{ $category->discount_percent }}</td>
                            <td>{{ $category->enable_loyalty ? 'Yes' : 'No' }}</td>
                            <td><x-status-badge :active="$category->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.customer-categories.edit', $category) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.customer-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this customer category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No customer categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $customerCategories->links() }}</div>
    </div>
@stop
