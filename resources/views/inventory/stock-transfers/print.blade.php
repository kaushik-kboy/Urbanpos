<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer - {{ $stockTransfer->transfer_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 20px;
            line-height: 1.4;
        }
        .header-table, .meta-table, .items-table {
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
            .no-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right;">
        <button onclick="window.print()" class="btn-print">Print Transfer Note</button>
    </div>

    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="header-title">STOCK TRANSFER NOTE</div>
                <div class="font-bold" style="font-size: 14px;">{{ config('app.name', 'UrbanPOS') }}</div>
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div style="font-size: 14px; font-weight: bold;">Transfer #: {{ $stockTransfer->transfer_number }}</div>
                <div>Date: <strong>{{ $stockTransfer->transfer_date->format('d-m-Y') }}</strong></div>
                <div>Status: <strong>{{ $stockTransfer->status }}</strong></div>
            </td>
        </tr>
    </table>

    <div class="border-box">
        <table class="meta-table">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>From Branch (Dispatched):</strong><br>
                    <span class="font-bold">{{ $stockTransfer->fromBranch?->name }}</span><br>
                    {{ $stockTransfer->fromBranch?->address ?? '' }}<br>
                    @if($stockTransfer->fromBranch?->phone)Phone: {{ $stockTransfer->fromBranch->phone }}<br>@endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <strong>To Branch (Destination):</strong><br>
                    <span class="font-bold">{{ $stockTransfer->toBranch?->name }}</span><br>
                    {{ $stockTransfer->toBranch?->address ?? '' }}<br>
                    @if($stockTransfer->toBranch?->phone)Phone: {{ $stockTransfer->toBranch->phone }}<br>@endif
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 35px;">#</th>
                <th class="text-left" style="width: 100px;">Item Code</th>
                <th class="text-left">Item Description</th>
                <th class="text-center" style="width: 85px;">Exp Date</th>
                <th class="text-right" style="width: 80px;">Dispatched</th>
                <th class="text-right" style="width: 80px;">Received</th>
                <th class="text-right" style="width: 90px;">Unit Cost</th>
                <th class="text-right" style="width: 100px;">Total Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($stockTransfer->items as $idx => $line)
                @php
                    $lineTotal = (float)$line->qty * (float)$line->unit_cost;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                    <td class="font-bold">{{ $line->item?->name }}</td>
                    <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                    <td class="text-right font-bold">{{ number_format($line->qty, 3) }}</td>
                    <td class="text-right">{{ $line->received_qty !== null ? number_format($line->received_qty, 3) : '—' }}</td>
                    <td class="text-right">₹{{ number_format($line->unit_cost, 2) }}</td>
                    <td class="text-right font-bold">₹{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f9f9f9; font-weight: bold;">
                <td colspan="4" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($stockTransfer->items->sum('qty'), 3) }}</td>
                <td class="text-right">{{ $stockTransfer->items->whereNotNull('received_qty')->isNotEmpty() ? number_format($stockTransfer->items->sum('received_qty'), 3) : '—' }}</td>
                <td></td>
                <td class="text-right">₹{{ number_format($stockTransfer->total_value, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if($stockTransfer->remarks)
        <div style="margin-top: 15px; border: 1px dashed #666; padding: 8px; font-size: 11px;">
            <strong>Remarks:</strong> {{ $stockTransfer->remarks }}
        </div>
    @endif

    <div class="sign-box">
        <div class="sign-line">Dispatched By (From Branch)</div>
        <div class="sign-line">Received By (To Branch)</div>
    </div>
</body>
</html>
