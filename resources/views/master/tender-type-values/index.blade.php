@extends('adminlte::page')

@section('title', 'Tender Type Values')

@section('content_header')
    <h1>Tender Type Values</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.tender-type-values.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Tender Type Value
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Tender Name</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenderTypeValues as $value)
                        <tr>
                            <td>{{ $value->name }}</td>
                            <td>{{ $value->tenderType?->name }}</td>
                            <td>{{ $value->branch?->name ?? 'GLOBAL' }}</td>
                            <td><x-status-badge :active="$value->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.tender-type-values.edit', $value) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.tender-type-values.destroy', $value) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this tender type value?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No tender type values yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $tenderTypeValues->links() }}</div>
    </div>
@stop
