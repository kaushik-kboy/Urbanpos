<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MasterAuxController extends Controller
{
    public function renderModule(Request $request, string $module)
    {
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
}
