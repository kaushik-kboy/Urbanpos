@extends('adminlte::page')

@section('title', 'Item Categories')

@section('content_header')
    <h1>Item Category</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.item-categories.import')" :sample-route="route('master.item-categories.import-sample')" title="Item Category" />
            <a href="{{ route('master.item-categories.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Item Category
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Item Category Name</th>
                        <th>Is Mandatory</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($itemCategories as $itemCategory)
                        <tr>
                            <td>{{ $itemCategory->name }}</td>
                            <td>{{ $itemCategory->is_mandatory ? 'Yes' : 'No' }}</td>
                            <td><x-status-badge :active="$itemCategory->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.item-categories.edit', $itemCategory) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.item-categories.destroy', $itemCategory) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this item category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No item categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$itemCategories->perPage()" />
            {{ $itemCategories->appends(request()->query())->links() }}
        </div>
    </div>
@stop
