@extends('adminlte::page')

@section('title', 'Breed Master')

@section('content_header')
    <h1>Breed Master</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.breeds.import')" :sample-route="route('master.breeds.import-sample')" title="Breed" />
            <a href="{{ route('master.breeds.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Breed
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Breed Name</th>
                        <th>Pet Type</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($breeds as $breed)
                        <tr>
                            <td>{{ $breed->name }}</td>
                            <td>{{ $breed->petType?->name }}</td>
                            <td><x-status-badge :active="$breed->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.breeds.edit', $breed) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.breeds.destroy', $breed) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this breed?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No breeds yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$breeds->perPage()" />
            {{ $breeds->appends(request()->query())->links() }}
        </div>
    </div>
@stop
