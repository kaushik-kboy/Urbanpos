<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchaseOrder->po_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 20px;
            line-height: 1.4;
        }
        .header-table, .meta-table, .items-table, .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .border-box {
            border: 1px solid #333;
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .items-table th, .items-table td {
            border: 1px solid #333;
            padding: 5px 6px;
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
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .sign-line {
            width: 200px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 5px;
            font-size: 11px;
        }
        .btn-print {
            padding: 7px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 15px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Purchase Order</button>
    </div>

    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="header-title">PURCHASE ORDER</div>
                <div style="font-size: 14px; font-weight: bold;">{{ $purchaseOrder->branch?->name ?? config('app.name', 'UrbanPOS') }}</div>
                @if($purchaseOrder->branch?->address)
                    <div>{{ $purchaseOrder->branch->address }}</div>
                @endif
                @if($purchaseOrder->branch?->phone)
                    <div>Phone: {{ $purchaseOrder->branch->phone }}</div>
                @endif
                @if($purchaseOrder->branch?->gst_no)
                    <div>GSTIN: <strong>{{ $purchaseOrder->branch->gst_no }}</strong></div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <table style="margin-left: auto; text-align: left;">
                    <tr>
                        <td class="font-bold" style="padding-right: 10px;">PO Number:</td>
                        <td><strong>{{ $purchaseOrder->po_number }}</strong></td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding-right: 10px;">PO Date:</td>
                        <td>{{ optional($purchaseOrder->po_date)->format('d-m-Y') }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold" style="padding-right: 10px;">Status:</td>
                        <td>{{ $purchaseOrder->status ?? 'Active' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="border-box">
        <table class="meta-table">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px;">VENDOR / SUPPLIER DETAILS:</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $purchaseOrder->supplier?->name }}</div>
                    @if($purchaseOrder->supplier?->address)
                        <div>{{ $purchaseOrder->supplier->address }}</div>
                    @endif
                    @if($purchaseOrder->supplier?->phone)
                        <div>Phone: {{ $purchaseOrder->supplier->phone }}</div>
                    @endif
                    @if($purchaseOrder->supplier?->gst_no)
                        <div>GSTIN: {{ $purchaseOrder->supplier->gst_no }}</div>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="font-bold" style="text-decoration: underline; margin-bottom: 4px;">SHIP TO / DELIVERY LOCATION:</div>
                    <div style="font-size: 13px; font-weight: bold;">{{ $purchaseOrder->branch?->name }}</div>
                    @if($purchaseOrder->branch?->address)
                        <div>{{ $purchaseOrder->branch->address }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table" style="margin-bottom: 12px;">
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">#</th>
                <th class="text-left">Item Description</th>
                <th class="text-left" style="width: 100px;">Item Code</th>
                <th class="text-right" style="width: 60px;">Qty</th>
                <th class="text-right" style="width: 50px;">Free</th>
                <th class="text-right" style="width: 80px;">Cost Rate</th>
                <th class="text-right" style="width: 60px;">GST %</th>
                <th class="text-right" style="width: 70px;">GST Amt</th>
                <th class="text-right" style="width: 90px;">Net Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $i => $row)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row->item?->name }}</td>
                    <td>{{ $row->item?->item_code ?? $row->item?->ean_upc_code ?? '-' }}</td>
                    <td class="text-right">{{ number_format($row->qty, 2) }}</td>
                    <td class="text-right">{{ $row->free_qty > 0 ? number_format($row->free_qty, 2) : '-' }}</td>
                    <td class="text-right">{{ number_format($row->cost_price, 2) }}</td>
                    <td class="text-right">{{ number_format($row->gst_percent, 2) }}%</td>
                    <td class="text-right">{{ number_format($row->gst_tax_amount, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($row->net_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-bold" style="background-color: #f9f9f9;">
                <td colspan="3" class="text-right">Totals:</td>
                <td class="text-right">{{ number_format($purchaseOrder->items->sum('qty'), 2) }}</td>
                <td class="text-right">{{ number_format($purchaseOrder->items->sum('free_qty'), 2) }}</td>
                <td colspan="2"></td>
                <td class="text-right">₹{{ number_format($purchaseOrder->total_gst, 2) }}</td>
                <td class="text-right">₹{{ number_format($purchaseOrder->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table" style="margin-bottom: 20px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if($purchaseOrder->remarks)
                    <div style="font-size: 11px;"><strong>Remarks:</strong> {{ $purchaseOrder->remarks }}</div>
                @endif
                @if($purchaseOrder->message)
                    <div style="font-size: 11px;"><strong>Message to Vendor:</strong> {{ $purchaseOrder->message }}</div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 3px 0;">Total Tax (GST):</td>
                        <td class="text-right" style="padding: 3px 0;">₹{{ number_format((float)($purchaseOrder->total_gst ?? 0), 2) }}</td>
                    </tr>
                    <tr style="border-top: 1px solid #333; font-size: 14px;" class="font-bold">
                        <td style="padding: 5px 0;">Grand Total:</td>
                        <td class="text-right" style="padding: 5px 0;">₹{{ number_format((float)($purchaseOrder->total ?? 0), 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="sign-box">
        <div class="sign-line">Prepared By</div>
        <div class="sign-line">Verified By</div>
        <div class="sign-line">Authorised Signatory</div>
    </div>

</body>
</html>
