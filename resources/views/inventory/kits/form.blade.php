@extends('adminlte::page')

@section('title', $isEdit ? 'Edit Kit Recipe' : 'New Kit Recipe')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold h5">
                <i class="fas fa-layer-group text-primary mr-2"></i> {{ $isEdit ? 'Edit Kit Recipe Definition' : 'Define New Kit Recipe' }}
            </h1>
            <small class="text-muted">Map a parent kit/combo product to its constituent items for automated assembly</small>
        </div>
        <a href="{{ route('inventory.kit-recipes.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> Back to Recipes
        </a>
    </div>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm">
        <form method="POST" action="{{ $isEdit ? route('inventory.kit-recipes.update', $recipe) : route('inventory.kit-recipes.store') }}" id="kit-recipe-form">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="font-weight-bold">Parent Kit Product to Produce <span class="text-danger">*</span></label>
                            <select name="kit_item_id" id="kit_item_id" class="form-control item-search-select2" required style="width:100%">
                                @if($recipe->kitItem)
                                    <option value="{{ $recipe->kitItem->id }}" selected>
                                        {{ $recipe->kitItem->name }} ({{ $recipe->kitItem->item_code ?: ('ITEM-' . $recipe->kitItem->id) }})
                                    </option>
                                @else
                                    <option value="">-- Type name or barcode to select master kit item --</option>
                                @endif
                            </select>
                            <small class="form-text text-muted">Select the master bundle product (e.g. Dog Grooming Kit, Starter Pack).</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="font-weight-bold">Recipe Name / Alias</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Standard 3-Item Assembly Recipe" value="{{ old('name', $recipe->name) }}">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="font-weight-bold">Status</label>
                            <select name="is_active" class="form-control">
                                <option value="1" @selected(old('is_active', $recipe->is_active ?? true) == 1)>Active</option>
                                <option value="0" @selected(old('is_active', $recipe->is_active ?? true) == 0)>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group mb-2">
                            <label class="font-weight-bold">Production / Assembly Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Optional notes for warehouse assemblers..." value="{{ old('notes', $recipe->notes) }}">
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="font-weight-bold mb-0 text-dark">
                        <i class="fas fa-puzzle-piece text-warning mr-1"></i> Ingredients / Components Bill of Materials
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="btn-add-line">
                        <i class="fas fa-plus mr-1"></i> Add Component
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped" id="components-table">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th style="min-width: 320px;">Component Product <span class="text-danger">*</span></th>
                                <th style="width: 180px;">Qty Consumed per 1 Kit <span class="text-danger">*</span></th>
                                <th>Notes / Position</th>
                                <th style="width: 50px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="components-body">
                            @php
                                $lines = old('components', $recipe->items ?? []);
                            @endphp
                            @if(count($lines) > 0)
                                @foreach($lines as $i => $line)
                                    @php
                                        $cItemId = is_array($line) ? ($line['component_item_id'] ?? null) : $line->component_item_id;
                                        $cQty = is_array($line) ? ($line['qty_per_kit'] ?? 1) : $line->qty_per_kit;
                                        $cRemarks = is_array($line) ? ($line['remarks'] ?? '') : $line->remarks;
                                        $cItem = is_array($line) ? \App\Models\Item::find($cItemId) : ($line->componentItem ?? null);
                                    @endphp
                                    <tr class="component-row">
                                        <td class="text-center align-middle row-num">{{ $i + 1 }}</td>
                                        <td>
                                            <select name="components[{{ $i }}][component_item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                                                @if($cItem)
                                                    <option value="{{ $cItem->id }}" selected>{{ $cItem->name }} ({{ $cItem->item_code ?: ('ITEM-' . $cItem->id) }})</option>
                                                @else
                                                    <option value="">-- Type to search item --</option>
                                                @endif
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0.0001" name="components[{{ $i }}][qty_per_kit]" class="form-control form-control-sm font-weight-bold" value="{{ $cQty }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="components[{{ $i }}][remarks]" class="form-control form-control-sm" placeholder="e.g. Inside pouch" value="{{ $cRemarks }}">
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button" class="btn btn-xs btn-outline-danger btn-remove-line"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr class="component-row">
                                    <td class="text-center align-middle row-num">1</td>
                                    <td>
                                        <select name="components[0][component_item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                                            <option value="">-- Type to search item --</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.0001" min="0.0001" name="components[0][qty_per_kit]" class="form-control form-control-sm font-weight-bold" value="1.0000" required>
                                    </td>
                                    <td>
                                        <input type="text" name="components[0][remarks]" class="form-control form-control-sm" placeholder="e.g. Main bottle">
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-xs btn-outline-danger btn-remove-line"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between">
                <a href="{{ route('inventory.kit-recipes.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4 font-weight-bold shadow-sm">
                    <i class="fas fa-save mr-1"></i> {{ $isEdit ? 'Update Kit Recipe' : 'Save Kit Recipe' }}
                </button>
            </div>
        </form>
    </div>
@stop

@section('js')
<script>
    $(document).ready(function () {
        let compIndex = {{ count($lines ?? []) > 0 ? count($lines) : 1 }};

        function initSelect2($element) {
            $element.select2({
                placeholder: '-- Type item name or barcode --',
                allowClear: true,
                ajax: {
                    url: '{{ route("inventory.barcode.search") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term };
                    },
                    processResults: function (data) {
                        return {
                            results: (data.items || []).map(function (item) {
                                return {
                                    id: item.id,
                                    text: item.name + (item.item_code ? ' (' + item.item_code + ')' : '') + ' - ₹' + parseFloat(item.sell_price || 0).toFixed(2)
                                };
                            })
                        };
                    },
                    cache: true
                }
            });
        }

        $('.item-search-select2').each(function () {
            initSelect2($(this));
        });

        $('#btn-add-line').on('click', function () {
            const rowHtml = `
                <tr class="component-row">
                    <td class="text-center align-middle row-num">${$('#components-body tr').length + 1}</td>
                    <td>
                        <select name="components[${compIndex}][component_item_id]" class="form-control form-control-sm item-search-select2" required style="width:100%">
                            <option value="">-- Type to search item --</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.0001" min="0.0001" name="components[${compIndex}][qty_per_kit]" class="form-control form-control-sm font-weight-bold" value="1.0000" required>
                    </td>
                    <td>
                        <input type="text" name="components[${compIndex}][remarks]" class="form-control form-control-sm" placeholder="Notes">
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-xs btn-outline-danger btn-remove-line"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
            const $row = $(rowHtml);
            $('#components-body').append($row);
            initSelect2($row.find('.item-search-select2'));
            compIndex++;
            reindexRows();
        });

        $(document).on('click', '.btn-remove-line', function () {
            if ($('#components-body tr').length <= 1) {
                alert('A kit recipe must have at least one component item.');
                return;
            }
            $(this).closest('tr').remove();
            reindexRows();
        });

        function reindexRows() {
            $('#components-body tr').each(function (idx) {
                $(this).find('.row-num').text(idx + 1);
            });
        }
    });
</script>
@stop
