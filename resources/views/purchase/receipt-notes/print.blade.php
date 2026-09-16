<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goods Receipt Note - {{ $purchaseReceiptNote->receipt_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #222;
            margin: 20px;
            line-height: 1.4;
        }
        .header-table, .meta-table, .items-table, .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .border-box {
            border: 1px solid #333;
            padding: 10px;
            margin-bottom: 15px;
        }
        .items-table th, .items-table td {
            border: 1px solid #333;
            padding: 6px 8px;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .sign-box {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .sign-line {
            width: 220px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
            font-size: 12px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            Print Receipt Note
        </button>
    </div>

    <table class="header-table" style="margin-bottom: 15px;">
        <tr>
            <td style="width: 60%;">
                <div class="header-title">URBAN PETS POS</div>
                <div><strong>Branch:</strong> {{ $purchaseReceiptNote->branch?->name }}</div>
                <div>{{ $purchaseReceiptNote->branch?->address }}</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <h2 style="margin: 0 0 5px 0; color: #333;">GOODS RECEIPT NOTE</h2>
                <div><strong>GRN No:</strong> {{ $purchaseReceiptNote->receipt_number }}</div>
                <div><strong>Date:</strong> {{ $purchaseReceiptNote->receipt_date->format('d-M-Y') }}</div>
                <div><strong>Status:</strong> {{ strtoupper($purchaseReceiptNote->status) }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table border-box" style="margin-bottom: 15px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border-right: 1px solid #ccc; padding-right: 10px;">
                <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px;">SUPPLIER DETAILS:</div>
                <div class="font-bold">{{ $purchaseReceiptNote->supplier?->name }}</div>
                <div>Phone: {{ $purchaseReceiptNote->supplier?->phone ?: '—' }}</div>
                <div>GSTIN: {{ $purchaseReceiptNote->supplier?->gstin ?: '—' }}</div>
                <div>{{ $purchaseReceiptNote->supplier?->address }}</div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 15px;">
                <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px;">CHALLAN & TRANSPORT DETAILS:</div>
                <div><strong>Supplier Challan No:</strong> {{ $purchaseReceiptNote->supplier_challan_no ?: '—' }}</div>
                <div><strong>Challan Date:</strong> {{ optional($purchaseReceiptNote->supplier_challan_date)->format('d-M-Y') ?: '—' }}</div>
                <div><strong>Ref Purchase Order:</strong> {{ $purchaseReceiptNote->purchaseOrder?->po_number ?: 'Direct (No PO)' }}</div>
                <div><strong>Vehicle No:</strong> {{ $purchaseReceiptNote->vehicle_no ?: '—' }}</div>
                <div><strong>Transporter:</strong> {{ $purchaseReceiptNote->transporter_name ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="items-table" style="margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">#</th>
                <th class="text-left">Item Description</th>
                <th style="width: 70px;" class="text-center">Ordered</th>
                <th style="width: 70px;" class="text-center">Received</th>
                <th style="width: 70px;" class="text-center">Accepted</th>
                <th style="width: 60px;" class="text-center">Rejected</th>
                <th style="width: 80px;" class="text-right">Unit Cost (₹)</th>
                <th style="width: 80px;" class="text-right">MRP (₹)</th>
                <th style="width: 75px;" class="text-center">Batch</th>
                <th style="width: 80px;" class="text-center">Expiry</th>
                <th style="width: 90px;" class="text-right">Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseReceiptNote->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->item?->name }}</strong>
                        @if ($item->item?->code) [{{ $item->item->code }}] @endif
                    </td>
                    <td class="text-center">{{ number_format($item->ordered_qty, 2) }}</td>
                    <td class="text-center font-bold">{{ number_format($item->received_qty, 2) }}</td>
                    <td class="text-center font-bold">{{ number_format($item->accepted_qty, 2) }}</td>
                    <td class="text-center">{{ number_format($item->rejected_qty, 2) }}</td>
                    <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                    <td class="text-right">{{ $item->mrp ? number_format($item->mrp, 2) : '—' }}</td>
                    <td class="text-center">{{ $item->batch_no ?: '—' }}</td>
                    <td class="text-center">{{ optional($item->exp_date)->format('d-m-Y') ?: '—' }}</td>
                    <td class="text-right font-bold">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-bold">
                <td colspan="2" class="text-right">Totals:</td>
                <td class="text-center">{{ number_format($purchaseReceiptNote->total_ordered_qty, 2) }}</td>
                <td class="text-center">{{ number_format($purchaseReceiptNote->total_received_qty, 2) }}</td>
                <td class="text-center">{{ number_format($purchaseReceiptNote->total_accepted_qty, 2) }}</td>
                <td class="text-center">{{ number_format($purchaseReceiptNote->total_rejected_qty, 2) }}</td>
                <td colspan="4" class="text-right">Grand Total:</td>
                <td class="text-right">₹{{ number_format($purchaseReceiptNote->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($purchaseReceiptNote->remarks)
        <div style="margin-bottom: 25px;">
            <strong>Remarks / Inspection Notes:</strong> {{ $purchaseReceiptNote->remarks }}
        </div>
    @endif

    <div class="sign-box" style="margin-top: 60px;">
        <div class="sign-line">
            Received & Inspected By (Storekeeper)
        </div>
        <div class="sign-line">
            Delivered By (Driver / Transporter)
        </div>
        <div class="sign-line">
            Verified By (Store Manager)
        </div>
    </div>
</body>
</html>
