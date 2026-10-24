@extends('adminlte::page')

@section('title', 'Module & Menu Integration Manager')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-toggle-on text-primary mr-2"></i> Module & Menu Integration
            </h1>
            <p class="text-muted small mb-0">
                कन्फ्यूजन खत्म! जो मेनू और रिपोर्ट्स आपको या आपके स्टाफ को देखनी हैं, सिर्फ वही ऑन रखें।
            </p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center">
            <button onclick="window.location.reload();" class="btn btn-outline-secondary mr-2 shadow-sm btn-sm">
                <i class="fas fa-sync-alt mr-1"></i> Refresh Sidebar
            </button>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-info shadow-sm btn-sm">
                <i class="fas fa-chart-pie mr-1"></i> Reports Center
            </a>
        </div>
    </div>
@stop

@section('content')
    {{-- ROLE SELECTOR HEADER --}}
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span class="font-weight-bold mr-3 text-secondary text-uppercase small">
                        <i class="fas fa-user-shield mr-1 text-primary"></i> लक्ष्य भूमिका (Target Role):
                    </span>
                    <div class="btn-group btn-group-toggle flex-wrap" data-toggle="buttons">
                        @foreach ($roles as $role)
                            <a href="{{ route('tools.module-integrations.index', ['role_id' => $role->id]) }}" 
                               class="btn btn-sm {{ $selectedRoleId === $role->id ? 'btn-primary font-weight-bold active' : 'btn-outline-secondary' }} px-3 py-1">
                                <i class="fas fa-id-badge mr-1"></i> {{ $role->name }}
                                <span class="badge badge-light ml-1">{{ $role->users_count }}</span>
                            </a>
                        @endforeach
                        <a href="{{ route('tools.module-integrations.index', ['role_id' => 'global']) }}" 
                           class="btn btn-sm {{ $selectedRoleId === null ? 'btn-primary font-weight-bold active' : 'btn-outline-secondary' }} px-3 py-1">
                            <i class="fas fa-globe mr-1"></i> Global (सभी के लिए)
                        </a>
                    </div>
                </div>

                {{-- Live Search Input --}}
                <div class="search-box" style="min-width: 280px;">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light border-right-0"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" id="menuSearchInput" class="form-control border-left-0" placeholder="सर्च मेनू या रिपोर्ट (उदा. GST, Daily, Sale)...">
                        <div class="input-group-append">
                            <button class="btn btn-light border border-left-0 text-muted" type="button" id="clearSearchBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Target Role Description Alert --}}
            <div class="mt-3 pt-2 border-top d-flex flex-wrap justify-content-between align-items-center small text-muted">
                <div>
                    <i class="fas fa-info-circle text-info mr-1"></i>
                    वर्तमान कॉन्फ़िगरेशन: 
                    <strong class="text-dark">{{ $selectedRole ? $selectedRole->name . ' Role' : 'Global Default (दुकान भर के लिए)' }}</strong>
                    | एक्टिव मेनू: <span class="badge badge-success">{{ $enabledMainMenuCount }} / {{ $totalMainMenuCount }}</span>
                    | एक्टिव रिपोर्ट्स: <span class="badge badge-info">{{ $enabledReportCount }} / {{ $totalReportCount }}</span>
                </div>
                <div>
                    <button type="button" class="btn btn-xs btn-outline-danger" data-toggle="modal" data-target="#resetConfirmModal">
                        <i class="fas fa-undo mr-1"></i> Reset Role to Default
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN NAVIGATION TABS --}}
    <ul class="nav nav-pills mb-3" id="integrationTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active font-weight-bold px-4 py-2 shadow-sm" id="menus-tab" data-toggle="pill" href="#tab-menus" role="tab">
                <i class="fas fa-th-list mr-2"></i> Main Menus & Submenus 
                <span class="badge badge-pill badge-light ml-1">{{ $enabledMainMenuCount }}</span>
            </a>
        </li>
        <li class="nav-item ml-2">
            <a class="nav-link font-weight-bold px-4 py-2 shadow-sm" id="reports-tab" data-toggle="pill" href="#tab-reports" role="tab">
                <i class="fas fa-chart-line mr-2"></i> Reports Visibility 
                <span class="badge badge-pill badge-light ml-1">{{ $enabledReportCount }}</span>
            </a>
        </li>
        <li class="nav-item ml-2">
            <a class="nav-link font-weight-bold px-4 py-2 shadow-sm text-dark bg-white border" id="presets-tab" data-toggle="pill" href="#tab-presets" role="tab">
                <i class="fas fa-magic text-warning mr-2"></i> 1-Click Quick Presets
            </a>
        </li>
    </ul>

    {{-- TAB CONTENTS --}}
    <div class="tab-content" id="integrationTabsContent">
        
        {{-- ================= TAB 1: MAIN MENUS ================= --}}
        <div class="tab-pane fade show active" id="tab-menus" role="tabpanel">
            <div class="row">
                @foreach ($mainMenus as $catKey => $category)
                    @php
                        $catItems = $category['items'];
                        $allCatKeys = array_keys($catItems);
                    @endphp
                    <div class="col-lg-6 mb-4 menu-category-card" data-category="{{ strtolower($category['label']) }}">
                        <div class="card card-outline card-primary shadow-sm h-100">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <h3 class="card-title font-weight-bold text-dark mb-0">
                                    <i class="{{ $category['icon'] }} text-primary mr-2"></i> {{ $category['label'] }}
                                </h3>
                                <div class="card-tools d-flex align-items-center">
                                    <button type="button" class="btn btn-xs btn-outline-success mr-1 bulk-toggle-btn" 
                                            data-keys='@json($allCatKeys)' data-visible="1" title="Enable All">
                                        All ON
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger bulk-toggle-btn" 
                                            data-keys='@json($allCatKeys)' data-visible="0" title="Disable All">
                                        All OFF
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    @foreach ($catItems as $key => $item)
                                        @php
                                            $isChecked = array_key_exists($key, $visibilityMap) 
                                                ? (bool)$visibilityMap[$key] 
                                                : (array_key_exists($key, $globalMap) ? (bool)$globalMap[$key] : true);
                                        @endphp
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 menu-item-row"
                                            data-name="{{ strtolower($item['label']) }}" data-url="{{ strtolower($item['url']) }}">
                                            <div>
                                                <span class="font-weight-600 text-dark">{{ $item['label'] }}</span>
                                                <div class="small text-muted">
                                                    <code>/{{ $item['url'] }}</code>
                                                    @if(isset($item['permission']))
                                                        <span class="badge badge-light border ml-1 text-muted">{{ $item['permission'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="custom-control custom-switch custom-switch-lg">
                                                <input type="checkbox" class="custom-control-input visibility-switch" 
                                                       id="switch_{{ str_replace(['.', '/', '-'], '_', $key) }}" 
                                                       data-key="{{ $key }}" 
                                                       {{ $isChecked ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="switch_{{ str_replace(['.', '/', '-'], '_', $key) }}"></label>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ================= TAB 2: REPORTS HUB ================= --}}
        <div class="tab-pane fade" id="tab-reports" role="tabpanel">
            <div class="alert alert-info py-2 px-3 mb-3 shadow-sm d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>रिपोर्ट्स विजिबिलिटी:</strong> जो रिपोर्ट आप OFF करेंगे, वह एडमिन के <strong>साइडबार</strong> और <strong>Reports Center</strong> दोनों जगह से छुप जाएगी।
                </div>
                <div>
                    <span class="badge badge-light font-weight-normal">कुल 186 रिपोर्ट्स कैटलॉग</span>
                </div>
            </div>

            <div class="row">
                @foreach ($reportsGrouped as $groupName => $group)
                    @php
                        $groupItems = $group['items'];
                        $allGroupKeys = array_keys($groupItems);
                    @endphp
                    <div class="col-lg-6 mb-4 menu-category-card" data-category="{{ strtolower($group['label']) }}">
                        <div class="card card-outline card-info shadow-sm h-100">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                <h3 class="card-title font-weight-bold text-dark mb-0">
                                    <i class="fas fa-chart-pie text-info mr-2"></i> {{ $group['label'] }}
                                    <span class="badge badge-secondary ml-1 small">{{ count($groupItems) }}</span>
                                </h3>
                                <div class="card-tools d-flex align-items-center">
                                    <button type="button" class="btn btn-xs btn-outline-success mr-1 bulk-toggle-btn" 
                                            data-keys='@json($allGroupKeys)' data-visible="1" title="Enable All">
                                        All ON
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger bulk-toggle-btn" 
                                            data-keys='@json($allGroupKeys)' data-visible="0" title="Disable All">
                                        All OFF
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                                <ul class="list-group list-group-flush">
                                    @foreach ($groupItems as $key => $report)
                                        @php
                                            $isChecked = array_key_exists($key, $visibilityMap) 
                                                ? (bool)$visibilityMap[$key] 
                                                : (array_key_exists($key, $globalMap) ? (bool)$globalMap[$key] : true);
                                        @endphp
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 menu-item-row"
                                            data-name="{{ strtolower($report['label']) }}" data-url="{{ strtolower($report['url']) }}">
                                            <div class="mr-2">
                                                <span class="font-weight-600 text-dark">{{ $report['label'] }}</span>
                                                <div class="small text-muted">
                                                    <code>/{{ $report['url'] }}</code>
                                                    @if(!empty($report['has_export']))
                                                        <span class="badge badge-light border text-muted"><i class="fas fa-file-excel mr-1"></i>Excel</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="custom-control custom-switch custom-switch-lg">
                                                <input type="checkbox" class="custom-control-input visibility-switch" 
                                                       id="switch_{{ str_replace(['.', '/', '-'], '_', $key) }}" 
                                                       data-key="{{ $key }}" 
                                                       {{ $isChecked ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="switch_{{ str_replace(['.', '/', '-'], '_', $key) }}"></label>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ================= TAB 3: QUICK PRESETS ================= --}}
        <div class="tab-pane fade" id="tab-presets" role="tabpanel">
            <div class="row">
                @foreach ($presets as $pKey => $preset)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow-sm border-0 bg-white">
                            <div class="card-body d-flex flex-column justify-content-between p-4">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="font-weight-bold text-dark mb-0">{{ $preset['name'] }}</h4>
                                        <span class="badge {{ $preset['badge'] }} px-2 py-1">Preset</span>
                                    </div>
                                    <p class="text-muted small mb-4">
                                        {{ $preset['description'] }}
                                    </p>
                                </div>
                                <div>
                                    <form action="{{ route('tools.module-integrations.preset') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="role_id" value="{{ $selectedRoleId ?? 'global' }}">
                                        <input type="hidden" name="preset" value="{{ $pKey }}">
                                        <button type="submit" class="btn btn-block btn-outline-primary font-weight-bold shadow-sm"
                                                onclick="return confirm('क्या आप {{ $selectedRole ? $selectedRole->name : 'Global' }} पर यह प्रीसेट लागू करना चाहते हैं?');">
                                            <i class="fas fa-check-circle mr-1"></i> Apply This Preset
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- RESET CONFIRMATION MODAL --}}
    <div class="modal fade" id="resetConfirmModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-exclamation-triangle mr-2"></i> Reset Visibility Confirmation</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-2">
                        क्या आप वाकई <strong>{{ $selectedRole ? $selectedRole->name : 'Global' }}</strong> के सभी मेनू और रिपोर्ट्स को डिफ़ॉल्ट (सारे चालू) पर रीसेट करना चाहते हैं?
                    </p>
                    <p class="text-muted small mb-0">
                        इससे सारे बंद मेनू दोबारा दिखने लगेंगे।
                    </p>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">रद्द करें (Cancel)</button>
                    <form action="{{ route('tools.module-integrations.reset') }}" method="POST">
                        @csrf
                        <input type="hidden" name="role_id" value="{{ $selectedRoleId ?? 'global' }}">
                        <button type="submit" class="btn btn-danger btn-sm font-weight-bold">
                            <i class="fas fa-undo mr-1"></i> हां, रीसेट करें (Reset)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- TOAST NOTIFICATION CONTAINER --}}
    <div id="toastContainer" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

@stop

@section('css')
<style>
    .font-weight-600 { font-weight: 600; }
    .custom-switch-lg .custom-control-label::before {
        height: 1.5rem;
        width: 2.75rem;
        border-radius: 3rem;
        cursor: pointer;
    }
    .custom-switch-lg .custom-control-label::after {
        width: calc(1.5rem - 4px);
        height: calc(1.5rem - 4px);
        border-radius: 3rem;
        background-color: #fff;
        cursor: pointer;
    }
    .custom-switch-lg .custom-control-input:checked ~ .custom-control-label::after {
        transform: translateX(1.25rem);
    }
    .menu-item-row:hover {
        background-color: #f8fafc;
    }
</style>
@stop

@section('js')
<script>
$(document).ready(function() {
    const currentRoleId = @json($selectedRoleId ?? 'global');
    const toggleUrl = @json(route('tools.module-integrations.toggle'));
    const bulkToggleUrl = @json(route('tools.module-integrations.bulk'));
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';

    function showToast(message, type = 'success') {
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const id = 'toast_' + Date.now();
        const html = `
            <div id="${id}" class="toast align-items-center text-white ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2500">
                <div class="d-flex p-2">
                    <div class="toast-body d-flex align-items-center">
                        <i class="fas ${icon} mr-2"></i> <span>${message}</span>
                    </div>
                </div>
            </div>
        `;
        $('#toastContainer').append(html);
        $('#' + id).toast('show');
        setTimeout(() => $('#' + id).remove(), 3000);
    }

    // Single item toggle
    $('.visibility-switch').on('change', function() {
        const switchInput = $(this);
        const menuKey = switchInput.data('key');
        const isVisible = switchInput.is(':checked') ? 1 : 0;

        $.ajax({
            url: toggleUrl,
            method: 'POST',
            data: {
                _token: csrfToken,
                role_id: currentRoleId,
                menu_key: menuKey,
                is_visible: isVisible
            },
            success: function(res) {
                showToast(isVisible ? 'मेनू चालू कर दिया गया है' : 'मेनू बंद (छिपा) दिया गया है', 'success');
            },
            error: function() {
                switchInput.prop('checked', !isVisible);
                showToast('सेटिंग अपडेट करने में त्रुटि हुई', 'danger');
            }
        });
    });

    // Bulk category toggle
    $('.bulk-toggle-btn').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const keys = btn.data('keys');
        const isVisible = parseInt(btn.data('visible'));

        btn.prop('disabled', true);

        $.ajax({
            url: bulkToggleUrl,
            method: 'POST',
            data: {
                _token: csrfToken,
                role_id: currentRoleId,
                keys: keys,
                is_visible: isVisible
            },
            success: function(res) {
                btn.prop('disabled', false);
                keys.forEach(key => {
                    const selector = '#switch_' + key.replace(/[.\/\-]/g, '_');
                    $(selector).prop('checked', isVisible === 1);
                });
                showToast(res.message || 'कैटेगरी अपडेट हो गई है', 'success');
            },
            error: function() {
                btn.prop('disabled', false);
                showToast('कैटेगरी अपडेट करने में त्रुटि हुई', 'danger');
            }
        });
    });

    // Realtime Search Filter
    $('#menuSearchInput').on('keyup', function() {
        const query = $(this).val().toLowerCase().trim();
        if (!query) {
            $('.menu-item-row').show();
            $('.menu-category-card').show();
            return;
        }

        $('.menu-category-card').each(function() {
            let catMatches = false;
            $(this).find('.menu-item-row').each(function() {
                const name = $(this).data('name') || '';
                const url = $(this).data('url') || '';
                if (name.includes(query) || url.includes(query)) {
                    $(this).show();
                    catMatches = true;
                } else {
                    $(this).hide();
                }
            });

            if (catMatches) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#clearSearchBtn').on('click', function() {
        $('#menuSearchInput').val('').trigger('keyup');
    });
});
</script>
@stop
