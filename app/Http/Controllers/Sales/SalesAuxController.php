<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesAuxController extends Controller
{
    public function renderModule(Request $request, string $module)
    {
        $configs = [
            'quotations' => [
                'title' => 'Sales Quotation',
                'section' => 'Sales',
                'icon' => 'fas fa-file-signature',
                'newButton' => 'New Quotation',
                'columns' => ['Quotation No', 'Date', 'Customer Name', 'Items Qty', 'Net Amount (₹)', 'Status'],
            ],
            'orders' => [
                'title' => 'Sales Order',
                'section' => 'Sales',
                'icon' => 'fas fa-shopping-basket',
                'newButton' => 'New Sales Order',
                'columns' => ['Order No', 'Order Date', 'Customer Name', 'Delivery Date', 'Total Amount (₹)', 'Status'],
            ],
            'order-approval' => [
                'title' => 'Sales Order Approval',
                'section' => 'Sales',
                'icon' => 'fas fa-user-check',
                'newButton' => null,
                'columns' => ['Order No', 'Date', 'Customer', 'Credit Limit', 'Order Amount (₹)', 'Approval Status'],
            ],
            'delivery-notes' => [
                'title' => 'Delivery Note',
                'section' => 'Sales',
                'icon' => 'fas fa-truck',
                'newButton' => 'New Delivery Note',
                'columns' => ['DN Number', 'Date', 'Customer / Recipient', 'Transport Details', 'Total Qty', 'Status'],
            ],
            'delivery-note-returns' => [
                'title' => 'Delivery Note Return',
                'section' => 'Sales',
                'icon' => 'fas fa-truck-loading',
                'newButton' => 'New DN Return',
                'columns' => ['DN Return No', 'Return Date', 'Ref DN No', 'Customer Name', 'Returned Qty', 'Status'],
            ],
            'transfer-out-approval' => [
                'title' => 'Transfer Out Approval & Auto TI',
                'section' => 'Sales > More',
                'icon' => 'fas fa-check-double',
                'newButton' => null,
                'columns' => ['TO Number', 'Transfer Date', 'Source Branch', 'Destination Branch', 'Items Qty', 'Approval Status'],
            ],
        ];

        $config = $configs[$module] ?? [
            'title' => ucwords(str_replace('-', ' ', $module)),
            'section' => 'Sales',
            'icon' => 'fas fa-layer-group',
            'newButton' => 'New Entry',
            'columns' => ['Ref No', 'Date', 'Customer / Party', 'Status', 'Total'],
        ];

        return view('common.module-view', $config);
    }
}
