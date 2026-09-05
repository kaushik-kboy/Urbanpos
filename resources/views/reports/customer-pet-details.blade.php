@extends('adminlte::page')

@section('title', 'Customer Pet Details')

@section('content_header')
    <h1>Customer Pet Details</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Customer Code</th>
                        <th>Customer Name</th>
                        <th>Pet Name</th>
                        <th>Pet Type</th>
                        <th>Breed</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Birth Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @foreach ($customer->pets as $pet)
                            <tr>
                                <td>{{ $customer->customer_code }}</td>
                                <td>{{ $customer->name }}</td>
                                <td>{{ $pet->name }}</td>
                                <td>{{ $pet->petType?->name }}</td>
                                <td>{{ $pet->breed?->name }}</td>
                                <td>{{ $pet->gender }}</td>
                                <td>{{ $pet->age }}</td>
                                <td>{{ optional($pet->birth_date)->format('d-m-Y') }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No pet details recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $customers->links() }}</div>
    </div>
@stop
