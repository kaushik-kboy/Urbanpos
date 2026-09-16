<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class MasterAuxController extends Controller
{
    public function renderModule(Request $request, string $module)
    {
        if ($module === 'item-ean-upc-entry') {
            return $this->itemEanUpcEntry($request);
        }
        $configs = [
            'item-property-setting' => [
                'title' => 'Item Property Setting',
                'section' => 'Master > Item',
                'icon' => 'fas fa-sliders-h',
                'newButton' => 'Add Property Setting',
                'columns' => ['Property Name', 'Field Type', 'Mandatory', 'Category Scope', 'Status'],
            ],
            'item-ean-upc-entry' => [
                'title' => 'Item EAN/UPC Entry (Multiple Barcodes)',
                'section' => 'Master > Item',
                'icon' => 'fas fa-barcode',
                'newButton' => 'Add Barcode Mapping',
                'columns' => ['Item Code', 'Item Name', 'EAN/UPC Barcode', 'Pack Size', 'Status'],
            ],
            'assembly' => [
                'title' => 'Assembly Master',
                'section' => 'Master > Item',
                'icon' => 'fas fa-cogs',
                'newButton' => 'Create Assembly',
                'columns' => ['Assembly Code', 'Assembly Name', 'Components Count', 'Total Cost', 'Status'],
            ],
            'kit-mapping' => [
                'title' => 'Kit Mapping Master',
                'section' => 'Master > Item',
                'icon' => 'fas fa-boxes',
                'newButton' => 'New Kit Mapping',
                'columns' => ['Kit Item', 'Component Item', 'Quantity Required', 'Cost Share %', 'Status'],
            ],
            'uom-vs-item-mapping' => [
                'title' => 'UOM Vs Item Mapping',
                'section' => 'Master > Item',
                'icon' => 'fas fa-balance-scale',
                'newButton' => 'Add UOM Mapping',
                'columns' => ['Item Name', 'Base UOM', 'Alternate UOM', 'Conversion Factor', 'Status'],
            ],
            'tax-slab' => [
                'title' => 'Tax Slab Master',
                'section' => 'Master > Item',
                'icon' => 'fas fa-percentage',
                'newButton' => 'New Tax Slab',
                'columns' => ['Slab Name', 'Threshold From', 'Threshold To', 'GST Rate %', 'Status'],
            ],
            'loyalty-program-info' => [
                'title' => 'Loyalty Program Info',
                'section' => 'Master > Customer',
                'icon' => 'fas fa-award',
                'newButton' => 'Create Loyalty Tier',
                'columns' => ['Tier Name', 'Min Spend (₹)', 'Points per ₹100', 'Point Value (₹)', 'Status'],
            ],
            'loyalty-points-update' => [
                'title' => 'Loyalty Points Update',
                'section' => 'Master > Customer',
                'icon' => 'fas fa-coins',
                'newButton' => 'Adjust Points',
                'columns' => ['Customer Name', 'Mobile', 'Current Points', 'Delta Points', 'Updated By', 'Date'],
            ],
            'gstno-restriction' => [
                'title' => 'GSTNo Restriction Master',
                'section' => 'Master > Tax',
                'icon' => 'fas fa-shield-alt',
                'newButton' => 'Add GST Restriction',
                'columns' => ['State Code', 'State Name', 'Regex Pattern', 'Validation Rule', 'Status'],
            ],
            'distribution-centre-mapping' => [
                'title' => 'Distribution Centre Mapping',
                'section' => 'Master > Branch',
                'icon' => 'fas fa-warehouse',
                'newButton' => 'Map DC to Branch',
                'columns' => ['DC Name', 'Mapped Branch', 'Priority', 'Distance (km)', 'Status'],
            ],
            'promotion-management' => [
                'title' => 'Promotion Management',
                'section' => 'Master > Promotion',
                'icon' => 'fas fa-bullhorn',
                'newButton' => 'New Promotion Offer',
                'columns' => ['Promotion Title', 'Offer Type', 'Valid From', 'Valid Till', 'Discount Rule', 'Status'],
            ],
            'master-configuration' => [
                'title' => 'Master Configuration',
                'section' => 'Master > More > Tools',
                'icon' => 'fas fa-cog',
                'newButton' => 'Add Config Key',
                'columns' => ['Config Name', 'Category', 'Default Value', 'Current Value', 'Last Modified'],
            ],
            'unicode-master' => [
                'title' => 'Unicode Master',
                'section' => 'Master > More > Tools',
                'icon' => 'fas fa-language',
                'newButton' => 'Add Unicode Text',
                'columns' => ['English Text', 'Unicode / Regional Script', 'Module Scope', 'Status'],
            ],
            'master-attributes' => [
                'title' => 'Master Attributes',
                'section' => 'Master > More > Tools',
                'icon' => 'fas fa-tags',
                'newButton' => 'Create Attribute',
                'columns' => ['Attribute Name', 'Attribute Type', 'Applicable Entity', 'Options List', 'Status'],
            ],
            'addon-devices' => [
                'title' => 'Addon Devices Inactivation',
                'section' => 'Master > More > Tools',
                'icon' => 'fas fa-desktop',
                'newButton' => 'Register Device',
                'columns' => ['Device Identifier', 'Counter Name', 'Device Type', 'Status', 'Last Ping'],
            ],
            'transporters' => [
                'title' => 'Transporter Master',
                'section' => 'Master > More > Utility',
                'icon' => 'fas fa-shipping-fast',
                'newButton' => 'New Transporter',
                'columns' => ['Transporter Name', 'Transporter ID / GSTIN', 'Vehicle No', 'Contact Phone', 'Status'],
            ],
            'freight-settings' => [
                'title' => 'Freight Settings',
                'section' => 'Master > More > Utility',
                'icon' => 'fas fa-truck-loading',
                'newButton' => 'Add Freight Rule',
                'columns' => ['Route / Zone', 'Min Weight (KG)', 'Max Weight (KG)', 'Rate per KG (₹)', 'Status'],
            ],
        ];

        $config = $configs[$module] ?? [
            'title' => ucwords(str_replace('-', ' ', $module)),
            'section' => 'Master',
            'icon' => 'fas fa-layer-group',
            'newButton' => 'New Entry',
            'columns' => ['Code', 'Name', 'Description', 'Status'],
        ];

        return view('common.module-view', $config);
    }

    public function itemEanUpcEntry(Request $request)
    {
        $query = Item::query()->with(['brand', 'departmentValue', 'categoryValue']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%")
                  ->orWhere('ean_upc_code', 'like', "%{$search}%")
                  ->orWhere('alias', 'like', "%{$search}%");
            });
        }

        $eanFilter = $request->input('ean_filter', 'all');
        if ($eanFilter === 'with_ean') {
            $query->whereNotNull('ean_upc_code')->where('ean_upc_code', '!=', '');
        } elseif ($eanFilter === 'missing_ean') {
            $query->where(function ($q) {
                $q->whereNull('ean_upc_code')->orWhere('ean_upc_code', '');
            });
        }

        $statusFilter = $request->input('status', 'all');
        if ($statusFilter === 'active') {
            $query->where('status', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('status', false);
        }

        $totalCount = Item::count();
        $withEanCount = Item::whereNotNull('ean_upc_code')->where('ean_upc_code', '!=', '')->count();
        $missingEanCount = $totalCount - $withEanCount;

        $items = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('master.item-ean-upc', compact(
            'items',
            'search',
            'eanFilter',
            'statusFilter',
            'totalCount',
            'withEanCount',
            'missingEanCount'
        ));
    }

    public function updateItemEanUpc(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'ean_upc_code' => ['nullable', 'string', 'max:50'],
        ]);

        $item = Item::findOrFail($request->item_id);
        $eanCode = trim((string) $request->ean_upc_code);

        if ($eanCode !== '') {
            $duplicate = Item::where('ean_upc_code', $eanCode)
                ->where('id', '!=', $item->id)
                ->first();

            if ($duplicate) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => "This barcode '{$eanCode}' is already assigned to item: {$duplicate->name} (Code: {$duplicate->item_code}).",
                    ], 422);
                }
                return back()->withErrors(['ean_upc_code' => "This barcode '{$eanCode}' is already assigned to {$duplicate->name}."]);
            }
        }

        $item->ean_upc_code = $eanCode !== '' ? $eanCode : null;
        $item->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Barcode updated successfully for {$item->name}!",
                'ean_upc_code' => $item->ean_upc_code ?? '',
            ]);
        }

        return back()->with('status', "Barcode updated successfully for {$item->name}.");
    }
}
