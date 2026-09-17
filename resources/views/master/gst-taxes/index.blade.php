@extends('adminlte::page')

@section('title', 'GST Tax')

@section('content_header')
    <h1>GST Tax</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.gst-taxes.import')" :sample-route="route('master.gst-taxes.import-sample')" title="GST Tax" />
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.gst-taxes.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add GST Tax
                </a>
                <x-table-column-customizer table-key="master.gst-taxes" table-id="gst-taxes-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="gst-taxes-table" class="table table-striped mb-0">
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
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No GST taxes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$gstTaxes->perPage()" />
            {{ $gstTaxes->appends(request()->query())->links() }}
        </div>
    </div>
@stop
