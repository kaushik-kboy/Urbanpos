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
        {!! $receiptSettings->custom_css ?? '' !!}
    </style>
</head>
@php
    $receiptSettings = \App\Models\ReceiptSetting::forDocument('stock_transfer');
@endphp
<body>
    <div class="no-print" style="text-align: right;">
        <button onclick="window.print()" class="btn-print">Print Transfer Note</button>
    </div>

    <table class="header-table" style="margin-bottom: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if($receiptSettings->show_logo && $receiptSettings->logo_path)
                    <img src="{{ asset($receiptSettings->logo_path) }}" alt="{{ $receiptSettings->store_name }}" style="max-width: {{ $receiptSettings->logo_width ?? 120 }}px; height: auto; margin-bottom: 6px;"><br>
                @endif
                <div class="header-title">STOCK TRANSFER NOTE</div>
                <div class="font-bold" style="font-size: 14px;">{{ $receiptSettings->store_name ?: config('app.name', 'UrbanPOS') }}</div>
                @if($receiptSettings->tagline)
                    <div style="font-size: 11px; color: #555; font-weight: bold;">{{ $receiptSettings->tagline }}</div>
                @endif
                @if($receiptSettings->header_address)
                    <div style="font-size: 11px;">{!! nl2br(e($receiptSettings->header_address)) !!}</div>
                @endif
                @if($receiptSettings->phone)
                    <div style="font-size: 11px;">Tel: {{ $receiptSettings->phone }}</div>
                @endif
                @if($receiptSettings->gstin)
                    <div style="font-size: 11px; font-weight: bold;">GSTIN: {{ $receiptSettings->gstin }}</div>
                @endif
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

    @if($receiptSettings->footer_policy || $receiptSettings->footer_note)
        <div style="margin-top: 25px; font-size: 11px; color: #444; border-top: 1px dashed #999; padding-top: 10px; text-align: center;">
            @if($receiptSettings->footer_policy)
                <div style="white-space: pre-line; margin-bottom: 4px;">{!! nl2br(e($receiptSettings->footer_policy)) !!}</div>
            @endif
            @if($receiptSettings->footer_note)
                <div style="font-weight: bold; white-space: pre-line;">{!! nl2br(e($receiptSettings->footer_note)) !!}</div>
            @endif
        </div>
    @endif

    @if($receiptSettings->show_barcode)
        <div style="text-align: center; margin-top: 20px;">
            <div style="display: inline-block; height: 32px; width: 180px; background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
            <div style="font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-top: 2px;">* {{ $stockTransfer->transfer_number }} *</div>
        </div>
    @endif
</body>
</html>
