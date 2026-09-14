@extends('adminlte::page')

@section('title', 'Customer Pet Details')

@section('content_header')
    <h1>Customer Pet Details</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.customer-pet-details') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Customer Name, Phone, Pet Name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Pet Type</label>
                    <select name="pet_type_id" class="form-control form-control-sm">
                        <option value="">All Pet Types</option>
                        @foreach ($petTypes as $ptId => $ptName)
                            <option value="{{ $ptId }}" {{ request('pet_type_id') == $ptId ? 'selected' : '' }}>{{ $ptName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Breed</label>
                    <select name="breed_id" class="form-control form-control-sm">
                        <option value="">All Breeds</option>
                        @foreach ($breeds as $bId => $bName)
                            <option value="{{ $bId }}" {{ request('breed_id') == $bId ? 'selected' : '' }}>{{ $bName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.customer-pet-details') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

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
