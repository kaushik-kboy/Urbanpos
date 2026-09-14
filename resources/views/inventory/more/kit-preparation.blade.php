@extends('adminlte::page')

@section('title', 'Kit Preparation')

@section('content_header')
    <h1><i class="fas fa-tools text-primary mr-2"></i>Kit Preparation</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm">
        <form method="POST" action="{{ route('inventory.kit-preparation.process') }}">
            @csrf
            <div class="card-header bg-light">
                <h5 class="card-title font-weight-bold mb-0">Assemble Kit / Combo Product from Components</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Location / Branch</label>
                            <select name="branch_id" class="form-control" required>
                                @foreach ($branches as $id => $name)
                                    <option value="{{ $id }}" @selected((string)$branchId === (string)$id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Master Kit Product to Produce</label>
                            <select name="kit_item_id" class="form-control select2" required>
                                <option value="">-- Select Combo / Kit Product --</option>
                                @foreach ($items as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Kits to Assemble (Quantity)</label>
                            <input type="number" step="1" min="1" name="kit_qty" class="form-control font-weight-bold" placeholder="e.g. 10" required>
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="font-weight-bold mb-3"><i class="fas fa-puzzle-piece mr-1"></i> Ingredients / Components Consumed per 1 Kit</h6>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="kit-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Component Item</th>
                                <th style="width: 180px;">Qty Consumed per 1 Kit</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="kit-body">
                            <tr>
                                <td>
                                    <select name="components[0][item_id]" class="form-control form-control-sm" required>
                                        <option value="">-- Select Component Item --</option>
                                        @foreach ($items as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0.01" name="components[0][qty_per_kit]" class="form-control form-control-sm font-weight-bold" placeholder="e.g. 1" required>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-comp"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btn-add-comp">
                    <i class="fas fa-plus mr-1"></i> Add Component Line
                </button>
            </div>

            <div class="card-footer bg-light text-right">
                <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm" onclick="return confirm('Assemble kits and adjust stock?')">
                    <i class="fas fa-check mr-1"></i> Assemble Kits
                </button>
            </div>
        </form>
    </div>
@stop

@section('js')
<script>
    let compIdx = 1;
    $('#btn-add-comp').on('click', function() {
        let options = $('#kit-body tr:first select').html();
        let row = `
            <tr>
                <td>
                    <select name="components[${compIdx}][item_id]" class="form-control form-control-sm" required>
                        ${options}
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" min="0.01" name="components[${compIdx}][qty_per_kit]" class="form-control form-control-sm font-weight-bold" placeholder="Qty" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-comp"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#kit-body').append(row);
        compIdx++;
    });

    $(document).on('click', '.btn-remove-comp', function() {
        if ($('#kit-body tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });
</script>
@stop
