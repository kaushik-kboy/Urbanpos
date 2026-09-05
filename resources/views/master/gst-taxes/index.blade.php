@extends('adminlte::page')

@section('title', 'GST Tax')

@section('content_header')
    <h1>GST Tax</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('master.gst-taxes.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add GST Tax
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Percentage</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gstTaxes as $gstTax)
                        <tr>
                            <td>{{ $gstTax->description }}</td>
                            <td>{{ $gstTax->percentage }}</td>
                            <td><x-status-badge :active="$gstTax->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.gst-taxes.edit', $gstTax) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.gst-taxes.destroy', $gstTax) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this GST tax?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No GST taxes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $gstTaxes->links() }}</div>
    </div>
@stop
