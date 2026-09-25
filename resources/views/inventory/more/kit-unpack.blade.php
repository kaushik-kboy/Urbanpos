@extends('adminlte::page')

@section('title', 'Kit Item Unpack')

@section('content_header')
    <h1><i class="fas fa-box-open text-primary mr-2"></i>Kit Item – Unpack</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm">
        <form method="POST" action="{{ route('inventory.kit-unpack.process') }}">
            @csrf
            <div class="card-header bg-light">
                <h5 class="card-title font-weight-bold mb-0">Disassemble Combo / Kit Item Back into Individual Components</h5>
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
                            <label>Master Kit Product to Unpack</label>
                            <select name="kit_item_id" class="form-control item-search-select2" required style="width:100%">
                                <option value="">-- Type to search kit item --</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Kits to Unpack (Quantity)</label>
                            <input type="number" step="1" min="1" name="unpack_qty" class="form-control font-weight-bold" placeholder="e.g. 5" required>
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="font-weight-bold mb-3"><i class="fas fa-undo mr-1"></i> Components Returned to Stock per 1 Kit</h6>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="unpack-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Component Item Returned</th>
                                <th style="width: 180px;">Qty Returned per 1 Kit</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="unpack-body">
                            <tr>
                                <td>
                                    <select name="components[0][item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                                        <option value="">-- Type to search component --</option>
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
                <button type="submit" class="btn btn-warning px-4 font-weight-bold shadow-sm" onclick="return confirm('Unpack kits and reverse stock?')">
                    <i class="fas fa-box-open mr-1"></i> Confirm Kit Unpack
                </button>
            </div>
        </form>
    </div>
@stop

@section('js')
<script>
    const ITEM_SEARCH_URL = '{{ route("sales.sales-bills.item-list") }}';

    function initItemSelect2(el) {
        $(el).select2({
            theme: 'bootstrap4',
            placeholder: 'Type to search item…',
            minimumInputLength: 1,
            ajax: {
                url: ITEM_SEARCH_URL,
                dataType: 'json',
                delay: 250,
                data: params => ({ search: params.term, show_all: 1 }),
                processResults: data => ({
                    results: (data.items || []).map(i => ({ id: i.id, text: i.name + (i.item_code ? ' [' + i.item_code + ']' : '') }))
                }),
                cache: true,
            },
            allowClear: true,
        });
    }

    $('.item-search-select2').each(function() { initItemSelect2(this); });

    let compIdx = 1;
    $('#btn-add-comp').on('click', function() {
        const row = `
            <tr>
                <td>
                    <select name="components[${compIdx}][item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                        <option value="">-- Type to search component --</option>
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
        const $row = $(row);
        $('#unpack-body').append($row);
        initItemSelect2($row.find('.item-search-select2'));
        compIdx++;
    });

    $(document).on('click', '.btn-remove-comp', function() {
        if ($('#unpack-body tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });
</script>
@stop
