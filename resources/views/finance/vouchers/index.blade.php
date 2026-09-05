@extends('adminlte::page')

@section('title', 'Voucher Entry')

@section('content_header')
    <h1>Voucher Entry</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('finance.vouchers.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> New Voucher
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Voucher No</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Narration</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vouchers as $voucher)
                        <tr>
                            <td>{{ $voucher->voucher_number }}</td>
                            <td>{{ $voucher->voucher_type }}</td>
                            <td>{{ $voucher->voucher_date->format('d-m-Y') }}</td>
                            <td>{{ $voucher->branch?->name }}</td>
                            <td>{{ $voucher->narration }}</td>
                            <td class="text-right">{{ number_format($voucher->total_debit, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('finance.vouchers.edit', $voucher) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('finance.vouchers.destroy', $voucher) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No vouchers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $vouchers->links() }}</div>
    </div>
@stop
