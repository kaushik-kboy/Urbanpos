@extends('adminlte::page')

@section('title', 'Repack Entry')

@section('content_header')
    <h1><i class="fas fa-boxes text-primary mr-2"></i>Repack Operation</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm">
        <form method="POST" action="{{ route('inventory.repack.process') }}">
            @csrf
            <div class="card-header bg-light">
                <h5 class="card-title font-weight-bold mb-0">Convert Bulk Bag into Repack Packages</h5>
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
                            <label>Bulk Source Item (e.g. 20KG Bag)</label>
                            {{-- Select2 AJAX: type to search, supports 1-crore items --}}
                            <select name="bulk_item_id" class="form-control item-search-select2" required style="width:100%">
                                <option value="">-- Type to search item --</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Qty Taken from Bulk (KG / Units)</label>
                            <input type="number" step="0.01" min="0.01" name="bulk_qty_taken" class="form-control font-weight-bold" placeholder="e.g. 20" required>
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="font-weight-bold mb-3"><i class="fas fa-box-open mr-1"></i> Resulting Repack Packets to Create</h6>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="repack-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Packaged Item (e.g. 1KG / 500g Pack)</th>
                                <th style="width: 150px;">Packets Qty Created</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="repack-body">
                            <tr>
                                <td>
                                    <select name="packs[0][item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                                        <option value="">-- Type to search item --</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="1" min="1" name="packs[0][qty]" class="form-control form-control-sm font-weight-bold" placeholder="Qty" required>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-pack"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btn-add-pack">
                    <i class="fas fa-plus mr-1"></i> Add Another Pack Line
                </button>
            </div>

            <div class="card-footer bg-light text-right">
                <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm" onclick="return confirm('Execute this repack conversion and adjust inventory?')">
                    <i class="fas fa-check mr-1"></i> Execute Repack
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

    // Init existing selects
    $('.item-search-select2').each(function() { initItemSelect2(this); });

    let packIdx = 1;
    $('#btn-add-pack').on('click', function() {
        const row = `
            <tr>
                <td>
                    <select name="packs[${packIdx}][item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                        <option value="">-- Type to search item --</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="1" min="1" name="packs[${packIdx}][qty]" class="form-control form-control-sm font-weight-bold" placeholder="Qty" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-pack"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        const $row = $(row);
        $('#repack-body').append($row);
        initItemSelect2($row.find('.item-search-select2'));
        packIdx++;
    });

    $(document).on('click', '.btn-remove-pack', function() {
        if ($('#repack-body tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });
</script>
@stop
