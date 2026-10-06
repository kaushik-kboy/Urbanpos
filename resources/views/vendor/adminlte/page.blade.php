@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/urbanpets-theme.css') }}?v={{ file_exists(public_path('css/urbanpets-theme.css')) ? filemtime(public_path('css/urbanpets-theme.css')) : '20260929' }}">
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('urbanpos_theme');
                if (theme === 'dark' || (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark-mode');
                }
                var density = localStorage.getItem('urbanpos_table_density');
                if (density === 'comfortable') {
                    document.documentElement.classList.add('density-comfortable');
                } else {
                    document.documentElement.classList.add('density-compact');
                }
            } catch(e) {}
        })();
    </script>
    <style>
        /* Global Compact Styles for all Item Tables across POS */
        .table-items-dense th {
            vertical-align: middle;
            padding: 4px 2px !important;
            font-size: 0.77rem;
            white-space: nowrap;
        }
        .table-items-dense td {
            vertical-align: middle;
            padding: 1px 1px !important;
        }
        .table-items-dense input.form-control-sm,
        .table-items-dense select.form-control-sm {
            font-size: 0.81rem;
            padding: 1px 3px !important;
            height: 27px !important;
            border-radius: 2px;
        }
        .table-items-dense .btn-xs {
            padding: 1px 4px !important;
            font-size: 0.75rem;
            line-height: 1.2;
        }
        /* Specific column sizes requested globally */
        .table-items-dense th[data-col-key="free"],
        .table-items-dense td[data-col-key="free"] {
            width: 45px !important;
            min-width: 45px !important;
        }
        .table-items-dense th[data-col-key="disc_pct"],
        .table-items-dense td[data-col-key="disc_pct"],
        .table-items-dense th[data-col-key="disc_percent"],
        .table-items-dense td[data-col-key="disc_percent"] {
            width: 48px !important;
            min-width: 48px !important;
        }
        .table-items-dense th[data-col-key="margin"],
        .table-items-dense td[data-col-key="margin"] {
            width: 50px !important;
            min-width: 50px !important;
        }
        .table-items-dense th[data-col-key="profit"],
        .table-items-dense td[data-col-key="profit"] {
            width: 50px !important;
            min-width: 50px !important;
        }
        .table-items-dense th[data-col-key="gst"],
        .table-items-dense td[data-col-key="gst"],
        .table-items-dense th[data-col-key="gst_percent"],
        .table-items-dense td[data-col-key="gst_percent"] {
            width: 45px !important;
            min-width: 45px !important;
        }
    </style>
    @stack('css')
    @yield('css')
    @if(request()->query('is_iframe') == '1')
    <style>
        .main-sidebar, .main-header, .main-footer, .pos-keyboard-bar, .up-latency-banner-wrap { display: none !important; }
        .content-wrapper { margin-left: 0 !important; margin-top: 0 !important; padding-top: 0 !important; }
        body { padding-bottom: 0 !important; background-color: #f4f6f9 !important; }
    </style>
    @endif
@stop

@section('classes_body', $layoutHelper->makeBodyClasses())

@section('body_data', $layoutHelper->makeBodyData())

@section('body')
    <div class="wrapper">

        {{-- Preloader Animation (fullscreen mode) --}}
        @if($preloaderHelper->isPreloaderEnabled())
            @include('adminlte::partials.common.preloader')
        @endif

        {{-- Top Navbar --}}
        @if($layoutHelper->isLayoutTopnavEnabled())
            @include('adminlte::partials.navbar.navbar-layout-topnav')
        @else
            @include('adminlte::partials.navbar.navbar')
        @endif

        @auth
            <x-pos-latency-banner />
        @endauth

        {{-- Left Main Sidebar --}}
        @if(!$layoutHelper->isLayoutTopnavEnabled())
            @include('adminlte::partials.sidebar.left-sidebar')
        @endif

        {{-- Content Wrapper --}}
        @empty($iFrameEnabled)
            @include('adminlte::partials.cwrapper.cwrapper-default')
        @else
            @include('adminlte::partials.cwrapper.cwrapper-iframe')
        @endempty

        {{-- Footer --}}
        @hasSection('footer')
            @include('adminlte::partials.footer.footer')
        @else
            <footer class="main-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Urban Pets</strong> &copy; {{ date('Y') }} POS / ERP System.
                    </div>
                    <div class="d-none d-sm-inline-block">
                        <span class="up-footer-pill">Urban Pets UI 2.0</span>
                    </div>
                </div>
            </footer>
        @endif

        {{-- Right Control Sidebar --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.sidebar.right-sidebar')
        @endif

        @auth
            @if(request()->route() && (Str::endsWith(request()->route()->getName(), '.create') || Str::endsWith(request()->route()->getName(), '.edit') || Str::contains(request()->route()->getName(), 'pos.terminal')))
                <x-pos-keyboard-bar />
            @endif
        @endauth

    </div>
    @auth
        <x-date-settings-modal />
        <x-command-palette />
    @endauth
@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')
    @auth
    <script>
        window.POS_HOTKEYS = @json(\App\Models\FunctionKeyMapping::getActiveMappings());
        window.APP_URL = (function() {
            var url = "{{ url('/') }}";
            if (window.location.protocol === 'https:' && url.indexOf('http:') === 0) {
                url = url.replace(/^http:/, 'https:');
            }
            try {
                var u = new URL(url);
                if (u.hostname !== window.location.hostname) {
                    return window.location.origin + u.pathname.replace(/\/+$/, '');
                }
            } catch (e) {}
            return url.replace(/\/+$/, '');
        })();
        window.exportTableToCSV = function(tableId, filename) {
            // If the table is paginated, download the full dataset from server preserving active filters
            if (document.querySelector('.pagination, .pagination-sm')) {
                var currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('export', 'csv');
                window.location.href = currentUrl.toString();
                return;
            }

            var table = document.getElementById(tableId);
            if (!table) {
                table = document.querySelector('.card-body table') || document.querySelector('table');
            }
            if (!table) {
                alert('No table data found to export.');
                return;
            }
            var csv = [];
            var rows = table.querySelectorAll('tr');
            for (var i = 0; i < rows.length; i++) {
                var row = [];
                var cols = rows[i].querySelectorAll('td, th');
                for (var j = 0; j < cols.length; j++) {
                    var col = cols[j];
                    var text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
                    if (j === cols.length - 1 && (text.toLowerCase() === 'action' || text.toLowerCase() === 'actions' || col.querySelector('.btn-group, .btn-xs, .btn-sm, a.btn'))) {
                        continue;
                    }
                    row.push('"' + text + '"');
                }
                if (row.length > 0) {
                    csv.push(row.join(','));
                }
            }
            var blob = new Blob(['\uFEFF' + csv.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            var cleanName = (filename || 'report').replace(/\.csv$/i, '') + '.csv';
            link.download = cleanName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };
    </script>
    @php
        $dynSvc = app(\App\Services\DynamicValidationService::class);
        $activeDynMod = $dynSvc->resolveModuleFromRequest();
        $activeDynConfigs = $activeDynMod ? $dynSvc->getConfigsForModule($activeDynMod)->values() : collect();
        $activeDynConfigsJson = $activeDynConfigs->isNotEmpty() ? $activeDynConfigs->map(function($c) {
            return [
                'field_name' => $c->field_name,
                'field_label' => $c->field_label,
                'field_type' => $c->field_type,
                'is_required' => (bool)$c->is_required,
                'is_readonly' => (bool)$c->is_readonly,
                'block_future_date' => (bool)$c->block_future_date,
                'is_unique' => (bool)$c->is_unique,
                'custom_error_message' => $c->custom_error_message,
            ];
        })->toJson() : '[]';
    @endphp
    @if($activeDynConfigs->isNotEmpty())
    <script>
        window.DYNAMIC_FORM_MODULE = {!! json_encode($activeDynMod) !!};
        window.DYNAMIC_FORM_CONFIGS = {!! $activeDynConfigsJson !!};
    </script>
    @endif
    <script src="{{ asset('js/pos-hotkeys.js') }}?v={{ file_exists(public_path('js/pos-hotkeys.js')) ? filemtime(public_path('js/pos-hotkeys.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/pos-latency-monitor.js') }}?v={{ file_exists(public_path('js/pos-latency-monitor.js')) ? filemtime(public_path('js/pos-latency-monitor.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/pos-telemetry.js') }}?v={{ file_exists(public_path('js/pos-telemetry.js')) ? filemtime(public_path('js/pos-telemetry.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/form-sequential-validator.js') }}?v={{ file_exists(public_path('js/form-sequential-validator.js')) ? filemtime(public_path('js/form-sequential-validator.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/urbanpos-theme-density.js') }}?v={{ file_exists(public_path('js/urbanpos-theme-density.js')) ? filemtime(public_path('js/urbanpos-theme-density.js')) : '1.0' }}"></script>
    @if(auth()->user()->time_out && !auth()->user()->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator']))
    <script>
        (function() {
            var timeOutStr = "{{ substr(auth()->user()->time_out, 0, 5) }}";
            function checkShiftTimeout() {
                var now = new Date();
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var currentTimeStr = hours + ':' + minutes;
                if (currentTimeStr >= timeOutStr && timeOutStr !== "00:00") {
                    alert('Your shift has ended (' + timeOutStr + '). You are being logged out automatically.');
                    window.location.href = "{{ route('home') }}";
                }
            }
            setInterval(checkShiftTimeout, 20000);
        })();
    </script>
    @endif
    @endauth
@stop
