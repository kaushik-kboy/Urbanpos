@extends('adminlte::page')

@section('title', 'Item Category Values')

@section('content_header')
    <h1>Item Category Value</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.item-category-values.import')" :sample-route="route('master.item-category-values.import-sample')" title="Item Category Value" />
            <a href="{{ route('master.item-category-values.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Item Category Value
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category Head</th>
                        <th>Show in Webstore</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($itemCategoryValues as $value)
                        <tr>
                            <td>{{ $value->name }}</td>
                            <td>{{ $value->itemCategory?->name }}</td>
                            <td>{{ $value->show_in_webstore ? 'Yes' : 'No' }}</td>
                            <td><x-status-badge :active="$value->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.item-category-values.edit', $value) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.item-category-values.destroy', $value) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this value?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No item category values yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$itemCategoryValues->perPage()" />
            {{ $itemCategoryValues->appends(request()->query())->links() }}
        </div>
    </div>
@stop
