@extends('adminlte::page')

@section('title', 'Change Serial No')

@section('content_header')
    <h1><i class="fas fa-barcode text-primary mr-2"></i>Change Serial No (Serialized Products)</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm">
        <form method="POST" action="{{ route('inventory.change-serial-no.process') }}">
            @csrf
            <div class="card-header bg-light">
                <h5 class="card-title font-weight-bold mb-0">Update Manufacturer Serial Numbers</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Location / Branch</label>
                            <select name="branch_id" class="form-control">
                                @foreach ($branches as $id => $name)
                                    <option value="{{ $id }}" @selected((string)$branchId === (string)$id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Item Name (Serialized Only)</label>
                            <select name="item_id" class="form-control select2" required id="serial-item-select" style="width: 100%;">
                                <option value="">-- Type to search serialized item --</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Part No (Read-Only)</label>
                            <input type="text" id="part-no-input" class="form-control" readonly placeholder="Auto-populated">
                        </div>
                    </div>
                </div>

                <hr>

                <h6 class="font-weight-bold mb-3"><i class="fas fa-list-ol mr-1"></i> Serial Numbers Mapping Grid</h6>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="serial-grid">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 50px;">S.No</th>
                                <th>MFR Serial No</th>
                                <th>New MFR Serial No</th>
                                <th>Serial No 2 &rarr; New Serial No 2</th>
                                <th>Serial No 3 &rarr; New Serial No 3</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="serial-body">
                            <tr>
                                <td class="text-center font-weight-bold">1</td>
                                <td>
                                    <input type="text" name="serials[0][old_serial]" class="form-control form-control-sm" placeholder="Current Serial">
                                </td>
                                <td>
                                    <input type="text" name="serials[0][new_serial]" class="form-control form-control-sm font-weight-bold text-primary" placeholder="New Serial" required>
                                </td>
                                <td>
                                    <input type="text" name="serials[0][serial_2]" class="form-control form-control-sm" placeholder="Serial No 2">
                                </td>
                                <td>
                                    <input type="text" name="serials[0][serial_3]" class="form-control form-control-sm" placeholder="Serial No 3">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-serial"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btn-add-serial">
                    <i class="fas fa-plus mr-1"></i> Add Serial Line
                </button>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between">
                <a href="{{ route('home') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-success px-4 font-weight-bold shadow-sm">
                    <i class="fas fa-save mr-1"></i> Save Serial Changes
                </button>
            </div>
        </form>
    </div>
@stop

@section('js')
<script>
    const ITEM_SEARCH_URL = '{{ route("sales.sales-bills.item-list") }}';

    $('#serial-item-select').select2({
        theme: 'bootstrap4',
        placeholder: '-- Type to search serialized item --',
        minimumInputLength: 1,
        ajax: {
            url: ITEM_SEARCH_URL,
            dataType: 'json',
            delay: 250,
            data: params => ({ search: params.term, show_all: 1 }),
            processResults: data => ({
                results: (data.items || []).map(i => ({ 
                    id: i.id, 
                    text: i.name + (i.item_code ? ' [' + i.item_code + ']' : ''),
                    item_code: i.item_code
                }))
            }),
            cache: true,
        },
        allowClear: true,
    }).on('select2:select', function(e) {
        let data = e.params.data;
        if (data && data.item_code) {
            $('#part-no-input').val(data.item_code);
        } else if (data && data.id) {
            $('#part-no-input').val('SKU-PART-' + data.id);
        } else {
            $('#part-no-input').val('');
        }
    }).on('select2:clear', function() {
        $('#part-no-input').val('');
    });

    let sIndex = 1;
    $('#btn-add-serial').on('click', function() {
        let row = `
            <tr>
                <td class="text-center font-weight-bold">${sIndex + 1}</td>
                <td>
                    <input type="text" name="serials[${sIndex}][old_serial]" class="form-control form-control-sm" placeholder="Current Serial">
                </td>
                <td>
                    <input type="text" name="serials[${sIndex}][new_serial]" class="form-control form-control-sm font-weight-bold text-primary" placeholder="New Serial" required>
                </td>
                <td>
                    <input type="text" name="serials[${sIndex}][serial_2]" class="form-control form-control-sm" placeholder="Serial No 2">
                </td>
                <td>
                    <input type="text" name="serials[${sIndex}][serial_3]" class="form-control form-control-sm" placeholder="Serial No 3">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-xs btn-outline-danger btn-remove-serial"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#serial-body').append(row);
        sIndex++;
    });

    $(document).on('click', '.btn-remove-serial', function() {
        if ($('#serial-body tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });
</script>
@stop
