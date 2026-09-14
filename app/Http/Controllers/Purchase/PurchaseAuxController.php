<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PurchaseAuxController extends Controller
{
    public function renderModule(Request $request, string $module)
    {
        $configs = [
            'receipt-notes' => [
                'title' => 'Receipt Note (GRN)',
                'section' => 'Purchase',
                'icon' => 'fas fa-receipt',
                'newButton' => 'Create Receipt Note',
                'columns' => ['RN Number', 'Date', 'Supplier Name', 'Ref PO No', 'Received Qty', 'Status'],
            ],
            'purchase-returns' => [
                'title' => 'Purchase Return',
                'section' => 'Purchase',
                'icon' => 'fas fa-undo-alt',
                'newButton' => 'New Purchase Return',
                'columns' => ['PR Number', 'Date', 'Supplier Name', 'Ref Invoice No', 'Net Return Amount (₹)', 'Status'],
            ],
            'indents' => [
                'title' => 'Branch Indent Request',
                'section' => 'Purchase',
                'icon' => 'fas fa-clipboard-list',
                'newButton' => 'Create Indent',
                'columns' => ['Indent No', 'Date', 'Requesting Branch', 'Fulfilling DC', 'Items Count', 'Status'],
            ],
            'auto-indent' => [
                'title' => 'Auto Indent Generation',
                'section' => 'Purchase > More',
                'icon' => 'fas fa-robot',
                'newButton' => 'Run Auto Indent',
                'columns' => ['Rule Name', 'Branch', 'Replenishment Formula', 'Trigger Level', 'Last Generated', 'Status'],
            ],
            'indent-cancellation' => [
                'title' => 'Indent Cancellation',
                'section' => 'Purchase > More',
                'icon' => 'fas fa-calendar-times',
                'newButton' => 'Cancel Indent',
                'columns' => ['Indent No', 'Date', 'Branch', 'Reason for Cancellation', 'Cancelled By'],
            ],
            'transfer-in-touch' => [
                'title' => 'Transfer In Touch Screen',
                'section' => 'Purchase > More',
                'icon' => 'fas fa-tablet-alt',
                'newButton' => 'Quick Receive',
                'columns' => ['TO Ref', 'Source Branch', 'Dispatched Qty', 'Scanned Qty', 'Pending Qty', 'Status'],
            ],
            'indent-cutoff' => [
                'title' => 'Indent CutOff Time Configuration',
                'section' => 'Purchase > More',
                'icon' => 'fas fa-stopwatch',
                'newButton' => 'Set CutOff Rule',
                'columns' => ['Branch Name', 'CutOff Day', 'CutOff Time (HH:MM)', 'Auto Approval', 'Status'],
            ],
        ];

        $config = $configs[$module] ?? [
            'title' => ucwords(str_replace('-', ' ', $module)),
            'section' => 'Purchase',
            'icon' => 'fas fa-layer-group',
            'newButton' => 'New Entry',
            'columns' => ['Ref No', 'Date', 'Supplier / Source', 'Status', 'Total'],
        ];

        return view('common.module-view', $config);
    }
}
