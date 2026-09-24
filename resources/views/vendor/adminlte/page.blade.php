@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/urbanpets-theme.css') }}?v=20260921_2">
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
            <x-date-settings-modal />
        @endauth

    </div>
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
    <script src="{{ asset('js/pos-hotkeys.js') }}?v={{ file_exists(public_path('js/pos-hotkeys.js')) ? filemtime(public_path('js/pos-hotkeys.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/pos-latency-monitor.js') }}?v={{ file_exists(public_path('js/pos-latency-monitor.js')) ? filemtime(public_path('js/pos-latency-monitor.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/pos-telemetry.js') }}?v={{ file_exists(public_path('js/pos-telemetry.js')) ? filemtime(public_path('js/pos-telemetry.js')) : '1.0' }}"></script>
    <script src="{{ asset('js/form-sequential-validator.js') }}?v={{ file_exists(public_path('js/form-sequential-validator.js')) ? filemtime(public_path('js/form-sequential-validator.js')) : '1.0' }}"></script>
    @endauth
@stop
