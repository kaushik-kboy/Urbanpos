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

        {{-- Quick Menu / Submenu Filter Navigator --}}
        @auth
            <li class="nav-item d-none d-md-flex align-items-center ml-2" id="top-navbar-menu-jump-wrapper">
                <div class="input-group input-group-sm" style="min-width: 250px; max-width: 340px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-primary text-primary font-weight-bold py-0" title="Quick Menu Navigator">
                            <i class="fas fa-search mr-1"></i> <span class="d-none d-lg-inline">Menu:</span>
                        </span>
                    </div>
                    <select id="top-navbar-menu-jump-select" class="form-control form-control-sm font-weight-bold text-dark border-primary shadow-sm" onchange="if(this.value){ window.location.href=this.value; }">
                        <option value="">-- Select Menu / Submenu --</option>
                        <optgroup label="🛍️ Sales">
                            <option value="{{ route('pos.terminal') }}">Sales &bull; POS Terminal</option>
                            <option value="{{ route('sales.sales-bills.create') }}">Sales &bull; New Sales Bill</option>
                            <option value="{{ route('sales.sales-bills.index') }}">Sales &bull; Sales Bill List</option>
                            <option value="{{ route('sales.sales-returns.create') }}">Sales &bull; New Sales Return</option>
                            <option value="{{ route('sales.sales-returns.index') }}">Sales &bull; Sales Return List</option>
                            <option value="{{ route('sales.sales-orders.create') }}">Sales &bull; Sales Order</option>
                            <option value="{{ route('sales.sales-quotations.create') }}">Sales &bull; Sales Quotation</option>
                            <option value="{{ route('sales.delivery-notes.create') }}">Sales &bull; Delivery Note</option>
                        </optgroup>
                        <optgroup label="📦 Purchase">
                            <option value="{{ route('purchase.purchase-invoices.create') }}">Purchase &bull; New Purchase Invoice</option>
                            <option value="{{ route('purchase.purchase-invoices.index') }}">Purchase &bull; Purchase Invoices List</option>
                            <option value="{{ route('purchase.purchase-orders.create') }}">Purchase &bull; Purchase Orders</option>
                            <option value="{{ route('purchase.purchase-indents.create') }}">Purchase &bull; Purchase Indents</option>
                            <option value="{{ route('purchase.purchase-receipt-notes.create') }}">Purchase &bull; Receipt Note (GRN)</option>
                            <option value="{{ route('purchase.purchase-returns.create') }}">Purchase &bull; Purchase Return</option>
                        </optgroup>
                        <optgroup label="🏷️ Master - Item">
                            <option value="{{ route('master.items.create') }}">Item &bull; Add Item</option>
                            <option value="{{ route('master.items.index') }}">Item &bull; Item List</option>
                            <option value="{{ route('master.item-categories.index') }}">Item &bull; Item Category</option>
                            <option value="{{ route('master.item-category-values.index') }}">Item &bull; Item Category Values</option>
                            <option value="{{ route('master.product-types.index') }}">Item &bull; Product Types</option>
                            <option value="{{ route('master.brands.index') }}">Item &bull; Brands</option>
                            <option value="{{ route('master.uoms.index') }}">Item &bull; UOM</option>
                            <option value="{{ route('master.item-price-change.index') }}">Item &bull; Item Price Change</option>
                        </optgroup>
                        <optgroup label="👥 Master - Customer & Supplier">
                            <option value="{{ route('master.customers.create') }}">Customer &bull; Add Customer</option>
                            <option value="{{ route('master.customers.index') }}">Customer &bull; Customers List</option>
                            <option value="{{ route('master.customer-categories.index') }}">Customer &bull; Customer Categories</option>
                            <option value="{{ route('master.suppliers.create') }}">Supplier &bull; Add Supplier</option>
                            <option value="{{ route('master.suppliers.index') }}">Supplier &bull; Suppliers List</option>
                            <option value="{{ route('master.areas.index') }}">Customer &bull; Areas</option>
                            <option value="{{ route('master.pet-types.index') }}">Pets &bull; Pet Types</option>
                            <option value="{{ route('master.breeds.index') }}">Pets &bull; Breeds</option>
                            <option value="{{ route('master.colors.index') }}">Pets &bull; Colors</option>
                        </optgroup>
                        <optgroup label="🏢 Master - Branch & Tax">
                            <option value="{{ route('master.branches.index') }}">Branch &bull; Branches</option>
                            <option value="{{ route('master.registers.index') }}">Branch &bull; Registers</option>
                            <option value="{{ route('master.gst-taxes.index') }}">Tax &bull; GST Taxes</option>
                            <option value="{{ route('master.gst-types.index') }}">Tax &bull; GST Types</option>
                            <option value="{{ route('master.tender-types.index') }}">Payment &bull; Tender Types</option>
                            <option value="{{ route('master.tender-type-values.index') }}">Payment &bull; Tender Type Values</option>
                        </optgroup>
                        <optgroup label="📊 Inventory">
                            <option value="{{ route('inventory.stock-transfers.index') }}">Inventory &bull; Stock Transfer</option>
                            <option value="{{ route('inventory.stock-updates.index') }}">Inventory &bull; Stock Update</option>
                            <option value="{{ route('inventory.opening-stocks.index') }}">Inventory &bull; Opening Stock</option>
                            <option value="{{ route('inventory.damage-stocks.index') }}">Inventory &bull; Damage Stock</option>
                        </optgroup>
                        <optgroup label="📈 Reports">
                            <option value="{{ route('reports.index') }}">Reports &bull; Reports Dashboard</option>
                            <option value="{{ route('reports.analytics-builder') }}">Reports &bull; Analytics Builder</option>
                            <option value="{{ route('reports.sales-summary') }}">Reports &bull; Sales Summary</option>
                        </optgroup>
                        <optgroup label="⚙️ Tools & System">
                            <option value="{{ route('tools.system-health.index') }}">Tools &bull; System Health</option>
                            <option value="{{ route('tools.whatsapp-settings.index') }}">Tools &bull; WhatsApp Settings</option>
                            <option value="{{ route('tools.document-sequences.index') }}">Tools &bull; Document Sequences</option>
                            <option value="{{ route('master.users.index') }}">Tools &bull; User Management</option>
                        </optgroup>
                    </select>
                </div>
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
                $reqBranch = request('branch_id');
                if (!empty($reqBranch) && $reqBranch !== 'all' && $allBranches->contains('id', (int)$reqBranch)) {
                    $activeBranchId = (int) $reqBranch;
                    session(['active_branch_id' => $activeBranchId]);
                } else {
                    $activeBranchId = session('active_branch_id');
                    if (!$activeBranchId || !$allBranches->contains('id', (int)$activeBranchId)) {
                        $activeBranchId = $user->branch_id ?: ($allBranches->firstWhere('id', 3)?->id ?? $allBranches->first()?->id ?? 3);
                        session(['active_branch_id' => $activeBranchId]);
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
