@extends('adminlte::page')

@section('title', 'Replenishment Rules & Settings')

@push('css')
<style>
/* Modern 2026 High-Contrast Settings Design */
body, .content-wrapper {
    color: #0f172a !important;
}

.settings-heading {
    color: #0f172a !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
    font-size: 1.6rem;
}
.settings-subheading {
    color: #1e293b !important;
    font-weight: 600;
    font-size: 0.85rem;
}

.modern-settings-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.modern-settings-header {
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-settings-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.modern-table-settings {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}
.modern-table-settings thead th {
    background: #0f172a !important;
    color: #ffffff !important;
    font-size: 0.72rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    text-transform: uppercase !important;
    padding: 0.85rem 1rem !important;
    border: none !important;
}
.modern-table-settings tbody td {
    padding: 0.85rem 1rem !important;
    vertical-align: middle !important;
    border-top: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    font-size: 0.85rem;
}

.btn-action-dark {
    background: #0f172a;
    border: 1.5px solid #0f172a;
    color: #ffffff !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-action-dark:hover {
    background: #1e293b;
    border-color: #1e293b;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
}
.btn-action-outline {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    color: #0f172a !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-action-outline:hover {
    background: #0f172a;
    color: #ffffff !important;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    font-size: 0.76rem;
    font-weight: 800;
    border-radius: 6px;
    letter-spacing: 0.02em;
}
.status-pill.critical { background: #fee2e2; color: #991b1b; border: 1.5px solid #f87171; }
.status-pill.low { background: #fef3c7; color: #92400e; border: 1.5px solid #fbbf24; }
.status-pill.healthy { background: #dcfce7; color: #166534; border: 1.5px solid #4ade80; }
.status-pill.overstock { background: #e0f2fe; color: #075985; border: 1.5px solid #38bdf8; }

.modern-input-num {
    border-radius: 8px !important;
    border: 1.5px solid #0f172a !important;
    color: #0f172a !important;
    font-weight: 800 !important;
    font-size: 0.95rem !important;
}
</style>
@endpush

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="settings-heading m-0">
                <i class="fas fa-sliders-h text-primary mr-2"></i> Replenishment Rules & Settings
            </h1>
            <div class="settings-subheading mt-1">
                Configure Sales Movement thresholds, Target Cover Days buffer, and Stock Condition logic
            </div>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-action-outline shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-dark alert-dismissible fade show border-dark shadow-sm mb-3 font-weight-bold" style="background:#0f172a; color:#ffffff; border-radius:10px;" role="alert">
            <i class="fas fa-check-circle mr-1 text-success"></i> {{ session('success') }}
            <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <form action="{{ route('inventory.replenishment.settings.update') }}" method="POST">
        @csrf
        <div class="row">
            {{-- Sales Movement Rules Configuration --}}
            <div class="col-lg-7">
                <div class="modern-settings-card">
                    <div class="modern-settings-header">
                        <h3 class="modern-settings-title">
                            <i class="fas fa-chart-line text-primary mr-2"></i> 1. Sales Movement Rules (Target Buffer Days)
                        </h3>
                        <span class="badge badge-dark px-2 py-1 font-weight-bold text-xs" style="border-radius: 6px;">Configurable</span>
                    </div>
                    <div class="p-3 bg-white border-bottom font-weight-bold text-dark" style="font-size: 0.82rem; background: #f8fafc !important;">
                        <i class="fas fa-info-circle mr-1 text-primary"></i>
                        The system classifies every product based on its 30-day velocity. You can adjust the <strong>Target Cover Days</strong> buffer maintained for each category.
                    </div>
                    <div class="p-0 table-responsive">
                        <table class="modern-table-settings">
                            <thead>
                                <tr>
                                    <th>Sales Type</th>
                                    <th>Daily Avg Velocity</th>
                                    <th style="width: 175px;">Target Cover Days</th>
                                    <th>Buffer Guidance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salesRules as $rule)
                                    <tr>
                                        <td>
                                            @if($rule->rule_name === 'Highest Moving')
                                                <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 6px;"><i class="fas fa-fire mr-1"></i> Highest Moving</span>
                                            @elseif($rule->rule_name === 'Fast Moving')
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold" style="border-radius: 6px;"><i class="fas fa-tachometer-alt mr-1"></i> Fast Moving</span>
                                            @elseif($rule->rule_name === 'Normal Moving')
                                                <span class="badge badge-info px-2 py-1 font-weight-bold" style="border-radius: 6px;"><i class="fas fa-boxes mr-1"></i> Normal Moving</span>
                                            @elseif($rule->rule_name === 'Slow Moving')
                                                <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold" style="border-radius: 6px;"><i class="fas fa-hourglass-half mr-1"></i> Slow Moving</span>
                                            @else
                                                <span class="badge badge-dark px-2 py-1 font-weight-bold" style="border-radius: 6px;"><i class="fas fa-bed mr-1"></i> Dead Stock</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center" style="max-width: 220px;">
                                                <input type="number" 
                                                       step="0.1" 
                                                       min="0" 
                                                       name="sales_rules[{{ $rule->id }}][min_daily_avg]" 
                                                       class="form-control form-control-sm text-center modern-input-num font-weight-bold" 
                                                       value="{{ $rule->min_daily_avg }}" 
                                                       style="width: 70px; border-radius: 6px;" 
                                                       required>
                                                <span class="mx-1 font-weight-bold text-dark">&ndash;</span>
                                                @if($rule->rule_name === 'Highest Moving')
                                                    <input type="text" 
                                                           class="form-control form-control-sm text-center font-weight-bold bg-light" 
                                                           value="&infin;" 
                                                           readonly 
                                                           style="width: 65px; border-radius: 6px;">
                                                @else
                                                    <input type="number" 
                                                           step="0.1" 
                                                           min="0" 
                                                           name="sales_rules[{{ $rule->id }}][max_daily_avg]" 
                                                           class="form-control form-control-sm text-center modern-input-num font-weight-bold" 
                                                           value="{{ $rule->max_daily_avg }}" 
                                                           style="width: 70px; border-radius: 6px;">
                                                @endif
                                                <span class="font-weight-bold text-dark text-xs ml-1 text-nowrap">u/day</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" 
                                                       name="sales_rules[{{ $rule->id }}][target_cover_days]" 
                                                       class="form-control text-center modern-input-num" 
                                                       value="{{ $rule->target_cover_days }}" 
                                                       min="1" 
                                                       max="180" 
                                                       required>
                                                <div class="input-group-append">
                                                     <span class="input-group-text font-weight-bold bg-dark text-white text-xs" style="border-radius: 0 8px 8px 0;">Days</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="font-weight-bold text-dark" style="font-size: 0.78rem;">
                                            @if($rule->rule_name === 'Highest Moving')
                                                Top selling SKU; keep 30 days buffer to prevent stockouts.
                                            @elseif($rule->rule_name === 'Fast Moving')
                                                High demand; 25 days recommended.
                                            @elseif($rule->rule_name === 'Normal Moving')
                                                Steady velocity; 20 days standard.
                                            @elseif($rule->rule_name === 'Slow Moving')
                                                Low velocity; keep lean 15 days buffer.
                                            @else
                                                Near zero sales; keep minimal 7 days to preserve working capital.
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-dark text-xs">
                            <i class="fas fa-bolt text-warning mr-1"></i> Updates take effect immediately and apply on the next live stock sync.
                        </span>
                        <button type="submit" class="btn btn-action-dark shadow-sm">
                            <i class="fas fa-save mr-1 text-success"></i> Save Rules & Settings
                        </button>
                    </div>
                </div>
            </div>

            {{-- Stock Condition Rules Configuration --}}
            <div class="col-lg-5">
                <div class="modern-settings-card mb-4">
                    <div class="modern-settings-header">
                        <h3 class="modern-settings-title">
                            <i class="fas fa-layer-group text-primary mr-2"></i> 2. Stock Condition Statuses
                        </h3>
                        <span class="badge badge-dark px-2 py-1 font-weight-bold text-xs" style="border-radius: 6px;">Configurable</span>
                    </div>
                    <div class="p-3 bg-white border-bottom font-weight-bold text-dark" style="font-size: 0.82rem; background: #f8fafc !important;">
                        <i class="fas fa-info-circle mr-1 text-primary"></i>
                        Adjust the cover day thresholds to categorize your branch inventory into Critical, Low, Healthy, or Overstock.
                    </div>
                    <div class="p-3">
                        @php
                            $stockMap = $stockRules->keyBy('stock_type');
                            $critRule = $stockMap['Critical'] ?? null;
                            $lowRule = $stockMap['Low'] ?? null;
                            $healthyRule = $stockMap['Healthy'] ?? null;
                            $overRule = $stockMap['Overstock'] ?? null;
                        @endphp
                        <div class="p-3 rounded border border-danger mb-3 bg-white" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="status-pill critical">Critical Stock</span>
                                <div class="d-flex align-items-center">
                                    <span class="font-weight-bold mr-2 text-xs">0 &ndash;</span>
                                    <input type="number" name="stock_rules[critical_max]" id="inpCritMax" class="form-control form-control-sm text-center font-weight-bold modern-input-num" style="width: 75px; border-radius: 6px;" value="{{ $critRule->max_cover_days ?? 3 }}" min="1" max="60" required>
                                    <span class="font-weight-bold ml-1 text-xs">Days</span>
                                </div>
                            </div>
                            <div class="font-weight-bold text-dark" style="font-size: 0.78rem;">
                                Stock almost depleted. High stockout risk. Immediate supplier PO or emergency inter-branch transfer required.
                            </div>
                        </div>

                        <div class="p-3 rounded border border-warning mb-3 bg-white" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="status-pill low">Low Stock</span>
                                <div class="d-flex align-items-center">
                                    <span class="font-weight-bold mr-1 text-xs" id="lblLowMin">{{ ($critRule->max_cover_days ?? 3) + 1 }} &ndash;</span>
                                    <input type="number" name="stock_rules[low_max]" id="inpLowMax" class="form-control form-control-sm text-center font-weight-bold modern-input-num" style="width: 75px; border-radius: 6px;" value="{{ $lowRule->max_cover_days ?? 10 }}" min="2" max="120" required>
                                    <span class="font-weight-bold ml-1 text-xs">Days</span>
                                </div>
                            </div>
                            <div class="font-weight-bold text-dark" style="font-size: 0.78rem;">
                                Approaching minimum threshold. Added to suggested reorder list so goods arrive before stock depletion.
                            </div>
                        </div>

                        <div class="p-3 rounded border border-success mb-3 bg-white" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="status-pill healthy">Healthy Stock</span>
                                <div class="d-flex align-items-center">
                                    <span class="font-weight-bold mr-1 text-xs" id="lblHealthyMin">{{ ($lowRule->max_cover_days ?? 10) + 1 }} &ndash;</span>
                                    <input type="number" name="stock_rules[healthy_max]" id="inpHealthyMax" class="form-control form-control-sm text-center font-weight-bold modern-input-num" style="width: 75px; border-radius: 6px;" value="{{ $healthyRule->max_cover_days ?? 30 }}" min="3" max="180" required>
                                    <span class="font-weight-bold ml-1 text-xs">Days</span>
                                </div>
                            </div>
                            <div class="font-weight-bold text-dark" style="font-size: 0.78rem;">
                                Optimum inventory level. Well balanced between shelf availability and capital efficiency.
                            </div>
                        </div>

                        <div class="p-3 rounded border border-primary bg-white" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="status-pill overstock">Overstock</span>
                                <strong class="text-primary font-weight-bold" style="font-size: 0.95rem;" id="lblOverstock">&gt; {{ $healthyRule->max_cover_days ?? 30 }} Days</strong>
                            </div>
                            <div class="font-weight-bold text-dark" style="font-size: 0.78rem;">
                                Surplus inventory available. System prioritizes these stores as sources for Inter-Branch transfers.
                            </div>
                        </div>
                    </div>
                    <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                        <span class="font-weight-bold text-dark text-xs">
                            <i class="fas fa-check-circle text-success mr-1"></i> Continuous zero-gap coverage automatically maintained.
                        </span>
                        <button type="submit" class="btn btn-action-dark shadow-sm">
                            <i class="fas fa-save mr-1 text-success"></i> Save Rules & Settings
                        </button>
                    </div>
                </div>

                {{-- Calculation Engine Reference --}}
                <div class="modern-settings-card">
                    <div class="modern-settings-header">
                        <h3 class="modern-settings-title">
                            <i class="fas fa-calculator text-primary mr-2"></i> 3. Calculation Logic (V1)
                        </h3>
                    </div>
                    <div class="p-3 font-weight-bold text-dark" style="font-size: 0.82rem; line-height: 1.6;">
                        <div class="mb-3 p-2 rounded bg-light border">
                            <div class="text-primary font-weight-bold mb-1">1. Daily Average Sales:</div>
                            <div class="text-dark">&bull; <strong>Daily Avg Sales</strong> = Last 30 Days Sold Quantity &divide; 30</div>
                        </div>
                        <div class="mb-3 p-2 rounded bg-light border">
                            <div class="text-primary font-weight-bold mb-1">2. Stock Cover Days:</div>
                            <div class="text-dark">&bull; <strong>Cover Days</strong> = Current Branch Stock &divide; Daily Avg Sales</div>
                        </div>
                        <div class="mb-3 p-2 rounded bg-light border">
                            <div class="text-primary font-weight-bold mb-1">3. Target Required Stock:</div>
                            <div class="text-dark">&bull; <strong>Target Stock</strong> = Daily Avg Sales &times; Target Cover Days</div>
                        </div>
                        <div class="p-2 rounded bg-light border">
                            <div class="text-primary font-weight-bold mb-1">4. Suggested Purchase Qty:</div>
                            <div class="text-dark">&bull; <strong>Suggested Qty</strong> = Max(0, Target Stock &minus; Current Stock)</div>
                            <div class="text-dark">&bull; If Daily Avg Sales = 0 &rarr; Suggested Qty = 0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@push('js')
<script>
$(function() {
    function updateStockBounds() {
        const crit = parseInt($('#inpCritMax').val(), 10) || 3;
        const low = parseInt($('#inpLowMax').val(), 10) || 10;
        const healthy = parseInt($('#inpHealthyMax').val(), 10) || 30;

        $('#lblLowMin').text((crit + 1) + ' –');
        $('#inpLowMax').attr('min', crit + 1);

        $('#lblHealthyMin').text((low + 1) + ' –');
        $('#inpHealthyMax').attr('min', low + 1);

        $('#lblOverstock').text('> ' + healthy + ' Days');
    }

    $('#inpCritMax, #inpLowMax, #inpHealthyMax').on('input change', updateStockBounds);
});
</script>
@endpush
