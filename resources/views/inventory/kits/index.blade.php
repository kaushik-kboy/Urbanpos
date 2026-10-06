@extends('adminlte::page')

@section('title', 'Kit Recipe Master')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold h5">
                <i class="fas fa-layer-group text-primary mr-2"></i> Kit Recipe Master (Assembly Definitions)
            </h1>
            <small class="text-muted">Define parent combo/bundle products and ingredient bills of materials (GoFrugal / TruePOS Parity)</small>
        </div>
        <div>
            <a href="{{ route('inventory.kit-preparation.index') }}" class="btn btn-outline-info btn-sm mr-2 font-weight-bold">
                <i class="fas fa-tools mr-1"></i> Assemble Kits
            </a>
            <a href="{{ route('inventory.kit-recipes.create') }}" class="btn btn-primary btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-plus-circle mr-1"></i> Define New Recipe
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-white py-2">
            <form method="GET" action="{{ route('inventory.kit-recipes.index') }}" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search by Kit product name or item code..." value="{{ request('search') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </div>
                @if(request('search'))
                    <div class="col-md-2">
                        <a href="{{ route('inventory.kit-recipes.index') }}" class="btn btn-outline-secondary btn-sm">Clear Filter</a>
                    </div>
                @endif
            </form>
        </div>

        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">#</th>
                        <th style="width: 140px;">Kit Item Code</th>
                        <th>Parent Kit Product Name</th>
                        <th style="width: 120px;" class="text-center">Ingredients</th>
                        <th>Bill of Materials (Components Summary)</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 170px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recipes as $idx => $r)
                        <tr>
                            <td class="text-center align-middle">{{ $recipes->firstItem() + $idx }}</td>
                            <td class="align-middle font-weight-bold text-monospace">
                                <code>{{ $r->kitItem?->item_code ?: ('ITEM-' . $r->kit_item_id) }}</code>
                            </td>
                            <td class="align-middle">
                                <strong class="text-dark">{{ $r->kitItem?->name ?? 'Unknown Item' }}</strong>
                                @if($r->notes)
                                    <div class="text-muted small"><i class="fas fa-sticky-note mr-1"></i>{{ Str::limit($r->notes, 60) }}</div>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <span class="badge badge-info px-2 py-1 font-weight-bold">
                                    {{ $r->items->count() }} items
                                </span>
                            </td>
                            <td class="align-middle small">
                                <ul class="list-unstyled mb-0">
                                    @foreach($r->items->take(4) as $ci)
                                        <li>
                                            <i class="fas fa-caret-right text-primary mr-1"></i>
                                            <strong>{{ number_format($ci->qty_per_kit, 2) }}x</strong> {{ $ci->componentItem?->name ?? 'Item' }}
                                        </li>
                                    @endforeach
                                    @if($r->items->count() > 4)
                                        <li class="text-muted"><em>+ {{ $r->items->count() - 4 }} more components...</em></li>
                                    @endif
                                </ul>
                            </td>
                            <td class="text-center align-middle">
                                @if($r->is_active)
                                    <span class="badge badge-success px-2 py-1">Active</span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Inactive</span>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <a href="{{ route('inventory.kit-recipes.edit', $r) }}" class="btn btn-xs btn-outline-primary mr-1" title="Edit Recipe">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <form action="{{ route('inventory.kit-recipes.destroy', $r) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete recipe for this kit product?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete Recipe">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-box-open fa-3x mb-2 text-secondary"></i>
                                <p class="mb-2 font-weight-bold">No Kit Recipes defined yet.</p>
                                <a href="{{ route('inventory.kit-recipes.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus mr-1"></i> Define Your First Kit Recipe
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($recipes->hasPages())
            <div class="card-footer bg-white d-flex justify-content-end py-2">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>
@stop
