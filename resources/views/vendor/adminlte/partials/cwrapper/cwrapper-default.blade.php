@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@if($layoutHelper->isLayoutTopnavEnabled())
    @php( $def_container_class = 'container' )
@else
    @php( $def_container_class = 'container-fluid' )
@endif

{{-- Default Content Wrapper --}}
<div class="{{ $layoutHelper->makeContentWrapperClasses() }}">

    {{-- Preloader Animation (cwrapper mode) --}}
    @if($preloaderHelper->isPreloaderEnabled('cwrapper'))
        @include('adminlte::partials.common.preloader')
    @endif

    {{-- Content Header (Rendered on all pages for consistent top-right Back button) --}}
    <div class="content-header py-2">
        <div class="{{ config('adminlte.classes_content_header') ?: $def_container_class }} d-flex justify-content-between align-items-center">
            <div>
                @hasSection('content_header')
                    @yield('content_header')
                @endif
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-dark font-weight-bold shadow-sm" onclick="if(window.history.length > 1){ window.history.back(); } else { window.location.href='{{ url('/') }}'; }" title="Go Back">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </button>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="content">
        <div class="{{ config('adminlte.classes_content') ?: $def_container_class }}">
            @stack('content')
            @yield('content')
        </div>
    </div>

</div>
