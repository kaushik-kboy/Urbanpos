@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')

<nav class="main-header navbar
    {{ config('adminlte.classes_topnav_nav', 'navbar-expand') }}
    {{ config('adminlte.classes_topnav', 'navbar-white navbar-light') }}">

    {{-- Navbar left links --}}
    <ul class="navbar-nav">
        {{-- Left sidebar toggler link --}}
        @include('adminlte::partials.navbar.menu-item-left-sidebar-toggler')

        {{-- Configured left links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-left'), 'item')

        {{-- Custom left links --}}
        @yield('content_top_nav_left')
    </ul>

    {{-- Navbar right links --}}
    <ul class="navbar-nav ml-auto align-items-center">
        {{-- Custom right links --}}
        @yield('content_top_nav_right')

        {{-- Single Global Branch Selector --}}
        @auth
            @php
                $user = auth()->user();
                $activeBranchId = session('active_branch_id');
                $branches = \App\Models\Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
            @endphp
            <li class="nav-item d-flex align-items-center mr-2" id="top-navbar-branch-wrapper">
                <span class="badge badge-primary px-2 py-1 mr-1 font-weight-bold" style="font-size: 0.82rem;">
                    <i class="fas fa-store-alt mr-1"></i> Branch:
                </span>
                @if ($user->branch_id !== null)
                    <span class="badge badge-light border px-2 py-1 font-weight-bold text-dark">
                        {{ $user->branch?->name ?? 'Assigned Branch' }}
                    </span>
                @else
                    <select id="top-navbar-branch-select" class="form-control form-control-sm font-weight-bold text-dark border-primary" style="width: auto; height: calc(1.5em + .5rem + 2px); min-width: 170px;">
                        <option value="all" {{ empty($activeBranchId) || $activeBranchId === 'all' ? 'selected' : '' }}>All Branches (Consolidated)</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" {{ (string)$activeBranchId === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
            </li>
        @endauth

        {{-- Configured right links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-right'), 'item')

        {{-- User menu link --}}
        @if(Auth::user())
            @if(config('adminlte.usermenu_enabled'))
                @include('adminlte::partials.navbar.menu-item-dropdown-user-menu')
            @else
                @include('adminlte::partials.navbar.menu-item-logout-link')
            @endif
        @endif

        {{-- Right sidebar toggler link --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.navbar.menu-item-right-sidebar-toggler')
        @endif
    </ul>

</nav>
