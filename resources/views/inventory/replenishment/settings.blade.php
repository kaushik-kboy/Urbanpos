@extends('adminlte::page')

@section('title', 'Replenishment Rules & Settings')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-sliders-h text-primary mr-2"></i> Replenishment Rules & Settings
            </h1>
            <small class="text-muted">Configure Demand Velocity thresholds, Target Cover Days buffer, and Stock Condition logic</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <form action="{{ route('inventory.replenishment.settings.update') }}" method="POST">
        @csrf
        <div class="row">
            {{-- Sales Movement Rules Configuration --}}
            <div class="col-lg-7">
                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-chart-line text-primary mr-1"></i> 1. Sales Movement Rules (Configurable Target Cover Days)
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3 bg-light border-bottom text-muted text-xs">
                            <i class="fas fa-info-circle mr-1 text-primary"></i>
                            The system assigns every product a <strong>Sales Type</strong> based on its 30-day velocity, then sets how many days of inventory to maintain as a safe target buffer.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-sm">
                                <thead class="bg-light text-muted text-xs text-uppercase">
                                    <tr>
                                        <th>Sales Type</th>
                                        <th>Daily Avg Sales</th>
                                        <th style="width: 170px;">Target Cover Days</th>
                                        <th>Recommended Buffer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($salesRules as $rule)
                                        <tr>
                                            <td class="font-weight-bold">
                                                @if($rule->rule_name === 'Highest Moving')
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-fire mr-1"></i> Highest Moving</span>
                                                @elseif($rule->rule_name === 'Fast Moving')
                                                    <span class="badge badge-primary px-2 py-1"><i class="fas fa-tachometer-alt mr-1"></i> Fast Moving</span>
                                                @elseif($rule->rule_name === 'Normal Moving')
                                                    <span class="badge badge-info px-2 py-1"><i class="fas fa-boxes mr-1"></i> Normal Moving</span>
                                                @elseif($rule->rule_name === 'Slow Moving')
                                                    <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-hourglass-half mr-1"></i> Slow Moving</span>
                                                @else
                                                    <span class="badge badge-secondary px-2 py-1"><i class="fas fa-bed mr-1"></i> Dead Stock</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="font-weight-bold">{{ $rule->min_daily_avg }}</span>
                                                @if($rule->max_daily_avg)
                                                    <span class="text-muted"> to </span><span class="font-weight-bold">{{ $rule->max_daily_avg }}</span>
                                                @else
                                                    <span class="text-muted">+</span>
                                                @endif
                                                <small class="text-muted">units/day</small>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <input type="number" 
                                                           name="sales_rules[{{ $rule->id }}]" 
                                                           class="form-control text-center font-weight-bold" 
                                                           value="{{ $rule->target_cover_days }}" 
                                                           min="1" 
                                                           max="180" 
                                                           required>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text text-xs">Days</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-xs text-muted">
                                                @if($rule->rule_name === 'Highest Moving')
                                                    High turnover; keep 30 days buffer to prevent stockouts.
                                                @elseif($rule->rule_name === 'Fast Moving')
                                                    Fast seller; 25 days recommended.
                                                @elseif($rule->rule_name === 'Normal Moving')
                                                    Steady velocity; 20 days standard.
                                                @elseif($rule->rule_name === 'Slow Moving')
                                                    Low velocity; keep lean 15 days buffer.
                                                @else
                                                    Near zero sales; keep minimal 7 days to free working capital.
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <span class="text-muted text-xs">
                            <i class="fas fa-lightbulb text-warning mr-1"></i> Saving changes will automatically apply on the next live stock sync.
                        </span>
                        <button type="submit" class="btn btn-primary font-weight-bold shadow-sm">
                            <i class="fas fa-save mr-1"></i> Save Rules & Settings
                        </button>
                    </div>
                </div>
            </div>

            {{-- Stock Condition Rules & Formulas Reference --}}
            <div class="col-lg-5">
                <div class="card card-outline card-secondary shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-layer-group text-secondary mr-1"></i> 2. Stock Condition Statuses
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-12 mb-2">
                                <div class="p-3 rounded border border-danger bg-light d-flex align-items-center">
                                    <span class="badge badge-danger p-2 mr-3 font-weight-bold" style="min-width: 90px;">Critical</span>
                                    <div>
                                        <div class="font-weight-bold text-sm text-danger">0 &ndash; 3 Cover Days</div>
                                        <small class="text-muted">Stock is almost exhausted. Immediate Supplier PO or urgent Inter-Branch transfer needed.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-2">
                                <div class="p-3 rounded border border-warning bg-light d-flex align-items-center">
                                    <span class="badge badge-warning text-dark p-2 mr-3 font-weight-bold" style="min-width: 90px;">Low</span>
                                    <div>
                                        <div class="font-weight-bold text-sm text-warning text-dark">4 &ndash; 10 Cover Days</div>
                                        <small class="text-muted">Stock below comfortable operating levels. Included in Suggested Orders list.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-2">
                                <div class="p-3 rounded border border-success bg-light d-flex align-items-center">
                                    <span class="badge badge-success p-2 mr-3 font-weight-bold" style="min-width: 90px;">Healthy</span>
                                    <div>
                                        <div class="font-weight-bold text-sm text-success">11 &ndash; 30 Cover Days</div>
                                        <small class="text-muted">Ideal balance between customer availability and inventory holding cost.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-2">
                                <div class="p-3 rounded border border-info bg-light d-flex align-items-center">
                                    <span class="badge badge-info p-2 mr-3 font-weight-bold" style="min-width: 90px;">Overstock</span>
                                    <div>
                                        <div class="font-weight-bold text-sm text-info">&gt; 30 Cover Days</div>
                                        <small class="text-muted">Excess inventory available for Inter-Branch transfer to branches with Critical/Low stock.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Calculation Engine Reference --}}
                <div class="card card-outline card-info shadow-sm">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-calculator text-info mr-1"></i> 3. Calculation Formula (V1)
                        </h3>
                    </div>
                    <div class="card-body p-3 text-xs">
                        <div class="mb-2">
                            <strong class="text-dark">1. Daily Average Sales:</strong>
                            <div class="text-muted pl-2">&bull; Daily Avg Sales = Last 30 Days Sold Quantity &divide; 30</div>
                        </div>
                        <div class="mb-2">
                            <strong class="text-dark">2. Stock Cover Days:</strong>
                            <div class="text-muted pl-2">&bull; Cover Days = Current Branch Stock &divide; Daily Avg Sales</div>
                        </div>
                        <div class="mb-2">
                            <strong class="text-dark">3. Target Required Stock:</strong>
                            <div class="text-muted pl-2">&bull; Target Stock = Daily Avg Sales &times; Target Cover Days (from Sales Rules above)</div>
                        </div>
                        <div class="mb-2">
                            <strong class="text-dark">4. Suggested Purchase Qty:</strong>
                            <div class="text-muted pl-2">&bull; Suggested Qty = Max(0, Target Stock &minus; Current Stock)</div>
                            <div class="text-muted pl-2">&bull; If Daily Avg Sales = 0 &rarr; Suggested Qty = 0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop
