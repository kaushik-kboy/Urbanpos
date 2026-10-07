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

        {{-- Global Command Palette Trigger Button (Ctrl+K) --}}
        @auth
        <li class="nav-item ml-2 d-none d-md-flex align-items-center">
            <button type="button" id="btn-open-command-palette" class="btn btn-sm btn-light border up-spotlight-btn d-flex align-items-center text-muted px-2 py-1 shadow-xs" title="Open Spotlight Command Palette (Ctrl+K)">
                <i class="fas fa-search text-primary mr-2" style="font-size: 0.85rem;"></i>
                <span class="d-none d-lg-inline mr-3" style="font-size: 0.82rem;">Quick Search / Commands...</span>
                <kbd class="up-kbd-chip">Ctrl K</kbd>
            </button>
        </li>
        @endauth
    </ul>

    {{-- Navbar right links --}}
    <ul class="navbar-nav ml-auto align-items-center">
        {{-- Custom right links --}}
        @yield('content_top_nav_right')

        {{-- Single Global Branch Selector --}}
        @auth
            @php
                $user = auth()->user();
                $allBranches = \App\Models\Branch::where('status', true)->orderBy('id')->get();
                if ($user->branch_id !== null) {
                    $activeBranchId = (int) $user->branch_id;
                    session(['active_branch_id' => $activeBranchId]);
                } else {
                    $reqBranch = request('branch_id');
                    if (!empty($reqBranch) && $reqBranch !== 'all' && $allBranches->contains('id', (int)$reqBranch)) {
                        $activeBranchId = (int) $reqBranch;
                        session(['active_branch_id' => $activeBranchId]);
                    } else {
                        $activeBranchId = session('active_branch_id');
                        if (!$activeBranchId || !$allBranches->contains('id', (int)$activeBranchId)) {
                            $activeBranchId = $allBranches->firstWhere('id', 3)?->id ?? $allBranches->first()?->id ?? 3;
                            session(['active_branch_id' => $activeBranchId]);
                        }
                    }
                }
                $activeBranchObj = $allBranches->firstWhere('id', (int)$activeBranchId) ?? $allBranches->first();
            @endphp
            <li class="nav-item d-flex align-items-center mr-2" id="top-navbar-branch-wrapper">
                <span class="badge badge-primary px-2 py-1 mr-1 font-weight-bold" style="font-size: 0.85rem;">
                    <i class="fas fa-store mr-1"></i> Branch:
                </span>
                @if ($user->branch_id !== null)
                    <span class="badge badge-light border px-2 py-1 font-weight-bold text-dark" style="font-size: 0.85rem;">
                        {{ $user->branch?->name ?? ($activeBranchObj?->name ?? 'Assigned Branch') }}
                    </span>
                @else
                    <select id="top-navbar-branch-select" class="form-control form-control-sm font-weight-bold text-dark border-primary shadow-sm" style="width: auto; height: calc(1.5em + .5rem + 2px); min-width: 190px;">
                        @foreach ($allBranches as $b)
                            <option value="{{ $b->id }}" {{ (int)$activeBranchId === (int)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                @endif
            </li>
            <li class="nav-item d-flex align-items-center mr-2" id="top-navbar-health-wrapper">
                <a href="{{ route('tools.system-health.index') }}" class="badge badge-success px-2 py-1 font-weight-bold text-white shadow-sm d-flex align-items-center text-decoration-none" title="Live System Health & 24/7 Monitor (Click to View Diagnostics)" style="font-size: 0.82rem; height: calc(1.5em + .5rem + 2px);">
                    <span class="mr-1 text-white" style="animation: pulse 1.5s infinite; font-size: 0.7rem;">&#9679;</span>
                    <i class="fas fa-heartbeat mr-1"></i> 100% Healthy
                </a>
            </li>
            {{-- Modern Table Density Switcher --}}
            <li class="nav-item d-flex align-items-center mr-2" id="top-navbar-density-wrapper">
                <button type="button" id="btn-toggle-table-density" class="btn btn-sm btn-light border px-2 py-1 shadow-xs text-secondary d-flex align-items-center" title="Toggle Table Density (Compact / Comfortable - Shift+Alt+C)" style="height: calc(1.5em + .5rem + 2px);">
                    <i class="fas fa-compress-arrows-alt" id="table-density-icon"></i>
                </button>
            </li>
            {{-- Dark / Light Theme Toggle (Commented out per user request - to be refined later)
            <li class="nav-item d-flex align-items-center mr-2" id="top-navbar-theme-wrapper">
                <button type="button" id="btn-toggle-theme-mode" class="btn btn-sm btn-light border px-2 py-1 shadow-xs text-secondary d-flex align-items-center" title="Toggle Dark/Light Mode (Shift+Alt+D)" style="height: calc(1.5em + .5rem + 2px);">
                    <i class="fas fa-moon" id="theme-mode-icon"></i>
                </button>
            </li>
            --}}
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

        {{-- Global Back Button in Top Right Corner --}}
        <li class="nav-item ml-2">
            <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold shadow-sm d-flex align-items-center my-1 mr-2 px-2" onclick="if(window.history.length > 1){ window.history.back(); } else { window.location.href='{{ url('/') }}'; }" title="Go Back">
                <i class="fas fa-arrow-left mr-1"></i> <span>Back</span>
            </button>
        </li>
    </ul>

</nav>
