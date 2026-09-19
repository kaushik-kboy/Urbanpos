<div class="card-body">
    <x-error-summary />

    <ul class="nav nav-tabs mb-4" id="loyaltyTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active font-weight-bold" id="info-tab" data-toggle="tab" href="#tab-info" role="tab">
                <i class="fas fa-info-circle mr-1"></i>Loyalty Program Details
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold" id="slabs-tab" data-toggle="tab" href="#tab-slabs" role="tab">
                <i class="fas fa-layer-group mr-1"></i>Bill-Wise Slabs (Optional)
            </a>
        </li>
    </ul>

    <div class="tab-content" id="loyaltyTabContent">
        {{-- Tab 1: Info --}}
        <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name" class="font-weight-bold">Program Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $program->name) }}" required placeholder="e.g. Standard Customer Rewards 2026">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="start_date" class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="start_date" id="start_date" class="form-control datepicker" value="{{ old('start_date', optional($program->start_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" placeholder="YYYY-MM-DD" autocomplete="off" required>
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="end_date" class="font-weight-bold">End Date (Leave blank for ongoing)</label>
                        <div class="input-group">
                            <input type="text" name="end_date" id="end_date" class="form-control datepicker" value="{{ old('end_date', optional($program->end_date)->format('Y-m-d')) }}" placeholder="YYYY-MM-DD" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="based_on" class="font-weight-bold">Calculation Based On <span class="text-danger">*</span></label>
                        <select name="based_on" id="based_on" class="form-control">
                            <option value="Bill Amount" {{ old('based_on', $program->based_on) === 'Bill Amount' ? 'selected' : '' }}>Bill Amount</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="points_per_hundred" class="font-weight-bold">Points per ₹100 Spend <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" name="points_per_hundred" id="points_per_hundred" class="form-control text-right" value="{{ old('points_per_hundred', $program->points_per_hundred ?? 1.00) }}" required>
                            <div class="input-group-append"><span class="input-group-text">pts</span></div>
                        </div>
                        <small class="text-muted">e.g. 1 point for every ₹100 bill amount</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="min_points_redeem" class="font-weight-bold">Min Points for Redemption <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" min="1" name="min_points_redeem" id="min_points_redeem" class="form-control text-right" value="{{ old('min_points_redeem', $program->min_points_redeem ?? 50) }}" required>
                            <div class="input-group-append"><span class="input-group-text">pts</span></div>
                        </div>
                        <small class="text-muted">Customer must reach this threshold to redeem</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="amount_per_point" class="font-weight-bold">Rupee Value per 1 Point <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                            <input type="number" step="0.01" min="0.01" name="amount_per_point" id="amount_per_point" class="form-control text-right" value="{{ old('amount_per_point', $program->amount_per_point ?? 1.00) }}" required>
                        </div>
                        <small class="text-muted">Discount value when redeeming (e.g. ₹1.00/pt)</small>
                    </div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-md-4">
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="roundoff" name="roundoff" value="1" {{ old('roundoff', $program->roundoff ?? true) ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold" for="roundoff">Round Points to Whole Integer</label>
                        <small class="form-text text-muted">e.g. 14.8 points rounds to 15 points</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="status" name="status" value="1" {{ old('status', $program->status ?? true) ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold" for="status">Active Program</label>
                        <small class="form-text text-muted">Only active programs will accrue points on sales</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 2: Slabs --}}
        <div class="tab-pane fade" id="tab-slabs" role="tabpanel">
            <div class="alert alert-light border py-2 mb-3">
                <i class="fas fa-lightbulb text-warning mr-1"></i>
                <strong>Optional Custom Slabs</strong>: Define fixed points earned for specific bill ranges (e.g. ₹500–₹999 = 10 pts, ₹1000–₹2499 = 25 pts). If no slab matches, the default <em>Points per ₹100 spend</em> from Tab 1 will be used.
            </div>

            <table class="table table-bordered table-sm" id="slabs-table">
                <thead class="bg-dark text-white">
                    <tr>
                        <th>Min Bill Amount (₹)</th>
                        <th>Max Bill Amount (₹) (Leave empty for no upper limit)</th>
                        <th class="text-right" style="width: 180px;">Points Earned (pts)</th>
                        <th style="width: 50px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="slabs-tbody">
                    @php
                        $rules = old('rules', $program->rules ?? collect());
                    @endphp
                    @forelse($rules as $i => $rule)
                        <tr>
                            <td>
                                <input type="number" step="0.01" min="0" name="rules[{{ $i }}][min_bill_amount]" class="form-control form-control-sm text-right" value="{{ is_array($rule) ? ($rule['min_bill_amount'] ?? 0) : $rule->min_bill_amount }}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="rules[{{ $i }}][max_bill_amount]" class="form-control form-control-sm text-right" value="{{ is_array($rule) ? ($rule['max_bill_amount'] ?? '') : $rule->max_bill_amount }}" placeholder="Unlimited">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="rules[{{ $i }}][points_earned]" class="form-control form-control-sm text-right font-weight-bold text-success" value="{{ is_array($rule) ? ($rule['points_earned'] ?? 0) : $rule->points_earned }}" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-danger btn-remove-slab"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="btn-add-slab">
                <i class="fas fa-plus-circle mr-1"></i>Add Slab Row
            </button>
        </div>
    </div>
</div>

<div class="card-footer bg-light d-flex justify-content-between">
    <a href="{{ route('master.loyalty-programs.index') }}" class="btn btn-default">
        <i class="fas fa-arrow-left mr-1"></i>Cancel
    </a>
    <button type="submit" class="btn btn-primary px-4 font-weight-bold">
        <i class="fas fa-save mr-1"></i>Save Loyalty Program
    </button>
</div>

@push('js')
<script>
$(function () {
    var slabIndex = {{ count($rules ?? []) }};

    $('#btn-add-slab').on('click', function () {
        var rowHtml = '<tr>' +
            '<td><input type="number" step="0.01" min="0" name="rules[' + slabIndex + '][min_bill_amount]" class="form-control form-control-sm text-right" value="0" required></td>' +
            '<td><input type="number" step="0.01" min="0" name="rules[' + slabIndex + '][max_bill_amount]" class="form-control form-control-sm text-right" placeholder="Unlimited"></td>' +
            '<td><input type="number" step="0.01" min="0" name="rules[' + slabIndex + '][points_earned]" class="form-control form-control-sm text-right font-weight-bold text-success" value="10" required></td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger btn-remove-slab"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        $('#slabs-tbody').append(rowHtml);
        slabIndex++;
    });

    $(document).on('click', '.btn-remove-slab', function () {
        $(this).closest('tr').remove();
    });
});
</script>
@endpush
