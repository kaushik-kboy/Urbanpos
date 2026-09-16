<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan - {{ $salesDeliveryNote->delivery_number }}</title>
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
            Print Delivery Challan
        </button>
    </div>

    <table class="header-table" style="margin-bottom: 15px;">
        <tr>
            <td style="width: 60%;">
                <div class="header-title">URBAN PETS POS</div>
                <div><strong>Dispatch Branch:</strong> {{ $salesDeliveryNote->branch?->name }}</div>
                <div>{{ $salesDeliveryNote->branch?->address }}</div>
                @if ($salesDeliveryNote->branch?->phone)
                    <div>Phone: {{ $salesDeliveryNote->branch->phone }}</div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <h2 style="margin: 0 0 5px 0; color: #333;">DELIVERY CHALLAN</h2>
                <div style="font-size: 11px; color: #666; margin-bottom: 4px;">(Issued under GST Rule 55 / Transport Gate Pass)</div>
                <div><strong>Challan No:</strong> {{ $salesDeliveryNote->delivery_number }}</div>
                <div><strong>Dispatch Date:</strong> {{ optional($salesDeliveryNote->delivery_date)->format('d-M-Y') }}</div>
                <div><strong>Status:</strong> {{ strtoupper($salesDeliveryNote->status) }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table border-box" style="margin-bottom: 15px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border-right: 1px solid #ccc; padding-right: 10px;">
                <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px;">CONSIGNEE / CUSTOMER DETAILS:</div>
                <div class="font-bold">{{ $salesDeliveryNote->customer?->name }}</div>
                <div>Phone: {{ $salesDeliveryNote->customer?->phone ?: '—' }}</div>
                <div>GSTIN: {{ $salesDeliveryNote->customer?->gstin ?: 'Unregistered' }}</div>
                <div><strong>Delivery Address:</strong> {{ $salesDeliveryNote->delivery_address ?: ($salesDeliveryNote->customer?->address ?: 'As per invoice') }}</div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 15px;">
                <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px;">TRANSPORT & DISPATCH DETAILS:</div>
                <div><strong>Vehicle No:</strong> {{ $salesDeliveryNote->vehicle_no ?: '—' }}</div>
                <div><strong>Transporter:</strong> {{ $salesDeliveryNote->transporter_name ?: '—' }}</div>
                <div><strong>LR / Bilty No:</strong> {{ $salesDeliveryNote->lr_no ?: '—' }}</div>
                <div><strong>LR Date:</strong> {{ optional($salesDeliveryNote->lr_date)->format('d-M-Y') ?: '—' }}</div>
                <div><strong>Ref Sales Order:</strong> {{ $salesDeliveryNote->salesOrder?->order_number ?: 'Direct Dispatch' }}</div>
                @if ($salesDeliveryNote->reference_no)
                    <div><strong>Customer Ref:</strong> {{ $salesDeliveryNote->reference_no }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items-table" style="margin-bottom: 15px;">
        <thead>
            <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th class="text-left">Item Description</th>
                <th style="width: 70px;" class="text-center">Batch</th>
                <th style="width: 80px;" class="text-center">Exp Date</th>
                <th style="width: 90px;" class="text-right">Ordered</th>
                <th style="width: 90px;" class="text-right">Dispatched</th>
                <th style="width: 90px;" class="text-right">Rate (₹)</th>
                <th style="width: 100px;" class="text-right">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salesDeliveryNote->items as $idx => $line)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-left">
                        <div class="font-bold">{{ $line->item?->name }}</div>
                        @if ($line->item?->item_code)
                            <div style="font-size: 11px; color: #555;">Code: {{ $line->item->item_code }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ $line->batch_no ?: '—' }}</td>
                    <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                    <td class="text-right">{{ number_format($line->ordered_qty, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($line->dispatched_qty, 2) }}</td>
                    <td class="text-right">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f9f9f9; font-weight: bold;">
                <td colspan="4" class="text-right">Totals:</td>
                <td class="text-right">{{ number_format($salesDeliveryNote->total_ordered_qty, 2) }}</td>
                <td class="text-right">{{ number_format($salesDeliveryNote->total_dispatched_qty, 2) }}</td>
                <td class="text-right">Total Valuation:</td>
                <td class="text-right">₹{{ number_format($salesDeliveryNote->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($salesDeliveryNote->remarks)
        <div style="margin-bottom: 20px; font-size: 12px;">
            <strong>Remarks / Instructions:</strong> {{ $salesDeliveryNote->remarks }}
        </div>
    @endif

    <div style="font-size: 11px; color: #666; margin-top: 15px;">
        <em>Declaration: This delivery challan is issued for movement of goods for transportation/delivery. The goods dispatched above are covered under relevant GST provisions. Tax invoice shall follow separately.</em>
    </div>

    <div class="sign-box">
        <div class="sign-line">
            Receiver's Signature & Stamp
        </div>
        <div class="sign-line">
            Transporter / Driver's Signature
        </div>
        <div class="sign-line">
            For URBAN PETS POS<br>(Authorized Signatory)
        </div>
    </div>
</body>
</html>
