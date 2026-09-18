@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    @stack('css')
    @yield('css')
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
        @endif

        {{-- Right Control Sidebar --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.sidebar.right-sidebar')
        @endif

        @auth
            <x-pos-keyboard-bar />
        @endauth

    </div>
@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')
    @auth
    <script>
        window.POS_HOTKEYS = @json(\App\Models\FunctionKeyMapping::getActiveMappings());
        window.APP_URL = "{{ url('/') }}";
        window.exportTableToCSV = function(tableId, filename) {
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
    <script src="{{ asset('js/pos-hotkeys.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/pos-latency-monitor.js') }}?v={{ time() }}"></script>
    @endauth
@stop
